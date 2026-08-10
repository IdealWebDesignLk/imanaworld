<?php
defined( 'ABSPATH' ) || exit;

/**
 * Deactivation intentionally keeps custom tables, options, and the branch
 * staff role in place — orders and stock data must survive a plugin update
 * that deactivates/reactivates. Full removal only happens in uninstall.php,
 * and only when the site owner opts in.
 */
class IPN_Deactivator {

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ipn_uncollected_orders_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ipn_uncollected_orders_check' );
		}

		flush_rewrite_rules();
	}
}
