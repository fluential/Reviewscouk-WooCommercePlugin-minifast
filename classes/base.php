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
				$this->cronDeactivate();
				$this->cronActivate();
				wp_redirect(admin_url('admin.php?page=' . $this->admin->slug));
			}

			if (isset($_GET['sync-force'])) {
				$this->startSync();
			}

			register_post_type($this->type, array(
				'labels' => array(
					'name' => __('Reviews', 'rio'),
					'singular_name' => __('Review', 'rio'),
					'new_item' => __('New Review', 'rio'),
					'add_new' => __('Add Review', 'rio'),
					'add_new_item' => __('Add New Review', 'rio'),
					'edit_item' => __('Edit Review', 'rio'),
					'view_item' => __('View Review', 'rio'),
					'all_items' => __('All Reviews', 'rio'),
					'search_items' => __('Search Reviews', 'rio'),
					'not_found' => __('Reviews not found', 'rio'),
					'not_found_in_trash' => __('Reviews not found in trash', 'rio'),
				),
				'description' => __('Reviews.io Customer Reviews', 'rio'),
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

		add_action('plugins_loaded', function(){
			load_plugin_textdomain('rio', false, basename(RIO_DIR) . '/languages/');
		});

		$this->woo->init();
		$this->admin->init();

	}


	public function startSync() {

		$time = time();

		$key = $this->id . '_sync_started';

		$offset = intval(get_option('gmt_offset') * HOUR_IN_SECONDS);

		$sync_started = intval(get_option($key, 0));

		if ($time - $sync_started > 5 * 60) {

			update_option($key, $time);

			$this->log->info(__('Sync started on', 'rio') . ' <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$products = $this->getProductIds();

			$parents = $this->getParentProducts();

			$existing = $this->getExistingReviewIds();

			$reviews = $this->api->getAllProductReviews();

			$this->log->info('Total products count: <b>' . count($products) . '</b>');

			$total = 0;

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

							$total++;

							if (empty($existing[$review['product_review_id']])) {

								$args = array(
									'post_title' => sprintf(__('Review #%d', 'rio'), $review['product_review_id']),
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
									$this->log->error(__('Failed to add a review:', 'rio') . ' <b>' . $post_id->get_error_message() . '</b>');
								} else {
									$this->log->info(__('Added a new review:', 'rio') . ' <b>#' . $review['product_review_id'] . '</b>');
								}

							}

						}

						$rating = round($rating / $count, 2);

						update_post_meta($product_id, 'rating_count', $count);
						update_post_meta($product_id, 'rating_value', $rating);

					}

				}

			}

			$this->log->info(__('Product reviews processed:', 'rio') . ' <b>' . $total . '</b>');

			$this->log->info(__('Product reviews sync finished on', 'rio') . ' <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$reviews = $this->api->getAllMerchantReviews();

			$this->log->info(__('Merchant reviews count:', 'rio') . ' <b>' . count($reviews) . '</b>');

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
							$this->log->error(__('Failed to add a review:', 'rio') . ' <b>' . $post_id->get_error_message() . '</b>');
						} else {
							$this->log->info(__('Added a new merchant review:', 'rio') . ' <b>#' . $review['store_review_id'] . '</b>');
						}

					}

				}

				$rating = round($rating / $count, 2);

				update_option($this->id . '_rating_count', $count);
				update_option($this->id . '_rating_value', $rating);

			}

			$this->log->info(__('Merchant reviews sync finished on', 'rio') . ' <b>' . date('F j, Y, H:i:s', $time + $offset) . '</b>');

			$this->log->save();

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

		$array['meta_query'] = array();

		if ($args['product_id'] >= 0) {

			$array['meta_query'] = array(
				array(
					'key' => 'review_product',
					'value' => intval($args['product_id']),
					'compare' => '=',
					'type' => 'NUMERIC'
				)
			);

		}

		$array['meta_query']['review_date'] = array(
			'key' => 'review_date',
			'compare' => 'EXISTS',
			'type' => 'DATETIME'
		);

		$array['orderby'] = array(
			'review_date' => 'DESC'
		);

		if ($args['offset']) {
			$array['offset'] = intval($args['offset']);
		}

		return new \WP_Query($array);

	}


	/* Add a cron event on plugin activation */

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
					'name' => __('Synchronization Interval', 'rio'),
					'type' => 'select',
					'value' => 'hourly',
					'values' => array(
						'minutes_15' => __('Every 15 minutes', 'rio'),
						'minutes_30' => __('Every 30 minutes', 'rio'),
						'hourly' => 'Hourly',
						'twicedaily' => 'Twice a day',
						'daily' => 'Daily',
						'weekly' => 'Weekly'
					),
					'validation' => function($value) {

						$current = get_option($this->id . '_interval');

						$schedules = wp_get_schedules();

						if ($current != $value and !empty($schedules[$value])) {
							$this->cronDeactivate();
							$this->cronActivate($value);
						}

						return $value;

					}
				),
				'stars' => array(
					'name' => __('Stars', 'rio'),
					'type' => 'select',
					'value' => 0,
					'values' => array(
						0 => 'Plugin',
						1 => 'Theme'
					)
				),
				'pages' => array(
					'name' => __('Pages with store reviews', 'rio'),
					'type' => 'textarea',
					'value' => '',
					'hint' => __('Enter the relative page urls, where you want to display the schema with store reviews. One url per line', 'rio')
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


	/* Add a cron event on plugin activation */

	public function cronActivate($interval = false) {

		if (!wp_next_scheduled($this->event)) {

			if (empty($interval)) {
				$interval = $this->getSetting('interval');
			}

			$time = time() + 10;

			$schedules = wp_get_schedules();

			if (empty($interval) or empty($schedules[$interval])) {
				$interval = 'hourly';
			}

			wp_schedule_event($time, $interval, $this->event);

		}

	}


	/* Remove a cron event on plugin deactivation */

	public function cronDeactivate() {

		wp_clear_scheduled_hook($this->event);

	}


	/* Register additional intervals */

	public function cronIntervals($schedules) {

		$schedules['minutes_15'] = array(
			'interval' => 60 * 15,
			'display' => __('Every 15 minutes', 'rio')
		);

		$schedules['minutes_30'] = array(
			'interval' => 60 * 30,
			'display' => __('Every 30 minutes', 'rio')
		);

		return $schedules;

	}

}