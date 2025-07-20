<?php
/**
 * A trait to be included in entities to enable WP CLI commands.
 *
 * @package WP_Ultimo
 * @subpackage Apis
 * @since 2.0.0
 */

namespace WP_Ultimo\Apis;

defined( 'ABSPATH' ) || exit;

/**
 * WP CLI trait.
 */
trait WP_CLI {

	/**
	 * The base used in the command right after the root: `wp <root> <command_base> <sub_command>`.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $wp_cli_command_base = '';

	/**
	 * WP-CLI Sub_command enabled for this entity.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $wp_cli_enabled_sub_commands = [];

	/**
	 * Returns the base used right after the root.
	 * Uses the `wp_cli_command_base` attribute if set, `slug` otherwise.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_wp_cli_command_base() {

		return (! empty($this->wp_cli_command_base)) ? $this->wp_cli_command_base : $this->slug;
	}

	/**
	 * Registers the routes. Should be called by the entity
	 * to actually enable the REST API.
	 *
	 * @since 2.0.0
	 */
	public function enable_wp_cli(): void {

		if ( ! defined('WP_CLI')) {
			return;
		}

		$wp_cli_root = 'wu';

		$this->set_wp_cli_enabled_sub_commands();

		foreach ($this->wp_cli_enabled_sub_commands as $sub_command => $sub_command_data) {
			\WP_CLI::add_command(
				"{$wp_cli_root} {$this->get_wp_cli_command_base()} {$sub_command}",
				$sub_command_data['callback'],
				[
					'synopsis' => $sub_command_data['synopsis'],
				]
			);
		}
	}

	/**
	 * Set wP-CLI Sub-command enabled for this entity.
	 */
	public function set_wp_cli_enabled_sub_commands(): void {

		$sub_commands = [
			'get'    => [
				'callback' => [$this, 'wp_cli_get_item'],
			],
			'list'   => [
				'callback' => [$this, 'wp_cli_get_items'],
			],
			'create' => [
				'callback' => [$this, 'wp_cli_create_item'],
			],
			'update' => [
				'callback' => [$this, 'wp_cli_update_item'],
			],
			'delete' => [
				'callback' => [$this, 'wp_cli_delete_item'],
			],
		];

		$params = array_merge($this->wp_cli_get_fields(), $this->wp_cli_extra_parameters());

		$params = array_unique($params);

		/**
		 * Unset undesired Params.
		 */
		$params_to_remove = apply_filters(
			'wu_cli_params_to_remove',
			[
				'id',
				'model',
			]
		);

		$params = array_filter($params, fn($param) => ! in_array($param, $params_to_remove, true));

		foreach ($sub_commands as $sub_command => &$sub_command_data) {
			$sub_command_data['synopsis'] = [];

			if (in_array($sub_command, ['get', 'update', 'delete'], true)) {
				$sub_command_data['synopsis'][] = [
					'name'        => 'id',
					'type'        => 'positional',
					'description' => __('The id for the resource.', 'multisite-ultimate'),
					'optional'    => false,
				];
			}

			if (in_array($sub_command, ['list', 'update', 'create'], true)) {
				$explanation_list = wu_rest_get_endpoint_schema($this->model_class, 'update');

				foreach ($params as $name) {
					$explanation = wu_get_isset($explanation_list, $name, []);

					$type = wu_get_isset($explanation, 'type', 'assoc');

					$field = [
						'name'        => $name,
						'description' => wu_get_isset($explanation, 'description', __('No description found.', 'multisite-ultimate')),
						'optional'    => ! wu_get_isset($explanation, 'required'),
						'type'        => 'assoc',
					];

					$options = wu_get_isset($explanation, 'options', []);

					if ($options) {
						$field['options'] = $options;
					}

					$sub_command_data['synopsis'][] = $field;
				}
			}

			if (in_array($sub_command, ['create', 'update'], true)) {
				$sub_command_data['synopsis'][] = [
					'name'        => 'porcelain',
					'type'        => 'flag',
					'description' => __('Output just the id when the operation is successful.', 'multisite-ultimate'),
					'optional'    => true,
				];
			}

			if (in_array($sub_command, ['list', 'get'], true)) {
				$sub_command_data['synopsis'][] = [
					'name'        => 'format',
					'type'        => 'assoc',
					'description' => __('Render response in a particular format.', 'multisite-ultimate'),
					'optional'    => true,
					'default'     => 'table',
					'options'     => [
						'table',
						'json',
						'csv',
						'ids',
						'yaml',
						'count',
					],
				];

				$sub_command_data['synopsis'][] = [
					'name'        => 'fields',
					'type'        => 'assoc',
					'description' => __('Limit response to specific fields. Defaults to id, name', 'multisite-ultimate'),
					'optional'    => true,
					'options'     => array_merge(['id'], $params),
				];
			}
		}

		$this->wp_cli_enabled_sub_commands = $sub_commands;

		/**
		 * Filters which sub_commands are enabled for this entity.
		 *
		 * @since 2.0.0
		 *
		 * @param array        $sub_commands  Default sub_commands.
		 * @param string       $command_base The base used in the command right after the root.
		 * @param Base_Manager $this         The object instance.
		 */
		$this->wp_cli_enabled_sub_commands = apply_filters(
			'wu_wp_cli_enabled_sub_commands',
			$this->wp_cli_enabled_sub_commands,
			$this->get_wp_cli_command_base(),
			$this
		);
	}

	/**
	 * Allows the additional of additional parameters.
	 *
	 * @since 2.0.0
	 */
	public function wp_cli_extra_parameters(): array {

		$model = new $this->model_class();

		return array_keys($model->to_array());
	}

	/**
	 * Returns the list of default fields, based on the table schema.
	 *
	 * @since 2.0.0
	 * @return array List of the schema columns.
	 */
	public function wp_cli_get_fields(): array {

		$schema = $this->model_class::get_schema();

		return array_column($schema, 'name');
	}

	/**
	 * Returns a specific item.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args        Positional arguments passed. ID expected.
	 * @param array $array_assoc Assoc arguments passed.
	 */
	public function wp_cli_get_item($args, $array_assoc): void {

		$item = $this->model_class::get_by_id($args[0]);

		if (empty($item)) {
			\WP_CLI::error('Invalid ID.');
		}

		$fields = (! empty($array_assoc['fields'])) ? $array_assoc['fields'] : $this->wp_cli_get_fields();

		$formatter = new \WP_CLI\Formatter($array_assoc, $fields);

		$formatter->display_item($item->to_array());
	}

	/**
	 * Returns a list of items.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args        Positional arguments passed. ID expected.
	 * @param array $array_assoc Assoc arguments passed.
	 */
	public function wp_cli_get_items($args, $array_assoc): void {

		$fields = (! empty($array_assoc['fields'])) ? $array_assoc['fields'] : $this->wp_cli_get_fields();

		unset($array_assoc['fields']);

		$items = $this->model_class::query($array_assoc);

		$items = array_map(fn($item) => $item->to_array(), $items);

		\WP_CLI\Utils\format_items($array_assoc['format'], $items, $fields);
	}

	/**
	 * Creates an item.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args        Positional arguments passed. ID expected.
	 * @param array $array_assoc Assoc arguments passed.
	 */
	public function wp_cli_create_item($args, $array_assoc): void {

		$item = new $this->model_class($array_assoc);

		$success = $item->save();

		if (true === $success) {
			$item_id = $item->get_id();

			if ( ! empty($array_assoc['porcelain'])) {
				\WP_CLI::line($item_id);
			} else {
				$message = sprintf('Item created with ID %d', $item_id);

				\WP_CLI::success($message);
			}
		} else {
			\WP_CLI::error($success);
		}
	}

	/**
	 * Updates an item.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args        Positional arguments passed. ID expected.
	 * @param array $array_assoc Assoc arguments passed.
	 */
	public function wp_cli_update_item($args, $array_assoc): void {

		$item = $this->model_class::get_by_id($args[0]);

		if (empty($item)) {
			\WP_CLI::error('Invalid ID.');
		}

		$porcelain = false;

		if ( ! empty($array_assoc['porcelain'])) {
			$porcelain = true;

			unset($array_assoc['porcelain']);
		}

		$params = $array_assoc;

		foreach ($params as $param => $value) {
			$set_method = "set_{$param}";

			if ('meta' === $param) {
				$item->update_meta_batch($value);
			} elseif (method_exists($item, $set_method)) {
				call_user_func([$item, $set_method], $value);
			} else {
				$error_message = sprintf(
					/* translators: 1. Object class name; 2. Set method name */
					__('The %1$s object does not have a %2$s method', 'multisite-ultimate'),
					get_class($item),
					$set_method
				);

				\WP_CLI::error($error_message);
			}
		}

		$success = $item->save();

		if ($success) {
			$item_id = $item->get_id();

			if ($porcelain) {
				\WP_CLI::line($item_id);
			} else {
				$message = sprintf('Item updated with ID %d', $item_id);

				\WP_CLI::success($message);
			}
		} else {
			\WP_CLI::error('Unexpected error. The item was not updated.');
		}
	}

	/**
	 * Deletes an item.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Positional arguments passed. ID expected.
	 */
	public function wp_cli_delete_item($args): void {

		$item = $this->model_class::get_by_id($args[0]);

		if (empty($item)) {
			\WP_CLI::error('Invalid ID.');
		}

		$success = $item->delete();

		if (is_wp_error($success) || ! $success) {
			\WP_CLI::error('Unexpected error. The item was not deleted.');
		} else {
			\WP_CLI::success('Item deleted.');
		}
	}
}
