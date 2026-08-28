<?php
defined( 'ABSPATH' ) || exit;
/**
 * The Sign Out control that sits at the top right of every staff screen.
 *
 * It replaced the "Open now / Closed now" pill that used to live here. That
 * pill only restated the branch's opening hours, which staff no longer set
 * (issue #26), and it was the thing people were reaching for when they wanted
 * to leave the dashboard (issue #28).
 *
 * One partial rather than three copies, so the control cannot drift between
 * screens.
 */
?>
<a class="ipn-sd-signout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
	<span class="ipn-sd-signout__ico" aria-hidden="true">&#8617;</span>
	<span><?php esc_html_e( 'Sign Out', 'ipn' ); ?></span>
</a>
