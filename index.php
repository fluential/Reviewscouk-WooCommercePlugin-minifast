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

include __DIR__ . '/base.php';
include __DIR__ . '/admin.php';
include __DIR__ . '/logs.php';
include __DIR__ . '/api.php';

$sync = RIO_Base::getInstance();

$sync->init();