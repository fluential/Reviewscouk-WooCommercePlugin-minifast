<?php if (!empty($item) and $item instanceof WP_Post) {

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

	if (empty($gravatar)) {
		$gravatar = 'c8e57bdf509598c0a214f7b5c0b80bb3';
	}

	$name = $first_name;

	if ($last_name) {
		$name .= ' ' . $last_name;
	}

	$name = trim($name);

	?>

	<div class="item">

		<div class="author">

			<div class="avatar">
				<img src="https://www.gravatar.com/avatar/<?php echo $gravatar; ?>?s=160" alt="<?php echo esc_attr($name); ?>">
				<?php if ($verified) { ?>
					<div class="verified" title="Verified Buyer"></div>
				<?php } ?>
			</div>

			<div class="details">

				<div class="name"><?php echo $name; ?></div>

				<?php if ($rating) { ?>
					<div class="stars">
						<span style="width: <?php echo $rating * 20; ?>%;"></span>
					</div>
				<?php } ?>

				<div class="date">
					<?php echo date('d M Y', $date); ?>
				</div>

			</div>

		</div>

		<div class="content">
			<?php echo apply_filters('the_content', $item->post_content); ?>
		</div>

	</div>

<?php }