<?php

namespace RIO;

class Woo {

	public $log;

	public $base;


	public function __construct(Base $base) {

		$this->log = $base->log;

		$this->base = $base;

	}


	public function init() {

		// Enqueue required scripts and styles
		add_action('wp_enqueue_scripts', array($this, 'assets'));

		// Replace the product rating
		remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);

		add_action('woocommerce_after_shop_loop_item_title', array($this, 'productRating'));
		add_action('woocommerce_single_product_summary', array($this, 'productRating'), 8);

		// Add product reviews to the product schema
		add_filter('woocommerce_structured_data_product', array($this, 'markupProduct'), 10, 2);

		// Output the store schema
		add_action('wp_head', array($this, 'markupStore'));

		// Add a custom AJAX handler to load more reviews
		add_action('wp_ajax_nopriv_rio_load', array($this, 'loadHandler'));
		add_action('wp_ajax_rio_load', array($this, 'loadHandler'));

		// Add a new tab with reviews
		add_filter('woocommerce_product_tabs', array($this, 'productTabs'));

		// Register a shortcode to display reviews
		add_shortcode('rio_shortcode', array($this, 'reviewsShortcode'));

	}


	/* Enqueue assets */

	public function assets() {

		if (!is_admin()) {

			wp_enqueue_style('rio_styles', RIO_URL . 'assets/styles.css');

			wp_enqueue_script('rio_scripts', RIO_URL . 'assets/scripts.js', array('jquery'), false, true);

			wp_localize_script('rio_scripts', 'rio', array(
				'nonce' => wp_create_nonce('ajax-nonce'),
				'ajaxurl' => admin_url('admin-ajax.php'),
			));

		}

	}


	public function productRating() {

		global $product;

		if (!empty($product) and $product instanceof \WC_Product) {

			$product_id = $product->get_id();

			$rating = floatval(get_post_meta($product_id, 'rating_value', true));

			if ($rating > 0) {

				$stars = $this->base->getSetting('stars');

				if (empty($stars)) {
					echo '<div class="rio_stars"><span style="width: ' . ($rating * 20) . '%;"></span></div>';
				} else {
					echo wc_get_rating_html($rating);
				}

			}

		}

	}


	public function productTabs($tabs) {

		$tabs['reviews'] = array(
			'title' => 'Reviews',
			'callback' => array($this, 'reviewsTab'),
			'priority' => 50,
		);

		return $tabs;

	}


	public function reviewsTab($data = array()) {

		$product_id = 0;

		if (!empty($data['product_id'])) {
			$product_id = intval($data['product_id']);
		} elseif (is_singular('product')) {
			$product_id = get_queried_object_id();
		}

		$data = array(
			'product_id' => $product_id,
			'number' => 5
		);

		$query = $this->base->getReviewsQuery($data);

		$variables = array(
			'query' => $query,
			'base' => $this->base,
			'product_id' => $product_id,
			'loader' => $this->loadButton($query),
			'link' => $this->reviewsLink($product_id),
			'rating_count' => get_post_meta($product_id, 'rating_count', true),
			'rating_value' => get_post_meta($product_id, 'rating_value', true),
		);

		$this->base->getTemplate('tab', $variables);

		wp_reset_postdata();

	}


	public function reviewsShortcode($data) {

		ob_start();

		$data = wp_parse_args($data,array(
			'product_id' => 0,
			'number' => 5,
		));

		if (!empty($data['product_id'])) {
			$product_id = intval($data['product_id']);
		} else {
			$product_id = 0;
		}

		$query = $this->base->getReviewsQuery($data);

		$variables = array(
			'query' => $query,
			'base' => $this->base,
			'loader' => $this->loadButton($query),
			'link' => $this->reviewsLink($product_id)
		);

		$this->base->getTemplate('tab', $variables);

		wp_reset_postdata();

		$result = ob_get_contents();

		ob_end_clean();

		return $result;

	}


	public function reviewsLink($product_id) {

		$link = 'https://www.reviews.co.uk/product-reviews/store/' . $this->base->getSetting('api_store_id');

		if ($product_id > 0) {

			$sku = get_post_meta($product_id, '_sku', true);

			if ($sku) {
				$link .= '/' . $sku;
			}

		}

		return $link;

	}


	public function loadHandler() {

		$result = array(
			'result' => '',
			'more' => false
		);

		if (isset($_POST['noncer']) and wp_verify_nonce($_POST['noncer'], 'ajax-nonce')) {

			$fields = array('offset', 'number');

			$params = array();

			foreach ($fields as $field) {
				if (isset($_REQUEST[$field])) {
					$params[$field] = intval($_REQUEST[$field]);
				} else {
					$params[$field] = 0;
				}
			}

			if ($params['number'] > 0) {

				$args = array(
					'post_type' => $this->base->type,
					'post_status' => 'publish',
					'offset' => $params['offset'],
					'posts_per_page' => $params['number']
				);

				if (!empty($_REQUEST['query_meta']) and is_array($_REQUEST['query_meta'])) {
					$args['meta_query'] = $_REQUEST['query_meta'];
				}

				if (!empty($_REQUEST['query_order']) and is_array($_REQUEST['query_order'])) {

					foreach ($_REQUEST['query_order'] as $key => $value) {
						$key = trim(esc_attr($key));
						$value = trim(esc_attr($value));
						$args['orderby'][$key] = $value;
					}

				}

				if (!empty($_REQUEST['query_direction']) and in_array($_REQUEST['query_direction'], array('ASC', 'DESC'))) {
					$args['order'] = $_REQUEST['query_direction'];
				}

				if (!empty($_REQUEST['order']) and in_array($_REQUEST['order'], array('date', 'title', 'author'))) {

					$order = esc_attr($_REQUEST['order']);

					$args['orderby'] = $order;

					$args['order'] = 'DESC';

					if ($order == 'title') {
						$args['order'] = 'ASC';
					}

					if (is_array($args['orderby'])) {
						$args['orderby'][$order] = $args['order'];
					} else {
						$args['orderby'] = array(
							$order => $args['order']
						);
					}

				}

				$query = new \WP_Query($args);

				if ($query->have_posts()) {

					if (($params['number'] + $params['offset']) < $query->found_posts) {
						$result['more'] = true;
					}

					ob_start();

					while ($query->have_posts()) {

						$query->the_post();

						$this->base->getTemplate('review', array('item' => $query->post));

					}

					$result['result'] = ob_get_contents();

				} else {

					$result['result'] = '<div class="message">Nothing had been found</div>';

				}

				ob_end_clean();

				wp_reset_postdata();

			}

		}

		wp_send_json($result);

	}


	public function loadButton($query) {

		$result = '';

		if ($query instanceof \WP_Query) {

			$number = intval($query->query_vars['posts_per_page']);

			$paged = intval($query->query_vars['paged']);

			if ($paged == 0) {
				$paged = 1;
			}

			$offset = $paged * $number;

			$hidden = true;

			if ($offset < $query->found_posts) {
				$hidden = false;
			}

			$args = array(
				'number' => $number,
				'offset' => $offset,
				'wrapper' => '.reviews_box',
				'query_meta' => $query->get('meta_query'),
				'query_order' => $query->get('orderby'),
				'query_direction' => $query->get('order')
			);

			$result = '
			<div class="load">
				<div class="button' . ($hidden ? ' hidden' : '') . '" data-loader="' . htmlspecialchars(json_encode($args), ENT_QUOTES, 'UTF-8') . '">' . __('Show More', 'rio') . '</div>
			</div>';

		}

		return $result;

	}


	public function markupProduct($markup, $product) {

		if ($product instanceof \WC_Product) {

			$product_id = $product->get_id();

			$data = array(
				'product_id' => $product_id,
				'number' => -1
			);

			$query = $this->base->getReviewsQuery($data);

			if ($query->have_posts()) {

				$reviews = $query->posts;
				$count = count($reviews);
				$total = 0;

				$markup['review'] = array();

				foreach ($reviews as $review) {

					if ($review instanceof \WP_Post) {

						$first_name = get_post_meta($review->ID, 'review_first_name', true);
						$last_name = get_post_meta($review->ID, 'review_last_name', true);
						$gravatar = get_post_meta($review->ID, 'review_gravatar', true);
						$rating = intval(get_post_meta($review->ID, 'review_rating', true));
						$date = get_post_meta($review->ID, 'review_date', true);

						if ($date) {
							$date = strtotime($date);
						} else {
							$date = time() - rand(0, 100) * 3600 * 24;
						}

						if (empty($gravatar)) {
							$gravatar = 'c8e57bdf509598c0a214f7b5c0b80bb3';
						}

						$name = $first_name;

						if ($last_name) {
							$name .= ' ' . $last_name;
						}

						$name = trim($name);

						$markup['review'][] = array(
							'@type' => 'Review',
							'reviewRating' => array(
								'@type' => 'Rating',
								'bestRating' => '5',
								'ratingValue' => $rating,
								'worstRating' => '1',
							),
							'author' => array(
								'@type' => 'Person',
								'name' => $name,
								'givenName' => $first_name,
								'familyName' => $last_name,
								'image' => 'https://www.gravatar.com/avatar/' . $gravatar
							),
							'reviewBody' => $review->post_content,
							'datePublished' => date('c', $date),
						);

						$total += $rating;

					}

				}

				$markup['aggregateRating'] = array(
					'@type' => 'AggregateRating',
					'ratingValue' => round($total / $count, 2),
					'reviewCount' => $count,
				);

			}

		}

		wp_reset_postdata();

		return $markup;

	}


	public function markupStore() {

		$pages = $this->base->getSetting('pages');

		if ($pages) {

			$pages = explode("\n", $pages);

			$pages = array_map('trim', $pages);

			if (in_array($_SERVER['REQUEST_URI'], $pages)) {

				$markup = array(
					'@context' => 'http://schema.org',
					'@type' => 'Organization',
					'name' => get_bloginfo('name'),
					'url' => home_url(),
					'image' => get_site_icon_url(),
					'description' => get_bloginfo('description'),
					//'price_range' => '$$',
					'potentialAction' => array(
						'@type' => 'SearchAction',
						'target' => home_url('?s={search_term_string}&post_type=product'),
						'query-input' => 'required name=search_term_string',
					),
				);

				$data = array(
					'product_id' => 0,
					'number' => -1
				);

				$query = $this->base->getReviewsQuery($data);

				if ($query->have_posts()) {

					$reviews = $query->posts;
					$count = count($reviews);
					$total = 0;

					$markup['review'] = array();

					foreach ($reviews as $review) {

						if ($review instanceof \WP_Post) {

							$first_name = get_post_meta($review->ID, 'review_first_name', true);
							$last_name = get_post_meta($review->ID, 'review_last_name', true);
							$gravatar = get_post_meta($review->ID, 'review_gravatar', true);
							$rating = intval(get_post_meta($review->ID, 'review_rating', true));
							$date = get_post_meta($review->ID, 'review_date', true);

							if ($date) {
								$date = strtotime($date);
							} else {
								$date = time() - rand(0, 100) * 3600 * 24;
							}

							if (empty($gravatar)) {
								$gravatar = 'c8e57bdf509598c0a214f7b5c0b80bb3';
							}

							$name = $first_name;

							if ($last_name) {
								$name .= ' ' . $last_name;
							}

							$name = trim($name);

							$markup['review'][] = array(
								'@type' => 'Review',
								'reviewRating' => array(
									'@type' => 'Rating',
									'bestRating' => '5',
									'ratingValue' => $rating,
									'worstRating' => '1',
								),
								'author' => array(
									'@type' => 'Person',
									'name' => $name,
									'givenName' => $first_name,
									'familyName' => $last_name,
									'image' => 'https://www.gravatar.com/avatar/' . $gravatar
								),
								'reviewBody' => $review->post_content,
								'datePublished' => date('c', $date),
							);

							$total += $rating;

						}

					}

					$markup['aggregateRating'] = array(
						'@type' => 'AggregateRating',
						'ratingValue' => round($total / $count, 2),
						'reviewCount' => $count,
					);

				}

				if (function_exists('get_woocommerce_currency_symbol')) {

					//$markup['currenciesAccepted'] = get_woocommerce_currency();

					$fields = array(
						'woocommerce_store_address' => 'streetAddress',
						'woocommerce_store_city' => 'addressLocality',
						'woocommerce_store_postcode' => 'postalCode',
						'woocommerce_default_country' => 'addressCountry',
					);

					$data = array();

					foreach ($fields as $field => $property) {

						$option = get_option($field);

						if ($option) {
							$data[$property] = $option;
						}

					}

					if ($data) {

						$markup['address'] = $data;

						$markup['address']['@type'] = 'PostalAddress';

					}

				}

				echo "<script type=\"application/ld+json\">\n" . json_encode($markup, JSON_UNESCAPED_SLASHES) . "\n</script>\n";

			}

		}

	}

}
