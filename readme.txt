=== Sample Shelf for WooCommerce ===
Contributors: cartwrightplugins
Tags: woocommerce, samples, free samples, flooring, tile
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let shoppers request a free or low-cost sample of flooring, tile or fabric before they order.

== Description ==

Sample Shelf adds a **Request a sample** button to your product pages, so shoppers can order a free (or low-cost) sample of a flooring, tile or fabric product before they buy the real thing.

Made for shops that sell by the box, the metre or the roll, where a customer wants to see and feel the product first.

**What shoppers see**

* A "Request a sample" button on the product page. On products with options (a colour or finish), the shopper picks the option first.
* The sample appears in the cart, at checkout, in the order and in the emails as its own line marked **Sample: Yes**, always quantity 1.
* Clear messages when a limit is reached.

**What you control** (WooCommerce > Settings > Sample Shelf, and each product's General tab)

* Switch samples on for every simple and variable product, or only for the products you choose.
* Sample price: free, or a fixed price (set for the whole shop or per product).
* Maximum number of samples per order. Each product (or variation) can be sampled once per order.
* Optional: samples reduce stock when the order is placed (off by default).
* A shipping class for samples, so a small sample is not charged the freight of a full pack. Samples always count as weightless.
* Your own button text.

**Built to be safe**

* The price, the quantity and the limits are always decided on your server. Nothing sent by the browser can change them.
* Works with the block and the classic cart and checkout, and with High-Performance Order Storage.
* No tracking, no ads, no external requests.
* Works together with Cut & Pack for WooCommerce (version 1.2 or newer): a sample of a made-to-measure product needs no size.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Sample Shelf for WooCommerce.
3. Go to WooCommerce > Settings > Sample Shelf, or open a product and use the "Sample" fields on its General tab.

== Frequently Asked Questions ==

= Does a sample reduce my stock? =

Not by default. When you turn on "Samples reduce the product's stock", each sample takes one unit when the order is placed. Samples cannot be requested for products that are out of stock. While a sample sits in the cart, WooCommerce still counts it like any other item, so a shopper who wants every remaining unit plus a sample is told there is not enough stock.

= Can people order unlimited free samples? =

Each order is limited (5 samples by default) and each product can be sampled once per order. Version 1.0 does not limit how many orders one person can place. If free samples are abused, set a small sample price or a sample shipping class with a shipping cost.

= Do coupons apply to samples? =

Percent and fixed-product coupons never apply to a sample. A fixed-cart coupon is applied by WooCommerce to the cart as a whole, so it can still lower a paid sample.

= Which products are supported? =

Simple and variable products. Grouped and external products are not supported.

= How is the sample price taxed? =

Like your other prices: enter it with or without tax the same way you enter product prices.

== Screenshots ==

1. The Request a sample button on a product page.
2. A sample in the cart, marked Sample: Yes.
3. The Sample Shelf settings.

== Changelog ==

= 1.0.0 =
* First release: a "Request a sample" button for simple and variable products, free or low-cost samples, limits per order, a sample shipping class, optional stock use, settings screen and per-product options, block and classic cart and checkout, HPOS, and Cut & Pack 1.2 support.

== Upgrade Notice ==

= 1.0.0 =
First release.
