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
			'all_items'          => 'All venues',
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

		echo "VENUES";
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
		// $results = $this->request( 'create_event', $params, false, true );

		// if( empty($result->errors) ) {
		// 	add_post_meta( $post_id, 'eventbrite_event_id', $result->id, true );
		// 	add_post_meta( $post_id, 'eventbrite_event_url', $result->url, true );

		// 	add_post_meta( $post_id, 'eventbrite_event_created', 'true', true );
		// } else {
		// 	add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		// }
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
		// $event_id = get_post_meta( $post_id, 'eventbrite_event_id', true );

		// // Get the raw results.
		// $results = $this->request( 'update_event', $params, $event_id, true );

		// if( empty($result->errors) ) {
		// 	add_post_meta( $post_id, 'eventbrite_event_updated', 'true', true );
		// } else {
		// 	add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		// }
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

		$venue['venue.name']                = array();
		$venue['venue.address.address_1']   = array();
		$venue['venue.address.address_2']   = array();
		$venue['venue.address.city']        = array();
		$venue['venue.address.region']      = array();
		$venue['venue.address.postal_code'] = array();
		$venue['venue.address.country']     = array();
		$venue['venue.address.latitude']    = array();
		$venue['venue.address.longitude']   = array();

		return $venue;
	}
}

function eventbrite_venues() {
	echo "eventbrite_venues";
	return Eventbrite_Venues::$instance;
}
