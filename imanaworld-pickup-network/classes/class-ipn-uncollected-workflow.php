<?php
defined( 'ABSPATH' ) || exit;

/**
 * Uncollected orders workflow (Section 3.11): reminder at reminder_after_hours
 * (past Ready), expiry at collection_window_days (also past Ready), stock
 * release, and the admin daily digest. Refund is always flagged for manual
 * review — confirmed decision, no auto-refund exists anywhere in this flow.
 * Driven by an hourly WP-Cron event.
 */
class IPN_Uncollected_Workflow {

	const CRON_HOOK        = 'ipn_uncollected_orders_check';
	const DIGEST_CRON_HOOK = 'ipn_daily_digest_email';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'init', $this, 'schedule_cron' );
		$loader->add_action( self::CRON_HOOK, $this, 'run_daily_check' );
	}

	/**
	 * Schedules both recurring jobs: the hourly reminder/expiry sweep, and
	 * the once-daily digest email (handled by IPN_Notifications::send_daily_digest(),
	 * which listens for DIGEST_CRON_HOOK — scheduling lives here since this
	 * class already owns "when do IPN's background jobs run").
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}

		if ( ! wp_next_scheduled( self::DIGEST_CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 6:00am' ), 'daily', self::DIGEST_CRON_HOOK );
		}
	}

	/**
	 * Walks Ready-for-Collection orders: sends the reminder once
	 * reminder_after_hours has elapsed since Ready, and auto-expires (stock
	 * released, refund flagged for manual review) once collection_window_days
	 * has elapsed.
	 */
	public function run_daily_check() {
		do_action( 'ipn_before_uncollected_check' );

		$this->send_reminders();
		$this->expire_overdue_orders();

		do_action( 'ipn_after_uncollected_check' );
	}

	/**
	 * Every ready order that hasn't been reminded yet, checked against its
	 * own branch's reminder_after_hours (branches can configure this
	 * differently — read live rather than snapshotted at Ready time).
	 */
	protected function send_reminders() {
		global $wpdb;

		$meta_table   = IPN_Order::table();
		$branch_table = IPN_Branch::table();

		$rows = $wpdb->get_results(
			"SELECT m.order_id, m.ready_at, b.reminder_after_hours
			 FROM {$meta_table} m
			 INNER JOIN {$branch_table} b ON b.id = m.branch_id
			 WHERE m.reminder_sent_at IS NULL AND m.ready_at IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		foreach ( $rows as $row ) {
			$order = wc_get_order( $row->order_id );

			if ( ! $order || 'ipn-ready' !== $order->get_status() ) {
				continue; // Collected, disputed, or expired since — nothing to remind.
			}

			$due_at = strtotime( $row->ready_at . ' UTC' ) + ( (int) $row->reminder_after_hours * HOUR_IN_SECONDS );

			if ( time() >= $due_at ) {
				do_action( 'ipn_order_collection_reminder', (int) $row->order_id );
			}
		}
	}

	/**
	 * Every ready order whose collection_window_expires (set once, at Ready
	 * time, from the branch's collection_window_days) has passed.
	 */
	protected function expire_overdue_orders() {
		global $wpdb;

		$meta_table = IPN_Order::table();

		$order_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT order_id FROM {$meta_table} WHERE collection_window_expires IS NOT NULL AND collection_window_expires <= %s", // phpcs:ignore WordPress.DB.PreparedSQL
			gmdate( 'Y-m-d H:i:s' )
		) );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order || 'ipn-ready' !== $order->get_status() ) {
				continue; // Already collected or disputed — window no longer applies.
			}

			$order->update_status( 'ipn-expired', __( 'Collection window expired — order auto-cancelled by IPN. Stock released; refund needs manual review.', 'ipn' ) );
		}
	}
}
