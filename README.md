## Reviews.co.uk & Reviews.io WooCommerce Integration Plugin

This plugin integrates the merchant and product reviews on reviews.co.uk & reviews.io with a WooCommerce store eliminating slow js requests.

## Rationale
The original plugin "Reviewscouk WooCommerce" creates a massive cascade of javascript requests calling everytime APIs refreshing reviews -- slowing down website in avg ~2s.
This is it nos acceptable for ecommerce sites where 2s is often the max time allowed.

## Features
This plugin pulls all product and store reviews via cron mechanism into a database, the data is rendered serverside eliminating slow js.
* Backend reviews sync via cron
* Merchant and product rich snippets on per url basis
* System Logs
* No Automated review collections -- use alternatives
* Small size 26 KiB zip / 96 KiB uncompressed


### Installation

Step 0: Download repo master .zip

Step 1: Go to the Plugins section of your Wordpress installation

Step 2: Click 'Add New' and select Upload Plugin.

Step 3: Upload this plugins ZIP file.

Step 4: Activate the Plugin

### Configuration

Step 1: Go to Settings > Reviews.io

Step 2: Enter your Reviews.io/Reviews.co.uk API Credentials and Configure all settings.

### Usage
Rich snippets as well as product reviews will be automatically generated on product pages and selected urls (for merchan rich snippets)

### Support

If you have any enquiries just open a new issue

## Contributors
[Toniievych Andrii](https://github.com/TwistedAndy)
