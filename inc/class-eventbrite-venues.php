<?php
/**
 * Eventbrite Venues class for handling creation and updating of venues for events through WordPress post type
 *
 * @package Eventbrite_API
 */

class Eventbrite_Venues extends Eventbrite_Creator {
	/**
	 * Class instance used by themes and plugins.
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Stores the post_type to use when creating events
	 *
	 * @var string
	 */
	protected $post_type;

	public function __construct() {
		// Assign our instance.
		self::$instance = $this;

		add_action( 'save_post', array( $this, 'venue_post_save' ) );
	}

	public function setup_post_type( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * Register a new post type for storing events
	 *
	 * @param  string $post_type Name of the post type the user wants to create
	 * @return void
	 */
	public function register_post_type( $post_type, $event_post_type ) {
		$labels = array(
			'name'               => 'Venues',
			'singular_name'      => 'Venue',
			'add_new'            => 'Add new',
			'add_new_item'       => 'Add new venue',
			'edit_item'          => 'Edit venue',
			'new_item'           => 'New venue',
			'all_items'          => 'Venues',
			'view_item'          => 'View venues',
			'search_items'       => 'Search venues',
			'not_found'          => 'No venues found',
			'not_found_in_trash' => 'No venues found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Venues',
			);

		register_post_type( $post_type,
			array(
				'labels'          => $labels,
				'public'          => true,
				'has_archive'     => false,
				'capability_type' => 'post',
				'supports'        => array( 'title', 'page-attributes' ),
				'rewrite'         => array( 'slug' => 'venue' ),
				'show_in_menu'    => 'edit.php?post_type=' . $event_post_type,
				'menu_icon'       => 'dashicons-calendar-alt',
				)
			);

		$this->post_type = $post_type;
		$this->setup_acf_fields();
	}

	/**
	 * Add an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_venues_create( $post_id, $params = array() ) {
		// Get the raw results.
		$result = $this->request( 'create_venue', $params, false, true );

		if( empty($result->errors) ) {
			add_post_meta( $post_id, 'eventbrite_venue_id', $result->id, true );
		} else {
			add_post_meta( $post_id, 'eventbrite_venue_error', '', true );
		}
	}

	/**
	 * Update an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_venues_update( $post_id, $params = array() ) {
		$venue_id = get_post_meta( $post_id, 'eventbrite_venue_id', true );

		// Get the raw results.
		$results = $this->request( 'update_venue', $params, $venue_id, true );

		if( empty($result->errors) ) {
			add_post_meta( $post_id, 'eventbrite_event_updated', 'true', true );
		} else {
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		}
	}

	public function venue_post_save( $post_id ) {
		// verify if this is an auto save routine.
		// If it is the post has not been updated, so we don’t want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Get the post type object.
		global $post;
		$post_type = get_post_type_object( $post->post_type );

		// Check if the current user has permission to edit the post.
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return $post_id;
		}

		if( get_post_meta( $post_id, 'eventbrite_venue_id', true) ) {
			$this->do_venues_update( $post_id, $this->map_event_keys($post_id) );
		} else {
			// Create the event through the API
			$this->do_venues_create( $post_id, $this->map_event_keys($post_id) );
		}
	}

	/**
	 * Return an array of valid request parameters by endpoint.
	 *
	 * @access protected
	 *
	 * @return array All valid request parameters for supported endpoints.
	 */
	protected function get_endpoint_params() {
		$venue_params = array(
			'venue.name' => array(),
			'venue.address.address_1' => array(),
			'venue.address.address_2' => array(),
			'venue.address.city' => array(),
			'venue.address.region' => array(),
			'venue.address.postal_code' => array(),
			'venue.address.country' => array(),
			'venue.address.latitude' => array(),
			'venue.address.longitude' => array(),
		);

		$params = array(
			'create_venue' => $venue_params,
			'update_venue' => $venue_params,
		);

		return $params;
	}

	/**
	 * Convert the post properties into properties used by the Eventbrite API.
	 *
	 * @access protected
	 *
	 * @param object $api_event A single event from the API results.
	 * @return object Event with Eventbrite_Event keys.
	 */
	protected function map_event_keys( $post_id ) {
		$venue = array();

		$venue['venue.name']                 = get_the_title( $post_id );
		$venue['venue.address.address_1']    = get_field( 'venue_address_line_1', $post_id );
		$venue['venue.address.address_2']    = get_field( 'venue_address_line_2', $post_id );
		$venue['venue.address.city']         = get_field( 'venue_city', $post_id );
		$venue['venue.address.region']       = get_field( 'venue_county', $post_id );
		$venue['venue.address.postal_code']  = get_field( 'venue_post_code', $post_id );
		$venue['venue.address.country']      = 'GB';
		$venue['venue.address.latitude']     = $location['lat'];
		$venue['venue.address.longitude']    = $location['lng'];

		return $venue;
	}

	protected function setup_acf_fields() {
		if( function_exists('acf_add_local_field_group') ) {

			acf_add_local_field_group(array (
				'key' => 'group_56dc5e215de0d',
				'title' => 'Venues',
				'fields' => array (
					array (
						'key' => 'field_56dc5ee4b7708',
						'label' => 'Address line 1',
						'name' => 'venue_address_line_1',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5ef5b7709',
						'label' => 'Address line 2',
						'name' => 'venue_address_line_2',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5f05b770a',
						'label' => 'City',
						'name' => 'venue_city',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 'London',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5f17b770b',
						'label' => 'County',
						'name' => 'venue_county',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5f32b770c',
						'label' => 'Post code',
						'name' => 'venue_post_code',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5f3db770d',
						'label' => 'Country',
						'name' => 'venue_country',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 'United Kingdom',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5e3248eab',
						'label' => 'Venue location',
						'name' => 'venue_location',
						'type' => 'google_map',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'center_lat' => '',
						'center_lng' => '',
						'zoom' => '',
						'height' => '',
					),
				),
				'location' => array (
					array (
						array (
							'param' => 'post_type',
							'operator' => '==',
							'value' => $this->post_type,
						),
					),
				),
				'menu_order' => 0,
				'position' => 'acf_after_title',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'field',
				'hide_on_screen' => '',
				'active' => 1,
				'description' => '',
			));
		} else {
			// Show error about ACF
		}
	}
}

function eventbrite_venues() {
	return Eventbrite_Venues::$instance;
}
