<?php
defined( 'ABSPATH' ) || exit;
/**
 * The staff dashboard's bottom tab bar, shared by every screen so a new tab
 * only has to be added once.
 *
 * Each screen sets $ipn_active_tab before including this; anything that
 * doesn't is treated as the queue, which is the dashboard's home screen.
 *
 * @var string $ipn_active_tab queue | stock
 * @var int    $new_count      Optional: unaccepted orders, badged on Queue.
 */

$ipn_active_tab = isset( $ipn_active_tab ) ? $ipn_active_tab : 'queue';
$ipn_new_count  = isset( $new_count ) ? (int) $new_count : 0;

$ipn_tabs = array(
	'queue' => array(
		'url'  => IPN_Staff_Dashboard::screen_url( 'queue' ),
		'icon' => '&#128203;',
		'text' => __( 'Queue', 'ipn' ),
	),
	'stock' => array(
		'url'  => IPN_Staff_Dashboard::screen_url( 'stock' ),
		'icon' => '&#128230;',
		'text' => __( 'Branch stock', 'ipn' ),
	),
);
?>
<div class="tabbar">
	<?php foreach ( $ipn_tabs as $ipn_key => $ipn_tab ) : ?>
		<a class="tab<?php echo $ipn_active_tab === $ipn_key ? ' active' : ''; ?>" href="<?php echo $ipn_tab['url']; // phpcs:ignore WordPress.Security.EscapeOutput -- screen_url() returns an escaped URL. ?>">
			<span class="tab-ico" aria-hidden="true"><?php echo esc_html( html_entity_decode( $ipn_tab['icon'], ENT_QUOTES, 'UTF-8' ) ); ?></span>
			<span class="tab-lbl"><?php echo esc_html( $ipn_tab['text'] ); ?></span>
			<?php if ( 'queue' === $ipn_key && $ipn_new_count > 0 ) : ?>
				<span class="tab-badge"><?php echo esc_html( $ipn_new_count ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
