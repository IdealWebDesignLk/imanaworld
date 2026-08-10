<?php
defined( 'ABSPATH' ) || exit;

/**
 * Custom "IPN Branch Staff" role — scoped to the branch order dashboard only,
 * no wp-admin, product, or cross-branch access. Branch assignment is stored
 * as user meta (_ipn_branch_id) since a staff login belongs to exactly one branch.
 */
class IPN_Roles {

	const ROLE = 'ipn_branch_staff';

	public static function add_role() {
		add_role(
			self::ROLE,
			__( 'IPN Branch Staff', 'ipn' ),
			array(
				'read' => true,
			)
		);
	}

	public static function remove_role() {
		remove_role( self::ROLE );
	}

	public static function get_branch_id( $user_id ) {
		$branch_id = get_user_meta( $user_id, '_ipn_branch_id', true );
		return $branch_id ? (int) $branch_id : 0;
	}

	public static function set_branch_id( $user_id, $branch_id ) {
		update_user_meta( $user_id, '_ipn_branch_id', (int) $branch_id );
	}

	public static function is_branch_staff( $user_id ) {
		$user = get_userdata( $user_id );
		return $user && in_array( self::ROLE, (array) $user->roles, true );
	}
}
