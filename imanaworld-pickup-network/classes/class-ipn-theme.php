<?php
defined( 'ABSPATH' ) || exit;

/**
 * The staff dashboard's colour scheme — three admin-chosen colours, and
 * everything derived from them (issue #25).
 *
 * The dashboard's stylesheet is written entirely against CSS custom
 * properties, so re-colouring it is a matter of redefining a handful of
 * variables rather than touching any rule. Each colour owns a role:
 *
 *   Primary   the surfaces you look at — top bar, active tab, links, headings
 *   Secondary the things you press — buttons and the marks that lead to them
 *   Accent    the quiet highlights — soft fills behind chips and notes, and
 *             the focus ring
 *
 * The text colour that sits on primary and on secondary is computed for
 * contrast rather than chosen, so no combination of three colours can produce
 * unreadable text on a button or a header. That matters more here than with
 * one colour: an admin who picks a pale secondary would otherwise get white
 * text on a pale button and no warning.
 *
 * Status colours are deliberately NOT derived. "Disputed" has to stay red and
 * "Ready" has to stay distinguishable from "New" whatever the brand colours
 * are — those carry meaning, not branding.
 */
class IPN_Theme {

	const OPTION_PRIMARY   = 'ipn_staff_primary_color';
	const OPTION_SECONDARY = 'ipn_staff_secondary_color';
	const OPTION_ACCENT    = 'ipn_staff_accent_color';

	const DEFAULT_PRIMARY   = '#1b5e20';
	const DEFAULT_SECONDARY = '#2e7d32';
	const DEFAULT_ACCENT    = '#2e7d32';

	/**
	 * The three settings, as role => colour, each falling back to the shipped
	 * value when the stored one is missing or is not a colour we can parse.
	 *
	 * @return array
	 */
	public static function colours() {
		return array(
			'primary'   => self::colour( self::OPTION_PRIMARY, self::DEFAULT_PRIMARY ),
			'secondary' => self::colour( self::OPTION_SECONDARY, self::DEFAULT_SECONDARY ),
			'accent'    => self::colour( self::OPTION_ACCENT, self::DEFAULT_ACCENT ),
		);
	}

	protected static function colour( $option, $fallback ) {
		$hex = self::normalise_hex( get_option( $option, $fallback ) );

		return $hex ? $hex : $fallback;
	}

	public static function primary() {
		return self::colour( self::OPTION_PRIMARY, self::DEFAULT_PRIMARY );
	}

	public static function secondary() {
		return self::colour( self::OPTION_SECONDARY, self::DEFAULT_SECONDARY );
	}

	public static function accent() {
		return self::colour( self::OPTION_ACCENT, self::DEFAULT_ACCENT );
	}

	/**
	 * Accepts "#abc", "abc", "#AABBCC" or "aabbcc" and returns "#aabbcc".
	 * Returns '' for anything else, which is what makes this safe to run over
	 * a posted value before it reaches the database or a stylesheet.
	 *
	 * @return string
	 */
	public static function normalise_hex( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( '#' !== substr( $value, 0, 1 ) ) {
			$value = '#' . $value;
		}

		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m ) ) {
			$value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * @return int[] [r, g, b]
	 */
	protected static function to_rgb( $hex ) {
		$hex = self::normalise_hex( $hex );
		$hex = $hex ? $hex : self::DEFAULT_PRIMARY;

		return array(
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) ),
		);
	}

	protected static function to_hex( array $rgb ) {
		$out = '#';

		foreach ( $rgb as $channel ) {
			$out .= str_pad( dechex( max( 0, min( 255, (int) round( $channel ) ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Blends $hex towards $target by $weight (0 = unchanged, 1 = fully $target).
	 */
	protected static function mix( $hex, array $target, $weight ) {
		$rgb    = self::to_rgb( $hex );
		$weight = max( 0, min( 1, (float) $weight ) );
		$out    = array();

		foreach ( $rgb as $i => $channel ) {
			$out[] = $channel + ( ( $target[ $i ] - $channel ) * $weight );
		}

		return self::to_hex( $out );
	}

	public static function shade( $hex, $amount ) {
		return self::mix( $hex, array( 0, 0, 0 ), $amount );
	}

	public static function tint( $hex, $amount ) {
		return self::mix( $hex, array( 255, 255, 255 ), $amount );
	}

	/**
	 * WCAG relative luminance, used to decide what colour text can sit on a
	 * given background.
	 */
	protected static function luminance( $hex ) {
		$channels = array();

		foreach ( self::to_rgb( $hex ) as $channel ) {
			$c          = $channel / 255;
			$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}

	/**
	 * White or near-black, whichever contrasts better against $hex. Picking
	 * by contrast ratio rather than a brightness threshold means a mid-tone
	 * colour lands on the readable side rather than the guessed one.
	 */
	public static function readable_on( $hex ) {
		$lum = self::luminance( $hex );

		$against_white = 1.05 / ( $lum + 0.05 );
		$against_black = ( $lum + 0.05 ) / 0.05;

		return $against_white >= $against_black ? '#ffffff' : '#1c1b18';
	}

	/**
	 * The variables the staff stylesheet reads, as name => colour.
	 *
	 * @return array
	 */
	public static function palette() {
		$c = self::colours();

		return array(
			// Kept in step with primary so anything reaching for the darker
			// brand shade stays in the same family.
			'--brand-900'    => self::shade( $c['primary'], 0.32 ),
			// Top bar, active tab, links, the logo mark.
			'--brand-700'    => $c['primary'],
			// Buttons and the marks that lead to them.
			'--brand-600'    => $c['secondary'],
			// Soft fills behind chips, customer notes and the code panel.
			'--brand-100'    => self::tint( $c['accent'], 0.88 ),
			// Text that sits on each of those two, by contrast rather than by
			// assumption — a pale secondary must not keep white button text.
			'--on-brand'     => self::readable_on( $c['primary'] ),
			'--on-brand-600' => self::readable_on( $c['secondary'] ),
			'--focus'        => $c['accent'],
		);
	}

	/**
	 * The palette as a stylesheet, for wp_add_inline_style().
	 *
	 * The selector matches the one the base stylesheet defines these
	 * variables on, and the inline style is appended after it, so equal
	 * specificity resolves in this block's favour on source order.
	 *
	 * @return string
	 */
	public static function css_variables() {
		$c = self::colours();

		// Nothing to override while the admin is still on the shipped
		// colours; emitting the defaults again would only be noise, and the
		// base stylesheet's own values are what the design was drawn against.
		if ( self::DEFAULT_PRIMARY === $c['primary']
			&& self::DEFAULT_SECONDARY === $c['secondary']
			&& self::DEFAULT_ACCENT === $c['accent'] ) {
			return '';
		}

		$lines = array();

		foreach ( self::palette() as $name => $value ) {
			$lines[] = sprintf( "\t%s: %s;", $name, $value );
		}

		return ".ipn-staff-dashboard.ipn-staff-dashboard {\n" . implode( "\n", $lines ) . "\n}\n";
	}
}
