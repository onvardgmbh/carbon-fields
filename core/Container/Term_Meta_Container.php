<?php

namespace Carbon_Fields\Container;

use Carbon_Fields\Datastore\Meta_Datastore;
use Carbon_Fields\Datastore\Term_Meta_Datastore;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Term meta container class.
 */
class Term_Meta_Container extends Container {
	protected $term_id;

	public $settings = array(
		'taxonomy' => array( 'category' ),
		'show_on_level' => false,
	);

	/**
	 * Create a new term meta fields container
	 *
	 * @param string $title Unique title of the container
	 **/
	public function __construct( $title ) {
		parent::__construct( $title );

		if ( ! $this->get_datastore() ) {
			$this->set_datastore( new Term_Meta_Datastore(), $this->has_default_datastore() );
		}
	}

	/**
	 * Bind attach() and save() to the appropriate WordPress actions.
	 **/
	public function init() {
		// force taxonomy to be array
		if ( ! is_array( $this->settings['taxonomy'] ) ) {
			$this->settings['taxonomy'] = array( $this->settings['taxonomy'] );
		}

		add_action( 'carbon_containers_attach', array( $this, '_attach' ) );
		add_action( 'carbon_containers_attach_all', array( $this, '_attach_all' ) );
		add_action( 'rest_api_init', array( $this, '_attach_all' ) );

		foreach ( $this->settings['taxonomy'] as $taxonomy ) {
			add_action( 'edited_' . $taxonomy, array( $this, '_save' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( $this, '_save' ), 10, 2 );
		}
	}

	/**
	 * Set the term ID the container will operate with.
	 *
	 * @param int $term_id
	 **/
	public function set_term_id( $term_id ) {
		$this->term_id = $term_id;
		$this->get_datastore()->set_id( $term_id );
	}

	/**
	 * Perform checks whether the current save() request is valid.
	 *
	 * @param int $term_id ID of the term against which save() is ran
	 * @return bool
	 **/
	public function is_valid_save( $term_id = null ) {
		if ( ! isset( $_REQUEST[ $this->get_nonce_name() ] ) || ! wp_verify_nonce( $_REQUEST[ $this->get_nonce_name() ], $this->get_nonce_name() ) ) { // Input var okay.
			return false;
		} else if ( $term_id < 1 ) {
			return false;
		}

		return true;
	}

	/**
	 * Perform save operation after successful is_valid_save() check.
	 * The call is propagated to all fields in the container.
	 *
	 * @param int $term_id ID of the term against which save() is ran
	 **/
	public function save( $term_id ) {
		$this->set_term_id( $term_id );

		foreach ( $this->fields as $field ) {
			$field->set_value_from_input();
			$field->save();
		}

		do_action( 'carbon_after_save_term_meta', $term_id );
	}

	/**
	 * Returns the number of parents of a taxonomy term
	 * 
	 * @param  object $term 
	 * @return int
	 */
	protected function get_term_level( $term ) {
		$ancestors = array();	
		while ( ! is_wp_error( $term ) && ! empty( $term->parent ) && ! in_array( $term->parent, $ancestors ) ) {
			$ancestors[] = intval( $term->parent );
			$term        = get_term( $term->parent );
		}

		return count( $ancestors ) + 1;
	}

	/**
	 * Perform checks whether the container should be attached during the current request
	 *
	 * @return bool True if the container is allowed to be attached
	 **/
	public function is_valid_attach() {
		if ( isset( $_GET['taxonomy'] ) && in_array( $_GET['taxonomy'], $this->settings['taxonomy'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Perform checks whether the container should be attached for the specified object (id)
	 *
	 * @return bool True if the container is allowed to be attached
	 **/
	public function is_valid_attach_for_object( $object_id = 0 ) {
		$term = get_term( $object_id );

		if ( empty( $term ) || is_wp_error( $term ) ) { 
			return false;
		}
		
		$taxonomy = $term->taxonomy;

		if ( ! in_array( $taxonomy, $this->settings['taxonomy'] ) ) {
			return false;
		}

		if ( $this->settings['show_on_level'] ) { 
			
			$show_level = $this->settings['show_on_level'];
			$term_level = $this->get_term_level( $term );

			if ( $term_level !== $show_level ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add term meta for each of the container taxonomies
	 **/
	public function attach() {
		foreach ( $this->settings['taxonomy'] as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render' ), 10, 2 );
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render' ), 10, 2 );
		}
	}

	/**
	 * Revert the result of attach()
	 *
	 **/
	public function detach() {
		parent::detach();

		remove_action( 'carbon_containers_attach', array( $this, '_attach' ) );
		remove_action( 'carbon_containers_attach_all', array( $this, '_attach_all' ) );
		remove_action( 'rest_api_init', array( $this, '_attach_all' ) );

		foreach ( $this->settings['taxonomy'] as $taxonomy ) {
			remove_action( 'edited_' . $taxonomy, array( $this, '_save' ), 10 );
			remove_action( 'created_' . $taxonomy, array( $this, '_save' ), 10 );
		}

		// unregister field names
		foreach ( $this->fields as $field ) {
			$this->drop_unique_field_name( $field->get_name() );
		}
	}

	/**
	 * Output the container markup
	 **/
	public function render( $term = null ) {
		if ( is_object( $term ) ) {
			$this->set_term_id( $term->term_id );
		}

		include \Carbon_Fields\DIR . '/templates/Container/term_meta.php';
	}

	/**
	 * Show the container only on terms from the specified taxonomies.
	 *
	 * @param string|array $taxonomies
	 * @return object $this
	 **/
	public function show_on_taxonomy( $taxonomies ) {
		$taxonomies = (array) $taxonomies;

		$this->settings['taxonomy'] = $taxonomies;

		return $this;
	}

	/**
	 * Show the container only on particular term level.
	 *
	 * @param int $term_level
	 * @return object $this
	 */
	public function show_on_level( $term_level ) {
		$this->settings['show_on_level'] = $term_level;
		return $this;
	}
}
