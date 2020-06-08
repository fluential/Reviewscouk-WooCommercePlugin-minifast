<?php
/*
Plugin Name:	Custom Reviews
Plugin URI:		https://example.com
Description:	My custom functions.
Version:		1.0.0
Author:			EXAMPLE
Author URI:		example.com
License:		GPL-2.0+
License URI:	http://www.gnu.org/licenses/gpl-2.0.txt

*/
add_action('wp_enqueue_scripts', 'fwds_styles2');
function fwds_styles2() {
	$version = rand(10,10000);
	wp_register_style('slidesjs_example', plugins_url('/assets/css/style.css', __FILE__),array(),$version);
	wp_enqueue_style('slidesjs_example');
	wp_register_style( 'fontawesome-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );
	wp_enqueue_style('fontawesome-css');
	wp_enqueue_script('cst_custom',  plugins_url('/assets/js/custom.js', __FILE__),array("jquery"),$version);
	wp_localize_script('cst_custom', 'ajaxobj', array(
		  'pro_id' => get_the_ID(),
		 'ajaxurl' => admin_url( 'admin-ajax.php' ),
	));
}
add_action('init', 'cst_init');
function cst_init(){
add_filter('woocommerce_product_tabs', 'cst_product_review_tab');
}
function cst_product_review_tab($tabs){
	$tabs['reviews'] = array(
                    'title'    => 'Reviews',
                    'callback' => 'reviews_shortcode_function',
                    'priority' => 50,
                );
				return $tabs;
}


add_shortcode( 'wpb_custom_cron_short_code', 'wpb_custom_cron_func' );
// Don't delete "wpb_custom_cron_func11" function, 
//this function fetch published products and fetch reviews then store in db
function wpb_custom_cron_func() {
	$args = array(
		post_status => 'publish',
		post_type => 'product'
	);
	$sku = "";
	$posts_array = get_posts($args);
	
	foreach($posts_array as $post) {
		
		$product_ID = $post->ID;
		$product = wc_get_product($product_ID);
		if($product->is_type('variable')){
			foreach($product->get_children() as $variable_pro){
				if(!empty($sku)) {
					$sku .= ";".get_post_meta($variable_pro, "_sku", true);
				}
				else{
					$sku = get_post_meta($variable_pro, "_sku", true);
				}
			}
		}
		else{
			$sku = get_post_meta($product_ID, "_sku", true);
		}
		if(!empty($sku)) {
			
			//$product_ID = 5;
			//$sku = 7350092800037;
			$url = 'https://api.reviews.io/product/review?store=probion-com&sku='.$sku;
			$http = _wp_http_get_object();
			$res =  $http->get( $url); 
			$res_data = wp_remote_retrieve_body($res);
			$res_data_decoded = json_decode($res_data);
			$stats = $res_data_decoded->stats;
			//add_post_meta(1,rand(), $aa);
			$product_stats_average = $stats->average;
			$product_stats_count = $stats->count;
			$product_stats_sku = $sku;
			$review_count = 0;
			if(!empty($product_stats_average)) {
				update_post_meta($product_ID, 'product_stats_average', $product_stats_average);
			}
			if(!empty($product_stats_count)) {
				update_post_meta($product_ID, 'product_stats_count', $product_stats_count);
			}
			if(!empty($product_stats_sku)) {
				update_post_meta($product_ID, 'product_stats_sku', $product_stats_sku);
			}
			$reviews = $res_data_decoded->reviews;
		
			if(!empty($reviews)) {
				$reviews_data = $reviews->data;
				
				if(!empty($reviews_data) && count($reviews_data) > 0) {
					
					foreach($reviews_data as $review_data) {
						$review_product_review_id=$review_data->product_review_id;
						
						$result = get_page_by_title($review_data->product_review_id, OBJECT, 'custom_reviews');
						
						if(empty($result) || is_null($result)) {
							$review_count = count($reviews_data);
								$review_vote= $review_data->votes;
								$review_flags= $review_data->flags;
								$review_title=$review_data->title;
								$review_review=$review_data->review;
								$review_sku=$review_data->sku;
								$review_rating=$review_data->rating;
								$review_date_created=$review_data->date_created;
								$review_timeago=$review_data->timeago;
								$reviewer_first_name=$review_data->reviewer->first_name;
								$reviewer_last_name=$review_data->reviewer->last_name;
								$reviewer_verified_buyer=$review_data->reviewer->verified_buyer;
								$reviewer_address=$review_data->reviewer->address;
								$reviewer_profile_picture=$review_data->reviewer->profile_picture;
								$reviewer_gravatar=$review_data->reviewer->gravatar;
								
								$new_post = array(
									'post_title' => $review_product_review_id,
									'post_status'   => 'publish',
									'post_type'     => 'custom_reviews'
								);
								$postId = wp_insert_post($new_post);
								add_post_meta($postId, 'results', json_encode($result));
								add_post_meta($postId, 'review_vote', $review_vote);
								add_post_meta($postId, 'review_flags', $review_flags);
								add_post_meta($postId, 'review_title', $review_title);
								add_post_meta($postId, 'review_review', $review_review);
								add_post_meta($postId, 'review_sku', $review_sku);
								add_post_meta($postId, 'review_rating', $review_rating);
								add_post_meta($postId, 'review_date_created', $review_date_created);
								add_post_meta($postId, 'review_timeago', $review_timeago);
								add_post_meta($postId, 'reviewer_first_name', $reviewer_first_name);
								add_post_meta($postId, 'reviewer_last_name', $reviewer_last_name);
								add_post_meta($postId, 'reviewer_verified_buyer', $reviewer_verified_buyer);
								add_post_meta($postId, 'reviewer_address', $reviewer_address);
								add_post_meta($postId, 'reviewer_profile_picture', $reviewer_profile_picture);
								add_post_meta($postId, 'reviewer_gravatar', $reviewer_gravatar);
								add_post_meta($postId, 'review_product_review_id', $review_product_review_id);
								add_post_meta($postId, 'review_product_id', $product_ID);
						}
					}
				}
			}
		}
	}
	if($review_count){
		echo "Total ".$review_count. " fetched";
	}
	else{
		echo "No new review fetched";
	}
}

add_action('wp_ajax_more_reviews', 'reviews_shortcode_function');
add_action('wp_ajax_nopriv_more_reviews', 'reviews_shortcode_function');
add_shortcode('reviews_shortcode', 'reviews_shortcode_function');
function reviews_shortcode_function($atts) {
	$offset = 0;
	if(!empty($atts["product_id"])) {
		$product_ID = $atts["product_id"];
	}
	else if(isset($_POST['cst_offset'])){
		$product_ID = $_POST['pro_id'];
		$offset = intval($_POST['cst_offset']);
	}
	else{
		$product_ID = get_the_ID();
	}
	if(empty($product_ID)){
		$product_ID = get_the_ID();
	}
		$args = array(
			'post_type'  => 'custom_reviews',
			'numberposts'  => 5,
			'offset'     => $offset,
			'meta_query' => array(
				'relation' => 'AND',
				array(
						'key'     => 'review_product_id',
						'value'   => $product_ID,
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
		$total_pages = ceil($count_all/5);
		$productRating = get_post_meta($product_ID, "product_stats_average", true);
		if(!empty($productRating)) {
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
		if(!isset($_POST['cst_offset'])){
		$out = '<div class="ratingLogoWrap cfx">
				<div class="overallStatsWrap" ng-show="count > 0">
				<div class="overallRating ng-binding">
				
				'.number_format((float)$productRating, 1, '.', '').'
				</div>
				<div class="overallStarsWrap">
				<div class="starsWrap ng-binding">
					<span class="fa fa-star '.$average_rating_star_1.'"></span>
					<span class="fa fa-star '.$average_rating_star_2.'"></span>
					<span class="fa fa-star '.$average_rating_star_3.'"></span>
					<span class="fa fa-star '.$average_rating_star_4.'"></span>
					<span class="fa fa-star '.$average_rating_star_5.'"></span>
				</div>
				<div class="numReviews ng-binding">
				'.$review_count.' Reviews
				</div>
				</div>
				</div>

				</div>';
		}
				if(!isset($_POST['cst_offset'])){
					$out .='<div class="cst_review_cvr">';
				}
		if(!empty($reviews_ids) && $review_count > 0) {
			foreach($reviews_ids as $reviews_id) {
				
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
			    $out .='<div class="cst_indi_review">
								<div class="cst_indi_review_meta">
									<div class="cst_indi_review_gravtar">
										<img class="reviewer_img" src="'. $gravatar_url .'" alt="" />';
										if($reviewer_verified_buyer == "yes"){
											$out .='<div class="cst_avatar_verifiedBadge">
														<div class="cst_verifiedBadge_wrapper">
															<i class="cst_verified"></i>
														</div>
													</div>';
										}
									$out .='</div>
									<div class="cst_indi_review_name">
										<p class="cst_indi_review_user_title">'.  $reviewer_first_name .' '.$reviewer_last_name.'</p>
										<p class="cst_indi_review_user_ver">Verified Buyer: '. $reviewer_verified_buyer .'</p>
									</div>
									<div class="cst_indi_review_gravtar_ratings">
										<span class="fa fa-star '.$rating_star_1.'"></span>
										<span class="fa fa-star '.$rating_star_2.'"></span>
										<span class="fa fa-star '.$rating_star_3.'"></span>
										<span class="fa fa-star '.$rating_star_4.'"></span>
										<span class="fa fa-star '.$rating_star_5.'"></span>
									</div>
								</div>
								<div class="cst_indi_review_text_cvr">
								     <div class="cst_indi_review_text">
										<p>';
										if(empty($review_review)){
											$review_review = "This review has no comments";
										}
										$out .= $review_review.'</p>
									</div>
									<div class="cst_indi_review_time">
										<p>'. $review_timeago .'</p>
									</div>
								</div>
						</div>';
				
			}	
		}
		if(!isset($_POST['cst_offset'])){
		$out .='</div>';
			$out .= cst_pagination($total_pages);
		}
		echo $out;
		if(isset($_POST['cst_offset'])){
		exit;
		}
	}

function cst_pagination($page_number, $para_product_ID = ""){
	$cst_pagination_cvr_class = "cst_pagination_cvr";
	if(!empty($para_product_ID)) {
		$cst_pagination_cvr_class = $cst_pagination_cvr_class.'_'.$para_product_ID.' cst_pagination_cvr_review';
	}
	$out = '<div class="'. $cst_pagination_cvr_class .'"><ul>';
	for($i=1;$i<=$page_number;$i++){
		if($i==1){
			$class='class="cst_curr"';
		}
		else{
			$class = '';
		}
		
		$out .= '<li '.$class.'>'.$i.'</li>';
	}
	$out .= '</ul></div>';
	return $out;
}


add_action('admin_menu', 'cst_custom_menu');
function cst_custom_menu() { 
  add_menu_page( 
      'Get Reviews', 
      'Get Reviews', 
      'edit_posts', 
      'cst_get_reviews', 
      'cst_callback_function', 
      'dashicons-media-spreadsheet' 
     );
}

function cst_callback_function(){
	echo '<form style="padding: 20px;" method="POST"><input type="hidden" name="get_reviews_form"><input type="submit" value="Get Reviews"></form>';
	if(isset($_POST['get_reviews_form'])){
		wpb_custom_cron_func();
	}
}

add_action('wp_ajax_more_popup_reviews', 'generate_reviews');
add_action('wp_ajax_nopriv_more_popup_reviews', 'generate_reviews');
function generate_reviews($atts, $para_product_ID = "") {
	$offset = 0;
	if(!empty($atts["product_id"])) {
		$product_ID = $atts["product_id"];
	}
	else if(isset($_POST['cst_offset'])){
		$product_ID = $_POST['pro_id'];
		$offset = intval($_POST['cst_offset']);
		if($_POST['ispopupReviews']) {
			$para_product_ID = $product_ID;
		}
	}
	else{
		$product_ID = get_the_ID();
	}
	if(empty($product_ID)){
		$product_ID = get_the_ID();
	}
		$args = array(
			'post_type'  => 'custom_reviews',
			'numberposts'  => 5,
			'offset'     => $offset,
			'meta_query' => array(
				'relation' => 'AND',
				array(
						'key'     => 'review_product_id',
						'value'   => $product_ID,
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
		$total_pages = ceil($count_all/5);
		$productRating = get_post_meta($product_ID, "product_stats_average", true);
		if(!empty($productRating)) {
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
		if(!isset($_POST['cst_offset'])){
		$out = '<div class="ratingLogoWrap cfx">
				<div class="overallStatsWrap" ng-show="count > 0">
				<div class="overallRating ng-binding">
				
				'.number_format((float)$productRating, 1, '.', '').'
				</div>
				<div class="overallStarsWrap">
				<div class="starsWrap ng-binding">
					<span class="fa fa-star '.$average_rating_star_1.'"></span>
					<span class="fa fa-star '.$average_rating_star_2.'"></span>
					<span class="fa fa-star '.$average_rating_star_3.'"></span>
					<span class="fa fa-star '.$average_rating_star_4.'"></span>
					<span class="fa fa-star '.$average_rating_star_5.'"></span>
				</div>
				<div class="numReviews ng-binding">
				'.$review_count.' Reviews
				</div>
				</div>
				</div>

				</div>';
		}
				if(!isset($_POST['cst_offset'])){
					$cst_review_class = "cst_review_cvr";
					if(!empty($para_product_ID)) {
						$cst_review_class = $cst_review_class.'_'.$para_product_ID;
					}
					$out .='<div class="'. $cst_review_class .'">';
				}
		if(!empty($reviews_ids) && $review_count > 0) {
			foreach($reviews_ids as $reviews_id) {
				
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
			    $out .='<div class="cst_indi_review">
								<div class="cst_indi_review_meta">
									<div class="cst_indi_review_gravtar">
										<img class="reviewer_img" src="'. $gravatar_url .'" alt="" />';
										if($reviewer_verified_buyer == "yes"){
											$out .='<div class="cst_avatar_verifiedBadge">
														<div class="cst_verifiedBadge_wrapper">
															<i class="cst_verified"></i>
														</div>
													</div>';
										}
									$out .='</div>
									<div class="cst_indi_review_name">
										<p class="cst_indi_review_user_title">'.  $reviewer_first_name .' '.$reviewer_last_name.'</p>
										<p class="cst_indi_review_user_ver">Verified Buyer: '. $reviewer_verified_buyer .'</p>
									</div>
									<div class="cst_indi_review_gravtar_ratings">
										<span class="fa fa-star '.$rating_star_1.'"></span>
										<span class="fa fa-star '.$rating_star_2.'"></span>
										<span class="fa fa-star '.$rating_star_3.'"></span>
										<span class="fa fa-star '.$rating_star_4.'"></span>
										<span class="fa fa-star '.$rating_star_5.'"></span>
									</div>
								</div>
								<div class="cst_indi_review_text_cvr">
								     <div class="cst_indi_review_text">
										<p>';
										if(empty($review_review)){
											$review_review = "This review has no comments";
										}
										$out .= $review_review.'</p>
									</div>
									<div class="cst_indi_review_time">
										<p>'. $review_timeago .'</p>
									</div>
								</div>
						</div>';
				
			}	
		}
		if(!isset($_POST['cst_offset'])){
		$out .='</div>';
			$out .= cst_pagination($total_pages, $para_product_ID);
		}
		if($_POST["isAjaxRequest"]) {
			echo $out;
		} else {
			return $out;
		}
		
		if(isset($_POST['cst_offset'])){
		exit;
		}
}

function getStarClass($productRating, $num) {
	$starClasses = array("first", "secound", "third", "fourth" , "fifth", "sixth", "seventh", "eighth", "nineth", "tenth");
	
	if($productRating >= $num) {
		return "checked_star";
	} else {
		if($productRating < $num) {
			if($num > 1) {
				$num = $num - 1;
			}
			for ($x = 1; $x <= 10; $x++) {
			   $current_decimal = $num.".".$x;
			   $Rating_decimal = number_format($productRating , 1);
			   if($current_decimal == $Rating_decimal) {
				 return $starClasses[$x-1];
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
				<input type="hidden" id="productStarID_'.$product_ID.'" value="'. $product_ID .'" />
				<div id="myModal_'. $product_ID .'" class="modal my_modal">
										  <div class="modal-content">
											<span class="close_modal">&times;</span>
											<h2>Reviews</h2>
											<div>';
											$out .= generate_reviews($attr, $product_ID).'
											   
											</div>
										  </div>
										</div>
							<div class="cst_overallStatsWrap ">
							<div class="cst_overallStarsWrap ">
							<div class="starsWrap ng-binding ">
								<span class="fa fa-star ProductStar_'. $product_ID .' '.$average_rating_star_1.'"></span>
								<span class="fa fa-star ProductStar_'. $product_ID .' '.$average_rating_star_2.'"></span>
								<span class="fa fa-star ProductStar_'. $product_ID .' '.$average_rating_star_3.'"></span>
								<span class="fa fa-star ProductStar_'. $product_ID .' '.$average_rating_star_4.'"></span>
								<span class="fa fa-star ProductStar_'. $product_ID .' '.$average_rating_star_5.'"></span>
							</div>
							</div>
							</div>
							</div>
							
							';	
	return $out;
}

add_action( 'woocommerce_after_shop_loop_item_title', 'custom_field_display_below_title', 2 );
function custom_field_display_below_title(){
    global $product;
	$out = getWooCommereceProduct($product);
    echo $out;
}

add_action('woocommerce_before_single_product_summary','mycustomfuncion',11);
function mycustomfuncion()
{
	global $product;
	$pro_id = $product->get_id();
	$out = '<div id="singleProduct_'. $pro_id .'" class="single_product_desc">';
	$out .= getWooCommereceProduct($product);
    $out .= '</div>';
	echo $out;
}
