## Reviews.co.uk & Reviews.io WooCommerce Plugin

This plugin integrates the merchant and product reviews on reviews.co.uk & reviews.io with a WooCommerce store eliminating slow js requests.

## Rationale
The original plugin "Reviewscouk WooCommerce" creates a massive cascade of javascript requests calling everytime APIs refreshing reviews -- slowing down website in avg ~2s.
This is it nos acceptable for ecommerce sites where 2s is often the max time allowed.

## Features
This plugin pulls all product and store reviews via cron mechanism into a database, the data is rendered serverside eliminating slow js.
* Backend reviews sync via cron
* Merchant and product rich snippets on per url basis
