<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Cli;

use DesignsLabz\Relocate\Admin\Admin;
use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Installer;
use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobException;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Jobs\JobRunner;
use DesignsLabz\Relocate\Jobs\JobStarter;
use DesignsLabz\Relocate\Jobs\JobStatus;
use DesignsLabz\Relocate\Rest\JobFormatter;
use RuntimeException;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Search and replace with DesignsLabz Relocate.
 *
 * Jobs started here are the same jobs the admin screen runs: they appear in
 * its History, can be resumed from either place, and save the same file of
 * original values.
 */
final class Command {

	private const REPORT_FIELDS = array( 'table', 'rows_scanned', 'rows_changed', 'replacements', 'skipped', 'note' );

	// With a machine-readable --format, stdout carries only the report; errors and warnings go to stderr as always.
	private bool $quiet = false;

	public function __construct(
		private Installer $installer,
		private Schema $schema,
		private JobRepository $jobs,
		private JobRunner $runner,
		private JobStarter $starter,
		private JobFormatter $formatter
	) {}

	/**
	 * Replaces text or URLs across the database, without breaking serialized data or JSON.
	 *
	 * Always starts with a dry run and reports what would change. Without
	 * --dry-run it then asks for confirmation and applies exactly that dry run,
	 * saving the original value of everything it changes to a file.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : The text or URL to search for, matched exactly.
	 *
	 * <replace>
	 * : The replacement. Pass '' to remove the matches.
	 *
	 * [--tables=<tables>]
	 * : Comma-separated tables to search. Defaults to every table with the WordPress prefix.
	 *
	 * [--all-tables]
	 * : Search every table in the database, not only those with the WordPress prefix.
	 *
	 * [--dry-run]
	 * : Only report what would change.
	 *
	 * [--case-insensitive]
	 * : Ignore upper and lower case.
	 *
	 * [--whole-words]
	 * : Match whole words only.
	 *
	 * [--url-variants]
	 * : For a URL, also match its http:// and protocol-relative (//) versions.
	 *
	 * [--include-guids]
	 * : Also replace inside post GUIDs.
	 *
	 * [--skip-columns=<columns>]
	 * : Comma-separated table.column pairs to leave out, e.g. wp_options.option_value.
	 *
	 * [--[no-]before-image]
	 * : Save the original values of everything that changes to a file. On by default.
	 *
	 * [--format=<format>]
	 * : How to print the per-table report.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--yes]
	 * : Apply the replacement without asking.
	 *
	 * ## EXAMPLES
	 *
	 *     # See what would change.
	 *     $ wp dlz search-replace https://staging.example.com https://example.com --dry-run
	 *
	 *     # Replace across all WordPress tables, after confirming.
	 *     $ wp dlz search-replace https://staging.example.com https://example.com
	 *
	 *     # Replace in two tables without asking.
	 *     $ wp dlz search-replace "Old Name" "New Name" --tables=wp_posts,wp_postmeta --yes
	 *
	 * @subcommand search-replace
	 *
	 * @param array{0: string, 1: string} $args
	 * @param array<string, string|bool>  $assoc_args
	 */
	public function search_replace( array $args, array $assoc_args ): void {
		$this->installer->maybe_upgrade();

		[ $search, $replace ] = $args;
		$format               = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->quiet          = 'table' !== $format;

		try {
			$dry_run = $this->starter->dry_run(
				$search,
				$replace,
				array(
					'case_sensitive' => ! Utils\get_flag_value( $assoc_args, 'case-insensitive', false ),
					'whole_words'    => (bool) Utils\get_flag_value( $assoc_args, 'whole-words', false ),
					'url_variants'   => (bool) Utils\get_flag_value( $assoc_args, 'url-variants', false ),
					'skip_guids'     => ! Utils\get_flag_value( $assoc_args, 'include-guids', false ),
				),
				$this->tables( $assoc_args ),
				$this->skip_columns( $assoc_args )
			);
		} catch ( JobException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$dry_run = $this->run( $dry_run, sprintf( 'Dry run #%d', $dry_run->id ) );
		$this->report( $dry_run, $format );

		if ( JobStatus::Completed !== $dry_run->status ) {
			WP_CLI::error( sprintf( 'The dry run stopped: %s Continue it with: wp dlz resume %d', (string) $dry_run->error_message, $dry_run->id ) );
		}

		$totals = $dry_run->report->totals();

		if ( 0 === $totals['rows_changed'] ) {
			$this->success( 'Nothing to replace.' );
			return;
		}

		if ( Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			$this->success( 'Dry run complete. Nothing in the database was changed.' );
			return;
		}

		WP_CLI::confirm(
			sprintf( 'Write %1$s replacements in %2$s rows to the database?', number_format( $totals['replacements'] ), number_format( $totals['rows_changed'] ) ),
			$assoc_args
		);

		try {
			$live = $this->starter->replacement( $dry_run, (bool) Utils\get_flag_value( $assoc_args, 'before-image', true ) );
		} catch ( JobException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$this->finish_replacement( $this->run( $live, sprintf( 'Replacement #%d', $live->id ) ), $format );
	}

	/**
	 * Continues a job that stopped before it finished, or resumes a failed one.
	 *
	 * Picks up from the last completed batch. Batches of a live replacement are
	 * saved completely or not at all, so nothing is applied twice.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The job number, as shown in History.
	 *
	 * [--format=<format>]
	 * : How to print the per-table report.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp dlz resume 42
	 *
	 * @param array{0: string}           $args
	 * @param array<string, string|bool> $assoc_args
	 */
	public function resume( array $args, array $assoc_args ): void {
		$this->installer->maybe_upgrade();

		$format      = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->quiet = 'table' !== $format;
		$job         = $this->jobs->find( (int) $args[0] );

		if ( null === $job ) {
			WP_CLI::error( sprintf( 'Job %d does not exist.', (int) $args[0] ) );
		}

		if ( $job->status->is_finished() && JobStatus::Failed !== $job->status ) {
			WP_CLI::error( sprintf( 'Job %d has already finished (%s).', $job->id, $job->status->value ) );
		}

		try {
			$resumed = $this->runner->resume( $job );
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		if ( null === $resumed ) {
			WP_CLI::error( 'The job is being processed by another request right now.' );
		}

		$job = $this->run( $resumed, sprintf( 'Job #%d', $resumed->id ) );

		if ( $job->dry_run ) {
			$this->report( $job, $format );
			$this->success( sprintf( 'Dry run #%d finished (%s). Apply it from its page in History.', $job->id, $job->status->value ) );
			return;
		}

		$this->finish_replacement( $job, $format );
	}

	/**
	 * @param array<string, string|bool> $assoc_args
	 * @return array<string, list<string>> Table => columns.
	 */
	private function skip_columns( array $assoc_args ): array {
		$skip = array();

		foreach ( array_filter( array_map( 'trim', explode( ',', (string) Utils\get_flag_value( $assoc_args, 'skip-columns', '' ) ) ) ) as $pair ) {
			$dot = strrpos( $pair, '.' );

			if ( false === $dot ) {
				WP_CLI::error( sprintf( '"%s" is not a table.column pair.', $pair ) );
			}

			$skip[ substr( $pair, 0, $dot ) ][] = substr( $pair, $dot + 1 );
		}

		return $skip;
	}

	/**
	 * @param array<string, string|bool> $assoc_args
	 * @return list<string>
	 */
	private function tables( array $assoc_args ): array {
		$requested = (string) Utils\get_flag_value( $assoc_args, 'tables', '' );

		if ( '' !== $requested ) {
			return array_values( array_filter( array_map( 'trim', explode( ',', $requested ) ) ) );
		}

		try {
			$tables = $this->schema->searchable_tables();
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		if ( ! Utils\get_flag_value( $assoc_args, 'all-tables', false ) ) {
			$tables = array_filter( $tables, fn( $table ): bool => $table->prefixed );
		}

		return array_keys( $tables );
	}

	private function run( Job $job, string $label ): Job {
		$progress = $this->quiet ? new \WP_CLI\NoOp() : Utils\make_progress_bar( $label, 100 );
		$shown    = 0;

		while ( ! $job->status->is_finished() ) {
			try {
				$next = $this->runner->step( $job );
			} catch ( RuntimeException $e ) {
				WP_CLI::error( $e->getMessage() );
			}

			if ( null === $next ) {
				WP_CLI::error( sprintf( 'Job %d is being processed by another request right now.', $job->id ) );
			}

			$job = $next;

			$progress->tick( max( 0, $job->progress() - $shown ) );
			$shown = max( $shown, $job->progress() );
		}

		$progress->tick( 100 - $shown );
		$progress->finish();

		return $job;
	}

	private function finish_replacement( Job $job, string $format ): void {
		$this->report( $job, $format );

		$data = $this->formatter->format( $job );

		if ( $data['site_address_changed'] ) {
			WP_CLI::warning( 'The site address (siteurl and home) was changed. Anyone logged in will need to log in again at the new address.' );
		}

		if ( $job->before_image && ! $this->quiet ) {
			WP_CLI::log( sprintf( 'Original values saved. Download them from the job page: %s', Admin::job_url( $job->id ) ) );
		}

		match ( $job->status ) {
			JobStatus::Completed => $this->success( sprintf( 'Replacement #%d complete.', $job->id ) ),
			JobStatus::Cancelled => WP_CLI::warning( sprintf( 'Replacement #%d was cancelled. Batches finished before that were written.', $job->id ) ),
			default              => WP_CLI::error(
				sprintf(
					'Replacement #%1$d stopped: %2$s Batches finished before the error were written; the failed batch was rolled back. Continue with: wp dlz resume %1$d',
					$job->id,
					(string) $job->error_message
				)
			),
		};
	}

	private function report( Job $job, string $format ): void {
		$data = $this->formatter->format( $job );
		$rows = array_map(
			fn( array $table ): array => array(
				'table'        => $table['name'],
				'rows_scanned' => $table['rows_scanned'],
				'rows_changed' => $table['rows_changed'],
				'replacements' => $table['replacements'],
				'skipped'      => array_sum( array_column( $table['skipped'], 'count' ) ),
				'note'         => (string) $table['note'],
			),
			$data['report']['tables'] ?? array()
		);

		Utils\format_items( $format, $rows, self::REPORT_FIELDS );

		$totals = $job->report->totals();

		if ( $totals['skipped'] ) {
			WP_CLI::warning( sprintf( '%s matching values are left unchanged because changing them could corrupt data. The job page lists why.', number_format( $totals['skipped'] ) ) );
		}

		if ( $this->quiet ) {
			return;
		}

		WP_CLI::log(
			sprintf(
				'%1$s replacements in %2$s rows, %3$s rows scanned.',
				number_format( $totals['replacements'] ),
				number_format( $totals['rows_changed'] ),
				number_format( $totals['rows_scanned'] )
			)
		);
	}

	private function success( string $message ): void {
		if ( ! $this->quiet ) {
			WP_CLI::success( $message );
		}
	}
}
