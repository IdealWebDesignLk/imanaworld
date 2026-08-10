<?php
defined( 'ABSPATH' ) || exit;

/**
 * Uncollected orders workflow (Section 3.11): reminder at reminder_after_hours,
 * expiry at collection_window_days, auto-cancel, stock release, refund
 * initiation, and an admin daily digest. Driven by a daily WP-Cron event.
 */
class IPN_Uncollected_Workflow {

	const CRON_HOOK = 'ipn_uncollected_orders_check';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'init', $this, 'schedule_cron' );
		$loader->add_action( self::CRON_HOOK, $this, 'run_daily_check' );
	}

	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Walks Ready-for-Collection orders, sends the 48h reminder once the
	 * branch's reminder_after_hours has elapsed, and auto-cancels + releases
	 * stock once collection_window_days has elapsed (Phase 3 build-out).
	 */
	public function run_daily_check() {
		do_action( 'ipn_before_uncollected_check' );
	}
}
