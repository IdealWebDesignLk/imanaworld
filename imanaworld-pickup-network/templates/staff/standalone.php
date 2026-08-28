<?php
defined( 'ABSPATH' ) || exit;
/**
 * The staff dashboard's own page shell (issue #25).
 *
 * Branch staff work this screen standing at a counter, often on a phone. The
 * shop's header, mega-menu, breadcrumbs, newsletter block and footer are all
 * noise there, and they pushed the dashboard itself into a small card floating
 * in the middle of a very tall page.
 *
 * So this page does not use the theme's template at all. It is a complete
 * document of its own, and the only thing in the body is the dashboard.
 * wp_head() and wp_footer() still run, because WordPress, WooCommerce and the
 * plugin all put things there that have to load — this replaces the theme's
 * chrome, not WordPress's.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<meta name="robots" content="noindex, nofollow" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'ipn-sd-standalone' ); ?>>
<?php
while ( have_posts() ) {
	the_post();

	// the_content() rather than the shortcode directly: the page belongs to
	// the site's admin, and whatever they have put on it should still render.
	the_content();
}

wp_footer();
?>
</body>
</html>
