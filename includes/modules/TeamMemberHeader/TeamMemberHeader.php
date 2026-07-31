<?php
/**
 * Team Member Header module.
 *
 * The top of an individual team member's page: a full-width "Back to Team
 * Page" strip, then a two-column header -- name/title/bio/quote/LinkedIn on
 * the left, portrait on the right. It renders on a Divi Theme Builder body
 * template assigned to the `article-author` post type, so `get_the_ID()`
 * there resolves to the member itself. Fields are read directly in PHP via
 * `honest_team_get_member( get_the_ID() )` (includes/data/team-data.php)
 * rather than assembled from Divi dynamic content: an ACF image field
 * (`author_image`) stores a raw attachment ID and an ACF relationship field
 * stores serialized data, neither of which dynamic content can turn into a
 * portrait or a link on its own. If there is no member in context (wrong
 * template, or the post is missing/unpublished), `render()` returns exactly
 * `''`, same as every other module here.
 *
 * Figma (file 6LBpKOMFlN8KxaKbut00YW), composited frame 224:2532 ("Team
 * Member Page"). Every node this module draws from was rendered with
 * get_screenshot and re-confirmed via get_design_context before anything was
 * extracted from it, per the standing lesson in this plugin that this file
 * mixes finished design with wireframe scaffolding that reads identically in
 * a metadata dump:
 *
 *   - Left column frame 224:3000 (name 224:2798, job title 224:2995, bio
 *     224:2799, pull quote 224:2910, LinkedIn group 224:2961 with label
 *     224:2962 and icon 224:2963) -- real, screenshot matches the design
 *     exactly, no placeholder wording ("Aaron DeBoer, MBA" / "Executive Vice
 *     President" / a genuine bio paragraph / a genuine attributed quote),
 *     unlike the "View All CTA" scaffolding a previous task in this plugin
 *     was burned by.
 *   - Portrait 224:2849 -- real (a real headshot), confirmed via
 *     get_design_context: the node is a single flattened image asset (ring,
 *     lavender backdrop and photo already baked into one PNG per member by
 *     whoever built the mock), not live, separately-stroked circle layers.
 *     Pixel-sampled off that asset: ring #6a4c92 (rounds to `#6a4c91`, the
 *     same purple already used elsewhere in this plugin -- the member card's
 *     name colour and this design's own quote colour) and backdrop
 *     `#d2d8ee` (the exact same lavender already the member card's
 *     `--hh-card-bg` fallback in modules.css).
 *
 *     An earlier pass here assumed a real deployment would upload a PLAIN
 *     portrait photo with no ring baked in, and so drew the ring and backdrop
 *     unconditionally in live CSS. That assumption is wrong for this site:
 *     every published member's `author_image` is a `*-PurpleCircle.png`
 *     asset with the ring and lavender backdrop already baked into the
 *     bitmap -- 12 of the 12 members with a usable portrait -- exactly like
 *     the Figma node itself. Drawing a second ring around one of those
 *     produced two concentric purple rings with a lavender gap between them
 *     on every member page.
 *
 *     So the CSS ring now defaults to OFF (`portrait_ring` => 'off'), and
 *     the capability is kept rather than deleted: turning the field on adds
 *     `honest-member--portrait-ring` to this module's wrapper, which is what
 *     switches the ring's border width on, and `portrait_ring_color` still
 *     governs its colour. That is the setting to flip if portraits are ever
 *     re-cut as plain photos. `portrait_bg_color` is applied either way: it
 *     is invisible behind a full-bleed circular portrait and is what the
 *     fallback placeholder mark sits on when a member's attachment is
 *     missing.
 *   - Back bar: strip 224:2787 (`#6a4c91` fill), label 224:2788 ("Back to
 *     Team Page", white, bold), arrow 224:2790 -- all real and rendered
 *     together in place above the header exactly as the brief describes.
 *     The arrow instance decomposes (get_design_context) into two mirrored
 *     chevron paths in white plus two smaller duplicate paths in `#6a4c91`
 *     offset slightly behind them -- a drop-shadow duplicate, the same class
 *     of thing a previous task in this plugin mistook for a gradient on a
 *     different node. The two white paths alone reproduce the visible "«"
 *     glyph; the purple duplicates are reproduced here as none, since they
 *     only contribute a few pixels of shadow depth to a purely decorative
 *     icon (aria-hidden, the link's own visible text carries the accessible
 *     name) and hand-transcribing a shadow offset from raw coordinates would
 *     add fragility for no accessible or informational gain.
 *
 * No node here was rejected as scaffolding -- everything the brief points at
 * checked out as the real, finished design.
 *
 * Every colour this module renders is a `'type' => 'color'` field
 * (get_fields()), Figma-extracted, applied via wrap()'s $css_vars map, same
 * as every other module in this plugin. Six of them (name, job title, bio,
 * quote, the LinkedIn link, the back-bar link) are paired with a font group
 * of the same slug so their typography is editable too; `hide_text_color` is
 * set on all six so Divi's own auto-generated "Text Color" sub-option can
 * never emit a competing rule that silently beats the inherited custom
 * property (the standing rule in this plugin -- see CallToAction.php for the
 * fullest account of why). Each colour field is named `{slug}_color`, never
 * `{slug}_text_color`: Divi's font-group generator auto-creates an
 * `"{group}_text_color"` prop for every font group unless `hide_text_color`
 * suppresses it (class-et-builder-element.php,
 * ET_Builder_Element::generate_font_options()), so naming a field
 * `name_text_color`, `quote_text_color`, etc. would be an exact match for
 * that auto-generated prop name on this module's own `name`/`quote`/etc.
 * font groups -- the same trap CallToAction.php already documents and was
 * fixed for. The two colours with no font group of their own -- the back
 * bar's solid fill and the portrait's ring/backdrop -- are named
 * `back_bg_color` and `portrait_ring_color`/`portrait_bg_color`, never
 * `background_color`: that exact name is reserved by Divi's own native
 * "Background" advanced option, which base_advanced_fields() enables for
 * every module in this plugin, and reusing it for an unrelated purpose here
 * would make Divi write its own competing `background-color`/`background-
 * image` CSS straight onto this module's order class (confirmed by testing
 * in CallToAction.php, not just by reading the source). Checked against both
 * traps before naming a single field here.
 *
 * The LinkedIn glyph and the back-bar arrow are both inlined as single-path
 * (or two-path) SVGs with `fill="currentColor"` rather than shipped as
 * static image assets: that is what lets one colour field
 * (`linkedin_color`, `back_label_color`) govern both the link's text and its
 * icon without a second field for the icon alone -- the icon is decorative
 * either way (aria-hidden), so it has no colour of its own to expose
 * separately from the text it sits beside.
 *
 * Missing-portrait fallback: this module falls back to the shared
 * `honest_team_render_media_placeholder()` partial (the greyscale Honest
 * Health heart), exactly like the member-card partials, and for the same
 * reason -- the check is against the rendered HTML, not `image_id`, because
 * a non-zero `author_image` meta value can still point at a deleted or
 * never-existent attachment. Member 102473 (Greg Johnson, MD, MBA, CMD) is a
 * live example: `author_image` is `102469`, a non-existent attachment ID, so
 * `wp_get_attachment_image()` returns `''` despite the meta being non-zero.
 * Reusing the shared placeholder here (rather than inventing a second, header-
 * specific empty state) keeps the "missing media" treatment consistent for a
 * site visitor regardless of which module they're looking at, and costs
 * nothing extra to wire in -- the partial is already loaded by the plugin
 * bootstrap for the card partials.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Team_Member_Header extends Honest_Divi_Module_Base {

	public $slug = 'honest_team_member_header';

	/**
	 * Full builder compatibility; component in assets/js/vb-modules.js under this
	 * slug.
	 *
	 * Everything this module draws comes from the member being viewed, so the
	 * whole body arrives as server-rendered HTML through the `__body` computed
	 * property and the component is a thin shell around it. That includes the back
	 * bar: its label and URL are props, but folding it into the same string keeps
	 * the two inline SVGs -- the back arrow and the LinkedIn mark -- out of
	 * JavaScript, which is worth more than making a rarely-edited label instant.
	 *
	 * Colours and the portrait ring stay prop-driven: they are custom properties
	 * and a modifier class on the wrapper the component builds, so they update
	 * without a round-trip.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	public function init() {
		$this->name             = esc_html__( 'Team Member Header', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Back Bar', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'name'       => esc_html__( 'Name', 'honest-divi-modules' ),
					'job_title'  => esc_html__( 'Job Title', 'honest-divi-modules' ),
					'bio'        => esc_html__( 'Bio', 'honest-divi-modules' ),
					'quote'      => esc_html__( 'Pull Quote', 'honest-divi-modules' ),
					'linkedin'   => esc_html__( 'LinkedIn Link', 'honest-divi-modules' ),
					'back_label' => esc_html__( 'Back Bar Link', 'honest-divi-modules' ),
					'colors'     => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color on every font group below: colour for each is owned
		// exclusively by this module's own `{slug}_color` custom colour field,
		// applied as an inline CSS custom property (see render()). Without
		// this flag Divi's font-group builder auto-generates a native "Text
		// Color" sub-option per group that emits a directly-targeted CSS rule,
		// which would silently beat the inherited custom-property value --
		// see the file header comment for the full account, including the
		// second, independent reason this flag matters (it also suppresses
		// Divi's auto-generated `"{slug}_text_color"` prop, which is exactly
		// why none of this module's colour fields are named that).
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				// font_size / weight / line_height defaults are the values on the
				// Figma text nodes in q1MGpWgDpBoZeS6dgrddjB node 21:459: name
				// 1:339 (48 / 700 / 1.27), job title 1:345 (24.444 / 700), bio
				// 1:340 (18 / 400 / 1.45), quote 1:341 (24 / 700 / 1.27) and the
				// LinkedIn link 1:343 (18 / 700). The same numbers live in
				// assets/css/modules.css, which is the floor -- a Divi default
				// emits no CSS for an instance saved before the default existed,
				// and this template was built long before this pass.
				// No line_height default on the job title or the link: the design
				// specifies `normal`, which Divi's control cannot express.
				'name'       => array(
					'label'           => esc_html__( 'Name', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-member__name" ),
					'toggle_slug'     => 'name',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '48px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.27em' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
				'job_title'  => array(
					'label'           => esc_html__( 'Job Title', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-member__title" ),
					'toggle_slug'     => 'job_title',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '24.444px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
				'bio'        => array(
					'label'           => esc_html__( 'Bio', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-member__bio" ),
					'toggle_slug'     => 'bio',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '18px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.45em' ),
					'font'            => array( 'default' => '|400|||||||' ),
				),
				'quote'      => array(
					'label'           => esc_html__( 'Pull Quote', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-member__quote" ),
					'toggle_slug'     => 'quote',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '24px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.27em' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
				'linkedin'   => array(
					'label'           => esc_html__( 'LinkedIn Link', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-member__linkedin" ),
					'toggle_slug'     => 'linkedin',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '18px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
				'back_label' => array( 'label' => esc_html__( 'Back Bar Link', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-member__back-link" ), 'toggle_slug' => 'back_label', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			// The only two genuinely editable content fields on this module --
			// everything else in the header (name, title, bio, quote,
			// LinkedIn, portrait) comes straight off the member post itself
			// via honest_team_get_member(), per the file header comment.
			// Delivers the whole body to the builder's React component.
			'__body'              => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Team_Member_Header', 'get_body_html' ),
				'computed_depends_on' => array( 'back_text', 'back_url' ),
			),
			'back_text'           => array(
				'label'           => esc_html__( 'Back Bar Text', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => esc_html__( 'Back to Team Page', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'back_url'            => array(
				'label'           => esc_html__( 'Back Bar URL', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Where the back bar links to, e.g. the Our Team page.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'url',
			),
			// Colour fields. Defaults are the hexes extracted from Figma (file
			// 6LBpKOMFlN8KxaKbut00YW) -- see the file header comment for the
			// method (get_screenshot + get_design_context on every node, pixel
			// sampling for the portrait) and for why none of these are named
			// `{slug}_text_color` or `background_color`.
			'name_color'          => array(
				'label'        => esc_html__( 'Name Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the member\'s name.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#1e1e1e',
			),
			'job_title_color'     => array(
				'label'        => esc_html__( 'Job Title Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the job title.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#070707',
			),
			'bio_color'           => array(
				'label'        => esc_html__( 'Bio Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the bio paragraph.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#1e1e1e',
			),
			'quote_color'         => array(
				'label'        => esc_html__( 'Pull Quote Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the pull quote. Hidden entirely if the member has no quote.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
			'linkedin_color'      => array(
				'label'        => esc_html__( 'LinkedIn Link Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the LinkedIn link and its icon. Hidden entirely if the member has no LinkedIn URL.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			// Named `back_bg_color`, not `background_color` -- see the file
			// header comment for why that name collides with Divi's own
			// native Background option.
			'back_bg_color'       => array(
				'label'        => esc_html__( 'Back Bar Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Fill colour of the full-width back bar.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
			'back_label_color'    => array(
				'label'        => esc_html__( 'Back Bar Link Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the back bar\'s text and arrow icon.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			// Portrait ring + backdrop. Both pixel-sampled off the Figma
			// portrait asset (see the file header comment) -- both numbers
			// happen to match tokens already used elsewhere in this plugin
			// (the member card's name colour and `--hh-card-bg` fallback
			// respectively).
			//
			// The ring is OFF by default because every published member's
			// portrait is a `*-PurpleCircle.png` with the ring already baked
			// into the bitmap, and a CSS ring on top of one of those renders
			// as a double ring -- see the file header comment. The field is
			// here, rather than the ring simply being deleted, so the
			// treatment can be switched back on (with its colour) if
			// portraits are ever re-cut as plain photos.
			'portrait_ring'       => array(
				'label'            => esc_html__( 'Portrait Ring', 'honest-divi-modules' ),
				'description'      => esc_html__( 'Draw a coloured ring around the portrait. Leave off for portraits that already have the ring baked into the image, which is how every current team member\'s photo is supplied.', 'honest-divi-modules' ),
				'type'             => 'yes_no_button',
				'option_category'  => 'configuration',
				'options'          => array(
					'off' => esc_html__( 'No', 'honest-divi-modules' ),
					'on'  => esc_html__( 'Yes', 'honest-divi-modules' ),
				),
				'default'          => 'off',
				'default_on_front' => 'off',
				'tab_slug'         => 'advanced',
				'toggle_slug'      => 'colors',
			),
			'portrait_ring_color' => array(
				'label'        => esc_html__( 'Portrait Ring Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the circular ring around the portrait. Only visible while Portrait Ring is on.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
				'show_if'      => array(
					'portrait_ring' => 'on',
				),
			),
			'portrait_bg_color'   => array(
				'label'        => esc_html__( 'Portrait Backdrop Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour behind the portrait (and behind the fallback mark if no portrait resolves).', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#d2d8ee',
			),
		);
	}

	/**
	 * The LinkedIn brand mark, single-path, `fill="currentColor"` so it
	 * always matches whatever `linkedin_color` resolves to -- see the file
	 * header comment for why there is no separate icon colour field.
	 * Decorative: aria-hidden, no alt text of its own. Path traced from the
	 * real Figma icon node (224:2963, file 6LBpKOMFlN8KxaKbut00YW), which
	 * was itself a flat `#6985c3` fill -- this module's own default for
	 * `linkedin_color`.
	 *
	 * @return string Trusted, static SVG markup (no user input).
	 */
	private static function linkedin_icon_svg() {
		return '<svg class="honest-member__linkedin-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M21.3333 0C22.0406 0 22.7189 0.280951 23.219 0.781048C23.719 1.28115 24 1.95942 24 2.66667V21.3333C24 22.0406 23.719 22.7189 23.219 23.219C22.7189 23.719 22.0406 24 21.3333 24H2.66667C1.95942 24 1.28115 23.719 0.781048 23.219C0.280951 22.7189 0 22.0406 0 21.3333V2.66667C0 1.95942 0.280951 1.28115 0.781048 0.781048C1.28115 0.280951 1.95942 0 2.66667 0H21.3333ZM20.6667 20.6667V13.6C20.6667 12.4472 20.2087 11.3416 19.3936 10.5264C18.5784 9.71128 17.4728 9.25333 16.32 9.25333C15.1867 9.25333 13.8667 9.94667 13.2267 10.9867V9.50667H9.50667V20.6667H13.2267V14.0933C13.2267 13.0667 14.0533 12.2267 15.08 12.2267C15.5751 12.2267 16.0499 12.4233 16.3999 12.7734C16.75 13.1235 16.9467 13.5983 16.9467 14.0933V20.6667H20.6667ZM5.17333 7.41333C5.76742 7.41333 6.33717 7.17733 6.75725 6.75725C7.17733 6.33717 7.41333 5.76742 7.41333 5.17333C7.41333 3.93333 6.41333 2.92 5.17333 2.92C4.57571 2.92 4.00257 3.1574 3.57999 3.57999C3.1574 4.00257 2.92 4.57571 2.92 5.17333C2.92 6.41333 3.93333 7.41333 5.17333 7.41333ZM7.02667 20.6667V9.50667H3.33333V20.6667H7.02667Z"/></svg>';
	}

	/**
	 * The back bar's double-chevron ("«") mark, two paths, both
	 * `fill="currentColor"` so they always match `back_label_color`.
	 * Decorative: aria-hidden. Traced from the two real (white) foreground
	 * paths of the Figma arrow instance (224:2790); the instance's two
	 * additional, smaller `#6a4c91` paths are a drop-shadow duplicate offset
	 * slightly behind them, deliberately not reproduced here -- see the file
	 * header comment for why.
	 *
	 * @return string Trusted, static SVG markup (no user input).
	 */
	private static function back_arrow_svg() {
		return '<svg class="honest-member__back-icon" width="20" height="17" viewBox="0 0 26 22" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M10.8137 0.0213716C10.8974 0.010144 10.9817 0.00320219 11.0661 0.000547019C11.9226 -0.024753 12.5032 0.833285 13.0656 1.38355C13.5634 1.87059 14.1437 2.37173 14.5505 2.92518C14.7697 3.22328 14.7552 3.9563 14.5072 4.23756C13.9249 4.93723 13.2157 5.53563 12.5778 6.18771C10.9716 7.82966 9.28128 9.41329 7.69764 11.075C8.1233 11.391 8.36144 11.6775 8.73897 12.0458L10.7506 14.0427C11.9205 15.2101 13.1238 16.366 14.286 17.5422C14.5998 17.8597 14.7297 18.1483 14.7054 18.6055C14.6932 18.8409 14.6166 19.0684 14.4839 19.2634C14.2507 19.6111 13.6355 20.142 13.3195 20.4595C12.9157 20.8518 12.5389 21.2739 12.1257 21.6564C11.7368 22.0163 11.4242 22.1107 10.9158 22.0821C10.7868 22.0579 10.6618 22.0162 10.5441 21.9582C10.2091 21.7948 7.86261 19.3785 7.39526 18.9171L2.83226 14.3978C2.04386 13.6147 1.24911 12.8304 0.469412 12.0395C0.270876 11.8381 0.160833 11.7108 0.0616221 11.456C-0.0243603 11.244 -0.0313541 10.6987 0.111646 10.5082C0.701663 9.72201 1.63029 8.88236 2.32298 8.19342L5.08635 5.43675L8.35346 2.17377C8.95298 1.57518 9.55577 0.959937 10.173 0.379364C10.3559 0.207385 10.5768 0.100306 10.8137 0.0213716Z"/><path fill="currentColor" transform="translate(11.23, -0.02)" d="M10.8211 0.024065C10.9287 0.0109409 11.0363 0.00301359 11.1442 0.000320488C11.7448 -0.0152692 12.3287 0.54099 12.721 0.992103C13.3915 1.76336 15.3559 2.90948 14.6653 4.10928C14.4847 4.42285 13.9518 4.92039 13.6876 5.18254L12.4324 6.43267C10.8922 7.96819 9.30424 9.51896 7.78787 11.0742C7.91555 11.1718 8.06293 11.3189 8.17924 11.4329L12.4157 15.6619L13.7416 16.9765C14.1589 17.3908 14.7899 17.906 14.7778 18.5333C14.7641 19.2323 13.9503 19.8534 13.4755 20.3149C12.9205 20.8546 12.3937 21.5192 11.7505 21.9413C11.5342 22.0834 11.2385 22.0937 10.9891 22.0903C10.8584 22.0673 10.7327 22.0351 10.6115 21.9795C10.1864 21.7844 6.81337 18.2951 6.19332 17.6837L2.3595 13.9096C1.74823 13.3043 1.13681 12.6963 0.530832 12.0869C-0.126429 11.4259 -0.204809 10.7395 0.473243 10.0523C1.14457 9.37187 1.82661 8.69908 2.50436 8.02418L5.84958 4.68753L8.67746 1.87874C9.18781 1.37206 10.1851 0.233748 10.8211 0.024065Z"/></svg>';
	}

	/**
	 * The whole module body -- back bar and member block -- rendered server-side.
	 *
	 * Shared by render() and by the `__body` computed property.
	 *
	 * Static because Divi calls computed callbacks as plain callables with no
	 * module instance, so the two inline SVGs it needs are static too.
	 *
	 * @param array $args         back_text / back_url.
	 * @param array $conditional_tags Unused; part of Divi's callback signature.
	 * @param array $current_page Divi's page context. Preferred over get_the_ID()
	 *                            because during a computed-property request the
	 *                            queried object is not the member being previewed.
	 * @return string Empty when the post in context is not a team member.
	 */
	public static function get_body_html( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		$member_id = ! empty( $current_page['id'] ) ? (int) $current_page['id'] : (int) get_the_ID();
		$member    = honest_team_get_member( $member_id );

		if ( ! $member ) {
			return '';
		}

		$back_text = trim( (string) ( isset( $args['back_text'] ) ? $args['back_text'] : '' ) );
		if ( '' === $back_text ) {
			// Never let the back link fall back to an icon-only accessible
			// name (the arrow is aria-hidden) -- see the accessibility
			// requirements in the brief.
			$back_text = esc_html__( 'Back to Team Page', 'honest-divi-modules' );
		}
		$back_url = trim( (string) ( isset( $args['back_url'] ) ? $args['back_url'] : '' ) );

		$backbar = sprintf(
			'<div class="honest-member__backbar"><a class="honest-member__back-link" href="%1$s">%2$s%3$s</a></div>',
			esc_url( '' !== $back_url ? $back_url : '#' ),
			self::back_arrow_svg(),
			esc_html( $back_text )
		);

		$linkedin = '' !== trim( $member['linkedin'] )
			? sprintf(
				'<a class="honest-member__linkedin" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s%3$s</a>',
				esc_url( $member['linkedin'] ),
				self::linkedin_icon_svg(),
				esc_html__( 'View LinkedIn Profile', 'honest-divi-modules' )
			)
			: '';

		$quote = '' !== trim( $member['quote'] )
			? sprintf( '<blockquote class="honest-member__quote">%s</blockquote>', esc_html( trim( $member['quote'] ) ) )
			: '';

		// Tested on the rendered HTML, not on image_id -- a member can carry a
		// non-zero author_image whose attachment no longer exists (member
		// 102473 does exactly this), in which case wp_get_attachment_image()
		// returns ''. Both that and a genuinely unset image land on the
		// shared placeholder partial -- see the file header comment.
		$portrait = $member['image_id']
			? wp_get_attachment_image( $member['image_id'], 'large', false, array( 'class' => 'honest-member__portrait' ) )
			: '';

		if ( '' === $portrait ) {
			$portrait = honest_team_render_media_placeholder();
		}

		return $backbar . sprintf(
			'<div class="honest-member__inner">
				<div class="honest-member__text">
					<h1 class="honest-member__name">%1$s</h1>
					<p class="honest-member__title">%2$s</p>
					<div class="honest-member__bio">%3$s</div>
					%4$s
					%5$s
				</div>
				<div class="honest-member__media">%6$s</div>
			</div>',
			esc_html( $member['name'] ),
			esc_html( $member['job_title'] ),
			wpautop( esc_html( $member['bio'] ) ),
			$quote,
			$linkedin,
			$portrait
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$inner = self::get_body_html(
			array(
				'back_text' => $this->props['back_text'],
				'back_url'  => $this->props['back_url'],
			)
		);

		if ( '' === $inner ) {
			return '';
		}

		// The ring is a wrapper modifier class rather than another custom
		// property because its "off" state is a border WIDTH of zero, and
		// wrap()'s $css_vars map only ever carries validated colour (or image
		// URL) values -- see Honest_Divi_Module_Base::build_style_attr().
		$ring = 'on' === $this->props['portrait_ring'];

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-member', $ring ? 'honest-member--portrait-ring' : '' ),
			array(
				'--hh-member-name'          => $this->props['name_color'],
				'--hh-member-title'         => $this->props['job_title_color'],
				'--hh-member-bio'           => $this->props['bio_color'],
				'--hh-member-quote'         => $this->props['quote_color'],
				'--hh-member-linkedin'      => $this->props['linkedin_color'],
				'--hh-member-back-bg'       => $this->props['back_bg_color'],
				'--hh-member-back-label'    => $this->props['back_label_color'],
				'--hh-member-portrait-ring' => $this->props['portrait_ring_color'],
				'--hh-member-portrait-bg'   => $this->props['portrait_bg_color'],
			)
		);
	}
}
