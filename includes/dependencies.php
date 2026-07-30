<?php
/**
 * Dependency detection and gating.
 *
 * This plugin cannot function without two things, and neither is on WordPress.org,
 * so the WP 6.5 `Requires Plugins:` header cannot express them -- it resolves
 * slugs against the .org directory only. Hence a manual check.
 *
 * The awkward part is TIMING. Plugin files are included before the theme is
 * loaded, so at that point `class_exists( 'ET_Builder_Module' )` is false even on
 * a site running Divi perfectly well. Detection therefore has to be able to
 * answer from the theme record alone, and the gate itself runs on
 * `plugins_loaded` so every other plugin has had a chance to declare itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the Divi Builder is available, from either route it ships by.
 *
 * Divi is normally a THEME, not a plugin -- and typically the parent of a child
 * theme, which is how this site runs it (child `HonestMedic`, template `Divi`).
 * It is also sold as a standalone "Divi Builder" plugin. Both provide
 * ET_Builder_Module, so both count.
 *
 * @return bool
 */
function honest_divi_modules_has_divi() {
	// Cheapest and most direct, but only true once Divi has actually booted, so
	// it answers at runtime and not during activation.
	if ( class_exists( 'ET_Builder_Module' ) || defined( 'ET_BUILDER_VERSION' ) ) {
		return true;
	}

	// The theme record, which is readable before the theme is loaded. Checks the
	// template (parent) as well as the stylesheet so a Divi child theme counts.
	$theme = wp_get_theme();

	foreach ( array( $theme->get_template(), $theme->get_stylesheet() ) as $slug ) {
		if ( 'divi' === strtolower( (string) $slug ) ) {
			return true;
		}
	}

	// The standalone builder plugin. Matched on the directory rather than a fixed
	// basename because the folder name has varied between releases.
	foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
		if ( false !== strpos( strtolower( $plugin ), 'divi-builder' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether ACF *Pro* is available.
 *
 * Pro specifically, not the free plugin: the Teams screens are built with
 * acf_add_options_page()/acf_add_options_sub_page() and the per-market editor is
 * a repeater. Both are Pro-only, and on free ACF they fail silently rather than
 * loudly, which is the worst outcome.
 *
 * @return bool
 */
function honest_divi_modules_has_acf_pro() {
	if ( ! class_exists( 'ACF' ) ) {
		return false;
	}

	// ACF's own flag, present on current versions (6.8 reports pro => true).
	if ( function_exists( 'acf_get_setting' ) && acf_get_setting( 'pro' ) ) {
		return true;
	}

	// Fallback for builds where that setting is unavailable this early: the
	// options-page API exists only in Pro.
	return function_exists( 'acf_add_options_page' );
}

/**
 * Missing dependencies, as display labels.
 *
 * @return string[] Empty when everything needed is present.
 */
function honest_divi_modules_missing_dependencies() {
	$missing = array();

	if ( ! honest_divi_modules_has_divi() ) {
		$missing[] = __( 'the Divi theme or the Divi Builder plugin', 'honest-divi-modules' );
	}

	if ( ! honest_divi_modules_has_acf_pro() ) {
		$missing[] = __( 'Advanced Custom Fields Pro', 'honest-divi-modules' );
	}

	return $missing;
}

/**
 * One sentence naming what is missing and what it is needed for.
 *
 * @param string[] $missing Labels from honest_divi_modules_missing_dependencies().
 * @return string
 */
function honest_divi_modules_dependency_message( $missing ) {
	return sprintf(
		/* translators: %s: comma-separated list of missing dependencies. */
		__( 'Honest Divi Modules requires %s. Its modules provide Divi Builder sections, and its team content is stored in ACF fields, so neither half can run without them.', 'honest-divi-modules' ),
		implode( __( ' and ', 'honest-divi-modules' ), $missing )
	);
}

/**
 * Refuse activation while a dependency is missing.
 *
 * Deactivates before dying so WordPress does not record the plugin as active:
 * the activation hook fires after it has already been added to the active list,
 * and bailing with wp_die() alone would leave it there.
 */
function honest_divi_modules_activation_check() {
	$missing = honest_divi_modules_missing_dependencies();

	if ( empty( $missing ) ) {
		return;
	}

	deactivate_plugins( plugin_basename( HONEST_DIVI_MODULES_FILE ) );

	wp_die(
		esc_html( honest_divi_modules_dependency_message( $missing ) ),
		esc_html__( 'Plugin dependencies missing', 'honest-divi-modules' ),
		array( 'back_link' => true )
	);
}

/**
 * Explain the inert state in the admin.
 *
 * Covers what the activation hook cannot: a dependency deactivated or switched
 * away AFTER this plugin was activated. The plugin no-ops in that case rather
 * than deactivating itself, because silently turning itself off during, say, a
 * theme switch would be harder to diagnose than a visible notice.
 */
function honest_divi_modules_dependency_notice() {
	$missing = honest_divi_modules_missing_dependencies();

	if ( empty( $missing ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( honest_divi_modules_dependency_message( $missing ) )
	);
}
