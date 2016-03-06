<?php
/**
 * ticket_class.name	string	Yes	Name of this ticket type
ticket_class.description	string	No	Description of the ticket
ticket_class.quantity_total	integer	No	Total available number of this ticket
ticket_class.cost	currency	No	Cost of the ticket (currently currency must match event currency) e.g. $45 would be ‘USD,4500’
ticket_class.donation	unknown	No	Is this a donation? (user-supplied cost)
ticket_class.free	unknown	No	Is this a free ticket?
ticket_class.include_fee	unknown	No	Absorb the fee into the displayed cost
ticket_class.split_fee	unknown	No	Absorb the payment fee, but show the eventbrite fee
ticket_class.hide_description	unknown	No	Hide the ticket description on the event page
ticket_class.sales_start	datetime	No	When the ticket is available for sale (leave empty for ‘when event published’)
ticket_class.sales_end	datetime	No	When the ticket stops being on sale (leave empty for ‘one hour before event start’)
ticket_class.sales_start_after	string	No	The ID of another ticket class - when it sells out, this class will go on sale.
ticket_class.minimum_quantity	integer	No	Minimum number per order
ticket_class.maximum_quantity	integer	No	Maximum number per order (blank for unlimited)
ticket_class.auto_hide	unknown	No	Hide this ticket when it is not on sale
ticket_class.auto_hide_before	datetime	No	Override reveal date for auto-hide
ticket_class.auto_hide_after	datetime	No	Override re-hide date for auto-hide
ticket_class.hidden	unknown	No	Hide this ticket
 */

/**
 * Eventbrite Ticket class for handling creation and updating of tickets for events through WordPress post type
 *
 * @package Eventbrite_API
 */

class Eventbrite_Tickets extends Eventbrite_Creator {

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
		$ticket_labels = array(
			'name'               => 'Tickets',
			'singular_name'      => 'Ticket',
			'add_new'            => 'Add new',
			'add_new_item'       => 'Add new ticket',
			'edit_item'          => 'Edit ticket',
			'new_item'           => 'New ticket',
			'all_items'          => 'All tickets',
			'view_item'          => 'View tickets',
			'search_items'       => 'Search tickets',
			'not_found'          => 'No tickets found',
			'not_found_in_trash' => 'No tickets found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Tickets',
			);

		register_post_type( $post_type,
			array(
				'labels'          => $ticket_labels,
				'public'          => true,
				'has_archive'     => false,
				'capability_type' => 'post',
				'supports'        => array( 'title', 'page-attributes' ),
				'rewrite'         => array( 'slug' => 'ticket' ),
				'show_in_menu'    => 'edit.php?post_type=' . $event_post_type,
				'menu_icon'       => 'dashicons-calendar-alt',
				)
			);

		echo "TICKETS";
	}

	/**
	 * Add an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_tickets_create( $post_id, $params = array() ) {
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
	public function do_tickets_update( $post_id, $params = array() ) {
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
		$ticket_params = array(
			'ticket_class.name' => array(),
			'ticket_class.description' => array(),
			'ticket_class.quantity_total' => array(),
			'ticket_class.cost' => array(),
			'ticket_class.donation' => array(),
			'ticket_class.free' => array(),
			'ticket_class.include_fee' => array(),
			'ticket_class.split_fee' => array(),
			'ticket_class.hide_description' => array(),
			'ticket_class.sales_start' => array(),
			'ticket_class.sales_end' => array(),
			'ticket_class.sales_start_after' => array(),
			'ticket_class.minimum_quantity' => array(),
			'ticket_class.maximum_quantity' => array(),
			'ticket_class.auto_hide' => array(),
			'ticket_class.auto_hide_before' => array(),
			'ticket_class.auto_hide_after' => array(),
			'ticket_class.hidden' => array(),
		);

		$params = array(
			'create_ticket' => $ticket_params,
			'update_ticket' => $ticket_params,
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
		$ticket = array();

		$ticket['ticket_class.name']              = array();
		$ticket['ticket_class.description']       = array();
		$ticket['ticket_class.quantity_total']    = array();
		$ticket['ticket_class.cost']              = array();
		$ticket['ticket_class.donation']          = array();
		$ticket['ticket_class.free']              = array();
		$ticket['ticket_class.include_fee']       = array();
		$ticket['ticket_class.split_fee']         = array();
		$ticket['ticket_class.hide_description']  = array();
		$ticket['ticket_class.sales_start']       = array();
		$ticket['ticket_class.sales_end']         = array();
		$ticket['ticket_class.sales_start_after'] = array();
		$ticket['ticket_class.minimum_quantity']  = array();
		$ticket['ticket_class.maximum_quantity']  = array();
		$ticket['ticket_class.auto_hide']         = array();
		$ticket['ticket_class.auto_hide_before']  = array();
		$ticket['ticket_class.auto_hide_after']   = array();
		$ticket['ticket_class.hidden']            = array();

		return $ticket;
	}
}

function eventbrite_tickets() {
	echo "eventbrite_tickets";
	return Eventbrite_Tickets::$instance;
}
