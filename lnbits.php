<?php

/*
Plugin Name: LNbits - Bitcoin Lightning and Onchain Payment Gateway
Plugin URI: https://github.com/lnbits/woocommerce-payment-gateway
Description: Accept Bitcoin on your WooCommerce store both on-chain and with Lightning with LNbits
Version: 1.0.4
Author: LNbits
Author URI: https://github.com/lnbits
*/

add_action('plugins_loaded', 'lnbits_satspay_server_init');

require_once(__DIR__ . '/includes/init.php');

use LNbitsSatsPayPlugin\Utils;
use LNbitsSatsPayPlugin\API;

// This is the entry point of the plugin, where everything is registered/hooked up into WordPress.
function lnbits_satspay_server_init()
{
    if ( ! class_exists('WC_Payment_Gateway')) {
        return;
    };

    // Set the cURL timeout to 15 seconds. When requesting a lightning invoice
    // If using a lnbits instance that is funded by a lnbits instance on Tor, a short timeout can result in failures.
    add_filter('http_request_args', 'lnbits_satspay_server_http_request_args', 100, 1);
    function lnbits_satspay_server_http_request_args($r) //called on line 237
    {
        $r['timeout'] = 15;
        return $r;
    }

    add_action('http_api_curl', 'lnbits_satspay_server_http_api_curl', 100, 1);
    function lnbits_satspay_server_http_api_curl($handle) //called on line 1315
    {
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($handle, CURLOPT_TIMEOUT, 15);
    }

    // Register the gateway, essentially a controller that handles all requests.
    function add_lnbits_satspay_server_gateway($methods)
    {
        $methods[] = 'WC_Gateway_LNbits_Satspay_Server';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_lnbits_satspay_server_gateway');

    /**
     * Handle a payment notification from SatsPay.
     *
     * The callback URL contains a random, per-order secret.  Its presence alone
     * is not sufficient to complete an order: the charge is always checked with
     * LNbits using the merchant API key before the order state is changed.
     */
    function lnbits_satspay_server_add_payment_complete_callback($data) {
        $order_id = absint($data['id']);
        $callback_token = (string) $data['token'];
        $order = wc_get_order($order_id);

        if (empty($order)) {
            return new WP_Error('lnbits_order_not_found', 'Order not found', array('status' => 404));
        }

        $expected_token = (string) $order->get_meta('lnbits_satspay_server_callback_token');
        if (empty($expected_token) || !hash_equals($expected_token, $callback_token)) {
            return new WP_Error('lnbits_invalid_callback_token', 'Invalid callback token', array('status' => 403));
        }

        // Check if order is already paid
        if ($order->is_paid()) {
            return new WP_REST_Response(array('status' => 'success', 'message' => 'Order already paid'), 200);
        }

        $payment_id = $order->get_meta('lnbits_satspay_server_payment_id');
        if (empty($payment_id)) {
            return new WP_Error('lnbits_charge_not_found', 'Charge not found for order', array('status' => 400));
        }

        $gateway = new WC_Gateway_LNbits_Satspay_Server();
        $result = $gateway->is_charge_paid($payment_id);
        if ($result !== true) {
            return new WP_Error('lnbits_payment_not_settled', 'Payment has not been confirmed by LNbits', array('status' => 409));
        }

        // Add order note
        $order->add_order_note('Payment is settled and has been credited to your LNbits account. Purchased goods/services can be securely delivered to the customer.');
        
        // Set paid date
        $order->set_date_paid(current_time('mysql', true));
        
        // Set transaction ID
        $payment_hash = $order->get_meta('lnbits_satspay_server_payment_hash');
        $order->set_transaction_id($payment_hash);
        
        // Mark payment complete - this will automatically set status based on product type
        $order->payment_complete($payment_hash);
        
        // Save all changes
        $order->save();

        // Clear cart
        if (function_exists('WC')) {
            WC()->cart->empty_cart();
        }

        return new WP_REST_Response(array('status' => 'success'), 200);
    }

    add_action("rest_api_init", function () {
        register_rest_route("lnbits_satspay_server/v1", "/payment_complete/(?P<id>\d+)/(?P<token>[A-Za-z0-9]+)", array(
            "methods" => "POST",
            "callback" => "lnbits_satspay_server_add_payment_complete_callback",
            "permission_callback" => "__return_true"
        ));
    });

    add_action("template_redirect", "btcpayment_callback_template_redirect");
    function btcpayment_callback_template_redirect()
    {
        if (get_query_var("btcpayment_callback"))
        {
            // Check for the request method
            if ($_SERVER["REQUEST_METHOD"] === "POST")
            {
                $order_id = get_query_var("order_id"); // Retrieve the parameter value from the URL
                $postBody = file_get_contents("php://input");
                $postData = json_decode($postBody, true); // Assuming it's JSON data
                // Use $postData for further processing
                if ($postData !== null)
                {
                    // Get charge id from payload
                    $request_charge = $postData["id"];

                    // Get charge id from order
                    $order = wc_get_order($order_id);
                    $order_charge_id = $order->get_meta("lnbits_satspay_server_payment_id");

                    // Check if order charge id equals charge id in request
                    if ($request_charge == $order_charge_id)
                    {
                        // If not already marked as paid.
                        if ($order && !$order->is_paid())
                        {
                            // Get an instance of WC_Gateway_LNbits_Satspay_Server, call check_payment method
                            $lnbits_gateway = new WC_Gateway_LNbits_Satspay_Server();
                            $r = $lnbits_gateway->api->checkChargePaid($order_charge_id);
                            if ($r["status"] == 200)
                            {
                                if ($r["response"]["paid"] == true && !$order->is_paid())
                                {
                                    $order->add_order_note("Payment completed (webhook).");
                                    $order->payment_complete();
                                    $order->save();
                                }
                            }
                            die();
                        }
                    }
                    else
                    {
                        header("HTTP/1.1 400 Bad Request");
                        echo "400 Bad Request";
                        die();
                    }
                }
                else
                {
                    header("HTTP/1.1 400 Bad Request");
                    echo "400 Bad Request";
                    die();
                }
            }
            else
            {
                header("HTTP/1.1 405 Method Not Allowed");
                echo "Method Not Allowed";
                die();
            }
        }
    }

    // Defined here, because it needs to be defined after WC_Payment_Gateway is already loaded.
    class WC_Gateway_LNbits_Satspay_Server extends WC_Payment_Gateway {
        // Declare the property at the class level
        protected $api;

        public function __construct()
        {
            global $woocommerce;

            $this->id                 = 'lnbits';
            $this->icon               = plugin_dir_url(__FILE__) . 'assets/lightning.png';
            $this->has_fields         = false;
            $this->method_title       = 'LNbits';
            $this->method_description = 'Take payments with Bitcoin using Lightning and Onchain, without 3rd party fees using LNbits.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');

            $url       = $this->get_option('lnbits_satspay_server_url');
            $api_key   = $this->get_option('lnbits_satspay_server_api_key');
            $wallet_id   = $this->get_option('lnbits_satspay_wallet_id');
            $watch_only_wallet_id   = $this->get_option('lnbits_satspay_watch_only_wallet_id');
            $this->api = new API($url, $api_key, $wallet_id, $watch_only_wallet_id);

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'check_payment'));
        }

        /**
         * Render admin options/settings.
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('LNbits', 'woothemes'); ?></h3>
            <p><?php _e('Accept Bitcoin instantly through the LNbits Satspay Server extension.', 'woothemes'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php

        }

        /**
         * Generate config form fields, shown in admin->WooCommerce->Settings.
         */
        public function init_form_fields()
        {
            // echo("init_form_fields");
            $this->form_fields = array(
                'enabled'                             => array(
                    'title'       => __('Enable LNbits payment', 'woocommerce'),
                    'label'       => __('Enable Bitcoin payments via LNbits', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                ),
                'title'                               => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('The payment method title which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default'     => __('Pay with Bitcoin with LNbits', 'woocommerce'),
                ),
                'description'                         => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('The payment method description which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default'     => __('You can use any Bitcoin wallet to pay. Powered by LNbits.'),
                ),
                'lnbits_satspay_server_url'           => array(
                    'title'       => __('LNbits URL', 'woocommerce'),
                    'description' => __('The URL where your LNbits server is running.', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => 'https://legend.lnbits.com',
                ),
                'lnbits_satspay_wallet_id'            => array(
                    'title'       => __('LNbits Wallet ID', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Available from your LNbits\' wallet\'s API info sidebar.', 'woocommerce'),
                    'default'     => '',
                ),
                'lnbits_satspay_server_api_key'       => array(
                    'title'       => __('LNbits Invoice/Read Key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Available from your LNbits\' wallet\'s API info sidebar.', 'woocommerce'),
                    'default'     => '',
                ),
                'lnbits_satspay_watch_only_wallet_id' => array(
                    'title'       => __('Watch Only Extension Wallet ID', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Available from your LNbits\' "Watch Only" extension.', 'woocommerce'),
                    'default'     => '',
                ),
                'lnbits_satspay_expiry_time' => array(
                    'title'       => __('Invoice expiry time in minutes', 'woocommerce'),
                    'type'        => 'number',
                    'description' => __('Set an invoice expiry time in minutes.', 'woocommerce'),
                    'default'     => '1440',
                ),
            );
        }


        /**
         * ? Output for thank you page.
         */
        public function thankyou()
        {
            if ($description = $this->get_description()) {
                echo esc_html(wpautop(wptexturize($description)));
            }
        }


        /**
         * Called from checkout page, when "Place order" hit, through AJAX.
         *
         * Call LNbits API to create an invoice, and store the invoice in the order metadata.
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $memo = get_bloginfo('name') . " Order #" . $order->get_id();
            $invoice_expiry_time = $this->get_option('lnbits_satspay_expiry_time');
            $callback_token = wp_generate_password(64, false, false);
            $callback_url = rest_url(sprintf(
                'lnbits_satspay_server/v1/payment_complete/%d/%s',
                $order->get_id(),
                $callback_token
            ));

            // Call LNbits server to create invoice
            $r = $this->api->createCharge(
                $order->get_total(),
                $memo,
                $order->get_id(),
                $callback_url,
                $invoice_expiry_time
            );
            
            if ($r['status'] === 200) {
                $resp = $r['response'];
                $order->update_meta_data('lnbits_satspay_server_payment_id', $resp['id']);
                $order->update_meta_data('lnbits_satspay_server_invoice', $resp['payment_request']);
                $order->update_meta_data('lnbits_satspay_server_payment_hash', $resp['payment_hash']);
                $order->update_meta_data('lnbits_satspay_server_callback_token', $callback_token);
                $order->save();
                
                // Set order status to pending payment
                $order->update_status('pending', __('Awaiting LNbits payment', 'lnbits'));
                
                // Reduce stock levels
                wc_reduce_stock_levels($order_id);
                
                // Remove cart
                WC()->cart->empty_cart();
                
                $url = sprintf("%s/satspay/%s",
                    rtrim($this->get_option('lnbits_satspay_server_url'), '/'),
                    $resp['id']
                );
                
                return array(
                    "result" => "success",
                    "redirect" => $url
                );
            }
            
            return array(
                "result" => "failure",
                "messages" => array("Failed to create LNbits invoice.")
            );
        }

        public function check_payment($order_id) {
            $order = wc_get_order($order_id);
            
            // If order is already paid, no need to check
            if ($order->is_paid()) {
                return;
            }

            // Check if this is a return from LNbits payment
            if (isset($_GET['key']) && $_GET['key'] === $order->get_order_key()) {
                $lnbits_payment_id = $order->get_meta('lnbits_satspay_server_payment_id');
                $r = $this->api->checkChargePaid($lnbits_payment_id);
                
                if ($r['status'] == 200 && $r['response']['paid'] == true) {
                    $payment_hash = $order->get_meta('lnbits_satspay_server_payment_hash');
                    $order->payment_complete($payment_hash);
                    $order->add_order_note(__('Payment verified via LNbits', 'lnbits'));
                    $order->save();
                }
            }
        }

        /**
         * Check a SatsPay charge using merchant credentials.
         */
        public function is_charge_paid($payment_id) {
            $response = $this->api->checkChargePaid($payment_id);

            return $response['status'] === 200
                && !empty($response['response']['paid']);
        }
    }

    // Add this near the top of your plugin initialization, before the blocks registration
    add_action('init', function() {
        load_plugin_textdomain('lnbits', false, dirname(plugin_basename(__FILE__)) . '/languages');
    });

    // Then your blocks registration
    add_action('woocommerce_blocks_loaded', function() {
        require_once(__DIR__ . '/blocks/index.php');
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function($payment_method_registry) {
                $payment_method_registry->register(new LNbitsSatsPayPlugin\Blocks\LNbitsPaymentMethod);
            }
        );
    });

}
