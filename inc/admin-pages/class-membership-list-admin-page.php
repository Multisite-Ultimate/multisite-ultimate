<?php
/**
 * WP Multisite WaaS Membership Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Membership;
use WP_Ultimo\Database\Memberships\Membership_Status;

/**
 * WP Multisite WaaS Membership Admin Page.
 */
class Membership_List_Admin_Page extends List_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-memberships';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'wu_read_memberships',
	];

	/**
	 * Register ajax forms to handle adding new memberships.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Add new Membership
		 */
		wu_register_form(
			'add_new_membership',
			[
				'render'     => [$this, 'render_add_new_membership_modal'],
				'handler'    => [$this, 'handle_add_new_membership_modal'],
				'capability' => 'wu_edit_memberships',
			]
		);
	}

	/**
	 * Renders the add new customer modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_add_new_membership_modal(): void {

		$fields = [
			'customer_id'     => [
				'type'        => 'model',
				'title'       => __('Customer', 'wp-multisite-waas'),
				'placeholder' => __('Search Customer...', 'wp-multisite-waas'),
				'desc'        => __('The customer to attach this membership to.', 'wp-multisite-waas'),
				'html_attr'   => [
					'data-model'        => 'customer',
					'data-value-field'  => 'id',
					'data-label-field'  => 'display_name',
					'data-search-field' => 'display_name',
					'data-max-items'    => 1,
				],
			],
			'product_ids'     => [
				'type'        => 'model',
				'title'       => __('Products', 'wp-multisite-waas'),
				'placeholder' => __('Search Products...', 'wp-multisite-waas'),
				'desc'        => __('You can add multiples products to this membership.', 'wp-multisite-waas'),
				'tooltip'     => '',
				'html_attr'   => [
					'data-model'        => 'product',
					'data-value-field'  => 'id',
					'data-label-field'  => 'name',
					'data-search-field' => 'name',
					'data-max-items'    => 99,
				],
			],
			'status'          => [
				'type'        => 'select',
				'title'       => __('Status', 'wp-multisite-waas'),
				'placeholder' => __('Status', 'wp-multisite-waas'),
				'desc'        => __('The membership status.', 'wp-multisite-waas'),
				'tooltip'     => '',
				'value'       => Membership_Status::PENDING,
				'options'     => Membership_Status::to_array(),
			],
			'lifetime'        => [
				'type'      => 'toggle',
				'title'     => __('Lifetime', 'wp-multisite-waas'),
				'desc'      => __('Activate this toggle to mark the newly created membership as lifetime.', 'wp-multisite-waas'),
				'value'     => 1,
				'html_attr' => [
					'v-model' => 'lifetime',
				],
			],
			'date_expiration' => [
				'title'             => __('Expiration Date', 'wp-multisite-waas'),
				'desc'              => __('Set the expiration date of the membership to be created.', 'wp-multisite-waas'),
				'type'              => 'text',
				'date'              => true,
				'value'             => gmdate('Y-m-d', strtotime('+1 month')),
				'placeholder'       => '2020-04-04',
				'html_attr'         => [
					'wu-datepicker'   => 'true',
					'data-format'     => 'Y-m-d',
					'data-allow-time' => 'false',
				],
				'wrapper_html_attr' => [
					'v-show'  => '!lifetime',
					'v-cloak' => 1,
				],
			],
			'submit_button'   => [
				'type'            => 'submit',
				'title'           => __('Create Membership', 'wp-multisite-waas'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'add_new_membership',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app'  => 'add_new_membership',
					'data-on-load' => 'wu_initialize_datepickers',
					'data-state'   => wu_convert_to_state(
						[
							'lifetime' => 1,
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles creation of a new memberships.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_add_new_membership_modal(): void {

		global $wpdb;

		$products = wu_request('product_ids', '');

		$products = explode(',', (string) $products);

		if (empty($products)) {
			wp_send_json_error(
				new \WP_Error(
					'empty-products',
					__('Products can not be empty.', 'wp-multisite-waas')
				)
			);
		}

		$customer = wu_get_customer(wu_request('customer_id', 0));

		if (empty($customer)) {
			wp_send_json_error(
				new \WP_Error(
					'customer-not-found',
					__('The selected customer does not exist.', 'wp-multisite-waas')
				)
			);
		}

		$cart = new \WP_Ultimo\Checkout\Cart(
			[
				'products' => $products,
				'country'  => $customer->get_country(),
			]
		);

		$data = $cart->to_membership_data();

		$data['customer_id'] = $customer->get_id();

		$data['status'] = wu_request('status');

		$date_expiration = gmdate('Y-m-d 23:59:59', strtotime((string) wu_request('date_expiration')));

		$maybe_lifetime = wu_request('lifetime');

		if ($maybe_lifetime) {
			$date_expiration = null;
		}

		$data['date_expiration'] = $date_expiration;

		$membership = wu_create_membership($data);

		if (is_wp_error($membership)) {
			wp_send_json_error($membership);
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-edit-membership',
					[
						'id' => $membership->get_id(),
					]
				),
			]
		);
	}

	/**
	 * Returns an array with the labels for the edit page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function get_labels() {

		return [
			'deleted_message' => __('Membership removed successfully.', 'wp-multisite-waas'),
			'search_label'    => __('Search Membership', 'wp-multisite-waas'),
		];
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Memberships', 'wp-multisite-waas');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Memberships', 'wp-multisite-waas');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Memberships', 'wp-multisite-waas');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		return [
			[
				'label'   => __('Add Membership'),
				'icon'    => 'wu-circle-with-plus',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('add_new_membership'),
			],
		];
	}

	/**
	 * Loads the list table for this particular page.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\List_Tables\Base_List_Table
	 */
	public function table() {

		return new \WP_Ultimo\List_Tables\Membership_List_Table();
	}
}
