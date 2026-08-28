<?php
defined( 'ABSPATH' ) || exit;

/**
 * Email OTP generation and collection verification (ipn_otp_codes).
 *
 * Verification still runs against a one-way hash, exactly as before. Since
 * 0.9.4 the code is ALSO kept in a recoverable form, because the vendor and
 * branch staff are shown it on the order (issue #30). That is a deliberate
 * decision by the site owner and it does weaken the control: a code the
 * counter can read is a code the counter can use without the customer being
 * there. What follows makes that storage as narrow as it can be —
 *
 *   - it is encrypted with a key derived from wp_salt(), which lives in
 *     wp-config.php and not in the database, so a leaked database dump on its
 *     own does not yield any codes;
 *   - a superseded code is wiped when its replacement is issued, so only the
 *     current one per order is ever recoverable;
 *   - if the server has no OpenSSL, nothing is stored rather than falling
 *     back to plain text, and the screens say the code is unavailable.
 *
 * The hash remains the only thing verification consults, so none of this
 * changes what it takes to complete a collection.
 */
class IPN_OTP {

	public static function table() {
		return IPN_Install::table( 'otp_codes' );
	}

	/**
	 * The key codes are encrypted under. Derived from the site's auth salt, so
	 * it is unique per site and stored outside the database.
	 */
	protected static function crypto_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|ipn-otp', true );
	}

	protected static function can_encrypt() {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& function_exists( 'random_bytes' );
	}

	/**
	 * @return string Base64 of iv+ciphertext, or '' when unavailable.
	 */
	public static function encrypt_code( $code ) {
		if ( ! self::can_encrypt() ) {
			return '';
		}

		try {
			$iv = random_bytes( 16 );
		} catch ( Exception $e ) {
			return '';
		}

		$cipher = openssl_encrypt( (string) $code, 'aes-256-cbc', self::crypto_key(), OPENSSL_RAW_DATA, $iv );

		return false === $cipher ? '' : base64_encode( $iv . $cipher );
	}

	/**
	 * @return string The code, or '' if it cannot be recovered — which is the
	 *                normal outcome for codes issued before 0.9.4, and for a
	 *                site whose salts have been rotated since.
	 */
	public static function decrypt_code( $stored ) {
		if ( '' === (string) $stored || null === $stored || ! self::can_encrypt() ) {
			return '';
		}

		$raw = base64_decode( (string) $stored, true );

		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}

		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::crypto_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );

		return false === $plain ? '' : $plain;
	}

	public static function generate( $order_id, $branch_id ) {
		global $wpdb;
		$table = self::table();

		// A resend rotates the code — the previous one must stop verifying, and
		// its recoverable copy is wiped at the same time so only the current
		// code for an order is ever readable.
		$wpdb->update( $table, array( 'status' => 'superseded', 'otp_code_enc' => null ), array( // phpcs:ignore WordPress.DB.PreparedSQL
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
			'otp_code_enc' => self::encrypt_code( $code ),
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
	 * ->code carries the collection code itself where it can be recovered, so
	 * the vendor and branch staff can be shown it (issue #30). It is '' for
	 * codes issued before 0.9.4, on a server without OpenSSL, and for any code
	 * that has been superseded — all of which the screens handle by saying the
	 * code is not available and offering a resend.
	 *
	 * otp_hash stays out of the SELECT regardless: it is of no use to a caller
	 * and there is no reason to move it around.
	 *
	 * @return object|null
	 */
	public static function status_for( $order_id ) {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT id, branch_id, status, created_at, expires_at, verified_at, failed_attempts, otp_code_enc
			   FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
			(int) $order_id
		) );

		if ( ! $row ) {
			return null;
		}

		$row->code = self::decrypt_code( isset( $row->otp_code_enc ) ? $row->otp_code_enc : '' );
		unset( $row->otp_code_enc );

		return $row;
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
