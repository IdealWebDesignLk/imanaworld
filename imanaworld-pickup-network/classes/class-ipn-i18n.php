<?php
defined( 'ABSPATH' ) || exit;

class IPN_I18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'ipn',
			false,
			dirname( IPN_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
