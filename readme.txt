=== InchTitle Variation Titles for WooCommerce ===
Contributors: shopforgelabs, mhkxx2
Tags: woocommerce, variable products, product title, screen size, tv products, variation attributes, dynamic title, seo title, product h1, attribute injection
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dynamically inject selected screen-size (or attribute) variations into the visible WooCommerce product H1 title for eligible categories.

== Description ==

**InchTitle Variation Titles for WooCommerce** solves a common electronics catalog problem: variable TV / size products often keep a generic H1 while the selected inch size lives only in the variation dropdown.

InchTitle watches the selected variation attribute (default: `pa_screen_size`) and injects the chosen size into the visible product title — typically right after the brand term — so shoppers and on-page SEO both see the exact configuration being viewed.

= Why store owners use InchTitle =

* Make product H1 titles reflect the selected screen size instantly
* Improve clarity for TV, monitor, and size-based variable products
* Keep titles readable: Brand + Size + Model instead of a vague parent title
* Target only the categories you choose (with child-category inheritance)
* Preview transformations in a built-in admin studio before going live

= Core features =

* **Live variation title injection** on single product pages
* **Category targeting rules** with hierarchy / inheritance awareness
* **Attribute-driven** (default taxonomy: `pa_screen_size`)
* **Admin Transformation Studio** with sandbox presets and live preview
* **Archive/variation title filter** support where WooCommerce exposes it
* **HPOS / Cart Checkout Blocks / Product Block Editor** compatibility declarations
* **Clean uninstall** — removes plugin options only

= Perfect for =

* Smart TV and electronics stores
* Any WooCommerce catalog where size/attribute belongs in the product title
* Stores using brand-first title structures (e.g. “Samsung 55 Inch …”)

= Developer notes =

* Frontend data localized for progressive enhancement
* Admin studio assets load only on the plugin settings screen
* Options cleaned on uninstall

== Installation ==

1. Upload `inchtitle-variation-titles-for-woocommerce` to `/wp-content/plugins/`
2. Activate **InchTitle Variation Titles for WooCommerce**
3. Go to **WooCommerce → TV Variation Titles** (InchTitle settings)
4. Select eligible product categories and save
5. Open a variable product in those categories and change screen size to verify the H1 update

== Frequently Asked Questions ==

= Which attribute does it use? =

By default it targets the `pa_screen_size` product attribute taxonomy.

= Will it rewrite product titles in the database? =

No. Titles are transformed at render/runtime for eligible products. Uninstall leaves your catalog titles untouched.

= Can I choose categories? =

Yes. Pick one or more root categories; child categories can be inherited by the targeting logic.

= Does it work without WooCommerce? =

No. WooCommerce is required.

== Screenshots ==

1. Admin Transformation Studio with live title preview
2. Category targeting and hierarchy explorer
3. Frontend H1 updating when a screen size is selected

== Changelog ==

= 1.0.0 =
* Initial public release
* Dynamic screen-size injection into visible product titles
* Category targeting studio
* WooCommerce feature compatibility declarations
* Uninstall cleanup

== Upgrade Notice ==

= 1.0.0 =
First public release of InchTitle Variation Titles for WooCommerce.
