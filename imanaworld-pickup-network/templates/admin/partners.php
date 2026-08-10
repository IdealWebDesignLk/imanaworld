<?php
defined( 'ABSPATH' ) || exit;
/**
 * There is no multi-vendor partner data model in the plugin yet — Choppies
 * is the only IPN-enabled vendor and it is hardcoded here, same as the
 * pilot scope described everywhere else in this plugin. Do not add fake
 * rows for other vendors; onboarding a second partner is future-phase work.
 */
$ipn_branch_count = count( IPN_Branch::get_all() );
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Dokan vendors with IPN mode', 'ipn' ); ?></div>
		<button type="button" class="btn btn-primary" data-ipn-toast="<?php esc_attr_e( 'Partner onboarding is not implemented yet.', 'ipn' ); ?>">
			<?php esc_html_e( '+ Onboard partner', 'ipn' ); ?>
		</button>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Vendor', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'IPN mode', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branches', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Model', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><b><?php esc_html_e( 'Choppies', 'ipn' ); ?></b></td>
					<td>
						<label class="switch">
							<input type="checkbox" checked disabled />
							<span class="switch-track"></span>
						</label>
					</td>
					<td><?php echo esc_html( $ipn_branch_count ); ?></td>
					<td><?php esc_html_e( 'CSV-based', 'ipn' ); ?></td>
					<td><span class="chip chip-active"><span class="chip-dot"></span><?php esc_html_e( 'Live — pilot', 'ipn' ); ?></span></td>
				</tr>
			</tbody>
		</table>
	</div>
	<p class="hint"><?php esc_html_e( 'Choppies is the only IPN pilot partner. Additional vendor onboarding is future-phase work.', 'ipn' ); ?></p>
</div>
