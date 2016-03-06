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
			'name.html' => array(),
			'description.html' => array(),
			'organizer_id' => array(),
			'start.utc' => array(),
			'start.timezone' => array(),
			'end.utc' => array(),
			'end.timezone' => array(),
			'currency' => array(),
			'venue_id' => array(),
			'online_event' => array(),
			'listed' => array(),
			'logo_id' => array(),
			'category_id' => array(),
			'format_id' => array(),
			'shareable' => array(),
			'invite_only' => array(),
			'password' => array(),
			'capacity' => array(),
			'show_remaining' => array(),
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
