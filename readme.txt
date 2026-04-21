=== UPI SmartPay for WooCommerce ===

Contributors:      iampmpksamy
Tags:              woocommerce, upi, qr code, payment gateway, india
Requires at least: 5.8
Tested up to:      6.6
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Smart UPI QR payment gateway for WooCommerce. Accept UPI payments without third-party fees or API keys.

== Description ==

**UPI SmartPay** adds a complete UPI payment flow to your WooCommerce store. Customers scan a QR code or copy the UPI ID, pay in any UPI app, then confirm with a UTR number. Store admins verify and approve with a single click.

No payment aggregator. No per-transaction fees. No API keys required.

= Key Features =

* Auto-generated UPI QR code per order (pre-filled amount + order reference)
* UPI deep-link button for one-tap payment on Android and iOS
* Copyable UPI ID with visual confirmation
* Support for multiple UPI IDs — random or priority selection per order
* Customisable step-by-step payment instructions
* UTR / transaction reference number input
* Optional payment screenshot upload (JPEG / PNG / WebP, max 5 MB)
* Real-time order status polling every 15 seconds
* QR code tap-to-zoom lightbox on mobile
* One-click Approve / Reject from the WooCommerce order page
* Admin email notification when a customer confirms payment
* UPI Status column on the orders list
* WooCommerce HPOS (High-Performance Order Storage) compatible
* Full translation support with .pot file included
* Integrated debug logging via WooCommerce Status → Logs

= Privacy Notice =

QR codes are generated using the Google Charts API. The UPI payment string (UPI ID, merchant name, amount) is sent as a URL parameter to `chart.googleapis.com` for rendering. No data is permanently stored by this service. To use a self-hosted QR service, use the `pmpksamy_qr_image_url` filter.

= Minimum Requirements =

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+

= Developed By =

Maalig (iampmpksamy) — https://www.pmpksamy.com

== Installation ==

= From the WordPress admin =

1. Go to **Plugins → Add New**.
2. Search for **UPI SmartPay for WooCommerce**.
3. Click **Install Now**, then **Activate**.
4. Go to **WooCommerce → Settings → Payments**.
5. Click **Manage** next to **UPI SmartPay**.
6. Enter at least one UPI ID and adjust settings.
7. Click **Save changes**.

= Manual installation =

1. Download the plugin ZIP.
2. Upload and extract to `wp-content/plugins/upi-smartpay/`.
3. Activate via **Plugins → Installed Plugins**.
4. Configure via **WooCommerce → Settings → Payments → UPI SmartPay → Manage**.

== Frequently Asked Questions ==

= Which UPI apps are supported? =

Any BHIM-UPI compliant app: Google Pay, PhonePe, Paytm, Amazon Pay, BHIM, and all bank-issued UPI apps. The QR code uses the standard `upi://pay` intent format.

= Can I use multiple UPI IDs? =

Yes. Enter one UPI VPA per line in the settings. Choose **Random** (different one per order) or **Priority** (always use the first entry).

= Is payment verified automatically? =

No. This gateway uses manual verification. After a customer pays and confirms, you receive an email and can approve the order from the WooCommerce order page. This gives you full control without a payment aggregator integration.

= Where are screenshots stored? =

In `wp-content/uploads/pmpksamy-upi-screenshots/`. The directory is protected with `.htaccess` (deny from all). Screenshots are only accessible through a secured admin AJAX action.

= Does this work with WooCommerce HPOS? =

Yes. Full HPOS compatibility is declared and all order meta uses the WooCommerce Order API.

= How do I enable debug logging? =

Enable **Debug Logging** in gateway settings. Logs appear under **WooCommerce → Status → Logs**, source `upi-smartpay`.

= My QR code is not loading =

QR codes are rendered by the Google Charts API. Ensure your server has outbound internet access. You can use the `pmpksamy_qr_image_url` filter to substitute a self-hosted QR rendering service.

= Can I customise the thank-you page UI? =

Override `public/templates/thankyou.php` in your theme's `woocommerce/` folder, or use the `pmpksamy_upi_intent_string` and `pmpksamy_qr_image_url` filters.

== Screenshots ==

1. Checkout — UPI SmartPay payment option with supported app badges.
2. Thank-You Page — QR code, UPI ID copy button, instructions, UTR input, screenshot upload.
3. Admin Order Detail — UPI metadata with Approve / Reject buttons.
4. Orders List — UPI Status column.
5. Gateway Settings Page — branded settings panel.

== Changelog ==

= 1.0.0 =
* Initial release.
* UPI QR code generation on the thank-you page.
* Multiple UPI ID support with random/priority selection.
* UTR input and optional screenshot upload.
* Admin Approve / Reject workflow.
* WooCommerce HPOS compatibility.
* Full i18n / translation support.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
