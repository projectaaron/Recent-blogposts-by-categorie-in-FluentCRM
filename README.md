# FluentCRM Recent Posts Block

A custom PHP code snippet that adds a "Recent Posts" SmartCode block to the FluentCRM email editor.

## Installation

1. Copy the contents of `fluentcrm-recent-posts-block.php` to your theme's `functions.php` file
2. Or create a custom plugin with this code
3. Or use a code snippets plugin (like Code Snippets) to add the code

## Usage

### Method 1: SmartCodes (Recommended)

After installing, you'll see a new **"Recent Posts"** group in the SmartCode dropdown in the FluentCRM email editor.

Available SmartCodes:

| SmartCode | Description |
|-----------|-------------|
| `{{recent_posts.list}}` | Display 5 recent posts with thumbnails |
| `{{recent_posts.list_3}}` | Display 3 recent posts |
| `{{recent_posts.list_10}}` | Display 10 recent posts |
| `{{recent_posts.latest_title}}` | Latest post title only |
| `{{recent_posts.latest_excerpt}}` | Latest post excerpt only |
| `{{recent_posts.latest_link}}` | Latest post URL |
| `{{recent_posts.latest_image}}` | Latest post featured image |
| `{{recent_posts.latest_full}}` | Full card layout for latest post |

### Method 2: Shortcode in HTML Block

If using the Visual Builder, add an HTML block and use the shortcode:

```html
[fluentcrm_recent_posts count="5" show_image="yes" show_excerpt="yes"]
```

#### Shortcode Parameters:

| Parameter | Default | Options | Description |
|-----------|---------|---------|-------------|
| `count` | 5 | Any number | Number of posts to display |
| `show_image` | yes | yes/no | Show featured images |
| `show_excerpt` | yes | yes/no | Show post excerpts |
| `show_date` | yes | yes/no | Show post dates |
| `category` | (all) | category-slug | Filter by category |
| `post_type` | post | post/page/custom | Post type to query |

#### Examples:

```html
<!-- Show 3 posts from "news" category -->
[fluentcrm_recent_posts count="3" category="news"]

<!-- Show 5 posts without images -->
[fluentcrm_recent_posts count="5" show_image="no"]

<!-- Show custom post type -->
[fluentcrm_recent_posts count="4" post_type="product"]
```

## Customization

### Modify the Query

Use the `fluentcrm_recent_posts_query_args` filter to modify the post query:

```php
add_filter('fluentcrm_recent_posts_query_args', function($args) {
    // Only show posts from specific category
    $args['category_name'] = 'news';

    // Exclude certain posts
    $args['post__not_in'] = [123, 456];

    return $args;
});
```

### Customize the HTML Output

Use the `fluentcrm_recent_posts_html` filter to modify the output:

```php
add_filter('fluentcrm_recent_posts_html', function($html, $posts, $count) {
    // Add custom wrapper
    return '<div class="my-custom-wrapper">' . $html . '</div>';
}, 10, 3);
```

### Customize the Latest Post Card

Use the `fluentcrm_latest_post_card_html` filter:

```php
add_filter('fluentcrm_latest_post_card_html', function($html, $post) {
    // Modify the card HTML
    return $html;
}, 10, 2);
```

## Styling Notes

- All HTML uses inline styles for email client compatibility
- Uses table-based layouts for maximum email client support
- Tested with major email clients (Gmail, Outlook, Apple Mail)
- Responsive design considerations built-in

## Requirements

- WordPress 5.0+
- FluentCRM 2.5+
- PHP 7.4+

## Hooks Reference

### Actions
- `fluent_crm/after_init` - Used to register the SmartCode

### Filters
- `fluentcrm_recent_posts_query_args` - Modify WP_Query arguments
- `fluentcrm_recent_posts_html` - Modify the posts list HTML output
- `fluentcrm_latest_post_card_html` - Modify the single post card HTML
- `fluent_crm/email-body-text` - Process shortcodes in email content
- `fluent_crm/parse_campaign_email_text` - Process shortcodes in campaigns

## Resources

- [FluentCRM Developer Documentation](https://developers.fluentcrm.com/)
- [FluentCRM SmartCode Documentation](https://developers.fluentcrm.com/modules/smart-code/)
- [FluentCRM Filter Hooks](https://developers.fluentcrm.com/hooks/filters/)
- [FluentCRM Action Hooks](https://developers.fluentcrm.com/hooks/actions/)

## License

GPL v2 or later
