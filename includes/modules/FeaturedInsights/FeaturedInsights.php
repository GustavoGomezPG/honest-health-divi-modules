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
 *   cards there, under a heading reading "Articles by Aaron" -- the member's
 *   FIRST name. See the `%first_name%` token below for how one shared
 *   template heading produces a different name on every member's page.
 *
 * `%first_name%` heading token
 * ----------------------------
 * One Theme Builder body template renders every team member's page, so the
 * heading it carries cannot be a hardcoded name. Typing
 * `Articles by %first_name%` into the `heading` field substitutes the current
 * member's first name at render time (`resolve_heading()` /
 * `get_member_first_name()`).
 *
 * A visible token was chosen over "auto-fill the heading when it is left
 * blank" for three reasons:
 *
 *   - It is discoverable and self-documenting. An editor opening the module
 *     in Divi sees the literal heading text that produces the output, plus
 *     the field description explaining the token. A blank field that silently
 *     grows copy of its own is invisible in the UI and reads as a bug to
 *     whoever inherits the page.
 *   - It keeps the surrounding wording editable. "Articles by Aaron",
 *     "Insights from Aaron", "More from Aaron" and any translated word order
 *     are all reachable without a code change; an automatic default would
 *     hardcode one English phrasing in PHP.
 *   - Blank still means blank. `heading` is an optional field and always has
 *     been: leaving it empty renders no heading at all, in every source mode.
 *     Auto-filling it would change that established behaviour for the two
 *     other source modes too.
 *
 * Degradation, in every direction:
 *
 *   - Token present, no member in context (wrong template, unpublished or
 *     deleted member, or `source` is `latest`/`manual` where "current member"
 *     is not a meaningful concept): the heading is dropped entirely rather
 *     than rendered with a hole in it. A heading reading "Articles by " with
 *     a dangling preposition is worse than no heading, and printing the raw
 *     `%first_name%` token to a site visitor is worse still.
 *   - Token absent: nothing changes at all. The heading is used verbatim, so
 *     `latest` and `manual` instances (the Our Team page) behave exactly as
 *     they did before the token existed.
 *   - Member title with no whitespace ("Cher") yields that whole title;
 *     an empty title yields an empty first name, which drops the heading
 *     under the first rule above. Neither path emits a PHP notice.
 *
 * Whatever the source, an empty result renders nothing at all: no heading, no
 * intro, no stranded empty grid, no button -- `render()` returns `''`. This
 * matters most for `current_member`: a team member with no credited articles
 * must not leave a "Articles by So-and-so" heading sitting over a blank grid.
 *
 * Two composited treatments exist, both real, and the `style` field picks
 * between them as ONE decision. They differ in more than button placement --
 * heading size and weight, and every foreground colour, change too -- so a
 * field naming only the button would have left the rest to be reproduced by
 * hand on each instance. That is what it used to be: `button_position` chose
 * the layout, and the member-page instance then carried seven hand-set colour
 * and typography overrides to finish the job. Nothing recorded that those seven
 * belonged together, so a third instance could only be built by copying values
 * off the second. The treatments now live in the stylesheet, keyed off a
 * modifier class, and the field selects one:
 *
 *   - `feature` (Our Team page, node 54:308): heading and intro centred, 70px
 *     extrabold white over the section's purple band, then the grid, then a
 *     centred solid `#6985c3` button reading "Explore All Articles".
 *   - `member` (Team Member Page, node 224:2532): "Articles by Aaron" at 40px
 *     bold `#1e1e1e` on the left with the button beside it on the right (node
 *     224:2997 -- no fill, `#6985c3` border and label), grid below, no visible
 *     intro in that instance. This is the `current_member` case, the one the
 *     brief calls out as the more important of the two ("This is what powers
 *     the 'Articles by [First Name]' section"), and it recurs on every team
 *     member's page -- so `member` is the field's default.
 *
 * The colour fields stay, and still win where they are set: a style supplies
 * the colour through a `var(--token, fallback)` fallback in the stylesheet,
 * while a field that has been given a value emits the token inline and
 * overrides it. Those fields therefore carry no `default` of their own -- a
 * default would emit the token on every instance and the style could never be
 * seen at all.
 *
 * An earlier pass at this module sourced its button/eyebrow colours from
 * Figma nodes 50:672/50:674, which turned out to be generic wireframe
 * scaffolding (literally named/labelled "Thought Leadership Headline" and
 * "View All CTA") sitting hidden behind the Our Team page's purple
 * background rectangle -- not a finished design. Colours now come from the
 * two real, visible button nodes above instead:
 *
 *   - `button_bg_color`/`button_label_color`/`button_border_color` default to
 *     the `below` (Our Team page) treatment -- `#6985c3` fill, white text,
 *     `#6985c3` border (same colour as the fill, so it reads as a plain
 *     solid pill, matching node 54:308 exactly). The Our Team page is this
 *     plugin's primary landing surface, so its button styling is the
 *     default; the `top` treatment (`#ffffff` background, `#6985c3` border
 *     and text) is fully reachable with these same three fields, no code
 *     changes needed, by whoever builds the member-page Theme Builder
 *     template.
 *   - `eyebrow_color` defaults to `#1e1e1e`: pixel-sampling both real
 *     composited sections (the Our Team purple band and the Team Member Page
 *     lavender band) turned up no eyebrow/kicker element anywhere -- it does
 *     not appear to be part of either finished design. Rather than keep the
 *     discarded wireframe node's colour, this now matches the dark-neutral
 *     text token this plugin already uses for on-light headings elsewhere
 *     (Executive Leadership's and Leadership by Market's `heading_color`/
 *     `intro_color`, both `#1e1e1e`).
 *   - `heading_color`/`intro_color` remain `#ffffff`, confirmed correct: both
 *     were pixel-sampled off the real, fully composited Our Team page render
 *     (not an isolated/hidden node -- the heading and intro genuinely are
 *     solid white there, readable against the purple section background
 *     actually behind them on that page). They read as invisible on a plain
 *     white page background precisely because that purple is a real part of
 *     the design and needs to be present (via Divi's own Section background
 *     colour, or by overriding these two fields per instance) for this
 *     module to look right -- the same trade-off Testimonials' white-on-band
 *     text already makes elsewhere in this plugin. The Team Member Page's
 *     own composited heading is dark (`#1e1e1e`, matching its own light
 *     lavender background) -- an instance-level override, not a reason to
 *     change this module's own default, which follows the Our Team page per
 *     the brief's original node reference (50:766).
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

	/**
	 * Full builder compatibility; component in assets/js/vb-modules.js under this
	 * slug. The card grid reaches the builder as server-rendered HTML through the
	 * `__cards` computed property, so the article card markup is never duplicated
	 * in JavaScript.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * Stand-in for "this member's own posts" inside a manual selection.
	 *
	 * Stored in the selection like a post ID, but expands at render time to
	 * whatever honest_team_get_articles_by_member() returns. Its POSITION in the
	 * list is the point: it lets an editor place chosen articles before it, after
	 * it, or both, without knowing which posts the member has.
	 *
	 * Deliberately not numeric, so it can never collide with a post ID.
	 *
	 * @var string
	 */
	const MEMBER_TOKEN = 'member';

	/**
	 * Placeholder an editor can type into the `heading` field to have the
	 * current team member's first name substituted at render time -- see the
	 * file header comment for why this is a visible token rather than an
	 * automatic default, and resolve_heading() for the substitution rules.
	 */
	const FIRST_NAME_TOKEN = '%first_name%';

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
				// font_size / weight / line_height defaults are the values on the
				// Figma text nodes in q1MGpWgDpBoZeS6dgrddjB node 11:761: heading
				// 1:63 (70px / 800), intro 1:64 (18px / 400 / 1.45) and the
				// secondary button 1:188 (24px / 700). The heading carries no
				// line_height default because the design specifies `normal`, which
				// Divi's control cannot express -- the stylesheet sets it instead.
				// These same numbers are duplicated in assets/css/modules.css,
				// which is the floor: a Divi default emits no CSS at all for an
				// instance saved before the default existed.
				'heading' => array(
					'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-insights__heading" ),
					'toggle_slug'     => 'heading',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '70px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'font'            => array( 'default' => '|800|||||||' ),
				),
				'intro'   => array(
					'label'           => esc_html__( 'Intro', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-insights__intro" ),
					'toggle_slug'     => 'intro',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '18px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.45em' ),
					'font'            => array( 'default' => '|400|||||||' ),
				),
				'button'  => array(
					'label'           => esc_html__( 'Button', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-insights__button" ),
					'toggle_slug'     => 'button',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '24px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
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
				'description'     => sprintf(
					/* translators: %s: the literal placeholder token an editor types, e.g. %first_name% */
					esc_html__( 'Optional. Leave blank to hide the heading. With Source set to Current Team Member, %s is replaced by that member\'s first name, e.g. "Articles by %s" becomes "Articles by Aaron". The whole heading is hidden if no member is in context.', 'honest-divi-modules' ),
					esc_html( self::FIRST_NAME_TOKEN ),
					esc_html( self::FIRST_NAME_TOKEN )
				),
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
			// query_posts_for_source() below for how the member is resolved.
			'source'               => array(
				'label'           => esc_html__( 'Source', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Where the article cards come from.', 'honest-divi-modules' ),
				'options'         => array(
					'latest'                => esc_html__( 'Latest Posts', 'honest-divi-modules' ),
					'manual'                => esc_html__( 'Manual Selection', 'honest-divi-modules' ),
					'current_member'        => esc_html__( 'Current Team Member', 'honest-divi-modules' ),
					'current_member_custom' => esc_html__( 'Current Team Member + Custom Posts', 'honest-divi-modules' ),
				),
				'default'         => 'latest',
				'toggle_slug'     => 'main_content',
			),
			'manual_ids'           => array(
				'label'           => esc_html__( 'Posts', 'honest-divi-modules' ),
				// Custom control, registered in assets/js/vb-modules.js under this
				// same name. Divi has no ordered multi-select of its own, and the
				// order posts appear in is the point of a "featured" section --
				// multiple_checkboxes could pick them but not sequence them.
				//
				// The stored value stays a plain pipe-delimited string, exactly
				// what Divi's own multi-value controls save, so it round-trips
				// through the shortcode like any built-in field and the render path
				// is unchanged.
				'type'            => 'honest_post_picker',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Choose the articles to feature and put them in order with the arrows. With the "+ Custom Posts" source, add the highlighted "Member posts" entry wherever this member\'s own articles should sit -- anything above it appears first, anything below it after. Leave the list empty and only the member\'s posts are shown.', 'honest-divi-modules' ),
				'options'         => self::get_post_options(),
				'toggle_slug'     => 'main_content',
				// Read by the picker in assets/js/vb-modules.js. Passed through the
				// field definition rather than hard-coded there so the token and its
				// label stay defined in one place and the label stays translatable.
				'honest_member_token'       => self::MEMBER_TOKEN,
				'honest_member_token_label' => esc_html__( 'Member posts', 'honest-divi-modules' ),
				'show_if'         => array(
					'source' => array( 'manual', 'current_member_custom' ),
				),
			),
			// range_settings uses min_limit/max_limit (not just min/max) so a
			// value typed into the paired number box, or a hand-edited
			// shortcode, is still bounded by Divi's own validation -- the
			// same pattern used by the Leadership by Market module's
			// map_speed field. The render-time clamp in
			// query_posts_for_source() is the backstop for anything that
			// bypassed this UI entirely.
			// Delivers the card grid's HTML to the builder's React component, so
			// the article card markup lives only in PHP. Depends on everything
			// that changes which posts are shown; heading, colours and button
			// settings are prop-driven and stay instant.
			// Lets the builder's React component substitute the %first_name%
			// heading token, which it otherwise renders literally -- the name is
			// server data and cannot be worked out in JavaScript.
			'__first_name'         => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Featured_Insights', 'get_first_name' ),
				'computed_depends_on' => array( 'source' ),
			),
			'__cards'              => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Featured_Insights', 'get_cards_html' ),
				'computed_depends_on' => array( 'source', 'manual_ids', 'limit', 'show_all' ),
			),
			// Applies to every source, and is therefore always visible. That
			// visibility is what makes it safe for Number of Articles to hide while
			// this is on: the control that restores it can always be reached, which
			// was not true while this field was itself conditional.
			//
			// Note what "all" means per source: the whole selection for the two
			// manual ones, every article credited to the member for Current Team
			// Member, and EVERY PUBLISHED POST for Latest Posts.
			'show_all'             => array(
				'label'           => esc_html__( 'Show All', 'honest-divi-modules' ),
				'description'     => esc_html__( 'Ignore Number of Articles and show everything the chosen Source returns. On Latest Posts that means every published post, so use it with a source that is naturally bounded.', 'honest-divi-modules' ),
				'type'            => 'yes_no_button',
				'option_category' => 'configuration',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'honest-divi-modules' ),
					'off' => esc_html__( 'No', 'honest-divi-modules' ),
				),
				'default'         => 'off',
				'toggle_slug'     => 'main_content',
			),
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
				// Safe to hide only because Show All is now unconditional. While
				// that toggle was itself source-dependent, hiding this field could
				// strand it: both controls disappeared together and neither could be
				// reached to undo it. Divi cannot express the condition that would
				// have fixed that -- canShowField() ANDs every show_if /
				// show_if_not key, so there is no OR -- so the answer was to remove
				// the dependency rather than refine it.
				'show_if_not'     => array(
					'show_all' => 'on',
				),
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
			// The single control that picks a whole composited treatment --
			// layout, heading scale and colours together. See the file header
			// comment for what each one is and where it comes from. Defaults to
			// `member`: that treatment recurs on every team member's page,
			// versus the single Our Team page instance of `feature`.
			'style'                => array(
				'label'           => esc_html__( 'Style', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Which composited treatment to use. This sets the layout, the heading scale and the default colours together; the colour fields on the Design tab override individual parts of it.', 'honest-divi-modules' ),
				'options'         => array(
					'member'  => esc_html__( 'Author Articles - compact heading, button beside it', 'honest-divi-modules' ),
					'feature' => esc_html__( 'Feature Band - large centred heading, button below grid', 'honest-divi-modules' ),
				),
				'default'         => 'member',
				'toggle_slug'     => 'main_content',
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
				'default'      => '#1e1e1e',
			),
			// The four fields the two styles disagree about carry NO default, on
			// purpose. Divi passes a field's default through to the props like any
			// other value, so a default here would emit the custom property inline
			// on every instance -- and an inline declaration outranks the
			// stylesheet, which is where the styles live. Every instance would
			// render the default treatment and the Style field would do nothing
			// but move the button. Left empty, the token is not emitted at all
			// (see build_css_var_declarations()), the style's own
			// `var(--token, fallback)` decides, and setting the field still wins.
			'heading_color'        => array(
				'label'        => esc_html__( 'Heading Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Overrides the heading colour this style would use.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
			),
			'intro_color'          => array(
				'label'        => esc_html__( 'Intro Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Overrides the intro colour this style would use.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
			),
			'button_bg_color'      => array(
				'label'        => esc_html__( 'Button Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Overrides the button fill this style would use.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
			),
			// Named `button_label_color`, not `button_text_color` -- init()
			// declares a font group named `button`, and Divi's font-group
			// generator auto-creates a `"{group}_text_color"` prop for every
			// font group, so `button_text_color` is an exact collision with a
			// prop this module itself causes Divi to generate. It happens to
			// be suppressed today by `hide_text_color => true` on that group,
			// but that makes the field name's safety contingent on an
			// unrelated flag staying set. CallToAction.php was renamed away
			// from exactly this name for exactly this reason; this module now
			// matches it.
			'button_label_color'   => array(
				'label'        => esc_html__( 'Button Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Overrides the button label colour this style would use.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
			),
			'button_border_color'  => array(
				'label'        => esc_html__( 'Button Border Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Border colour of the "view all" button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
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
	/**
	 * The article cards, rendered server-side.
	 *
	 * Shared by render() and by the `__cards` computed property so the builder and
	 * the front end cannot drift, and so the card partial stays the single source
	 * of that markup.
	 *
	 * Static because Divi calls computed callbacks as plain callables with no
	 * module instance: call_user_func( $callback, $depends_on, $conditional_tags,
	 * $current_page ).
	 *
	 * @param array $args         Depends-on values: source, manual_ids, limit.
	 * @param array $conditional_tags Unused; part of Divi's callback signature.
	 * @param array $current_page Divi's description of the page being edited. Used
	 *                            for the `current_member` source, where the post in
	 *                            context is the member -- get_the_ID() is not that
	 *                            post during a computed-property AJAX request.
	 * @return string
	 */
	public static function get_cards_html( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		$posts = self::query_posts_for_source( $args, $current_page );

		if ( empty( $posts ) ) {
			return '';
		}

		$cards      = '';
		$card_index = 0;

		foreach ( $posts as $post ) {
			$cards .= honest_team_render_article_card( $post, $card_index++ );
		}

		return $cards;
	}

	/**
	 * Published posts as checkbox options, keyed by ID.
	 *
	 * Two things make this safe to call from get_fields(), which Divi runs for
	 * every module on every request that registers them:
	 *
	 * - It returns nothing outside a builder request. The options list only
	 *   populates a control in the settings modal; rendering the module needs the
	 *   stored value, not the catalogue. Without this guard every front-end page
	 *   view would run an extra post query for a control nobody is looking at.
	 * - It is memoised for the request, because get_fields() can be called more
	 *   than once per page load.
	 *
	 * Capped rather than unbounded: a site with thousands of posts would otherwise
	 * put thousands of checkboxes in a settings modal, which is neither usable nor
	 * cheap. The cap is generous next to the number of articles this section
	 * features, and the Source field's other modes exist for anything broader.
	 *
	 * @return array<string,string> Post ID => title.
	 */
	protected static function get_post_options() {
		static $options = null;

		if ( null !== $options ) {
			return $options;
		}

		if ( ! function_exists( 'honest_team_is_builder_render' ) || ! honest_team_is_builder_render() ) {
			return array();
		}

		$options = array();

		foreach ( get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		) as $post ) {
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );

			$options[ (string) $post->ID ] = '' !== $title
				/* translators: %d: post ID, shown when a post has no title. */
				? $title
				: sprintf( __( '(no title) #%d', 'honest-divi-modules' ), $post->ID );
		}

		return $options;
	}

	/**
	 * The member whose articles a "current member" source should show.
	 *
	 * Divi's page context is preferred over get_the_ID(), because during a
	 * computed-property request the queried object is not the member being
	 * previewed. When neither resolves to a member AND this is a builder request,
	 * a stand-in is used so the section is populated while the Theme Builder
	 * layout is being edited -- see honest_team_get_preview_member_id(), which
	 * shares its choice with the header module so the two cannot disagree about
	 * who is being previewed.
	 *
	 * Never substitutes on the front end: there, no member means no articles.
	 *
	 * @param array $current_page Builder page context, if any.
	 * @return int
	 */
	protected static function resolve_member_id( $current_page ) {
		$member_id = ! empty( $current_page['id'] ) ? (int) $current_page['id'] : (int) get_the_ID();

		if ( honest_team_get_member( $member_id ) ) {
			return $member_id;
		}

		if ( function_exists( 'honest_team_is_builder_render' ) && honest_team_is_builder_render() ) {
			return honest_team_get_preview_member_id();
		}

		return $member_id;
	}

	/**
	 * Post IDs from a stored selection.
	 *
	 * Accepts both separators so a value saved before the picker existed still
	 * works: the field was a comma-separated list typed by hand, and Divi's
	 * multi-select controls store their value delimited by `|`. Duplicates are
	 * dropped, order is preserved, and anything non-numeric disappears.
	 *
	 * @param mixed $raw Stored field value.
	 * @return int[]
	 */
	protected static function parse_post_ids( $raw ) {
		return array_values( array_filter( self::parse_selection( $raw ), 'is_int' ) );
	}

	/**
	 * The `current_member_custom` source: chosen posts with the member's own
	 * expanded in place.
	 *
	 * The selection is walked in order. A post ID contributes that post; the
	 * member token contributes everything the member wrote, at that point in the
	 * sequence -- which is what lets an editor put articles both before and after
	 * it.
	 *
	 * An empty selection means the token alone. Without that a freshly added
	 * module would render nothing at all until something was ticked, and "current
	 * team member" is the sensible thing for this source to show by default.
	 *
	 * Explicitly chosen posts are fetched in ONE query rather than per item, and
	 * the member's are fetched at most once and only if the token is actually
	 * reached. Duplicates are dropped, so a post that is both chosen and written
	 * by the member appears once, at its chosen position.
	 *
	 * @param array $args         source / manual_ids / limit.
	 * @param array $current_page Builder page context, if any.
	 * @param int   $limit        Already-validated maximum.
	 * @param bool  $unlimited    When true the limit is ignored and the whole
	 *                            selection renders, however long it turns out to
	 *                            be once the token has expanded.
	 * @return WP_Post[]
	 */
	protected static function query_member_with_custom( $args, $current_page, $limit, $unlimited = false ) {
		$items = self::parse_selection( isset( $args['manual_ids'] ) ? $args['manual_ids'] : '' );

		if ( empty( $items ) ) {
			$items = array( self::MEMBER_TOKEN );
		}

		$ids = array_values( array_filter( $items, 'is_int' ) );
		$chosen = array();

		if ( ! empty( $ids ) ) {
			foreach ( get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'post__in'       => $ids,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $ids ),
				)
			) as $post ) {
				$chosen[ $post->ID ] = $post;
			}
		}

		$member_id = self::resolve_member_id( $current_page );
		$out       = array();
		$seen      = array();

		foreach ( $items as $item ) {
			if ( ! $unlimited && count( $out ) >= $limit ) {
				break;
			}

			$batch = array();

			if ( self::MEMBER_TOKEN === $item ) {
				$batch = honest_team_get_articles_by_member( $member_id, $unlimited ? -1 : $limit );
			} elseif ( isset( $chosen[ $item ] ) ) {
				$batch = array( $chosen[ $item ] );
			}

			foreach ( $batch as $post ) {
				if ( ! $unlimited && count( $out ) >= $limit ) {
					break;
				}

				if ( isset( $seen[ $post->ID ] ) ) {
					continue;
				}

				$seen[ $post->ID ] = true;
				$out[]             = $post;
			}
		}

		return $out;
	}

	/**
	 * A stored selection as an ordered list of post IDs and tokens.
	 *
	 * Unlike parse_post_ids() this keeps MEMBER_TOKEN in place, because where it
	 * sits in the order is the whole reason it exists.
	 *
	 * @param mixed $raw Stored field value.
	 * @return array<int,int|string> Ints for posts, MEMBER_TOKEN for the member's own.
	 */
	protected static function parse_selection( $raw ) {
		$parts = preg_split( '/[|,]/', (string) $raw );

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$items = array();

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );

			if ( self::MEMBER_TOKEN === $part ) {
				// Only meaningful once; a second one would just expand to posts
				// already placed by the first.
				if ( ! in_array( self::MEMBER_TOKEN, $items, true ) ) {
					$items[] = self::MEMBER_TOKEN;
				}

				continue;
			}

			$id = (int) $part;

			if ( $id > 0 && ! in_array( $id, $items, true ) ) {
				$items[] = $id;
			}
		}

		return $items;
	}

	/**
	 * Resolve the configured source to a list of posts.
	 *
	 * @param array $args         source / manual_ids / limit.
	 * @param array $current_page Optional builder page context.
	 * @return WP_Post[]
	 */
	protected static function query_posts_for_source( $args = array(), $current_page = array() ) {
		// Re-validated against the SAME 1-12 bounds the field advertises, not
		// merely clamped at the bottom: a hand-edited shortcode or a stale
		// saved value bypasses the builder UI entirely, and `limit="9999"`
		// passed the old `max( 1, ... )` guard untouched and ran an unbounded
		// query. Anything non-numeric or outside the advertised range falls
		// back to the documented default rather than being snapped to a
		// bound, matching how LeadershipByMarket::render() validates
		// `map_speed`.
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 3;
		if ( $limit < 1 || $limit > 12 ) {
			$limit = 3;
		}

		$source = isset( $args['source'] ) ? (string) $args['source'] : 'latest';

		// Applies to every source. What it costs varies: the two manual sources
		// are bounded by the editor's own list, Current Team Member by how much
		// that person has written, and Latest Posts by nothing at all -- there it
		// really does query every published post.
		$unlimited = isset( $args['show_all'] ) && 'on' === $args['show_all'];

		switch ( $source ) {
			case 'current_member':
				return honest_team_get_articles_by_member( self::resolve_member_id( $current_page ), $unlimited ? -1 : $limit );

			case 'current_member_custom':
				return self::query_member_with_custom( $args, $current_page, $limit, $unlimited );

			case 'manual':
				$ids = self::parse_post_ids( isset( $args['manual_ids'] ) ? $args['manual_ids'] : '' );

				if ( empty( $ids ) ) {
					return array();
				}

				// orderby post__in keeps whatever order the stored value carries,
				// so the selection's own sequence survives rather than being
				// re-sorted by date.
				return get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'post__in'       => $ids,
						'orderby'        => 'post__in',
						'posts_per_page' => $unlimited ? -1 : $limit,
					)
				);

			default: // 'latest'.
				return get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => $unlimited ? -1 : $limit,
					)
				);
		}
	}

	/**
	 * The current team member's first name, for the `%first_name%` heading
	 * token. Returns `''` whenever there is no member in context, which is the
	 * single signal resolve_heading() uses to drop the heading entirely.
	 *
	 * Deliberately limited to `source === 'current_member'`: "the current
	 * member" is not a meaningful concept in `latest` or `manual` mode (those
	 * instances live on the Our Team page, where get_the_ID() is the page
	 * itself), so the token resolves to nothing there rather than to the page
	 * title's first word.
	 *
	 * First name = the first whitespace-delimited token of the member's post
	 * title, which is how the real titles are shaped: "Aaron DeBoer, MBA" ->
	 * "Aaron", "Greg Johnson, MD, MBA, CMD" -> "Greg". A single-word title
	 * ("Cher") yields itself; an empty or whitespace-only title yields '' --
	 * preg_split() on '' returns array( '' ), so index 0 always exists and no
	 * notice is ever raised.
	 *
	 * @return string First name, or '' if there is no member in context.
	 */
	protected function get_member_first_name() {
		return self::get_first_name(
			array( 'source' => $this->props['source'] )
		);
	}

	/**
	 * The member's first name, for the %first_name% heading token.
	 *
	 * Also the `__first_name` computed property, which is what lets the builder's
	 * React component resolve the token instead of printing it literally.
	 *
	 * Static, and reached through resolve_member_id(), so it answers in the Theme
	 * Builder's layout editor too -- where it returns the stand-in member's name
	 * rather than nothing.
	 *
	 * Both member sources qualify. Only `current_member` did before, so a heading
	 * on the combined source would have printed the raw token even on a real
	 * member page.
	 *
	 * @param array $args         Depends-on values; only 'source' is read.
	 * @param array $conditional_tags Unused; part of Divi's callback signature.
	 * @param array $current_page Builder page context, if any.
	 * @return string Empty when the source is not member-driven, or no member resolves.
	 */
	public static function get_first_name( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		$source = isset( $args['source'] ) ? (string) $args['source'] : 'latest';

		if ( ! in_array( $source, array( 'current_member', 'current_member_custom' ), true ) ) {
			return '';
		}

		$member = honest_team_get_member( self::resolve_member_id( $current_page ) );

		if ( ! $member ) {
			return '';
		}

		$parts = preg_split( '/\s+/', trim( (string) $member['name'] ) );

		return isset( $parts[0] ) ? (string) $parts[0] : '';
	}

	/**
	 * Resolve the `%first_name%` token in a heading, if it carries one.
	 *
	 * A heading with no token is returned verbatim (trimmed) -- the `latest`
	 * and `manual` source modes are completely unaffected by this method. A
	 * heading that does carry the token but cannot resolve it returns '',
	 * which render() treats exactly like a blank heading field: no heading
	 * markup at all. See the file header comment for why that is the chosen
	 * degradation rather than substituting an empty string in place.
	 *
	 * @param string $heading Raw heading as typed into the field.
	 * @return string Heading to render, or '' to render no heading.
	 */
	protected function resolve_heading( $heading ) {
		if ( false === strpos( $heading, self::FIRST_NAME_TOKEN ) ) {
			return trim( $heading );
		}

		$first_name = $this->get_member_first_name();

		if ( '' === $first_name ) {
			return '';
		}

		return trim( str_replace( self::FIRST_NAME_TOKEN, $first_name, $heading ) );
	}

	public function render( $attrs, $content, $render_slug ) {
		$cards = self::get_cards_html(
			array(
				'source'     => $this->props['source'],
				'manual_ids' => $this->props['manual_ids'],
				'limit'      => $this->props['limit'],
				'show_all'   => $this->props['show_all'],
			)
		);

		if ( '' === $cards ) {
			return '';
		}

		$eyebrow = '';
		if ( '' !== trim( (string) $this->props['eyebrow'] ) ) {
			$eyebrow = sprintf( '<p class="honest-insights__eyebrow">%s</p>', esc_html( $this->props['eyebrow'] ) );
		}

		// resolve_heading() returns '' both for a blank field and for a
		// `%first_name%` heading with no member in context -- one branch
		// covers both, so neither can leave a partial heading behind.
		$heading      = '';
		$heading_text = $this->resolve_heading( (string) $this->props['heading'] );
		if ( '' !== $heading_text ) {
			$heading = sprintf( '<h2 class="honest-insights__heading">%s</h2>', esc_html( $heading_text ) );
		}

		$intro = '';
		if ( '' !== trim( (string) $this->content ) ) {
			$intro = sprintf( '<div class="honest-insights__intro">%s</div>', et_core_esc_previously( $this->content ) );
		}

		$button      = '';
		$button_text = trim( (string) $this->props['button_text'] );
		if ( '' !== $button_text ) {
			$button_url = trim( (string) $this->props['button_url'] );
			$button     = sprintf(
				'<a class="honest-insights__button" href="%1$s">%2$s</a>',
				esc_url( '' !== $button_url ? $button_url : '#' ),
				esc_html( $button_text )
			);
		}

		// `member`: heading (+ eyebrow/intro) and the button share one row above
		// the grid, matching the Team Member Page's finished design -- the
		// button sits beside the heading, not below the grid, so `$foot`
		// stays empty and the button is folded into `$head` instead.
		// `feature`: the Our Team page's finished design -- heading/intro
		// centred, button centred under the grid in its own `$foot`.
		//
		// Anything unrecognised resolves to `feature` rather than being passed
		// through into a class name.
		$style      = 'member' === $this->props['style'] ? 'member' : 'feature';
		$top_button = 'member' === $style && '' !== $button;

		$head = sprintf( '<div class="honest-insights__headtext">%1$s%2$s%3$s</div>', $eyebrow, $heading, $intro );
		if ( $top_button ) {
			$head .= $button;
		}

		$foot = ( ! $top_button && '' !== $button )
			? sprintf( '<div class="honest-insights__foot">%s</div>', $button )
			: '';

		// No inner wrapper: the module renders at 100% width and Divi's Row is
		// the page container, so there is nothing for one to do -- see rule 4
		// in assets/css/modules.css's header.
		$inner = sprintf(
			'<div class="honest-insights__head">%1$s</div><div class="honest-insights__grid">%2$s</div>%3$s',
			$head,
			$cards,
			$foot
		);

		return $this->wrap(
			$render_slug,
			$inner,
			// Both styles get a modifier, including the one whose rules are the
			// unqualified base: it costs nothing, it makes the choice readable in
			// the inspector, and it gives the base treatment somewhere to diverge
			// later without another round of this.
			array( 'honest-insights', 'honest-insights--' . $style ),
			array(
				'--hh-insights-eyebrow'       => $this->props['eyebrow_color'],
				'--hh-insights-heading'       => $this->props['heading_color'],
				'--hh-insights-intro'         => $this->props['intro_color'],
				'--hh-insights-button-bg'     => $this->props['button_bg_color'],
				'--hh-insights-button-text'   => $this->props['button_label_color'],
				'--hh-insights-button-border' => $this->props['button_border_color'],
			)
		);
	}
}
