<?php
/**
 * Eventbrite Creator class for handling creation of events through WordPress post type
 *
 * @package Eventbrite_API
 */

class Eventbrite_Creator extends Eventbrite_Manager {

	/**
	 * Stores the post_type to use when creating events
	 *
	 * @var string
	 */
	protected $post_type;

	public function __construct() {
		parent::__construct();

		// Add post meta actions
		add_action( 'add_meta_boxes', array( $this, 'add_event_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_data' ) );
	}

	/**
	 * Register a new post type for storing events
	 *
	 * @param  string $post_type Name of the post type the user wants to create
	 * @return void
	 */
	public function register_post_type( $post_type ) {
		$labels = array(
			'name'               => 'Events',
			'singular_name'      => 'Event',
			'add_new'            => 'Add new',
			'add_new_item'       => 'Add new event',
			'edit_item'          => 'Edit event',
			'new_item'           => 'New event',
			'all_items'          => 'All events',
			'view_item'          => 'View events',
			'search_items'       => 'Search events',
			'not_found'          => 'No events found',
			'not_found_in_trash' => 'No events found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Events',
			);

		register_post_type( $post_type,
			array(
				'labels'          => $labels,
				'public'          => true,
				'has_archive'     => true,
				'capability_type' => 'post',
				'supports'        => array( 'title', 'page-attributes' ),
				'rewrite'         => array( 'slug' => 'event' ),
				'menu_position'   => 5,
				'menu_icon'       => 'dashicons-calendar-alt',
				)
			);
	}

	/**
	 * [setup_event_post_type description]
	 *
	 * @param  string $post_type Name of the post type to be used for events
	 * @return void
	 */
	public function setup_event_post_type( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * Add an event events.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return ?
	 */
	public function do_event_create( $params = array() ) {
		// Get the raw results.
		$results = $this->request( 'create_event', $params, false, true );

		return $results;
	}

	/**
	 * Setup meta boxes for the events
	 */
	public function add_event_meta_boxes() {
		add_meta_box (
				'eventbrite_event_meta',
				__('Event details', 'eventbrite-api'),
				array( $this, 'event_meta_fields' ),
				$this->post_type,
				'normal',
				'high'
			);
	}

	/**
	 * [event_meta_fields description]
	 *
	 * @param  object $post post object for the post being edited
	 * @return void
	 */
	public function event_meta_fields( $post ) {
		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), 'eventbrite_meta_noncename' );

		$fields = $this->get_event_post_fields();

		foreach( $fields as $field => $settings ) {
			$field_name = str_replace(['.'], '_', $field);

			$value = get_post_meta($post->ID, $field_name) ? get_post_meta($post->ID, $field_name, true) : '';
			echo $this->build_field($field, $settings, $value);
		}
	}

	/**
	 * [save_meta_data description]
	 *
	 * @param  integer $post_id ID of the post being updated
	 * @return void
	 */
	public function save_meta_data( $post_id ) {

		// verify if this is an auto save routine.
		// If it is the post has not been updated, so we don’t want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// verify this came from the screen and with proper authorization,
		// because save_post can be triggered at other times
		if ( !isset( $_POST['eventbrite_meta_noncename'] ) || !wp_verify_nonce( $_POST['eventbrite_meta_noncename'], plugin_basename( __FILE__ ) ) ) {
			return $post_id;
		}

		// Get the post type object.
		global $post;
		$post_type = get_post_type_object( $post->post_type );

		// Check if the current user has permission to edit the post.
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return $post_id;
		}

		$fields = $this->get_event_post_fields();
		$meta = array();

		// Get the posted data and pass it into an associative array for ease of entry
		foreach( $fields as $field => $settings ) {
			$field_name = str_replace(['.'], '_', $field);

			$meta[$field_name] = ( isset( $_POST[$field_name] ) ? $_POST[$field_name] : '' );
		}

		// add/update record (both are taken care of by update_post_meta)
		foreach( $meta as $key => $value ) {
			// get current meta value
			$current_value = get_post_meta( $post_id, $key, true);

			if ( $value && '' == $current_value ) {
				add_post_meta( $post_id, $key, $value, true );
			} elseif ( $value && $value != $current_value ) {
				update_post_meta( $post_id, $key, $value );
			} elseif ( '' == $value && $current_value ) {
				delete_post_meta( $post_id, $key, $current_value );
			}
		}

		// TODO: Check if there is already an event ID stored
		// if so, update instead of add

		if( get_post_meta( $post_id, 'eventbrite_event_id', true) ) {
			delete_post_meta( $post_id, 'eventbrite_event_created', 'true' );
			delete_post_meta( $post_id, 'eventbrite_event_error', '' );
		} else {
			// Create the event through the API
			$result = $this->do_event_create( $this->map_event_keys($post_id) );
		}

		if( empty($result->errors) ) {
			add_post_meta( $post_id, 'eventbrite_event_id', $result->id, true );
			add_post_meta( $post_id, 'eventbrite_event_url', $result->url, true );

			add_post_meta( $post_id, 'eventbrite_event_created', 'true', true );
		} else {
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
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
		$params = array(
			'create_event' => array(
				'event.name.html' => array(),
				'event.description.html' => array(),
				'event.organizer_id' => array(),
				'event.start.utc' => array(),
				'event.start.timezone' => array(),
				'event.end.utc' => array(),
				'event.end.timezone' => array(),
				'event.currency' => array(),
				'event.venue_id' => array(),
				'event.online_event' => array(),
				'event.listed' => array(),
				'event.logo_id' => array(),
				'event.category_id' => array(),
				'event.format_id' => array(),
				'event.shareable' => array(),
				'event.invite_only' => array(),
				'event.password' => array(),
				'event.capacity' => array(),
				'event.show_remaining' => array(),
			),
		);

		return $params;
	}

	protected function build_field($field, $settings, $value) {

		$field_name = str_replace(['.'], '_', $field);
		$field_id = str_replace(['.', '_'], '-', $field);

		echo '<div class="eventbrite-event-field">';

		echo '<label class="eventbrite-event-label" for="' . $field_id . '">' . $settings['title'] . '</label>';

		switch( $settings['type'] ) {
			case 'textarea':
				echo '<textarea class="eventbrite-event-textarea" name="'. $field_name . '" id="' . $field_id . '">' . $value . '</textarea>';
				break;
			default:
				echo '<input type="' . $settings['type'] . '" class="eventbrite-event-input" name="'. $field_name . '" id="' . $field_id . '" value="' . $value . '">';
				break;

			// case 'select':
			// 	// $options = $this->get_post_as_options($settings['values_arg']);

			// 	// echo '<select name="'. $field_name . '" id="' . $field_id . '">';



			// 	// echo '</select>';
			// 	break;
		}

		echo '</div>';
	}

	protected function get_event_post_fields() {
		$fields = array(
			'description.html' => array(
				'title' => 'Event description',
				'type' => 'textarea',
			),
			'start.utc.date' => array(
				'title' => 'Start date',
				'type' => 'date',
			),
			'start.utc.time' => array(
				'title' => 'Start time',
				'type' => 'time',
			),
			'end.utc.date' => array(
				'title' => 'End date',
				'type' => 'date',
			),
			'end.utc.time' => array(
				'title' => 'End time',
				'type' => 'time',
			),
			'venue_id' => array(
				'title' => 'Venue',
				'type' => 'select',
				'values' => 'post_type',
				'values_arg' => 'eventbrite_venue',
			),
			'online_event' => array(
				'title' => 'Online event only',
				'type' => 'checkbox',
			),
			'listed' => array(
				'title' => 'Publicly listed',
				'type' => 'checkbox',
			),
			'capacity' => array(
				'title' => 'Capacity',
				'type' => 'number',
			),
		);

		return $fields;
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
		$event = array();

		$event['event.name.html']        = get_the_title($post_id);
		$event['event.description.html'] = get_post_meta($post_id, 'description_html', true);
		// $event['event.organizer_id']     = get_post_meta($post_id, '', true);

		$start_time = get_post_meta($post_id, 'start_utc_date', true) . ' ' . get_post_meta($post_id, 'start_utc_time', true);
		$event['event.start.utc']        = gmdate('Y-m-d\TH:i:s\Z', strtotime($start_time));

		$event['event.start.timezone']   = 'Europe/London';

		$end_time = get_post_meta($post_id, 'end_utc_date', true) . ' ' . get_post_meta($post_id, 'end_utc_time', true);
		$event['event.end.utc']          = gmdate('Y-m-d\TH:i:s\Z', strtotime($end_time));
		$event['event.end.timezone']     = 'Europe/London';
		$event['event.currency']         = 'GBP'; // get_post_meta($post_id, '', true);
		// $event['event.venue_id']         = get_post_meta($post_id, '', true);
		$event['event.online_event']     = false; // get_post_meta($post_id, '', true);
		$event['event.listed']           = true; // get_post_meta($post_id, '', true);
		// $event['event.logo_id']          = get_post_meta($post_id, '', true);
		// $event['event.category_id']      = get_post_meta($post_id, '', true);
		// $event['event.format_id']        = get_post_meta($post_id, '', true);
		$event['event.shareable']        = true; // get_post_meta($post_id, '', true);
		// $event['event.invite_only']      = get_post_meta($post_id, '', true);
		// $event['event.password']         = get_post_meta($post_id, '', true);
		$event['event.capacity']         = get_post_meta($post_id, 'capacity', true);
		$event['event.show_remaining']   = true;

		return $event;
	}

	protected function get_post_as_options($post_type) {
		$venue_options = array();

		$args = array(
			'post_type' => $post_type,
			'posts_per_page' => -1,
			'post_status' => 'publish',
		);

		$venues = new WP_Query( $args );

		foreach( $venues->posts as $venue ) {
			$venue_options[] = array(
				'value' => get_post_meta($venue['post_id'], 'eventbrite_venue_id', true),
				'label' => $venue['post_title'],
			);
		}
	}
}

function eventbrite_creator() {
	return Eventbrite_Creator::$instance;
}
