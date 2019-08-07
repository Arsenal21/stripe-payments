<?php
class AcceptStripePayments_Process_IPN_NG {

	var $aspRedirectURL = '';
	function __construct() {
		$process_ipn = filter_input( INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT );
		if ( $process_ipn ) {
			$this->AcceptStripePayments = AcceptStripePayments::get_instance();
			add_action( 'init', array( $this, 'process_ipn' ) );
		}
	}

	function ipn_completed( $errMsg = '' ) {
		if ( ! empty( $errMsg ) ) {
			$aspData = array( 'error_msg' => $errMsg );
			ASP_Debug_Logger::log( $errMsg, false ); //Log the error

			$msg_before_process = __( 'Error occured before user interacted with payment popup. This might be caused by JavaScript errors on page.', 'stripe-payments' );
			$msg_after_process  = __( 'Error occured after user interacted with popup.', 'stripe-payments' );

			if ( isset( $_POST['clickProcessed'] ) ) {
				$additional_msg = $msg_after_process;
			} else {
				$additional_msg = $msg_before_process;
			}

			ASP_Debug_Logger::log( $additional_msg, false );

			$this->sess->set_transient_data( 'asp_data', $aspData );

			//send email to notify site admin (if option enabled)
			$opt = get_option( 'AcceptStripePayments-settings' );
			if ( isset( $opt['send_email_on_error'] ) && $opt['send_email_on_error'] ) {
				$to      = $opt['send_email_on_error_to'];
				$from    = get_option( 'admin_email' );
				$headers = 'From: ' . $from . "\r\n";
				$subj    = __( 'Stripe Payments Error', 'stripe-payments' );
				$body    = __( 'Following error occured during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
				$body   .= $errMsg . "\r\n\r\n";
				$body   .= $additional_msg . "\r\n";
				$body   .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
				foreach ( $_POST as $key => $value ) {
					$value = is_array( $value ) ? json_encode( $value ) : $value;
					$body .= $key . ': ' . $value . "\r\n";
				}
				wp_mail( $to, $subj, $body, $headers );
			}
		} else {
			ASP_Debug_Logger::log( 'Payment has been processed successfully.' );
		}
		ASP_Debug_Logger::log( sprintf( 'Redirecting to results page "%s"', $this->aspRedirectURL ) . "\r\n" );
		wp_redirect( $this->aspRedirectURL );
		exit;
	}

	function process_ipn() {
		ASP_Debug_Logger::log( 'Payment processing started.' );

		$post_thankyou_page_url = isset( $_POST['thankyou_page_url'] ) ? sanitize_text_field( $_POST['thankyou_page_url'] ) : '';

		$this->aspRedirectURL = empty( $post_thankyou_page_url ) ? $this->AcceptStripePayments->get_setting( 'checkout_url' ) : base64_decode( $post_thankyou_page_url );

		$prod_id = filter_input( INPUT_POST, 'asp_product_id', FILTER_SANITIZE_NUMBER_INT );
		$item    = new AcceptStripePayments_Item( $prod_id );

		$err = $item->get_last_error();

		if ( $err ) {
			$this->ipn_completed( $err );
		}

		if ( $item->get_redir_url() ) {
			$this->aspRedirectURL = $item->get_redir_url();
		}

		$pi = filter_input( INPUT_POST, 'asp_payment_intent', FILTER_SANITIZE_STRING );

		$completed_order = get_posts(
			array(
				'post_type'  => 'stripe_order',
				'meta_key'   => 'pi_id',
				'meta_value' => $pi,
			)
		);

		wp_reset_postdata();

		if ( ! empty( $completed_order ) ) {
			//already processed - let's redirect to results page
			$this->ipn_completed();
			exit;
		}

		$is_live = filter_input( INPUT_POST, 'asp_is_live', FILTER_VALIDATE_BOOLEAN );

		ASPMain::load_stripe_lib();
		$key = $is_live ? $this->AcceptStripePayments->APISecKey : $this->AcceptStripePayments->APISecKeyTest;
		\Stripe\Stripe::setApiKey( $key );

		$intent = \Stripe\PaymentIntent::retrieve( $pi );
		$charge = $intent->charges;

		//        echo '<pre>';
		//        var_dump($_POST);
		//        echo '</pre>';

		//        echo '<pre>';
		//        var_dump($charge);
		//        echo '</pre>';

		$sess = ASP_Session::get_instance();

		$data                       = array();
		$data['paid_amount']        = AcceptStripePayments::from_cents( $charge->data[0]->amount, $charge->data[0]->currency );
		$data['currency_code']      = strtoupper( $charge->data[0]->currency );
		$data['item_quantity']      = $item->get_quantity();
		$data['charge']             = $charge->data[0];
		$data['stripeToken']        = '';
		$data['stripeTokenType']    = 'card';
		$data['is_live']            = $is_live;
		$data['charge_description'] = $item->get_description();
		$data['item_name']          = $item->get_name();
		$price                      = $item->get_price();
		if ( empty( $price ) ) {
			$price = $charge->data[0]->amount;
			$price = AcceptStripePayments::from_cents( $price, $item->get_currency() );
			$item->set_price( $price );
		}
		$data['item_price']      = $price;
		$data['stripeEmail']     = $charge->data[0]->billing_details->email;
		$data['customer_name']   = $charge->data[0]->billing_details->name;
		$purchase_date           = date( 'Y-m-d H:i:s', $charge->data[0]->created );
		$purchase_date           = get_date_from_gmt( $purchase_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );
		$data['purchase_date']   = $purchase_date;
		$data['charge_date']     = $purchase_date;
		$data['charge_date_raw'] = $charge->data[0]->created;
		$data['txn_id']          = $charge->data[0]->id;
		//billing address
		$billing_address = '';
		$bd              = $charge->data[0]->billing_details;

		$billing_address         .= $bd->name ? $charge->data[0]->billing_details->name . "\n" : '';
		$billing_address         .= isset( $bd->address->line1 ) ? $bd->address->line1 . "\n" : '';
		$billing_address         .= isset( $bd->address->line2 ) ? $bd->address->line2 . "\n" : '';
		$billing_address         .= isset( $bd->address->postal_code ) ? $bd->address->postal_code . "\n" : '';
		$billing_address         .= isset( $bd->address->city ) ? $bd->address->city . "\n" : '';
		$billing_address         .= isset( $bd->address->state ) ? $bd->address->state . "\n" : '';
		$billing_address         .= isset( $bd->address->country ) ? $bd->address->country . "\n" : '';
		$data['billing_address']  = $billing_address;
		$data['additional_items'] = array();
		$item_price               = $item->get_price();
		$currency_code            = $item->get_currency();

		$custom_fields = array();
		if ( isset( $_POST['asp_stripeCustomFieldName'] ) ) {
			$custom_fields[] = array(
				'name'  => $_POST['stripeCustomFieldName'],
				'value' => $_POST['stripeCustomField'],
			);
		}

		//compatability with ACF addon
		if ( ! empty( $_POST['asp_stripeCustomFields'] ) ) {
			$_POST['stripeCustomFields'] = $_POST['asp_stripeCustomFields'];
		}
		$custom_fields = apply_filters( 'asp_process_custom_fields', $custom_fields, array( 'product_id' => $prod_id ) );

		if ( ! empty( $custom_fields ) ) {
			$data['custom_fields'] = $custom_fields;
		}

		//check if coupon was used
		if ( isset( $charge->data[0]->metadata['Coupon'] ) ) {
			$coupon_code = strtoupper( $charge->data[0]->metadata['Coupon'] );
			$coupon      = AcceptStripePayments_CouponsAdmin::get_coupon( $coupon_code );
			if ( $coupon['valid'] ) {
				if ( ! AcceptStripePayments_CouponsAdmin::is_coupon_allowed_for_product( $coupon['id'], $prod_id ) ) {
					//coupon not allowed for this product
					unset( $coupon );
				} else {
					if ( $coupon['discountType'] === 'perc' ) {
						$perc            = AcceptStripePayments::is_zero_cents( $currency_code ) ? 0 : 2;
						$discount_amount = round( $item_price * ( $coupon['discount'] / 100 ), $perc );
					} else {
						$discount_amount = $coupon['discount'];
					}
					$coupon['discountAmount'] = $discount_amount;
					$item_price               = $item_price - $discount_amount;
				}
			} else {
				unset( $coupon );
			}
		}
		if ( isset( $coupon ) && $coupon['valid'] ) {
			$data['additional_items'][ sprintf( __( 'Coupon "%s"', 'stripe-payments' ), $coupon['code'] ) ] = floatval( '-' . $coupon['discountAmount'] );
			$data['additional_items'][ __( 'Subtotal', 'stripe-payments' ) ]                                = $item_price;
			$item->set_price( $item_price );
			//increase coupon redeem count
			$curr_redeem_cnt = get_post_meta( $coupon['id'], 'asp_coupon_red_count', true );
			$curr_redeem_cnt++;
			update_post_meta( $coupon['id'], 'asp_coupon_red_count', $curr_redeem_cnt );
		}
		if ( ! empty( $item->get_tax() ) ) {
			$taxStr  = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
			$tax_amt = $item->get_tax_amount( false );
			$data['additional_items'][ ucfirst( $taxStr ) ] = $tax_amt;
			$data['tax_perc']                               = $item->get_tax();
			$data['tax']                                    = $tax_amt;
		}
		if ( ! empty( $item->get_shipping() ) ) {
			$shipStr = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
			$data['additional_items'][ ucfirst( $shipStr ) ] = $item->get_shipping();
			$data['shipping']                                = $item->get_shipping();
		}
		//custom fields
		$custom_fields = $sess->get_transient_data( 'custom_fields' );
		if ( ! empty( $custom_fields ) ) {
			$data['custom_fields'] = $custom_fields;
		}
		$product_details  = __( 'Product Name: ', 'stripe-payments' ) . $data['item_name'] . "\n";
		$product_details .= __( 'Quantity: ', 'stripe-payments' ) . $data['item_quantity'] . "\n";
		$product_details .= __( 'Item Price: ', 'stripe-payments' ) . AcceptStripePayments::formatted_price( $data['item_price'], $data['currency_code'] ) . "\n";
		//check if there are any additional items available like tax and shipping cost
		$product_details        .= AcceptStripePayments::gen_additional_items( $data );
		$product_details        .= '--------------------------------' . "\n";
		$product_details        .= __( 'Total Amount: ', 'stripe-payments' ) . AcceptStripePayments::formatted_price( $data['paid_amount'], $data['currency_code'] ) . "\n";
		$data['product_details'] = nl2br( $product_details );
		//Insert the order data to the custom post
		$dont_create_order = $this->AcceptStripePayments->get_setting( 'dont_create_order' );
		if ( ! $dont_create_order ) {
			$order                 = ASPOrder::get_instance();
			$order_post_id         = $order->insert( $data, $data['charge'] );
			$data['order_post_id'] = $order_post_id;
			update_post_meta( $order_post_id, 'order_data', $data );
			update_post_meta( $order_post_id, 'charge_data', $data['charge'] );
			update_post_meta( $order_post_id, 'trans_id', $charge->data[0]->balance_transaction );
			update_post_meta( $order_post_id, 'pi_id', $pi );
		}
		$sess->set_transient_data( 'asp_data', $data );
		$this->ipn_completed();
	}
}

new AcceptStripePayments_Process_IPN_NG();
