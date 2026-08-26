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
		'ipn_pages_version',
		'ipn_rewrite_version',
	);

	// The auto-created staff dashboard page goes only under the same opt-in,
	// and only if it still carries nothing but our shortcode — an admin may
	// have built real content around it.
	$ipn_page_id = (int) get_option( 'ipn_staff_dashboard_page_id' );

	if ( $ipn_page_id ) {
		$ipn_page = get_post( $ipn_page_id );

		if ( $ipn_page && false !== strpos( $ipn_page->post_content, '[ipn_staff_dashboard]' )
			&& '' === trim( str_replace( '[ipn_staff_dashboard]', '', $ipn_page->post_content ) ) ) {
			wp_delete_post( $ipn_page_id, true );
		}

		delete_option( 'ipn_staff_dashboard_page_id' );
	}

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}
