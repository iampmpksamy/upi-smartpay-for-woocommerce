# UPI SmartPay for WooCommerce

> Smart UPI QR payment gateway for WooCommerce — accept UPI payments without third-party fees or API keys.

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://wordpress.org/plugins/upi-smartpay/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588a.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

**Developed by [Maalig (iampmpksamy)](https://www.pmpksamy.com)**
🌐 Website: [pmpksamy.com](https://www.pmpksamy.com)
📱 Social Hub: [iampmpksamy.social](https://www.iampmpksamy.social)

---

## Overview

UPI SmartPay eliminates the need for a payment aggregator for UPI collections. Customers pay via QR code or UPI ID, confirm with a UTR number, and optionally upload a screenshot. Store admins verify and approve with one click — no third-party fees, no API keys.

---

## Features

| Feature | Detail |
|---|---|
| QR Code | Per-order QR with pre-filled amount and order ref |
| Multiple UPI IDs | Random or priority selection |
| Mobile Deep-link | Opens UPI app directly on Android/iOS |
| Copy UPI ID | Clipboard copy with visual feedback |
| Payment Instructions | Fully customisable step list |
| UTR Capture | Customer enters transaction reference |
| Screenshot Upload | Drag & drop, JPEG/PNG/WebP, 5 MB max |
| Status Polling | Auto-refreshes every 15 s |
| QR Lightbox | Tap-to-zoom on mobile |
| Admin Approve/Reject | One-click with optional reason |
| Orders List Column | UPI Status per order |
| Admin Email Alert | Notified on customer confirmation |
| HPOS Compatible | WooCommerce High-Performance Order Storage |
| Debug Logging | WooCommerce native logger |
| i18n Ready | `.pot` file included |

---

## Requirements

- **WordPress** 5.8+
- **WooCommerce** 6.0+
- **PHP** 7.4+

---

## Installation

### Via WordPress Admin

```
Plugins → Add New → Search "UPI SmartPay" → Install → Activate
WooCommerce → Settings → Payments → UPI SmartPay → Manage
```

### Manual

```bash
cd wp-content/plugins/
git clone https://github.com/iampmpksamy/upi-smartpay.git upi-smartpay
```

Activate from **Plugins → Installed Plugins**, then configure.

---

## Configuration

| Setting | Description |
|---|---|
| **Enable** | Toggle gateway on/off |
| **Title** | Shown at checkout |
| **Description** | Sub-title at checkout |
| **Merchant Name** | Shown inside UPI apps |
| **UPI IDs** | One VPA per line (e.g. `shop@upi`) |
| **Selection Mode** | Random or Priority |
| **Payment Instructions** | Step list on thank-you page |
| **Screenshot Upload** | Enable/disable customer screenshot |
| **Debug Logging** | Logs to WC Status → `upi-smartpay` |

---

## File Structure

```
upi-smartpay/
├── pmpk-upi-smartpay.php            # Bootstrap singleton (Plugin Name header here)
├── includes/
│   ├── class-pmpk-gateway.php       # PMPKSAMY_UPI_Gateway (WC_Payment_Gateway)
│   ├── class-pmpk-ajax.php          # PMPKSAMY_UPI_Ajax — all AJAX endpoints
│   ├── class-pmpk-qr-generator.php  # PMPKSAMY_QR_Generator
│   └── class-pmpk-helper.php        # PMPKSAMY_Helper — static utilities
├── admin/
│   └── class-pmpk-admin.php         # PMPKSAMY_UPI_Admin — order UI
├── public/
│   ├── class-pmpk-public.php        # PMPKSAMY_UPI_Public — asset loader
│   ├── templates/
│   │   └── thankyou.php             # Payment panel template
│   ├── branding-page.html           # Plugin landing page
│   └── portfolio.html               # Developer portfolio page
├── assets/
│   ├── js/
│   │   ├── pmpk-frontend.js         # Copy, upload, confirm, polling
│   │   └── pmpk-admin.js            # Approve/Reject AJAX
│   ├── css/
│   │   ├── pmpk-frontend.css        # Mobile-first payment panel
│   │   └── pmpk-admin.css           # Admin badges, meta box, branding
│   └── images/
│       └── upi-logo.svg             # Checkout icon
├── languages/
│   └── upi-smartpay.pot             # Translation template
├── .github/workflows/
│   └── release.yml                  # CI: lint → PHPCS → ZIP → GitHub Release
├── readme.txt                       # WordPress.org readme
└── README.md                        # This file
```

---

## Order Flow

```
Customer checkout
       │
       ▼
Order: On Hold  ·  _pmpksamy_payment_status = pending
       │
       ▼  Customer pays + clicks "I Have Paid"
       │
       ▼
Order: On Hold  ·  _pmpksamy_payment_status = confirmed
       │             Admin email sent
       │
       ├── Admin Approves → payment_complete()  → Processing
       │                     status = approved
       │
       └── Admin Rejects  → Status: Cancelled
                             status = rejected
```

---

## Developer Filters

| Filter | Description | Parameters |
|---|---|---|
| `pmpksamy_upi_intent_string` | Modify the `upi://pay?...` string | `$string`, `$params` |
| `pmpksamy_qr_image_url` | Replace QR image URL | `$url`, `$data`, `$size` |
| `pmpksamy_upi_icon` | Replace checkout gateway icon | `$url` |

### Example: self-hosted QR service

```php
add_filter( 'pmpksamy_qr_image_url', function( $url, $data, $size ) {
    return 'https://your-qr-server.com/qr?data=' . rawurlencode( $data ) . '&size=' . $size;
}, 10, 3 );
```

---

## Screenshots

| # | Description |
|---|---|
| 1 | Checkout page — UPI payment option |
| 2 | Thank-you page — QR code + instructions + UTR input |
| 3 | Admin order detail — UPI metadata + Approve/Reject |
| 4 | Orders list — UPI Status column |
| 5 | Settings page — branded admin panel |

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/your-feature`.
3. Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
4. Open a pull request against `main`.

---

## Security

Found a vulnerability? Email **security@pmpksamy.com** before public disclosure. Response within 48 hours.

---

## Changelog

### 1.0.0
- Initial public release.

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)

---

<p align="center">
  Developed by <a href="https://www.pmpksamy.com"><strong>Maalig (iampmpksamy)</strong></a>
  &nbsp;·&nbsp;
  <a href="https://www.iampmpksamy.social">🌐 Social Hub</a>
</p>
