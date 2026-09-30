<?php
/**
 * Dashboard.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

use DesignsLabz\Relocate\Admin\Admin;

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	return;
}

/** @var \DesignsLabz\Relocate\Jobs\Job|null $latest */
$latest = $args['latest'];
$stats  = $args['stats'];

$tiles = array(
	array( 'database', __( 'Tables in this database', 'designslabz-relocate' ), number_format_i18n( $stats['tables'] ) ),
	array( 'chart-pie', __( 'Database size', 'designslabz-relocate' ), (string) size_format( $stats['size'], 1 ) ),
	array( 'backup', __( 'Jobs in history', 'designslabz-relocate' ), number_format_i18n( $stats['jobs'] ) ),
	array( 'update', __( 'Replacements made', 'designslabz-relocate' ), number_format_i18n( $stats['replacements_made'] ) ),
);
?>
<h2 class="dlz-title"><?php esc_html_e( 'Dashboard', 'designslabz-relocate' ); ?></h2>

<ul class="dlz-tiles">
	<?php foreach ( $tiles as [ $icon, $label, $value ] ) : ?>
		<li class="dlz-tile">
			<span class="dlz-tile-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span class="dlz-tile-value"><?php echo esc_html( $value ); ?></span>
			<span class="dlz-tile-label"><?php echo esc_html( $label ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>

<div class="dlz-dashboard">
	<?php if ( $args['attention'] ) : ?>
		<section class="dlz-card dlz-card-wide dlz-card-attention" aria-labelledby="dlz-attention-title">
			<h3 id="dlz-attention-title" class="dlz-card-title"><span class="dashicons dashicons-flag" aria-hidden="true"></span> <?php esc_html_e( 'Needs attention', 'designslabz-relocate' ); ?></h3>
			<ul class="dlz-list">
				<?php foreach ( $args['attention'] as $job ) : ?>
					<li>
						<?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?>
						<a href="<?php echo esc_url( Admin::job_url( $job->id ) ); ?>"><?php echo esc_html( Admin::job_title( $job ) ); ?></a>
						<span class="description">
							<?php
							echo esc_html(
								$job->is_interrupted()
									? __( 'Stopped before it finished. Open it to continue from where it stopped.', 'designslabz-relocate' )
									: __( 'Stopped by an error. Open it to see what happened and resume.', 'designslabz-relocate' )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="dlz-card dlz-card-feature" aria-labelledby="dlz-quick-title">
		<h3 id="dlz-quick-title" class="dlz-card-title"><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php esc_html_e( 'Quick search & replace', 'designslabz-relocate' ); ?></h3>
		<form method="post" action="<?php echo esc_url( Admin::url( 'search-replace' ) ); ?>" class="dlz-stack">
			<?php wp_nonce_field( $args['quick_action'] ); ?>
			<p class="dlz-field">
				<label for="dlz-quick-search"><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></label>
				<input type="text" id="dlz-quick-search" name="search" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com">
			</p>
			<p class="dlz-field">
				<label for="dlz-quick-replace"><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></label>
				<input type="text" id="dlz-quick-replace" name="replace" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com">
			</p>
			<p>
				<button type="submit" class="button button-primary dlz-button-lg"><?php esc_html_e( 'Choose tables', 'designslabz-relocate' ); ?> <span aria-hidden="true">→</span></button>
			</p>
		</form>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-latest-title">
		<h3 id="dlz-latest-title" class="dlz-card-title"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Last replacement', 'designslabz-relocate' ); ?></h3>
		<?php if ( $latest ) : ?>
			<?php $latest_totals = $latest->report->totals(); ?>
			<p class="dlz-card-lead">
				<a href="<?php echo esc_url( Admin::job_url( $latest->id ) ); ?>"><?php echo esc_html( Admin::job_title( $latest ) ); ?></a>
				<?php echo Admin::status_badge( $latest ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?>
			</p>
			<dl class="dlz-summary">
				<dt><?php esc_html_e( 'When', 'designslabz-relocate' ); ?></dt>
				<dd><?php echo esc_html( Admin::format_date( $latest->finished_at ?? $latest->created_at ) ); ?></dd>
				<dt><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></dt>
				<dd><code><?php echo esc_html( $latest->search ); ?></code></dd>
				<dt><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></dt>
				<dd><code><?php echo esc_html( $latest->replace ); ?></code></dd>
				<dt><?php esc_html_e( 'Rows changed', 'designslabz-relocate' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $latest_totals['rows_changed'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Replacements', 'designslabz-relocate' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $latest_totals['replacements'] ) ); ?></dd>
			</dl>
		<?php else : ?>
			<p class="dlz-empty"><?php esc_html_e( 'Nothing has been replaced yet.', 'designslabz-relocate' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-status-title">
		<h3 id="dlz-status-title" class="dlz-card-title"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span> <?php esc_html_e( 'Status', 'designslabz-relocate' ); ?></h3>
		<dl class="dlz-summary">
			<?php foreach ( $args['status'] as $label => $value ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt>
				<dd><?php echo esc_html( $value ); ?></dd>
			<?php endforeach; ?>
		</dl>
	</section>

	<section class="dlz-card dlz-card-wide" aria-labelledby="dlz-recent-title">
		<h3 id="dlz-recent-title" class="dlz-card-title"><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Recent jobs', 'designslabz-relocate' ); ?></h3>
		<?php if ( $args['recent'] ) : ?>
			<div class="dlz-table-scroll">
				<table class="widefat striped dlz-tables">
					<caption class="screen-reader-text"><?php esc_html_e( 'Recent jobs', 'designslabz-relocate' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Job', 'designslabz-relocate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Created', 'designslabz-relocate' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Replacements', 'designslabz-relocate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'designslabz-relocate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $args['recent'] as $job ) : ?>
							<tr>
								<th scope="row"><a href="<?php echo esc_url( Admin::job_url( $job->id ) ); ?>"><?php echo esc_html( Admin::job_title( $job ) ); ?></a></th>
								<td><?php echo esc_html( Admin::format_date( $job->created_at ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $job->report->totals()['replacements'] ) ); ?></td>
								<td><?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>"><?php esc_html_e( 'View all history', 'designslabz-relocate' ); ?> <span aria-hidden="true">→</span></a></p>
		<?php else : ?>
			<p class="dlz-empty"><?php esc_html_e( 'No jobs yet.', 'designslabz-relocate' ); ?></p>
		<?php endif; ?>
	</section>
</div>
