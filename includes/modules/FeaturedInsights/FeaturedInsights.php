<?php
/**
 * Featured Insights module.
 *
 * A heading, an optional eyebrow, an intro paragraph, a grid of article cards
 * (the shared `honest_team_render_article_card()` partial), and a "view all"
 * button. It appears in two places with different content sources:
 *
 * - The "Our Team" page, where `source` is `latest` (or a curated `manual`
 *   list) and the design shows 3 cards in a single row on a purple band.
 * - The single team-member page, where `source` is `current_member` and
 *   the module powers the "Articles by [First Name]" section, rendered
 *   inside a Divi Theme Builder body template for the `article-author` post
 *   type -- `get_the_ID()` there resolves to the member, which is exactly
 *   what `honest_team_get_articles_by_member()` needs. The design shows 8
 *   cards there. `heading` is expected to carry dynamic content (or be typed
 *   per template) so it can read "Articles by Aaron" etc.
 *
 * Whatever the source, an empty result renders nothing at all: no heading, no
 * intro, no stranded empty grid, no button -- `render()` returns `''`. This
 * matters most for `current_member`: a team member with no credited articles
 * must not leave a "Articles by So-and-so" heading sitting over a blank grid.
 *
 * Every colour this module renders is exposed as an editable Divi colour
 * field (Design tab) whose default is the hex extracted from Figma (file
 * 6LBpKOMFlN8KxaKbut00YW) by pixel-sampling rendered node screenshots -- see
 * the task report for the full method and a discrepancy worth flagging here:
 *
 *   - Heading   node 50:766  -> solid white (#ffffff), sampled off the real
 *     composited "Our Team" page render (the isolated node screenshot alone
 *     renders blank, because the heading's own white fill is invisible
 *     against the screenshot API's default white matte -- it only reads
 *     against the purple section-background rectangle actually behind it on
 *     the page).
 *   - Intro     node 224:2323 -> also white (#ffffff), same reasoning; a
 *     `get_variable_defs` call on this node returns no bound variable, so it
 *     was pixel-sampled directly.
 *   - Eyebrow   node 50:672 ("Thought Leadership Headline") -> #070707,
 *     sampled directly off its own isolated node screenshot (black text on a
 *     white/transparent backdrop, matching its bound `Foreground/Default`
 *     variable).
 *   - Button    node 50:674 ("Button/Main") -> #070707 border and label text
 *     on a white/transparent fill (an outline pill button), matching its
 *     bound `Foreground/Default` / `Brand/Secondary` variables.
 *
 *   Flagged discrepancy: nodes 50:672 and 50:674 sit, in the Figma document,
 *   directly beneath the purple section-background rectangle in paint order
 *   on the "Our Team Layout" frame -- they are fully obscured by it and do
 *   not appear in the composited page render at all. The section's actually
 *   visible "view all" affordance there is a solid `#6985c3` filled button
 *   reading "Explore All Articles". However, the *same* heading+button
 *   pairing (an eyebrow-styled heading on the left, an outline "Secondary
 *   Button" on the right, in one row) appears again, unobscured, on the
 *   "Team Member Page" frame as "Articles by Aaron" + a Secondary Button --
 *   confirming this pairing is a real, intentional design pattern for this
 *   module and not a stray leftover, even though this particular instance of
 *   it is hidden on the Our Team page. The brief names 50:672/50:674
 *   explicitly as this module's own colour sources, so those (not the
 *   visible `#6985c3` override, which is a per-instance colour choice a
 *   Divi editor can already make with these same fields) are what the
 *   `eyebrow_color` / `button_*_color` defaults below carry.
 *
 * The card's own colours (background, byline, title, etc.) are owned
 * entirely by the shared article-card partial and its stylesheet rules --
 * this module does not expose or duplicate any of them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Featured_Insights extends Honest_Divi_Module_Base {

	public $slug = 'honest_featured_insights';

	public function init() {
		$this->name             = esc_html__( 'Featured Insights', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'eyebrow' => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
					'heading' => esc_html__( 'Heading', 'honest-divi-modules' ),
					'intro'   => esc_html__( 'Intro', 'honest-divi-modules' ),
					'button'  => esc_html__( 'Button', 'honest-divi-modules' ),
					'colors'  => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for each of these font groups is owned
		// exclusively by the custom colour fields below, applied as inline
		// CSS custom properties. Divi's font-group builder otherwise
		// auto-generates a native "Text Color" sub-option per group that
		// emits a directly-targeted CSS rule, which would silently beat the
		// inherited custom-property value and defeat the single-source-of-
		// truth colour rule (see the other modules in this plugin for the
		// same pattern).
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'eyebrow' => array( 'label' => esc_html__( 'Eyebrow', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-insights__eyebrow" ), 'toggle_slug' => 'eyebrow', 'hide_text_color' => true ),
				'heading' => array( 'label' => esc_html__( 'Heading', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-insights__heading" ), 'toggle_slug' => 'heading', 'hide_text_color' => true ),
				'intro'   => array( 'label' => esc_html__( 'Intro', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-insights__intro" ), 'toggle_slug' => 'intro', 'hide_text_color' => true ),
				'button'  => array( 'label' => esc_html__( 'Button', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-insights__button" ), 'toggle_slug' => 'button', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			'eyebrow'              => array(
				'label'           => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Optional small label shown above the heading.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'heading'              => array(
				'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'              => array(
				'label'           => esc_html__( 'Intro', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			// Source mode. `current_member` is what powers the "Articles by
			// [First Name]" section on the single team-member page -- see
			// get_posts_for_source() below for how it reads get_the_ID().
			'source'               => array(
				'label'           => esc_html__( 'Source', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Where the article cards come from.', 'honest-divi-modules' ),
				'options'         => array(
					'latest'         => esc_html__( 'Latest Posts', 'honest-divi-modules' ),
					'manual'         => esc_html__( 'Manual Selection', 'honest-divi-modules' ),
					'current_member' => esc_html__( 'Current Team Member', 'honest-divi-modules' ),
				),
				'default'         => 'latest',
				'toggle_slug'     => 'main_content',
			),
			'manual_ids'           => array(
				'label'           => esc_html__( 'Post IDs', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Comma-separated post IDs, in the order they should appear.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'show_if'         => array(
					'source' => 'manual',
				),
			),
			// range_settings uses min_limit/max_limit (not just min/max) so a
			// value typed into the paired number box, or a hand-edited
			// shortcode, is still bounded by Divi's own validation -- the
			// same pattern used by the Leadership by Market module's
			// map_speed field. The render-time clamp in
			// get_posts_for_source() is the backstop for anything that
			// bypassed this UI entirely.
			'limit'                => array(
				'label'           => esc_html__( 'Number of Articles', 'honest-divi-modules' ),
				'description'     => esc_html__( 'How many article cards to show. The design uses 3 on the Our Team page and 8 on a member page.', 'honest-divi-modules' ),
				'type'            => 'range',
				'option_category' => 'configuration',
				'range_settings'  => array(
					'min'       => '1',
					'max'       => '12',
					'step'      => '1',
					'min_limit' => '1',
					'max_limit' => '12',
				),
				'unitless'        => true,
				'default'         => '3',
				'toggle_slug'     => 'main_content',
			),
			'button_text'          => array(
				'label'           => esc_html__( 'Button Text', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Leave blank to hide the button entirely.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'button_url'           => array(
				'label'           => esc_html__( 'Button URL', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'url',
			),
			// Colour fields. Defaults are the hexes extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW) -- see the file header comment
			// for the full method and the flagged label/button discrepancy.
			'eyebrow_color'        => array(
				'label'        => esc_html__( 'Eyebrow Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the small label above the heading.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#070707',
			),
			'heading_color'        => array(
				'label'        => esc_html__( 'Heading Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the section heading.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'intro_color'          => array(
				'label'        => esc_html__( 'Intro Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the intro copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'button_bg_color'      => array(
				'label'        => esc_html__( 'Button Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Fill colour of the "view all" button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'button_text_color'    => array(
				'label'        => esc_html__( 'Button Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Label colour of the "view all" button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#070707',
			),
			'button_border_color'  => array(
				'label'        => esc_html__( 'Button Border Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Border colour of the "view all" button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#070707',
			),
		);
	}

	/**
	 * Resolve the posts to render, per the `source` setting.
	 *
	 * `current_member` reads get_the_ID() directly, which is why this only
	 * works correctly when the module is rendered in a context where the
	 * current post is the team member -- e.g. inside a Divi Theme Builder
	 * body template assigned to the `article-author` post type, or on a
	 * singular member page. It is not meaningful on the Our Team page itself
	 * (there, `source` is `latest` or `manual`).
	 *
	 * @return WP_Post[]
	 */
	protected function get_posts_for_source() {
		$limit = max( 1, (int) $this->props['limit'] );

		switch ( $this->props['source'] ) {
			case 'current_member':
				return honest_team_get_articles_by_member( get_the_ID(), $limit );

			case 'manual':
				$ids = array_filter( array_map( 'intval', explode( ',', (string) $this->props['manual_ids'] ) ) );

				if ( empty( $ids ) ) {
					return array();
				}

				return get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'post__in'       => $ids,
						'orderby'        => 'post__in',
						'posts_per_page' => $limit,
					)
				);

			default: // 'latest'.
				return get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => $limit,
					)
				);
		}
	}

	public function render( $attrs, $content, $render_slug ) {
		$posts = $this->get_posts_for_source();

		if ( empty( $posts ) ) {
			return '';
		}

		$cards = '';
		foreach ( $posts as $post ) {
			$cards .= honest_team_render_article_card( $post );
		}

		$eyebrow = '';
		if ( '' !== trim( (string) $this->props['eyebrow'] ) ) {
			$eyebrow = sprintf( '<p class="honest-insights__eyebrow">%s</p>', esc_html( $this->props['eyebrow'] ) );
		}

		$heading = '';
		if ( '' !== trim( (string) $this->props['heading'] ) ) {
			$heading = sprintf( '<h2 class="honest-insights__heading">%s</h2>', esc_html( $this->props['heading'] ) );
		}

		$intro = '';
		if ( '' !== trim( (string) $this->content ) ) {
			$intro = sprintf( '<div class="honest-insights__intro">%s</div>', et_core_esc_previously( $this->content ) );
		}

		$foot        = '';
		$button_text = trim( (string) $this->props['button_text'] );
		if ( '' !== $button_text ) {
			$button_url = trim( (string) $this->props['button_url'] );
			$foot       = sprintf(
				'<div class="honest-insights__foot"><a class="honest-insights__button" href="%1$s">%2$s</a></div>',
				esc_url( '' !== $button_url ? $button_url : '#' ),
				esc_html( $button_text )
			);
		}

		$inner = sprintf(
			'<div class="honest-insights__inner"><div class="honest-insights__head">%1$s%2$s%3$s</div><div class="honest-insights__grid">%4$s</div>%5$s</div>',
			$eyebrow,
			$heading,
			$intro,
			$cards,
			$foot
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-insights' ),
			array(
				'--hh-insights-eyebrow'       => $this->props['eyebrow_color'],
				'--hh-insights-heading'       => $this->props['heading_color'],
				'--hh-insights-intro'         => $this->props['intro_color'],
				'--hh-insights-button-bg'     => $this->props['button_bg_color'],
				'--hh-insights-button-text'   => $this->props['button_text_color'],
				'--hh-insights-button-border' => $this->props['button_border_color'],
			)
		);
	}
}
