<?php

namespace RIO;

class Base {

	public static $instance = false;

	public $id = 'rio';

	public $api;

	public $log;

	public $woo;

	public $admin;

	public $settings = array();

	public $type = 'rio_review';

	public $event = 'rio_sync_event';


	protected function __construct() {

		$this->api = new API($this);

		$this->log = new Logs($this);

		$this->woo = new Woo($this);

		$this->admin = new Admin($this);

	}


	public static function getInstance() {

		if (empty(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;

	}


	public function init() {

		// Add a cron event on plugin activation
		register_activation_hook(__FILE__, array($this, 'cronActivate'));

		// Clear all related cron event on plugin deactivation
		register_deactivation_hook(__FILE__, array($this, 'cronDeactivate'));

		// Add custom time intervals
		add_filter('cron_schedules', array($this, 'cronIntervals'));

		// Trigger synchronization on cron event
		add_action($this->event, array($this, 'startSync'));

		// Trigger synchronization on request
		add_action('init', function() {

			if (isset($_GET['sync-run'])) {
				$this->startSync();
				$this->cronDeactivate();
				$this->cronActivate();
			}

			register_post_type($this->type, array(
				'labels' => array(
					'name' => 'Reviews',
					'singular_name' => 'Review',
					'new_item' => 'New Review',
					'add_new' => 'Add Review',
					'add_new_item' => 'Add New Review',
					'edit_item' => 'Edit Review',
					'view_item' => 'View Review',
					'all_items' => 'All Reviews',
					'search_items' => 'Search Reviews',
					'not_found' => 'Reviews not found',
					'not_found_in_trash' => 'Reviews not found in trash',
				),
				'description' => 'Reviews.io Customer Reviews',
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'show_in_nav_menus' => true,
				'show_in_admin_bar' => true,
				'menu_position' => 10,
				'menu_icon' => 'dashicons-format-chat',
				'hierarchical' => false,
				'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
				'has_archive' => false,
				'rewrite' => array('slug' => 'reviews', 'with_front' => true, 'hierarchical' => false),
				'query_var' => false,
				'taxonomies' => array()
			));

		}, 100);

		$this->woo->init();
		$this->admin->init();

	}


	public function startSync() {

		$time = time();

		$key = $this->id . '_sync_started';

		$offset = intval(get_option('gmt_offset') * HOUR_IN_SECONDS);

		$sync_started = intval(get_option($key, 0));

		if (true or $time - $sync_started > 5 * 60) {

			update_option($key, $time);

			$this->log->info('Sync started on <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$products = $this->getProductIds();

			$parents = $this->getParentProducts();

			$existing = $this->getExistingReviewIds();

			$reviews = $this->api->getAllProductReviews();

			$this->log->info('Total products count: <b>' . count($products) . '</b>');

			$this->log->info('Product reviews count: <b>' . count($reviews) . '</b>');

			if ($reviews and $products) {

				foreach ($products as $sku => $product_id) {

					if (!empty($reviews[$sku]) and is_array($reviews[$sku])) {

						$rating = 0;
						$count = count($reviews[$sku]);

						// We should use the main product ID instead of variation ID

						if (!empty($parents[$product_id])) {
							$product_id = intval($parents[$product_id]);
						} else {
							$product_id = intval($product_id);
						}

						foreach ($reviews[$sku] as $review) {

							$rating += intval($review['rating']);

							if (empty($existing[$review['product_review_id']])) {

								$args = array(
									'post_title' => 'Review #' . $review['product_review_id'],
									'post_type' => $this->type,
									'post_status' => 'publish',
									'post_content' => $review['review'],
									'meta_input' => array(
										'review_product' => $product_id,
										'review_sku' => $sku,
										'review_id' => $review['product_review_id'],
										'review_rating' => intval($review['rating']),
										'review_order' => $review['order_id'],
										'review_date' => $review['date_created'],
										'review_first_name' => $review['reviewer']['first_name'],
										'review_last_name' => $review['reviewer']['last_name'],
										'review_verified' => ($review['reviewer']['verified_buyer'] == 'yes') ? 1 : 0,
										'review_gravatar' => $review['reviewer']['gravatar'],
										'review_address' => $review['reviewer']['address'],
										'review_photo' => strval($review['reviewer']['photo']),
									)
								);

								$post_id = wp_insert_post($args);

								if ($post_id instanceof \WP_Error) {
									$this->log->error('Failed to add a review: <b>' . $post_id->get_error_message() . '</b>');
								} else {
									$this->log->info('Added a new review: <b>#' . $review['product_review_id'] . '</b>');
								}

							}

						}

						$rating = round($rating / $count, 2);

						update_post_meta($product_id, 'rating_count', $count);
						update_post_meta($product_id, 'rating_value', $rating);

					}

				}

			}

			$this->log->info('Product reviews sync finished on <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$reviews = $this->api->getAllMerchantReviews();

			$this->log->info('Merchant reviews count: <b>' . count($reviews) . '</b>');

			if ($reviews) {

				$rating = 0;
				$count = count($reviews);

				foreach ($reviews as $review_id => $review) {

					$rating += intval($review['rating']);

					if (empty($existing[$review_id])) {

						$args = array(
							'post_title' => 'Store Review #' . $review['store_review_id'],
							'post_type' => $this->type,
							'post_status' => 'publish',
							'post_content' => $review['comments'],
							'meta_input' => array(
								'review_product' => 0,
								'review_sku' => 'store',
								'review_id' => $review['store_review_id'],
								'review_rating' => intval($review['rating']),
								'review_order' => 0,
								'review_user_id' => $review['user_id'],
								'review_date' => $review['date_created'],
								'review_first_name' => $review['reviewer']['first_name'],
								'review_last_name' => $review['reviewer']['last_name'],
								'review_verified' => ($review['reviewer']['verified_buyer'] == 'yes') ? 1 : 0,
								'review_gravatar' => '',
								'review_address' => $review['reviewer']['address'],
							)
						);

						$post_id = wp_insert_post($args);

						if ($post_id instanceof \WP_Error) {
							$this->log->error('Failed to add a review: <b>' . $post_id->get_error_message() . '</b>');
						} else {
							$this->log->info('Added a new merchant review: <b>#' . $review['store_review_id'] . '</b>');
						}

					}

				}

				$rating = round($rating / $count, 2);

				update_option($this->id . '_rating_count', $count);
				update_option($this->id . '_rating_value', $rating);

			}

			$this->log->info('Merchant reviews sync finished on <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$this->log->save();

		}

	}


	public function getDb() {

		global $wpdb;

		if ($wpdb instanceof \wpdb) {

			return $wpdb;

		} else {

			$dbuser = defined('DB_USER') ? DB_USER : '';
			$dbpassword = defined('DB_PASSWORD') ? DB_PASSWORD : '';
			$dbname = defined('DB_NAME') ? DB_NAME : '';
			$dbhost = defined('DB_HOST') ? DB_HOST : '';

			return new \wpdb($dbuser, $dbpassword, $dbname, $dbhost);

		}

	}

	public function getProductIds() {

		$db = $this->getDb();

		$result = $db->get_results("SELECT post_id, meta_value FROM {$db->postmeta} WHERE meta_key = '_sku'", ARRAY_A);

		$data = array();

		if ($result) {
			foreach ($result as $item) {
				if (!empty($item['meta_value']) and !empty($item['post_id'])) {
					$data[$item['meta_value']] = intval($item['post_id']);
				}
			}
		}

		return $data;

	}


	public function getParentProducts() {

		$db = $this->getDb();

		$result = $db->get_results("SELECT ID, post_parent FROM {$db->posts} WHERE post_type = 'product_variation'", ARRAY_A);

		$data = array();

		if ($result) {
			foreach ($result as $item) {
				if (!empty($item['ID']) and !empty($item['post_parent'])) {
					$data[$item['ID']] = intval($item['post_parent']);
				}
			}
		}

		return $data;

	}


	public function getExistingReviewIds() {

		$db = $this->getDb();

		$result = $db->get_results("SELECT post_id, meta_value FROM {$db->postmeta} WHERE meta_key = 'review_id'", ARRAY_A);

		$data = array();

		if ($result) {
			foreach ($result as $item) {
				if (!empty($item['meta_value']) and !empty($item['post_id'])) {
					$data[$item['meta_value']] = intval($item['post_id']);
				}
			}
		}

		return $data;

	}


	public function getReviewsQuery($data = array()) {

		$defaults = array(
			'product_id' => 0,
			'number' => 5,
			'offset' => 0,
			'status' => 'publish',
		);

		$args = wp_parse_args($data, $defaults);

		$array = array(
			'post_type' => $this->type,
			'post_status' => $args['status'],
			'posts_per_page' => intval($args['number']),
		);

		if ($args['product_id'] > 0) {

			$array['meta_query'] = array(
				array(
					'key' => 'review_product',
					'value' => intval($args['product_id']),
					'compare' => '=',
					'type' => 'NUMERIC'
				)
			);

		}

		if ($args['offset']) {
			$array['offset'] = intval($args['offset']);
		}

		return new \WP_Query($array);

	}


	public function getTemplate($template, $data = array()) {

		if ($data) {
			extract($data, EXTR_SKIP);
		}

		$filename = RIO_DIR . '/templates/' . $template . '.php';

		if (file_exists($filename)) {

			include $filename;

		}

	}


	/* Get all settings */

	public function cronDeactivate() {

		wp_clear_scheduled_hook($this->event);

	}

	public function cronActivate() {

		if (!wp_next_scheduled($this->event)) {

			$interval = $this->getSetting('interval');

			if (empty($interval)) {
				$interval = 'hourly';
			}

			$time = time();

			$schedules = wp_get_schedules();

			if (!empty($schedules[$interval])) {
				$schedule = $schedules[$interval];
			} else {
				$schedule = array_shift($schedule);
			}

			if (!empty($schedule['interval'])) {
				$time += intval($schedule['interval']);
			}

			wp_schedule_event($time, $interval, $this->event);

		}

	}

	public function getSetting($name) {

		$settings = $this->getSettings();

		$field = $this->id . '_' . $name;

		if (is_array($settings) and !empty($settings[$field])) {
			$result = $settings[$field]['value'];
		} else {
			$result = get_option($field, false);
		}

		return $result;

	}


	/* Add a cron event on plugin activation */

	public function getSettings() {

		if (empty($this->settings)) {

			$settings = array();

			$fields = array(
				'api_store_id' => array(
					'name' => 'API Store ID',
					'type' => 'text',
					'value' => ''
				),
				'api_key' => array(
					'name' => 'API Key',
					'type' => 'text',
					'value' => ''
				),
				'interval' => array(
					'name' => 'Synchronization Interval',
					'type' => 'select',
					'value' => 'hourly',
					'values' => array(
						'minutes_15' => __('Every 15 minutes'),
						'minutes_30' => __('Every 30 minutes'),
						'hourly' => 'Hourly',
						'twicedaily' => 'Twice a day',
						'daily' => 'Daily',
						'weekly' => 'Weekly'
					),
					'validation' => function($value) {

						$current = get_option($this->id . '_interval');

						$schedules = wp_get_schedules();

						if ($current != $value and !empty($schedules[$value])) {
							wp_clear_scheduled_hook($this->event);
							wp_schedule_event(time(), $value, $this->event);
						}

						return $value;

					}
				),
			);

			foreach ($fields as $field => $value) {

				$field = $this->id . '_' . $field;

				$settings[$field] = $value;

				$result = get_option($field, false);

				if ($result !== false) {

					if ($settings[$field]['type'] == 'checkbox' and !is_array($result)) {
						$result = array();
					}

					$settings[$field]['value'] = $result;

				}

			}

			$this->settings = $settings;

		}

		return $this->settings;

	}


	/* Remove a cron event on plugin deactivation */

	public function updateSetting($name, $value) {

		$field = $this->id . '_' . $name;

		update_option($field, $value);

		if (!empty($this->settings[$field])) {
			$this->settings[$field]['value'] = $value;
		}

	}


	/* Register additional intervals */

	public function cronIntervals($schedules) {

		$schedules['minutes_15'] = array(
			'interval' => 60 * 15,
			'display' => __('Every 15 minutes')
		);

		$schedules['minutes_30'] = array(
			'interval' => 60 * 30,
			'display' => __('Every 30 minutes')
		);

		return $schedules;

	}

}

/*
add_action('wp_ajax_more_reviews', 'reviews_shortcode_function');
add_action('wp_ajax_nopriv_more_reviews', 'reviews_shortcode_function');
add_shortcode('reviews_shortcode', 'reviews_shortcode_function');
function reviews_shortcode_function($atts) {

	$offset = 0;
	if (!empty($atts["product_id"])) {
		$product_ID = $atts["product_id"];
	} else {
		if (isset($_POST['cst_offset'])) {
			$product_ID = $_POST['pro_id'];
			$offset = intval($_POST['cst_offset']);
		} else {
			$product_ID = get_the_ID();
		}
	}
	if (empty($product_ID)) {
		$product_ID = get_the_ID();
	}
	$args = array(
		'post_type' => 'custom_reviews',
		'numberposts' => 5,
		'offset' => $offset,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => 'review_product_id',
				'value' => $product_ID,
				'compare' => '=',
			),
		),
		'order' => 'DESC',
		'orderby' => 'ID',
	);
	//var_dump($args);
	$reviews_ids = get_posts($args);
	$args['numberposts'] = -1;
	$all_reviews_ids = get_posts($args);
	$count_all = count($all_reviews_ids);
	$total_pages = ceil($count_all / 5);
	$productRating = get_post_meta($product_ID, "product_stats_average", true);
	if (!empty($productRating)) {
		$one_decimal_place = number_format($productRating, 1);
		$the_float_value = floatval($one_decimal_place);
	}
	//var_dump($total_pages);
	$review_count = count($reviews_ids);
	$average_rating_star_1 = getStarClass($productRating, 1);
	$average_rating_star_2 = getStarClass($productRating, 2);
	$average_rating_star_3 = getStarClass($productRating, 3);
	$average_rating_star_4 = getStarClass($productRating, 4);
	$average_rating_star_5 = getStarClass($productRating, 5);
	if (!isset($_POST['cst_offset'])) {
		$out = '<div class="ratingLogoWrap cfx">
				<div class="overallStatsWrap" ng-show="count > 0">
				<div class="overallRating ng-binding">
				
				' . number_format((float) $productRating, 1, '.', '') . '
				</div>
				<div class="overallStarsWrap">
				<div class="starsWrap ng-binding">
					<span class="fa fa-star ' . $average_rating_star_1 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_2 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_3 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_4 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_5 . '"></span>
				</div>
				<div class="numReviews ng-binding">
				' . $review_count . ' Reviews
				</div>
				</div>
				</div>

				</div>';
	}
	if (!isset($_POST['cst_offset'])) {
		$out .= '<div class="cst_review_cvr">';
	}
	if (!empty($reviews_ids) && $review_count > 0) {
		foreach ($reviews_ids as $reviews_id) {

			$reviewer_first_name = get_post_meta($reviews_id->ID, "reviewer_first_name", true);
			$reviewer_last_name = get_post_meta($reviews_id->ID, "reviewer_last_name", true);
			$reviewer_verified_buyer = get_post_meta($reviews_id->ID, "reviewer_verified_buyer", true);
			$review_review = get_post_meta($reviews_id->ID, "review_review", true);
			$review_timeago = get_post_meta($reviews_id->ID, "review_timeago", true);
			$review_rating = get_post_meta($reviews_id->ID, "review_rating", true);
			$reviewer_gravatar = get_post_meta($reviews_id->ID, "reviewer_gravatar", true);
			$gravatar_url = get_avatar_url($reviewer_gravatar);
			$rating_star_1 = getStarClass($review_rating, 1);
			$rating_star_2 = getStarClass($review_rating, 2);
			$rating_star_3 = getStarClass($review_rating, 3);
			$rating_star_4 = getStarClass($review_rating, 4);
			$rating_star_5 = getStarClass($review_rating, 5);
			$out .= '<div class="cst_indi_review">
								<div class="cst_indi_review_meta">
									<div class="cst_indi_review_gravtar">
										<img class="reviewer_img" src="' . $gravatar_url . '" alt="" />';
			if ($reviewer_verified_buyer == "yes") {
				$out .= '<div class="cst_avatar_verifiedBadge">
														<div class="cst_verifiedBadge_wrapper">
															<i class="cst_verified"></i>
														</div>
													</div>';
			}
			$out .= '</div>
									<div class="cst_indi_review_name">
										<p class="cst_indi_review_user_title">' . $reviewer_first_name . ' ' . $reviewer_last_name . '</p>
										<p class="cst_indi_review_user_ver">Verified Buyer: ' . $reviewer_verified_buyer . '</p>
									</div>
									<div class="cst_indi_review_gravtar_ratings">
										<span class="fa fa-star ' . $rating_star_1 . '"></span>
										<span class="fa fa-star ' . $rating_star_2 . '"></span>
										<span class="fa fa-star ' . $rating_star_3 . '"></span>
										<span class="fa fa-star ' . $rating_star_4 . '"></span>
										<span class="fa fa-star ' . $rating_star_5 . '"></span>
									</div>
								</div>
								<div class="cst_indi_review_text_cvr">
								     <div class="cst_indi_review_text">
										<p>';
			if (empty($review_review)) {
				$review_review = "This review has no comments";
			}
			$out .= $review_review . '</p>
									</div>
									<div class="cst_indi_review_time">
										<p>' . $review_timeago . '</p>
									</div>
								</div>
						</div>';

		}
	}
	if (!isset($_POST['cst_offset'])) {
		$out .= '</div>';
		$out .= cst_pagination($total_pages);
	}
	echo $out;
	if (isset($_POST['cst_offset'])) {
		exit;
	}
}


function cst_pagination($page_number, $para_product_ID = "") {

	$cst_pagination_cvr_class = "cst_pagination_cvr";
	if (!empty($para_product_ID)) {
		$cst_pagination_cvr_class = $cst_pagination_cvr_class . '_' . $para_product_ID . ' cst_pagination_cvr_review';
	}
	$out = '<div class="' . $cst_pagination_cvr_class . '"><ul>';
	for ($i = 1; $i <= $page_number; $i++) {
		if ($i == 1) {
			$class = 'class="cst_curr"';
		} else {
			$class = '';
		}

		$out .= '<li ' . $class . '>' . $i . '</li>';
	}
	$out .= '</ul></div>';

	return $out;
}


add_action('admin_menu', 'cst_custom_menu');
function cst_custom_menu() {

	add_menu_page('Get Reviews', 'Get Reviews', 'edit_posts', 'cst_get_reviews', 'cst_callback_function', 'dashicons-media-spreadsheet');
}

add_action('wp_ajax_more_popup_reviews', 'generate_reviews');
add_action('wp_ajax_nopriv_more_popup_reviews', 'generate_reviews');
function generate_reviews($atts, $para_product_ID = "") {

	$offset = 0;
	if (!empty($atts["product_id"])) {
		$product_ID = $atts["product_id"];
	} else {
		if (isset($_POST['cst_offset'])) {
			$product_ID = $_POST['pro_id'];
			$offset = intval($_POST['cst_offset']);
			if ($_POST['ispopupReviews']) {
				$para_product_ID = $product_ID;
			}
		} else {
			$product_ID = get_the_ID();
		}
	}
	if (empty($product_ID)) {
		$product_ID = get_the_ID();
	}
	$args = array(
		'post_type' => 'custom_reviews',
		'numberposts' => 5,
		'offset' => $offset,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => 'review_product_id',
				'value' => $product_ID,
				'compare' => '=',
			),
		),
		'order' => 'DESC',
		'orderby' => 'ID',
	);
	//var_dump($args);
	$reviews_ids = get_posts($args);
	$args['numberposts'] = -1;
	$all_reviews_ids = get_posts($args);
	$count_all = count($all_reviews_ids);
	$total_pages = ceil($count_all / 5);
	$productRating = get_post_meta($product_ID, "product_stats_average", true);
	if (!empty($productRating)) {
		$one_decimal_place = number_format($productRating, 1);
		$the_float_value = floatval($one_decimal_place);
	}
	//var_dump($total_pages);
	$review_count = count($reviews_ids);
	$average_rating_star_1 = getStarClass($productRating, 1);
	$average_rating_star_2 = getStarClass($productRating, 2);
	$average_rating_star_3 = getStarClass($productRating, 3);
	$average_rating_star_4 = getStarClass($productRating, 4);
	$average_rating_star_5 = getStarClass($productRating, 5);
	if (!isset($_POST['cst_offset'])) {
		$out = '<div class="ratingLogoWrap cfx">
				<div class="overallStatsWrap" ng-show="count > 0">
				<div class="overallRating ng-binding">
				
				' . number_format((float) $productRating, 1, '.', '') . '
				</div>
				<div class="overallStarsWrap">
				<div class="starsWrap ng-binding">
					<span class="fa fa-star ' . $average_rating_star_1 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_2 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_3 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_4 . '"></span>
					<span class="fa fa-star ' . $average_rating_star_5 . '"></span>
				</div>
				<div class="numReviews ng-binding">
				' . $review_count . ' Reviews
				</div>
				</div>
				</div>

				</div>';
	}
	if (!isset($_POST['cst_offset'])) {
		$cst_review_class = "cst_review_cvr";
		if (!empty($para_product_ID)) {
			$cst_review_class = $cst_review_class . '_' . $para_product_ID;
		}
		$out .= '<div class="' . $cst_review_class . '">';
	}
	if (!empty($reviews_ids) && $review_count > 0) {
		foreach ($reviews_ids as $reviews_id) {

			$reviewer_first_name = get_post_meta($reviews_id->ID, "reviewer_first_name", true);
			$reviewer_last_name = get_post_meta($reviews_id->ID, "reviewer_last_name", true);
			$reviewer_verified_buyer = get_post_meta($reviews_id->ID, "reviewer_verified_buyer", true);
			$review_review = get_post_meta($reviews_id->ID, "review_review", true);
			$review_timeago = get_post_meta($reviews_id->ID, "review_timeago", true);
			$review_rating = get_post_meta($reviews_id->ID, "review_rating", true);
			$reviewer_gravatar = get_post_meta($reviews_id->ID, "reviewer_gravatar", true);
			$gravatar_url = get_avatar_url($reviewer_gravatar);
			$rating_star_1 = getStarClass($review_rating, 1);
			$rating_star_2 = getStarClass($review_rating, 2);
			$rating_star_3 = getStarClass($review_rating, 3);
			$rating_star_4 = getStarClass($review_rating, 4);
			$rating_star_5 = getStarClass($review_rating, 5);
			$out .= '<div class="cst_indi_review">
								<div class="cst_indi_review_meta">
									<div class="cst_indi_review_gravtar">
										<img class="reviewer_img" src="' . $gravatar_url . '" alt="" />';
			if ($reviewer_verified_buyer == "yes") {
				$out .= '<div class="cst_avatar_verifiedBadge">
														<div class="cst_verifiedBadge_wrapper">
															<i class="cst_verified"></i>
														</div>
													</div>';
			}
			$out .= '</div>
									<div class="cst_indi_review_name">
										<p class="cst_indi_review_user_title">' . $reviewer_first_name . ' ' . $reviewer_last_name . '</p>
										<p class="cst_indi_review_user_ver">Verified Buyer: ' . $reviewer_verified_buyer . '</p>
									</div>
									<div class="cst_indi_review_gravtar_ratings">
										<span class="fa fa-star ' . $rating_star_1 . '"></span>
										<span class="fa fa-star ' . $rating_star_2 . '"></span>
										<span class="fa fa-star ' . $rating_star_3 . '"></span>
										<span class="fa fa-star ' . $rating_star_4 . '"></span>
										<span class="fa fa-star ' . $rating_star_5 . '"></span>
									</div>
								</div>
								<div class="cst_indi_review_text_cvr">
								     <div class="cst_indi_review_text">
										<p>';
			if (empty($review_review)) {
				$review_review = "This review has no comments";
			}
			$out .= $review_review . '</p>
									</div>
									<div class="cst_indi_review_time">
										<p>' . $review_timeago . '</p>
									</div>
								</div>
						</div>';

		}
	}
	if (!isset($_POST['cst_offset'])) {
		$out .= '</div>';
		$out .= cst_pagination($total_pages, $para_product_ID);
	}
	if ($_POST["isAjaxRequest"]) {
		echo $out;
	} else {
		return $out;
	}

	if (isset($_POST['cst_offset'])) {
		exit;
	}
}


function getStarClass($productRating, $num) {

	$starClasses = array(
		"first",
		"secound",
		"third",
		"fourth",
		"fifth",
		"sixth",
		"seventh",
		"eighth",
		"nineth",
		"tenth"
	);

	if ($productRating >= $num) {
		return "checked_star";
	} else {
		if ($productRating < $num) {
			if ($num > 1) {
				$num = $num - 1;
			}
			for ($x = 1; $x <= 10; $x++) {
				$current_decimal = $num . "." . $x;
				$Rating_decimal = number_format($productRating, 1);
				if ($current_decimal == $Rating_decimal) {
					return $starClasses[$x - 1];
				}
			}
		} else {
			return "";
		}
	}
}


function getWooCommereceProduct($product) {

	$product_ID = $product->get_id();
	$productRating = get_post_meta($product_ID, "product_stats_average", true);

	$average_rating_star_1 = getStarClass($productRating, 1);
	$average_rating_star_2 = getStarClass($productRating, 2);
	$average_rating_star_3 = getStarClass($productRating, 3);
	$average_rating_star_4 = getStarClass($productRating, 4);
	$average_rating_star_5 = getStarClass($productRating, 5);
	$attr = array();
	$attr["product_id"] = $product_ID;
	$out = '<div class="cst_ratingLogoWrap cfx " id="productStar_Reviews">
				<input type="hidden" id="productStarID_' . $product_ID . '" value="' . $product_ID . '" />
				<div id="myModal_' . $product_ID . '" class="modal my_modal">
										  <div class="modal-content">
											<span class="close_modal">&times;</span>
											<h2>Reviews</h2>
											<div>';
	$out .= generate_reviews($attr, $product_ID) . '
											   
											</div>
										  </div>
										</div>
							<div class="cst_overallStatsWrap ">
							<div class="cst_overallStarsWrap ">
							<div class="starsWrap ng-binding ">
								<span class="fa fa-star ProductStar_' . $product_ID . ' ' . $average_rating_star_1 . '"></span>
								<span class="fa fa-star ProductStar_' . $product_ID . ' ' . $average_rating_star_2 . '"></span>
								<span class="fa fa-star ProductStar_' . $product_ID . ' ' . $average_rating_star_3 . '"></span>
								<span class="fa fa-star ProductStar_' . $product_ID . ' ' . $average_rating_star_4 . '"></span>
								<span class="fa fa-star ProductStar_' . $product_ID . ' ' . $average_rating_star_5 . '"></span>
							</div>
							</div>
							</div>
							</div>
							
							';

	return $out;
}


add_action('woocommerce_after_shop_loop_item_title', 'custom_field_display_below_title', 2);
function custom_field_display_below_title() {

	global $product;
	$out = getWooCommereceProduct($product);
	echo $out;
}


add_action('woocommerce_before_single_product_summary', 'mycustomfuncion', 11);
function mycustomfuncion() {

	global $product;
	$pro_id = $product->get_id();
	$out = '<div id="singleProduct_' . $pro_id . '" class="single_product_desc">';
	$out .= getWooCommereceProduct($product);
	$out .= '</div>';
	echo $out;
}
*/