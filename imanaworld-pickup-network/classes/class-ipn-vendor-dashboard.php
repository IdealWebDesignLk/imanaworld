<?php
defined( 'ABSPATH' ) || exit;

/**
 * The vendor-facing half of IPN, added as a "Click & Collect" section inside
 * Dokan's own vendor dashboard rather than as a separate page.
 *
 * Vendors already sign in to /dashboard/ for products, orders, and payouts, so
 * their branches belong there too — it also means Dokan owns authentication,
 * the surrounding navigation, and the theme, and IPN only supplies the panel.
 *
 * Dokan gives one nav entry here; the Branches / Staff / Stock / Orders tabs
 * inside it are ours (`?ipn_tab=`), which keeps the integration surface to a
 * single query var and avoids depending on Dokan's sub-navigation API.
 *
 * Every read and every write is scoped through IPN_Access, never through the
 * branch_id that arrived in the request — see that class for why.
 */
class IPN_Vendor_Dashboard {

	const SLUG     = 'ipn';
	const PER_PAGE = 20;

	const TABS = array( 'branches', 'staff', 'stock', 'orders' );

	/**
	 * Result of whatever write was posted this request, surfaced as a notice.
	 *
	 * @var string|WP_Error|null
	 */
	protected $result = null;

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_filter( 'dokan_query_var_filter', $this, 'register_query_var' );
		$loader->add_filter( 'dokan_get_dashboard_nav', $this, 'register_nav' );
		$loader->add_action( 'dokan_load_custom_template', $this, 'load_template' );
		$loader->add_action( 'template_redirect', $this, 'maybe_handle_actions' );

		// Priority 999: Dokan registers its own dashboard rewrite rules on
		// init, and ours has to be regenerated after those exist.
		$loader->add_action( 'init', $this, 'maybe_flush_rewrites', 999 );
	}

	/**
	 * Regenerates rewrite rules once per plugin version.
	 *
	 * Dokan turns every query var from `dokan_query_var_filter` into a rewrite
	 * endpoint, and WordPress only learns about a new endpoint when the rules
	 * are regenerated. Activation does that; an in-place *update* does not,
	 * because the activation hook never fires for one. The visible symptom is
	 * exact and misleading: the nav item appears (that is only a filter) while
	 * /dashboard/ipn/ returns a 404, which reads like a broken integration
	 * rather than a stale rules table.
	 *
	 * Flushing is expensive, so it is keyed to the plugin version and happens
	 * once per upgrade rather than on every request. The soft flush is
	 * deliberate: endpoints live in the `rewrite_rules` option, and there is no
	 * reason to rewrite the server's .htaccess for this.
	 */
	public function maybe_flush_rewrites() {
		if ( get_option( 'ipn_rewrite_version' ) === IPN_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'ipn_rewrite_version', IPN_VERSION );
	}

	public function register_query_var( $query_vars ) {
		$query_vars[ self::SLUG ] = self::SLUG;
		return $query_vars;
	}

	public function register_nav( $urls ) {
		$urls[ self::SLUG ] = array(
			'title' => __( 'Click & Collect', 'ipn' ),
			'icon'  => '<i class="fa fa-shopping-bag"></i>',
			'url'   => function_exists( 'dokan_get_navigation_url' ) ? dokan_get_navigation_url( self::SLUG ) : '',
			'pos'   => 55,
		);

		return $urls;
	}

	/**
	 * Dokan calls this for every custom query var; only ours is ours to render.
	 */
	public function load_template( $query_vars ) {
		if ( ! isset( $query_vars[ self::SLUG ] ) ) {
			return;
		}

		$vendor_id = IPN_Access::current_vendor_id();

		if ( ! $vendor_id ) {
			echo '<div class="dokan-error">' . esc_html__( 'This area is only available to vendor accounts.', 'ipn' ) . '</div>';
			return;
		}

		if ( ! IPN_Vendor::is_partner( $vendor_id ) ) {
			echo '<div class="dokan-info">' . esc_html__( 'Your store is not part of the Click & Collect network yet. Contact IMANAWORLD to be set up as a pickup partner.', 'ipn' ) . '</div>';
			return;
		}

		$tab      = $this->current_tab();
		$result   = $this->result;
		$branches = IPN_Branch::get_all( array( 'vendor_id' => $vendor_id ) );

		$data = $this->tab_data( $tab, $vendor_id, $branches );

		include IPN_PLUGIN_DIR . 'templates/vendor/dashboard.php';
	}

	protected function current_tab() {
		$tab = isset( $_GET['ipn_tab'] ) ? sanitize_key( wp_unslash( $_GET['ipn_tab'] ) ) : 'branches'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, self::TABS, true ) ? $tab : 'branches';
	}

	public static function tab_url( $tab, array $args = array() ) {
		$base = function_exists( 'dokan_get_navigation_url' ) ? dokan_get_navigation_url( self::SLUG ) : home_url( '/dashboard/' . self::SLUG . '/' );
		return add_query_arg( array_merge( array( 'ipn_tab' => $tab ), $args ), $base );
	}

	/**
	 * Per-tab data, all of it already restricted to this vendor's branches.
	 */
	protected function tab_data( $tab, $vendor_id, array $branches ) {
		$branch_ids = array_map( 'intval', wp_list_pluck( $branches, 'id' ) );

		switch ( $tab ) {
			case 'staff':
				return array( 'staff' => $this->get_staff( $branch_ids ) );

			case 'stock':
				$branch_id = $this->requested_branch_id( $branch_ids );
				$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				$query_args = array(
					'branch_id' => $branch_id,
					'search'    => $search,
					'per_page'  => self::PER_PAGE,
					'page'      => $page,
				);

				$products = $branch_id ? IPN_Branch_Stock::query_products( $query_args ) : array();

				return array(
					'stock_branch_id' => $branch_id,
					'stock_products'  => $products,
					'stock_total'     => $branch_id ? IPN_Branch_Stock::count_products( $query_args ) : 0,
					'stock_search'    => $search,
					'stock_page'      => $page,
					'addable'         => ( $branch_id && '' !== $search ) ? $this->searchable_products( $vendor_id, $search, $branch_id ) : array(),
				);

			case 'orders':
				$branch_id = $this->requested_branch_id( $branch_ids, true );
				return array(
					'orders_branch_id' => $branch_id,
					'orders'           => $this->get_orders( $branch_ids, $branch_id ),
				);

			case 'branches':
			default:
				return array();
		}
	}

	/**
	 * A branch_id from the query string, but only if it is genuinely one of
	 * this vendor's. Falls back to their first branch so the Stock tab opens
	 * on something useful rather than an empty picker.
	 *
	 * @param bool $allow_all Whether 0 ("all branches") is a valid answer.
	 */
	protected function requested_branch_id( array $branch_ids, $allow_all = false ) {
		$requested = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $requested && in_array( $requested, $branch_ids, true ) ) {
			return $requested;
		}

		if ( $allow_all || ! $branch_ids ) {
			return 0;
		}

		return (int) $branch_ids[0];
	}

	/**
	 * Staff accounts assigned to any of this vendor's branches.
	 *
	 * @return object[]
	 */
	protected function get_staff( array $branch_ids ) {
		if ( ! $branch_ids ) {
			return array();
		}

		$users = get_users( array(
			'role'       => IPN_Roles::ROLE,
			'orderby'    => 'display_name',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_ipn_branch_id',
					'value'   => $branch_ids,
					'compare' => 'IN',
				),
			),
		) );

		$rows = array();

		foreach ( $users as $user ) {
			$branch_id = IPN_Roles::get_branch_id( $user->ID );
			$branch    = $branch_id ? IPN_Branch::get( $branch_id ) : null;

			$rows[] = (object) array(
				'user_id'      => (int) $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'branch_id'    => $branch_id,
				'branch_name'  => $branch ? $branch->name : '',
			);
		}

		return $rows;
	}

	/**
	 * This vendor's own products matching a search, annotated with whether
	 * they are already stocked at the branch being edited — the picker behind
	 * "add a product to this branch".
	 */
	protected function searchable_products( $vendor_id, $search, $branch_id ) {
		$products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'author'         => (int) $vendor_id,
			's'              => $search,
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$rows = array();

		foreach ( $products as $product ) {
			$rows[] = (object) array(
				'product_id' => (int) $product->ID,
				'name'       => $product->post_title,
				'stocked'    => (bool) IPN_Branch_Stock::get_row( $product->ID, $branch_id ),
			);
		}

		return $rows;
	}

	/**
	 * Orders across this vendor's branches, optionally narrowed to one.
	 *
	 * @return object[]
	 */
	protected function get_orders( array $branch_ids, $branch_id = 0 ) {
		$scope = $branch_id ? array( $branch_id ) : $branch_ids;

		if ( ! $scope ) {
			return array();
		}

		$rows = array();

		foreach ( $scope as $id ) {
			foreach ( IPN_Order::get_order_ids( array( 'branch_id' => $id, 'limit' => 100 ) ) as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					continue;
				}

				$status = IPN_Order::display_status( $order->get_status() );

				if ( ! $status ) {
					continue;
				}

				$branch  = IPN_Branch::get( $id );
				$meta    = IPN_Order::get_meta( $order_id );
				$created = $order->get_date_created();

				// The collection code, for issue #30. '' whenever it cannot be
				// read back, which the template shows as a dash rather than
				// pretending no code exists.
				$otp = IPN_OTP::status_for( $order_id );

				$rows[] = (object) array(
					'order_id'      => (int) $order_id,
					'order_number'  => $order->get_order_number(),
					'branch_name'   => $branch ? $branch->name : '',
					'customer_name' => IPN_Order::customer_name( $order ),
					'type'          => ( $meta && $meta->collection_type ) ? $meta->collection_type : 'standard',
					'status'        => $status,
					'otp_code'      => $otp ? $otp->code : '',
					'otp_status'    => $otp ? $otp->status : '',
					'total'         => $order->get_total(),
					'created_at'    => $created ? $created->getTimestamp() : 0,
					'date_label'    => IPN_Order::time_label( $order ),
				);
			}
		}

		usort( $rows, function ( $a, $b ) {
			return $b->created_at <=> $a->created_at;
		} );

		return $rows;
	}

	// ---- writes ----

	/**
	 * All vendor-dashboard writes, handled on template_redirect so a redirect
	 * after POST is still possible and the page renders the post-action state.
	 */
	public function maybe_handle_actions() {
		if ( is_admin() || ! is_user_logged_in() || empty( $_POST['ipn_vendor_action'] ) ) {
			return;
		}

		if ( ! IPN_Access::current_vendor_id() ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['ipn_vendor_action'] ) );

		// wp_verify_nonce rather than check_admin_referer: this is a
		// front-end dashboard, and an expired nonce should read as a normal
		// error on the page rather than WordPress's "Are you sure you want to
		// do this?" interstitial.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ipn_vendor_' . $action ) ) {
			$this->result = new WP_Error( 'ipn_nonce', __( 'That form has expired. Please reload the page and try again.', 'ipn' ) );
			return;
		}

		switch ( $action ) {
			case 'save_branch':
				$this->result = $this->handle_save_branch();
				break;
			case 'delete_branch':
				$this->result = $this->handle_delete_branch();
				break;
			case 'save_staff':
				$this->result = $this->handle_save_staff();
				break;
			case 'delete_staff':
				$this->result = $this->handle_delete_staff();
				break;
			case 'set_staff_password':
				$this->result = $this->handle_set_staff_password();
				break;
			case 'email_staff_reset':
				$this->result = $this->handle_email_staff_reset();
				break;
			case 'save_stock':
				$this->result = $this->handle_save_stock();
				break;
			case 'delete_stock':
				$this->result = $this->handle_delete_stock();
				break;
			case 'advance_order':
				$this->result = $this->handle_advance_order();
				break;
			case 'mark_paid':
				$this->result = $this->handle_mark_paid();
				break;
		}
	}

	/**
	 * Create or update one of this vendor's branches. The vendor_id is taken
	 * from the session, never from the form, so a vendor cannot file a branch
	 * under somebody else's account.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_save_branch() {
		$vendor_id = IPN_Access::current_vendor_id();
		$branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

		if ( $branch_id ) {
			$branch = IPN_Access::require_branch( $branch_id );

			if ( is_wp_error( $branch ) ) {
				return $branch;
			}
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( '' === $name || '' === $code ) {
			return new WP_Error( 'ipn_branch_invalid', __( 'Branch name and branch code are both required.', 'ipn' ) );
		}

		$data = array(
			'name'                  => $name,
			'code'                  => $code,
			'vendor_id'             => $vendor_id,
			'address'               => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'phone'                 => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'                 => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'status'                => empty( $_POST['active'] ) ? 'inactive' : 'active',
			'express_enabled'       => empty( $_POST['express_enabled'] ) ? 0 : 1,
			'express_surcharge'     => isset( $_POST['express_surcharge'] ) ? (float) $_POST['express_surcharge'] : 0,
			'express_prep_minutes'  => isset( $_POST['express_prep_minutes'] ) ? absint( $_POST['express_prep_minutes'] ) : 60,
			'standard_prep_minutes' => isset( $_POST['standard_prep_minutes'] ) ? absint( $_POST['standard_prep_minutes'] ) : 1440,
		);

		if ( $branch_id ) {
			IPN_Branch::update( $branch_id, $data );
		} else {
			$branch_id = IPN_Branch::create( $data );

			if ( ! $branch_id ) {
				return new WP_Error( 'ipn_branch_save_failed', __( 'Could not save the branch — that branch code may already be in use.', 'ipn' ) );
			}
		}

		$days = array();

		for ( $day = 0; $day <= 6; $day++ ) {
			$days[ $day ] = array(
				'is_closed'  => ! empty( $_POST[ 'hours_closed_' . $day ] ),
				'open_time'  => isset( $_POST[ 'hours_open_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_open_' . $day ] ) ) : '',
				'close_time' => isset( $_POST[ 'hours_close_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_close_' . $day ] ) ) : '',
			);
		}

		IPN_Branch::set_hours( $branch_id, $days );

		return __( 'Branch saved.', 'ipn' );
	}

	/**
	 * @return string|WP_Error
	 */
	protected function handle_delete_branch() {
		$branch = IPN_Access::require_branch( isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0 );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$deleted = IPN_Branch::delete( $branch->id );

		return is_wp_error( $deleted ) ? $deleted : __( 'Branch deleted.', 'ipn' );
	}

	/**
	 * Create a staff login, or move an existing one to another of this
	 * vendor's branches. As with vendor creation, no password is handled
	 * here — the account gets a random one and WordPress emails a set-your-own
	 * link, so a credential never passes through this form.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_save_staff() {
		$branch = IPN_Access::require_branch( isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0 );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( $user_id ) {
			if ( ! IPN_Access::can_manage_staff( $user_id ) ) {
				return new WP_Error( 'ipn_staff_forbidden', __( 'You do not have permission to manage that staff account.', 'ipn' ) );
			}

			IPN_Roles::set_branch_id( $user_id, $branch->id );

			return __( 'Staff member updated.', 'ipn' );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) ) {
			return new WP_Error( 'ipn_staff_invalid', __( 'A name and a valid email address are both required.', 'ipn' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'ipn_staff_email_taken', __( 'An account with that email address already exists.', 'ipn' ) );
		}

		$login = sanitize_user( current( explode( '@', $email ) ), true );

		if ( username_exists( $login ) ) {
			$login .= '-' . wp_generate_password( 4, false, false );
		}

		// A password typed here is optional. Left blank, the account gets a
		// random one and WordPress emails a set-your-own link — the safer
		// default, and the only one where nobody but the staff member ever
		// knows the credential. Counter staff often have no working email
		// though (and a site with no SMTP configured silently sends nothing),
		// so a manager can set one directly and hand it over in person.
		$chosen = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' !== $chosen && strlen( $chosen ) < 8 ) {
			return new WP_Error( 'ipn_staff_password_short', __( 'A password set here must be at least 8 characters. Leave it blank to email them a set-your-own link instead.', 'ipn' ) );
		}

		$new_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => '' !== $chosen ? $chosen : wp_generate_password( 24, true, true ),
			'display_name' => $name,
			'role'         => IPN_Roles::ROLE,
		) );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		IPN_Roles::set_branch_id( $new_id, $branch->id );

		// Deliberately records that an account was made, never the password.
		IPN_Audit_Log::log( 'staff_created', array(
			'branch_id' => $branch->id,
			'data'      => array( 'user_id' => (int) $new_id, 'name' => $name ),
		) );

		if ( '' !== $chosen ) {
			return sprintf(
				/* translators: %s: the password just set */
				__( 'Staff member added. Their password is %s — give it to them now, it is not shown again.', 'ipn' ),
				$chosen
			);
		}

		wp_new_user_notification( $new_id, null, 'user' );

		return __( 'Staff member added. They have been emailed a link to set their own password.', 'ipn' );
	}

	/**
	 * Sets an existing staff member's password directly.
	 *
	 * The manager never learns a password that already exists — WordPress only
	 * stores a hash — so this replaces it outright, which also ends any session
	 * that account had open. Shown back once so it can be handed over, and
	 * never written to the audit trail.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_set_staff_password() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! IPN_Access::can_manage_staff( $user_id ) ) {
			return new WP_Error( 'ipn_staff_forbidden', __( 'You do not have permission to manage that staff account.', 'ipn' ) );
		}

		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'ipn_staff_password_short', __( 'The password must be at least 8 characters.', 'ipn' ) );
		}

		wp_set_password( $password, $user_id );

		IPN_Audit_Log::log( 'staff_password_set', array(
			'branch_id' => IPN_Roles::get_branch_id( $user_id ),
			'data'      => array( 'user_id' => $user_id ),
		) );

		return sprintf(
			/* translators: %s: the password just set */
			__( 'Password updated. It is now %s — give it to them now, it is not shown again. They have been signed out everywhere.', 'ipn' ),
			$password
		);
	}

	/**
	 * Emails a staff member WordPress's own password-reset link.
	 *
	 * Built from get_password_reset_key() rather than by calling into
	 * wp-login.php, which is not loaded on a front-end request.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_email_staff_reset() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! IPN_Access::can_manage_staff( $user_id ) ) {
			return new WP_Error( 'ipn_staff_forbidden', __( 'You do not have permission to manage that staff account.', 'ipn' ) );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'ipn_staff_missing', __( 'That staff account no longer exists.', 'ipn' ) );
		}

		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$reset_url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );

		$sent = wp_mail(
			$user->user_email,
			__( 'Set your Click & Collect staff password', 'ipn' ),
			sprintf(
				/* translators: 1: staff display name, 2: password reset URL */
				__( "Hi %1\$s,\n\nUse the link below to set the password for your Click & Collect branch dashboard:\n\n%2\$s\n\nIf you were not expecting this, you can ignore it.", 'ipn' ),
				$user->display_name,
				$reset_url
			)
		);

		if ( ! $sent ) {
			return new WP_Error(
				'ipn_staff_mail_failed',
				__( 'WordPress could not send that email — this site may have no mail delivery configured. Set a password directly instead.', 'ipn' )
			);
		}

		return sprintf(
			/* translators: %s: staff email address */
			__( 'Reset link emailed to %s.', 'ipn' ),
			$user->user_email
		);
	}

	/**
	 * Removes a staff member from the network. The WordPress account itself is
	 * left in place and only its IPN role and branch assignment are dropped —
	 * deleting a user from a front-end form is not something a vendor should
	 * be able to do, and the account may be tied to orders or audit entries.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_delete_staff() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! IPN_Access::can_manage_staff( $user_id ) ) {
			return new WP_Error( 'ipn_staff_forbidden', __( 'You do not have permission to manage that staff account.', 'ipn' ) );
		}

		$branch_id = IPN_Roles::get_branch_id( $user_id );
		$user      = get_userdata( $user_id );

		if ( $user ) {
			$user->remove_role( IPN_Roles::ROLE );
			$user->add_role( 'customer' ); // Leaves them a working, harmless account.
		}

		delete_user_meta( $user_id, '_ipn_branch_id' );

		IPN_Audit_Log::log( 'staff_removed', array(
			'branch_id' => $branch_id,
			'data'      => array( 'user_id' => $user_id ),
		) );

		return __( 'Staff member removed. Their sign-in no longer reaches the branch dashboard.', 'ipn' );
	}

	/**
	 * @return string|WP_Error
	 */
	protected function handle_save_stock() {
		$branch = IPN_Access::require_branch( isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0 );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$total      = isset( $_POST['total_stock'] ) && '' !== $_POST['total_stock'] ? absint( $_POST['total_stock'] ) : null;

		if ( ! $product_id || null === $total ) {
			return new WP_Error( 'ipn_stock_invalid', __( 'A product and a stock quantity are both required.', 'ipn' ) );
		}

		// A vendor may only stock their own products — post_author is what
		// Dokan uses to decide whose product it is.
		if ( (int) get_post_field( 'post_author', $product_id ) !== IPN_Access::current_vendor_id() ) {
			return new WP_Error( 'ipn_stock_forbidden', __( 'That product does not belong to your store.', 'ipn' ) );
		}

		IPN_Branch_Stock::set_total( $product_id, $branch->id, $total );

		IPN_Audit_Log::log( 'stock_adjusted', array(
			'branch_id' => $branch->id,
			'data'      => array( 'product_id' => $product_id, 'new_total' => $total ),
		) );

		return __( 'Stock updated.', 'ipn' );
	}

	/**
	 * Moves one of this vendor's orders on to the next collection step.
	 *
	 * Until 0.8.0 only branch staff could move an order at all, which left a
	 * branch with no staff account of its own — a small store, a partner still
	 * setting up — with a queue nobody could work. The vendor now gets the
	 * same three steps: accept, preparing, ready.
	 *
	 * They deliberately do not get the fourth. An order is only marked
	 * collected against the customer's collection code, which is the sole
	 * evidence that the right person actually took the goods; a button here
	 * that skipped it would turn a real control into a formality.
	 *
	 * The branch is read back off the order rather than taken from the form,
	 * so order_id is the only posted value trusted, and it is checked against
	 * this vendor's own branches before anything moves.
	 *
	 * @return string|WP_Error
	 */
	protected function handle_advance_order() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$to       = isset( $_POST['to_status'] ) ? sanitize_key( wp_unslash( $_POST['to_status'] ) ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			return new WP_Error( 'ipn_order_missing', __( 'That order could not be found.', 'ipn' ) );
		}

		$branch = IPN_Access::require_branch( IPN_Order::branch_id_for( $order ) );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$steps   = IPN_Order::NEXT_STATUS;
		$current = IPN_Order::display_status( $order->get_status() );
		$step    = isset( $steps[ $current ] ) ? $steps[ $current ] : null;

		// The step comes from where the order actually is, not from the page
		// the button was on, so two people working the same queue cannot skip
		// a stage or repeat one. The posted target only has to agree with it.
		if ( ! $step || $step[1] !== $to ) {
			return new WP_Error(
				'ipn_order_moved',
				__( 'That order has already moved on. Reload the page to see where it is now.', 'ipn' )
			);
		}

		if ( ! IPN_Order::advance( $order_id, $step[0], $step[1] ) ) {
			return new WP_Error( 'ipn_advance_failed', __( 'Could not update that order. Please reload the page and try again.', 'ipn' ) );
		}

		return sprintf(
			/* translators: 1: order number, 2: the status it moved to */
			__( 'Order %1$s is now %2$s.', 'ipn' ),
			$order->get_order_number(),
			IPN_Order::status_label( IPN_Order::display_status( $step[1] ) )
		);
	}

	/**
	 * @return string|WP_Error
	 */
	protected function handle_delete_stock() {
		$branch = IPN_Access::require_branch( isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0 );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		$deleted = IPN_Branch_Stock::delete_row( $product_id, $branch->id );

		return is_wp_error( $deleted ) ? $deleted : __( 'Product removed from this branch.', 'ipn' );
	}
}
