<?php
/**
 * Customers Site List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Site List Table class.
 *
 * @since 2.0.0
 */
class Memberships_Site_List_Table extends Customers_Site_List_Table {

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'responsive' => '',
		];

		return $columns;
	}

	/**
	 * Renders the inside column responsive.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item The item being rendered.
	 * @return void
	 */
	public function column_responsive($item): void {

		$redirect = current_user_can('wu_edit_sites') ? 'wp-ultimo-edit-site' : 'wp-ultimo-sites';

		echo wu_responsive_table_row(
			[
				'id'     => $item->get_id(),
				'title'  => $item->get_title(),
				'url'    => wu_network_admin_url(
					$redirect,
					[
						'id' => $item->get_id(),
					]
				),
				'image'  => $this->column_featured_image_id($item),
				'status' => $this->column_type($item),
			],
			[
				'link'      => [
					'icon'  => 'dashicons-wu-link1 wu-align-middle wu-mr-1',
					'label' => __('Visit Site', 'wp-multisite-waas'),
					'value' => __('Homepage', 'wp-multisite-waas'),
					'url'   => $item->get_active_site_url(),
				],
				'dashboard' => [
					'icon'  => 'dashicons-wu-browser wu-align-middle wu-mr-1',
					'label' => __('Go to the Dashboard', 'wp-multisite-waas'),
					'value' => __('Dashboard', 'wp-multisite-waas'),
					'url'   => get_admin_url($item->get_id()),
				],
			],
			[
				'date_created' => [
					'icon'  => 'dashicons-wu-calendar1 wu-align-middle wu-mr-1',
					'label' => '',
					'value' => $item->get_type() === 'pending' ? __('Not Available', 'wp-multisite-waas') : sprintf(__('Created %s', 'wp-multisite-waas'), wu_human_time_diff(strtotime((string) $item->get_date_registered()))),
				],
			]
		);
	}
}
