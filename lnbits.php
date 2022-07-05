<?php

/*
Plugin Name: LNbits - Bitcoin Onchain and Lightning Payment Gateway
Plugin URI: https://github.com/lnbits/woocommerce-lnbits-payment-gateway
Description: Accept Bitcoin on-chain and on lightning with your own LNbits server
Version: 0.0.1
Author: BlackCoffee
Author URI: https://github.com/blackcoffeexbt
*/


add_action('plugins_loaded', 'lnbits_satspay_server_init');

define('LNBITS_SATSPAY_SERVER_PAYMENT_PAGE_SLUG', 'lnbits_satspay_server_payment');


require_once(__DIR__ . '/includes/init.php');

use LNbitsSatsPayPlugin\Utils;
use LNbitsSatsPayPlugin\API;



function woocommerce_lnbits_satspayserver_activate() {
    if (!current_user_can('activate_plugins')) return;

    global $wpdb;

    if ( null === $wpdb->get_row( "SELECT post_name FROM {$wpdb->prefix}posts WHERE post_name = '".LNBITS_SATSPAY_SERVER_PAYMENT_PAGE_SLUG."'", 'ARRAY_A' ) ) {
        $page = array(
          'post_title'  => __( 'Lightning Payment' ),
          'post_name' => LNBITS_SATSPAY_SERVER_PAYMENT_PAGE_SLUG,
          'post_status' => 'publish',
          'post_author' => wp_get_current_user()->ID,
          'post_type'   => 'page',
          'post_content' => render_template('payment_page.php', array())
        );

        // insert the post into the database
        wp_insert_post( $page );
    }
}

register_activation_hook(__FILE__, 'woocommerce_lnbits_satspayserver_activate');


// Helper to render templates under ./templates.
function render_template($tpl_name, $params) {
    return wc_get_template_html($tpl_name, $params, '', plugin_dir_path(__FILE__).'templates/');
}


// Generate lnbits_payment page, using ./templates/lnbits_payment.php
function lnbits_satspay_server_payment_shortcode() {
    $check_payment_url = trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_lnbits';

    if (isset($_REQUEST['order_id'])) {
        $order_id = absint($_REQUEST['order_id']);
        $order = wc_get_order($order_id);
        $invoice = $order->get_meta("lnbits_satspay_server_invoice");
        $success_url = $order->get_checkout_order_received_url();
    } else {
        // Likely when editting page with this shortcode, use dummy order.
        $order_id = 1;
        $invoice = "lnbc0000";
        $success_url = "/dummy-success";
    }

    $template_params = array(
        "invoice" => $invoice,
        "check_payment_url" => $check_payment_url,
        'order_id' => $order_id,
        'success_url' => $success_url
    );

    return render_template('payment_shortcode.php', $template_params);
}



// This is the entry point of the plugin, where everything is registered/hooked up into WordPress.
function lnbits_satspay_server_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    // Register shortcode for rendering Lightning invoice (QR code)
    add_shortcode('lnbits_satspay_server_payment_shortcode', 'lnbits_satspay_server_payment_shortcode');

    // Set the cURL timeout to 15 seconds. When requesting a lightning invoice
    // over Tor, a short timeout can result in failures.
    add_filter('http_request_args', 'lnbits_satspay_server_http_request_args', 100, 1);
    function lnbits_satspay_server_http_request_args($r) //called on line 237
    {
        $r['timeout'] = 15;
        return $r;
    }

    add_action('http_api_curl', 'lnbits_satspay_server_http_api_curl', 100, 1);
    function lnbits_satspay_server_http_api_curl($handle) //called on line 1315
    {
        curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 15 );
        curl_setopt( $handle, CURLOPT_TIMEOUT, 15 );
    }

    // Register the gateway, essentially a controller that handles all requests.
    function add_lnbits_satspay_server_gateway($methods) {
        $methods[] = 'WC_Gateway_LNbits_Satspay_Server';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_lnbits_satspay_server_gateway');


    // Defined here, because it needs to be defined after WC_Payment_Gateway is already loaded.
    class WC_Gateway_LNbits_Satspay_Server extends WC_Payment_Gateway {
        public function __construct() {
            global $woocommerce;

            $this->id = 'lnbits';
            $this->icon = plugin_dir_url(__FILE__).'assets/lightning.png';
            $this->has_fields = false;
            $this->method_title = 'LNbits';
            $this->method_description = 'Take payments in Bitcoin Onchain and with Lightning, without fees, using LNbits.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $url = $this->get_option('lnbits_satspay_server_url');
            $api_key = $this->get_option('lnbits_satspay_server_api_key');
            $this->api = new API($url, $api_key);

            add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_'.$this->id, array($this, 'check_payment'));
        }

        /**
         * Render admin options/settings.
         */
        public function admin_options() {
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
        public function init_form_fields() {
            // echo("init_form_fields");
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable LNbits payment', 'woocommerce'),
                    'label' => __('Enable Bitcoin payments via LNbits', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The payment method title which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('Pay with Bitcoin with LNbits', 'woocommerce'),
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('The payment method description which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('You can use any Bitcoin wallet to pay. Powered by LNbits.'),
                ),
                'lnbits_satspay_server_url' => array(
                    'title' => __('LNbits URL', 'woocommerce'),
                    'description' => __('The URL where your LNbits server is running.', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'https://legend.lnbits.com',
                ),
                'lnbits_satspay_server_api_key' => array(
                    'title' => __('LNbits Invoice/Read Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Available from your LNbits\' wallet\'s API info sidebar.', 'woocommerce'),
                    'default' => '',
                ),
                'lnbits_satspay_wallet_id' => array(
                    'title' => __('LNbits Wallet ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Available from your LNbits\' wallet\'s API info sidebar.', 'woocommerce'),
                    'default' => '',
                ),
                'lnbits_satspay_watch_only_wallet_id' => array(
                    'title' => __('Watch Only Extension Wallet ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Available from your LNbits\' Watch Only Extension.', 'woocommerce'),
                    'default' => '',
                ),
            );
        }


        /**
         * ? Output for thank you page.
         */
        public function thankyou() {
            if ($description = $this->get_description()) {
                echo esc_html(wpautop(wptexturize($description)));
            }
        }


        /**
         * Called from checkout page, when "Place order" hit, through AJAX.
         *
         * Call LNbits API to create an invoice, and store the invoice in the order metadata.
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // This will be stored in the Lightning invoice (ie. can be used to match orders in LNbits)
            $memo = "WordPress Order=".$order->get_id()." Total=".$order->get_total().get_woocommerce_currency();

            $amount = Utils::convert_to_satoshis($order->get_total(), get_woocommerce_currency());

            // Call LNbits server to create invoice
            // Expected 201
            // {
            //  "checking_id": "e0dfcde5ff9708d71e5947c86b9d46cc230fd0f16e0a5f03b00ac97f0b23e684",
            //  "lnurl_response": null,
            //  "payment_hash": "e0dfcde5ff9708d71e5947c86b9d46cc230fd0f16e0a5f03b00ac97f0b23e684",
            //  "payment_request": "..."
            // }
            $r = $this->api->createInvoice($amount, $memo);

            if ($r['status'] == 201) {
                $resp = $r['response'];
                $order->add_meta_data('lnbits_satspay_server_invoice', $resp['payment_request'], true);
                $order->add_meta_data('lnbits_satspay_server_payment_hash', $resp['payment_hash'], true);
                $order->save();

                // TODO: configurable payment page slug
                $redirect_url = add_query_arg(array("order_id" => $order->get_id()), get_permalink( get_page_by_path( LNBITS_SATSPAY_SERVER_PAYMENT_PAGE_SLUG ) ));

                return array(
                    "result" => "success",
                    "redirect" => $redirect_url
                );
            } else {
                error_log("LNbits API failure. Status=".$r['status']);
                error_log($r['response']);
                return array(
                    "result" => "failure",
                    "messages" => array("Failed to create LNbits invoice.")
                );
            }
        }


        /**
         * Called by lnbits_payment page (with QR code), through ajax.
         *
         * Checks whether given invoice was paid, using LNbits API,
         * and updates order metadata in the database.
         */
        public function check_payment() {
            $order = wc_get_order($_REQUEST['order_id']);
            $payment_hash = $order->get_meta('lnbits_satspay_server_payment_hash');
            $r = $this->api->checkInvoicePaid($payment_hash);

            if ($r['status'] == 200) {
                if ($r['response']['paid'] == true) {
                    $order->add_order_note('Payment is settled and has been credited to your LNbits account. Purchased goods/services can be securely delivered to the customer.');
                    $order->payment_complete();
                    $order->save();
                    error_log("PAID");
                    echo(json_encode(array(
                        'result' => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                        'paid' => true
                    )));
                } else {
                    echo(json_encode(array(
                        'result' => 'success',
                        'paid' => false
                    )));
                }
            } else {
                echo(json_encode(array(
                    'result' => 'failure',
                    'paid' => false,
                    'messages' => array('Request to LNbits failed.')
                )));

            }
            die();
        }
    }
}
