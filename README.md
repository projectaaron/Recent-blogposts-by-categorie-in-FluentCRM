# Recent Posts SmartCodes for FluentCRM

A single PHP snippet that adds a **Recent Posts** group to FluentCRM's SmartCode dropdown, so you can drop your latest blog posts (or the full latest post) into any campaign, sequence, or automation email. Works with any WordPress site running FluentCRM.

## Installation

Pick one:

1. **FluentSnippets** (or the Code Snippets plugin): create a new PHP snippet, paste the contents of `fluentcrm-recent-posts-block.php`, save and activate.
2. **Theme**: paste the code into your theme's `functions.php`.
3. **Plugin**: drop the file into `wp-content/plugins/` and activate it (add a plugin header if you want it to show a name).

No settings page. Everything is configured through the SmartCode name or the filters below.

## SmartCodes

Open the SmartCode dropdown in the FluentCRM email editor and look for **Recent Posts**.

| SmartCode | Output |
|-----------|--------|
| `{{recent_posts.list}}` | 5 latest posts (thumbnail, title, excerpt, date) |
| `{{recent_posts.list_3}}` | 3 latest posts |
| `{{recent_posts.list_10}}` | 10 latest posts |
| `{{recent_posts.latest_title}}` | Latest post title |
| `{{recent_posts.latest_excerpt}}` | Latest post excerpt |
| `{{recent_posts.latest_link}}` | Latest post URL without `https://` (for FluentCRM button/image link fields) |
| `{{recent_posts.latest_link_html}}` | Latest post title as a clickable link |
| `{{recent_posts.latest_button}}` | Styled "Read Latest Post" button |
| `{{recent_posts.latest_image}}` | Latest post featured image |
| `{{recent_posts.latest_full}}` | Latest post as a card (image, title, date, author, excerpt, button) |
| `{{recent_posts.latest_body}}` | Full content of the latest post |

### Filter by category

Append `_category_{slug-or-id}` to any SmartCode:

```
{{recent_posts.list_3_category_news}}
{{recent_posts.latest_body_category_12}}
{{recent_posts.latest_full_category_press_releases}}
```

Underscores in the slug are converted to hyphens, so `press_releases` matches the `press-releases` category. Numeric values are treated as category IDs.

## Shortcode

For finer control (post type, hiding images, etc.) use the shortcode. It works in posts, pages, and inside FluentCRM emails.

```
[upfluent_recent_posts count="5" category="news" show_image="yes" show_excerpt="yes" show_date="yes" post_type="post"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `count` | `5` | Number of posts |
| `category` | (all) | Category slug or ID |
| `show_image` | `yes` | `yes` / `no` |
| `show_excerpt` | `yes` | `yes` / `no` |
| `show_date` | `yes` | `yes` / `no` |
| `post_type` | `post` | Any public post type |

## Customization (filters)

All filters are prefixed `upfluent_`. Ready-to-uncomment examples are at the bottom of the PHP file.

| Filter | Arguments | Purpose |
|--------|-----------|---------|
| `upfluent_query_args` | `$args, $context` | Change the `WP_Query` args. `$context` is `list`, `latest`, `card`, or `body` so you can target one output. |
| `upfluent_list_html` | `$html, $posts, $count` | Modify the posts list HTML |
| `upfluent_card_html` | `$html, $post` | Modify the latest-post card HTML |
| `upfluent_body_html` | `$html, $post` | Modify the full-content HTML |
| `upfluent_body_styles` | `$css` | Inline CSS for the full-content wrapper |
| `upfluent_button_label` | `$label` | Text of the `latest_button` |
| `upfluent_empty_html` | `$html` | Output when no posts are found |

Example: always pull `latest_body` from one category.

```php
add_filter( 'upfluent_query_args', function ( $args, $context ) {
	if ( 'body' === $context ) {
		$args['category_name'] = 'news';
	}
	return $args;
}, 10, 2 );
```

## Notes

- Output uses table layouts and inline styles for email-client compatibility.
- Only published posts are returned; sticky posts are not pinned to the top.
- Requires WordPress 5.0+, FluentCRM 2.5+, PHP 7.4+.

## License

GPL v2 or later
