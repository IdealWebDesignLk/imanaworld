<?php
defined( 'ABSPATH' ) || exit;
/**
 * Wrapper for the "Click & Collect" section of Dokan's vendor dashboard —
 * see IPN_Vendor_Dashboard::load_template(). Renders our own tab strip and
 * hands off to the tab partial; Dokan supplies everything around it.
 *
 * @var int                  $vendor_id
 * @var string               $tab       One of IPN_Vendor_Dashboard::TABS.
 * @var string|WP_Error|null $result    Outcome of a write posted this request.
 * @var array                $branches  Every branch belonging to this vendor.
 * @var array                $data      Tab-specific data.
 */

// Every value in $data was already scoped to this vendor in tab_data().
extract( $data ); // phpcs:ignore WordPress.PHP.DontExtract

$ipn_tabs = array(
	'branches' => __( 'Branches', 'ipn' ),
	'staff'    => __( 'Staff', 'ipn' ),
	'stock'    => __( 'Branch stock', 'ipn' ),
	'orders'   => __( 'Orders', 'ipn' ),
);
?>
<div class="ipn-vd">
	<div class="ipn-vd__head">
		<h2 class="ipn-vd__title"><?php esc_html_e( 'Click & Collect', 'ipn' ); ?></h2>
		<p class="ipn-vd__sub">
			<?php esc_html_e( 'Your pickup branches, the staff who run them, what each one has in stock, and the orders waiting to be collected.', 'ipn' ); ?>
		</p>
	</div>

	<?php if ( $result instanceof WP_Error ) : ?>
		<div class="ipn-vd__notice ipn-vd__notice--error"><?php echo esc_html( $result->get_error_message() ); ?></div>
	<?php elseif ( is_string( $result ) && '' !== $result ) : ?>
		<div class="ipn-vd__notice ipn-vd__notice--ok"><?php echo esc_html( $result ); ?></div>
	<?php endif; ?>

	<nav class="ipn-vd__tabs">
		<?php foreach ( $ipn_tabs as $ipn_key => $ipn_label ) : ?>
			<a
				class="ipn-vd__tab<?php echo $tab === $ipn_key ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( $ipn_key ) ); ?>"
			><?php echo esc_html( $ipn_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( empty( $branches ) && 'branches' !== $tab ) : ?>
		<div class="ipn-vd__empty">
			<p><?php esc_html_e( 'You have no branches yet, so there is nothing to show here.', 'ipn' ); ?></p>
			<a class="ipn-vd__btn ipn-vd__btn--primary" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'branches' ) ); ?>">
				<?php esc_html_e( 'Add your first branch', 'ipn' ); ?>
			</a>
		</div>
	<?php else : ?>
		<?php include IPN_PLUGIN_DIR . 'templates/vendor/tab-' . $tab . '.php'; ?>
	<?php endif; ?>
</div>
