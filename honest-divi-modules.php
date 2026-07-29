<?php
/**
 * Plugin Name: Honest Divi Modules
 * Description: Custom Divi Builder modules for the Honest Health site.
 * Version:     1.0.1
 * Author:      Honest Health
 * Text Domain: honest-divi-modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HONEST_DIVI_MODULES_VERSION', '1.7.0' );
define( 'HONEST_DIVI_MODULES_DIR', plugin_dir_path( __FILE__ ) );
define( 'HONEST_DIVI_MODULES_URL', plugin_dir_url( __FILE__ ) );

require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/team-settings.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/member-fields.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/slug-migration.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/data/team-data.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/animation.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/media-placeholder.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/card-chevron.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/member-card.php';
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/article-card.php';

/**
 * Registered modules, as directory name => class name.
 *
 * Each entry maps to includes/modules/{$dir}/{$dir}.php. Add a module by
 * dropping in its directory and adding one line here.
 *
 * @return array
 */
function honest_divi_modules_map() {
	return array(
		'TextHero'            => 'Honest_Divi_Module_Text_Hero',
		'ExecutiveLeadership' => 'Honest_Divi_Module_Executive_Leadership',
		'LeadershipByMarket'  => 'Honest_Divi_Module_Leadership_By_Market',
		'Testimonials'        => 'Honest_Divi_Module_Testimonials',
		'FeaturedInsights'    => 'Honest_Divi_Module_Featured_Insights',
		'CallToAction'        => 'Honest_Divi_Module_Call_To_Action',
		'TeamMemberHeader'    => 'Honest_Divi_Module_Team_Member_Header',
	);
}

/**
 * Load and register the modules once the builder is ready.
 *
 * ET_Builder_Element::__construct() calls add_shortcode(), so instantiating
 * the class is what registers the module with Divi.
 */
function honest_divi_modules_register() {
	if ( ! class_exists( 'ET_Builder_Module' ) ) {
		return;
	}

	require_once HONEST_DIVI_MODULES_DIR . 'includes/class-honest-divi-module-base.php';

	foreach ( honest_divi_modules_map() as $dir => $class ) {
		$file = HONEST_DIVI_MODULES_DIR . "includes/modules/{$dir}/{$dir}.php";

		if ( ! file_exists( $file ) ) {
			continue;
		}

		require_once $file;

		if ( class_exists( $class ) ) {
			new $class();
		}
	}
}
add_action( 'et_builder_ready', 'honest_divi_modules_register' );

/**
 * Enqueue module styles.
 *
 * wp_enqueue_scripts covers the Visual Builder too, since the builder runs
 * on the front end.
 */
function honest_divi_modules_assets() {
	wp_enqueue_style(
		'honest-divi-modules',
		HONEST_DIVI_MODULES_URL . 'assets/css/modules.css',
		array(),
		HONEST_DIVI_MODULES_VERSION
	);

	// Registered only; the Leadership by Market module enqueues these
	// itself so the library loads only on pages that use it.
	wp_register_script(
		'lottie-web',
		'https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js',
		array(),
		'5.12.2',
		true
	);
	wp_register_script(
		'honest-market-map',
		HONEST_DIVI_MODULES_URL . 'assets/js/market-map.js',
		array( 'lottie-web' ),
		HONEST_DIVI_MODULES_VERSION,
		true
	);

	// Registered only; the Testimonials module enqueues this itself so the
	// script loads only on pages that use it.
	wp_register_script(
		'honest-testimonials',
		HONEST_DIVI_MODULES_URL . 'assets/js/testimonials.js',
		array(),
		HONEST_DIVI_MODULES_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'honest_divi_modules_assets' );

/**
 * Enqueue the builder-only assets.
 *
 * Two separate problems this solves, both measured on Divi 4.27.7:
 *
 * 1. vb-modules.js carries the React components that give the modules full
 *    builder compatibility. It has to be present in the builder and nowhere
 *    else -- on the front end there is no builder API to register against.
 *
 * 2. The runtime scripts (Lottie, the map driver, testimonials) are only
 *    *registered* above; each module enqueues its own during render() so the
 *    library loads solely on pages that use it. That never happens in the
 *    builder, because the ?et_fb=1 document contains no module markup at all --
 *    Divi renders modules into its preview iframe afterwards. So nothing ever
 *    triggered the enqueue and the map could not run in the builder. Enqueuing
 *    them unconditionally here fixes that; the iframe does receive front-end
 *    scripts (verified: 86 of them, jQuery included), it just never received
 *    ours.
 *
 * jquery is a hard dependency: registration hangs off the builder's
 * `et_builder_api_ready` jQuery event.
 */
function honest_divi_modules_builder_assets() {
	wp_enqueue_script( 'lottie-web' );
	wp_enqueue_script( 'honest-market-map' );
	wp_enqueue_script( 'honest-testimonials' );

	wp_enqueue_script(
		'honest-divi-vb-modules',
		HONEST_DIVI_MODULES_URL . 'assets/js/vb-modules.js',
		array( 'jquery' ),
		HONEST_DIVI_MODULES_VERSION,
		true
	);
}
add_action( 'et_fb_enqueue_assets', 'honest_divi_modules_builder_assets' );

/**
 * Warn when the active theme is not Divi, since the modules cannot register.
 */
function honest_divi_modules_theme_notice() {
	$theme = wp_get_theme();

	if ( 'Divi' === $theme->get( 'Name' ) || 'Divi' === $theme->get( 'Template' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Honest Divi Modules requires the Divi theme (or a Divi child theme) to be active. Its modules are not registered.', 'honest-divi-modules' )
	);
}
add_action( 'admin_notices', 'honest_divi_modules_theme_notice' );
