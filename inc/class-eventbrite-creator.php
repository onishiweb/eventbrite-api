<?php
/**
 * Eventbrite Creator class for handling creation of events through WordPress post type
 *
 * @package Eventbrite_API
 */

class Eventbrite_Creator extends Eventbrite_Manager {

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

	protected $defaults = array(
					'tickets' => 'eventbrite_tickets',
					'venues'  => 'eventbrite_venues',
					);

	public function __construct() {
		// Assign our instance.
		self::$instance = $this;

		// Add post meta actions
		add_action( 'add_meta_boxes', array( $this, 'add_event_meta_boxes' ) );

		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );

		// Eventbrite create action
	    add_action( 'admin_post_eventbrite_create_event', array( 'Eventbrite_Creator', 'do_event_create'), 1 );
	    add_action( 'admin_post_eventbrite_update_event', array( 'Eventbrite_Creator', 'do_event_update'), 1 );
	    add_action( 'admin_post_eventbrite_publish_event', array( 'Eventbrite_Creator', 'do_event_publish'), 1 );
	    add_action( 'admin_post_eventbrite_unpublish_event', array( 'Eventbrite_Creator', 'do_event_unpublish'), 1 );
	}

	public function get_defaults() {
		return $this->defaults;
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
	public function setup_event_post_type( $post_type, $args ) {
		$this->post_type = $post_type;

		new Eventbrite_Tickets;
		new Eventbrite_Venues;

		$defaults = $this->get_defaults();
		$args = wp_parse_args( $args, $defaults );

		if( ! post_type_exists( $args['tickets'] ) ) {
			eventbrite_tickets()->register_post_type($args['tickets'], $post_type);
		} else {
			eventbrite_tickets()->setup_post_type($args['tickets']);
		}

		if( ! post_type_exists( $args['venues'] ) ) {
			eventbrite_venues()->register_post_type($args['venues'], $post_type);
		} else {
			eventbrite_venues()->setup_post_type($args['venues']);
		}
	}

	/**
	 * Add an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_event_create() {

		$post_id = $_GET['post_id'];

		$params = eventbrite_creator()->map_event_keys($post_id);

		// Get the raw results.
		$result = eventbrite_creator()->request( 'create_event', $params, false, true );

		if( empty($result->errors) ) {
			add_post_meta( $post_id, 'eventbrite_event_id', $result->id, true );
			add_post_meta( $post_id, 'eventbrite_event_status', 'unpublished', true );
			add_post_meta( $post_id, 'eventbrite_event_url', $result->url, true );

			// If the event has been created successfully, now create the tickets
			eventbrite_creator()->add_tickets($post_id, $result->id);
		} else {
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		}

		$redirect_to = get_edit_post_link( $post_id, '' );

		wp_safe_redirect( $redirect_to );
		exit();
	}

	/**
	 * Update an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_event_update() {
		$post_id = $_GET['post_id'];
		$event_id = $_GET['eventbrite_id'];

		$params = eventbrite_creator()->map_event_keys($post_id);

		// Get the raw results.
		$results = eventbrite_creator()->request( 'update_event', $params, $event_id, true );

		if( empty($result->errors) ) {
			//
			// If the event has been updated successfully, now create the tickets
			eventbrite_creator()->add_tickets($post_id, $result->id);
		} else {
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		}

		$redirect_to = get_edit_post_link( $post_id, '' );

		wp_safe_redirect( $redirect_to );
		exit();
	}

	/**
	 * Publish an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_event_publish() {
		$post_id = $_GET['post_id'];
		$event_id = $_GET['eventbrite_id'];

		// Get the raw results.
		$results = eventbrite_creator()->request( 'publish_event', false, $event_id, true, 'publish' );

		if( $results && empty($result->errors) ) {
			// Event published
			add_post_meta( $post_id, 'eventbrite_event_status', 'published', true );
		} else {
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
			print_r($result);
			die();
		}

		$redirect_to = get_edit_post_link( $post_id, '' );

		wp_safe_redirect( $redirect_to );
		exit();
	}

	/**
	 * Unpublish an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_event_unpublish() {
		$post_id = $_GET['post_id'];
		$event_id = $_GET['eventbrite_id'];

		// Get the raw results.
		$results = eventbrite_creator()->request( 'publish_event', false, $event_id, true, 'unpublish' );

		if( empty($result->errors) ) {
			// Event published
			add_post_meta( $post_id, 'eventbrite_event_status', 'published', true );
		} else {
			print_r($result);
			die();
			add_post_meta( $post_id, 'eventbrite_event_error', '', true );
		}

		$redirect_to = get_edit_post_link( $post_id, '' );

		wp_safe_redirect( $redirect_to );
		exit();
	}

	public function add_tickets($post_id, $event_id) {
		$rows = get_field('event_tickets', $post_id);
		$results = array();

		if($rows) {
			foreach($rows as $row) {
				$results[] = eventbrite_tickets()->do_tickets_create($row['event_ticket_type'], $row['event_ticket_quantity'], $event_id);
			}
		}

		foreach( $results as $result ) {
			if( empty($result->errors) ) {
				// Handle success notification
			} else {
				// Handle error notification
			}
		}
	}

	public function display_admin_notice() {

		return;

		$notice = 'Eventbrite event created';

		// Output notice HTML.
		printf( '<div id="message" class="updated"><p>%s</p></div>', $notice );
	}

	/**
	 * Setup meta boxes for the events
	 */
	public function add_event_meta_boxes() {
		add_meta_box (
				'eventbrite_event_meta',
				__('Eventbrite', 'eventbrite-api'),
				array( $this, 'event_meta_fields' ),
				eventbrite_creator()->post_type,
				'side'
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

		$submit_value = 'Create Eventbrite Event';
		$action = 'eventbrite_create_event';
		$evenbrite_id_data = '';

		$evenbrite_id = get_post_meta( $post->ID, 'eventbrite_event_id', true);

		if( $eventbrite_id ) {
			$submit_value = 'Update Eventbrite Event';
			$action = 'eventbrite_update_event';
		}

		$url = add_query_arg( array(
		    'action'        => $action,
		    'post_id'       => $post->ID,
		    'eventbrite_id' => get_post_meta( $post->ID, 'eventbrite_event_id', true),
		), admin_url( 'admin-post.php' ) );
		?>

		<p><a href="<?php echo $url; ?>" class="button button-primary button-large"><?php echo $submit_value; ?></a></p>

		<?php

		if( ! $evenbrite_id ) {
			return;
		}

		$status = get_post_meta( $post_id, 'eventbrite_event_status', true );

		if( 'published' === $status ) {
			$publish_action = 'eventbrite_unpublish_event';
			$publish_submit = 'Unpublish event';
		} else {
			$publish_action = 'eventbrite_publish_event';
			$publish_submit = 'Publish event';
		}

		$publish_url = add_query_arg( array(
		    'action'        => $publish_action,
		    'post_id'       => $post->ID,
		    'eventbrite_id' => get_post_meta( $post->ID, 'eventbrite_event_id', true),
		), admin_url( 'admin-post.php' ) );

		?>
		<p><a href="<?php echo $publish_url; ?>" class="button button-secondary"><?php echo $publish_submit; ?></a></p>

		<?php
	}

	/**
	 * Return an array of valid request parameters by endpoint.
	 *
	 * @access protected
	 *
	 * @return array All valid request parameters for supported endpoints.
	 */
	protected function get_endpoint_params() {
		$event_params = array(
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
			'event.show_remaining' => array(),
		);

		$params = array(
			'create_event' => $event_params,
			'update_event' => $event_params,
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
		$event = array();

		$event['event.name.html']        = get_the_title( $post_id );
		$event['event.description.html'] = get_field( 'event_description', $post_id );
		$event['event.start.utc']        = gmdate('Y-m-d\TH:i:s\Z', strtotime( get_field( 'event_start_time', $post_id) ) );
		$event['event.start.timezone']   = 'Europe/London';
		$event['event.end.utc']          = gmdate('Y-m-d\TH:i:s\Z', strtotime( get_field( 'event_end_time', $post_id) ) );
		$event['event.end.timezone']     = 'Europe/London';
		$event['event.currency']         = 'GBP';
		$event['event.online_event']     = false;
		$event['event.listed']           = true;
		$event['event.shareable']        = true;
		$event['event.show_remaining']   = true;

		$venue_id = get_field( 'event_venue', $post_id );
		$event['event.venue_id']         = get_field( 'eventbrite_venue_id', $venue_id ); //'13696417';

		// Fields not yet being used:
		//
		// $event['event.organizer_id']     = get_post_meta($post_id, '', true);
		// $event['event.logo_id']          = get_post_meta($post_id, '', true);
		// $event['event.category_id']      = get_post_meta($post_id, '', true);
		// $event['event.format_id']        = get_post_meta($post_id, '', true);
		// $event['event.invite_only']      = get_post_meta($post_id, '', true);
		// $event['event.password']         = get_post_meta($post_id, '', true);
		// $start_time = get_post_meta($post_id, 'start_utc_date', true) . ' ' . get_post_meta($post_id, 'start_utc_time', true);
		// $event['event.start.utc']        = gmdate('Y-m-d\TH:i:s\Z', strtotime($start_time));
		// $end_time = get_post_meta($post_id, 'end_utc_date', true) . ' ' . get_post_meta($post_id, 'end_utc_time', true);
		// $event['event.end.utc']          = gmdate('Y-m-d\TH:i:s\Z', strtotime($end_time));

		return $event;
	}
}

new Eventbrite_Creator;

function eventbrite_creator() {
	return Eventbrite_Creator::$instance;
}
