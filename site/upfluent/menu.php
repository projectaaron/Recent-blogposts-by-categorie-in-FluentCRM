<?php
/**
 * Build the upfluent.io header menu the block-theme way.
 *
 * Back end: a saved Navigation ("Main Menu", post type wp_navigation) with real
 * page links, editable under Appearance → Editor → Navigation.
 * Front end: the theme's parts/header.html references that menu by ID with
 * <!-- wp:navigation {"ref":ID} /--> instead of hard-coded links.
 *
 *   wp eval-file menu.php
 *
 * Idempotent. Keeps a dated backup of header.html when it changes it.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit( "Run with: wp eval-file menu.php\n" );
}

$out = array();

/* ---- Menu definition: label => page slug (children under 'children') ---- */
$menu = array(
	array( 'label' => 'Add-ons', 'page' => 'add-ons', 'children' => array(
		array( 'label' => 'Meta Fields for FluentCart', 'page' => 'fluentcart-custom-meta-fields' ),
		array( 'label' => 'All-In-One MCP for Fluent Suite', 'page' => 'all-in-one-mcp-for-fluent-suite' ),
		array( 'label' => 'Recent Posts SmartCodes for FluentCRM', 'page' => 'fluentcrm-recent-posts' ),
		array( 'label' => 'All add-ons', 'page' => 'add-ons' ),
	) ),
	array( 'label' => 'Fluent Suite', 'page' => 'fluent' ),
	array( 'label' => 'Docs', 'page' => 'docs' ),
	array( 'label' => 'Changelog', 'page' => 'changelog' ),
	array( 'label' => 'Account', 'page' => 'account' ),
);

function upf_menu_link_attrs( $item ) {
	$page = get_page_by_path( $item['page'], OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::error( 'Page not found: ' . $item['page'] );
	}
	return array(
		'label' => $item['label'],
		'type'  => 'page',
		'id'    => $page->ID,
		'url'   => get_permalink( $page ),
		'kind'  => 'post-type',
	);
}

$blocks = '';
foreach ( $menu as $item ) {
	$attrs = upf_menu_link_attrs( $item );
	if ( ! empty( $item['children'] ) ) {
		$blocks .= '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . " -->\n";
		foreach ( $item['children'] as $child ) {
			$blocks .= '<!-- wp:navigation-link ' . wp_json_encode( upf_menu_link_attrs( $child ), JSON_UNESCAPED_SLASHES ) . " /-->\n";
		}
		$blocks .= "<!-- /wp:navigation-submenu -->\n";
	} else {
		$blocks .= '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . " /-->\n";
	}
}
$blocks = rtrim( $blocks );

/* ---- Back end: the saved Navigation post ---- */
$nav = get_page_by_path( 'main-menu', OBJECT, 'wp_navigation' );
if ( ! $nav ) {
	// Reuse the unused default "Navigation" post if it only holds the page-list placeholder.
	foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'numberposts' => -1, 'post_status' => 'any' ) ) as $cand ) {
		if ( trim( $cand->post_content ) === '<!-- wp:page-list /-->' ) {
			$nav = $cand;
			break;
		}
	}
}
$args = array(
	'post_type'    => 'wp_navigation',
	'post_status'  => 'publish',
	'post_title'   => 'Main Menu',
	'post_name'    => 'main-menu',
	'post_content' => $blocks,
);
if ( $nav ) {
	$args['ID'] = $nav->ID;
	$nav_id     = wp_update_post( $args, true );
} else {
	$nav_id = wp_insert_post( $args, true );
}
if ( is_wp_error( $nav_id ) ) {
	WP_CLI::error( 'Menu save failed: ' . $nav_id->get_error_message() );
}
$out[] = "menu: wp_navigation #$nav_id \"Main Menu\" (" . count( $menu ) . ' top-level items)';

/* ---- Front end: theme header references the menu ---- */
$header = get_stylesheet_directory() . '/parts/header.html';
if ( ! file_exists( $header ) ) {
	WP_CLI::error( "Missing $header" );
}
$c = file_get_contents( $header );
$a = strpos( $c, '<!-- wp:navigation' );
if ( false === $a ) {
	WP_CLI::error( 'No navigation block in header.html' );
}
$self = strpos( $c, '/-->', $a );
$end  = strpos( $c, '<!-- /wp:navigation -->', $a );
$is_self_closing = false !== $self && ( false === $end || $self < $end );
$b = $is_self_closing ? $self + 4 : $end + strlen( '<!-- /wp:navigation -->' );
$old_block = substr( $c, $a, $b - $a );

// Keep the block's own settings (overlay, layout, spacing), just add the ref and drop inline links.
preg_match( '/^<!-- wp:navigation (\{.*?\}) (\/)?-->/s', $old_block, $m );
$attrs = ! empty( $m[1] ) ? json_decode( $m[1], true ) : array();
if ( ! is_array( $attrs ) ) {
	$attrs = array();
}
$attrs = array( 'ref' => (int) $nav_id ) + $attrs;
$new_block = '<!-- wp:navigation ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';

if ( $new_block !== $old_block ) {
	copy( $header, $header . '.bak-' . gmdate( 'Ymd-His' ) );
	file_put_contents( $header, substr( $c, 0, $a ) . $new_block . substr( $c, $b ) );
	$out[] = 'header.html: now ' . $new_block;
} else {
	$out[] = 'header.html: already references the menu';
}

wp_cache_flush();
WP_CLI::success( implode( ' | ', $out ) );
