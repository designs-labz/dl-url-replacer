<?php
/**
 * Dashboard tab.
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
?>
<div class="dlz-dashboard">
	<?php if ( $args['attention'] ) : ?>
		<section class="dlz-card dlz-card-wide dlz-card-attention" aria-labelledby="dlz-attention-title">
			<h2 id="dlz-attention-title"><?php esc_html_e( 'Needs attention', 'designslabz-relocate' ); ?></h2>
			<ul>
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

	<section class="dlz-card" aria-labelledby="dlz-quick-title">
		<h2 id="dlz-quick-title"><?php esc_html_e( 'Quick search & replace', 'designslabz-relocate' ); ?></h2>
		<form method="post" action="<?php echo esc_url( Admin::url( 'search-replace' ) ); ?>">
			<?php wp_nonce_field( $args['quick_action'] ); ?>
			<p>
				<label for="dlz-quick-search"><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></label><br>
				<input type="text" id="dlz-quick-search" name="search" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com">
			</p>
			<p>
				<label for="dlz-quick-replace"><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></label><br>
				<input type="text" id="dlz-quick-replace" name="replace" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com">
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Choose tables…', 'designslabz-relocate' ); ?></button>
			</p>
		</form>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-latest-title">
		<h2 id="dlz-latest-title"><?php esc_html_e( 'Last replacement', 'designslabz-relocate' ); ?></h2>
		<?php if ( $latest ) : ?>
			<?php $latest_totals = $latest->report->totals(); ?>
			<p>
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
			<p><?php esc_html_e( 'Nothing has been replaced yet.', 'designslabz-relocate' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-status-title">
		<h2 id="dlz-status-title"><?php esc_html_e( 'Status', 'designslabz-relocate' ); ?></h2>
		<dl class="dlz-summary">
			<?php foreach ( $args['status'] as $label => $value ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt>
				<dd><?php echo esc_html( $value ); ?></dd>
			<?php endforeach; ?>
		</dl>
	</section>

	<section class="dlz-card dlz-card-wide" aria-labelledby="dlz-recent-title">
		<h2 id="dlz-recent-title"><?php esc_html_e( 'Recent jobs', 'designslabz-relocate' ); ?></h2>
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
			<p><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>"><?php esc_html_e( 'View all history', 'designslabz-relocate' ); ?></a></p>
		<?php else : ?>
			<p><?php esc_html_e( 'No jobs yet.', 'designslabz-relocate' ); ?></p>
		<?php endif; ?>
	</section>
</div>
