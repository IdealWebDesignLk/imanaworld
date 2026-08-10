<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[]           $recent_runs   Rows from ipn_import_log, each with a ->failed_rows array attached.
 * @var int|WP_Error|null  $import_result Result of an upload processed this request, if any.
 */
?>
<div class="wrap ipn-admin">
	<?php if ( $import_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $import_result->get_error_message() ); ?></p></div>
	<?php elseif ( is_int( $import_result ) ) : ?>
		<?php $run = current( array_filter( $recent_runs, function ( $r ) use ( $import_result ) { return (int) $r->id === $import_result; } ) ); ?>
		<div class="notice notice-success">
			<p>
				<?php if ( $run ) : ?>
					<?php
					printf(
						/* translators: 1: created count, 2: updated count, 3: failed count */
						esc_html__( 'Import complete — %1$d created, %2$d updated, %3$d failed. See the log below.', 'ipn' ),
						(int) $run->created_count,
						(int) $run->updated_count,
						(int) $run->failed_count
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Import complete — see the log below.', 'ipn' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'CSV / Excel catalogue import', 'ipn' ); ?></div>
			<div class="panel-sub">
				<?php esc_html_e( 'Creates or updates Choppies products by SKU. One row per SKU x Branch — see the template for the exact column format.', 'ipn' ); ?>
			</div>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ipn_catalogue_import' ); ?>
				<input type="hidden" name="ipn_run_import" value="1" />
				<div class="dropzone">
					<div class="dropzone-ico">⇧</div>
					<div class="dropzone-title"><?php esc_html_e( 'Choose catalogue file', 'ipn' ); ?></div>
					<div class="dropzone-sub">.csv <?php esc_html_e( 'or', 'ipn' ); ?> .xlsx</div>
					<input type="file" name="ipn_catalogue_file" accept=".csv,.xlsx" required="required" style="margin-bottom:12px;" />
					<br />
					<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Run import', 'ipn' ); ?></button>
				</div>
			</form>
			<a class="btn btn-ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ipn_download_import_template' ), 'ipn_download_import_template' ) ); ?>">
				<?php esc_html_e( '⇩ Download IPN import template', 'ipn' ); ?>
			</a>
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
					<?php if ( ! empty( $run->failed_rows ) ) : ?>
						<div class="hint" style="margin:4px 0 10px;padding-left:2px;">
							<?php foreach ( $run->failed_rows as $failed_row ) : ?>
								<div>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: row number in the source file, 2: SKU, 3: error message */
											__( 'Row %1$d (SKU %2$s): %3$s', 'ipn' ),
											(int) $failed_row->row_number,
											$failed_row->sku ? $failed_row->sku : '—',
											$failed_row->message
										)
									);
									?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
