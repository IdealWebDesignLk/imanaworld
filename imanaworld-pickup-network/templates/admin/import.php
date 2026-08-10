<?php
defined( 'ABSPATH' ) || exit;
/** @var array $recent_runs */
?>
<div class="wrap ipn-admin">
	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'CSV / Excel catalogue import', 'ipn' ); ?></div>
			<div class="panel-sub">
				<?php esc_html_e( 'Creates or updates Choppies products by SKU. File must include a Branch ID column mapping stock to each branch.', 'ipn' ); ?>
			</div>

			<form method="post" enctype="multipart/form-data" data-ipn-toast="<?php esc_attr_e( 'Catalogue import is not implemented yet.', 'ipn' ); ?>" onsubmit="ipnShowToast(this.getAttribute('data-ipn-toast'));return false;">
				<?php wp_nonce_field( 'ipn_catalogue_import' ); ?>
				<div class="dropzone">
					<div class="dropzone-ico">⇧</div>
					<div class="dropzone-title"><?php esc_html_e( 'Drop catalogue file here', 'ipn' ); ?></div>
					<div class="dropzone-sub">.csv <?php esc_html_e( 'or', 'ipn' ); ?> .xlsx</div>
					<input type="file" name="ipn_catalogue_file" accept=".csv,.xlsx" style="margin-bottom:12px;" />
					<br />
					<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Run import', 'ipn' ); ?></button>
				</div>
			</form>
			<button type="button" class="btn btn-ghost" data-ipn-toast="<?php esc_attr_e( 'Not implemented yet.', 'ipn' ); ?>">
				<?php esc_html_e( '⇩ Download IPN import template', 'ipn' ); ?>
			</button>
		</div>

		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Import log', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Results per run', 'ipn' ); ?></div>

			<?php if ( empty( $recent_runs ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No imports have been run yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php foreach ( $recent_runs as $run ) : ?>
					<div class="import-log-row">
						<span>
							<?php echo esc_html( $run->filename ); ?>
							<div class="hint"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $run->created_at ) ); ?></div>
						</span>
						<span style="text-align:right;">
							<span class="chip chip-active"><?php echo esc_html( $run->created_count ); ?> <?php esc_html_e( 'created', 'ipn' ); ?></span>
							<span class="chip chip-inactive"><?php echo esc_html( $run->updated_count ); ?> <?php esc_html_e( 'updated', 'ipn' ); ?></span>
							<?php if ( $run->failed_count > 0 ) : ?>
								<span class="chip chip-disputed"><?php echo esc_html( $run->failed_count ); ?> <?php esc_html_e( 'failed', 'ipn' ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
