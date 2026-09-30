<?php
/**
 * Deploy the Recent Posts SmartCodes product page to upfluent.io.
 *
 * Run on the site with WP-CLI from a folder containing this file, the
 * page template, the plugin zip and the four screenshots:
 *
 *   wp eval-file deploy.php
 *
 * Idempotent: re-running updates the page and skips index rows, changelog
 * lines and docs sections that already exist. Prints what it did.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit( "Run with: wp eval-file deploy.php\n" );
}

$dir     = __DIR__;
$version = '2.0.0';
$slug    = 'fluentcrm-recent-posts';
$title   = 'Recent Posts SmartCodes for FluentCRM';
$zipname = "recent-posts-smartcodes-for-fluentcrm-{$version}.zip";

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$uploads = wp_upload_dir();
$out     = array();

/* ---- 1. Plugin zip: copy into uploads/YYYY/MM and record the URL ---- */
$zip_src = "$dir/$zipname";
if ( ! file_exists( $zip_src ) ) {
	WP_CLI::error( "Missing $zip_src" );
}
$zip_dst = trailingslashit( $uploads['path'] ) . $zipname;
copy( $zip_src, $zip_dst );
$zip_url = trailingslashit( $uploads['url'] ) . $zipname;
update_option( 'upf_rp_free_url', $zip_url );
update_option( 'upf_rp_version', $version );
$out[] = "zip: $zip_url";

/* ---- 2. Screenshots: import into the media library once ---- */
$images = array(
	'LIST'    => array( 'fluentcrm-recent-posts-list.png', 'Three recent blog posts in a FluentCRM email, inserted with the {{recent_posts.list_3}} SmartCode' ),
	'CARD'    => array( 'fluentcrm-recent-posts-card.png', 'The newest post from one category as a card in a FluentCRM email' ),
	'BUTTONS' => array( 'fluentcrm-recent-posts-buttons.png', 'Five latest-post buttons styled with SmartCode options and a shortcode' ),
	'BODY'    => array( 'fluentcrm-recent-posts-body.png', 'The full text of the newest post in a FluentCRM email, styled with the upfluent_body_styles filter' ),
);
$tokens = array( '{{ZIP_URL}}' => $zip_url, '{{VERSION}}' => $version );
foreach ( $images as $key => $img ) {
	list( $file, $alt ) = $img;
	$existing = get_posts( array(
		'post_type'   => 'attachment',
		'meta_key'    => '_upf_rp_source',
		'meta_value'  => $file,
		'numberposts' => 1,
		'post_status' => 'any',
	) );
	if ( $existing ) {
		$id = $existing[0]->ID;
	} else {
		$src = "$dir/$file";
		if ( ! file_exists( $src ) ) {
			WP_CLI::error( "Missing $src" );
		}
		$tmp = wp_tempnam( $file );
		copy( $src, $tmp );
		$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $alt );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( 'Image import failed: ' . $id->get_error_message() );
		}
		update_post_meta( $id, '_upf_rp_source', $file );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	$url                               = wp_get_attachment_url( $id );
	$tokens[ '{{IMG_' . $key . '_ID}}' ]  = $id;
	$tokens[ '{{IMG_' . $key . '_URL}}' ] = $url;
	$out[]                             = "image $key: #$id $url";
}

/* ---- 3. Product page: create or update ---- */
$template = file_get_contents( "$dir/page-fluentcrm-recent-posts.html" );
if ( ! $template ) {
	WP_CLI::error( 'Missing page template' );
}
$content = strtr( $template, $tokens );
if ( preg_match( '/\{\{(ZIP_URL|VERSION|IMG_[A-Z]+_(ID|URL))\}\}/', $content, $m ) ) {
	WP_CLI::error( 'Unreplaced token: ' . $m[0] );
}

$page = get_page_by_path( $slug, OBJECT, 'page' );
$args = array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'post_title'     => $title,
	'post_name'      => $slug,
	'post_content'   => $content,
	'post_excerpt'   => 'Free FluentCRM add-on: insert your latest WordPress blog posts, or the full newest post from one category, into any email with a SmartCode.',
	'comment_status' => 'closed',
	'ping_status'    => 'closed',
);
if ( $page ) {
	$args['ID'] = $page->ID;
	$page_id    = wp_update_post( $args, true );
} else {
	$page_id = wp_insert_post( $args, true );
}
if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( 'Page save failed: ' . $page_id->get_error_message() );
}
update_post_meta( $page_id, '_wp_page_template', 'page-product' );
set_post_thumbnail( $page_id, $tokens['{{IMG_LIST_ID}}'] );

// Rank Math SEO.
update_post_meta( $page_id, 'rank_math_title', 'FluentCRM Recent Posts SmartCode: Blog Posts in Any Email, by Category (Free)' );
update_post_meta( $page_id, 'rank_math_description', 'Free FluentCRM add-on. Insert your latest WordPress posts, or the full newest post from one category, into campaigns, sequences and automations with one SmartCode.' );
update_post_meta( $page_id, 'rank_math_focus_keyword', 'FluentCRM recent posts,FluentCRM blog posts in email,FluentCRM latest post SmartCode' );
update_post_meta( $page_id, 'rank_math_facebook_image_id', $tokens['{{IMG_LIST_ID}}'] );
update_post_meta( $page_id, 'rank_math_facebook_image', $tokens['{{IMG_LIST_URL}}'] );
update_post_meta( $page_id, 'rank_math_twitter_use_facebook', 'on' );
$out[] = 'page: #' . $page_id . ' ' . get_permalink( $page_id );

/* ---- 4. Add-ons index and home page: insert a row after All-In-One MCP ---- */
function upf_rp_add_index_row( $post, $slug ) {
	if ( ! $post ) {
		return 'page missing';
	}
	if ( false !== strpos( $post->post_content, "/$slug/" ) ) {
		return 'already listed';
	}
	$c = $post->post_content;
	preg_match_all( '/<!-- wp:group \{[^\n]*?uf-index-row/', $c, $mm, PREG_OFFSET_CAPTURE );
	$starts = array_column( $mm[0], 1 );
	if ( count( $starts ) < 3 ) {
		return 'SKIPPED (rows not found, edit by hand)';
	}
	$row2 = substr( $c, $starts[1], $starts[2] - $starts[1] );
	$new  = $row2;
	$reps = array(
		'>02<' => '>03<',
		'<a href="/all-in-one-mcp-for-fluent-suite/">All-In-One MCP for Fluent Suite</a>' => '<a href="/' . $slug . '/">Recent Posts SmartCodes</a>',
		'>Fluent suite<' => '>FluentCRM<',
		'Your AI assistant runs the Fluent suite.' => 'Free · Your latest blog posts in any email, by category.',
	);
	foreach ( $reps as $from => $to ) {
		if ( 1 !== substr_count( $new, $from ) ) {
			return 'SKIPPED (row anchors not found, edit by hand)';
		}
		$new = str_replace( $from, $to, $new );
	}
	$tail = substr( $c, $starts[2] );
	$tail = str_replace( '>04<', '>05<', $tail );
	$tail = str_replace( '>03<', '>04<', $tail );
	$c    = substr( $c, 0, $starts[2] ) . $new . $tail;
	$c    = str_replace( 'are available now;', 'and Recent Posts SmartCodes for FluentCRM (free) are available now;', $c );
	wp_update_post( array( 'ID' => $post->ID, 'post_content' => $c ) );
	return 'row added';
}
$out[] = 'add-ons index: ' . upf_rp_add_index_row( get_page_by_path( 'add-ons', OBJECT, 'page' ), $slug );
$front = (int) get_option( 'page_on_front' );
$out[] = 'home: ' . ( $front ? upf_rp_add_index_row( get_post( $front ), $slug ) : 'no static front page' );

/* ---- 4b. Navigation: turn the "Add-ons" item into a submenu of available add-ons ---- */
$navs   = get_posts( array( 'post_type' => 'wp_navigation', 'numberposts' => -1, 'post_status' => 'publish' ) );
$navmsg = 'no navigation with an Add-ons link';
foreach ( $navs as $nav ) {
	$c = $nav->post_content;
	if ( false !== strpos( $c, "/$slug/" ) ) {
		$navmsg = 'already linked';
		break;
	}
	if ( ! preg_match( '/<!-- wp:navigation-link (\{[^\n]*?"url":"\/add-ons\/"[^\n]*?\}) \/-->/', $c, $m ) ) {
		continue;
	}
	$attrs = json_decode( $m[1], true );
	if ( ! is_array( $attrs ) ) {
		$navmsg = 'SKIPPED (could not parse Add-ons link)';
		break;
	}
	$children = array(
		array( 'Meta Fields for FluentCart', '/fluentcart-custom-meta-fields/' ),
		array( 'All-In-One MCP for Fluent Suite', '/all-in-one-mcp-for-fluent-suite/' ),
		array( 'Recent Posts SmartCodes for FluentCRM', "/$slug/" ),
		array( 'All add-ons', '/add-ons/' ),
	);
	$inner = '';
	foreach ( $children as $ch ) {
		$inner .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $ch[0], 'url' => $ch[1], 'kind' => 'custom', 'isTopLevelLink' => false ), JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
	$submenu = '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->' . $inner . '<!-- /wp:navigation-submenu -->';
	$c       = str_replace( $m[0], $submenu, $c );
	wp_update_post( array( 'ID' => $nav->ID, 'post_content' => $c ) );
	$navmsg = 'Add-ons submenu added (nav #' . $nav->ID . ')';
	break;
}
$out[] = 'nav: ' . $navmsg;

/* ---- 5. Changelog (page 40): add a line to the first list ---- */
$log = get_page_by_path( 'changelog', OBJECT, 'page' );
if ( $log && false === strpos( $log->post_content, 'Recent Posts SmartCodes' ) ) {
	$c   = $log->post_content;
	$pos = strpos( $c, '<ul class="wp-block-list">' );
	if ( false !== $pos ) {
		$li  = "\n<!-- wp:list-item -->\n<li><strong>Recent Posts SmartCodes for FluentCRM $version</strong>: new free add-on. Thirteen SmartCodes that insert your latest blog posts, a card, a button or the full newest post into any FluentCRM email, filtered by category. Inline button styling, two shortcodes, nine filters. <a href=\"/$slug/\">Product page</a>.</li>\n<!-- /wp:list-item -->";
		$pos += strlen( '<ul class="wp-block-list">' );
		$c    = substr( $c, 0, $pos ) . $li . substr( $c, $pos );
		wp_update_post( array( 'ID' => $log->ID, 'post_content' => $c ) );
		$out[] = 'changelog: line added';
	} else {
		$out[] = 'changelog: SKIPPED (list not found)';
	}
} else {
	$out[] = 'changelog: already listed';
}

/* ---- 6. Docs (page 39): append a section ---- */
$docs = get_page_by_path( 'docs', OBJECT, 'page' );
if ( $docs && false === strpos( $docs->post_content, 'id="fluentcrm-recent-posts"' ) ) {
	$section = <<<HTML


<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading {"anchor":"fluentcrm-recent-posts"} -->
<h2 class="wp-block-heading" id="fluentcrm-recent-posts">Recent Posts SmartCodes for FluentCRM</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Recent Posts SmartCodes for FluentCRM is free. It adds a <strong>Recent Posts</strong> group to the FluentCRM SmartCode dropdown so you can insert your latest blog posts, or the full newest post in one category, into any email. <a href="/$slug/">See the product page</a> for every SmartCode and styling examples.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">What you need</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>WordPress 5.0+ and PHP 7.4+.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>FluentCRM 2.5 or later, free or Pro.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Install</h3>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li><strong>Plugin:</strong> <a href="/$slug/#download">download the zip</a>, then Plugins → Add New → Upload Plugin, install and activate.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Snippet:</strong> paste the PHP file into FluentSnippets or Code Snippets as a PHP snippet and activate it.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Open a FluentCRM email and pick a code from the <strong>Recent Posts</strong> group in the SmartCode dropdown, for example <code>{{recent_posts.list_3}}</code>.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Filter by category, style the button</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Append <code>_category_slug</code> or <code>_category_ID</code> to any SmartCode: <code>{{recent_posts.latest_body_category_news}}</code>.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Append <code>_text_Label-Here</code>, <code>_bg_hex</code> and <code>_color_hex</code> to <code>latest_button</code>: <code>{{recent_posts.latest_button_text_Listen-Now_bg_8B4513}}</code>.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Shortcodes <code>[upfluent_recent_posts]</code> and <code>[upfluent_button]</code> add post type, show/hide options and button sizing.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Filters, all prefixed <code>upfluent_</code>, are listed in the <a href="https://github.com/projectaaron/Recent-blogposts-by-categorie-in-FluentCRM#customization-filters" target="_blank" rel="noopener">README</a>.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Pricing and support</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong>Free forever.</strong> GPL licensed, unlimited sites, no account.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Bugs and feature requests: <a href="https://github.com/projectaaron/Recent-blogposts-by-categorie-in-FluentCRM/issues" target="_blank" rel="noopener">GitHub issues</a> or <a href="mailto:support@upfluent.io">support@upfluent.io</a>.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML;
	wp_update_post( array( 'ID' => $docs->ID, 'post_content' => $docs->post_content . $section ) );
	$out[] = 'docs: section added';
} else {
	$out[] = 'docs: already present';
}

/* ---- 7. Caches ---- */
if ( function_exists( 'rank_math' ) && class_exists( '\RankMath\Helper' ) ) {
	do_action( 'rank_math/sitemap/invalidate_object_type', 'page', $page_id );
}
wp_cache_flush();

WP_CLI::success( implode( ' | ', $out ) );
