<?php 
namespace Carbon_Fields\Updater;

use \Carbon_Fields\Field\Field;
use \Carbon_Fields\Container\Container;
use \Carbon_Fields\Helper\Helper;

/**
* Class for updating meta data/theme options
*/
class Updater {

	/**
	 * Map for classes parsing
	 * special input types
	 * 
	 * @var array
	 */
	protected static $value_types = array(
		'complex'          => 'Complex_Value_Parser',
		'association'      => 'Association_Value_Parser',
		'map'              => 'Map_Value_Parser',
		'map_with_address' => 'Map_Value_Parser', // deprecated
	);

	/**
	 * Update a field's value
	 * 
	 * @param  string $context    post_meta|term_meta|user_meta|comment_meta|theme_option
	 * @param  string $object_id  
	 * @param  string $field_name 
	 * @param  mixed $input       string|array|json
	 * @param  string $value_type complex|map|map_with_address|association|null
	 */
	public static function update_field( $context, $object_id = '', $field_name, $input, $value_type = null ) {
		$rest_containers_only = ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		$containers = self::get_containers( $context, $object_id, $rest_containers_only );

		$is_option    = true;
		$field_name   = $object_id ? Helper::prepare_meta_name( $field_name ) : $field_name;
		$carbon_field = Field::get_field_by_name_in_containers( $field_name, $containers );
		if ( $carbon_field === null ) {
			throw new \Exception( sprintf( __( 'There is no <strong>%s</strong> Carbon Field.', 'crb' ), $field_name ) );
		}

		if ( $object_id ) {
			$carbon_field->get_datastore()->set_id( $object_id );
			$is_option = false;
		}	

		$carbon_field_type  = strtolower( $carbon_field->type );

		if ( $value_type && ( $carbon_field_type !== $value_type ) ) {
			throw new \Exception( printf( __( 'The field <strong>%s</strong> is of type <strong>%s</strong>. You are passing <strong>%s</strong> value.', 'crb' ), $field_name, $carbon_field_type, $value_type ) );
		}

		$input = self::maybe_json_decode( $input );
		$class = __NAMESPACE__ . '\\' . ( isset( self::$value_types[ $carbon_field_type ] ) ? self::$value_types[ $carbon_field_type ] : 'Value_Parser' );
		$input = $class::parse( $input, $is_option );

		$carbon_field->set_value_from_input( array( $field_name => $input ) );
		$carbon_field->save();
	}

	/**
	 * Get all containers of specific type and attached to a specific object id
	 * 
	 * @param  string $container_type
	 * @param  string $object_id
	 * @param  string $rest_containers_only
	 */
	protected static function get_containers( $container_type, $object_id, $rest_containers_only = false ) {
		if ( empty( Container::get_active_containers() ) ) {
			do_action( 'carbon_trigger_containers_attach' );
		}

		$container_type = self::normalize_container_type( $container_type );

		return Container::get_active_containers( $container_type, $object_id, $rest_containers_only );
	}

	/**
	 * Normalize container type
	 * 
	 * @param  string $type 
	 * @return string       
	 */
	protected static function normalize_container_type( $container_type ) {
		$container_type = Helper::prepare_data_type_name( $container_type );

		if ( $container_type === 'Theme_Option' ) {
			$container_type = 'Theme_Options';
		}

		return $container_type;
	}

	/**
	 * is_json
	 * 
	 * @param  string  $string
	 * @return boolean       
	 */
	protected static function is_json( $string ) {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) && ( json_last_error() === JSON_ERROR_NONE ) ? true : false;
	}

	/**
	 * Decode json if necessary
	 * 
	 * @param  mixed $maybe_json
	 * @return array
	 */
	protected static function maybe_json_decode( $maybe_json ) {
		if ( self::is_json( $maybe_json ) ) {
			return json_decode( $maybe_json );
		}

		return $maybe_json;
	}
}