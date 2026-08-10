<?php
defined( 'ABSPATH' ) || exit;

/**
 * Composition root — instantiates every module, registers their hooks with
 * the loader, and boots the loader. This is the only class main plugin file
 * touches directly once WooCommerce/Dokan are confirmed active.
 */
class IPN_Init {

	protected $loader;

	public function __construct() {
		$this->loader = new IPN_Loader();
		$this->define_hooks();
	}

	protected function define_hooks() {
		IPN_Install::maybe_upgrade();

		$i18n = new IPN_I18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );

		$admin = new IPN_Admin();
		$admin->register_hooks( $this->loader );

		$public = new IPN_Public();
		$public->register_hooks( $this->loader );

		$storefront = new IPN_Storefront();
		$storefront->register_hooks( $this->loader );

		$checkout = new IPN_Checkout();
		$checkout->register_hooks( $this->loader );

		$my_account = new IPN_My_Account();
		$my_account->register_hooks( $this->loader );

		$staff_dashboard = new IPN_Staff_Dashboard();
		$staff_dashboard->register_hooks( $this->loader );

		$notifications = new IPN_Notifications();
		$notifications->register_hooks( $this->loader );

		$uncollected_workflow = new IPN_Uncollected_Workflow();
		$uncollected_workflow->register_hooks( $this->loader );
	}

	public function run() {
		$this->loader->run();
	}
}
