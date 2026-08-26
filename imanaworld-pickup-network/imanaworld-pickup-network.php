<?php
/**
 * Plugin Name:       IMANAWORLD Pickup Network
 * Plugin URI:         https://imanaworld.com
 * Description:        Click & Collect fulfilment network for IMANAWORLD — per-branch inventory, branch staff order dashboard, OTP collection verification, and operational reporting on top of WooCommerce/Dokan. Pilot partner: Choppies.
 * Version:            0.7.9
 * Requires PHP:       7.4
 * Requires Plugins:   woocommerce, dokan-lite
 * Author:             Ideal Web Design
 * Author URI:         https://idealwebdesign.lk
 * Text Domain:        ipn
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'IPN_VERSION', '0.7.9' );
define( 'IPN_DB_VERSION', '1.2.0' );
define( 'IPN_PLUGIN_FILE', __FILE__ );
define( 'IPN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IPN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IPN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'IPN_TABLE_PREFIX', 'ipn_' );

require_once IPN_PLUGIN_DIR . 'classes/class-ipn-loader.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-i18n.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-install.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-roles.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-activator.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-deactivator.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-vendor.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-access.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-branch.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-branch-stock.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-otp.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-audit-log.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-order.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-csv-import.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-uncollected-workflow.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-notifications.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-reports.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-storefront.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-checkout.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-my-account.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-staff-dashboard.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-vendor-dashboard.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-admin-context.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-admin.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-user-profile.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-pages.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-public.php';
require_once IPN_PLUGIN_DIR . 'classes/class-ipn-init.php';

/**
 * GitHub-based auto-updates. The plugin lives in a subdirectory of the
 * imanaworld repo (not at its root, and the repo has other content
 * alongside it), so this runs in "release assets" mode: it looks at
 * GitHub Releases rather than downloading the whole repo, and expects
 * each release to have a pre-built plugin zip attached — see
 * .github/workflows/release.yml, which builds and attaches that zip
 * automatically on every push to main. Wired up directly (not inside a
 * hook) per the library's own documented usage pattern.
 */
if ( file_exists( IPN_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once IPN_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	$ipn_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/IdealWebDesignLk/imanaworld/',
		IPN_PLUGIN_FILE,
		'imanaworld-pickup-network'
	);

	$ipn_update_checker->getVcsApi()->enableReleaseAssets();

	// The imanaworld repo is public, so no credential is needed for the
	// default case. If it's ever made private again, set this constant in
	// wp-config.php with a GitHub personal access token that has read
	// access to the repo — never hardcode a token in plugin source.
	if ( defined( 'IPN_GITHUB_TOKEN' ) && IPN_GITHUB_TOKEN ) {
		$ipn_update_checker->setAuthentication( IPN_GITHUB_TOKEN );
	}
}

register_activation_hook( __FILE__, array( 'IPN_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IPN_Deactivator', 'deactivate' ) );

/**
 * Boots the plugin once all plugins are loaded, so the WooCommerce/Dokan
 * dependency check below can see whether those plugins are active.
 */
function ipn_run() {
	if ( ! ipn_dependencies_met() ) {
		add_action( 'admin_notices', 'ipn_dependency_notice' );
		return;
	}

	$plugin = new IPN_Init();
	$plugin->run();
}
add_action( 'plugins_loaded', 'ipn_run' );

function ipn_dependencies_met() {
	return class_exists( 'WooCommerce' ) && function_exists( 'dokan' );
}

function ipn_dependency_notice() {
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'IMANAWORLD Pickup Network requires WooCommerce and Dokan to be installed and active.', 'ipn' );
	echo '</p></div>';
}
