# Honest Health Team Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Our Team page and individual team member pages for honesthealth.com as reusable Divi modules driven by a dedicated Teams settings screen.

**Architecture:** Seven PHP-only Divi modules ship in the `honest-divi-modules` plugin. They are thin renderers: structured content (who appears where) lives in ACF fields on a Teams admin screen, and per-member data lives on the existing `article-author` post type. Shared markup lives in two partials (member card, article card) used by multiple modules. The single member page is a Divi Theme Builder body template, not a PHP template.

**Tech Stack:** WordPress, Divi 4.27.7 (parent) + HonestMedic child theme, ACF Pro 6.8, PHP 8.2, Lottie (lottie-web) for the market map.

## Global Constraints

- Divi modules MUST use `$vb_support = 'partial'`. `'on'` requires a React component we do not have; without one the Visual Builder stringifies a React factory into the canvas. See `docs/notes` below.
- With `vb_support = 'partial'`, Divi does NOT wrap module output. Each module's `render()` emits its own outer `<div>` using `$this->module_classname( $render_slug )` and `$this->module_id()`.
- Never use `<table>` markup in ACF field instructions. Instructions render inside the field wrapper and ACF counts repeater rows by finding `<tr>` elements there — a table silently trips "Maximum rows reached". Use list markup.
- Market segment order is fixed: **West, Southwest, Midwest, East**. The Figma frame (node 252:1245) lists East third and Midwest fourth; that is wrong and confirmed wrong by the client. The order lives in exactly one place: `honest_team_market_segments()`.
- All new ACF definitions are registered in PHP (`acf_add_local_field_group`), never via the ACF admin UI. The site has no `acf-json` sync, so UI-created definitions live only in the database and do not deploy.
- Post type is `article-author`. Do not create a new Team post type.
- Escape all output. Use `esc_html()`, `esc_url()`, `esc_attr()`; use `et_core_esc_previously()` only for content already sanitised by WordPress.
- Every colour is a `'type' => 'color'` module field with a `default` extracted from that component in Figma. Modules apply colours by writing CSS custom properties inline onto their own wrapper; the stylesheet only ever reads `var(--token, fallback)`. Never hardcode a hex in `modules.css` except as a `var()` fallback.
- No automated test harness exists. Every task ends with explicit verification commands. Run them and read the output before ticking the box.

**Environment (local):**
```bash
export PATH="$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin:$PATH"
cd "/Users/gustavogomez/Local Sites/honesthealth/app/public"
```
Site URL: `http://honesthealth.local` (plain HTTP, no SSL locally).

## Resolved Decisions

1. **Colour is editable, never hardcoded.** Every colour a module renders is exposed as a Divi `color` field with a default taken from the corresponding Figma component. Extract the hex from the component you are building at the time you build it — do not reuse a guess from another module. Known extracted values: map highlight `#9789AD`, map state border `#6A4C91`, unhighlighted states and map labels `#FFFFFF`. Figma variables also define HH Blue `#6985c3`, HH Green `#37a38f`, Blue shade `#3a61b6`, Dark Grey `#6a8090`, black `#070707`, font Outfit.
2. **Mobile and tablet are our responsibility, best-effort.** No designs exist; Figma is desktop-only at 1440. Use sensible breakpoints, do not block on design review.
3. **Testimonial ordering does not matter.** Task 11 may use any stable order.

## Already Complete

Do not redo these.

- Plugin scaffold, loader, asset enqueue, admin notice — `honest-divi-modules.php`
- Reference module proving the pipeline — `includes/modules/TestBlock/TestBlock.php` (removed in Task 18)
- Teams settings screens — `includes/admin/team-settings.php`, providing:
  - `honest_team_member_post_type(): string` → `'article-author'`
  - `honest_team_market_segments(): string[]` → `['West','Southwest','Midwest','East']`
  - `honest_team_markets_instructions(): string`
  - `honest_team_get_executive_members(): int[]`
  - `honest_team_get_markets(): array[]` — each `{ name, caption, members:int[], segment:int, segment_name:string }`

---

## File Structure

**Create:**
- `includes/class-honest-divi-module-base.php` — shared module base class
- `includes/data/team-data.php` — member + article data access
- `includes/partials/member-card.php` — member card markup
- `includes/partials/article-card.php` — article card markup
- `includes/admin/member-fields.php` — per-member ACF fields (quote, LinkedIn)
- `includes/admin/slug-migration.php` — `/team/` rewrite + 301 redirects
- `assets/js/market-map.js` — Lottie segment playback
- `assets/lottie/market-map.json` + `market-map-segments.json` — produced separately
- `includes/modules/{TextHero,ExecutiveLeadership,LeadershipByMarket,Testimonials,FeaturedInsights,CallToAction,TeamMemberHeader}/*.php`

**Modify:**
- `honest-divi-modules.php` — requires, module map, conditional JS enqueue
- `assets/css/modules.css` — tokens + all module styles

---

### Task 1: Module base class

Removes the boilerplate every module would otherwise repeat (toggles, advanced fields, wrapper markup).

**Files:**
- Create: `includes/class-honest-divi-module-base.php`
- Modify: `honest-divi-modules.php`

**Interfaces:**
- Produces: `abstract class Honest_Divi_Module_Base extends ET_Builder_Module` with `protected function wrap( $render_slug, $inner, $extra_classes = array() ): string` and `protected function base_advanced_fields( $selectors = array() ): array`.

- [ ] **Step 1: Create the base class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Honest_Divi_Module_Base extends ET_Builder_Module {

	public $vb_support = 'partial';

	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'Honest Health',
		'author_uri' => '',
	);

	/**
	 * Emit the outer module wrapper. Required because vb_support='partial'
	 * means Divi does not wrap third-party module output.
	 */
	protected function wrap( $render_slug, $inner, $extra_classes = array() ) {
		foreach ( (array) $extra_classes as $class ) {
			if ( '' !== $class ) {
				$this->add_classname( $class );
			}
		}

		return sprintf(
			'<div%2$s class="%1$s">%3$s</div>',
			$this->module_classname( $render_slug ),
			$this->module_id(),
			$inner
		);
	}

	/**
	 * Standard design-tab options every module gets.
	 *
	 * @param array $selectors Optional font groups keyed by slug.
	 */
	protected function base_advanced_fields( $selectors = array() ) {
		return array_merge(
			array(
				'fonts'          => $selectors,
				'background'     => array(),
				'margin_padding' => array(),
				'borders'        => array( 'default' => array() ),
				'box_shadow'     => array( 'default' => array() ),
				'button'         => false,
			)
		);
	}
}
```

- [ ] **Step 2: Require it before modules load**

In `honest-divi-modules.php`, inside `honest_divi_modules_register()`, immediately after the `class_exists( 'ET_Builder_Module' )` guard:

```php
require_once HONEST_DIVI_MODULES_DIR . 'includes/class-honest-divi-module-base.php';
```

- [ ] **Step 3: Verify**

```bash
php -l wp-content/plugins/honest-divi-modules/includes/class-honest-divi-module-base.php
wp eval 'do_action("et_builder_ready"); var_dump( class_exists("Honest_Divi_Module_Base") );'
```
Expected: no syntax errors, `bool(true)`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/honest-divi-modules
git commit -m "feat(modules): add shared Divi module base class"
```

---

### Task 2: Team data helpers

Single source of truth for reading member and article data, so no module queries directly.

**Files:**
- Create: `includes/data/team-data.php`
- Modify: `honest-divi-modules.php`

**Interfaces:**
- Consumes: `honest_team_get_executive_members()`, `honest_team_get_markets()` (Task 0, already built).
- Produces:
  - `honest_team_get_member( int $post_id ): array|null` → `{ id, name, job_title, bio, quote, linkedin, image_id, permalink }`
  - `honest_team_get_members( int[] $ids ): array[]` — preserves the given order, skips missing/unpublished
  - `honest_team_get_articles_by_member( int $member_id, int $limit = 8 ): WP_Post[]`
  - `honest_team_get_article_authors( int $post_id ): array[]` — the `honest_team_get_member()` shape, for card bylines

- [ ] **Step 1: Create the data layer**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalised member data. Returns null if the post is missing or not published.
 */
function honest_team_get_member( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );

	if ( ! $post || honest_team_member_post_type() !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	return array(
		'id'        => $post_id,
		'name'      => get_the_title( $post_id ),
		'job_title' => (string) get_post_meta( $post_id, 'job_title_short', true ),
		'bio'       => (string) get_post_meta( $post_id, 'bio', true ),
		'quote'     => (string) get_post_meta( $post_id, 'quote', true ),
		'linkedin'  => (string) get_post_meta( $post_id, 'linkedin_url', true ),
		'image_id'  => (int) get_post_meta( $post_id, 'author_image', true ),
		'permalink' => (string) get_permalink( $post_id ),
	);
}

/**
 * Members for a list of IDs, preserving the given order.
 *
 * @param int[] $ids
 * @return array[]
 */
function honest_team_get_members( $ids ) {
	$members = array();

	foreach ( (array) $ids as $id ) {
		$member = honest_team_get_member( $id );

		if ( $member ) {
			$members[] = $member;
		}
	}

	return $members;
}

/**
 * Posts this member is credited on, via the existing `article_authors`
 * relationship field stored on the post.
 *
 * @return WP_Post[]
 */
function honest_team_get_articles_by_member( $member_id, $limit = 8 ) {
	$member_id = (int) $member_id;

	if ( ! $member_id ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'meta_query'     => array(
				array(
					'key'     => 'article_authors',
					'value'   => '"' . $member_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);
}

/**
 * Credited members for a post, for card bylines.
 *
 * @return array[]
 */
function honest_team_get_article_authors( $post_id ) {
	$ids = get_post_meta( (int) $post_id, 'article_authors', true );

	return honest_team_get_members( (array) maybe_unserialize( $ids ) );
}
```

- [ ] **Step 2: Require it at plugin load**

In `honest-divi-modules.php`, directly after the existing `team-settings.php` require:

```php
require_once HONEST_DIVI_MODULES_DIR . 'includes/data/team-data.php';
```

- [ ] **Step 3: Verify against real data**

Write `/tmp/t2.php`:
```php
<?php
$m = honest_team_get_member( 102433 ); // Adam Silverman
echo 'member: ' . wp_json_encode( $m ) . "\n";
echo 'articles: ' . count( honest_team_get_articles_by_member( 102433 ) ) . "\n";
$posts = honest_team_get_articles_by_member( 102433, 1 );
if ( $posts ) {
	echo 'authors on first article: ' . wp_json_encode( wp_list_pluck( honest_team_get_article_authors( $posts[0]->ID ), 'name' ) ) . "\n";
}
echo 'order preserved: ' . wp_json_encode( wp_list_pluck( honest_team_get_members( array( 102437, 102433 ) ), 'id' ) ) . "\n";
```
```bash
wp eval-file /tmp/t2.php
```
Expected: member object with a non-empty `name`; `articles: 3`; author names listed; `order preserved: [102437,102433]` in that exact order.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(data): add team member and article data helpers"
```

---

### Task 3: Design tokens and member card partial

**Files:**
- Create: `includes/partials/member-card.php`
- Modify: `assets/css/modules.css`, `honest-divi-modules.php`

**Interfaces:**
- Consumes: `honest_team_get_member()` shape from Task 2.
- Produces: `honest_team_render_member_card( array $member ): string`

- [ ] **Step 1: Create the partial**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member card used by the Executive Leadership and Leadership by Market modules.
 *
 * @param array $member Shape returned by honest_team_get_member().
 */
function honest_team_render_member_card( $member ) {
	if ( empty( $member['id'] ) ) {
		return '';
	}

	$image = $member['image_id']
		? wp_get_attachment_image( $member['image_id'], 'medium', false, array( 'class' => 'honest-member-card__image', 'loading' => 'lazy' ) )
		: '';

	return sprintf(
		'<a class="honest-member-card" href="%1$s">
			<span class="honest-member-card__media">%2$s</span>
			<span class="honest-member-card__body">
				<span class="honest-member-card__text">
					<span class="honest-member-card__name">%3$s</span>
					<span class="honest-member-card__title">%4$s</span>
				</span>
				<span class="honest-member-card__arrow" aria-hidden="true"></span>
			</span>
		</a>',
		esc_url( $member['permalink'] ),
		$image,
		esc_html( $member['name'] ),
		esc_html( $member['job_title'] )
	);
}
```

- [ ] **Step 2: Add tokens and card styles**

Prepend to `assets/css/modules.css`. These are only fallbacks — modules override them per instance by writing the custom properties inline on their wrapper from `color` fields (see Global Constraints). Extract the real card purples from the member-card component in Figma and use them as the field defaults in Task 9.

```css
:root {
	--hh-purple: #6b4e9e;
	--hh-purple-soft: #d5d0ea;
	--hh-purple-deep: #55407e;
	--hh-blue: #6985c3;
	--hh-green: #37a38f;
	--hh-grey: #6a8090;
	--hh-black: #070707;
	--hh-font: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.honest-member-card {
	display: block;
	background: var(--hh-purple-soft);
	border-radius: 12px;
	padding: 20px;
	text-decoration: none;
	transition: background-color 0.2s ease, color 0.2s ease;
}
.honest-member-card__media { display: block; }
.honest-member-card__image { display: block; width: 100%; height: auto; border-radius: 50%; }
.honest-member-card__body { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; }
.honest-member-card__name { display: block; font-weight: 700; color: var(--hh-purple); }
.honest-member-card__title { display: block; font-weight: 600; color: var(--hh-black); }
.honest-member-card:hover { background: var(--hh-purple); }
.honest-member-card:hover .honest-member-card__name,
.honest-member-card:hover .honest-member-card__title { color: #fff; }
.honest-member-card:focus-visible { outline: 3px solid var(--hh-blue); outline-offset: 2px; }
```

- [ ] **Step 3: Require the partial**

Add to `honest-divi-modules.php` after the data require:
```php
require_once HONEST_DIVI_MODULES_DIR . 'includes/partials/member-card.php';
```

- [ ] **Step 4: Verify markup**

```bash
wp eval 'echo honest_team_render_member_card( honest_team_get_member( 102433 ) );'
```
Expected: a single `<a class="honest-member-card" href="http://honesthealth.local/...">` containing name and job title, no PHP notices.

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(partials): add design tokens and member card"
```

---

### Task 4: Article card partial

**Files:**
- Create: `includes/partials/article-card.php`
- Modify: `assets/css/modules.css`, `honest-divi-modules.php`

**Interfaces:**
- Consumes: `honest_team_get_article_authors()` from Task 2.
- Produces: `honest_team_render_article_card( WP_Post $post ): string`

- [ ] **Step 1: Create the partial**

Uses the existing `card_title` / `card_description` post meta, falling back to title and excerpt.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Article card used by Featured Insights and the member template.
 */
function honest_team_render_article_card( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$card_title = (string) get_post_meta( $post->ID, 'card_title', true );
	$card_desc  = (string) get_post_meta( $post->ID, 'card_description', true );
	$title      = '' !== $card_title ? $card_title : get_the_title( $post );
	$desc       = '' !== $card_desc ? $card_desc : get_the_excerpt( $post );

	$terms = get_the_terms( $post->ID, 'category' );
	$flag  = ( $terms && ! is_wp_error( $terms ) )
		? sprintf( '<span class="honest-article-card__flag">%s</span>', esc_html( $terms[0]->name ) )
		: '';

	$image = has_post_thumbnail( $post )
		? get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'honest-article-card__image', 'loading' => 'lazy' ) )
		: '';

	$byline = '';
	foreach ( honest_team_get_article_authors( $post->ID ) as $author ) {
		$byline .= sprintf(
			'<span class="honest-article-card__author"><span class="honest-article-card__author-name">%1$s</span><span class="honest-article-card__author-title">%2$s</span></span>',
			esc_html( $author['name'] ),
			esc_html( $author['job_title'] )
		);
	}

	return sprintf(
		'<article class="honest-article-card">
			<div class="honest-article-card__media">%1$s%2$s</div>
			<h3 class="honest-article-card__title">%3$s</h3>
			<div class="honest-article-card__byline">%4$s</div>
			<p class="honest-article-card__desc">%5$s</p>
			<a class="honest-article-card__link" href="%6$s">%7$s</a>
		</article>',
		$image,
		$flag,
		esc_html( $title ),
		$byline,
		esc_html( $desc ),
		esc_url( get_permalink( $post ) ),
		esc_html__( 'Read More', 'honest-divi-modules' )
	);
}
```

- [ ] **Step 2: Add card styles**

```css
.honest-article-card { background: #fff; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; }
.honest-article-card__media { position: relative; }
.honest-article-card__image { display: block; width: 100%; height: auto; }
.honest-article-card__flag { position: absolute; top: 0; left: 0; background: var(--hh-blue); color: #fff; font-style: italic; font-weight: 700; padding: 8px 24px 8px 20px; text-transform: uppercase; }
.honest-article-card__title { margin: 24px 20px 0; }
.honest-article-card__byline { margin: 20px; }
.honest-article-card__author { display: block; margin-bottom: 8px; }
.honest-article-card__author-name { display: block; font-weight: 700; color: var(--hh-blue); }
.honest-article-card__author-title { display: block; color: var(--hh-blue); font-size: 0.9em; }
.honest-article-card__desc { margin: 0 20px 20px; }
.honest-article-card__link { display: inline-block; margin: auto 20px 20px; background: var(--hh-black); color: #fff; padding: 12px 24px; text-decoration: none; font-weight: 700; }
```

- [ ] **Step 3: Require and verify**

Add the require, then:
```bash
wp eval '$p = honest_team_get_articles_by_member( 102433, 1 ); echo honest_team_render_article_card( $p[0] );'
```
Expected: an `<article>` with title, at least one author name, description and a Read More link. Confirm the byline repeats for a multi-author post.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(partials): add article card"
```

---

### Task 5: Per-member ACF fields

Adds `quote` and `linkedin_url` as a code-registered field group, so they deploy with the plugin. Does NOT modify the existing database-managed Article Authors group.

**Files:**
- Create: `includes/admin/member-fields.php`
- Modify: `honest-divi-modules.php`

- [ ] **Step 1: Register the group**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function honest_team_register_member_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_honest_member_details',
			'title'    => __( 'Team Member Details', 'honest-divi-modules' ),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => honest_team_member_post_type(),
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_honest_member_quote',
					'label'        => __( 'Pull Quote', 'honest-divi-modules' ),
					'name'         => 'quote',
					'type'         => 'textarea',
					'instructions' => __( 'Shown on this member\'s page and eligible for the testimonial carousel. Leave blank to exclude from the carousel.', 'honest-divi-modules' ),
					'rows'         => 4,
					'new_lines'    => '',
				),
				array(
					'key'          => 'field_honest_member_linkedin',
					'label'        => __( 'LinkedIn URL', 'honest-divi-modules' ),
					'name'         => 'linkedin_url',
					'type'         => 'url',
					'instructions' => __( 'Full profile URL. Leave blank to hide the link.', 'honest-divi-modules' ),
				),
			),
		)
	);
}
add_action( 'acf/init', 'honest_team_register_member_fields' );
```

- [ ] **Step 2: Require and verify**

```bash
wp eval '$g = acf_get_field_group("group_honest_member_details"); echo $g ? "found: " . $g["title"] . "\n" : "MISSING\n"; foreach ( acf_get_fields($g) as $f ) { echo "  {$f["name"]} ({$f["type"]})\n"; }'
```
Expected: `found: Team Member Details`, then `quote (textarea)` and `linkedin_url (url)`.

- [ ] **Step 3: Confirm in the admin**

Open `http://honesthealth.local/wp-admin/post.php?post=102433&action=edit`. Confirm a "Team Member Details" box with both fields. Save a test quote, then:
```bash
wp eval 'echo wp_json_encode( honest_team_get_member(102433)["quote"] );'
```
Expected: the saved text. This proves Task 2's reader matches the field name.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(acf): add member quote and LinkedIn fields"
```

---

### Task 6: Slug migration to /team/

**Files:**
- Create: `includes/admin/slug-migration.php`
- Modify: `honest-divi-modules.php`

Changing the ACF post type slug is done in the ACF UI (Post Types → Article Authors → URL slug → `team`) because the post type is ACF-UI-managed; the redirect below is what ships in code.

- [ ] **Step 1: Add the redirect**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 301 old /article-author/{slug}/ URLs to /team/{slug}/.
 *
 * These URLs are in the Yoast sitemap and are indexable, so they must not 404
 * after the rewrite slug changes.
 */
function honest_team_redirect_legacy_urls() {
	if ( is_admin() ) {
		return;
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

	if ( ! $path || 0 !== strpos( $path, '/article-author/' ) ) {
		return;
	}

	$target = home_url( str_replace( '/article-author/', '/team/', $path ) );

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'honest_team_redirect_legacy_urls', 1 );
```

- [ ] **Step 2: Change the slug and flush**

In the ACF UI set the Article Authors URL slug to `team`, then:
```bash
wp rewrite flush
```

- [ ] **Step 3: Verify both URLs**

```bash
wp eval 'echo get_permalink(102433) . "\n";'
curl -s -o /dev/null -w "new: %{http_code}\n" http://honesthealth.local/team/adam-silverman-md/
curl -s -o /dev/null -w "old: %{http_code} -> %{redirect_url}\n" http://honesthealth.local/article-author/adam-silverman-md/
```
Expected: permalink contains `/team/`; new URL `200`; old URL `301` redirecting to the `/team/` equivalent.

- [ ] **Step 4: Check the Our Team page slug does not collide**

If a page exists at `/team/`, confirm both still resolve:
```bash
curl -s -o /dev/null -w "page: %{http_code}\n" http://honesthealth.local/team/
```
Expected: `200` for both the page and member URLs. If the page 404s, the page slug must change or `has_archive` must stay `false`.

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(urls): migrate member URLs to /team/ with 301s"
```

---

### Task 7: Lottie playback

The Lottie file and its segment manifest are produced separately. This task wires them up.

**Files:**
- Create: `assets/js/market-map.js`
- Modify: `honest-divi-modules.php`

**Interfaces:**
- Consumes: `assets/lottie/market-map.json`, `assets/lottie/market-map-segments.json` (array of `{ name, in, out }` in West/Southwest/Midwest/East order).
- Produces: a global `HonestMarketMap` with `init(container)` and `showSegment(index)`.

- [ ] **Step 1: Confirm the manifest still matches these values**

```bash
cat wp-content/plugins/honest-divi-modules/assets/lottie/market-map-segments.json
```

The Lottie is already built and verified. Composition is 1319 × 814, 30 fps, frames 0–316, 40 layers, no external assets. Segments:

| index | name | in | out | label frame | states |
|---|---|---|---|---|---|
| 1 | West | 0 | 82 | 62 | 11 |
| 2 | Southwest | 88 | 142 | 122 | 4 |
| 3 | Midwest | 148 | 234 | 214 | 12 |
| 4 | East | 240 | 310 | 290 | 8 |

Each segment is followed by a 6-frame guard band that is never played, so one segment's coloured layers are fully retired before the next segment's first frame — this is what makes reversal clean. `emptyFrame` equals `in` for every segment, and the empty outlined map is the same base layer throughout. Segment lengths differ (82 / 54 / 86 / 70) because state counts differ; if uniform transition timing is wanted, drive playback speed per segment rather than assuming equal lengths.

Note the manifest's `index` is 1-based while the repeater row is 0-based — map with `index - 1`, or read the array position.

- [ ] **Step 2: Write the player**

```js
( function () {
	'use strict';

	var anim = null;
	var segments = [];
	var current = -1;

	function play( index, reverse ) {
		var seg = segments[ index ];
		if ( ! anim || ! seg ) { return; }
		anim.setDirection( reverse ? -1 : 1 );
		anim.playSegments( reverse ? [ seg.out, seg.in ] : [ seg.in, seg.out ], true );
	}

	function showSegment( index ) {
		if ( index === current ) { return; }
		if ( current === -1 ) {
			current = index;
			play( index, false );
			return;
		}
		var previous = current;
		current = index;
		play( previous, true );
		anim.addEventListener( 'complete', function handler() {
			anim.removeEventListener( 'complete', handler );
			play( index, false );
		} );
	}

	function init( container ) {
		if ( ! window.lottie || ! container ) { return; }
		segments = JSON.parse( container.getAttribute( 'data-segments' ) || '[]' );
		anim = window.lottie.loadAnimation( {
			container: container,
			renderer: 'svg',
			loop: false,
			autoplay: false,
			path: container.getAttribute( 'data-lottie' )
		} );
		anim.addEventListener( 'DOMLoaded', function () { showSegment( 0 ); } );
	}

	window.HonestMarketMap = { init: init, showSegment: showSegment };

	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.querySelector( '.honest-market-map' );
		if ( el ) { init( el ); }
	} );
}() );
```

- [ ] **Step 3: Enqueue conditionally**

In `honest_divi_modules_assets()`:
```php
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
```
The Leadership by Market module calls `wp_enqueue_script( 'honest-market-map' )` in its `render()` so the library only loads where used.

> Decide with the client whether to self-host lottie-web rather than use a CDN. If self-hosting, drop the file in `assets/js/vendor/` and change the registered source.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(map): add Lottie segment playback"
```

---

### Task 8: Text Hero module

**Files:**
- Create: `includes/modules/TextHero/TextHero.php`
- Modify: `honest-divi-modules.php` (add `'TextHero' => 'Honest_Divi_Module_Text_Hero'` to `honest_divi_modules_map()`), `assets/css/modules.css`

- [ ] **Step 1: Create the module**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Text_Hero extends Honest_Divi_Module_Base {

	public $slug = 'honest_text_hero';

	public function init() {
		$this->name             = esc_html__( 'Text Hero', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array( 'toggles' => array( 'eyebrow' => esc_html__( 'Eyebrow', 'honest-divi-modules' ), 'headline' => esc_html__( 'Headline', 'honest-divi-modules' ), 'body' => esc_html__( 'Body', 'honest-divi-modules' ) ) ),
		);

		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'eyebrow'  => array( 'label' => esc_html__( 'Eyebrow', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__eyebrow" ), 'toggle_slug' => 'eyebrow' ),
				'headline' => array( 'label' => esc_html__( 'Headline', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__headline" ), 'toggle_slug' => 'headline' ),
				'body'     => array( 'label' => esc_html__( 'Body', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__body" ), 'toggle_slug' => 'body' ),
			)
		);
	}

	public function get_fields() {
		return array(
			'eyebrow'  => array(
				'label'           => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Large display text, e.g. "Our Team."', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'headline' => array(
				'label'           => esc_html__( 'Headline', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'  => array(
				'label'           => esc_html__( 'Body', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$parts = '';

		if ( '' !== $this->props['eyebrow'] ) {
			$parts .= sprintf( '<p class="honest-text-hero__eyebrow"><span>%s</span></p>', esc_html( $this->props['eyebrow'] ) );
		}
		if ( '' !== $this->props['headline'] ) {
			$parts .= sprintf( '<h1 class="honest-text-hero__headline">%s</h1>', esc_html( $this->props['headline'] ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$parts .= sprintf( '<div class="honest-text-hero__body">%s</div>', et_core_esc_previously( $this->content ) );
		}

		return $this->wrap( $render_slug, sprintf( '<div class="honest-text-hero__inner">%s</div>', $parts ), array( 'honest-text-hero' ) );
	}
}
```

- [ ] **Step 2: Style it**

```css
.honest-text-hero { background: linear-gradient(90deg, var(--hh-purple-deep), var(--hh-purple)); color: #fff; padding: 80px 0 140px; position: relative; }
.honest-text-hero::after { content: ""; position: absolute; left: -5%; right: -5%; bottom: -80px; height: 160px; background: #fff; border-radius: 50%; }
.honest-text-hero__inner { max-width: 1030px; margin: 0 auto; padding: 0 20px; text-align: center; position: relative; z-index: 1; }
.honest-text-hero__eyebrow { font-size: 48px; font-weight: 700; margin: 0 0 16px; }
.honest-text-hero__headline { color: #fff; margin: 0 0 24px; }
```
The curved bottom edge is an approximation of the Figma shape and needs a visual check against the design.

- [ ] **Step 3: Verify**

```bash
php -l wp-content/plugins/honest-divi-modules/includes/modules/TextHero/TextHero.php
wp eval 'do_action("et_builder_ready"); var_dump( shortcode_exists("honest_text_hero") );'
```
Then add the module to the smoke-test page and confirm it renders over HTTP.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(modules): add Text Hero"
```

---

### Task 9: Executive Leadership module

**Files:**
- Create: `includes/modules/ExecutiveLeadership/ExecutiveLeadership.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`

**Interfaces:**
- Consumes: `honest_team_get_executive_members()`, `honest_team_get_members()`, `honest_team_render_member_card()`.

- [ ] **Step 1: Create the module**

Fields: `heading` (text), `content` (tiny_mce intro), `columns` (select `2|3|4`, default `4`). Slug `honest_executive_leadership`.

```php
	public function render( $attrs, $content, $render_slug ) {
		$members = honest_team_get_members( honest_team_get_executive_members() );

		if ( empty( $members ) ) {
			return '';
		}

		$cards = '';
		foreach ( $members as $member ) {
			$cards .= honest_team_render_member_card( $member );
		}

		$header = '';
		if ( '' !== $this->props['heading'] ) {
			$header .= sprintf( '<h2 class="honest-exec__heading">%s</h2>', esc_html( $this->props['heading'] ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$header .= sprintf( '<div class="honest-exec__intro">%s</div>', et_core_esc_previously( $this->content ) );
		}

		$inner = sprintf(
			'<div class="honest-exec__inner">%1$s<div class="honest-exec__grid honest-exec__grid--%2$s">%3$s</div></div>',
			$header,
			esc_attr( $this->props['columns'] ),
			$cards
		);

		return $this->wrap( $render_slug, $inner, array( 'honest-exec' ) );
	}
```

Returning `''` when no members are selected is deliberate — an empty section should not render an empty grid.

- [ ] **Step 2: Style the grid**

```css
.honest-exec__inner { max-width: 1245px; margin: 0 auto; padding: 0 20px; }
.honest-exec__grid { display: grid; gap: 30px; }
.honest-exec__grid--4 { grid-template-columns: repeat(4, 1fr); }
.honest-exec__grid--3 { grid-template-columns: repeat(3, 1fr); }
.honest-exec__grid--2 { grid-template-columns: repeat(2, 1fr); }
@media (max-width: 1024px) { .honest-exec__grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .honest-exec__grid { grid-template-columns: 1fr; } }
```

- [ ] **Step 3: Verify with real data**

Select 3 members on Teams → Executive Team, save, then render the module on the smoke-test page and confirm all three appear **in the order set in the relationship field**. Reorder them, save, reload, confirm the order changed.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(modules): add Executive Leadership"
```

---

### Task 10: Leadership by Market module

The largest module. Tabs, filtered grids, Lottie map and caption, all driven by the Markets screen.

**Files:**
- Create: `includes/modules/LeadershipByMarket/LeadershipByMarket.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`, `assets/js/market-map.js`

**Interfaces:**
- Consumes: `honest_team_get_markets()`, `honest_team_render_member_card()`, `HonestMarketMap.showSegment()`.

- [ ] **Step 1: Render tabs and panels**

Slug `honest_leadership_by_market`. Fields: `heading` (text), `content` (tiny_mce intro).

```php
	public function render( $attrs, $content, $render_slug ) {
		$markets = honest_team_get_markets();

		if ( empty( $markets ) ) {
			return '';
		}

		wp_enqueue_script( 'honest-market-map' );

		$tabs   = '';
		$panels = '';
		$segs   = array();

		foreach ( $markets as $i => $market ) {
			$selected = 0 === $i;
			$segs[]   = array( 'name' => $market['segment_name'], 'index' => $market['segment'] );

			$tabs .= sprintf(
				'<button type="button" class="honest-market__tab" role="tab" id="honest-market-tab-%1$d" aria-controls="honest-market-panel-%1$d" aria-selected="%2$s" tabindex="%3$s" data-segment="%4$d">%5$s</button>',
				$i,
				$selected ? 'true' : 'false',
				$selected ? '0' : '-1',
				(int) $market['segment'],
				esc_html( $market['name'] )
			);

			$cards = '';
			foreach ( honest_team_get_members( $market['members'] ) as $member ) {
				$cards .= honest_team_render_member_card( $member );
			}

			$panels .= sprintf(
				'<div class="honest-market__panel" role="tabpanel" id="honest-market-panel-%1$d" aria-labelledby="honest-market-tab-%1$d"%2$s><div class="honest-market__grid">%3$s</div></div>',
				$i,
				$selected ? '' : ' hidden',
				$cards
			);
		}

		$captions = '';
		foreach ( $markets as $i => $market ) {
			$captions .= sprintf(
				'<p class="honest-market__caption"%1$s data-index="%2$d">%3$s</p>',
				0 === $i ? '' : ' hidden',
				$i,
				esc_html( $market['caption'] )
			);
		}

		$map = sprintf(
			'<div class="honest-market__map"><div class="honest-market-map" data-lottie="%1$s" data-segments="%2$s" role="img" aria-label="%3$s"></div>%4$s</div>',
			esc_url( HONEST_DIVI_MODULES_URL . 'assets/lottie/market-map.json' ),
			esc_attr( wp_json_encode( honest_team_map_segment_ranges() ) ),
			esc_attr__( 'Map of the United States highlighting the selected market', 'honest-divi-modules' ),
			$captions
		);

		$inner = sprintf(
			'<div class="honest-market__inner">
				<div class="honest-market__head"><h2>%1$s</h2><div class="honest-market__intro">%2$s</div></div>
				<div class="honest-market__tabs" role="tablist" aria-label="%3$s">%4$s</div>
				<div class="honest-market__body"><div class="honest-market__panels">%5$s</div>%6$s</div>
			</div>',
			esc_html( $this->props['heading'] ),
			et_core_esc_previously( $this->content ),
			esc_attr__( 'Markets', 'honest-divi-modules' ),
			$tabs,
			$panels,
			$map
		);

		return $this->wrap( $render_slug, $inner, array( 'honest-market' ) );
	}
```

- [ ] **Step 2: Add the segment-range helper**

Append to `includes/data/team-data.php`:
```php
/**
 * Segment frame ranges from the Lottie manifest, cached per request.
 *
 * Returns only the `segments` array, in repeater-row order. The manifest's own
 * `index` is 1-based; array position is 0-based and is what the module uses.
 *
 * @return array[] Each: { index, name, slug, in, out, emptyFrame, frames, states, labelFrame }
 */
function honest_team_map_segment_ranges() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$path = HONEST_DIVI_MODULES_DIR . 'assets/lottie/market-map-segments.json';

	if ( ! file_exists( $path ) ) {
		$cache = array();
		return $cache;
	}

	$data  = json_decode( (string) file_get_contents( $path ), true );
	$cache = isset( $data['segments'] ) && is_array( $data['segments'] ) ? $data['segments'] : array();

	return $cache;
}
```

- [ ] **Step 3: Add tab switching JS**

Append to `assets/js/market-map.js`:
```js
document.addEventListener( 'DOMContentLoaded', function () {
	var root = document.querySelector( '.honest-market' );
	if ( ! root ) { return; }

	var tabs = [].slice.call( root.querySelectorAll( '.honest-market__tab' ) );

	function select( index ) {
		tabs.forEach( function ( tab, i ) {
			var on = i === index;
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', on ? '0' : '-1' );
			var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
			if ( panel ) { panel.hidden = ! on; }
		} );
		[].slice.call( root.querySelectorAll( '.honest-market__caption' ) ).forEach( function ( cap, i ) {
			cap.hidden = i !== index;
		} );
		if ( window.HonestMarketMap ) { window.HonestMarketMap.showSegment( index ); }
	}

	tabs.forEach( function ( tab, i ) {
		tab.addEventListener( 'click', function () { select( i ); } );
		tab.addEventListener( 'keydown', function ( e ) {
			var next = e.key === 'ArrowRight' ? i + 1 : ( e.key === 'ArrowLeft' ? i - 1 : null );
			if ( next === null ) { return; }
			e.preventDefault();
			next = ( next + tabs.length ) % tabs.length;
			tabs[ next ].focus();
			select( next );
		} );
	} );
} );
```

- [ ] **Step 4: Verify**

Populate all four markets with members and captions, then on the page confirm: four tabs render in repeater order; clicking each swaps the member grid and caption; the map animates out then into the correct region; arrow keys move between tabs; `aria-selected` follows the active tab.

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(modules): add Leadership by Market with Lottie map"
```

---

### Task 11: Testimonials module

**Files:**
- Create: `includes/modules/Testimonials/Testimonials.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`, `assets/js/testimonials.js`

Per Open Assumption 3, the source is every executive member with a non-empty `quote`, in relationship order.

- [ ] **Step 1: Build the slide source**

```php
	protected function get_slides() {
		$slides = array();

		foreach ( honest_team_get_members( honest_team_get_executive_members() ) as $member ) {
			if ( '' === trim( $member['quote'] ) ) {
				continue;
			}
			$slides[] = $member;
		}

		return $slides;
	}
```

- [ ] **Step 2: Render slides plus dot controls**

Each slide: `<blockquote>` with the quote and a `<cite>` of `name`, `job_title`. Dots are `<button>` elements with `aria-label` "Show quote N of M" and `aria-current` on the active one. The region wrapper takes `aria-roledescription="carousel"` and a visible pause control if autoplay is enabled.

- [ ] **Step 3: Verify**

Add quotes to two members, confirm two slides and two dots, and that a member without a quote is excluded. Confirm dots are reachable and operable by keyboard.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(modules): add Testimonials carousel"
```

---

### Task 12: Featured Insights module

**Files:**
- Create: `includes/modules/FeaturedInsights/FeaturedInsights.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`

- [ ] **Step 1: Implement three source modes**

Fields: `heading`, `content`, `source` (select: `latest` | `manual` | `current_member`, default `latest`), `manual_ids` (text, comma-separated post IDs, shown when `source` is `manual`), `limit` (range, default 3), `button_text`, `button_url`.

```php
	protected function get_posts_for_source() {
		$limit = max( 1, (int) $this->props['limit'] );

		switch ( $this->props['source'] ) {
			case 'current_member':
				return honest_team_get_articles_by_member( get_the_ID(), $limit );

			case 'manual':
				$ids = array_filter( array_map( 'intval', explode( ',', (string) $this->props['manual_ids'] ) ) );
				return $ids ? get_posts( array( 'post_type' => 'post', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => $limit ) ) : array();

			default:
				return get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $limit ) );
		}
	}
```

`current_member` is what powers "Articles by [First Name]" on the member template — the heading there should use dynamic content or be typed per template.

- [ ] **Step 2: Verify each mode**

Render with `latest` (3 recent posts), `manual` (specific IDs in the given order), and `current_member` on a member page (only that member's articles). Confirm a member with no articles renders nothing rather than an empty grid.

- [ ] **Step 3: Commit**

```bash
git commit -am "feat(modules): add Featured Insights"
```

---

### Task 13: CTA module

**Files:**
- Create: `includes/modules/CallToAction/CallToAction.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`

Fields: `heading`, `content`, `button_text`, `button_url`, `background_image` (upload), `alignment` (select left|right, default right).

- [ ] **Step 1: Implement and style**

Full-bleed background image with an overlay; content constrained and aligned per the setting. Use `wp_get_attachment_image_url()` for the background and set it via an inline `style` attribute on the wrapper.

- [ ] **Step 2: Verify**

Confirm it renders identically on the Our Team page and the member template, and that omitting the image leaves a solid background rather than a broken URL.

- [ ] **Step 3: Commit**

```bash
git commit -am "feat(modules): add CTA"
```

---

### Task 14: Team Member Header module

**Files:**
- Create: `includes/modules/TeamMemberHeader/TeamMemberHeader.php`
- Modify: `honest-divi-modules.php`, `assets/css/modules.css`

Reads the current post's fields directly. This exists because Divi's dynamic content returns raw meta, so an ACF image field would output an attachment ID rather than the portrait.

- [ ] **Step 1: Implement**

```php
	public function render( $attrs, $content, $render_slug ) {
		$member = honest_team_get_member( get_the_ID() );

		if ( ! $member ) {
			return '';
		}

		$linkedin = '' !== $member['linkedin']
			? sprintf(
				'<a class="honest-member__linkedin" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $member['linkedin'] ),
				esc_html__( 'View LinkedIn Profile', 'honest-divi-modules' )
			)
			: '';

		$quote = '' !== trim( $member['quote'] )
			? sprintf( '<blockquote class="honest-member__quote">%s</blockquote>', esc_html( $member['quote'] ) )
			: '';

		$portrait = $member['image_id']
			? wp_get_attachment_image( $member['image_id'], 'large', false, array( 'class' => 'honest-member__portrait' ) )
			: '';

		$inner = sprintf(
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

		return $this->wrap( $render_slug, $inner, array( 'honest-member' ) );
	}
```

- [ ] **Step 2: Verify**

Render on a member page and confirm name, title, bio, quote, LinkedIn and portrait all appear. Then test a member with no quote and no LinkedIn — neither element should emit empty markup.

- [ ] **Step 3: Commit**

```bash
git commit -am "feat(modules): add Team Member Header"
```

---

### Task 15: Theme Builder body template

**Files:** none in the repo — this is configured in the Divi Theme Builder UI.

- [ ] **Step 1: Create the template**

Divi → Theme Builder → Add New Template → assign to **All Article Authors**. Divi lists it because template conditions enumerate all public non-builtin post types.

- [ ] **Step 2: Build the body layout**

Order: back-to-team bar → Team Member Header → Featured Insights (source = `current_member`, heading "Articles by …") → CTA.

- [ ] **Step 3: Verify**

Visit two different member URLs and confirm each shows its own data, and that a member with no articles doesn't render an empty Insights section.

- [ ] **Step 4: Export the template as a backup**

Theme Builder → Portability → Export, and commit the JSON to `docs/theme-builder/` so the layout is recoverable.

---

### Task 16: Build the Our Team page

- [ ] **Step 1: Assemble**

Create/edit the Our Team page and add, in order: Text Hero → Leadership by Market → Executive Leadership → Testimonials → Featured Insights → CTA.

- [ ] **Step 2: Populate content**

Fill the Teams screens (executive members; four markets with names, captions and members) and backfill quote / LinkedIn / portrait on the 17 authors.

- [ ] **Step 3: Verify against Figma**

Compare against node `50:470` section by section. Note any deviation caused by the missing purple values (Open Assumption 1).

---

### Task 17: QA pass

- [ ] **Step 1: Responsive** — 1440 / 1024 / 768 / 375. Flag anything needing a design decision rather than guessing.
- [ ] **Step 2: Accessibility** — tab ARIA and keyboard, carousel controls, map alternative text, focus visibility, contrast on the purple hover state.
- [ ] **Step 3: Edge cases** — member with no quote / no LinkedIn / no articles / no portrait; market with no members; empty executive list; article with one vs. several authors.
- [ ] **Step 4: Builder** — open every module's settings via the Layers panel and confirm no errors; clear Divi cache.
- [ ] **Step 5: Verify no PHP notices**

```bash
tail -50 "/Users/gustavogomez/Local Sites/honesthealth/logs/php/error.log" 2>/dev/null || echo "no error log (good)"
```

---

### Task 18: Remove the test module

- [ ] **Step 1: Delete** `includes/modules/TestBlock/` and its entry in `honest_divi_modules_map()`.
- [ ] **Step 2: Delete** the "HDM Module Smoke Test" page (ID 109535).
- [ ] **Step 3: Verify** the site renders with no fatal and `honest_test_block` is gone:

```bash
wp eval 'do_action("et_builder_ready"); var_dump( shortcode_exists("honest_test_block") );'
```
Expected: `bool(false)`.

- [ ] **Step 4: Commit**

```bash
git commit -am "chore: remove test module and smoke test page"
```

---

## Notes

**Why `vb_support = 'partial'`:** `'on'` tells the Visual Builder a React component exists for the slug. With none, the VB falls back but `_getContent()` still returns a React component factory, which React stringifies into the canvas as `function(t){return o.default.createElement(...)}`. Any non-`'off'` value routes content down the string branch and keeps full builder support, since `has_vb_support()` is `'off' !== $vb_support`.

**Divi dynamic content limits:** ACF text fields bind natively (Divi exposes post meta as `custom_meta_{key}`). ACF image fields return a raw attachment ID, and relationship fields return serialized data — hence Tasks 13 and 14 read fields in PHP instead.
