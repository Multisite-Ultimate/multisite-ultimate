<?php
/**
 * General Compatibility Layer
 *
 * Handles General Support
 *
 * @package WP_Ultimo
 * @subpackage Compat/General_Compat
 * @since 2.0.0
 */

namespace WP_Ultimo\Compat;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles General Support
 *
 * @since 2.0.0
 */
class General_Compat {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		/**
		 * Woocommerce
		 *
		 * Removes the default woocommerce hook on switch_blog to another more performant
	 *
		 * @see https://github.com/woocommerce/woocommerce/pull/60174
		 */
		add_action('woocommerce_loaded', [$this, 'replace_wc_wpdb_table_fix']);

		/**
		 * WP Typography.
	 *
		 * @see https://de.wordpress.org/plugins/wp-typography/
		 */
		add_action('load-settings_page_wp-typography', [$this, 'add_wp_typography_warning_message']);

		add_filter('typo_settings', [$this, 'fix_wp_typography']);

		/**
		 * Brizy Page Builder.
		 *
		 * @see https://wordpress.org/plugins/brizy/
		 */
		add_filter('wu_append_preview_parameter', [$this, 'fix_brizy_preview_url']);

		add_filter('wu_should_redirect_to_primary_domain', [$this, 'fix_brizy_editor_screen']);

		/**
		 * Divi Page Builder.
		 *
		 * @see https://www.elegantthemes.com/
		 */
		add_filter('wu_should_redirect_to_primary_domain', [$this, 'fix_divi_editor_screen']);

		/**
		 * WP Hide Pro
	 *
		 * @see https://wp-hide.com/
		 */
		add_filter('wu_append_preview_parameter', [$this, 'fix_wp_hide_preview_url']);

		/**
		 * Frontend Admin.
		 *
		 * @see https://wpfrontendadmin.com/
		 */
		add_filter('wp_frontend_admin/shortcode/admin_page_final_url', [$this, 'fix_frontend_admin_loading_url'], 10, 3);

		/**
		 * Oxygen Builder.
		 *
		 * 1. Handles content parsing to decide if we should load our elements;
		 * 2. Prevent Oxygen from removing all the wp_head hooks on the template preview page;
		 *
		 * @see https://oxygenbuilder.com/
		 */
		add_filter('wu_element_should_enqueue_scripts', [$this, 'maybe_parse_oxygen_content'], 10, 3);

		add_action('wu_template_previewer', [$this, 'prevent_oxygen_cleanup_on_template_previewer']);

		/**
		 * WP Maintenance Mode. Adds SSO to WPMM, if enabled.
		 *
		 * @see https://wordpress.org/plugins/wp-maintenance-mode/
		 */
		add_filter('wu_sso_loaded_on_init', [$this, 'add_sso_to_maintenance_mode']);

		/**
		 * Avada Theme.
		 *
		 * 1. Fix the issue with the Avada theme that causes the template previewer to not load.
		 * 2. Handle cache on domain update.
		 *
		 * @see https://themeforest.net/item/avada-responsive-multipurpose-theme/
		 */
		add_filter('wu_template_previewer_before', [$this, 'run_wp_on_template_previewer']);
		add_filter('wu_domain_post_save', [$this, 'clear_avada_cache']);

		/**
		 * FluentCRM Pro
		 *
		 * 1. Fix the FluentCRM Pro on site duplication
		 *
		 * @see https://fluentcrm.com/
		 */
		add_action('wp_insert_site', [$this, 'fix_fluent_pro_site_duplication']);

		/**
		 * Rank Math (Free and Pro)
		 *
		 * 1. Remove rankmath auto activation hook to prevent database errors on site creation process
		 *
		 * @see https://rankmath.com/
		 */
		add_action('wp_initialize_site', [$this, 'fix_rank_math_site_creation'], 1);

		/**
		 * WP E-Signature and WP E-Signature Business add-ons
		 *
		 * 1. Remove e-signature auto activation hook to prevent database errors on site creation process
		 *
		 * @see https://www.approveme.com/
		 */
		add_action('wp_initialize_site', [$this, 'fix_wp_e_signature_site_creation'], 1);

		/**
		 * KeyPress DSN Manager backwards compatibility.
		 *
		 * @since 2.0.0
		 * @see https://getkeypress.com/dns-manager/
		 */
		add_action(
			'wu_before_pending_site_published',
			function () {

				if (function_exists('KPDNS')) {
					KPDNS(); // phpcs:ignore
				}
			},
			5
		); // need to hook before 10

		/**
		 * Perfmatters.
		 *
		 * 1. Remove action from perfamatters that disabled password strength meter script.
		 *
		 * @since 2.1.1
		 * @see https://perfmatters.io/
		 */
		add_filter('wp_print_scripts', [$this, 'remove_perfmatters_checkout_dep'], 99);

		/**
		 * Adds the setup preview for elements on DIVI.
		 *
		 * @since 2.0.5
		 */
		add_action(
			'wp',
			function () {

				if (wu_request('et_pb_preview')) {
					wu_element_setup_preview();
				}
			}
		);
	}

	/**
	 * Fixes a performance problem with Woocommerce.
	 *
	 * The default Woocommerce filter adds more elements to wpdb
	 * element without necessit and brings a overload when loading
	 * multiple sites data.
	 *
	 * @see https://wordpress.org/plugins/woocommerce/
	 * @since 2.0.14
	 */
	public function replace_wc_wpdb_table_fix(): void {

		global $wpdb;

		remove_action('switch_blog', [WC(), 'wpdb_table_fix'], 0);

		// List of tables without prefixes.
		$tables = [
			'payment_tokenmeta'      => 'woocommerce_payment_tokenmeta',
			'order_itemmeta'         => 'woocommerce_order_itemmeta',
			'wc_product_meta_lookup' => 'wc_product_meta_lookup',
			'wc_tax_rate_classes'    => 'wc_tax_rate_classes',
			'wc_reserved_stock'      => 'wc_reserved_stock',
		];

		add_action(
			'switch_blog',
			function () use ($wpdb, $tables) {

				foreach ( $tables as $name => $table ) {
					$wpdb->$name = $wpdb->prefix . $table;
				}
			},
			0
		);
	}

	/**
	 * Fixes incompatibility with the plugin WP Typography.
	 *
	 * This plugin has a setting that replaces quotes on the content.
	 * This breaks our moment configuration strings, and is generally
	 * not compatible with Ultimate Multisite vue templates.
	 *
	 * Here on this filter, we manually disable the smart quotes
	 * settings to prevent that kind of processing, as well as add
	 * an admin message telling admins that this is not supported.
	 *
	 * @see https://de.wordpress.org/plugins/wp-typography/
	 * @since 2.0.0
	 *
	 * @param array $settings The wp-typography settings.
	 * @return array
	 */
	public function fix_wp_typography($settings) {

		$settings['smartQuotes'] = false;

		return $settings;
	}

	/**
	 * Adds a warning message to let customers know why smart quotes are not working.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_wp_typography_warning_message(): void {

		WP_Ultimo()->notices->add(__('WP Typography "Smart Quotes" replacement is not compatible with Ultimate Multisite and will be automatically disabled.', 'ultimate-multisite'), 'warning');
	}

	/**
	 * Fixes brizy media URLs while on Ultimo's template preview
	 *
	 * In pages created with Brizy, the URLs break when
	 * we add the preview=1 parameter to urls.
	 *
	 * This fix prevent that addition.
	 * It is far from an optimal solution, but it will do
	 * for now.
	 *
	 * @todo Find a better way to exclude only Brizy urls.
	 *
	 * @since 2.0.0
	 * @param bool $value The current filter value.
	 * @return bool
	 */
	public function fix_brizy_preview_url($value) {

		return class_exists('Brizy_Editor') ? false : $value;
	}

	/**
	 * Fix the Brizy editor with domain mapping.
	 *
	 * @since 2.0.10
	 *
	 * @param bool $should_redirect If we should redirect to the mapped domain.
	 * @return bool
	 */
	public function fix_brizy_editor_screen($should_redirect) {

		if (class_exists('\Brizy_Editor')) {
			$key = \Brizy_Editor::prefix('-edit-iframe');

			if (wu_request($key, null) !== null) {
				return false;
			}
		}

		return $should_redirect;
	}

	/**
	 * Fix the Divi editor with domain mapping.
	 *
	 * @since 2.1.4
	 *
	 * @param bool $should_redirect If we should redirect to the mapped domain.
	 * @return bool
	 */
	public function fix_divi_editor_screen(bool $should_redirect): bool {

		if (isset($_GET['et_fb']) && (bool) $_GET['et_fb']) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		return $should_redirect;
	}

	/**
	 * Fixes WP Hide Pro URLs while on Ultimo's template preview
	 *
	 * @since 2.0.20
	 * @param bool $value The current filter value.
	 * @return bool
	 */
	public function fix_wp_hide_preview_url($value) {

		return class_exists('WPH') ? false : $value;
	}

	/**
	 * Fix the load URL for WP Frontend Admin.
	 *
	 * @since 2.0.0
	 *
	 * @param string $final_url The URL WFA wants to load.
	 * @param string $page_path_only The page path.
	 * @param int    $blog_id The blog ID.
	 * @return string
	 */
	public function fix_frontend_admin_loading_url($final_url, $page_path_only, $blog_id) {

		return wu_restore_original_url($final_url, $blog_id);
	}

	/**
	 * Oxygen renders things very strangely, so we need to handle it separately.
	 *
	 * @since 2.0.0
	 *
	 * @param bool     $should_enqueue If we should include the elements scripts.
	 * @param \WP_Post $post The post object.
	 * @param string   $shortcode_tag The shortcode.
	 * @return bool
	 */
	public function maybe_parse_oxygen_content($should_enqueue, $post, $shortcode_tag) {

		if (function_exists('oxygen_vsb_current_user_can_access') === false) {
			return $should_enqueue;
		}

		$shortcode_content = get_post_meta($post->ID, 'ct_builder_shortcodes', true);

		$has_shortcode = has_shortcode($shortcode_content, $shortcode_tag);

		/*
		 * Oxygen now base64 encodes shortcodes for some reason...
		 * Supporting third-party page builders is such a pain.
		 */
		if ( ! $has_shortcode) {
			$base64 = base64_encode("[$shortcode_tag]");

			$has_shortcode = strpos((string) $shortcode_content, $base64);
		}

		return $has_shortcode;
	}

	/**
	 * Prevent Oxygen from removing the real wp_head hook from the template
	 * previewer page.
	 *
	 * @since 2.0.4
	 * @return void
	 */
	public function prevent_oxygen_cleanup_on_template_previewer(): void {

		add_action(
			'wp_head',
			function () {

				remove_action('wp_head', 'oxy_print_cached_css', 999999);
			},
			10
		);
	}

	/**
	 * Adds SSO to WP Maintenance Mode.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Domain_Mapping\SSO $sso The SSO class instance.
	 * @return void
	 */
	public function add_sso_to_maintenance_mode($sso): void {

		add_action('wpmm_head', [$sso, 'enqueue_script']);
	}

	/**
	 * Run wp action on template previewer to prevent some errors like
	 * images not loading due lazy loading funcionality
	 *
	 * @since 2.0.11
	 */
	public function run_wp_on_template_previewer(): void {

		if (class_exists('Avada')) {
			do_action('wp'); //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
	}

	/**
	 * Run wp action on template previewer to prevent some errors like
	 * images not loading due lazy loading functionality
	 *
	 * @since 2.0.11
	 */
	public function clear_avada_cache($data): void {

		switch_to_blog($data['blog_id']);

		if (function_exists('fusion_reset_all_caches')) {
			fusion_reset_all_caches();
		} else {
			$theme = strtolower(wp_get_theme()) === 'avada' ? 'avada' : strtolower(wp_get_theme()->parent());

			$file_path = get_parent_theme_file_path('includes/lib/inc/functions.php');

			if ('avada' === $theme && file_exists($file_path)) {
				require_once get_parent_theme_file_path('includes/lib/inc/functions.php');

				fusion_reset_all_caches();
			}
		}

		restore_current_blog();
	}

	/**
	 * Fix the FluentCRM Pro on site duplication due to fc_meta table not exist
	 * The function causing problem should run after a user receive a role on the
	 * site to tag him on CRM as a customer.
	 *
	 * @since 2.0.11
	 */
	public function fix_fluent_pro_site_duplication(): void {

		$class_name = 'FluentCampaign\App\Hooks\Handlers\IntegrationHandler';

		if (class_exists($class_name)) {

			// Here we use this function due FluentCrm($class_name) returns an instance not working with remove_action
			$this->hard_remove_action('set_user_role', [$class_name, 'maybeAutoAlterTags'], 11);
		}
	}

	/**
	 * Removes RankMath and RankMath Pro Installer::wpmu_new_blog action
	 * This registered action causes error on database during site creation
	 * and serves only to force activate the plugin
	 *
	 * @since 2.0.20
	 */
	public function fix_rank_math_site_creation(): void {

		$class_names = [
			'RankMath\Installer',
			'RankMathPro\Installer',
		];

		foreach ($class_names as $class_name) {
			if (class_exists($class_name)) {

				// HankMath does not provide a instance of the activation class
				$this->hard_remove_action('wpmu_new_blog', [$class_name, 'activate_blog'], 10);
				$this->hard_remove_action('wp_initialize_site', [$class_name, 'initialize_site'], 10);
			}
		}
	}

	/**
	 * Removes WP E-Signature and WP E-Signature Business add-ons
	 * ESIG_SAD::wpmu_new_blog and ESIG_SIF::wpmu_new_blog actions
	 * This registered action causes error on database during site creation
	 * and serves only to force activate the plugin
	 *
	 * @since 2.1
	 */
	public function fix_wp_e_signature_site_creation(): void {

		$class_names = [
			'ESIG_SAD',
			'ESIG_SIF',
		];

		foreach ($class_names as $class_name) {
			if (class_exists($class_name)) {

				// WP E-Signature does not provide a instance of the activation class
				$this->hard_remove_action('wpmu_new_blog', [$class_name, 'activate_new_site'], 10);
			}
		}
	}

	/**
	 * A way to remove an action if instance is not available
	 *
	 * @since 2.0.11
	 *
	 * @param string $tag      The class name.
	 * @param array  $handler  The action handler.
	 * @param int    $priority The The action priority.
	 * @return void
	 */
	public function hard_remove_action($tag, $handler, $priority): void {

		global $wp_filter;

		if (! isset($wp_filter[ $tag ][ $priority ])) {
			return;
		}

		$handler_id = '';

		foreach ($wp_filter[ $tag ][ $priority ] as $handler_key => $filter_handler) {
			if (str_contains((string) $handler_key, (string) $handler[1]) && is_array($filter_handler['function']) && is_a($filter_handler['function'][0], $handler[0]) && $filter_handler['function'][1] === $handler[1]) {
				$handler_id = $handler_key;
			}
		}

		if (! empty($handler_id)) {
			remove_filter($tag, $handler_id, $priority);
		}

		return;
	}

	/**
	 * Remove action from perfamatters that disabled password strength meter script.
	 *
	 * @since 2.1.1
	 * @return void
	 */
	public function remove_perfmatters_checkout_dep(): void {

		if (is_main_site() || is_admin()) {
			remove_action('wp_print_scripts', 'perfmatters_disable_password_strength_meter', 100);
		}
	}
}
