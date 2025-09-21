<?php
/**
 * Ultimate Multisite overview collector.
 *
 * @package query-monitor
 * @since 2.0.11
 */

namespace WP_Ultimo\Development\Query_Monitor\Collectors;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Every QM Panel needs a collector.
 *
 * @since 2.0.11
 */
class Collector_Overview extends \QM_Collector {

	/**
	 * Sets the id of the collector.
	 *
	 * @since 2.0.11
	 * @var string
	 */
	public $id = 'wp-ultimo';

	/**
	 * Process the collection.
	 *
	 * Here, we just need to add items to the
	 * data property array.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public function process(): void {

		$this->data = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
	}
}
