<?php

class ASP_Admin_Order_Meta_Boxes {
	public function __construct() {
		add_action( 'add_meta_boxes_stripe_order', array( $this, 'add_meta_boxes' ), 0 );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'asp_order_events_meta_box',
			__( 'Order Events', 'stripe-payments' ),
			array( $this, 'display_order_events_meta_box' ),
			'stripe_order',
			'side',
			'default'
		);
	}

	public function display_order_events_meta_box( $post ) {
		$order_events = get_post_meta( $post->ID, 'asp_order_events', true );
		if ( ! empty( $order_events ) && is_array( $order_events ) ) {
			$i = 0;
			foreach ( $order_events as $event ) {
				$i++;
				if ( 1 === $i && empty( $event['comment'] ) ) {
					$event['comment'] = __( 'Order created.', 'stripe-payments' );
				}
				if ( 'paid' === $event['status'] && empty( $event['comment'] ) ) {
					$event['comment'] = __( 'Payment completed.', 'stripe-payments' );
				}
				echo sprintf(
					'<div class="asp-order-event-cont%s">
					<div class="asp-order-event-header">
					<span class="asp-order-event-status">%s</span>
					<span class="asp-order-event-date" title="%s">%s</span>
					</div>
					<div class="asp-order-event-comment">%s</div>
					</div>',
					' os-' . $event['status'],
					ASPOrder::get_status_str( $event['status'] ),
					gmdate( 'Y-m-d H:i:s', $event['date'] ),
					gmdate( 'M d H:i', $event['date'] ),
					$event['comment']
				);
				if ( count( $order_events ) !== $i ) {
					echo '<hr>';
				}
			}
		} else {
			echo __( 'No data available.', 'stripe-payments' );
		}
	}
}

new ASP_Admin_Order_Meta_Boxes();
