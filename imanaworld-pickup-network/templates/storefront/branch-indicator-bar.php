<?php
defined( 'ABSPATH' ) || exit;
/** @var object $branch */
/** @var string $change_url */
?>
<div class="ipn-branch-indicator-bar">
	<span class="ipn-branch-indicator-bar__text">
		<?php esc_html_e( 'Shopping at', 'ipn' ); ?>
		<b><?php echo esc_html( $branch->name ); ?></b>
	</span>
	<a href="<?php echo esc_url( $change_url ); ?>" class="ipn-branch-indicator-bar__change">
		<?php esc_html_e( 'Change branch', 'ipn' ); ?>
	</a>
</div>
