<?php 
namespace Carbon_Fields\REST;

use \Carbon_Fields\Field\Field;
use \Carbon_Fields\Container\Container;
use \Carbon_Fields\Updater\Updater;
use \Carbon_Fields\Helper\Helper;

/**
 * This class modifies the default REST routes
 * using the WordPress' register_rest_field() function 
 */

class Decorator {

	/**
	 * Singleton implementation.
	 *
	 * @return Decorator
	 */
	public static function instance() {
		// Store the instance locally to avoid private static replication.
		static $instance;

		if ( ! is_a( $instance, 'Decorator' ) ) {
			$instance = new Decorator();
		}
		return $instance;
	}

	function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Registers Carbon Fields using
	 * the register_rest_field() function
	 */
	public function register_fields() {
		$containers = $this->get_filtered_containers();

		foreach ( $containers as $container ) {
			$fields  = $this->get_filtered_fields( $container );
			$context = strtolower( $container->type );
			$types   = call_user_func( array( __CLASS__, "get_{$context}_container_settings" ), $container );

			foreach ( $fields as $field ) {
				$getter = function ( $object, $field_name, $request ) use ( $context ) {
					return $this->get_field_value( $object, $field_name, $request, Helper::prepare_data_type_name( $context ) );
				};
				$setter = function ( $object, $field_name, $request ) use ( $context ) {
					return $this->update_field_value( $object, $field_name, $request, Helper::prepare_data_type_name( $context ) );
				};

				register_rest_field( $types,
					$field->get_name(), array(
						'get_callback'    => $getter,
						'update_callback' => $setter,
						'schema'          => null,
					)
				);
			}
		}
	}

	/**
	 * Return all containers that 
	 * should be visible in the core REST API responses
	 *
	 * @return array
	 */
	public function get_filtered_containers() {
		return array_filter( Container::get_active_containers( '', 0, true ), function( $container ) {
			return $container->type !== 'Theme_Options' && $container->get_rest_visibility(); 
		} );
	}

	/**
	 * Return all fields attached to a container
	 * that should be included in the REST API response
	 * 
	 * @param object $container
	 * @return array
	 */
	public function get_filtered_fields( $container ) {
		return Data_Manager::instance()->filter_fields( $container->get_fields() );
	}

	/**
	 * Get the value of the "$field_name" field
	 *
	 * @param array $object Details of current object.
 	 * @param string $field_name Name of field.
 	 * @param WP_REST_Request $request Current request
 	 * @param string $context Post_Meta|Term_Meta|User_Meta|Comment_Meta
 	 * @return mixed
	 */	
	public function get_field_value( $object, $field_name, $request, $context ) {
		$containers = Container::get_active_containers( $context, 0, true );
		$field = Field::get_field_by_name_in_containers( $field_name, $containers );

		if ( empty( $field ) ) {
			return '';
		}

		return Data_Manager::instance()->get_field_value( $field, $object['id'] );
	}

	/**
	 * Handler for updating custom field data.
	 *
	 * @param mixed $value The value of the field
	 * @param object $object The object from the request
	 * @param string $field_name Name of field
	 * @param string $context Post_Meta|Term_Meta|User_Meta|Comment_Meta
	 */
	public function update_field_value( $value, $object, $field_name, $context ) {
		if ( ! $value || ! is_string( $value ) ) {
			return;
		}
		
		$containers = Container::get_active_containers( $context, 0, true );
		$field = Field::get_field_by_name_in_containers( $field_name, $containers );

		if ( empty( $field ) ) {
			return;
		}
		
		$type      = strtolower( $field->type );
		$object_id = Helper::get_object_id( $object, $context );

		try {
			Updater::update_field( strtolower( $context ), $object_id, $field_name, $value, $type );	
		} catch ( \Exception $e ) {
			echo wp_strip_all_tags( $e->getMessage() );
			exit;
		}
	}

	/**
	 * Get Post Meta Container visibility settings
	 *
	 * @return array
	 */	
	public static function get_post_meta_container_settings( $container ) {
		return $container->settings['post_type'];
	}

	/**
	 * Get Term Meta Container visibility settings
	 *
	 * @return array
	 */	
	public static function get_term_meta_container_settings( $container ) {
		return $container->settings['taxonomy'];
	}

	/**
	 * Get User Meta Container visibility settings
	 *
	 * @return string
	 */	
	public static function get_user_meta_container_settings( $container ) {
		return 'user';
	}

	/**
	 * Get Comment Meta Container visibility settings
	 * 
	 * @return string
	 */	
	public static function get_comment_meta_container_settings( $container ) {
		return 'comment';
	}
}