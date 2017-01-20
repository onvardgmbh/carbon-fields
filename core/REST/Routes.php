<?php 
namespace Carbon_Fields\REST;

use \Carbon_Fields\Updater\Updater;

/**
* Register custom REST routes		
*/
class Routes {

	/**
	 * Carbon Fields routes
	 * 
	 * @var array
	 */
	protected $routes = array(
		'post_meta' => array(
			'path'                => '/posts/(?P<id>\d+)',
			'callback'            => 'get_post_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'term_meta' => array(
			'path'                => '/terms/(?P<id>\d+)',
			'callback'            => 'get_term_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'user_meta' => array(
			'path'                => '/users/(?P<id>\d+)',
			'callback'            => 'get_user_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'comment_meta' => array(
			'path'                => '/comments/(?P<id>\d+)',
			'callback'            => 'get_comment_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'theme_options' => array(
			'path'                => '/options/',
			'callback'            => 'options_accessor',
			'permission_callback' => 'options_permission',
			'methods'             => array( 'GET', 'POST' ),
		),
	);

	/**
	 * Version of the API
	 * 
	 * @see set_version()
	 * @see get_version()
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Plugin slug
	 * 
	 * @see set_vendor()
	 * @see get_vendor()
	 * @var string
	 */
	protected $vendor = 'carbon-fields';

	/**
	 * Singleton implementation.
	 *
	 * @return Routes
	 */
	public static function instance() {
		// Store the instance locally to avoid private static replication.
		static $instance;

		if ( ! is_a( $instance, 'Routes' ) ) {
			$instance = new Routes();
		}
		return $instance;
	}
	
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 15 );
	}

	/**
	 * Register custom routes
	 * 
	 * @see  register_route()
	 */
	public function register_routes() {
		foreach ( $this->routes as $route ) {
			$this->register_route( $route );
		}
	}

	/**
	 * Register a custom REST route
	 * 
	 * @param  array $route
	 */
	protected function register_route( $route ) {
		register_rest_route( $this->get_vendor() . '/v' . $this->get_version(), $route['path'], array(
			'methods'             => $route['methods'],
			'permission_callback' => array( $this, $route['permission_callback'] ),
			'callback'            => array( $this, $route['callback'] ),
		) );
	}

	/**
	 * Get Carbon Fields post meta values
	 * 
	 * @param  array $data
	 * @return array
	 */
	public function get_post_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'Post_Meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields user meta values
	 * 
	 * @param  array $data
	 * @return array
	 */
	public function get_user_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'User_Meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields term meta values
	 * 
	 * @param  array $data
	 * @return array
	 */
	public function get_term_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'Term_Meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields comment meta values
	 * 
	 * @param  array $data
	 * @return array
	 */
	public function get_comment_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'Comment_Meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Wrapper method used for retrieving data from Data_Manager
	 * 
	 * @param  string $container_type 
	 * @param  string $id
	 * @return array
	 */
	protected function get_all_field_values( $container_type, $id = '' ) {
		return Data_Manager::instance()->get_all_field_values( $container_type, $id );
	}

	/**
	 * Retrieve Carbon theme options
	 * 
	 * @return array
	 */
	protected function get_options() {
		$carbon_data = $this->get_all_field_values( 'Theme_Options' );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Set Carbon theme options
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	protected function set_options( $request ) {
		$options = $request->get_params();
		
		if ( empty( $options ) ) {
			return new \WP_REST_Response( __( 'No option names provided', 'crb' ) );
		}
		
		foreach ( $options as $key => $value ) {
			try {
				Updater::update_field( 'theme_option', null, $key, $value );	
			} catch ( \Exception $e ) {
				return new \WP_REST_Response( wp_strip_all_tags( $e->getMessage() ) );
			}
		}

		return new \WP_REST_Response( __( 'Theme Options updated.', 'crb' ), 200 );
	}

	/**
	 * Proxy method for handling get/set for theme options
	 * 
	 * @param  WP_REST_Request $request 
	 * @return array|WP_REST_Response 
	 */
	public function options_accessor( $request ) {
		$request_type = $request->get_method();
		
		if ( $request_type === 'GET' ) {
			return $this->get_options();
		}

		if ( $request_type === 'POST' ) {
			return $this->set_options( $request );
		}
	}

	/**
	 * Proxy method for handling theme options permissions
	 * 
	 * @param  WP_REST_Request $request 
	 * @return bool
	 */
	public function options_permission( $request ) {
		$request_type = $request->get_method();

		if ( $request_type === 'GET' ) {
			return true;
		}

		if ( $request_type === 'POST' ) {
			return current_user_can( 'manage_options' );
		}
	}

	/**
	 * Set routes
	 */
	public function set_routes( $routes ) {
		$this->routes = $routes;
	}

	/**
	 * Return routes
	 * 
	 * @return array
	 */
	public function get_routes() {
		return $this->routes;
	}

	/**
	 * Set version
	 */
	public function set_version( $version ) {
		$this->version = $version;
	}

	/**
	 * Return version
	 * 
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Set vendor
	 */
	public function set_vendor( $vendor ) { 
		$this->vendor = $vendor;
	}

	/**
	 * Return vendor
	 * 
	 * @return string
	 */
	public function get_vendor() {
		return $this->vendor;
	}

	/**
	 * Allow access to an endpoint
	 * 
	 * @return bool true
	 */
	public function allow_access() {
		return true;
	}
}