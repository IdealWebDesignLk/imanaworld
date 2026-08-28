<?php
defined( 'ABSPATH' ) || exit;

/**
 * Email OTP generation and collection verification (ipn_otp_codes).
 * Only the hash is stored — the plain code exists only in memory long
 * enough to be emailed to the customer, mirroring how you'd store a password.
 */
class IPN_OTP {

	public static function table() {
		return IPN_Install::table( 'otp_codes' );
	}

	public static function generate( $order_id, $branch_id ) {
		global $wpdb;
		$table = self::table();

		// A resend rotates the code — the previous one must stop verifying.
		$wpdb->update( $table, array( 'status' => 'superseded' ), array( // phpcs:ignore WordPress.DB.PreparedSQL
			'order_id' => (int) $order_id,
			'status'   => 'active',
		) );

		$code       = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$branch     = IPN_Branch::get( $branch_id );
		$expiry_hrs = $branch ? (int) $branch->otp_expiry_hours : (int) get_option( 'ipn_otp_expiry_hours', 72 );

		$wpdb->insert( self::table(), array(
			'order_id'   => (int) $order_id,
			'branch_id'  => (int) $branch_id,
			'otp_hash'   => wp_hash_password( $code ),
			'status'     => 'active',
			'created_at' => current_time( 'mysql' ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $expiry_hrs . ' hours', current_time( 'timestamp', true ) ) ),
		) );

		IPN_Audit_Log::log( 'otp_generated', array(
			'order_id'  => $order_id,
			'branch_id' => $branch_id,
		) );

		return $code;
	}

	/**
	 * The most recent collection code's lifecycle for an order — when it was
	 * issued, when it expires, whether it has been used, and how many wrong
	 * entries it has taken (issue #30).
	 *
	 * otp_hash is deliberately excluded from the SELECT. The code itself is
	 * stored the way a password is, so there is nothing here to reveal even by
	 * accident, and a caller cannot leak one by passing this row somewhere it
	 * should not go.
	 *
	 * @return object|null
	 */
	public static function status_for( $order_id ) {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT id, branch_id, status, created_at, expires_at, verified_at, failed_attempts
			   FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
			(int) $order_id
		) );

		return $row ? $row : null;
	}

	public static function verify( $order_id, $submitted_code, $staff_user_id = 0 ) {
		global $wpdb;
		$table = self::table();

		$otp = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE order_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
			$order_id
		) );

		if ( ! $otp ) {
			return new WP_Error( 'ipn_otp_not_found', __( 'No active collection code for this order.', 'ipn' ) );
		}

		if ( strtotime( $otp->expires_at ) < time() ) {
			$wpdb->update( $table, array( 'status' => 'expired' ), array( 'id' => $otp->id ) );
			return new WP_Error( 'ipn_otp_expired', __( 'Collection code has expired.', 'ipn' ) );
		}

		$wp_hasher = new PasswordHash( 8, true );

		if ( ! $wp_hasher->CheckPassword( $submitted_code, $otp->otp_hash ) ) {
			$attempts = (int) $otp->failed_attempts + 1;
			$wpdb->update( $table, array( 'failed_attempts' => $attempts ), array( 'id' => $otp->id ) );

			IPN_Audit_Log::log( 'otp_verify_failed', array(
				'order_id'  => $order_id,
				'branch_id' => $otp->branch_id,
				'actor_id'  => $staff_user_id,
				'data'      => array( 'attempt' => $attempts ),
			) );

			if ( $attempts >= (int) get_option( 'ipn_max_otp_attempts', 3 ) ) {
				do_action( 'ipn_otp_max_attempts_reached', $order_id, $otp->branch_id );
			}

			return new WP_Error( 'ipn_otp_mismatch', __( 'Incorrect collection code.', 'ipn' ) );
		}

		$wpdb->update( $table, array(
			'status'      => 'used',
			'verified_at' => current_time( 'mysql' ),
		), array( 'id' => $otp->id ) );

		IPN_Audit_Log::log( 'otp_verify_success', array(
			'order_id'  => $order_id,
			'branch_id' => $otp->branch_id,
			'actor_id'  => $staff_user_id,
		) );

		return true;
	}
}
