<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level "IPN" wp-admin menu and its submenus (Dashboard,
 * Partners, Branches, Staff, Stock, Catalogue Import, Orders, Disputes &
 * Returns, Daily Digest, Audit Trail, Reports, Settings). Each submenu
 * renders a template under templates/admin/ with real data where a backing
 * data model already exists; screens with no data model yet (Disputes,
 * Digest, most of Reports) render an honest empty state instead.
 */
class IPN_Admin {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_menu' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
	}

	public function register_menu() {
		$capability = 'manage_woocommerce';

		add_menu_page(
			__( 'IPN', 'ipn' ),
			__( 'IPN', 'ipn' ),
			$capability,
			'ipn-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-store',
			56
		);

		$submenus = array(
			'ipn-dashboard'   => __( 'Dashboard', 'ipn' ),
			'ipn-partners'    => __( 'Partners', 'ipn' ),
			'ipn-branches'    => __( 'Branches', 'ipn' ),
			'ipn-staff'       => __( 'Staff', 'ipn' ),
			'ipn-stock'       => __( 'Stock', 'ipn' ),
			'ipn-import'      => __( 'Catalogue Import', 'ipn' ),
			'ipn-orders'      => __( 'Orders & Disputes', 'ipn' ),
			'ipn-disputes'    => __( 'Disputes & Returns', 'ipn' ),
			'ipn-digest'      => __( 'Daily Digest', 'ipn' ),
			'ipn-audit-log'   => __( 'Audit Trail', 'ipn' ),
			'ipn-reports'     => __( 'Reports', 'ipn' ),
			'ipn-settings'    => __( 'Settings', 'ipn' ),
		);

		foreach ( $submenus as $slug => $label ) {
			$callback = 'render_' . str_replace( array( 'ipn-', '-' ), array( '', '_' ), $slug );

			add_submenu_page(
				'ipn-dashboard',
				$label,
				$label,
				$capability,
				$slug,
				array( $this, $callback )
			);
		}
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'ipn-' ) === false && 'toplevel_page_ipn-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ipn-admin', IPN_PLUGIN_URL . 'assets/css/admin.css', array(), IPN_VERSION );
		wp_enqueue_script( 'ipn-admin', IPN_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), IPN_VERSION, true );
	}

	protected function view( $template, array $vars = array() ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include IPN_PLUGIN_DIR . 'templates/admin/' . $template . '.php';
	}

	public function render_dashboard() {
		$this->view( 'dashboard' );
	}

	public function render_partners() {
		$this->view( 'partners' );
	}

	public function render_branches() {
		$this->view( 'branches', array( 'branches' => IPN_Branch::get_all() ) );
	}

	public function render_staff() {
		$this->view( 'staff' );
	}

	public function render_stock() {
		$this->view( 'stock', array( 'branches' => IPN_Branch::get_all() ) );
	}

	public function render_import() {
		$this->view( 'import', array( 'recent_runs' => IPN_CSV_Import::get_recent_runs() ) );
	}

	public function render_orders() {
		$this->view( 'orders' );
	}

	public function render_disputes() {
		$this->view( 'disputes' );
	}

	public function render_digest() {
		$this->view( 'digest' );
	}

	public function render_audit_log() {
		$this->view( 'audit-log', array(
			'entries'  => IPN_Audit_Log::query(),
			'branches' => IPN_Branch::get_all(),
		) );
	}

	public function render_reports() {
		$this->view( 'reports' );
	}

	public function render_settings() {
		$this->view( 'settings' );
	}
}
