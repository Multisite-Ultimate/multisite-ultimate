<?php
/**
 * Discount_Code List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Discount_Code List Table class.
 *
 * @since 2.0.0
 */
class Discount_Code_List_Table extends Base_List_Table {

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Discount_Codes\Discount_Code_Query::class;

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => __('Discount Code', 'wp-multisite-waas'),  // singular name of the listed records
				'plural'   => __('Discount Codes', 'wp-multisite-waas'), // plural name of the listed records
				'ajax'     => true,                              // does this table support ajax?
				'add_new'  => [
					'url'     => wu_network_admin_url('wp-ultimo-edit-discount-code'),
					'classes' => '',
				],
			]
		);
	}

	/**
	 * Displays the content of the name column.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Ultimo\Models\Discount_Code $item Discount_Code object.
	 */
	public function column_name($item): string {

		$url_atts = [
			'id'    => $item->get_id(),
			'model' => 'discount_code',
		];

		$title = sprintf('<a href="%s"><strong>%s</strong></a>', wu_network_admin_url('wp-ultimo-edit-discount-code', $url_atts), $item->get_name());

		$actions = [
			'edit'   => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-discount-code', $url_atts), __('Edit', 'wp-multisite-waas')),
			'delete' => sprintf(
				'<a title="%s" class="wubox" href="%s">%s</a>',
				__('Delete', 'wp-multisite-waas'),
				wu_get_form_url(
					'delete_modal',
					$url_atts
				),
				__('Delete', 'wp-multisite-waas')
			),
		];

		return $title . $this->row_actions($actions);
	}

	/**
	 * Displays the content of the value column.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Ultimo\Models\Discount_Code $item Discount_Code object.
	 *
	 * @return string
	 */
	public function column_value($item) {

		if ( ! $item->get_value()) {
			return __('No Discount', 'wp-multisite-waas');
		}

		$value = wu_format_currency($item->get_value());

		if ($item->get_type() === 'percentage') {
			$value = number_format($item->get_value(), 0) . '%';
		}

		// translators: placeholder is the amount of discount. e.g. 10% or $5.
		return sprintf(__('%s OFF', 'wp-multisite-waas'), $value);
	}

	/**
	 * Displays the content of the setup fee value column.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Ultimo\Models\Discount_Code $item Discount_Code object.
	 *
	 * @return string
	 */
	public function column_setup_fee_value($item) {

		if ( ! $item->get_setup_fee_value()) {
			return __('No Discount', 'wp-multisite-waas');
		}

		$value = wu_format_currency($item->get_setup_fee_value());

		if ($item->get_setup_fee_type() === 'percentage') {
			$value = number_format($item->get_setup_fee_value()) . '%';
		}

		// translators: placeholder is the amount of discount. e.g. 10% or $5.
		return sprintf(__('%s OFF', 'wp-multisite-waas'), $value);
	}

	/**
	 * Displays the use limitations.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Ultimo\Models\Discount_Code $item Discount_Code object.
	 * @return string
	 */
	public function column_uses($item) {

		// translators: the placeholder is the number of times this coupon was used.
		$html = sprintf(__('Used %d time(s)', 'wp-multisite-waas'), $item->get_uses());

		if ($item->get_max_uses() > 0) {

			// translators: the placeholder is the number of times this coupon can be used before becoming inactive.
			$html .= '<small class="wu-block">' . sprintf(__('Allowed uses: %d', 'wp-multisite-waas'), $item->get_max_uses()) . '</span>';
		} else {
			$html .= '<small class="wu-block">' . __('No Limits', 'wp-multisite-waas') . '</span>';
		}

		return $html;
	}

	/**
	 * Shows the code as a tag.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Ultimo\Models\Discount_Code $item Discount_Code object.
	 * @return string
	 */
	public function column_coupon_code($item) {

		$code = $item->get_code();

		$html = '<span class="wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono wu-bg-gray-200 wu-text-gray-700">' . strtoupper((string) $code) . '</span>';

		$valid = $item->is_valid();

		if (is_wp_error($valid)) {
			$html .= sprintf('<small class="wu-block wu-sans" %s>%s</small>', wu_tooltip_text($valid->get_error_message()), __('Inactive', 'wp-multisite-waas'));
		}

		return $html;
	}

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'cb'              => '<input type="checkbox" />',
			'name'            => __('Name', 'wp-multisite-waas'),
			'coupon_code'     => __('Code', 'wp-multisite-waas'),
			'uses'            => __('Uses', 'wp-multisite-waas'),
			'value'           => __('Value', 'wp-multisite-waas'),
			'setup_fee_value' => __('Setup Fee Value', 'wp-multisite-waas'),
			'date_expiration' => __('Dates', 'wp-multisite-waas'),
		];

		return $columns;
	}

	/**
	 * Returns the filters for this page.
	 *
	 * @since 2.0.0
	 */
	public function get_filters(): array {

		return [
			'filters'      => [],
			'date_filters' => [],
		];
	}
}
