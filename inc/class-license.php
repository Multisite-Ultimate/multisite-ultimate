<?php

namespace WP_Ultimo;

defined( 'ABSPATH' ) || exit;

class License {
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * This exists to maintain compatibility with Addons
	 *
	 * @return null
	 */
	public function get_license_key() {
		return null;
	}
}
