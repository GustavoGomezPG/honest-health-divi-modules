<?php
/**
 * Seed the "Our Team" page and the Teams settings that fill it.
 *
 * Everything this script writes is DATABASE STATE, not code, so none of it
 * travels with a plugin deploy: the Divi layout on the "Our Team" page, the
 * Executive Team relationship on Teams -> Executive Team, the four rows on
 * Teams -> Markets, and the pull quotes that decide which members appear in
 * the testimonial carousel. This script is how all of it reaches another
 * environment (staging/production).
 *
 * Run with:
 *   wp eval-file bin/seed-team-content.php
 *
 * SAFE TO RE-RUN. There are three separate write paths and each one refuses,
 * by default, to overwrite anything a human may have chosen:
 *
 *   1. THE PAGE LAYOUT is only rewritten while it is still byte-for-byte what
 *      this script last wrote, tracked by a content hash in
 *      `_honest_our_team_seed_hash` on the page. If it has been edited since --
 *      most realistically by someone re-saving the page in the Divi Builder,
 *      which re-serializes the shortcode -- the script leaves the content
 *      alone, says so, and KEEPS REFUSING ON EVERY SUBSEQUENT RUN. It
 *      deliberately does not re-record the hash on that path: doing so would
 *      make the next run mistake the edit for its own output and silently
 *      revert it, so two deploys in a row would quietly destroy the editor's
 *      work. This is the same guard, and the same reasoning, as
 *      bin/create-member-template.php.
 *
 *      A MISSING hash is deliberately not "untouched" either: page 20 already
 *      existed and already had a hand-built Divi layout on it before this
 *      script was ever written, so "I have no record of writing this" must
 *      mean "someone else built it", never "safe to replace".
 *
 *   2. THE TEAMS SETTINGS (Executive Team, Markets) are only seeded while they
 *      are EMPTY. They are the client's own editorial decisions -- who counts
 *      as an executive, which market each person belongs to, and what each
 *      market's caption says -- so the moment there is anything on either
 *      screen this script stops touching it. The values below are a plausible
 *      starting point built from the members' own job titles, NOT a client
 *      decision; see the report for exactly what was guessed.
 *
 *   3. THE PULL QUOTES are only written onto a member whose `quote` is
 *      currently empty, for the same reason: a quote is copy, and copy is the
 *      client's. Only members with a non-empty quote appear in the testimonial
 *      carousel, so seeding two or three is what makes the carousel
 *      demonstrable at all.
 *
 * Resolving a refusal is a deliberate human act, one of exactly two:
 *
 *   - Keep what is there: do nothing. A refusal is not an error and the run
 *     still exits successfully.
 *   - Discard it and reseed from this script:
 *       wp eval-file bin/seed-team-content.php force
 *
 * `force` is the ONLY way to make the script overwrite content it did not
 * author, and it applies to all three paths at once.
 *
 * NOTHING IS ADDRESSED BY POST ID. The page is found by its path
 * (`our-team`) and every member by its post slug, so this script works
 * unchanged against a database whose IDs differ -- the same rule
 * bin/migrate-slug.php follows for the same reason. A slug that resolves to
 * nothing is reported and skipped rather than silently dropping a person out
 * of a grid.
 *
 * WHAT THE PAGE CONTAINS
 *
 * Six Divi sections, in the order of Figma frame 50:470 (file
 * 6LBpKOMFlN8KxaKbut00YW), one module each, every row at DIVI'S OWN WIDTH:
 *
 *   1. [honest_text_hero]             purple gradient band, "Our Team." eyebrow
 *   2. [honest_leadership_by_market]  region tabs, member grid, Lottie map
 *   3. [honest_executive_leadership]  heading, intro, 4-up member grid
 *   4. [honest_testimonials]          quote carousel on its own coloured band
 *   5. [honest_featured_insights]     3 article cards, button centred BELOW
 *   6. [honest_call_to_action]        closing band
 *
 * THE ROWS CARRY NO WIDTH ATTRIBUTES. They used to be forced to
 * `width="100%" max_width="100%"`, which was only ever necessary because every
 * module carried a page container of its own (`max-width: 1245px; margin: 0
 * auto`) and the two would otherwise have compounded -- i.e. the modules were
 * acting as rows. The modules render at 100% width now, so the row is the only
 * container and Divi decides how wide it is (Divi > Theme Options > Layout >
 * Website Content Width). The rows keep `custom_padding="0px||0px||true|false"`
 * because that is VERTICAL padding only: these sections are edge-to-edge bands
 * and stacked grids that supply their own vertical rhythm, and nothing about
 * the width fix changes that. A module whose band has to reach the viewport
 * edges paints it with a breakout layer of its own -- see rule 5 in
 * assets/css/modules.css's header -- so a normal-width row is no obstacle.
 *
 * Section 5 is the one section that carries a background: the Featured
 * Insights module's heading/intro default to WHITE because on this page they
 * sit on the purple band (Figma node 50:812, #6a4c91), and that band is
 * Divi's own native section background -- the module does not render or own
 * it. Without it the white text would be white-on-white. (On the member page
 * the same module sits on a light background and overrides those two colours
 * to dark instead -- see bin/create-member-template.php.)
 *
 * Sections 1, 4 and 6 need no section background: the Text Hero, Testimonials
 * and Call To Action modules each render their own band.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'update_field' ) || ! function_exists( 'get_field' ) ) {
	WP_CLI::error( 'ACF is not active; update_field()/get_field() are unavailable.' );
}

if ( ! function_exists( 'honest_team_member_post_type' ) ) {
	WP_CLI::error( 'The Honest Divi Modules plugin is not active; honest_team_member_post_type() is unavailable.' );
}

$honest_seed_page_path   = 'our-team';
$honest_seed_hash_meta   = '_honest_our_team_seed_hash';
$honest_seed_member_type = honest_team_member_post_type();

/**
 * Whether to discard human edits and reseed everything from this script. Off
 * unless `force` is passed as a positional argument:
 *
 *   wp eval-file bin/seed-team-content.php force
 *
 * A positional argument, not `--force`: WP-CLI's own parser rejects unknown
 * `--` flags on `wp eval-file` before the file is ever included, so a `--`
 * flag cannot reach this script at all. `--force` is still accepted here in
 * case a future WP-CLI passes it through, so the obvious spelling never
 * silently no-ops into a destructive default.
 */
$honest_seed_force = isset( $args ) && is_array( $args )
	&& ( in_array( 'force', $args, true ) || in_array( '--force', $args, true ) );

/**
 * Whether to (re)record the page content hash at the end of the run.
 *
 * Cleared on exactly one path: when a foreign edit to the page is detected and
 * preserved. The hash must always describe the content THIS SCRIPT wrote,
 * never whatever happens to be in the post -- if a run that refused to touch
 * an edit still re-stamped the hash, the next run would read that edit as its
 * own previous output and silently overwrite it.
 */
$honest_seed_stamp_hash = true;

/**
 * Resolve a member post slug to a published member post ID.
 *
 * Returns 0 when the slug matches nothing published of the member post type,
 * which every caller reports rather than swallowing: a mistyped or renamed
 * slug should show up as a named warning, not as a person quietly missing
 * from a grid.
 *
 * @param string $slug Post slug (post_name).
 * @return int Post ID, or 0 if there is no published member with that slug.
 */
$honest_seed_resolve = static function ( $slug ) use ( $honest_seed_member_type ) {
	$found = get_posts(
		array(
			'post_type'        => $honest_seed_member_type,
			'post_status'      => 'publish',
			'name'             => $slug,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	return ! empty( $found ) ? (int) $found[0] : 0;
};

/**
 * Resolve a list of member slugs, warning about each one that does not
 * resolve.
 *
 * @param callable $resolve Slug resolver.
 * @param string[] $slugs   Member post slugs, in display order.
 * @param string   $context Human label for the warning message.
 * @return int[] Resolved post IDs, in the same order, misses omitted.
 */
$honest_seed_resolve_all = static function ( $resolve, $slugs, $context ) {
	$ids = array();

	foreach ( $slugs as $slug ) {
		$id = $resolve( $slug );

		if ( ! $id ) {
			WP_CLI::warning( sprintf( '%s: no published member with slug "%s"; skipping.', $context, $slug ) );
			continue;
		}

		$ids[] = $id;
	}

	return $ids;
};

/*
 * -------------------------------------------------------------------------
 * The seed values.
 * -------------------------------------------------------------------------
 *
 * PROVISIONAL -- NOT CLIENT-CONFIRMED. Who is an executive, which market each
 * person belongs to, and what each market caption says are all the client's
 * decisions. These groupings were inferred from the members' own
 * `job_title_short` values ("MCMO NY" -> East, "VP & GM MI" -> Midwest, and
 * so on) and, where the titles said nothing at all, guessed outright. The
 * state lists in the captions are NOT guessed: they are read off the Lottie
 * map's own segment manifest (assets/lottie/market-map-segments.json), so the
 * text under the map always names the states the map actually highlights.
 */

/** Executive Team, in the order they should appear in the grid. */
$honest_seed_executives = array(
	'rob-bessler-md',
	'jason-howard',
	'aaron-deboer',
	'scott-sears-md-mba',
	'greg-johnson-md-mba-cmd',
	'dave-crocker',
	'soumya-mamidala',
	'michael-burris',
);

/*
 * Markets. ROW ORDER IS LOAD-BEARING: the Lottie map segment a market plays is
 * chosen by its POSITION in this repeater, not by the name typed into it (see
 * honest_team_get_markets() and honest_team_market_segments()). Row 1 must be
 * West, row 2 Southwest, row 3 Midwest, row 4 East, or every tab drives the
 * wrong region of the map.
 */
$honest_seed_markets = array(
	array(
		'name'    => 'West',
		'caption' => 'Honest Health’s West market includes: California, Oregon, Washington, Nevada, Arizona, Idaho, Utah, Montana, New Mexico, Wyoming, and Colorado.',
		'members' => array( 'spencer-solomon', 'scott-sears-md-mba' ),
	),
	array(
		'name'    => 'Southwest',
		'caption' => 'Honest Health’s Southwest market includes: Arizona, New Mexico, Texas, and Oklahoma.',
		'members' => array( 'soumya-mamidala', 'greg-johnson-md-mba-cmd' ),
	),
	array(
		'name'    => 'Midwest',
		'caption' => 'Honest Health’s Midwest market includes: North Dakota, South Dakota, Nebraska, Minnesota, Iowa, Missouri, Wisconsin, Illinois, Michigan, Indiana, and Ohio.',
		'members' => array( 'melanie-odeleye-md-mph', 'mary-zuckerman' ),
	),
	array(
		'name'    => 'East',
		'caption' => 'Honest Health’s East market includes: Maine, New Hampshire, Vermont, Massachusetts, Connecticut, New York, New Jersey, and Pennsylvania.',
		'members' => array( 'adam-silverman-md', 'jessi-gordon' ),
	),
);

/*
 * Pull quotes, keyed by member slug. Only members with a non-empty quote reach
 * the testimonial carousel, and every member's quote was empty, so the
 * carousel rendered nothing at all before this.
 *
 * The Rob Bessler quote is REAL: it is the finished copy in the Figma design
 * (frame 50:470, quote node 50:816, attributed on node 50:817 to
 * "Rob Bessler, MD, CEO"). The other two are INVENTED placeholder copy written
 * to give the carousel more than one slide to move between -- they are not
 * anything either person said, and must be replaced or removed before this
 * page is shown to anyone outside the project.
 */
$honest_seed_quotes = array(
	'rob-bessler-md' => 'Honest Health approaches value-based care differently, with the strong belief of our team and our experience at delivering on performance and understanding how important it is as a physician-run and led business to have the physician out front in getting results. That is a unique model.',
	'jason-howard'   => 'The partnerships that work are the ones built on shared accountability. We show up as an extension of the practices we serve, and we measure ourselves on the same outcomes they do.',
	'aaron-deboer'   => 'Health systems do not need another vendor. They need a partner who has done the work before, understands the economics, and can translate strategy into something a clinic can run on Monday morning.',
);

/*
 * -------------------------------------------------------------------------
 * 1. Teams -> Executive Team.
 * -------------------------------------------------------------------------
 *
 * Written by FIELD KEY, not by field name. Both field groups here are
 * registered in code against an `options_page` location rule
 * (includes/admin/team-settings.php), and ACF cannot reliably resolve a plain
 * field NAME against a bare 'option' post_id -- there is no options page in
 * context during a WP-CLI run for it to match that location rule against. The
 * key is unambiguous and needs no location match. Reading it back afterwards
 * by name still works, because the write also stores the name -> key reference
 * row that get_field() follows.
 */
$honest_seed_exec_ids     = $honest_seed_resolve_all( $honest_seed_resolve, $honest_seed_executives, 'Executive Team' );
$honest_seed_exec_current = array_map( 'intval', (array) get_field( 'executive_team_members', 'option' ) );
$honest_seed_exec_current = array_filter( $honest_seed_exec_current );

if ( empty( $honest_seed_exec_ids ) ) {
	WP_CLI::warning( 'Executive Team: not one slug resolved to a published member; leaving the setting alone.' );
} elseif ( empty( $honest_seed_exec_current ) ) {
	update_field( 'field_honest_executive_members', $honest_seed_exec_ids, 'option' );
	WP_CLI::log( sprintf( 'Executive Team: seeded %d members (%s).', count( $honest_seed_exec_ids ), implode( ', ', $honest_seed_exec_ids ) ) );
} elseif ( $honest_seed_force ) {
	WP_CLI::warning( 'force: replacing the existing Executive Team selection.' );
	update_field( 'field_honest_executive_members', $honest_seed_exec_ids, 'option' );
} else {
	WP_CLI::log( sprintf( 'Executive Team: already has %d members; left untouched (use `force` to reseed).', count( $honest_seed_exec_current ) ) );
}

/*
 * -------------------------------------------------------------------------
 * 2. Teams -> Markets.
 * -------------------------------------------------------------------------
 */
$honest_seed_market_rows = array();

foreach ( $honest_seed_markets as $honest_seed_market ) {
	$honest_seed_market_rows[] = array(
		'market_name'    => $honest_seed_market['name'],
		'market_caption' => $honest_seed_market['caption'],
		'market_members' => $honest_seed_resolve_all(
			$honest_seed_resolve,
			$honest_seed_market['members'],
			sprintf( 'Market "%s"', $honest_seed_market['name'] )
		),
	);
}

if ( count( $honest_seed_market_rows ) > HONEST_TEAM_MAX_MARKETS ) {
	WP_CLI::error(
		sprintf(
			'This script defines %d markets but the Lottie map has only %d segments; aborting rather than writing rows the map cannot drive.',
			count( $honest_seed_market_rows ),
			HONEST_TEAM_MAX_MARKETS
		)
	);
}

$honest_seed_markets_current = (array) get_field( 'markets', 'option' );
$honest_seed_markets_current = array_filter(
	$honest_seed_markets_current,
	static function ( $row ) {
		return ! empty( $row['market_name'] );
	}
);

if ( ! empty( $honest_seed_markets_current ) && ! $honest_seed_force ) {
	WP_CLI::log( sprintf( 'Markets: already has %d rows; left untouched (use `force` to reseed).', count( $honest_seed_markets_current ) ) );
} else {
	if ( ! empty( $honest_seed_markets_current ) ) {
		WP_CLI::warning( 'force: replacing the existing Markets rows.' );
	}

	update_field( 'field_honest_markets', $honest_seed_market_rows, 'option' );

	foreach ( $honest_seed_market_rows as $honest_seed_index => $honest_seed_row ) {
		WP_CLI::log(
			sprintf(
				'Markets: row %d = %s -> map segment %d, %d members.',
				$honest_seed_index + 1,
				$honest_seed_row['market_name'],
				$honest_seed_index,
				count( $honest_seed_row['market_members'] )
			)
		);
	}
}

/*
 * -------------------------------------------------------------------------
 * 3. Pull quotes.
 * -------------------------------------------------------------------------
 *
 * By field key for the same reason as above, and per-member rather than in a
 * batch so one unresolvable slug cannot cost the others their quote.
 */
foreach ( $honest_seed_quotes as $honest_seed_slug => $honest_seed_quote ) {
	$honest_seed_member_id = $honest_seed_resolve( $honest_seed_slug );

	if ( ! $honest_seed_member_id ) {
		WP_CLI::warning( sprintf( 'Quote: no published member with slug "%s"; skipping.', $honest_seed_slug ) );
		continue;
	}

	$honest_seed_existing = trim( (string) get_post_meta( $honest_seed_member_id, 'quote', true ) );

	if ( '' !== $honest_seed_existing && ! $honest_seed_force ) {
		WP_CLI::log( sprintf( 'Quote: %s (%d) already has one; left untouched.', $honest_seed_slug, $honest_seed_member_id ) );
		continue;
	}

	if ( '' !== $honest_seed_existing ) {
		WP_CLI::warning( sprintf( 'force: replacing the existing quote on %s (%d).', $honest_seed_slug, $honest_seed_member_id ) );
	}

	update_field( 'field_honest_member_quote', $honest_seed_quote, $honest_seed_member_id );
	WP_CLI::log( sprintf( 'Quote: set on %s (%d).', $honest_seed_slug, $honest_seed_member_id ) );
}

/*
 * -------------------------------------------------------------------------
 * 4. The "Our Team" page layout.
 * -------------------------------------------------------------------------
 */
$honest_seed_page = get_page_by_path( $honest_seed_page_path, OBJECT, 'page' );

if ( ! $honest_seed_page ) {
	WP_CLI::error( sprintf( 'No page found at path "%s"; aborting without changing the page.', $honest_seed_page_path ) );
}

$honest_seed_page_id = (int) $honest_seed_page->ID;

WP_CLI::log( sprintf( 'Found page %d ("%s") at /%s/.', $honest_seed_page_id, $honest_seed_page->post_title, $honest_seed_page_path ) );

/**
 * One Divi section wrapping one module, in a row of Divi's own width with zero
 * VERTICAL padding -- see the file docblock for why the row no longer sets a
 * width of its own.
 *
 * @param string $module      The module shortcode, opening tag through closing tag.
 * @param string $admin_label Label shown on the section in the Divi Builder.
 * @param string $section_atts Extra attributes for the section tag, e.g. a background colour.
 * @return string
 */
$honest_seed_section = static function ( $module, $admin_label, $section_atts = '' ) {
	return sprintf(
		'[et_pb_section fb_built="1" admin_label="%1$s" _builder_version="4.27.4" _module_preset="default"%2$s custom_padding="0px||0px||true|false" global_colors_info="{}"]'
		. '[et_pb_row _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" global_colors_info="{}"]'
		. '[et_pb_column type="4_4" _builder_version="4.27.4" _module_preset="default" global_colors_info="{}"]'
		. '%3$s'
		. '[/et_pb_column][/et_pb_row][/et_pb_section]',
		esc_attr( $admin_label ),
		'' === $section_atts ? '' : ' ' . $section_atts,
		$module
	);
};

/*
 * Copy provenance, section by section (Figma file 6LBpKOMFlN8KxaKbut00YW,
 * frame 50:470). "REAL" means the finished words are in the design; "INVENTED"
 * means the design node holds obvious placeholder text (lorem ipsum, or
 * wireframe labels like "About Page Callout") and the words below were written
 * for this build and need replacing:
 *
 *   1. Text Hero            REAL     eyebrow 56:395, headline 56:406, body 56:404
 *   2. Leadership by Market REAL     heading 145:415, intro 223:447
 *   3. Executive Leadership REAL     heading 53:390, intro 223:449
 *   4. Testimonials         n/a      no copy of its own; slides come from member quotes
 *   5. Featured Insights    REAL     heading 50:766, intro 224:2323, button label 54:308
 *   6. Call To Action       INVENTED heading 224:2325 is "About Page Callout",
 *                                    body 55:320 is lorem ipsum, button 55:319
 *                                    is "Learn More" -- all three are wireframe
 *                                    placeholders, so the copy below is new.
 */
$honest_seed_content = implode(
	'',
	array(
		// 1. Hero. The module renders its own purple gradient band, so the
		// section carries no background of its own.
		$honest_seed_section(
			'[honest_text_hero eyebrow="Our Team." headline="Physician-Led Experience. People-Driven Results." _builder_version="4.27.4" _module_preset="default"]'
			. '<p>Our team includes physicians, health system leaders, operators, and industry experts who have firsthand experience navigating today’s evolving health care landscape. We partner with organizations across the country, bringing that perspective to help build the capabilities, relationships, and operational alignment that lead to lasting success.</p>'
			. '[/honest_text_hero]',
			'Hero'
		),

		// 2. Leadership by Market. Tabs, member grid and the Lottie map all
		// come from Teams -> Markets; the module renders nothing at all if
		// that screen is empty.
		$honest_seed_section(
			'[honest_leadership_by_market heading="Leadership by Market" _builder_version="4.27.4" _module_preset="default"]'
			. '<p>Healthcare is local, and every market brings its own opportunities and challenges. Our regional leaders work alongside health systems and providers every day, combining local market insight with the strength of a national organization. They help translate strategy into action, supporting the relationships, alignment, and execution that drive lasting performance.</p>'
			. '[/honest_leadership_by_market]',
			'Leadership by Market'
		),

		// 3. Executive Leadership. `columns="4"` is the module default and is
		// stated explicitly so the 4-up grid in the design is not left
		// depending on a default that could change.
		$honest_seed_section(
			'[honest_executive_leadership heading="Executive Leadership Team" columns="4" _builder_version="4.27.4" _module_preset="default"]'
			. '<p>Our executive leaders bring together decades of experience spanning clinical care, health system operations, payer strategy, technology, and business leadership. Together, they provide the strategic direction, innovation, and leadership that strengthen our partnerships, empower our teams, and help shape the future of value-based care.</p>'
			. '[/honest_executive_leadership]',
			'Executive Leadership'
		),

		// 4. Testimonials. One slide per Executive Team member with a
		// non-empty quote; the module renders its own coloured band, so again
		// no section background.
		$honest_seed_section(
			'[honest_testimonials _builder_version="4.27.4" _module_preset="default"][/honest_testimonials]',
			'Testimonials'
		),

		// 5. Featured Insights. `button_position="below"` is the Our Team
		// page's treatment -- button centred under the grid -- as against the
		// member page's `top`. The section background is the purple band from
		// Figma node 50:812 (#6a4c91): the module's heading/intro default to
		// white FOR this band and it does not render the band itself, so
		// without this attribute the heading and intro would be invisible.
		$honest_seed_section(
			'[honest_featured_insights heading="Featured Insights" source="latest" limit="3" button_text="Explore All Articles" button_url="/industry-news/" button_position="below" _builder_version="4.27.4" _module_preset="default"]'
			. '<p>Our leaders regularly share perspectives on the issues shaping the future of healthcare — from value-based care strategy and policy to operational excellence and innovation. Explore their latest insights and practical guidance for navigating an increasingly complex industry.</p>'
			. '[/honest_featured_insights]',
			'Featured Insights',
			'background_color="#6a4c91" background_enable_image="off"'
		),

		// 6. Closing CTA. All of this copy is invented -- see the provenance
		// note above. No `cta_image` is set: the photo in the design
		// (AdobeStock_603904097) is not in this site's media library, and the
		// module falls back to a solid `cta_bg_color` rather than emitting a
		// broken background-image.
		$honest_seed_section(
			'[honest_call_to_action heading="Partner With Honest Health" button_text="Get in Touch" button_url="/contact-us/" alignment="right" _builder_version="4.27.4" _module_preset="default"]'
			. '<p>Our team works alongside health systems, physician groups, and independent providers across the country to build value-based care programs that last. Tell us about your organization and we can show you what partnership could look like.</p>'
			. '[/honest_call_to_action]',
			'Call To Action'
		),
	)
);

$honest_seed_current  = (string) get_post_field( 'post_content', $honest_seed_page_id, 'raw' );
$honest_seed_last_run = (string) get_post_meta( $honest_seed_page_id, $honest_seed_hash_meta, true );

// "Untouched" means: still byte-for-byte what THIS SCRIPT last wrote. A
// missing hash is not "untouched" -- see the file docblock.
$honest_seed_untouched = '' !== $honest_seed_last_run && md5( $honest_seed_current ) === $honest_seed_last_run;
$honest_seed_wrote     = false;

if ( $honest_seed_current === $honest_seed_content ) {
	// Already exactly the canonical content, so hashing it records a true
	// statement regardless of who typed it. This is the one branch that stamps
	// without writing, and it is safe precisely because there is no editor
	// work to lose: the two are the same bytes.
	WP_CLI::log( 'Page content already matches; left untouched.' );
} elseif ( $honest_seed_untouched || $honest_seed_force ) {
	if ( $honest_seed_force && ! $honest_seed_untouched ) {
		WP_CLI::warning( sprintf( 'force: discarding the current content of page %d and rebuilding it from this script.', $honest_seed_page_id ) );
	}

	$honest_seed_updated = wp_update_post(
		array(
			'ID'           => $honest_seed_page_id,
			'post_content' => $honest_seed_content,
		),
		true
	);

	if ( is_wp_error( $honest_seed_updated ) ) {
		WP_CLI::error( 'Could not update the page: ' . $honest_seed_updated->get_error_message() );
	}

	$honest_seed_wrote = true;
	WP_CLI::log( sprintf( 'Page %d content written (%d bytes).', $honest_seed_page_id, strlen( $honest_seed_content ) ) );
} else {
	// A foreign edit -- including the page's original, hand-built layout on a
	// database this script has never run against. Refuse, loudly, and do NOT
	// stamp the hash: stamping here would make the very next run mistake the
	// edit for this script's own work and silently revert it.
	$honest_seed_stamp_hash = false;

	WP_CLI::warning(
		sprintf(
			"Page %d does not match what this script last wrote -- it has been edited, or this is the first run against a page that already had a layout on it.\n"
			. "Its content is LEFT AS-IS. This script will keep refusing on every run until a human resolves it; it will not overwrite the edit on a later deploy.\n"
			. "  To KEEP the current page:  do nothing. The Teams settings and quotes above were still seeded; only the page content was held back.\n"
			. '  To REPLACE it with the six-section Our Team layout: wp eval-file %s force',
			$honest_seed_page_id,
			'wp-content/plugins/honest-divi-modules/bin/seed-team-content.php'
		)
	);
}

if ( $honest_seed_wrote ) {
	// Divi caches a page's parsed shortcodes and per-module feature flags
	// against its content. Leaving those in place after replacing the content
	// serves the old layout's cached decisions to the new one, so drop them
	// and let Divi rebuild on the next request.
	foreach ( array( '_et_dynamic_cached_shortcodes', '_et_dynamic_cached_attributes', '_et_builder_module_features_cache', '_et_pb_first_image' ) as $honest_seed_stale ) {
		delete_post_meta( $honest_seed_page_id, $honest_seed_stale );
	}

	if ( function_exists( 'et_core_clear_wp_cache' ) ) {
		et_core_clear_wp_cache( $honest_seed_page_id );
		WP_CLI::log( 'Cleared Divi/page caches for this page.' );
	}
}

// Divi will not process the layout's shortcodes unless the page is flagged as
// a builder post. Written only where they differ, so a re-run is a no-op.
$honest_seed_page_meta = array(
	'_et_pb_use_builder'         => 'on',
	'_et_pb_built_for_post_type' => 'page',
);

foreach ( $honest_seed_page_meta as $honest_seed_key => $honest_seed_value ) {
	if ( (string) get_post_meta( $honest_seed_page_id, $honest_seed_key, true ) !== (string) $honest_seed_value ) {
		update_post_meta( $honest_seed_page_id, $honest_seed_key, $honest_seed_value );
		WP_CLI::log( sprintf( '  set %s = %s', $honest_seed_key, $honest_seed_value ) );
	}
}

// Record the hash of the content THIS SCRIPT wrote, so the next run can tell
// "unchanged since I wrote it" from "a human edited this in the Divi Builder".
// Taken by re-reading the stored content rather than hashing
// $honest_seed_content directly, so anything WordPress's save filters do to
// the shortcode on the way in is captured too -- otherwise a filtered save
// would read as a foreign edit on the very next run.
if ( $honest_seed_stamp_hash ) {
	update_post_meta( $honest_seed_page_id, $honest_seed_hash_meta, md5( (string) get_post_field( 'post_content', $honest_seed_page_id, 'raw' ) ) );
}

WP_CLI::success( sprintf( 'Seeded Teams settings, member quotes and the Our Team page (%d).', $honest_seed_page_id ) );
