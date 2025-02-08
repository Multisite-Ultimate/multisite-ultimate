<?php

namespace WP_Ultimo\Models;

use WP_Error;
use WP_UnitTestCase;

class Discount_Code_Test extends WP_UnitTestCase {

	/**
	 * Tests that a valid discount code returns true.
	 */
	public function test_is_valid_active_discount_code() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests that an inactive discount code returns an error.
	 */
	public function test_is_valid_inactive_discount_code() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(false);

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code with max uses returns an error after being used maximum times.
	 */
	public function test_is_valid_max_uses_exceeded() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(5);
		$discount_code->set_uses(5);

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals(
			'This discount code was already redeemed the maximum amount of times allowed.',
			$result->get_error_message()
		);
	}

	/**
	 * Tests that a discount code before the start date is invalid.
	 */
	public function test_is_valid_before_start_date() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_start(date('Y-m-d H:i:s', strtotime('+1 day')));

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code after the expiration date is invalid.
	 */
	public function test_is_valid_after_expiration_date() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_expiration(date('Y-m-d H:i:s', strtotime('-1 day')));

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code limited to specific products returns true for allowed products.
	 */
	public function test_is_valid_for_allowed_product() {
		$product_id    = 123;
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products(array($product_id));

		$result = $discount_code->is_valid($product_id);

		$this->assertTrue($result);
	}

	/**
	 * Tests that a discount code limited to specific products returns an error for disallowed products.
	 */
	public function test_is_valid_for_disallowed_product() {
		$allowed_product_id    = 123;
		$disallowed_product_id = 456;
		$discount_code         = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products(array($allowed_product_id));

		$result = $discount_code->is_valid($disallowed_product_id);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code with no product limits returns true.
	 */
	public function test_is_valid_no_product_limits() {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(false);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}
}
