<div align="center">
  <img src="./logo-tripay.png" alt="Tripay for FOSSBilling">
  <h1>Tripay Integration for FOSSBilling</h1>
  <img src="https://img.shields.io/github/v/release/cakadi190/Tripay-FOSSBilling?include_prereleases&sort=semver&display_name=release&style=flat">
  <img src="https://img.shields.io/github/downloads/cakadi190/Tripay-FOSSBilling/total?style=flat">
  <img src="https://img.shields.io/github/repo-size/cakadi190/Tripay-FOSSBilling">
  <img alt="GitHub" src="https://img.shields.io/github/license/cakadi190/Tripay-FOSSBilling?style=flat">
</div>

## Overview
Provide your [FOSSBilling](https://fossbilling.org) customers with a variety of payment options, including Virtual Accounts, E-Wallets, Retail Outlets, and more through [Tripay](https://tripay.co.id).

> **Note**
> Warning This extension, like FOSSBilling itself is under active development but is currently very much beta software. This means that there may be stability or security issues and it is not yet recommended for use in active production environments!

## Table of Contents
- [Overview](#overview)
- [Table of Contents](#table-of-contents)
- [Installation](#installation)
  - [1). Extension directory](#1-extension-directory)
  - [2). Manual installation](#2-manual-installation)
- [Configuration](#configuration)
  - [Webhook Configuration](#webhook-configuration)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Features](#features)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)

## Installation

### 1). Extension directory
The easiest way to install this extension is by using the [FOSSBilling extension directory](https://extensions.fossbilling.org/extension/Tripay).
### 2). Manual installation
1. Download the latest release from the [GitHub repository](https://github.com/cakadi190/Tripay-FOSSBilling/releases)
2. Create a new folder named **Tripay** in the **/library/Payment/Adapter** directory of your FOSSBilling installation
3. Extract the archive you've downloaded in the first step into the new directory
4. Go to the "**Payment gateways**" page in your admin panel (under the "System" menu in the navigation bar) and find Tripay in the "**New payment gateway**" tab
5. Click the *cog icon* next to Tripay to install and configure Tripay

## Configuration
1. Access Tripay Settings: In your FOSSBilling admin panel, find "**Tripay**" under "**Payment gateways.**"
2. Enter API Credentials: Input your Tripay `API Key`, `Private Key`, and `Merchant Code`. You can obtain these from your Tripay dashboard at Profile > API & Callback.
3. Configure Preferences: Customize settings like sandbox mode and logging as needed.
4. Save Changes: Remember to update your configuration.
5. Test Transactions: It's recommended to test your gateway integration through a payment process in sandbox mode before going live.
6. Go Live: Switch to live mode to start accepting real payments once testing is complete.


### Callback Configuration

To set up callbacks:

1. Log in to your Tripay dashboard.
2. Navigate to Profile > API & Callback.
3. Add your callback URL:
   `https://your-fossbilling-domain.com/ipn.php?gateway_id=payment_gateway_id`
   (Replace `your-fossbilling-domain.com` with your actual domain and `payment_gateway_id` with the ID assigned by FOSSBilling)
4. Tripay will automatically verify callbacks using HMAC signature with your Private Key.



## Usage
Once installed and configured, Tripay will appear as a payment option during the checkout process. The module handles various payment statuses including successful payments, pending transactions, expired payments, and failed attempts.

## Troubleshooting

- Check the logs at `library/Payment/Adapter/Tripay/logs/tripay.log` for detailed information on transactions and errors.
- Ensure your server can reach Tripay's API endpoints (https://tripay.co.id/api or https://tripay.co.id/api-sandbox).
- Verify that the API Key, Private Key, and Merchant Code are correctly entered in the FOSSBilling configuration.
- If you encounter timezone-related issues, check your php.ini configuration or server settings.
- Make sure your callback URL is accessible from the internet for Tripay to send payment notifications.

## Features

- [x] Using Tripay Closed Payment (https://tripay.co.id/developer)
- [x] Automatic invoice status update to 'paid' upon successful payment
- [x] Activate service automatically after payment confirmation
- [x] Comprehensive handling of various payment statuses (PAID, EXPIRED, UNPAID, FAILED, REFUND)
- [x] Secure callback verification using HMAC-SHA256 signature
- [x] Support for multiple payment channels (Virtual Accounts, E-Wallets, Retail Outlets)
- [x] Detailed transaction logging for easy tracking and debugging
- [x] Sandbox mode for testing before going live


## Contributing
I welcome contributions to enhance and improve this integration module. If you'd like to contribute, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bugfix: `git checkout -b feature-name`.
3. Make your changes and commit them with a clear and concise commit message.
4. Push your branch to your fork: `git push origin feature-name` and create a [pull request](https://github.com/cakadi190/Tripay-FOSSBilling/pulls).

## License
This FOSSBilling Tripay Payment Gateway Integration module is open-source software licensed under the [Apache License 2.0](LICENSE).

> *Note*: This module is not officially affiliated with [FOSSBilling](https://fossbilling.org) or [Tripay](https://tripay.co.id). Please refer to their respective documentation for detailed information on FOSSBilling and Tripay.


## Support

For issues related to this adapter, please open an issue.

For Tripay-specific issues, please contact Tripay support at https://tripay.co.id.
