<?php if (!empty($query) and $query instanceof \WP_Query and $base instanceof \RIO\Base) {

	$items = $query->posts;

	$rating_count = 0;
	$rating_value = 0;

	if ($items) {

		foreach ($items as $item) {
			$rating_value += floatval(get_post_meta($item->ID, 'review_rating', true));
			$rating_count++;
		}

		$rating_value = floatval($rating_value / $rating_count);

	}

	?>

	<div class="reviews_box">

		<?php if (!empty($title)) { ?>
			<h2><?php echo $title; ?></h2>
		<?php } ?>
		
		<?php if ($query->have_posts()) { ?>

			<?php if ($rating_count > 0) { ?>
				<div class="stats">
					<div class="number">
						<?php echo number_format(floatval($rating_value), 1, '.', ''); ?>
					</div>
					<div class="rating">
						<div class="stars">
							<span style="width: <?php echo $rating_value * 20; ?>%;"></span>
						</div>
						<div class="votes"><?php echo sprintf(_n('%s Review', '%s Reviews', $rating_count, 'rio'), $rating_count); ?></div>
					</div>
					<?php if (!empty($link)) { ?>
						<a href="<?php echo esc_url($link); ?>" class="logo" rel="nofollow" title="Reviews.io"></a>
					<?php } ?>
				</div>
			<?php } ?>

			<div class="items">
				<?php foreach ($items as $item) { ?>
					<?php $base->getTemplate('review', array('item' => $item, 'base' => $base)); ?>
				<?php } ?>
			</div>

		<?php } else { ?>

			<div class="message">
				<?php echo __('There are no reviews at the moment :(', 'rio'); ?>
			</div>

		<?php } ?>

		<?php if (!empty($loader) and is_string($loader)) { ?>
			<?php echo $loader; ?>
		<?php } ?>
		
	</div>
	
<?php } ?>