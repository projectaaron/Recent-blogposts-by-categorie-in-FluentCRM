# upfluent.io listing

Files used to publish this plugin as a free add-on on [upfluent.io](https://upfluent.io/fluentcrm-recent-posts/).

| File | Purpose |
| --- | --- |
| `page-fluentcrm-recent-posts.html` | Block markup for the product page (`page-product` template). Tokens `{{ZIP_URL}}`, `{{VERSION}}`, `{{IMG_*_ID}}`, `{{IMG_*_URL}}` are filled by the deploy script. |
| `deploy.php` | WP-CLI script. Copies the zip into uploads, imports the screenshots, creates or updates the page, sets Rank Math meta, adds the add-on to the Add-ons index, Changelog and Docs pages. Idempotent. |
| `menu.php` | WP-CLI script. Builds the header menu the block-theme way: a saved Navigation post ("Main Menu", editable under Appearance → Editor → Navigation) with page links and an Add-ons submenu, and points the theme's `parts/header.html` at it with `{"ref":ID}`. Idempotent; backs up header.html. |
| `images/` | Real plugin output rendered with sample posts, used as screenshots. |

## Release a new version

1. Bump `Version` in the plugin header and `Stable tag` in `readme.txt`, add a changelog entry.
2. Build the zip into `dist/` (plugin folder `recent-posts-smartcodes-for-fluentcrm/` containing the PHP file, `readme.txt` and `LICENSE`).
3. Update `$version` in `deploy.php`, commit and merge to `main`.
4. On the site (Pressable WP-CLI), fetch this folder plus the zip into a temporary directory and run `wp eval-file deploy.php`, then delete the directory and purge the edge and CDN caches.

The free download URL is stored in option `upf_rp_free_url`, the version in `upf_rp_version`.
