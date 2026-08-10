<?php
/**
 * Fires only on "Delete" from the Plugins screen (not on deactivate).
 * Tables and order data are kept unless the site owner has opted in via
 * the ipn_delete_data_on_uninstall setting — accidental data loss on a
 * routine plugin removal would be worse than a few orphaned tables.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/classes/class-ipn-install.php';
require_once __DIR__ . '/classes/class-ipn-roles.php';

if ( ! defined( 'IPN_TABLE_PREFIX' ) ) {
	define( 'IPN_TABLE_PREFIX', 'ipn_' );
}

IPN_Roles::remove_role();

if ( 'yes' === get_option( 'ipn_delete_data_on_uninstall' ) ) {
	IPN_Install::drop_tables();

	$options = array(
		'ipn_db_version',
		'ipn_otp_expiry_hours',
		'ipn_collection_window_days',
		'ipn_reminder_after_hours',
		'ipn_max_otp_attempts',
		'ipn_auto_cancel_enabled',
		'ipn_auto_refund_mode',
		'ipn_delete_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}
