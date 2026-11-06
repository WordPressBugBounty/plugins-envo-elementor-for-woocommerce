=== Envo's Templates & Widgets for Elementor and WooCommerce ===
Contributors: EnvoThemes
Tags: elementor, woocommerce, elementor templates, woocommerce templates, elementor widgets
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Ready-made Elementor shop designs with their subpages, imported in one click, plus 12 Elementor widgets for WooCommerce.

== Description ==

**Envo's Templates & Widgets** gives your WooCommerce store a finished look in minutes. Pick a design from the template library, choose its pages and import them. You get a home page with matching About, FAQ and Contact pages, all made with Elementor and ready to edit. Then add products, carousels, tabs and more with 12 widgets made for shops.

[Live demo](https://envothemes.com/elementor-templates-for-woocommerce/) | [Documentation](https://envothemes.com/docs/) | [PRO add-on](https://envothemes.com/product/elementor-templates-woocommerce-pro/)

= Template library: whole designs, not just single pages =

The library (**Envo Templates** in the admin menu) has 80 designs for shops: fashion, electronics, furniture, kitchenware, beauty, sport, gaming and more. Every design is a home page together with its subpages, so the whole site looks consistent.

* **Import a whole design.** Tick the pages you want and import them in one go. Links between the pages point to your new pages.
* **Front page and menu.** Optionally set the imported home page as the front page of your site and create a menu with the new pages.
* **Single pages.** About, FAQ, Contact, Shipping & Returns and other pages can be imported on their own, as a page, a post or into My Templates.
* **Inside the Elementor editor.** The Envo Templates button (next to "Add Template") inserts a page into the page you are editing. Ctrl+Z takes it back.
* **Required plugins.** WooCommerce or Contact Form 7 are installed and activated with one click before the import, if a page needs them.
* **Images included.** Images of the pages are copied into your media library.
* **Always up to date.** New designs are added to the library on our side. You see them right away, without updating the plugin.
* **Free and PRO.** 13 designs are free. The [PRO add-on](https://envothemes.com/product/elementor-templates-woocommerce-pro/) unlocks 67 more designs and their pages.

= 12 Elementor widgets for WooCommerce stores =

All widgets are in the Elementor panel under "ETWW Elements". Each one has its own styling options, and its scripts and styles load only on pages that use it.

* **Woo – Products:** featured, best selling, on sale, recent or hand-picked products in a grid, with include and exclude options.
* **Woo – Carousel:** the same products in a sliding carousel.
* **Woo – Categories:** product categories in a grid.
* **Woo – Add To Cart:** an add to cart button for a product you choose.
* **Ajax Search:** live search of products, posts or pages, without leaving the page.
* **Tabs:** tabs with several layouts, for text, Elementor templates or WooCommerce product lists.
* **Contact Form 7:** styling options for forms of the free [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) plugin.
* **Price Table:** pricing tables in several designs.
* **Flip Box:** a box that turns over on hover and shows its back side.
* **Off Canvas:** a button that slides in a panel with your sidebar or any Elementor template.
* **Blog Grid:** your latest posts in a grid.
* **Animated Heading:** headings with typing and other animations.

= Works with your theme =

The designs and widgets work with any theme that supports WooCommerce and Elementor. Products and categories keep the look of your theme. Our demos use the free [Envo Storefront](https://wordpress.org/themes/envo-storefront/) theme.

Tested with themes by EnvoThemes, such as [Envo One](https://envothemes.com/envo-one-woocommerce/), [Envo Royal](https://envothemes.com/envo-royal-free-wp-theme/), [Enwoo](https://enwoo-wp.com/), [Entr](https://envothemes.com/entr-free-wp-theme/), [Spacr](https://envothemes.com/spacr-free-wp-theme/), [Envo Shop](https://wordpress.org/themes/envo-shop/), [Envo Storefront](https://wordpress.org/themes/envo-storefront/) and [Envo eCommerce](https://wordpress.org/themes/envo-ecommerce/). Also tested with Hello Elementor, Astra, OceanWP, GeneratePress, Neve, Sydney, Zakra and the Twenty Twenty themes.

== External services ==

The template library loads its designs from the EnvoThemes template library on [envothemes.com](https://envothemes.com/). The list of designs is also checked once a day in the background, so that you are told about new designs.

* **List of designs** – loaded at most once a day (in the background or when the library is opened), or when you click Refresh. Only a plain request is sent; the address of your site is not sent. The background check can be turned off with `add_filter( 'etww/library/check_new', '__return_false' );`.
* **Preview images** – loaded from envothemes.com and copied into `wp-content/uploads/etww-previews` of your site, so later visits show the local copies.
* **Page content** – downloaded when you import or insert a page. Images used by the page are downloaded from envothemes.com into your media library by Elementor.
* **PRO designs** – only when the PRO add-on is active, its licence key and your site's domain are sent with the request for a PRO page, so that the library can check the licence.

No personal data is stored on envothemes.com. The library address can be changed with the `etww/library_url` filter or the `ETWW_LIBRARY_URL` constant.

* [Terms and conditions](https://envothemes.com/terms-and-conditions/)
* [Privacy policy](https://envothemes.com/privacy-policy/)

== Installation ==

1. In your dashboard go to Plugins › Add New Plugin and search for "Envo's Templates & Widgets for Elementor and WooCommerce". Or upload the `envo-elementor-for-woocommerce` folder to `/wp-content/plugins/`.
2. Activate the plugin. It needs [Elementor](https://wordpress.org/plugins/elementor/), and WordPress asks you to install it first if it is missing. The WooCommerce widgets need [WooCommerce](https://wordpress.org/plugins/woocommerce/).
3. Go to **Envo Templates** in the admin menu, pick a design, choose its pages and click Import. Or switch to Single pages and import one page.
4. In the Elementor editor, click the Envo Templates button next to "Add Template" to insert a page into the page you are editing.
5. Find the widgets in the Elementor panel under "ETWW Elements".

== Frequently Asked Questions ==

= Is Elementor PRO required? =

No. Everything works with the free Elementor plugin.

= Do I need WooCommerce? =

The template library works without it. The WooCommerce widgets (products, carousel, categories, add to cart) and shop designs that show products need WooCommerce. The library tells you which plugins a design needs and installs them for you.

= What exactly does a design import? =

The pages you tick, as Elementor pages with their images. Your products, theme settings and other pages stay as they are. If you choose to, the home page becomes your front page and a menu with the new pages is created; assign it to a menu location of your theme under Appearance › Menus.

= My products look different than in the demo. =

Product and category boxes get their look from your theme, and each theme styles WooCommerce in its own way. Our demos use the free Envo Storefront theme. Some themes override the WooCommerce styling, so a few widget style options may have no effect there.

= How do I get the PRO designs? =

Install and activate the [PRO add-on](https://envothemes.com/product/elementor-templates-woocommerce-pro/) and enter your licence key. The PRO designs in the library unlock right away.

= Will my pages break when I update from version 1.x? =

No. The widgets kept their names, settings and HTML, so pages made with them look the same. Only the template library is new; its old address redirects to Envo Templates.

= Does the plugin slow down my site? =

Each widget loads its scripts and styles only on pages that use it. The library runs only in the admin and in the Elementor editor.

== Screenshots ==

1. 80 ready-made shop designs – each one a home page with its About, FAQ and Contact pages
2. Import a whole design: choose its pages, set the front page and create a menu
3. Or import a single page – as a page, a post or into My Templates
4. Insert a page right in the Elementor editor with the Envo Templates button
5. 12 widgets for your shop in the Elementor panel
6. WooCommerce products in a grid, a carousel or tabs
7. Animated headings, flip boxes, pricing tables and more
8. Start with the free designs and unlock more with the PRO add-on

== Changelog ==

= 2.0.0 =
* New template library: designs with their subpages, imported as a whole or page by page
* Import a single page as a new page, into My Templates, or insert it in the Elementor editor
* Optional front page and menu after importing a design; links between the imported pages point to the new pages
* Required plugins are installed and activated from the library
* Templates load from the envothemes.com library, every file is checked with a checksum; PRO designs need an active PRO licence
* Preview images are kept on your site
* New designs are announced: a count in the menu, a notice on the dashboard and a "New" badge in the library
* WooCommerce is no longer required for the library (WooCommerce widgets still need it)
* The library moved from WooCommerce › Templates Library to Envo Templates (the old address redirects)
* New plugin structure, widgets unchanged
* Security: the post search of the widget settings needs a nonce and lists only published content; widget output escaped
* Text domain changed to envo-elementor-for-woocommerce (the plugin slug), for translations on translate.wordpress.org
* New readme, banner, icon and screenshots
* Requires WordPress 6.5 and PHP 7.4
* Tested with Elementor 4.3 and WooCommerce 11.2

= 1.4.28 =
* Tested with WP 7.1
* Tested with WooCommerce 11.0
* Tested with Elementor 4.2
* Code update

= 1.4.27 =
* Security update

= 1.4.26 =
* Tested with WP 7.0
* Tested with WooCommerce 10.8
* Tested with Elementor 4.1

= 1.4.25 =
* Updated plugin description
* Tested with Elementor 3.33
* Tested with WooCommerce 10.3

= 1.4.24 =
* Renamed plugin
* Updated banners
* Tested with Elementor 3.32
* Tested with WooCommerce 10.2

= 1.4.23 =
* Tagged for WP 6.8
* Tested with Elementor 3.28
* Tested with WooCommerce 9.8

= 1.4.22 =
* Fixed missing ajax search button background color
* Tested with Elementor 3.27
* Tested with WooCommerce 9.5

= 1.4.21 =
* Tagged for WP 6.7
* Tested with Elementor 3.25

= 1.4.20 =
* Security update

= 1.4.19 =
* Fixed incorrectly tagged version

= 1.4.18 =
* Fixed security issue posted on Patchstack
* Fixed flip box icon issue
* Tested with WooCommerce 9.4
* Tested with Elementor 3.24


= 1.4.17 =
* Security update

= 1.4.16 =
* Tested with WP 6.6
* Tested with WooCommerce 9.1
* Tested with Elementor 3.23

= 1.4.15 =
* Updated notifications
* Updated plugin description

= 1.4.14 =
* Updated importer
* Fixed images import
* Tested with WooCommerce 9.0

= 1.4.13 =
* Code improvements
* Tested with Elementor 3.22

= 1.4.12 =
* Fixed missing icons

= 1.4.11 =
* CSS improvements and fixes

= Version: 1.4.10 =
* Fixed PHP 8.2+ deprecated error notice

= Version: 1.4.9 =
* Fixed animated heading security issue

= Version: 1.4.8 =
* Fixed carousel slider error
* Tested with WooCommerce 8.8
* Tested with Elementor 3.21

= Version: 1.4.7 =
* Tested with WP 6.5
* Tested with WooCommerce 8.7

= Version: 1.4.6 =
* Added new templates

= Version: 1.4.5 =
* Updated code
* Fixed security issues
* Tested with WooCommerce 8.6
* Tested with WP 6.4
* Tested with Elementor 3.20

= Version: 1.4.4 =
* Updated templates

= Version: 1.4.3 =
* Updated slider font
* Tested with WooCommerce 8.2
* Tested with WP 6.3
* Tested with Elementor 3.17
* Added support for WooCommerce HPOS

= Version: 1.4.2 =
* Updated slider font
* Tested with WooCommerce 7.3
* Tested with WP 6.2
* Tested with Elementor 3.11
* Removed deprecated code

= Version: 1.4.1 =
* Tested with WooCommerce 7.0
* Tested with WP 6.1
* Added support for Elementor 3.8
* Fixed carousel undefined variable issue

= Version: 1.4.0 =
* Tested with WooCommerce 6.5
* Tested with WP 6.0
* Added support for Elementor 3.7
* Removed deprecated code

= Version: 1.3.3 =
* Tested with WooCommerce 6.1
* Tested with WP 5.9

= Version: 1.3.2 =
* Added option to import the template into page
* Added option to select page template
* Added 3 new PRO templates
* Tested with WooCommerce 6.0

= Version: 1.3.0 =
* Added support for Elementor 3.5
* Fixed Elementor carousel widget in Elementor 3.5
* Fixed templates importer in Elementor 3.5

= Version: 1.2.11 =
* Added new templates
* Updated deprecated code
* Fixed ajax search widget display issue

= Version: 1.2.9 =
* Fix WP compatibility

= Version: 1.2.8 =
* Tested with latest WooCommerce and Elementor

= Version: 1.2.7 =
* Added new templates

= Version: 1.2.6 =
* Added new templates
* Fixed WooCommerce carousel arrows colors
* Tested with WooCommerce 5.0

= Version: 1.2.5 =
* Tested with WP 5.6
* Tested with WooCommerce 4.8
* Added support for new theme

= Version: 1.2.4 =
* Added 3 new PRO FAQ templates
* Added 5 new PRO About templates
* Added 8 new PRO Contact templates

= Version: 1.2.3 =
* Added 3 new templates

= Version: 1.2.2 =
* Fixed widgets image options

= Version: 1.2.1 =
* Fixed products images compatibility with Envo PRO addons
* Fixed category color widget setting (with Envo PRO addons)

= Version: 1.2.0 =
* Added new free and PRO templates
* Code updates

= Version: 1.1.3 =
* Added 3 new PRO templates
* Minor code fixes

= Version: 1.1.2 =
* Fixed Demo #2 issue

= Version: 1.1.2 =
* Fixed Elementor tabs loading issue

= Version: 1.1.1 =
* Updated readme file
* Added list of tested themes

= Version: 1.1.0 =
* Code improvements
* Added PRO banner and templates

= Version: 1.0.2 =
* Added ability to render shortcode in tabs
* Added options to change WooCommerce products layout in tabs (using shortcode)
* Code improvements

= Version: 1.0.1 =
* Added item margin for Woo - Products widget
* Fixed button styling for non-elementor areas

= Version: 1.0 =
* Initial version
