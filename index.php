<?php

/*
Plugin Name:	WooCommerce Reviews.io Integration
Plugin URI:		http://probion.com/
Description:	Integrate the Reviews.io with the WooCommerce store
Version:		1.0.0
Author:			ProBion
Author URI:		http://probion.com/
License:		GPL-2.0+
License URI:	http://www.gnu.org/licenses/gpl-2.0.txt
*/

namespace RIO;

include __DIR__ . '/classes/base.php';
include __DIR__ . '/classes/woo.php';
include __DIR__ . '/classes/admin.php';
include __DIR__ . '/classes/logs.php';
include __DIR__ . '/classes/api.php';

$sync = Base::getInstance();

$sync->init();