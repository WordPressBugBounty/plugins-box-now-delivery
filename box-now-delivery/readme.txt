=== BOX NOW Delivery ===
Contributors: boxnow
Tags: delivery, boxnow
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 3.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

BOX NOW the future of parcel delivery.

== Description ==

BOX NOW Delivery is used for connecting e-shops with parcel delivery services from BOX NOW.

== Changelog ==

= 3.4.0 =
*Save locker names, addresses, and postal codes with orders.
*Displayed locker details in admin order page.
*Fixed Thank You page locker selection update.
*Updated WordPress compatibility to 7.1.

= 3.3.2 =
*GPS Functionality improvements.
*Added handling for fixed product coupons codes.
*Improved resiliency to conflicts with other plugins relusting from race conditions of admin-ajax.php.
*Restricted COD logic and direct global cart deferences to checkout requests only to prevent fatal errors  via calling get() on null sessions.
*Improved API call error descriptions for most common error types.


= 3.3.1 =
*Prevented admin AJAX nonce conflicts with other plugins by giving BOX NOW voucher requests a dedicated JavaScript configuration object.
*Improved voucher admin compatibility by allowing limited order managers with `edit_shop_orders` to create, print, and cancel vouchers.
*Added safer voucher cancellation controls, including a confirmed "Cancel All Vouchers" action for orders with multiple BOX NOW vouchers.
*Improved multi-parcel cancellation handling across `_boxnow_parcel_ids` and `_boxnow_parcel_id`.
*Restricted voucher/admin scripts to relevant WooCommerce order screens and cleaned up script handles.
*Improved compatibility with Stripe, Apple Pay, Google Pay, PayPal, and WooCommerce express checkout flows by validating BOX NOW locker selection server-side.
*Hardened BOX NOW admin order API handling, voucher print/cancel flows, and order cancellation request handling.
*Declared compatibility with WooCommerce High-Performance Order Storage (HPOS).
*Added Slovenia support to the BOX NOW locker widget.
*Refreshed the plugin settings interface and added a locally hosted BOX NOW logo.
*Added a shipping phone fallback when the billing phone is unavailable.
*Improved Thank You page translations and fixed delayed locker cleanup after checkout.
*Improved compatibility with block themes by replacing deprecated theme detection.
*Reduced unnecessary autoloaded data by migrating legacy parcel options.
*Standardized the size and alignment of voucher actions on WooCommerce order screens.

= 3.2.1 =
*Hotfix: improved asset versioning for plugin scripts and styles to reduce stale-cache issues after update, including WooCommerce admin voucher printing.

= 3.2.0 =
*Security hardening for checkout, admin order updates, voucher actions, and thank you page locker updates.
*Plugin Check cleanup for escaping, sanitization, text domains, direct access protection, and release metadata.
*CVE-2026-24571 Patched.
*MAP GPS Functionality patch.
*Blocks type checkout functionality updates.
*CoD Title and Description logic patches.
*General Plugin fixups on translations and robustness.

= 3.1.1 =
*BOX NOW shipping method: Max Package Dimensions have been migrated to centimeters (cm). 
Max Package Dimensions values and unit are now fixed and can no longer be edited in BOX NOW shipping method.
*Use order id as prefix in delivery requests, both manual and automatic.
*Admin: Use WooCommerce order notes to display BOX NOW feedback to user.

== Upgrade Notice ==

= 3.3.1 =
Recommended update with an admin AJAX compatibility fix, voucher management fixes, safer multi-voucher cancellation, express checkout improvements, HPOS compatibility, Slovenia widget support, and an updated settings interface.

= 3.2.1 =
Hotfix release for stale-cache issues after update, including WooCommerce admin voucher printing.

= 3.2.0 =
Recommended update with security hardening, Plugin Check cleanup, and release metadata alignment.

= 3.1.1 =
Please upgrade BOX NOW plugin to the latest version to avoid conflicts and errors of older versions.
