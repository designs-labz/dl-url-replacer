<?php
/**
 * A single job: its details, results and log. Results are rendered by the same
 * script as the Search & Replace tab, which can also continue or apply the job.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

use DesignsLabz\Relocate\Admin\Admin;
use DesignsLabz\Relocate\Admin\LogTable;

defined( 'ABSPATH' ) || exit;

/** @var \DesignsLabz\Relocate\Jobs\Job $job */
$job  = $args['job'];
$user = get_userdata( $job->user_id );

$options = array_filter(
	array(
		! $job->settings['case_sensitive'] ? __( 'Ignore upper and lower case', 'dl-relocate-db' ) : '',
		$job->settings['whole_words'] ? __( 'Whole words only', 'dl-relocate-db' ) : '',
		$job->settings['url_variants'] ? __( 'Also http:// and // versions of the URL', 'dl-relocate-db' ) : '',
		$job->settings['skip_guids'] ? __( 'Post GUIDs left unchanged', 'dl-relocate-db' ) : '',
		! $job->dry_run && empty( $job->settings['before_image'] ) ? __( 'Original values not saved', 'dl-relocate-db' ) : '',
	)
);

$excluded = array();
foreach ( $job->settings['exclude_columns'] ?? array() as $table_name => $table_columns ) {
	foreach ( $table_columns as $column ) {
		$excluded[] = $table_name . '.' . $column;
	}
}
?>
<p class="dlz-back"><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>">&larr; <?php esc_html_e( 'All jobs', 'dl-relocate-db' ); ?></a></p>

<div class="dlz-title-row">
	<h2 class="dlz-title"><?php echo esc_html( Admin::job_title( $job ) ); ?> <?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></h2>
	<?php if ( Admin::can_delete( $job ) ) : ?>
		<a href="<?php echo esc_url( Admin::delete_url( $job->id ) ); ?>" class="button button-link-delete dlz-delete-job" data-job="<?php echo esc_attr( Admin::job_title( $job ) ); ?>">
			<span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e( 'Delete job', 'dl-relocate-db' ); ?>
		</a>
	<?php endif; ?>
</div>

<section class="dlz-card" aria-label="<?php esc_attr_e( 'Job details', 'dl-relocate-db' ); ?>">
<dl class="dlz-summary">
	<dt><?php esc_html_e( 'Search for', 'dl-relocate-db' ); ?></dt>
	<dd><code><?php echo esc_html( $job->search ); ?></code></dd>

	<dt><?php esc_html_e( 'Replace with', 'dl-relocate-db' ); ?></dt>
	<dd><?php echo '' === $job->replace ? '<em>' . esc_html__( '(nothing: matches are removed)', 'dl-relocate-db' ) . '</em>' : '<code>' . esc_html( $job->replace ) . '</code>'; ?></dd>

	<dt><?php esc_html_e( 'Options', 'dl-relocate-db' ); ?></dt>
	<dd><?php echo esc_html( $options ? implode( ', ', $options ) : __( 'Defaults', 'dl-relocate-db' ) ); ?></dd>

	<?php if ( $excluded ) : ?>
		<dt><?php esc_html_e( 'Columns left out', 'dl-relocate-db' ); ?></dt>
		<dd><code><?php echo esc_html( implode( ', ', $excluded ) ); ?></code></dd>
	<?php endif; ?>

	<dt><?php esc_html_e( 'Tables', 'dl-relocate-db' ); ?></dt>
	<dd>
		<details>
			<summary><?php echo esc_html( number_format_i18n( count( $job->settings['tables'] ) ) ); ?></summary>
			<code><?php echo esc_html( implode( ', ', $job->settings['tables'] ) ); ?></code>
		</details>
	</dd>

	<dt><?php esc_html_e( 'Started by', 'dl-relocate-db' ); ?></dt>
	<dd>
		<?php
		// Jobs started from WP-CLI without --user have no user.
		echo esc_html( $user ? $user->display_name : ( 0 === $job->user_id ? __( 'WP-CLI', 'dl-relocate-db' ) : __( 'Unknown user', 'dl-relocate-db' ) ) );
		?>
	</dd>

	<dt><?php esc_html_e( 'Created', 'dl-relocate-db' ); ?></dt>
	<dd><?php echo esc_html( Admin::format_date( $job->created_at ) ); ?></dd>

	<dt><?php esc_html_e( 'Finished', 'dl-relocate-db' ); ?></dt>
	<dd><?php echo esc_html( Admin::format_date( $job->finished_at ) ); ?></dd>

	<?php if ( $job->parent_id ) : ?>
		<dt><?php esc_html_e( 'Previewed by', 'dl-relocate-db' ); ?></dt>
		<dd>
			<a href="<?php echo esc_url( Admin::job_url( $job->parent_id ) ); ?>">
				<?php
				/* translators: %d: job number. */
				echo esc_html( sprintf( __( 'Dry run #%d', 'dl-relocate-db' ), $job->parent_id ) );
				?>
			</a>
		</dd>
	<?php endif; ?>

	<?php if ( $args['child_id'] ) : ?>
		<dt><?php esc_html_e( 'Applied by', 'dl-relocate-db' ); ?></dt>
		<dd>
			<a href="<?php echo esc_url( Admin::job_url( $args['child_id'] ) ); ?>">
				<?php
				/* translators: %d: job number. */
				echo esc_html( sprintf( __( 'Replacement #%d', 'dl-relocate-db' ), $args['child_id'] ) );
				?>
			</a>
		</dd>
	<?php endif; ?>
</dl>
</section>

<?php require __DIR__ . '/partials/runner.php'; ?>

<section class="dlz-card" aria-labelledby="dlz-job-log">
<h3 id="dlz-job-log" class="dlz-card-title"><?php esc_html_e( 'Log', 'dl-relocate-db' ); ?></h3>
<?php if ( $args['logs'] ) : ?>
	<div class="dlz-table-scroll">
		<table class="widefat striped dlz-tables">
			<caption class="screen-reader-text"><?php esc_html_e( 'Log entries for this job', 'dl-relocate-db' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'dl-relocate-db' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'dl-relocate-db' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'dl-relocate-db' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $args['logs'] as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( Admin::format_date( $entry->created_at ) ); ?></td>
						<td><?php echo LogTable::level_badge( (string) $entry->level ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></td>
						<td><?php echo LogTable::message( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( $args['log_total'] > count( $args['logs'] ) ) : ?>
		<p>
			<a href="
			<?php
			echo esc_url(
				Admin::url(
					'history',
					array(
						'view'    => 'log',
						'log_job' => $job->id,
					)
				)
			);
			?>
						">
				<?php
				/* translators: %s: number of log entries. */
				echo esc_html( sprintf( __( 'View all %s entries', 'dl-relocate-db' ), number_format_i18n( $args['log_total'] ) ) );
				?>
				<span aria-hidden="true">→</span>
			</a>
		</p>
	<?php endif; ?>
<?php else : ?>
	<p class="dlz-empty"><?php esc_html_e( 'No log entries for this job.', 'dl-relocate-db' ); ?></p>
<?php endif; ?>
</section>
