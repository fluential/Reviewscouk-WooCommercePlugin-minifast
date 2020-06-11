<?php

class RIO_Admin {

	public $log;

	public $base;

	public $slug = 'rio_settings';

	public function __construct(RIO_Base $base) {

		$this->log = $base->log;

		$this->base = $base;

		add_action('admin_menu', array($this, 'admin_menu'), 100);

		add_action('admin_init', array($this, 'register_settings'));

	}

	/* Add an item to the admin menu */

	public function admin_menu() {

		add_submenu_page('tools.php', 'Reviews.io Synchronization', 'Reviews.io', 'administrator', $this->slug, array($this, 'settings_page'));

	}


	/* Output the settings page */

	public function settings_page() {

		$logs = $this->log->get();

		$offset = intval(get_option('gmt_offset') * HOUR_IN_SECONDS);

		?>

		<div class="wrap">

			<h1>Reviews.io Synchronization</h1>

			<form method="post" action="options.php">

				<?php settings_fields($this->base->id); ?>

				<?php do_settings_sections($this->slug); ?>

				<table class="form-table">

					<?php $this->output_settings(); ?>

				</table>

				<?php submit_button(); ?>

			</form>

			<h2>Latest Synchronization Report</h2>

			<?php if ($logs) {
				foreach ($logs as $type => $lines) {
					echo '<pre>';
					echo implode("\n", $lines);
					echo '</pre>';
				}
			} else { ?>

				<p>No data available</p>

			<?php } ?>

			<?php if ($timestamp = wp_next_scheduled($this->base->event)) { ?>

				<h2>Upcoming Synchronization</h2>

				<p>Next synchronization is scheduled on: <?php echo date('F j, Y, H:i:s', $timestamp + $offset); ?></p>

				<p>
					<a class="button button-primary" href="<?php echo admin_url('admin.php?page=' . $this->slug . '&sync-run'); ?>">Launch Now</a>
				</p>

			<?php } ?>


		</div>

		<?php

	}


	/* Register plugin settings for automated form handling */

	public function register_settings() {

		$settings = $this->base->getSettings();

		foreach ($settings as $setting => $value) {

			if (empty($value['validation'])) {
				register_setting($this->base->id, $setting);
			} else {
				register_setting($this->base->id, $setting, array('sanitize_callback' => $value['validation']));
			}

		}

	}


	/* Output fields on the plugin settings page */

	function output_settings() {

		$settings = $this->base->getSettings();

		foreach ($settings as $setting => $value) { ?>

			<tr>
				<th scope="row"><?php echo $value['name']; ?></th>
				<td>
					<?php if ($value['type'] == 'textarea') { ?>

						<textarea id="<?php echo $setting; ?>" name="<?php echo $setting; ?>" cols="80" rows="8"><?php echo $value['value']; ?></textarea>

					<?php } elseif ($value['type'] == 'checkbox' and !empty($value['values']) and is_array($value['value'])) { ?>

						<?php foreach ($value['values'] as $key => $label) { ?>
							<label>
								<input type="checkbox" value="<?php echo $key; ?>" name="<?php echo $setting; ?>[]"<?php echo(in_array($key, $value['value']) ? ' checked="checked"' : ''); ?> />
								<?php echo $label; ?>
							</label>
							<br />
						<?php } ?>

					<?php } elseif ($value['type'] == 'radio' and !empty($value['values'])) { ?>

						<?php foreach ($value['values'] as $key => $label) { ?>
							<label>
								<input type="radio" value="<?php echo $key; ?>" name="<?php echo $setting; ?>"<?php echo(($value['value'] == $key) ? ' checked="checked"' : ''); ?> />
								<?php echo $label; ?>
							</label>
							<br />
						<?php } ?>

					<?php } elseif ($value['type'] == 'select' and !empty($value['values'])) { ?>

						<select name="<?php echo $setting; ?>">
							<?php foreach ($value['values'] as $key => $label) { ?>
								<option value="<?php echo $key; ?>" <?php echo ($value['value'] == $key) ? ' selected="selected"' : ''; ?>><?php echo $label; ?></option>
							<?php } ?>
						</select>

					<?php } elseif ($value['type'] == 'number') { ?>

						<input type="text" name="<?php echo $setting; ?>" value="<?php echo $value['value']; ?>" size="4" step="1" />

					<?php } elseif ($value['type'] == 'table' and is_callable($value['callback'])) { ?>

						<?php $value['callback'](); ?>

					<?php } else { ?>

						<input type="text" name="<?php echo $setting; ?>" value="<?php echo $value['value']; ?>" size="70" />

					<?php } ?>

					<?php if (!empty($value['hint'])) { ?>
						<p><?php echo $value['hint']; ?></p>
					<?php } ?>

				</td>
			</tr>

			<?php

		}

	}

}