<?php
defined( 'ABSPATH' ) || exit;

/**
 * One-shot wipe of every piece of data IPN itself owns, leaving WooCommerce
 * and Dokan alone: vendor accounts, stores, WooCommerce products and the
 * WooCommerce orders themselves all survive. Triggered only from the
 * "Danger zone" on the Settings screen, behind a typed confirmation.
 *
 * Removes: every ipn_ table's rows (branches, hours, closures, stock, OTP
 * codes, audit log, import history, per-order IPN meta), the partner flag on
 * vendors, the branch link and employee number on staff accounts, the IPN
 * fields mirrored onto WooCommerce orders, and the admin's remembered
 * partner choice. Tables are truncated rather than dropped so the plugin
 * keeps working immediately afterwards.
 *
 * Not touched: staff user accounts themselves (they just lose their branch),
 * and any leftover ipn-* order status on an old order.
 */
class IPN_Data_Reset {

	const CONFIRM_WORD = 'RESET';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'admin_post_ipn_reset_data', $this, 'handle' );
	}

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ipn' ) );
		}

		check_admin_referer( 'ipn_reset_data' );

		$typed = isset( $_POST['ipn_reset_confirm'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ipn_reset_confirm'] ) ) ) : '';

		if ( self::CONFIRM_WORD !== $typed ) {
			wp_safe_redirect( add_query_arg( 'ipn_reset', 'mismatch', admin_url( 'admin.php?page=ipn-settings' ) ) );
			exit;
		}

		$summary = self::run();

		set_transient( 'ipn_reset_summary_' . get_current_user_id(), $summary, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'ipn_reset', 'done', admin_url( 'admin.php?page=ipn-settings' ) ) );
		exit;
	}

	/**
	 * @return array Counts of what was removed, for the confirmation notice.
	 */
	public static function run() {
		global $wpdb;

		$summary = array();

		foreach ( array( 'branches', 'branch_hours', 'branch_closures', 'branch_stock', 'otp_codes', 'audit_log', 'import_log', 'import_log_rows', 'order_meta' ) as $name ) {
			$table = IPN_Install::table( $name );
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL

			$summary[ $name ] = $count;
		}

		// Partner flag, staff links, and the admin's remembered partner.
		$summary['partners']         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", IPN_Vendor::PARTNER_META ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$summary['staff_links']      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", '_ipn_branch_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		foreach ( array( IPN_Vendor::PARTNER_META, '_ipn_branch_id', '_ipn_employee_number', '_ipn_admin_partner_id' ) as $meta_key ) {
			$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
		}

		// IPN fields mirrored onto WooCommerce orders. Through the order CRUD
		// so it works under HPOS as well as legacy post-based orders.
		$order_ids = wc_get_orders( array(
			'limit'      => -1,
			'return'     => 'ids',
			'status'     => array_keys( wc_get_order_statuses() ),
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_ipn_branch_id',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$order->delete_meta_data( '_ipn_branch_id' );
			$order->delete_meta_data( '_ipn_collection_type' );
			$order->delete_meta_data( '_ipn_nominated_recipient_name' );
			$order->save();
		}

		$summary['orders_unlinked'] = count( $order_ids );

		return $summary;
	}
}
