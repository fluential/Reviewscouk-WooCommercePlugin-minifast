<?php if (!empty($item) and $item instanceof WP_Post) {

	$review_id = get_post_meta($item->ID, 'review_id', true);

	$first_name = get_post_meta($item->ID, 'review_first_name', true);
	$last_name = get_post_meta($item->ID, 'review_last_name', true);

	$verified = get_post_meta($item->ID, 'review_verified', true);
	$gravatar = get_post_meta($item->ID, 'review_gravatar', true);
	$rating = get_post_meta($item->ID, 'review_rating', true);
	$date = get_post_meta($item->ID, 'review_date', true);

	if ($date) {
		$date = strtotime($date);
	} else {
		$date = time() - rand(0, 100) * 3600 * 24;
	}

	$name = $first_name;

	if ($last_name) {
		$name .= ' ' . $last_name;
	}

	$name = trim($name);

	?>

	<div class="item">

		<div class="info">

			<?php if ($gravatar) { ?>
				<div class="avatar">
					<img src="https://www.gravatar.com/avatar/<?php echo $gravatar; ?>?s=80" alt="<?php echo esc_attr($name); ?>">
				</div>
			<?php } ?>

			<div class="name">
				<?php echo $name; ?>
				<?php if ($verified) { ?>
					<div class="verified" title="Verified Buyer"></div>
				<?php } ?>
			</div>

			<?php if ($rating) { ?>
				<div class="stars">
					<span style="width: <?php echo $rating * 20; ?>%;"></span>
				</div>
			<?php } ?>

			<div class="date">
				<?php echo date('d M Y', $date); ?>
			</div>

		</div>

		<div class="content">
			<?php echo apply_filters('the_content', $item->post_content); ?>
		</div>

	</div>

<?php }