<?php
class AcceptStripePayments_Process_IPN_NG
{
    function __construct()
    {
        $process_ipn = filter_input(INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT);
        if ($process_ipn) {
            $this->AcceptStripePayments = AcceptStripePayments::get_instance();
            $this->process_ipn();
        }
    }
    function process_ipn()
    {
        $pi = filter_input(INPUT_POST, 'asp_payment_intent', FILTER_SANITIZE_STRING);
        $is_live = filter_input(INPUT_POST, 'asp_is_live', FILTER_VALIDATE_BOOLEAN);

        ASPMain::load_stripe_lib();
        $key = $is_live ? $this->AcceptStripePayments->APISecKey : $this->AcceptStripePayments->APISecKeyTest;
        \Stripe\Stripe::setApiKey($key);

        $intent = \Stripe\PaymentIntent::retrieve($pi);
        $charges = $intent->charges->data;

        echo '<pre>';
        var_dump($_POST);
        echo '</pre>';

        echo '<pre>';
        var_dump($charges);
        echo '</pre>';

        $prod_id = filter_input(INPUT_POST, 'asp_product_id', FILTER_SANITIZE_NUMBER_INT);
        $item         = new AcceptStripePayments_Item($prod_id);

        wp_die();
    }
}

new AcceptStripePayments_Process_IPN_NG();
