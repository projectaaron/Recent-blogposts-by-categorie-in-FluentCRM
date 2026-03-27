<?php
/**
 * FluentCRM Recent Posts SmartCode Block
 *
 * Add this code to your theme's functions.php file or create a custom plugin.
 * This adds a "Recent Posts" SmartCode to the FluentCRM email editor that you can
 * insert using the SmartCode dropdown: {{recent_posts.list}}
 *
 * @package FluentCRM_Recent_Posts
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Recent Posts SmartCode for FluentCRM
 *
 * This creates a new SmartCode group "Recent Posts" in the FluentCRM email editor
 * that allows you to dynamically insert recent blog posts into your emails.
 */
add_action('fluent_crm/after_init', function () {

    // Unique key for this smartcode group (prefixed to avoid conflicts)
    $key = 'recent_posts';

    // Title shown in the SmartCode dropdown
    $title = 'Recent Posts';

    // Available SmartCodes in this group
    $shortCodes = [
        'list'              => 'Recent Posts List (Default 5)',
        'list_3'            => 'Recent Posts List (3 Posts)',
        'list_10'           => 'Recent Posts List (10 Posts)',
        'latest_title'      => 'Latest Post Title',
        'latest_excerpt'    => 'Latest Post Excerpt',
        'latest_link'       => 'Latest Post URL (raw)',
        'latest_link_html'  => 'Latest Post Link (clickable)',
        'latest_button'     => 'Latest Post Button (styled)',
        'latest_image'      => 'Latest Post Featured Image',
        'latest_full'       => 'Latest Post (Full Card)',
        'latest_body'       => 'Latest Post Full HTML Body',
    ];

    // Callback function that returns the actual content
    $callback = function ($code, $valueKey, $defaultValue, $subscriber) {

        // Check for category suffix (e.g., latest_excerpt_category_marriage_prayers)
        $category = '';
        if (preg_match('/^(.+)_category_(.+)$/', $valueKey, $matches)) {
            $valueKey = $matches[1];
            $category = $matches[2];
        }

        switch ($valueKey) {
            case 'list':
                return fluentcrm_get_recent_posts_html(5, $category);

            case 'list_3':
                return fluentcrm_get_recent_posts_html(3, $category);

            case 'list_10':
                return fluentcrm_get_recent_posts_html(10, $category);

            case 'latest_title':
                return fluentcrm_get_latest_post_field('title', $category);

            case 'latest_excerpt':
                return fluentcrm_get_latest_post_field('excerpt', $category);

            case 'latest_link':
                return fluentcrm_get_latest_post_field('link', $category);

            case 'latest_link_html':
                return fluentcrm_get_latest_post_field('link_html', $category);

            case 'latest_button':
                return fluentcrm_get_latest_post_field('button', $category);

            case 'latest_image':
                return fluentcrm_get_latest_post_field('image', $category);

            case 'latest_full':
                return fluentcrm_get_latest_post_card($category);

            case 'latest_body':
                return fluentcrm_get_latest_post_body($category);

            default:
                return $defaultValue;
        }
    };

    // Register the SmartCode with FluentCRM
    FluentCrmApi('extender')->addSmartCode($key, $title, $shortCodes, $callback);

}, 10);


/**
 * Generate HTML for recent posts list
 *
 * @param int $count Number of posts to display
 * @param string $category Category slug to filter by
 * @return string HTML output
 */
function fluentcrm_get_recent_posts_html($count = 5, $category = '') {

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filter by category if specified (supports both ID and slug)
    if (!empty($category)) {
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_text_field($category);
        }
    }

    // Allow filtering the query args
    $args = apply_filters('fluentcrm_recent_posts_query_args', $args);

    $posts = get_posts($args);

    if (empty($posts)) {
        return '<p style="color: #666; font-style: italic;">No recent posts available.</p>';
    }

    // Build the HTML output - email-safe inline styles
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; margin: 20px 0;">';

    foreach ($posts as $post) {
        $permalink = get_permalink($post->ID);
        $title = esc_html($post->post_title);
        $excerpt = wp_trim_words($post->post_excerpt ?: $post->post_content, 20, '...');
        $excerpt = esc_html($excerpt);
        $date = get_the_date('M j, Y', $post->ID);
        $thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail');

        $html .= '<tr>';
        $html .= '<td style="padding: 15px 0; border-bottom: 1px solid #e5e5e5; vertical-align: top;">';

        // Container table for image + content layout
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">';
        $html .= '<tr>';

        // Featured image column (if exists)
        if ($thumbnail) {
            $html .= '<td width="80" style="padding-right: 15px; vertical-align: top;">';
            $html .= '<a href="' . esc_url($permalink) . '" style="text-decoration: none;">';
            $html .= '<img src="' . esc_url($thumbnail) . '" alt="' . $title . '" width="80" height="80" style="display: block; border-radius: 4px; object-fit: cover;" />';
            $html .= '</a>';
            $html .= '</td>';
        }

        // Content column
        $html .= '<td style="vertical-align: top;">';
        $html .= '<a href="' . esc_url($permalink) . '" style="color: #333333; text-decoration: none; font-size: 16px; font-weight: 600; line-height: 1.4; display: block; margin-bottom: 5px;">' . $title . '</a>';
        $html .= '<p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 0 0 8px 0;">' . $excerpt . '</p>';
        $html .= '<span style="color: #999999; font-size: 12px;">' . $date . '</span>';
        $html .= '</td>';

        $html .= '</tr>';
        $html .= '</table>';

        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';

    // Allow filtering the final HTML output
    return apply_filters('fluentcrm_recent_posts_html', $html, $posts, $count);
}


/**
 * Get a specific field from the latest post
 *
 * @param string $field Field to retrieve (title, excerpt, link, image)
 * @param string $category Category slug to filter by
 * @return string Field value or empty string
 */
function fluentcrm_get_latest_post_field($field, $category = '') {

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filter by category if specified (supports both ID and slug)
    if (!empty($category)) {
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_text_field($category);
        }
    }

    $posts = get_posts($args);

    if (empty($posts)) {
        return '';
    }

    $post = $posts[0];

    switch ($field) {
        case 'title':
            return esc_html($post->post_title);

        case 'excerpt':
            $excerpt = $post->post_excerpt ?: $post->post_content;
            return esc_html(wp_trim_words($excerpt, 30, '...'));

        case 'link':
            // Strip protocol so editor's auto-added http:// creates valid URL
            $url = get_permalink($post->ID);
            return preg_replace('#^https?://#', '', $url);

        case 'link_html':
            $url = get_permalink($post->ID);
            $title = esc_html($post->post_title);
            return '<a href="' . esc_url($url) . '" style="color: #0073aa; text-decoration: underline;">' . $title . '</a>';

        case 'button':
            $url = get_permalink($post->ID);
            return '<a href="' . esc_url($url) . '" style="display: inline-block; background-color: #0073aa; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; font-weight: 600;">Read Latest Post</a>';

        case 'image':
            $thumbnail = get_the_post_thumbnail_url($post->ID, 'medium');
            if ($thumbnail) {
                return '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($post->post_title) . '" style="max-width: 100%; height: auto; border-radius: 4px; display: block;" />';
            }
            return '';

        default:
            return '';
    }
}


/**
 * Get a full card layout for the latest post
 *
 * @param string $category Category slug to filter by
 * @return string HTML card for the latest post
 */
function fluentcrm_get_latest_post_card($category = '') {

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filter by category if specified (supports both ID and slug)
    if (!empty($category)) {
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_text_field($category);
        }
    }

    $posts = get_posts($args);

    if (empty($posts)) {
        return '<p style="color: #666; font-style: italic;">No recent posts available.</p>';
    }

    $post = $posts[0];
    $permalink = get_permalink($post->ID);
    $title = esc_html($post->post_title);
    $excerpt = wp_trim_words($post->post_excerpt ?: $post->post_content, 40, '...');
    $excerpt = esc_html($excerpt);
    $date = get_the_date('F j, Y', $post->ID);
    $author = get_the_author_meta('display_name', $post->post_author);
    $thumbnail = get_the_post_thumbnail_url($post->ID, 'large');

    // Build card HTML with email-safe inline styles
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; margin: 20px 0; background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;">';

    // Featured image row
    if ($thumbnail) {
        $html .= '<tr>';
        $html .= '<td style="padding: 0;">';
        $html .= '<a href="' . esc_url($permalink) . '" style="text-decoration: none;">';
        $html .= '<img src="' . esc_url($thumbnail) . '" alt="' . $title . '" width="100%" style="display: block; max-width: 100%; height: auto;" />';
        $html .= '</a>';
        $html .= '</td>';
        $html .= '</tr>';
    }

    // Content row
    $html .= '<tr>';
    $html .= '<td style="padding: 20px;">';

    // Title
    $html .= '<a href="' . esc_url($permalink) . '" style="color: #333333; text-decoration: none; font-size: 22px; font-weight: 700; line-height: 1.3; display: block; margin-bottom: 10px;">' . $title . '</a>';

    // Meta info
    $html .= '<p style="color: #999999; font-size: 13px; margin: 0 0 12px 0;">';
    $html .= '<span>' . esc_html($date) . '</span>';
    if ($author) {
        $html .= ' &bull; <span>By ' . esc_html($author) . '</span>';
    }
    $html .= '</p>';

    // Excerpt
    $html .= '<p style="color: #555555; font-size: 15px; line-height: 1.6; margin: 0 0 15px 0;">' . $excerpt . '</p>';

    // Read more button
    $html .= '<a href="' . esc_url($permalink) . '" style="display: inline-block; background-color: #0073aa; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600;">Read More &rarr;</a>';

    $html .= '</td>';
    $html .= '</tr>';

    $html .= '</table>';

    return apply_filters('fluentcrm_latest_post_card_html', $html, $post);
}


/**
 * Get the full HTML body/content of the latest post
 *
 * @param string $category Category slug to filter by
 * @return string Full HTML content of the latest post
 */
function fluentcrm_get_latest_post_body($category = '') {

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filter by category if specified (supports both ID and slug)
    if (!empty($category)) {
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_text_field($category);
        }
    }

    // Allow filtering the query args
    $args = apply_filters('fluentcrm_latest_post_body_query_args', $args);

    $posts = get_posts($args);

    if (empty($posts)) {
        return '';
    }

    $post = $posts[0];

    // Get the full post content with filters applied (shortcodes, embeds, etc.)
    $content = apply_filters('the_content', $post->post_content);

    // Default inline styles for the container - can be filtered
    $default_styles = 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size: 16px; line-height: 1.6; color: #333333;';
    $container_styles = apply_filters('fluentcrm_latest_post_body_styles', $default_styles);

    // Wrap in a styled div container so it can be styled as a block
    $html = '<div style="' . esc_attr($container_styles) . '">' . $content . '</div>';

    // Allow filtering the final output
    return apply_filters('fluentcrm_latest_post_body_html', $html, $post);
}


/**
 * Optional: Add a shortcode that can be used in the Visual Builder HTML block
 * Usage: [fluentcrm_recent_posts count="5" show_image="yes" show_excerpt="yes"]
 */
add_shortcode('fluentcrm_recent_posts', function($atts) {

    $atts = shortcode_atts([
        'count'        => 5,
        'show_image'   => 'yes',
        'show_excerpt' => 'yes',
        'show_date'    => 'yes',
        'category'     => '',
        'post_type'    => 'post',
    ], $atts);

    $args = [
        'post_type'      => sanitize_text_field($atts['post_type']),
        'post_status'    => 'publish',
        'posts_per_page' => intval($atts['count']),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filter by category if specified (supports both ID and slug)
    if (!empty($atts['category'])) {
        if (is_numeric($atts['category'])) {
            $args['cat'] = intval($atts['category']);
        } else {
            $args['category_name'] = sanitize_text_field($atts['category']);
        }
    }

    $posts = get_posts($args);

    if (empty($posts)) {
        return '<p style="color: #666; font-style: italic;">No recent posts available.</p>';
    }

    $show_image = ($atts['show_image'] === 'yes');
    $show_excerpt = ($atts['show_excerpt'] === 'yes');
    $show_date = ($atts['show_date'] === 'yes');

    // Build the HTML output
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; margin: 20px 0;">';

    foreach ($posts as $post) {
        $permalink = get_permalink($post->ID);
        $title = esc_html($post->post_title);
        $excerpt = wp_trim_words($post->post_excerpt ?: $post->post_content, 20, '...');
        $excerpt = esc_html($excerpt);
        $date = get_the_date('M j, Y', $post->ID);
        $thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail');

        $html .= '<tr>';
        $html .= '<td style="padding: 15px 0; border-bottom: 1px solid #e5e5e5; vertical-align: top;">';

        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">';
        $html .= '<tr>';

        // Featured image column
        if ($show_image && $thumbnail) {
            $html .= '<td width="80" style="padding-right: 15px; vertical-align: top;">';
            $html .= '<a href="' . esc_url($permalink) . '" style="text-decoration: none;">';
            $html .= '<img src="' . esc_url($thumbnail) . '" alt="' . $title . '" width="80" height="80" style="display: block; border-radius: 4px; object-fit: cover;" />';
            $html .= '</a>';
            $html .= '</td>';
        }

        // Content column
        $html .= '<td style="vertical-align: top;">';
        $html .= '<a href="' . esc_url($permalink) . '" style="color: #333333; text-decoration: none; font-size: 16px; font-weight: 600; line-height: 1.4; display: block; margin-bottom: 5px;">' . $title . '</a>';

        if ($show_excerpt) {
            $html .= '<p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 0 0 8px 0;">' . $excerpt . '</p>';
        }

        if ($show_date) {
            $html .= '<span style="color: #999999; font-size: 12px;">' . $date . '</span>';
        }

        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';

    return $html;
});


/**
 * Process shortcodes in FluentCRM email content
 * This ensures the shortcode works when used in the Visual Builder HTML block
 */
add_filter('fluent_crm/email-body-text', function($content, $subscriber) {
    // Process our shortcode in the email content
    if (has_shortcode($content, 'fluentcrm_recent_posts')) {
        $content = do_shortcode($content);
    }
    return $content;
}, 10, 2);

// Also handle the raw email content filter
add_filter('fluent_crm/parse_campaign_email_text', function($content, $subscriber) {
    if (has_shortcode($content, 'fluentcrm_recent_posts')) {
        $content = do_shortcode($content);
    }
    return $content;
}, 10, 2);
