<?php
defined( 'ABSPATH' ) || exit;

class IPN_Activator {

	public static function activate() {
		IPN_Install::create_tables();
		update_option( 'ipn_db_version', IPN_DB_VERSION );

		IPN_Roles::add_role();

		self::add_default_options();

		flush_rewrite_rules();
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
