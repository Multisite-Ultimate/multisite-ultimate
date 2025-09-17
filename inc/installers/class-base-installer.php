<?php
/**
 * Ultimate Multisite base Installer Class.
 *
 * @package WP_Ultimo
 * @subpackage Installers
 * @since 2.0.0
 */

namespace WP_Ultimo\Installers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite base Installer Class.
 *
 * @since 2.0.0
 */
class Base_Installer {

	/**
	 * Keeps track of the current step.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $current_step;

	/**
	 * Returns the list of migration steps.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_steps() {

		return [];
	}

	/**
	 * Runs through all the steps to see if they are all done or not.
	 *
	 * @since 2.0.0
	 *
	 * @return boolean
	 */
	public function all_done() {

		$all_done = true;

		foreach ($this->get_steps() as $step) {
			if (false === $step['done']) {
				$all_done = false;
			}
		}

		return $all_done;
	}

	/**
	 * Handles the installer.
	 *
	 * This wraps the installer into a try catch block
	 * so we can use that to rollback on database entries.
	 *
	 * @since 2.0.0
	 *
	 * @param bool|\WP_Error $status Status of the installer.
	 * @param string         $installer The installer name.
	 * @param object         $wizard Wizard class.
	 * @return bool|\WP_Error
	 */
	public function handle($status, $installer, $wizard) {

		global $wpdb;

		$callable = [$this, "_install_{$installer}"];

		$callable = apply_filters("wu_installer_{$installer}_callback", $callable, $installer);

		/*
		* No installer on this class.
		*/
		if ( ! is_callable($callable)) {
			return $status;
		}

		try {
			$wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			call_user_func($callable);
		} catch (\Throwable $e) {
			$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return new \WP_Error(esc_html($installer), esc_html($e->getMessage()));
		}

		$wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $status;
	}
}
