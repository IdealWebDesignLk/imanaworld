<?php
defined( 'ABSPATH' ) || exit;

/**
 * The "Make IPN Partner" switch on a vendor's user-profile screen.
 *
 * Being a Dokan vendor and being a Click & Collect partner are separate
 * things — a marketplace carries far more vendors than the handful running
 * pickup points. This flag is what promotes one to the other: only flagged
 * vendors appear on IPN → Partners, and only they can be picked as the
 * partner a branch belongs to.
 *
 * It lives on the core user-edit screen rather than on Dokan's vendor list so
 * it keeps working regardless of Dokan's own markup, and so it sits next to
 * the account it actually describes.
 */
class IPN_User_Profile {

	const NONCE = 'ipn_save_partner_flag';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'show_user_profile', $this, 'render_partner_field' );
		$loader->add_action( 'edit_user_profile', $this, 'render_partner_field' );
		$loader->add_action( 'personal_options_update', $this, 'save_partner_field' );
		$loader->add_action( 'edit_user_profile_update', $this, 'save_partner_field' );
	}

	/**
	 * Only rendered for vendor accounts — the flag is meaningless on a
	 * customer or an administrator, and showing it there would invite
	 * someone to tick it and wonder why nothing happened.
	 */
	public function render_partner_field( $user ) {
		if ( ! in_array( IPN_Vendor::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return; // A vendor viewing their own profile can see it, but not set it.
		}

		$is_partner   = IPN_Vendor::is_partner( $user->ID );
		$branch_count = count( IPN_Branch::get_all( array( 'vendor_id' => $user->ID ) ) );

		wp_nonce_field( self::NONCE, 'ipn_partner_nonce' );
		?>
		<h2><?php esc_html_e( 'IMANAWORLD Pickup Network', 'ipn' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Click & Collect partner', 'ipn' ); ?></th>
				<td>
					<label for="ipn_is_partner">
						<input
							type="checkbox"
							name="ipn_is_partner"
							id="ipn_is_partner"
							value="1"
							<?php checked( $is_partner ); ?>
						/>
						<?php esc_html_e( 'Make IPN Partner', 'ipn' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Adds this vendor to IPN → Partners and makes it selectable as the partner a branch belongs to. Vendors that are not partners are left out of the pickup network entirely.', 'ipn' ); ?>
					</p>
					<?php if ( $branch_count ) : ?>
						<p class="description">
							<strong>
								<?php
								printf(
									/* translators: %d: number of branches already attached to this vendor */
									esc_html( _n( 'This vendor already has %d branch.', 'This vendor already has %d branches.', $branch_count, 'ipn' ) ),
									(int) $branch_count
								);
								?>
							</strong>
							<?php esc_html_e( 'Unticking this hides the vendor from the Partners screen but does not delete those branches.', 'ipn' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_partner_field( $user_id ) {
		if ( ! isset( $_POST['ipn_partner_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_partner_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! in_array( IPN_Vendor::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		$is_partner = ! empty( $_POST['ipn_is_partner'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		// Only write (and only log) on an actual change, so re-saving an
		// unrelated part of the profile doesn't fill the audit trail.
		if ( $is_partner !== IPN_Vendor::is_partner( $user_id ) ) {
			IPN_Vendor::set_partner( $user_id, $is_partner );
		}
	}
}
