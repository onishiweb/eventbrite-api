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
		$ticket_labels = array(
			'name'               => 'Tickets',
			'singular_name'      => 'Ticket',
			'add_new'            => 'Add new',
			'add_new_item'       => 'Add new ticket',
			'edit_item'          => 'Edit ticket',
			'new_item'           => 'New ticket',
			'all_items'          => 'Tickets',
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
	public function do_tickets_create($ticket_id, $quantity, $event_id) {
		$params = eventbrite_tickets()->map_ticket_keys($ticket_id, $quantity);

		// Get the raw results.
		$result = eventbrite_tickets()->request( 'create_ticket', $params, $event_id, true, 'ticket_classes/' );

		if( empty($result->errors) ) {
			add_post_meta( $ticket_id, 'eventbrite_ticket_id', $result->id, true );
		} else {
			add_post_meta( $event_id, 'eventbrite_event_error', '', true );
		}

		return $result;
	}

	/**
	 * Update an event.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @return void
	 */
	public function do_tickets_update( $ticket_id, $event_id ) {
		// $params = eventbrite_tickets()->map_ticket_keys($ticket_id, get_post_meta($event_id, 'eventbrite_quantity'));

		// // Get the raw results.
		// $result = eventbrite_tickets()->request( 'create_ticket', $params, $event_id, true, 'ticket_classes/' );

		// if( empty($result->errors) ) {
		// 	add_post_meta( $ticket_id, 'eventbrite_ticket_id', $result->id, true );
		// } else {
		// 	add_post_meta( $event_id, 'eventbrite_event_error', '', true );
		// }

		// return $result;
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
	protected function map_ticket_keys( $post_id, $quantity ) {
		$ticket = array();

		// Change the value to an Int and change to pence value for Eventbrite
		$cost = intval(get_field( 'ticket_cost', $post_id)) * 100;

		$ticket['ticket_class.name']              = get_the_title($post_id);
		$ticket['ticket_class.description']       = get_field( 'ticket_description', $post_id);
		$ticket['ticket_class.quantity_total']    = $quantity;
		$ticket['ticket_class.cost']              = 'GBP,' . $cost;
		$ticket['ticket_class.include_fee']       = get_field( 'ticket_fee', $post_id);
		$ticket['ticket_class.minimum_quantity']  = 1;
		$ticket['ticket_class.maximum_quantity']  = get_field( 'ticket_max_purchase', $post_id);

		if( get_field( 'ticket_start', $post_id) ) {
			$ticket['ticket_class.sales_start']   = gmdate('Y-m-d\TH:i:s\Z', strtotime( get_field( 'ticket_start', $post_id) ) );
		} else {
			$ticket['ticket_class.sales_start']   = '';
		}

		if( get_field( 'ticket_start', $post_id) ) {
			$ticket['ticket_class.sales_end']     = gmdate('Y-m-d\TH:i:s\Z', strtotime( get_field( 'ticket_end', $post_id) ) );
		} else {
			$ticket['ticket_class.sales_end']     = '';
		}

		return $ticket;
	}

	protected function setup_acf_fields() {
		if( function_exists('acf_add_local_field_group') ) {

			acf_add_local_field_group(array (
				'key' => 'group_56dc5b4bdca64',
				'title' => 'Tickets',
				'fields' => array (
					array (
						'key' => 'field_56dc5b6bcfdd6',
						'label' => 'Description',
						'name' => 'ticket_description',
						'type' => 'wysiwyg',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'tabs' => 'all',
						'toolbar' => 'basic',
						'media_upload' => 0,
					),
					array (
						'key' => 'field_56dc5b8acfdd7',
						'label' => 'Cost',
						'name' => 'ticket_cost',
						'type' => 'text',
						'instructions' => 'Ticket cost in GBP (£)',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 5,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_56dc5c68cfdd8',
						'label' => 'Absorb fee',
						'name' => 'ticket_fee',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 1,
					),
					array (
						'key' => 'field_56dc5c7fcfdd9',
						'label' => 'Tickets on sale from',
						'name' => 'ticket_start',
						'type' => 'text',
						'instructions' => 'In format (YYYY-MM-DD hh:mm). Leave empty for "when event published"',
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
						'key' => 'field_56dc5d46cfdda',
						'label' => 'Ticket sale ends',
						'name' => 'ticket_end',
						'type' => 'text',
						'instructions' => 'In format (YYYY-MM-DD hh:mm). Leave empty for "one hour before event start"',
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
						'key' => 'field_56dc5d71cfddb',
						'label' => 'Maximum purchase',
						'name' => 'ticket_max_purchase',
						'type' => 'number',
						'instructions' => 'Maximum number per order (blank for unlimited)',
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
						'min' => '',
						'max' => '',
						'step' => '',
						'readonly' => 0,
						'disabled' => 0,
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

function eventbrite_tickets() {
	return Eventbrite_Tickets::$instance;
}
