<?php
/**
 * Adds the Payment_Methods_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Payment_Methods_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * Something simple, without prefixes, like 'checkout', or 'pricing-tables'.
	 *
	 * This is used to construct shortcodes by prefixing the id with 'wu_'
	 * e.g. an id checkout becomes the shortcode 'wu_checkout' and
	 * to generate the Gutenberg block by prefixing it with 'wp-ultimo/'
	 * e.g. checkout would become the block 'wp-ultimo/checkout'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'payment-methods';

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 * @return string
	 */
	public function get_icon($context = 'block') {

		if ('elementor' === $context) {
			return 'eicon-info-circle-o';
		}

		return 'fa fa-search';
	}

	/**
	 * The title of the UI element.
	 *
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'multisite-ultimate').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Payment Methods', 'multisite-ultimate');
	}

	/**
	 * The description of the UI element.
	 *
	 * This is also used on the Gutenberg block list
	 * to explain what this block is about.
	 * You should return a string with the localized title.
	 * e.g. return __('Adds a checkout form to the page', 'multisite-ultimate').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Adds a checkout form block to the page.', 'multisite-ultimate');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * If you plan to add Gutenberg controls to this block,
	 * you'll need to return an array of fields, following
	 * our fields interface (@see inc/ui/class-field.php).
	 *
	 * You can create new Gutenberg panels by adding fields
	 * with the type 'header'. See the Checkout Elements for reference.
	 *
	 * @see inc/ui/class-checkout-element.php
	 *
	 * Return an empty array if you don't have controls to add.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function fields() {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'multisite-ultimate'),
			'desc'  => __('General', 'multisite-ultimate'),
			'type'  => 'header',
		];

		$fields['password_strength'] = [
			'type'    => 'toggle',
			'title'   => __('Password Strength Meter', 'multisite-ultimate'),
			'desc'    => __('Set this customer as a VIP.', 'multisite-ultimate'),
			'tooltip' => '',
			'value'   => 1,
		];

		$fields['apply_styles'] = [
			'type'    => 'toggle',
			'title'   => __('Apply Styles', 'multisite-ultimate'),
			'desc'    => __('Set this customer as a VIP.', 'multisite-ultimate'),
			'tooltip' => '',
			'value'   => 1,
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * Return an array of strings with keywords describing this
	 * element. Gutenberg uses this to help customers find blocks.
	 *
	 * e.g.:
	 * return array(
	 *  'Multisite Ultimate',
	 *  'Payment Methods',
	 *  'Form',
	 *  'Cart',
	 * );
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Multisite Ultimate',
			'Payment Methods',
			'Form',
			'Cart',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * If you are planning to add controls using the fields,
	 * it might be a good idea to use this method to set defaults
	 * for the parameters you are expecting.
	 *
	 * These defaults will be used inside a 'wp_parse_args' call
	 * before passing the parameters down to the block render
	 * function and the shortcode render function.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [];
	}

	/**
	 * The content to be output on the screen.
	 *
	 * Should return HTML markup to be used to display the block.
	 * This method is shared between the block render method and
	 * the shortcode implementation.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null): void {

		echo 'lol';
	}
}
