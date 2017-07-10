<?php

global $asp_payment_success, $asp_error;
if ($asp_payment_success) {

    echo "<div class='asp-thank-you-page-wrap'>";

    if (!empty($content)) {
        echo $content;
    }

    $output = "<div class='asp-thank-you-page-msg-wrap' style='background: #dff0d8; border: 1px solid #C9DEC1; margin: 10px 0px; padding: 15px;'>";
    $output .= '<p class="asp-thank-you-page-msg1">' . __("Thank you for your payment.", "stripe-payments") . '</p>';
    $output .= '<p class="asp-thank-you-page-msg2">' . __("Here's what you purchased: ", "stripe-payments") . '</p>';
    $output .= '<div class="asp-thank-you-page-product-name">' . __("Product Name: ", "stripe-payments") . $post_data['item_name'] . '</div>';
    $output .= '<div class="asp-thank-you-page-qty">' . __("Quantity: ", "stripe-payments") . $post_data['item_quantity'] . '</div>';
    $output .= '<div class="asp-thank-you-page-qty">' . __("Amount: ", "stripe-payments") . $post_data['item_price'] . ' ' . $post_data['currency_code'] . '</div>';
    $output .= '<div class="asp-thank-you-page-txn-id">' . __("Transaction ID: ", "stripe-payments") . $post_data['txn_id'] . '</div>';

    if (!empty($item_url)) {
        $output .= "<div class='asp-thank-you-page-download-link'>";
        $output .= __("Please ", "stripe-payments") . "<a href='" . $item_url . "'>" . __("click here", "stripe-payments") . "</a>" . __(" to download.", "stripe-payments");
        $output .= "</div>";
    }
    $output .= "</div>"; //end of .asp-thank-you-page-msg-wrap

    echo apply_filters('asp_stripe_payments_checkout_page_result', $output, $post_data); //Filter that allows you to modify the output data on the checkout result page

    echo "</div>"; //end of .asp-thank-you-page-wrap
} else {
    echo __("System was not able to complete the payment.", "stripe-payments") . $asp_error;
}
