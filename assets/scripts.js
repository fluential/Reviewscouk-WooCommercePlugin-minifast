jQuery(document).ready(function() {
	if (jQuery('#productStar_Reviews').length > 0) {
		var active_review_product_id = null;
	}
	jQuery('.cst_pagination_cvr ul li').on('click', function() {
		if (!jQuery(this).hasClass('cst_curr')) {
			var ths = jQuery(this);
			var offset = (parseInt(jQuery(this).text()) - 1) * 5;
			jQuery.ajax({
				url: ajaxobj.ajaxurl,
				method: 'post',
				data: {
					action: 'more_reviews',
					cst_offset: offset,
					pro_id: ajaxobj.pro_id
				},
				success: function(resp) {
					jQuery('.cst_review_cvr').html(resp);
					jQuery('.cst_pagination_cvr ul li').each(function(i, v) {
						jQuery(this).removeClass('cst_curr');
					});
					ths.addClass('cst_curr');
					jQuery('html, body').animate({
						scrollTop: $('.cst_review_cvr').offset().top - 200
					}, 200);
				}
			});
		}
	});
	if (jQuery('#productStar_Reviews').length > 0) {
		jQuery('#myModal').hide();
		jQuery('.woocommerce-LoopProduct-link').each(function() {
			var childrenLength = this.children.length;
			if (childrenLength > 0) {
				for (var i = 0; i < childrenLength; i++) {
					if (this.children[i] && this.children[i].classList && this.children[i].classList.length > 0 && this.children[i].classList.contains('cst_ratingLogoWrap')) {
						var starChild = this.children[i];
						var starChildrenLength = starChild.children.length;
						if (starChildrenLength > 0) {
							for (var s = 0; s < starChildrenLength; s++) {
								if (starChild.children[s] && starChild.children[s].id && starChild.children[s].id.includes('productStarID')) {
									var productId = starChild.children[s].value;
									var starsClass = `ProductStar_${productId}`;
									starClickEvents(productId);
								}
							}
						}
						this.children[i].remove();
						this.parentElement.append(starChild);
					}
				}
			}
		});

		jQuery('.close_modal').on('click', function(event) {
			active_review_product_id = '';
			jQuery('.my_modal').hide();
		});

		var modal = document.getElementsByClassName('my_modal');
		window.onclick = function(event) {
			if (event.target.className.includes('my_modal')) {
				jQuery('.my_modal').hide();
			}
		};

		function paging(that) {
			if (!jQuery(that).hasClass('cst_curr')) {
				var ths = jQuery(that);
				var offset = (parseInt(jQuery(that).text()) - 1) * 5;
				jQuery.ajax({
					url: ajaxobj.ajaxurl,
					method: 'post',
					data: {
						action: 'more_popup_reviews',
						cst_offset: offset,
						pro_id: active_review_product_id,
						ispopupReviews: !!active_review_product_id,
						isAjaxRequest: true
					},
					success: function(resp) {
						if (active_review_product_id) {
							jQuery(`.cst_review_cvr_${active_review_product_id}`).html(resp);
							jQuery(`.cst_pagination_cvr_${active_review_product_id} ul li`).each(function(i, v) {
								jQuery(this).removeClass('cst_curr');
							});
							ths.addClass('cst_curr');
							jQuery('html, body').animate({
								scrollTop: $(`.cst_review_cvr_${active_review_product_id}`).offset().top - 200
							}, 200);
						} else {
							jQuery('.cst_review_cvr').html(resp);
							jQuery('.cst_pagination_cvr ul li').each(function(i, v) {
								jQuery(this).removeClass('cst_curr');
							});
							ths.addClass('cst_curr');
							jQuery('html, body').animate({
								scrollTop: $('.cst_review_cvr').offset().top - 200
							}, 200);
						}

					}
				});
			}
		}
	}

	if (jQuery('.single_product_desc').length > 0) {
		var productId = jQuery('.single_product_desc').find('input[type="hidden"]').val();
		if (productId) {
			starClickEvents(productId);
		}
	}

	function starClickEvents(productId) {
		var starClass = `ProductStar_${productId}`;
		if (jQuery(`.${starClass}`).length > 0) {
			jQuery(`.${starClass}`).on('click', function(event) {
				event.preventDefault();
				active_review_product_id = productId;
				jQuery(`.cst_pagination_cvr_${productId} > ul > li:first`).click();
				jQuery(`#myModal_${productId}`).show();
			});
		}
		if (jQuery(`.cst_pagination_cvr_${productId}`).length > 0) {
			jQuery(`.cst_pagination_cvr_${productId} ul li`).on('click', function() {
				paging(this);
			});
		}
	}
});
