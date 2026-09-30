<?php
/**
 * Dashboard.
 *
 * @package CraftRoq\Relocate
 *
 * @var array $args
 */

use CraftRoq\Relocate\Admin\Admin;

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	return;
}

/** @var \CraftRoq\Relocate\Jobs\Job|null $latest */
$latest = $args['latest'];
$stats  = $args['stats'];

$tiles = array(
	array( 'database', __( 'Tables in this database', 'cr-relocate-db' ), number_format_i18n( $stats['tables'] ) ),
	array( 'chart-pie', __( 'Database size', 'cr-relocate-db' ), (string) size_format( $stats['size'], 1 ) ),
	array( 'backup', __( 'Jobs in history', 'cr-relocate-db' ), number_format_i18n( $stats['jobs'] ) ),
	array( 'update', __( 'Replacements made', 'cr-relocate-db' ), number_format_i18n( $stats['replacements_made'] ) ),
);
?>
<h2 class="crq-title"><?php esc_html_e( 'Dashboard', 'cr-relocate-db' ); ?></h2>

<?php if ( 0 === $stats['jobs'] ) : ?>
	<section class="crq-card crq-welcome" aria-labelledby="crq-welcome-title">
		<div>
			<h3 id="crq-welcome-title"><?php esc_html_e( 'Move a site safely in three steps', 'cr-relocate-db' ); ?></h3>
			<p class="crq-intro"><?php esc_html_e( 'Change a domain, switch to HTTPS or update text everywhere it appears, without breaking serialized data.', 'cr-relocate-db' ); ?></p>
		</div>
		<a class="button button-primary crq-button-lg" href="<?php echo esc_url( Admin::url( 'search-replace' ) ); ?>">
			<?php esc_html_e( 'Start a search & replace', 'cr-relocate-db' ); ?> <span aria-hidden="true">→</span>
		</a>
		<ol class="crq-welcome-steps">
			<li>
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Choose', 'cr-relocate-db' ); ?></strong> <?php esc_html_e( 'What to find, what to replace it with, and which tables.', 'cr-relocate-db' ); ?></span>
			</li>
			<li>
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Preview', 'cr-relocate-db' ); ?></strong> <?php esc_html_e( 'A dry run shows every change without writing anything.', 'cr-relocate-db' ); ?></span>
			</li>
			<li>
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Apply', 'cr-relocate-db' ); ?></strong> <?php esc_html_e( 'Confirm, and the original values are saved to a file first.', 'cr-relocate-db' ); ?></span>
			</li>
		</ol>
	</section>
<?php endif; ?>

<ul class="crq-tiles">
	<?php foreach ( $tiles as [ $icon, $label, $value ] ) : ?>
		<li class="crq-tile">
			<span class="crq-tile-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span class="crq-tile-value"><?php echo esc_html( $value ); ?></span>
			<span class="crq-tile-label"><?php echo esc_html( $label ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>

<div class="crq-dashboard">
	<?php if ( $args['attention'] ) : ?>
		<section class="crq-card crq-card-wide crq-card-attention" aria-labelledby="crq-attention-title">
			<h3 id="crq-attention-title" class="crq-card-title"><span class="dashicons dashicons-flag" aria-hidden="true"></span> <?php esc_html_e( 'Needs attention', 'cr-relocate-db' ); ?></h3>
			<ul class="crq-list">
				<?php foreach ( $args['attention'] as $job ) : ?>
					<li>
						<?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?>
						<a href="<?php echo esc_url( Admin::job_url( $job->id ) ); ?>"><?php echo esc_html( Admin::job_title( $job ) ); ?></a>
						<span class="description">
							<?php
							echo esc_html(
								$job->is_interrupted()
									? __( 'Stopped before it finished. Open it to continue from where it stopped.', 'cr-relocate-db' )
									: __( 'Stopped by an error. Open it to see what happened and resume.', 'cr-relocate-db' )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="crq-card crq-card-feature" aria-labelledby="crq-quick-title">
		<h3 id="crq-quick-title" class="crq-card-title"><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php esc_html_e( 'Quick search & replace', 'cr-relocate-db' ); ?></h3>
		<form method="post" action="<?php echo esc_url( Admin::url( 'search-replace' ) ); ?>" class="crq-stack">
			<?php wp_nonce_field( $args['quick_action'] ); ?>
			<p class="crq-field">
				<label for="crq-quick-search"><?php esc_html_e( 'Search for', 'cr-relocate-db' ); ?></label>
				<input type="text" id="crq-quick-search" name="search" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com">
			</p>
			<p class="crq-field">
				<label for="crq-quick-replace"><?php esc_html_e( 'Replace with', 'cr-relocate-db' ); ?></label>
				<input type="text" id="crq-quick-replace" name="replace" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com">
			</p>
			<p>
				<button type="submit" class="button button-primary crq-button-lg"><?php esc_html_e( 'Choose tables', 'cr-relocate-db' ); ?> <span aria-hidden="true">→</span></button>
			</p>
		</form>
	</section>

	<section class="crq-card" aria-labelledby="crq-latest-title">
		<h3 id="crq-latest-title" class="crq-card-title"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Last replacement', 'cr-relocate-db' ); ?></h3>
		<?php if ( $latest ) : ?>
			<?php $latest_totals = $latest->report->totals(); ?>
			<p class="crq-card-lead">
				<a href="<?php echo esc_url( Admin::job_url( $latest->id ) ); ?>"><?php echo esc_html( Admin::job_title( $latest ) ); ?></a>
				<?php echo Admin::status_badge( $latest ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?>
			</p>
			<dl class="crq-summary">
				<dt><?php esc_html_e( 'When', 'cr-relocate-db' ); ?></dt>
				<dd><?php echo esc_html( Admin::format_date( $latest->finished_at ?? $latest->created_at ) ); ?></dd>
				<dt><?php esc_html_e( 'Search for', 'cr-relocate-db' ); ?></dt>
				<dd><code><?php echo esc_html( $latest->search ); ?></code></dd>
				<dt><?php esc_html_e( 'Replace with', 'cr-relocate-db' ); ?></dt>
				<dd><code><?php echo esc_html( $latest->replace ); ?></code></dd>
				<?php if ( count( $latest->pairs() ) > 1 ) : ?>
					<dt><?php esc_html_e( 'Also', 'cr-relocate-db' ); ?></dt>
					<dd>
						<?php
						$more_pairs = count( $latest->pairs() ) - 1;
						/* translators: %s: number of further search and replacement pairs. */
						echo esc_html( sprintf( _n( '%s more pair', '%s more pairs', $more_pairs, 'cr-relocate-db' ), number_format_i18n( $more_pairs ) ) );
						?>
					</dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Rows changed', 'cr-relocate-db' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $latest_totals['rows_changed'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Replacements', 'cr-relocate-db' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $latest_totals['replacements'] ) ); ?></dd>
			</dl>
		<?php else : ?>
			<p class="crq-empty"><?php esc_html_e( 'Nothing has been replaced yet.', 'cr-relocate-db' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="crq-card" aria-labelledby="crq-status-title">
		<h3 id="crq-status-title" class="crq-card-title"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span> <?php esc_html_e( 'Status', 'cr-relocate-db' ); ?></h3>
		<dl class="crq-summary">
			<?php foreach ( $args['status'] as $label => $value ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt>
				<dd><?php echo esc_html( $value ); ?></dd>
			<?php endforeach; ?>
		</dl>
	</section>

	<section class="crq-card crq-card-wide" aria-labelledby="crq-recent-title">
		<h3 id="crq-recent-title" class="crq-card-title"><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Recent jobs', 'cr-relocate-db' ); ?></h3>
		<?php if ( $args['recent'] ) : ?>
			<div class="crq-table-scroll">
				<table class="widefat striped crq-tables crq-stack-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Recent jobs', 'cr-relocate-db' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Job', 'cr-relocate-db' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Created', 'cr-relocate-db' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Replacements', 'cr-relocate-db' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'cr-relocate-db' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $args['recent'] as $job ) : ?>
							<tr>
								<th scope="row"><a href="<?php echo esc_url( Admin::job_url( $job->id ) ); ?>"><?php echo esc_html( Admin::job_title( $job ) ); ?></a></th>
								<td data-label="<?php esc_attr_e( 'Created', 'cr-relocate-db' ); ?>"><?php echo esc_html( Admin::format_date( $job->created_at ) ); ?></td>
								<td class="num" data-label="<?php esc_attr_e( 'Replacements', 'cr-relocate-db' ); ?>"><?php echo esc_html( number_format_i18n( $job->report->totals()['replacements'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Status', 'cr-relocate-db' ); ?>"><?php echo Admin::status_badge( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped HTML. ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>"><?php esc_html_e( 'View all history', 'cr-relocate-db' ); ?> <span aria-hidden="true">→</span></a></p>
		<?php else : ?>
			<div class="crq-empty-state">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<?php esc_html_e( 'No jobs yet. Your dry runs and replacements will appear here.', 'cr-relocate-db' ); ?>
			</div>
		<?php endif; ?>
	</section>
</div>
