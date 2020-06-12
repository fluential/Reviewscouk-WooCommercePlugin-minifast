<?php

namespace RIO;

class API {

	public $url = 'https://api.reviews.io/';

	public $log;

	public $base;

	protected $key;

	protected $store_id;

	public function __construct(Base $base) {

		$this->log = $base->log;
		$this->base = $base;
		$this->key = $base->getSetting('api_key');
		$this->store_id = $base->getSetting('api_store_id');

	}


	/**
	 * Get all product reviews grouped by product SKU
	 *
	 * @return array
	 */

	public function getAllProductReviews() {

		$reviews = array();

		$page = 0;

		$data = array(
			'store' => $this->store_id,
			'apikey' => $this->key,
			'per_page' => 100,
			'page' => $page,
			'photos' => 0
		);

		$response = $this->requestGet('product/reviews/all', $data);

		if (is_array($response) and !empty($response['reviews']) and is_array($response['reviews'])) {

			foreach ($response['reviews'] as $review) {
				if (!empty($review['sku']) and !empty($review['product_review_id'])) {
					if (empty($reviews[$review['sku']])) {
						$reviews[$review['sku']] = array();
					}
					$reviews[$review['sku']][] = $review;
				}
			}

			if (!empty($response['total_pages']) and $response['total_pages'] > 1) {

				$total = intval($response['total_pages']);

				for ($page = 1; $page <= $total; $page++) {

					$data['page'] = $page;

					$response = $this->requestGet('product/reviews/all', $data);

					if (is_array($response) and !empty($response['reviews']) and is_array($response['reviews'])) {
						foreach ($response['reviews'] as $review) {
							if (!empty($review['sku'])) {
								if (empty($reviews[$review['sku']])) {
									$reviews[$review['sku']] = array();
								}
								$reviews[$review['sku']][] = $review;
							}
						}
					}

				}

			}

		}

		return $reviews;

	}


	/**
	 * Get all store reviews grouped by review ID
	 *
	 * @return array
	 */

	public function getAllMerchantReviews() {

		$reviews = array();

		$page = 0;

		$data = array(
			'store' => $this->store_id,
			'apikey' => $this->key,
			'per_page' => 100,
			'page' => $page,
			'photos' => 0,
			'include_replies' => 0
		);

		$response = $this->requestGet('merchant/reviews', $data);

		if (is_array($response) and !empty($response['reviews']) and is_array($response['reviews'])) {

			foreach ($response['reviews'] as $review) {
				if (!empty($review['store_review_id'])) {
					$reviews[$review['store_review_id']] = $review;
				}
			}

			if (!empty($response['total_pages']) and $response['total_pages'] > 1) {

				$total = intval($response['total_pages']);

				for ($page = 1; $page <= $total; $page++) {

					$data['page'] = $page;

					$response = $this->requestGet('product/reviews/all', $data);

					if (is_array($response) and !empty($response['reviews']) and is_array($response['reviews'])) {
						foreach ($response['reviews'] as $review) {
							if (!empty($review['store_review_id'])) {
								$reviews[$review['store_review_id']] = $review;
							}
						}
					}

				}

			}

		}

		return $reviews;

	}

	public function requestGet($endpoint, $data = array()) {

		try {

			$url = $this->url . $endpoint;

			if ($data) {
				$url = add_query_arg($data, $url);
			}

			$response = wp_remote_get($url);

			if (is_array($response) and !empty($response['body'])) {
				return json_decode($response['body'], true);
			}

			return false;

		} catch (Exception $e) {

			$this->log->error($e->getMessage());

			return false;

		}

	}

	public function requestPost($endpoint, $data = array()) {

		try {

			$response = wp_remote_post($this->url . $endpoint, array(
				'method' => 'POST',
				'headers' => array(
					'store' => $this->store_id,
					'apikey' => $this->key,
					'Content-Type' => 'application/json',
				),
				'body' => json_encode($data),
			));

			if (is_array($response)) {
				return $response['body'];
			}

			return false;

		} catch (Exception $e) {

			$this->log->error($e->getMessage());

			return false;

		}

	}

}