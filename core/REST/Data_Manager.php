<?php 
namespace Carbon_Fields\REST;

use Carbon_Fields\Container\Container;

/**
 * Class for retrieving relative data for REST responses
 */
class Data_Manager {

	/**
	 * Singleton implementation.
	 *
	 * @return Sidebar_Manager
	 */
	public static function instance() {
		// Store the instance locally to avoid private static replication.
		static $instance;

		if ( ! is_a( $instance, 'Data_Manager' ) ) {
			$instance = new Data_Manager();
		}
		return $instance;
	}
	
	/**
	 * Special field types, that require 
	 * different data loading
	 * 
	 * @var array
	 */
	public $special_field_types = array(
		'complex',
		'relationship',
		'association',
		'map'
	); 

	/**
	 * Field types that should be excluded
	 * from the REST response
	 * 
	 * @var array
	 */
	protected $exclude_field_types = array(
		'html',
		'separator',
	);

	/**
	 * Returns the Carbon Fields data based
	 * on $type and $id
	 * 
	 * @param  string $type 
	 * @param  string $id 
	 * @return array
	 */
	public function get_data( $type, $id  = '' ) {
		$response   = array();
		$containers = $this->filter_containers( $type, $id );

		foreach ( $containers as $container ) {
			$fields = $this->filter_fields( $container->get_fields() );

			foreach ( $fields as $field ) {
				
				if ( $id ) {
					$field->get_datastore()->set_id( $id );
				}

				$field->load();

				$response[ $field->get_name() ] = $this->get_field_value( $field );
			}
		}

		return $response;
	}

	/**
	 * Filters all available containers 
	 * based on $type
	 * 
	 * @param  string $type 
	 * @param  string $id
	 * @return array
	 */
	public function filter_containers( $type, $id = '' ) {
		return array_filter( Container::get_active_containers( true ), function( $container ) use ( $type, $id ) {
			return ( $container->type === $type && $container->is_valid_attach_for_object( $id ) );
		} );
	}

	/**
	 * Checks if fields should be excluded from the response
	 * 
	 * @param  array $fields 
	 * @return array
	 */
	public function filter_fields( $fields ) {
		return array_filter( $fields, array( $this, 'should_load_field' ) );
	}

	/**
	 * Checks if a field should be excluded from the response
	 * 
	 * @param  object $field
	 * @return array       
	 */
	public function should_load_field( $field ) {
		return $field->get_rest_visibility() && ! in_array( strtolower( $field->type ), $this->exclude_field_types );
	}

	/**
	 * Loads field value (proxy for specific field implementations)
	 * 
	 * @param  object $field
	 * @return array
	 */
	public function get_field_value( $field ) {
		$field_type = in_array( strtolower( $field->type ), $this->special_field_types ) ? strtolower( $field->type ) : 'generic';
		return call_user_func( array( $this, "get_{$field_type}_field_value" ), $field );
	}

	/**
	 * Loads field value
	 * 
	 * @param  object $field
	 * @return array
	 */
	protected function get_generic_field_value( $field ) {
		return $field->get_value();
	}

	/**
	 * Loads the value of a complex field
	 * 
	 * @param  object $field 
	 * @return array
	 */
	protected function get_complex_field_value( $field ) {
		$field_json = $field->to_json( false );
		return $field_json['value'];
	}

	/**
	 * Load the value of a map field
	 * 
	 * @param  object $field 
	 * @return array
	 */
	protected function get_map_field_value( $field ) {
		$map_data = $field->to_json( false );

		return array(
			'lat'     => $map_data['lat'],
			'lng'     => $map_data['lng'],
			'zoom'    => $map_data['zoom'],
			'address' => $map_data['address'],
		);
	}

	/**
	 * Loads the value of a relationship field
	 * 
	 * @param  object $field 
	 * @return array
	 */
	protected function get_relationship_field_value( $field ) {
		return maybe_unserialize( $field->get_value() );
	}

	/**
	 * Loads the value of an association field
	 * 
	 * @param object $field 
	 * @return array
	 */
	protected function get_association_field_value( $field ) {
		$field->process_value();
		return $field->get_value();
	}
}