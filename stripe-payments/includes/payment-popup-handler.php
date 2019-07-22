<?php
class AcceptStripePaymentsPP
{
    protected $AcceptStripePayments;
    function __construct()
    {
        $action = filter_input(INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING);
        if ($action === 'show_pp') {
            $this->AcceptStripePayments = AcceptStripePayments::get_instance();
            $this->showpp();
        }
    }

    function showpp()
    {
        $product_id = filter_input(INPUT_GET, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $item = new AcceptStripePayments_Item($product_id);

        if ($item->get_last_error()) {
            echo $item->get_last_error();
            exit;
        }

        ASPMain::load_stripe_lib();
        $key = $this->AcceptStripePayments->is_live ? $this->AcceptStripePayments->APISecKey : $this->AcceptStripePayments->APISecKeyTest;
        \Stripe\Stripe::setApiKey($key);

        $a = array();
        $a['page_title'] = 'Blah';
        $a['plugin_url'] = WP_ASP_PLUGIN_URL;
        $a['stripe_key'] = $this->AcceptStripePayments->APIPubKeyTest;
        $intent = \Stripe\PaymentIntent::create([
            'amount' => $item->get_price(true),
            'currency' => $item->get_currency(),
        ]);
        $a['client_secret'] = $intent->client_secret;

        ob_start();
        require_once(WP_ASP_PLUGIN_PATH . 'public/views/templates/default/payment-popup.php');
        $tpl = ob_get_clean();
        echo $tpl;
        exit;
    }
}

new AcceptStripePaymentsPP();
