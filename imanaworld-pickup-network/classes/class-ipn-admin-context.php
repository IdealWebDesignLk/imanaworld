<?php
defined( 'ABSPATH' ) || exit;

/**
 * The partner an admin is currently working on, and the scope that follows
 * from it.
 *
 * IPN's admin was built partner-agnostic: every screen showed every branch,
 * every order, every audit entry, across all partners at once. That is fine
 * with one pilot partner and unusable with several — "which branch is this
 * order at" becomes "which partner does this branch even belong to". So the
 * admin now works the way the vendor dashboard already does: pick a partner,
 * and Branches, Staff, Stock, Orders, Disputes, Digest, Audit and Reports all
 * narrow to that partner's branches.
 *
 * The choice is stored per administrator in user meta rather than in the
 * session, so it survives a logout and two admins can work on different
 * partners at the same time without fighting over one global option.
 *
 * "All partners" stays available deliberately — some questions ("is anything
 * disputed anywhere") are genuinely network-wide, and forcing a partner would
 * make those unanswerable.
 */
class IPN_Admin_Context {

	const META_KEY = '_ipn_admin_partner_id';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'admin_init', $this, 'maybe_handle_switch' );
	}

	/**
	 * Switching partner is a nonce-guarded GET, the same pattern WordPress's
	 * own list-table row actions use — it is capability-gated and idempotent,
	 * not a form submission with fields.
	 */
	public function maybe_handle_switch() {
		if ( ! isset( $_GET['ipn_partner'], $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ipn_switch_partner' ) ) {
			return;
		}

		self::set_partner_id( absint( $_GET['ipn_partner'] ) );

		wp_safe_redirect( remove_query_arg( array( 'ipn_partner', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * The partner currently in scope, or 0 for "all partners".
	 *
	 * With exactly one partner configured the answer is obvious, so it is
	 * chosen automatically rather than making an admin pick from a list of
	 * one before anything will display.
	 */
	public static function get_partner_id() {
		$stored = (int) get_user_meta( get_current_user_id(), self::META_KEY, true );

		if ( $stored && IPN_Vendor::is_partner( $stored ) ) {
			return $stored;
		}

		$partners = IPN_Vendor::get_partners();

		if ( 1 === count( $partners ) ) {
			return (int) $partners[0]->ID;
		}

		// A stored partner that has since lost its flag is treated as no
		// selection at all, rather than silently scoping to a vendor that is
		// no longer part of the network.
		return 0;
	}

	/**
	 * @param int $partner_id 0 clears the selection back to "all partners".
	 */
	public static function set_partner_id( $partner_id ) {
		$partner_id = (int) $partner_id;

		if ( $partner_id && ! IPN_Vendor::is_partner( $partner_id ) ) {
			return;
		}

		if ( $partner_id ) {
			update_user_meta( get_current_user_id(), self::META_KEY, $partner_id );
		} else {
			delete_user_meta( get_current_user_id(), self::META_KEY );
		}
	}

	/**
	 * @return WP_User|null
	 */
	public static function get_partner() {
		$id = self::get_partner_id();

		if ( ! $id ) {
			return null;
		}

		$user = get_userdata( $id );

		return $user ? $user : null;
	}

	/**
	 * Label for the selected partner, preferring the Dokan store name so the
	 * admin sees the same words everywhere the partner is named.
	 */
	public static function partner_label( $partner_id = null ) {
		$partner_id = null === $partner_id ? self::get_partner_id() : (int) $partner_id;

		if ( ! $partner_id ) {
			return '';
		}

		$store = IPN_Vendor::store_name( $partner_id );

		if ( $store ) {
			return $store;
		}

		$user = get_userdata( $partner_id );

		return $user ? $user->display_name : '';
	}

	/**
	 * Branch IDs in scope: the selected partner's branches, or every branch
	 * when no partner is selected.
	 *
	 * @return int[]
	 */
	public static function branch_ids() {
		$partner_id = self::get_partner_id();
		$args       = $partner_id ? array( 'vendor_id' => $partner_id ) : array();
		$ids        = array_map( 'intval', wp_list_pluck( IPN_Branch::get_all( $args ), 'id' ) );

		// A partner with no branches yet must scope to nothing, not to
		// everything. Callers treat an empty array as "no scoping", so a
		// newly onboarded partner would otherwise show the whole network's
		// orders and stock. Branch ids are always positive, so 0 matches
		// nothing and produces the empty result that is actually correct.
		if ( $partner_id && ! $ids ) {
			return array( 0 );
		}

		return $ids;
	}

	/**
	 * Branch rows in scope, for populating the filters each screen already has.
	 *
	 * @return object[]
	 */
	public static function branches() {
		$partner_id = self::get_partner_id();

		return IPN_Branch::get_all( $partner_id ? array( 'vendor_id' => $partner_id ) : array() );
	}

	/**
	 * Whether a branch is inside the current scope. Screens that accept a
	 * branch_id from the query string check this so a stale bookmark cannot
	 * show another partner's branch while a partner is selected.
	 */
	public static function is_branch_in_scope( $branch_id ) {
		if ( ! self::get_partner_id() ) {
			return true;
		}

		return in_array( (int) $branch_id, self::branch_ids(), true );
	}

	public static function switch_url( $partner_id ) {
		return wp_nonce_url(
			add_query_arg( 'ipn_partner', (int) $partner_id ),
			'ipn_switch_partner'
		);
	}
}
