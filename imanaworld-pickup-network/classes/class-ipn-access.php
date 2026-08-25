<?php
defined( 'ABSPATH' ) || exit;

/**
 * The single place that answers "is this user allowed to touch this branch?".
 *
 * Three roles reach IPN data, and each sees a different slice of it:
 *
 *   Admin        (manage_woocommerce) — every branch, every partner.
 *   Vendor       (Dokan seller)       — every branch belonging to their own
 *                                       vendor account, and nothing else.
 *   Branch staff (ipn_branch_staff)   — the single branch assigned to them.
 *
 * Every write path in the vendor and staff dashboards calls a guard here
 * before touching a row, rather than trusting the branch_id that arrived in
 * the request. A vendor editing another vendor's branch, or staff reaching a
 * second branch, is a matter of changing one number in a form post — so the
 * check has to happen server-side on the way in, not by only rendering the
 * right options on the way out.
 */
class IPN_Access {

	/**
	 * The vendor (Dokan seller) whose data the current user owns, or 0.
	 *
	 * Dokan's own helper is preferred when it exists so that anything Dokan
	 * does about staff/impersonation is respected; the role check is the
	 * fallback for a plain seller account.
	 */
	public static function current_vendor_id() {
		if ( ! is_user_logged_in() ) {
			return 0;
		}

		$user_id = get_current_user_id();

		if ( function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $user_id ) ) {
			return (int) $user_id;
		}

		$user = wp_get_current_user();

		return ( $user && in_array( IPN_Vendor::ROLE, (array) $user->roles, true ) ) ? (int) $user_id : 0;
	}

	/**
	 * The single branch the current user staffs, or 0 if they aren't staff.
	 */
	public static function current_staff_branch_id() {
		if ( ! is_user_logged_in() ) {
			return 0;
		}

		$user_id = get_current_user_id();

		if ( ! IPN_Roles::is_branch_staff( $user_id ) ) {
			return 0;
		}

		return IPN_Roles::get_branch_id( $user_id );
	}

	public static function is_admin_user() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Branch IDs the current user may see. Admins get everything; a vendor
	 * gets their own; staff get the one they're assigned to.
	 *
	 * @return int[]
	 */
	public static function allowed_branch_ids() {
		if ( self::is_admin_user() ) {
			return array_map( 'intval', wp_list_pluck( IPN_Branch::get_all(), 'id' ) );
		}

		$vendor_id = self::current_vendor_id();

		if ( $vendor_id ) {
			return array_map( 'intval', wp_list_pluck( IPN_Branch::get_all( array( 'vendor_id' => $vendor_id ) ), 'id' ) );
		}

		$staff_branch_id = self::current_staff_branch_id();

		return $staff_branch_id ? array( $staff_branch_id ) : array();
	}

	/**
	 * Whether the current user may read/write a specific branch.
	 */
	public static function can_manage_branch( $branch_id ) {
		$branch_id = (int) $branch_id;

		if ( ! $branch_id ) {
			return false;
		}

		if ( self::is_admin_user() ) {
			return true;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch ) {
			return false;
		}

		$vendor_id = self::current_vendor_id();

		if ( $vendor_id ) {
			return (int) $branch->vendor_id === $vendor_id;
		}

		return self::current_staff_branch_id() === $branch_id;
	}

	/**
	 * Guard for a branch-scoped write. Returns the branch row so callers can
	 * use it, or a WP_Error they can hand straight to the template.
	 *
	 * @return object|WP_Error
	 */
	public static function require_branch( $branch_id ) {
		$branch = IPN_Branch::get( (int) $branch_id );

		if ( ! $branch ) {
			return new WP_Error( 'ipn_branch_missing', __( 'That branch no longer exists.', 'ipn' ) );
		}

		if ( ! self::can_manage_branch( $branch->id ) ) {
			return new WP_Error( 'ipn_branch_forbidden', __( 'You do not have permission to manage that branch.', 'ipn' ) );
		}

		return $branch;
	}

	/**
	 * Whether the current vendor owns a given staff account — the guard
	 * behind a vendor editing or deleting staff. A staff user belongs to a
	 * vendor only through the branch they're assigned to.
	 */
	public static function can_manage_staff( $user_id ) {
		if ( ! IPN_Roles::is_branch_staff( $user_id ) ) {
			return false;
		}

		if ( self::is_admin_user() ) {
			return true;
		}

		$branch_id = IPN_Roles::get_branch_id( $user_id );

		// An unassigned staff account belongs to nobody yet, so only an admin
		// can pick it up — otherwise any vendor could claim it.
		return $branch_id ? self::can_manage_branch( $branch_id ) : false;
	}
}
