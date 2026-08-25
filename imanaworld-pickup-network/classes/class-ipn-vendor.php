<?php
defined( 'ABSPATH' ) || exit;

/**
 * Partner (Dokan vendor) lifecycle, as far as IPN needs it.
 *
 * A C&C partner *is* a Dokan vendor account, one-to-one (see
 * IPN_Project_Context.md), so there's no separate partner table here — this
 * class is a thin, Dokan-shaped wrapper over the WP user with the `seller`
 * role and the two bits of Dokan user meta that decide whether that vendor
 * can trade: `dokan_enable_selling` ('yes'/'no') and `dokan_profile_settings`
 * (where the store name lives).
 *
 * `dokan_enable_selling` is the same flag Dokan's own "vendors must be
 * approved" registration flow sets, which is why approving a pending vendor
 * and re-enabling a suspended one are the same write — they're only
 * different words for the admin. IPN keeps its own `ipn_vendor_disabled_at`
 * marker alongside it purely so the Partners screen can tell those two
 * cases apart and label the button correctly.
 */
class IPN_Vendor {

	const ROLE           = 'seller';
	const SELLING_META   = 'dokan_enable_selling';
	const PROFILE_META   = 'dokan_profile_settings';
	const DISABLED_MARKER = 'ipn_vendor_disabled_at';
	const PARTNER_META   = '_ipn_is_partner';

	/**
	 * One page of vendor accounts, newest registrations last (Dokan's own
	 * admin orders by display name, and so does the branch dropdown).
	 *
	 * @param array $args {
	 *     @type string $search        Matches display name, login, or email.
	 *     @type bool   $partners_only Restrict to vendors flagged as C&C partners.
	 *     @type int    $per_page
	 *     @type int    $page          1-based.
	 * }
	 * @return array { @type WP_User[] $vendors, @type int $total }
	 */
	public static function query( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'search'   => '',
			'per_page' => 25,
			'page'     => 1,
		) );

		$per_page = max( 1, (int) $args['per_page'] );

		$query_args = array(
			'role'    => self::ROLE,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => $per_page,
			'paged'   => max( 1, (int) $args['page'] ),
		);

		if ( '' !== $args['search'] ) {
			$query_args['search']         = '*' . $args['search'] . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
		}

		if ( ! empty( $args['partners_only'] ) ) {
			$query_args['meta_key']   = self::PARTNER_META; // phpcs:ignore WordPress.DB.SlowDBQuery
			$query_args['meta_value'] = 1; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$query = new WP_User_Query( $query_args );

		return array(
			'vendors' => $query->get_results(),
			'total'   => (int) $query->get_total(),
		);
	}

	/**
	 * Every vendor, unpaginated — only for the branch modal's vendor
	 * dropdown, which has to be able to reach any vendor by definition.
	 */
	public static function get_all() {
		return get_users( array(
			'role'    => self::ROLE,
			'orderby' => 'display_name',
			'fields'  => array( 'ID', 'display_name' ),
		) );
	}

	/**
	 * Whether a vendor has been marked as a Click & Collect partner.
	 *
	 * Being a Dokan vendor and being an IPN partner are deliberately not the
	 * same thing: a marketplace has far more vendors than it has C&C partners,
	 * and only the handful an admin explicitly opts in should show up on the
	 * IPN Partners screen or be selectable when creating a branch. The flag is
	 * set from the vendor's own user profile in wp-admin.
	 */
	public static function is_partner( $user_id ) {
		return (bool) get_user_meta( (int) $user_id, self::PARTNER_META, true );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function set_partner( $user_id, $is_partner ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return new WP_Error( 'ipn_vendor_not_found', __( 'That vendor account no longer exists.', 'ipn' ) );
		}

		if ( $is_partner ) {
			update_user_meta( (int) $user_id, self::PARTNER_META, 1 );
		} else {
			delete_user_meta( (int) $user_id, self::PARTNER_META );
		}

		IPN_Audit_Log::log( $is_partner ? 'partner_enabled' : 'partner_disabled', array(
			'data' => array(
				'vendor_id' => (int) $user_id,
				'vendor'    => $user->display_name,
			),
		) );

		return true;
	}

	/**
	 * Every vendor flagged as a C&C partner. Used for the branch screen's
	 * "Select partner" control, which must never offer a vendor that isn't
	 * actually part of the pickup network.
	 *
	 * @return WP_User[]
	 */
	public static function get_partners() {
		return get_users( array(
			'role'       => self::ROLE,
			'orderby'    => 'display_name',
			'meta_key'   => self::PARTNER_META, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => 1, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
	}

	public static function is_selling_enabled( $user_id ) {
		return 'yes' === get_user_meta( $user_id, self::SELLING_META, true );
	}

	/**
	 * Where a vendor sits in its lifecycle, for labelling the Partners row.
	 *
	 * @return string enabled | disabled | pending
	 */
	public static function state( $user_id ) {
		if ( self::is_selling_enabled( $user_id ) ) {
			return 'enabled';
		}

		// Never enabled and never explicitly switched off by an admin — this
		// is a signup waiting on approval, not a suspended partner.
		return get_user_meta( $user_id, self::DISABLED_MARKER, true ) ? 'disabled' : 'pending';
	}

	public static function store_name( $user_id ) {
		$profile = get_user_meta( $user_id, self::PROFILE_META, true );

		if ( is_array( $profile ) && ! empty( $profile['store_name'] ) ) {
			return $profile['store_name'];
		}

		return '';
	}

	/**
	 * Approve / re-enable / suspend a vendor. Fires Dokan's own
	 * `dokan_seller_enabled` / `dokan_seller_disabled` actions so whatever
	 * Dokan (or another plugin) hangs off them — vendor notification email,
	 * bulk product status changes — still runs.
	 *
	 * @return true|WP_Error
	 */
	public static function set_selling_enabled( $user_id, $enabled ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return new WP_Error( 'ipn_vendor_not_found', __( 'That vendor account no longer exists.', 'ipn' ) );
		}

		update_user_meta( $user_id, self::SELLING_META, $enabled ? 'yes' : 'no' );

		if ( $enabled ) {
			delete_user_meta( $user_id, self::DISABLED_MARKER );
			do_action( 'dokan_seller_enabled', $user_id );
		} else {
			update_user_meta( $user_id, self::DISABLED_MARKER, current_time( 'mysql' ) );
			do_action( 'dokan_seller_disabled', $user_id );
		}

		IPN_Audit_Log::log( $enabled ? 'vendor_enabled' : 'vendor_disabled', array(
			'data' => array(
				'vendor_id' => (int) $user_id,
				'vendor'    => $user->display_name,
			),
		) );

		return true;
	}

	/**
	 * Creates a Dokan vendor account from the Partners screen, so onboarding
	 * a partner no longer means sending them through the public vendor
	 * registration form first (issue #15).
	 *
	 * No password is set or handled here: the account gets a random one and
	 * the vendor receives WordPress's own "set your password" email, which
	 * keeps a credential out of this form, out of the request, and out of
	 * the audit log.
	 *
	 * @param array $data store_name, email, username, first_name, last_name, phone, enable_selling.
	 * @return int|WP_Error New user ID.
	 */
	public static function create( array $data ) {
		if ( ! get_role( self::ROLE ) ) {
			return new WP_Error( 'ipn_no_seller_role', __( 'The Dokan vendor role is not available — check that Dokan is active.', 'ipn' ) );
		}

		$store_name = isset( $data['store_name'] ) ? trim( $data['store_name'] ) : '';
		$email      = isset( $data['email'] ) ? trim( $data['email'] ) : '';
		$username   = isset( $data['username'] ) ? trim( $data['username'] ) : '';

		if ( '' === $store_name || '' === $email ) {
			return new WP_Error( 'ipn_vendor_invalid', __( 'A store name and email address are both required.', 'ipn' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ipn_vendor_email', __( 'That email address is not valid.', 'ipn' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'ipn_vendor_email_taken', __( 'An account with that email address already exists.', 'ipn' ) );
		}

		if ( '' === $username ) {
			$username = sanitize_user( current( explode( '@', $email ) ), true );
		}

		if ( username_exists( $username ) ) {
			return new WP_Error( 'ipn_vendor_username_taken', __( 'That username is already taken — choose another.', 'ipn' ) );
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'first_name'   => isset( $data['first_name'] ) ? $data['first_name'] : '',
			'last_name'    => isset( $data['last_name'] ) ? $data['last_name'] : '',
			'display_name' => $store_name,
			'role'         => self::ROLE,
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$enable_selling = ! empty( $data['enable_selling'] );
		$phone          = isset( $data['phone'] ) ? $data['phone'] : '';

		$profile = array(
			'store_name'     => $store_name,
			'social'         => array(),
			'payment'        => array(),
			'phone'          => $phone,
			'show_email'     => 'no',
			'address'        => array(),
			'location'       => '',
			'banner'         => 0,
			'icon'           => 0,
			'gravatar'       => 0,
			'show_more_ptab' => 'yes',
			'store_ppp'      => 10,
			'enable_tnc'     => 'off',
			'store_tnc'      => '',
		);

		update_user_meta( $user_id, self::PROFILE_META, $profile );
		update_user_meta( $user_id, 'dokan_store_name', $store_name );
		update_user_meta( $user_id, self::SELLING_META, $enable_selling ? 'yes' : 'no' );

		if ( ! $enable_selling ) {
			update_user_meta( $user_id, self::DISABLED_MARKER, current_time( 'mysql' ) );
		}

		// Lets Dokan finish its own vendor setup (store page, capabilities,
		// its own new-vendor emails) exactly as it would for a self-service
		// signup, rather than leaving a half-configured vendor behind.
		do_action( 'dokan_new_seller_created', $user_id, $profile );

		// WordPress's own new-user email: the vendor sets their own password
		// from the link in it. Nothing here ever knows their password.
		wp_new_user_notification( $user_id, null, 'user' );

		IPN_Audit_Log::log( 'vendor_created', array(
			'data' => array(
				'vendor_id' => (int) $user_id,
				'vendor'    => $store_name,
			),
		) );

		return (int) $user_id;
	}
}
