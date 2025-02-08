<?php
/**
 * Creates a cart with the parameters of the purchase being placed.
 *
 * @package WP_Ultimo
 * @subpackage Order
 * @since 2.0.0
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_Ultimo\Checkout\Signup_Fields\Base_Signup_Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Creates an cart with the parameters of the purchase being placed.
 *
 * @package WP_Ultimo
 * @subpackage Checkout
 * @since 2.0.0
 */
class Signup_Field_Password extends Base_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type() {

		return 'password';
	}

	/**
	 * Returns if this field should be present on the checkout flow or not.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_required() {

		return true;
	}

	/**
	 * Is this a user-related field?
	 *
	 * If this is set to true, this field will be hidden
	 * when the user is already logged in.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_user_field() {

		return true;
	}

	/**
	 * Requires the title of the field/element type.
	 *
	 * This is used on the Field/Element selection screen.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Password', 'wp-ultimo');
	}

	/**
	 * Returns the description of the field/element.
	 *
	 * This is used as the title attribute of the selector.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Adds a password field, with options for enforcing password strength and adding password confirmation field. This password is then used to create the WordPress user.', 'wp-ultimo');
	}

	/**
	 * Returns the tooltip of the field/element.
	 *
	 * This is used as the tooltip attribute of the selector.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_tooltip() {

		return __('Adds a password field, with options for enforcing password strength and adding password confirmation field. This password is then used to create the WordPress user.', 'wp-ultimo');
	}

	/**
	 * Returns the icon to be used on the selector.
	 *
	 * Can be either a dashicon class or a wu-dashicon class.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_icon() {

		return 'dashicons-wu-lock1';
	}

	/**
	 * Returns the default values for the field-elements.
	 *
	 * This is passed through a wp_parse_args before we send the values
	 * to the method that returns the actual fields for the checkout form.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'password_confirm_field' => false,
			'password_confirm_label' => __('Confirm Password', 'wp-ultimo'),
		];
	}

	/**
	 * List of keys of the default fields we want to display on the builder.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function default_fields() {

		return [
			'name',
			'placeholder',
			'tooltip',
		];
	}

	/**
	 * If you want to force a particular attribute to a value, declare it here.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function force_attributes() {

		return [
			'id'       => 'password',
			'required' => true,
		];
	}

	/**
	 * Returns the list of additional fields specific to this type.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {

		return [
			'password_strength_meter' => [
				'type'  => 'toggle',
				'title' => __('Display Password Strength Meter', 'wp-ultimo'),
				'desc'  => __('Adds a password strength meter below the password field. Enabling this option also enforces passwords to be strong.', 'wp-ultimo'),
				'value' => 1,
			],
			'password_confirm_field'  => [
				'type'  => 'toggle',
				'title' => __('Display Password Confirm Field', 'wp-ultimo'),
				'desc'  => __('Adds a "Confirm your Password" field below the default password field to reduce the chance or making a mistake.', 'wp-ultimo'),
				'value' => 1,
			],
		];
	}

	/**
	 * Returns the field/element actual field array to be used on the checkout form.
	 *
	 * @since 2.0.0
	 *
	 * @param array $attributes Attributes saved on the editor form.
	 * @return array An array of fields, not the field itself.
	 */
	public function to_fields_array($attributes) {
		/*
		 * Logged in user, bail.
		 */
		if (is_user_logged_in()) {
			return [];
		}

		$checkout_fields = [];

		$checkout_fields['password'] = [
			'type'              => 'password',
			'id'                => 'password',
			'name'              => $attributes['name'],
			'placeholder'       => $attributes['placeholder'],
			'tooltip'           => $attributes['tooltip'],
			'meter'             => $attributes['password_strength_meter'],
			'required'          => true,
			'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
			'classes'           => wu_get_isset($attributes, 'element_classes', ''),
			'html_attr'         => [
				'autocomplete' => 'new-password',
			],
			'wrapper_html_attr' => [
				'style' => $this->calculate_style_attr(),
			],
		];

		if ($attributes['password_confirm_field']) {
			$checkout_fields['password_conf'] = [
				'type'              => 'password',
				'id'                => 'password_conf',
				'name'              => $attributes['password_confirm_label'],
				'placeholder'       => '',
				'tooltip'           => '',
				'meter'             => false,
				'required'          => true,
				'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
				'classes'           => wu_get_isset($attributes, 'element_classes', ''),
				'html_attr'         => [
					'autocomplete' => 'new-password',
				],
				'wrapper_html_attr' => [
					'style' => $this->calculate_style_attr(),
				],
			];
		}

		return $checkout_fields;
	}
}
