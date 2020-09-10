<?php

/*
Plugin Name:    WooCommerce Reviews.io Integration
Plugin URI:     https://github.com/fluential/Reviewscouk-WooCommercePlugin-minifast
Description:    Built for speed WooCommerce Plugin for Reviews.io & Reviews.co.uk
Text Domain:    rio
Version:        1.0.5
Author:		fluential
License:	Apache-2.0
License URI:	http://www.apache.org/licenses/LICENSE-2.0
*/

namespace RIO;

define('RIO_DIR', __DIR__);
define('RIO_URL', plugin_dir_url(__FILE__));

include __DIR__ . '/classes/base.php';
include __DIR__ . '/classes/woo.php';
include __DIR__ . '/classes/admin.php';
include __DIR__ . '/classes/logs.php';
include __DIR__ . '/classes/api.php';

$sync = Base::getInstance();

$sync->init();
