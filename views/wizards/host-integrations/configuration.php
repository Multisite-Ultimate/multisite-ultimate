<?php
/**
 * Host integrations configuration view.
 *
 * @since 2.0.0
 */
?>
<h1>
	<?php esc_html_e('We are almost there!', 'multisite-ultimate'); ?>
</h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4">
	<?php esc_html_e('You should have all the information we need in hand right now. The next step is to configure it.', 'multisite-ultimate'); ?>
</p>

<div class="wu-mt-6 wu--mx-4">

	<?php

	/**
	 * Renders the form.
	 */
	$form->render();

	?>

</div>

<!-- Submit Box -->
<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">

	<a
	href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'integrations'])); ?>"
	class="wu-self-center button button-large wu-float-left"
	>
	<?php esc_html_e('&larr; Cancel', 'multisite-ultimate'); ?>
	</a>

	<span class="wu-self-center wu-content-center">

	<button name="submit" value="0" class="button button-large">
		<?php esc_html_e('Add manually', 'multisite-ultimate'); ?>
	</button>

	<button name="submit" value="1" class="wu-ml-2 button button-primary button-large">
		<?php esc_html_e('Add automatically', 'multisite-ultimate'); ?>
	</button>

	</span>

</div>
<!-- End Submit Box -->

