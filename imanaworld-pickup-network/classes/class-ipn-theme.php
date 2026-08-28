<?php
defined( 'ABSPATH' ) || exit;

/**
 * The staff dashboard's colour scheme, as one admin-chosen colour plus
 * everything derived from it (issue #25).
 *
 * The dashboard's stylesheet is already written entirely against CSS custom
 * properties, so re-colouring it is a matter of redefining a handful of
 * variables rather than touching any rule. That is the whole point of this
 * class: an admin picks one colour, and the header, buttons, tabs, active
 * states, links and icons all follow, with no per-screen editing.
 *
 * Deriving the shades rather than asking for each one is deliberate. An admin
 * given eight colour pickers produces eight colours that do not belong to each
 * other; an admin given one produces a palette. The text colour that sits on
 * the brand is computed for contrast rather than chosen, so a light brand
 * colour cannot end up with unreadable white text on it.
 *
 * Status colours are deliberately NOT derived. "Disputed" has to stay red and
 * "Ready" has to stay distinguishable from "New" whatever the brand colour is
 * — those carry meaning, not branding.
 */
class IPN_Theme {

	const OPTION_PRIMARY  = 'ipn_staff_primary_color';
	const DEFAULT_PRIMARY = '#1b5e20';

	/**
	 * The admin's chosen colour, falling back to the shipped green whenever
	 * the stored value is missing or not a colour we can parse.
	 */
	public static function primary() {
		$hex = self::normalise_hex( get_option( self::OPTION_PRIMARY, self::DEFAULT_PRIMARY ) );

		return $hex ? $hex : self::DEFAULT_PRIMARY;
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
	 * brand colour lands on the readable side rather than the guessed one.
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
		$primary = self::primary();

		return array(
			// The dark band behind the top bar.
			'--brand-900' => self::shade( $primary, 0.32 ),
			// Buttons, active tab, the top bar itself.
			'--brand-700' => $primary,
			// Hover and the lighter half of the header.
			'--brand-600' => self::tint( $primary, 0.12 ),
			// Soft fills behind brand-coloured chips and rows.
			'--brand-100' => self::tint( $primary, 0.88 ),
			'--on-brand'  => self::readable_on( $primary ),
			'--focus'     => $primary,
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
		$primary = self::primary();

		// Nothing to override while the admin is still on the shipped colour;
		// emitting the defaults again would only be noise in the page.
		if ( self::DEFAULT_PRIMARY === $primary ) {
			return '';
		}

		$lines = array();

		foreach ( self::palette() as $name => $value ) {
			$lines[] = sprintf( "\t%s: %s;", $name, $value );
		}

		return ".ipn-staff-dashboard.ipn-staff-dashboard {\n" . implode( "\n", $lines ) . "\n}\n";
	}
}
