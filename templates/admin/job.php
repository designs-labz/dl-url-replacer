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
		! $job->settings['case_sensitive'] ? __( 'Ignore upper and lower case', 'designslabz-relocate' ) : '',
		$job->settings['whole_words'] ? __( 'Whole words only', 'designslabz-relocate' ) : '',
		$job->settings['url_variants'] ? __( 'Also http:// and // versions of the URL', 'designslabz-relocate' ) : '',
		$job->settings['skip_guids'] ? __( 'Post GUIDs left unchanged', 'designslabz-relocate' ) : '',
		! $job->dry_run && empty( $job->settings['before_image'] ) ? __( 'Original values not saved', 'designslabz-relocate' ) : '',
	)
);

$excluded = array();
foreach ( $job->settings['exclude_columns'] ?? array() as $table_name => $table_columns ) {
	foreach ( $table_columns as $column ) {
		$excluded[] = $table_name . '.' . $column;
	}
}
?>
<p class="dlz-back"><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>">&larr; <?php esc_html_e( 'All jobs', 'designslabz-relocate' ); ?></a></p>

<div class="dlz-title-row">
	<h2 class="dlz-title"><?php echo esc_html( Admin::job_title( $job ) ); ?> <?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></h2>
	<?php if ( Admin::can_delete( $job ) ) : ?>
		<a href="<?php echo esc_url( Admin::delete_url( $job->id ) ); ?>" class="button button-link-delete dlz-delete-job" data-job="<?php echo esc_attr( Admin::job_title( $job ) ); ?>">
			<span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e( 'Delete job', 'designslabz-relocate' ); ?>
		</a>
	<?php endif; ?>
</div>

<section class="dlz-card" aria-label="<?php esc_attr_e( 'Job details', 'designslabz-relocate' ); ?>">
<dl class="dlz-summary">
	<dt><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></dt>
	<dd><code><?php echo esc_html( $job->search ); ?></code></dd>

	<dt><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo '' === $job->replace ? '<em>' . esc_html__( '(nothing: matches are removed)', 'designslabz-relocate' ) . '</em>' : '<code>' . esc_html( $job->replace ) . '</code>'; ?></dd>

	<dt><?php esc_html_e( 'Options', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( $options ? implode( ', ', $options ) : __( 'Defaults', 'designslabz-relocate' ) ); ?></dd>

	<?php if ( $excluded ) : ?>
		<dt><?php esc_html_e( 'Columns left out', 'designslabz-relocate' ); ?></dt>
		<dd><code><?php echo esc_html( implode( ', ', $excluded ) ); ?></code></dd>
	<?php endif; ?>

	<dt><?php esc_html_e( 'Tables', 'designslabz-relocate' ); ?></dt>
	<dd>
		<details>
			<summary><?php echo esc_html( number_format_i18n( count( $job->settings['tables'] ) ) ); ?></summary>
			<code><?php echo esc_html( implode( ', ', $job->settings['tables'] ) ); ?></code>
		</details>
	</dd>

	<dt><?php esc_html_e( 'Started by', 'designslabz-relocate' ); ?></dt>
	<dd>
		<?php
		// Jobs started from WP-CLI without --user have no user.
		echo esc_html( $user ? $user->display_name : ( 0 === $job->user_id ? __( 'WP-CLI', 'designslabz-relocate' ) : __( 'Unknown user', 'designslabz-relocate' ) ) );
		?>
	</dd>

	<dt><?php esc_html_e( 'Created', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( Admin::format_date( $job->created_at ) ); ?></dd>

	<dt><?php esc_html_e( 'Finished', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( Admin::format_date( $job->finished_at ) ); ?></dd>

	<?php if ( $job->parent_id ) : ?>
		<dt><?php esc_html_e( 'Previewed by', 'designslabz-relocate' ); ?></dt>
		<dd>
			<a href="<?php echo esc_url( Admin::job_url( $job->parent_id ) ); ?>">
				<?php
				/* translators: %d: job number. */
				echo esc_html( sprintf( __( 'Dry run #%d', 'designslabz-relocate' ), $job->parent_id ) );
				?>
			</a>
		</dd>
	<?php endif; ?>

	<?php if ( $args['child_id'] ) : ?>
		<dt><?php esc_html_e( 'Applied by', 'designslabz-relocate' ); ?></dt>
		<dd>
			<a href="<?php echo esc_url( Admin::job_url( $args['child_id'] ) ); ?>">
				<?php
				/* translators: %d: job number. */
				echo esc_html( sprintf( __( 'Replacement #%d', 'designslabz-relocate' ), $args['child_id'] ) );
				?>
			</a>
		</dd>
	<?php endif; ?>
</dl>
</section>

<?php require __DIR__ . '/partials/runner.php'; ?>

<section class="dlz-card" aria-labelledby="dlz-job-log">
<h3 id="dlz-job-log" class="dlz-card-title"><?php esc_html_e( 'Log', 'designslabz-relocate' ); ?></h3>
<?php if ( $args['logs'] ) : ?>
	<div class="dlz-table-scroll">
		<table class="widefat striped dlz-tables">
			<caption class="screen-reader-text"><?php esc_html_e( 'Log entries for this job', 'designslabz-relocate' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'designslabz-relocate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'designslabz-relocate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'designslabz-relocate' ); ?></th>
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
				echo esc_html( sprintf( __( 'View all %s entries', 'designslabz-relocate' ), number_format_i18n( $args['log_total'] ) ) );
				?>
				<span aria-hidden="true">→</span>
			</a>
		</p>
	<?php endif; ?>
<?php else : ?>
	<p class="dlz-empty"><?php esc_html_e( 'No log entries for this job.', 'designslabz-relocate' ); ?></p>
<?php endif; ?>
</section>
