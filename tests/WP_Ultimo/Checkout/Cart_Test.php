<?php

namespace WP_Ultimo\Checkout;

use WP_Error;
use WP_Ultimo\Models\Customer;
use WP_UnitTestCase;


class Cart_Test extends WP_UnitTestCase {

	private static Customer $customer;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_customers");

		self::$customer = wu_create_customer(
			[
				'username' => 'testuser2',
				'email'    => 'test2@example.com',
				'password' => 'password123',
			]
		);
	}
	/**
	 * Test if the constructor correctly initializes the default attributes.
	 */
	public function test_constructor_initializes_defaults() {
		$args = [];
		$cart = new Cart($args);

		$this->assertEquals('new', $cart->get_cart_type());
		$this->assertEmpty($cart->get_country());
		$this->assertEmpty($cart->get_currency());
		$this->assertEmpty($cart->get_customer());
	}

	/**
	 * Test if the constructor correctly assigns custom attributes passed in an array.
	 */
	public function test_constructor_assigns_custom_attributes() {
		$args = [
			'cart_type' => 'new',
			'country'   => 'US',
			'state'     => 'CA',
			'city'      => 'Los Angeles',
			'currency'  => 'USD',
		];
		$cart = new Cart($args);

		$this->assertEquals('new', $cart->get_cart_type());
		$this->assertEquals('US', $cart->get_country());
		$this->assertEquals('USD', $cart->get_currency());
	}

	/**
	 * Test if the constructor initializes the errors property as an instance of WP_Error.
	 */
	public function test_constructor_initializes_errors() {
		$args = [];
		$cart = new Cart($args);

		$this->assertInstanceOf(WP_Error::class, $cart->get_errors());
	}

	/**
	 * Test if the constructor triggers the setup actions.
	 */
	public function test_constructor_triggers_setup_actions() {
		$args          = [];
		$action_called = false;

		add_action(
			'wu_cart_setup',
			function () use (&$action_called) {
				$action_called = true;
			}
		);

		new Cart($args);

		$this->assertTrue($action_called);
	}

	/**
	 * Test handling invalid cart type in input arguments.
	 */
	public function test_constructor_handles_invalid_cart_type() {
		$args = [
			'cart_type' => 'invalid_type',
		];
		$cart = new Cart($args);

		$this->assertEquals('new', $cart->get_cart_type()); // Fallback to default value
	}

	/**
	 * Test if the constructor correctly sets the attributes field.
	 */
	public function test_constructor_sets_attributes_field() {
		$args = [
			'cart_type' => 'upgrade',
			'currency'  => 'EUR',
		];
		$cart = new Cart($args);

		$attributes = $cart->get_param('cart_type');
		$this->assertEquals('upgrade', $attributes);

		$attributes = $cart->get_param('currency');
		$this->assertEquals('EUR', $attributes);
	}

	public function test_create_from_membership() {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());
		$product = wu_create_product(
			[
				'name'                => 'Test Product',
				'slug'                => 'test-product',
				'amount'              => 50.00,
				'recurring'           => true,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		// Create a second product for upgrade/downgrade scenarios
		$second_product = wu_create_product(
			[
				'name'                => 'Second Product',
				'slug'                => 'second-product',
				'amount'              => 75.00,
				'recurring'           => true,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		// Create membership
		$membership = wu_create_membership(
			[
				'customer_id'     => self::$customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => 'active',
				'recurring'       => true,
				'date_expiration' => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
				'amount'          => 50.0,
				'initial_amount'  => 50.0,
			]
		);

		$cart_args = [
			'cart_type'     => 'upgrade',
			'products'      => [$second_product->get_id()],
			'duration'      => 1,
			'duration_unit' => 'month',
			'membership_id' => $membership->get_id(),
			'payment_id'    => false,
			'discount_code' => false,
			'auto_renew'    => true,
			'country'       => 'US',
			'state'         => 'NY',
			'city'          => 'New York',
			'currency'      => 'USD',
		];

		$cart       = new \WP_Ultimo\Checkout\Cart($cart_args);
		$line_items = $cart->get_line_items();

		$this->assertNotEmpty($line_items);
		$this->assertContainsOnlyInstancesOf(Line_Item::class, $line_items);
		$this->assertCount(2, $line_items);
		foreach ($line_items as $line_item) {
			$this->assertTrue(in_array($line_item->get_type(), ['product', 'credit']));
			if ( 'credit' === $line_item->get_type() ) {
				$this->assertEquals(-50.0, $line_item->get_total());
			}
		}
	}

	/**
	 * Test domain mapping downgrade validation when domains exceed new plan limit.
	 */
	public function test_domain_mapping_downgrade_validation_over_limit() {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		// Create a high-tier product with unlimited domains
		$high_tier_product = wu_create_product([
			'name'                => 'High Tier Product',
			'slug'                => 'high-tier-product',
			'amount'              => 100.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => true, // unlimited
				],
			],
		]);

		// Create a low-tier product with 1 domain limit
		$low_tier_product = wu_create_product([
			'name'                => 'Low Tier Product',
			'slug'                => 'low-tier-product',
			'amount'              => 25.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => 1, // only 1 domain allowed
				],
			],
		]);

		// Create a site with membership
		$site = wu_create_site([
			'title'      => 'Test Site for Domain Validation',
			'url'        => 'https://domain-test.example.com',
			'template_id' => 1,
		]);

		$membership = wu_create_membership([
			'plan_id'            => $high_tier_product->get_id(),
			'customer_id'        => $customer->get_id(),
			'amount'             => $high_tier_product->get_amount(),
			'duration'           => $high_tier_product->get_duration(),
			'duration_unit'      => $high_tier_product->get_duration_unit(),
			'recurring'          => $high_tier_product->is_recurring(),
			'date_created'       => wu_get_current_time('mysql', true),
			'date_activated'     => wu_get_current_time('mysql', true),
			'status'             => 'active',
		]);

		// Add the site to the membership
		$membership->add_site($site);

		// Create 3 domains for the site (more than the low tier limit of 1)
		$domain1 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom1.example.com',
			'active'         => true,
			'primary_domain' => true,
			'stage'          => 'done',
		]);

		$domain2 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom2.example.com',
			'active'         => true,
			'primary_domain' => false,
			'stage'          => 'done',
		]);

		$domain3 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom3.example.com',
			'active'         => true,
			'primary_domain' => false,
			'stage'          => 'done',
		]);

		// Create a downgrade cart
		$cart_args = [
			'cart_type'     => 'downgrade',
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'plan_id'       => $low_tier_product->get_id(),
			'duration'      => $low_tier_product->get_duration(),
			'duration_unit' => $low_tier_product->get_duration_unit(),
			'auto_renew'    => $low_tier_product->is_recurring(),
		];

		$cart = new Cart($cart_args);
		$validation_errors = $cart->validate();

		// Cart should have validation errors due to domain limit
		$this->assertInstanceOf(\WP_Error::class, $validation_errors);
		$this->assertArrayHasKey('overlimits', $validation_errors->errors);
		
		$error_messages = $validation_errors->get_error_messages('overlimits');
		$domain_error_found = false;
		foreach ($error_messages as $message) {
			if (strpos($message, 'custom domain') !== false) {
				$domain_error_found = true;
				break;
			}
		}
		$this->assertTrue($domain_error_found, 'Domain mapping validation error not found');

		// Clean up
		$domain1->delete();
		$domain2->delete();
		$domain3->delete();
		$site->delete();
		$membership->delete();
		$high_tier_product->delete();
		$low_tier_product->delete();
	}

	/**
	 * Test domain mapping downgrade validation when domains are within new plan limit.
	 */
	public function test_domain_mapping_downgrade_validation_within_limit() {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		// Create a high-tier product with unlimited domains
		$high_tier_product = wu_create_product([
			'name'                => 'High Tier Product 2',
			'slug'                => 'high-tier-product-2',
			'amount'              => 100.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => true, // unlimited
				],
			],
		]);

		// Create a mid-tier product with 3 domain limit
		$mid_tier_product = wu_create_product([
			'name'                => 'Mid Tier Product',
			'slug'                => 'mid-tier-product',
			'amount'              => 50.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => 3, // 3 domains allowed
				],
			],
		]);

		// Create a site with membership
		$site = wu_create_site([
			'title'      => 'Test Site for Domain Validation 2',
			'url'        => 'https://domain-test-2.example.com',
			'template_id' => 1,
		]);

		$membership = wu_create_membership([
			'plan_id'            => $high_tier_product->get_id(),
			'customer_id'        => $customer->get_id(),
			'amount'             => $high_tier_product->get_amount(),
			'duration'           => $high_tier_product->get_duration(),
			'duration_unit'      => $high_tier_product->get_duration_unit(),
			'recurring'          => $high_tier_product->is_recurring(),
			'date_created'       => wu_get_current_time('mysql', true),
			'date_activated'     => wu_get_current_time('mysql', true),
			'status'             => 'active',
		]);

		// Add the site to the membership
		$membership->add_site($site);

		// Create 2 domains for the site (within the mid tier limit of 3)
		$domain1 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom1-valid.example.com',
			'active'         => true,
			'primary_domain' => true,
			'stage'          => 'done',
		]);

		$domain2 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom2-valid.example.com',
			'active'         => true,
			'primary_domain' => false,
			'stage'          => 'done',
		]);

		// Create a downgrade cart
		$cart_args = [
			'cart_type'     => 'downgrade',
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'plan_id'       => $mid_tier_product->get_id(),
			'duration'      => $mid_tier_product->get_duration(),
			'duration_unit' => $mid_tier_product->get_duration_unit(),
			'auto_renew'    => $mid_tier_product->is_recurring(),
		];

		$cart = new Cart($cart_args);
		$validation_errors = $cart->validate();

		// Cart should NOT have domain validation errors
		if ($validation_errors instanceof \WP_Error) {
			$error_messages = $validation_errors->get_error_messages('overlimits');
			$domain_error_found = false;
			foreach ($error_messages as $message) {
				if (strpos($message, 'custom domain') !== false) {
					$domain_error_found = true;
					break;
				}
			}
			$this->assertFalse($domain_error_found, 'Domain mapping validation error should not be present');
		}

		// Clean up
		$domain1->delete();
		$domain2->delete();
		$site->delete();
		$membership->delete();
		$high_tier_product->delete();
		$mid_tier_product->delete();
	}

	/**
	 * Test domain mapping downgrade validation when domains are disabled in new plan.
	 */
	public function test_domain_mapping_downgrade_validation_disabled_in_new_plan() {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		// Create a high-tier product with unlimited domains
		$high_tier_product = wu_create_product([
			'name'                => 'High Tier Product 3',
			'slug'                => 'high-tier-product-3',
			'amount'              => 100.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => true, // unlimited
				],
			],
		]);

		// Create a basic product with no domains allowed
		$basic_product = wu_create_product([
			'name'                => 'Basic Product',
			'slug'                => 'basic-product',
			'amount'              => 10.00,
			'recurring'           => true,
			'duration'            => 1,
			'duration_unit'       => 'month',
			'type'                => 'plan',
			'pricing_type'        => 'paid',
			'active'              => true,
			'limitations'         => [
				'domain_mapping' => [
					'enabled' => true,
					'limit'   => false, // no domains allowed
				],
			],
		]);

		// Create a site with membership
		$site = wu_create_site([
			'title'      => 'Test Site for Domain Validation 3',
			'url'        => 'https://domain-test-3.example.com',
			'template_id' => 1,
		]);

		$membership = wu_create_membership([
			'plan_id'            => $high_tier_product->get_id(),
			'customer_id'        => $customer->get_id(),
			'amount'             => $high_tier_product->get_amount(),
			'duration'           => $high_tier_product->get_duration(),
			'duration_unit'      => $high_tier_product->get_duration_unit(),
			'recurring'          => $high_tier_product->is_recurring(),
			'date_created'       => wu_get_current_time('mysql', true),
			'date_activated'     => wu_get_current_time('mysql', true),
			'status'             => 'active',
		]);

		// Add the site to the membership
		$membership->add_site($site);

		// Create 1 domain for the site
		$domain1 = wu_create_domain([
			'blog_id'        => $site->get_id(),
			'domain'         => 'custom-disabled.example.com',
			'active'         => true,
			'primary_domain' => true,
			'stage'          => 'done',
		]);

		// Create a downgrade cart
		$cart_args = [
			'cart_type'     => 'downgrade',
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'plan_id'       => $basic_product->get_id(),
			'duration'      => $basic_product->get_duration(),
			'duration_unit' => $basic_product->get_duration_unit(),
			'auto_renew'    => $basic_product->is_recurring(),
		];

		$cart = new Cart($cart_args);
		$validation_errors = $cart->validate();

		// Cart should have validation errors due to domain limit being 0
		$this->assertInstanceOf(\WP_Error::class, $validation_errors);
		$this->assertArrayHasKey('overlimits', $validation_errors->errors);
		
		$error_messages = $validation_errors->get_error_messages('overlimits');
		$domain_error_found = false;
		foreach ($error_messages as $message) {
			if (strpos($message, 'custom domain') !== false) {
				$domain_error_found = true;
				break;
			}
		}
		$this->assertTrue($domain_error_found, 'Domain mapping validation error not found for disabled domains');

		// Clean up
		$domain1->delete();
		$site->delete();
		$membership->delete();
		$high_tier_product->delete();
		$basic_product->delete();
	}

	public static function tear_down_after_class() {
		global $wpdb;
		self::$customer->delete();
	}
}
