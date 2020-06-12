<?php if (!empty($query) and $query instanceof \WP_Query and $base instanceof \RIO\Base) {

	if (empty($rating_count)) {
		$rating_count = 0;
	}

	if (empty($rating_value)) {
		$rating_value = 0;
	}

	$items = $query->posts;

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
						<div class="votes"><?php echo $rating_count . ' ' . ($rating_count == 1 ? 'Review' : 'Reviews'); ?></div>
					</div>
				</div>
			<?php } ?>

			<div class="items">
				<?php foreach ($items as $item) { ?>
					<?php $base->getTemplate('review', array('item' => $item)); ?>
				<?php } ?>
			</div>

		<?php } else { ?>

			<div class="message">
				There are no reviews at the moment :(
			</div>

		<?php } ?>

	</div>

<?php } ?>