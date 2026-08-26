<?php
defined( 'ABSPATH' ) || exit;

/**
 * The pages IPN needs in order to work, created and kept alive by the plugin
 * rather than left as a manual setup step.
 *
 * There is exactly one: the branch staff dashboard, which is a front-end page
 * carrying the [ipn_staff_dashboard] shortcode. Branch staff have no wp-admin
 * access, so that page *is* their entire interface — a plugin that ships the
 * dashboard but not the page it lives on isn't actually installed. WooCommerce
 * does the same for Cart and Checkout, and Dokan for its vendor dashboard.
 *
 * Everything here is idempotent and adoption-first: if a page carrying the
 * shortcode already exists, that page is claimed rather than a second one
 * created, so an admin who set this up by hand doesn't end up with duplicates.
 */
class IPN_Pages {

	const PAGE_OPTION    = 'ipn_staff_dashboard_page_id';
	const VERSION_OPTION = 'ipn_pages_version';
	const SHORTCODE      = 'ipn_staff_dashboard';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'admin_init', $this, 'maybe_ensure_pages' );
	}

	/**
	 * Runs once per plugin version, and only in wp-admin.
	 *
	 * Deliberately not on activation alone: an in-place plugin *update* never
	 * fires the activation hook, which is the same trap that left the vendor
	 * dashboard's rewrite rules stale in 0.7.0. Keying to the version means an
	 * existing install picks the page up on the next admin page load.
	 *
	 * Restricted to admin requests so that creating content is never triggered
	 * by a customer hitting the storefront.
	 */
	public function maybe_ensure_pages() {
		if ( get_option( self::VERSION_OPTION ) === IPN_VERSION ) {
			return;
		}

		self::ensure_staff_dashboard_page();

		update_option( self::VERSION_OPTION, IPN_VERSION );
	}

	/**
	 * Guarantees a published page carrying the staff-dashboard shortcode, and
	 * returns its ID.
	 *
	 * Order of preference: the page we already know about, then any existing
	 * page that carries the shortcode, then a newly created one.
	 *
	 * @return int 0 if the page could not be created.
	 */
	public static function ensure_staff_dashboard_page() {
		$stored_id = (int) get_option( self::PAGE_OPTION );

		if ( $stored_id ) {
			$page = get_post( $stored_id );

			// A trashed page is treated as gone: it no longer serves staff,
			// and silently reviving something an admin deleted on purpose
			// would be worse than making a fresh one.
			if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
				if ( 'publish' !== $page->post_status ) {
					wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) );
				}

				return (int) $page->ID;
			}
		}

		$adopted = self::find_page_with_shortcode();

		if ( $adopted ) {
			update_option( self::PAGE_OPTION, $adopted );
			return $adopted;
		}

		$page_id = wp_insert_post( array(
			'post_title'     => __( 'Branch Staff', 'ipn' ),
			'post_name'      => 'branch-staff',
			'post_content'   => '[' . self::SHORTCODE . ']',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::PAGE_OPTION, (int) $page_id );

		IPN_Audit_Log::log( 'staff_page_created', array(
			'data' => array( 'page_id' => (int) $page_id ),
		) );

		return (int) $page_id;
	}

	/**
	 * Any page already carrying the shortcode, whoever made it.
	 *
	 * Matched with a LIKE on post_content rather than WP_Query's `s`, which
	 * does not handle square brackets usefully. Drafts count — an admin who
	 * started the page by hand should have it adopted and published, not
	 * shadowed by a duplicate.
	 *
	 * @return int
	 */
	protected static function find_page_with_shortcode() {
		global $wpdb;

		$id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'page'
			   AND post_status NOT IN ( 'trash', 'auto-draft' )
			   AND post_content LIKE %s
			 ORDER BY ID ASC
			 LIMIT 1",
			'%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%'
		) );

		return $id ? (int) $id : 0;
	}

	/**
	 * The staff dashboard's page ID, creating the page if it has gone missing.
	 */
	public static function get_staff_dashboard_page_id() {
		$id   = (int) get_option( self::PAGE_OPTION );
		$page = $id ? get_post( $id ) : null;

		if ( ! $page || 'trash' === $page->post_status ) {
			return self::ensure_staff_dashboard_page();
		}

		return $id;
	}

	/**
	 * URL branch staff should be given. Empty only if the page could not be
	 * created at all, which the admin screen reports rather than hiding.
	 */
	public static function staff_dashboard_url() {
		$id = self::get_staff_dashboard_page_id();

		return $id ? get_permalink( $id ) : '';
	}
}
