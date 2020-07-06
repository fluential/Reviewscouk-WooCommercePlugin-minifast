## Reviews.co.uk & Reviews.io WooCommerce Integration Plugin

This plugin integrates the merchant and product reviews on reviews.co.uk & reviews.io with a WooCommerce store eliminating slow js requests.

## Rationale
The original plugin "Reviewscouk WooCommerce" creates a massive cascade of javascript requests calling everytime APIs refreshing reviews -- slowing down website in avg ~2s.
This is it nos acceptable for ecommerce sites where 2s is often the max time allowed.

## Features
This plugin pulls all product and store reviews via cron mechanism into a database, the data is rendered serverside eliminating slow js.
* Backend reviews sync via cron
* Merchant and product rich snippets on per url basis


### Installation

Step 1: Go to the Plugins section of your Wordpress installation

Step 2: Click 'Add New' and select Upload Plugin.

Step 3: Upload this plugins ZIP file.

Step 4: Activate the Plugin

### Configuration

Step 1: Go to Settings > Reviews.co.uk

Step 2: Enter your Reviews.co.uk API Credentials and Configure all settings.

Step 3: Full Instructions for integration can be found on the Reviews.co.uk Dashboard at Company Setup > Automated Review Collection > WooCommerce.

### Usage

Invitations will automatically be sent when the status of your order is set to Complete.

### Support

If you have any enquiries just open a new issue
