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

		add_action('wp_enqueue_scripts', array($this, 'assets'));

		add_filter('woocommerce_product_tabs', function($tabs){

			$tabs['reviews'] = array(
				'title' => 'Reviews',
				'callback' => array($this, 'reviewsTab'),
				'priority' => 50,
			);

			return $tabs;

		});

		add_filter('woocommerce_structured_data_product', array($this, 'schemaProduct'), 10, 2);

	}


	/* Enqueue assets */

	public function assets() {

		if (!is_admin()) {

			wp_enqueue_style('rio_styles', RIO_URL . 'assets/styles.css');

			wp_enqueue_script('rio_scripts', RIO_URL . 'assets/scripts.js', array('jquery'), false, true);

			wp_localize_script('rio_scripts', 'rio', array(
				'product_id' => get_the_ID(),
				'ajaxurl' => admin_url('admin-ajax.php'),
			));

		}

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
			'number' => 10
		);

		$query = $this->base->getReviewsQuery($data);

		$variables = array(
			'query' => $query,
			'base' => $this->base,
			'product_id' => $product_id,
			'rating_count' => get_post_meta($product_id, 'rating_count', true),
			'rating_value' => get_post_meta($product_id, 'rating_value', true),
		);

		$this->base->getTemplate('tab', $variables);

		wp_reset_postdata();

	}


	public function schemaProduct($markup, $product) {

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

}