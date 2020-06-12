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

	}


	/* Enqueue assets */

	public function assets() {

		if (!is_admin()) {

			$dir = plugin_dir_url(__FILE__) . 'assets/';

			wp_enqueue_style('rio_styles', $dir . '/styles.css');

			wp_enqueue_script('rio_scripts', $dir . '/scripts.js', array('jquery'), false, true);

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

}