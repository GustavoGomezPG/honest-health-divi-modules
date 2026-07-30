<?php
/**
 * Plugin Name: Honest Divi Modules
 * Description: Custom Divi Builder modules for the Honest Health site.
 * Version:     1.16.0
 * Author:      Honest Health
 * Text Domain: honest-divi-modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HONEST_DIVI_MODULES_VERSION', '1.25.0' );
define( 'HONEST_DIVI_MODULES_DIR', plugin_dir_path( __FILE__ ) );
define( 'HONEST_DIVI_MODULES_URL', plugin_dir_url( __FILE__ ) );
// plugin_basename() needs this file's own path, and the activation callback lives
// in another file, so the path is shared through a constant.
define( 'HONEST_DIVI_MODULES_FILE', __FILE__ );

require_once HONEST_DIVI_MODULES_DIR . 'includes/dependencies.php';

// Refuses activation outright while Divi or ACF Pro is missing.
register_activation_hook( __FILE__, 'honest_divi_modules_activation_check' );

/**
 * Load the plugin, but only when what it depends on is actually present.
 *
 * Deferred to `plugins_loaded` rather than running at file scope so every other
 * plugin has declared itself before ACF Pro is looked for -- inter-plugin load
 * order is alphabetical and not worth relying on. Nothing here needs to run
 * sooner: every hook registered below fires after `plugins_loaded`.
 *
 * When a dependency is missing this returns before requiring anything. That is
 * not just tidiness -- the admin screens call ACF functions at load time and the
 * module base class extends ET_Builder_Module, so including those files without
 * their dependency is exactly how a missing plugin becomes a fatal error rather
 * than a notice.
 */
function honest_divi_modules_bootstrap() {
	if ( honest_divi_modules_missing_dependencies() ) {
		add_action( 'admin_notices', 'honest_divi_modules_dependency_notice' );

		return;
	}

	require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/team-settings.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/member-fields.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/admin/slug-migration.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/data/team-data.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/animation.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/media-placeholder.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/card-chevron.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/member-card.php';
	require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/article-card.php';

	add_action( 'et_builder_ready', 'honest_divi_modules_register' );
	add_action( 'wp_enqueue_scripts', 'honest_divi_modules_assets' );
	add_action( 'et_fb_enqueue_assets', 'honest_divi_modules_builder_assets' );
}
add_action( 'plugins_loaded', 'honest_divi_modules_bootstrap' );

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
	//
	// Served from the plugin rather than a CDN: the map would otherwise depend on
	// an outbound request that a strict CSP, an offline environment or a blocked
	// host can each deny. The version stays in the handle's version argument, so
	// the cache key still moves if the library is replaced.
	//
	// Bundled library: lottie-web 5.12.2 (airbnb/lottie-web), MIT licensed, used
	// unmodified. Replace by dropping in a new build and updating the version
	// string below -- there is no build step here to regenerate it.
	wp_register_script(
		'lottie-web',
		HONEST_DIVI_MODULES_URL . 'assets/js/lottie.min.js',
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

	// Styles the custom settings-modal fields. Builder-only, and separate from
	// modules.css because none of it is ever rendered on the front end.
	wp_enqueue_style(
		'honest-divi-vb-fields',
		HONEST_DIVI_MODULES_URL . 'assets/css/vb-fields.css',
		array(),
		HONEST_DIVI_MODULES_VERSION
	);

	wp_enqueue_script(
		'honest-divi-vb-modules',
		HONEST_DIVI_MODULES_URL . 'assets/js/vb-modules.js',
		array( 'jquery' ),
		HONEST_DIVI_MODULES_VERSION,
		true
	);
}

