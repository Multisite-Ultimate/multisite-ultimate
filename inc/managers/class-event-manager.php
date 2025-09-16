<?php
/**
 * Events Manager
 *
 * Handles processes related to events.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Event_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Models\Base_Model;
use WP_Ultimo\Models\Event;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to events.
 *
 * @since 2.0.0
 */
class Event_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'event';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Event::class;

	/**
	 * Holds the list of available events for webhooks.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $events = [];

	/**
	 * The list of registered models events.
	 *
	 * @since 2.1.4
	 * @var array
	 */
	protected $models_events = [];

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->enable_rest_api();

		$this->enable_wp_cli();

		add_action('init', [$this, 'register_all_events']);

		add_action('wp_ajax_wu_get_event_payload_preview', [$this, 'event_payload_preview']);

		add_action('rest_api_init', [$this, 'hooks_endpoint']);

		add_action('wu_model_post_save', [$this, 'log_transitions'], 10, 4);

		add_action('wu_daily', [$this, 'clean_old_events']);
	}

	/**
	 * Returns the payload to be displayed in the payload preview field.
	 * Log model transitions.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $model The model name.
	 * @param array      $data The data being saved, serialized.
	 * @param array      $data_unserialized The data being saved, un-serialized.
	 * @param Base_Model $obj The object being saved.
	 *
	 * @return void
	 */
	public function log_transitions($model, $data, $data_unserialized, $obj) {

		if ('event' === $model) {
			return;
		}

		/*
		 * Editing Model
		 */
		if (wu_get_isset($data_unserialized, 'id')) {
			$original = $obj->_get_original();

			$diff = wu_array_recursive_diff($data_unserialized, $original);

			$keys_to_remove = apply_filters(
				'wu_exclude_transitions_keys',
				[
					'meta',
					'last_login',
					'ips',
					'query_class',
					'settings',
					'_compiled_product_list',
					'_gateway_info',
					'_limitations',
				]
			);

			foreach ($keys_to_remove as $key_to_remove) {
				unset($diff[ $key_to_remove ]);
			}

			/**
			 * If empty, go home.
			 */
			if (empty($diff)) {
				return;
			}

			$changed = [];

			/**
			 * Loop changed data.
			 */
			foreach ($diff as $key => $new_value) {
				$old_value = wu_get_isset($original, $key, '');

				if ('id' === $key && intval($old_value) === 0) {
					return;
				}

				if (empty(wp_json_encode($old_value)) && empty(wp_json_encode($new_value))) {
					return;
				}

				$changed[ $key ] = [
					'old_value' => $old_value,
					'new_value' => $new_value,
				];
			}

			$event_data = [
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'changed',
				'object_type' => $model,
				'object_id'   => $obj->get_id(),
				'payload'     => $changed,
			];
		} else {
			$event_data = [
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'created',
				'object_type' => $model,
				'object_id'   => $obj->get_id(),
				'payload'     => [],
			];
		}

		if ( ! empty($_POST) && is_user_logged_in()) { // phpcs:ignore WordPress.Security.NonceVerification
			$event_data['initiator'] = 'manual';
			$event_data['author_id'] = get_current_user_id();
		}

		wu_create_event($event_data);
	}

	/**
	 * Returns the payload to be displayed in the payload preview field.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function event_payload_preview(): void {

		if ( ! wu_request('event')) {
			wp_send_json_error(new \WP_Error('error', __('No event was selected.', 'ultimate-multisite')));
		}

		$slug = wu_request('event');

		if ( ! $slug) {
			wp_send_json_error(new \WP_Error('not-found', __('Event was not found.', 'ultimate-multisite')));
		}

		$event = wu_get_event_type($slug);

		if ( ! $event) {
			wp_send_json_error(new \WP_Error('not-found', __('Data not found.', 'ultimate-multisite')));
		} else {
			$payload = isset($event['payload']) ? wu_maybe_lazy_load_payload($event['payload']) : '{}';

			$payload = array_map('htmlentities2', $payload);

			wp_send_json_success($payload);
		}
	}

	/**
	 * Returns the list of event types to register.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_event_type_as_options() {
		/*
		 * We use this to order the options.
		*/
		$event_type_settings = wu_get_setting('saving_type', []);

		$types = [
			'id'         => '$id',
			'title'      => '$title',
			'desc'       => '$desc',
			'class_name' => '$class_name',
			'active'     => 'in_array($id, $active_gateways, true)',
			'gateway'    => '$class_name', // Deprecated.
			'hidden'     => false,
		];

		$types = array_filter($types, fn($item) => false === $item['hidden']);

		return $types;
	}

	/**
	 * Add a new event.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the event. Something like payment_received.
	 * @param array  $payload with the events information.
	 *
	 * @return array with returns message for now.
	 */
	public function do_event($slug, $payload) {

		$registered_event = $this->get_event($slug);

		if ( ! $registered_event) {
			return ['error' => 'Event not found'];
		}

		$payload_diff = array_diff_key(wu_maybe_lazy_load_payload($registered_event['payload']), $payload);

		if (isset($payload_diff[0])) {
			foreach ($payload_diff[0] as $diff_key => $diff_value) {
				return ['error' => 'Param required:' . $diff_key];
			}
		}

		$payload['wu_version'] = wu_get_version();

		do_action('wu_event', $slug, $payload);

		do_action("wu_event_{$slug}", $payload);

		/**
		 * Saves in the database
		 */
		$this->save_event($slug, $payload);
	}

	/**
	 * Register a new event to be used as param.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the event. Something like payment_received.
	 * @param array  $args with the events information.
	 *
	 * @return true
	 */
	public function register_event($slug, $args): bool {

		$this->events[ $slug ] = $args;

		return true;
	}

	/**
	 * Returns the list of available webhook events.
	 *
	 * @since 2.0.0
	 * @return array $events with all events.
	 */
	public function get_events() {

		return $this->events;
	}

	/**
	 * Returns the list of available webhook events.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug of the event.
	 * @return array|false $event with event params.
	 */
	public function get_event($slug) {

		$events = $this->get_events();

		if ($events) {
			foreach ($events as $key => $event) {
				if ($key === $slug) {
					return $event;
				}
			}
		}

		return false;
	}

	/**
	 * Saves event in the database.
	 *
	 * @param string $slug of the event.
	 * @param array  $payload with event params.
	 * @return void.
	 */
	public function save_event($slug, $payload): void {

		$event = new Event(
			[
				'object_id'    => wu_get_isset($payload, 'object_id', ''),
				'object_type'  => wu_get_isset($payload, 'object_type', ''),
				'severity'     => wu_get_isset($payload, 'type', Event::SEVERITY_INFO),
				'date_created' => wu_get_current_time('mysql', true),
				'slug'         => strtolower($slug),
				'payload'      => $payload,
			]
		);

		$event->save();
	}

	/**
	 * Registers the list of default events.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_all_events(): void {

		/**
		 * Payment Received.
		 */
		wu_register_event_type(
			'payment_received',
			[
				'name'            => __('Payment Received', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new payment is received, regardless of the payment status.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('payment'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [
					'user_id' => 'customer_user_id',
					'amount'  => 'payment_total',
					'gateway' => 'payment_gateway',
					'status'  => 'payment_status',
					'date'    => 'payment_date_created',
				],
			]
		);

		/**
		 * Site Published.
		 */
		wu_register_event_type(
			'site_published',
			[
				'name'            => __('Site Published', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new site is created tied to a membership, or transitions from a pending state to a published state.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('site'),
					wu_generate_event_payload('customer'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Confirm Email Address
		 */
		wu_register_event_type(
			'confirm_email_address',
			[
				'name'            => __('Email Verification Needed', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new customer is added with an email verification status of pending.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'verification_link' => 'https://linktoverifyemail.com',
					],
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Domain Mapping Added
		 */
		wu_register_event_type(
			'domain_created',
			[
				'name'            => __('New Domain Mapping Added', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new domain mapping is added by a customer.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('domain'),
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [
					'user_id'       => 1,
					'user_site_id'  => 1,
					'mapped_domain' => 'mydomain.com',
					'user_site_url' => 'http://test.mynetwork.com/',
					'network_ip'    => '125.399.3.23',
				],
			]
		);

		/**
		 * Renewal payment created
		 */
		wu_register_event_type(
			'renewal_payment_created',
			[
				'name'            => __('New Renewal Payment Created', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new renewal payment is created by Multisite Ultimate.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'default_payment_url' => 'https://linktopayment.com',
					],
					wu_generate_event_payload('payment'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		$models = $this->models_events;

		foreach ($models as $model => $params) {
			foreach ($params['types'] as $type) {
				wu_register_event_type(
					$model . '_' . $type,
					[
						// translators: %1$s is the model name, %2$s is the event type.
						'name'            => sprintf(__('%1$s %2$s', 'ultimate-multisite'), $params['label'], ucfirst($type)),
						// translators: %1$s is the model name, %2$s is the event type.
						'desc'            => sprintf(__('This event is fired every time a %1$s is %2$s by Multisite Ultimate.', 'ultimate-multisite'), $params['label'], $type),
						'deprecated_args' => [],
						'payload'         => fn() => $this->get_model_payload($model),
					]
				);
			}

			add_action("wu_{$model}_post_save", [$this, 'dispatch_base_model_event'], 10, 3);
		}

		do_action('wu_register_all_events');
	}

	/**
	 * Register models events
	 *
	 * @param string $slug slug of event.
	 * @param string $label label of event.
	 * @param array  $event_types event types allowed.
	 * @since 2.1.4
	 */
	public static function register_model_events(string $slug, string $label, array $event_types): void {

		$instance = self::get_instance();

		$instance->models_events[ $slug ] = [
			'label' => $label,
			'types' => $event_types,
		];
	}

	/**
	 * Dispatch registered model events
	 *
	 * @param array $data Data.
	 * @param mixed $obj Object.
	 * @param bool  $new_model New.
	 *
	 * @since 2.1.4
	 */
	public function dispatch_base_model_event(array $data, $obj, bool $new_model): void {

		$model = $obj->model;

		$type = $new_model ? 'created' : 'updated';

		$registered_model = wu_get_isset($this->models_events, $model);

		if ( ! $registered_model || ! in_array($type, $registered_model['types'], true)) {
			return;
		}

		$payload = $this->get_model_payload($model, $obj);

		wu_do_event($model . '_' . $type, $payload);
	}

	/**
	 * Returns the full payload for a given model.
	 *
	 * @param string      $model The model name.
	 * @param object|null $model_object The model object.
	 * @return array
	 *
	 * @since 2.3.0
	 */
	public function get_model_payload(string $model, ?object $model_object = null) {

		$obj     = $model_object ?? call_user_func("wu_mock_{$model}");
		$payload = wu_generate_event_payload($model, $obj);

		if (method_exists($obj, 'get_membership')) {
			$membership = $model_object ? $obj->get_membership() : false;

			$payload = array_merge(
				$payload,
				wu_generate_event_payload('membership', $membership)
			);
		}

		if (method_exists($obj, 'get_customer')) {
			$customer = $model_object ? $obj->get_customer() : false;

			$payload = array_merge(
				$payload,
				wu_generate_event_payload('customer', $customer)
			);
		}

		if (method_exists($obj, 'get_billing_address') || method_exists($obj, 'get_membership')) {
			if (null !== $model_object) {
				$payload = method_exists($obj, 'get_billing_address')
					? array_merge(
						$payload,
						$obj->get_billing_address()->to_array()
					) : array_merge(
						$payload,
						$obj->get_membership()->get_billing_address()->to_array()
					);
			} else {
				$payload = array_merge(
					$payload,
					array_map(
						fn() => '',
						\WP_Ultimo\Objects\Billing_Address::fields()
					)
				);
			}
		}

		return $payload;
	}

	/**
	 * Every day, deletes old events that we don't want to keep.
	 *
	 * @since 2.0.0
	 */
	public function clean_old_events(): bool {
		/*
		 * Add a filter setting this to 0 or false
		 * to prevent old events from being ever deleted.
		 */
		$threshold_days = apply_filters('wu_events_threshold_days', 1);

		if (empty($threshold_days)) {
			return false;
		}

		$events_to_remove = wu_get_events(
			[
				'number'     => 100,
				'date_query' => [
					'column'    => 'date_created',
					'before'    => "-{$threshold_days} days",
					'inclusive' => true,
				],
			]
		);

		$success_count = 0;

		foreach ($events_to_remove as $event) {
			$status = $event->delete();

			if ( ! is_wp_error($status) && $status) {
				++$success_count;
			}
		}

		// Translators:  1: Number of successfully removed events.  2: Number of failed events to remove.
		wu_log_add('wu-cron', sprintf(__('Removed %1$d events successfully. Failed to remove %2$d events.', 'ultimate-multisite'), $success_count, count($events_to_remove) - $success_count));

		return true;
	}

	/**
	 * Create a endpoint to retrieve all available event hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function hooks_endpoint(): void {

		if ( ! wu_get_setting('enable_api', true)) {
			return;
		}

		$api = \WP_Ultimo\API::get_instance();

		register_rest_route(
			$api->get_namespace(),
			'/hooks',
			[
				'methods'             => 'GET',
				'callback'            => [$this, 'get_hooks_rest'],
				'permission_callback' => [$api, 'check_authorization'],
			]
		);
	}

	/**
	 * Return all event types for the REST API request.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request The request sent.
	 * @return mixed
	 */
	public function get_hooks_rest($request) {

		$response = wu_get_event_types();

		foreach ($response as $key => $value) {
			$payload = wu_get_isset($value, 'payload');

			if (is_callable($payload)) {
				$response[ $key ]['payload'] = $payload();
			}
		}

		return rest_ensure_response($response);
	}
}
