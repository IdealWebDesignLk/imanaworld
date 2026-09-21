<?php
defined( 'ABSPATH' ) || exit;

class IPN_Activator {

	public static function activate() {
		IPN_Install::create_tables();
		update_option( 'ipn_db_version', IPN_DB_VERSION );

		IPN_Roles::add_role();

		self::add_default_options();

		// The staff dashboard is a front-end page; shipping the dashboard
		// without the page it lives on leaves branch staff with no way in.
		IPN_Pages::ensure_staff_dashboard_page();
		update_option( 'ipn_pages_version', IPN_VERSION );

		// Deliberately not flushing rewrite rules here. During activation the
		// plugin's hooks are not booted, so Dokan has not been told about the
		// /dashboard/ipn/ endpoint and a flush now would write rules without
		// it — and recording the version as "done" would then stop the real
		// flush from ever running (a permanent 404 on the vendor dashboard).
		// Clearing the marker makes the first normal request flush once the
		// endpoint is registered; see IPN_Vendor_Dashboard::maybe_flush_rewrites().
		delete_option( 'ipn_rewrite_version' );
	}

	protected static function add_default_options() {
		add_option( 'ipn_otp_expiry_hours', 72 );
		add_option( 'ipn_collection_window_days', 5 );
		add_option( 'ipn_reminder_after_hours', 48 );
		add_option( 'ipn_max_otp_attempts', 3 );
		add_option( 'ipn_auto_cancel_enabled', 'yes' );
		add_option( 'ipn_auto_refund_mode', 'manual' ); // 'auto' or 'manual'
		add_option( 'ipn_delete_data_on_uninstall', 'no' );
	}
}
