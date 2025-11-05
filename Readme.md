# QuickPay PrestaShop Module

QuickPay adds payment options to your PrestaShop store through the QuickPay gateway. 
The module installs like any other PrestaShop payment extension and exposes configuration for API credentials, payment methods, and callback handling inside the back office.

## Supports
- Prestashop 1.6, 1.7 & 8.x with PHP 7.2+

## Requirements
- PrestaShop 1.6, 1.7 or 8 with PHP 7.2+
- Active QuickPay merchant account with API key and agreement ID
- TLS 1.2 support on the hosting environment for secure callbacks

## Installation
1. Download `quickpay.zip` from this repository.
2. In the PrestaShop back office, navigate to `Modules > Module Manager > Upload a module` and upload the archive.
3. Enable **QuickPay** and open the module configuration screen to supply API credentials and payment settings.

## Configuration & Usage
- Enter the QuickPay API key, private key, and agreement ID provided in your QuickPay dashboard.
- Choose accepted card types, default capture settings, and optional payment overlays.
- Ensure the callback/notification URL in QuickPay matches the module endpoint shown in the settings panel.
- Test a transaction using QuickPay’s test card numbers before going live.

## Support & Development
- For merchant support, contact support@quickpay.net.
- Developers can review hooks and controllers under `quickpay.php` and `controllers/front`. Use `php -l` for syntax validation and install into a staging shop to exercise payment, cancel, and callback flows prior to release.
