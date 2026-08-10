<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer email notifications (Section 3.8) — email only, no SMS (confirmed
 * decision, see IPN_Project_Context.md). Each method renders the matching
 * template under templates/emails/ and sends it to the order's billing
 * email. Fired off the real ipn_order_* hooks IPN_Order dispatches.
 *
 * The collection code (OTP) is only ever emailed once, at Ready for
 * Collection — IPN_OTP stores it hash-only, so there is no plaintext copy
 * left to redisplay later. If a customer needs it resent (lost the email,
 * reminder window, etc.), IPN_Notifications::resend() rotates the code
 * (the old one stops verifying) and emails the new one the same way.
 */
class IPN_Notifications {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'ipn_order_placed', $this, 'send_order_placed' );
		$loader->add_action( 'ipn_order_accepted', $this, 'send_order_accepted' );
		$loader->add_action( 'ipn_order_ready_for_collection', $this, 'send_ready_for_collection', 10, 2 );
		$loader->add_action( 'ipn_order_collection_reminder', $this, 'send_collection_reminder' );
		$loader->add_action( 'ipn_order_collected', $this, 'send_order_collected' );
		$loader->add_action( 'ipn_order_cancelled', $this, 'send_order_cancelled', 10, 2 );
		$loader->add_action( 'ipn_order_disputed', $this, 'send_dispute_alert' );
		$loader->add_action( IPN_Uncollected_Workflow::DIGEST_CRON_HOOK, $this, 'send_daily_digest' );
	}

	/**
	 * The once-a-day cron-fired digest — silently does nothing when there's
	 * nothing to report, rather than emailing an empty "0 orders" digest
	 * every single day.
	 */
	public function send_daily_digest() {
		$content = self::build_digest_content();

		if ( ! $content['count'] ) {
			return;
		}

		wp_mail( get_option( 'admin_email' ), $content['subject'], $content['body'] );
	}

	/**
	 * Shared by the cron send above and IPN_Admin's "Preview digest email"
	 * link, so the preview always shows exactly what would actually be
	 * sent.
	 *
	 * @return array{subject:string,body:string,count:int}
	 */
	public static function build_digest_content() {
		$expired = IPN_Order::expired_since( gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ) );

		$subject = sprintf(
			/* translators: %d: number of orders that expired uncollected in the last 24 hours */
			_n( '[IPN] Daily digest — %d order expired uncollected', '[IPN] Daily digest — %d orders expired uncollected', count( $expired ), 'ipn' ),
			count( $expired )
		);

		if ( ! $expired ) {
			return array(
				'subject' => $subject,
				'body'    => __( 'No orders expired uncollected in the last 24 hours.', 'ipn' ),
				'count'   => 0,
			);
		}

		$lines = array();

		foreach ( $expired as $row ) {
			$lines[] = sprintf(
				'%s — %s (%s) — BWP %s — %s',
				$row->order_number,
				$row->customer_name,
				$row->branch_name,
				number_format( (float) $row->total, 2 ),
				$row->refunded ? __( 'Refunded', 'ipn' ) : __( 'Needs refund review', 'ipn' )
			);
		}

		$body = __( "Orders that expired uncollected in the last 24 hours:\n\n", 'ipn' )
			. implode( "\n", $lines )
			. "\n\n" . sprintf(
				/* translators: %s: URL to the admin Daily Digest screen */
				__( 'Review: %s', 'ipn' ),
				admin_url( 'admin.php?page=ipn-digest' )
			);

		return array(
			'subject' => $subject,
			'body'    => $body,
			'count'   => count( $expired ),
		);
	}

	public function send_order_placed( $order_id ) {
		$context = $this->context( $order_id );
		if ( ! $context ) {
			return;
		}

		$this->send(
			$context['order'],
			__( 'Order received — thanks for shopping Click & Collect', 'ipn' ),
			'order-placed',
			array(
				'order'  => $context['order'],
				'branch' => $context['branch'],
			)
		);
	}

	public function send_order_accepted( $order_id ) {
		$context = $this->context( $order_id );
		if ( ! $context ) {
			return;
		}

		$this->send(
			$context['order'],
			__( 'Your order is being prepared', 'ipn' ),
			'order-accepted',
			array(
				'order'  => $context['order'],
				'branch' => $context['branch'],
			)
		);
	}

	/**
	 * The primary collection-code email. $otp is the plaintext code, passed
	 * straight through from IPN_Order::on_ready() in the same request it
	 * was generated in — it is never stored anywhere in plaintext.
	 */
	public function send_ready_for_collection( $order_id, $otp = '' ) {
		$context = $this->context( $order_id );
		if ( ! $context ) {
			return;
		}

		$this->send(
			$context['order'],
			__( 'Ready for collection — here is your code', 'ipn' ),
			'ready-for-collection',
			array(
				'order'  => $context['order'],
				'branch' => $context['branch'],
				'otp'    => $otp,
			)
		);
	}

	/**
	 * Fired by IPN_Uncollected_Workflow's cron once reminder_after_hours has
	 * elapsed on a Ready order. Rotates the OTP (see class docblock) and
	 * sends the reminder with the fresh code.
	 */
	public function send_collection_reminder( $order_id ) {
		$context = $this->context( $order_id );
		if ( ! $context || ! $context['meta']->branch_id ) {
			return;
		}

		$otp = IPN_OTP::generate( $order_id, $context['meta']->branch_id );

		$this->send(
			$context['order'],
			__( "Reminder — don't forget to collect your order", 'ipn' ),
			'collection-reminder',
			array(
				'order'  => $context['order'],
				'branch' => $context['branch'],
				'otp'    => $otp,
			)
		);

		global $wpdb;
		$wpdb->update( IPN_Order::table(), array( 'reminder_sent_at' => current_time( 'mysql' ) ), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public function send_order_collected( $order_id ) {
		$context = $this->context( $order_id );
		if ( ! $context ) {
			return;
		}

		$this->send(
			$context['order'],
			__( 'Order complete — thank you', 'ipn' ),
			'order-collected',
			array(
				'order'  => $context['order'],
				'branch' => $context['branch'],
			)
		);
	}

	public function send_order_cancelled( $order_id, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$meta   = IPN_Order::get_meta( $order_id );
		$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;

		$this->send(
			$order,
			__( 'Your order has been cancelled', 'ipn' ),
			'order-cancelled',
			array(
				'order'  => $order,
				'branch' => $branch,
				'reason' => $reason,
			)
		);
	}

	/**
	 * IMANAWORLD-facing (not customer) alert when a branch rejects a
	 * collection — previously only the audit log recorded this, so nobody
	 * was actually told a refund needed reviewing until they happened to
	 * open the Disputes screen. Plain text, not the branded customer
	 * templates — this is an internal ops alert, not a customer email.
	 */
	public function send_dispute_alert( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$meta   = IPN_Order::get_meta( $order_id );
		$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;
		$reason = $meta ? IPN_Order::dispute_reason_label( $meta->dispute_reason ) : '';

		$subject = sprintf(
			/* translators: %s: order number */
			__( '[IPN] Collection disputed — order %s', 'ipn' ),
			$order->get_order_number()
		);

		$body = sprintf(
			/* translators: 1: order number, 2: branch name, 3: customer name, 4: dispute reason, 5: order edit URL */
			__( "Order %1\$s was rejected at collection and needs review.\n\nBranch: %2\$s\nCustomer: %3\$s\nReason: %4\$s\n\nRefunds are always manual — review and refund here:\n%5\$s\n", 'ipn' ),
			$order->get_order_number(),
			$branch ? $branch->name : '—',
			IPN_Order::customer_name( $order ),
			$reason ? $reason : '—',
			$order->get_edit_order_url()
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}

	/**
	 * Manual resend — called from the staff dashboard's "Resend collection
	 * code" action and the "Resend IPN collection code" WooCommerce admin
	 * order action (see IPN_Admin). Callable statically since neither
	 * caller holds an IPN_Notifications instance.
	 *
	 * @param int $order_id
	 * @return bool True if a code was generated and emailed.
	 */
	public static function resend( $order_id ) {
		$meta = IPN_Order::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return false;
		}

		$instance = new self();
		$otp      = IPN_OTP::generate( $order_id, $meta->branch_id );
		$instance->send_ready_for_collection( $order_id, $otp );

		return true;
	}

	protected function context( $order_id ) {
		$order = wc_get_order( $order_id );
		$meta  = IPN_Order::get_meta( $order_id );

		if ( ! $order || ! $meta ) {
			return null;
		}

		return array(
			'order'  => $order,
			'meta'   => $meta,
			'branch' => $meta->branch_id ? IPN_Branch::get( $meta->branch_id ) : null,
		);
	}

	protected function send( $order, $subject, $template, array $vars ) {
		$to = $order->get_billing_email();

		if ( ! $to ) {
			return;
		}

		$body = $this->render_template( $template, $vars );

		if ( ! $body ) {
			return;
		}

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		wp_mail( $to, $subject, $body );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
	}

	public function html_content_type() {
		return 'text/html';
	}

	protected function render_template( $template, array $vars = array() ) {
		$path = IPN_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		ob_start();
		include $path;
		return ob_get_clean();
	}
}
