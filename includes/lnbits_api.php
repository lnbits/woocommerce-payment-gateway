<?php
namespace LNBitsPlugin;

// use OpenNode\OpenNode;
// use OpenNode\Merchant;
// use OpenNode\OrderIsNotValid;
// use OpenNode\OrderNotFound;


/**
 * For calling LNBits API
 */
class LNBitsAPI {

    protected $url;
    protected $api_key;

    public function __construct($url, $api_key) {
        $this->url = $url;
        $this->api_key = $api_key;
    }

    public function createInvoice($amount, $memo) {
        $c = new CurlWrapper();
        $data = array(
            "out" => false,
            "amount" => $amount,
            "memo" => $memo,
            "webhook" => "http://webhook.com"
        );
        $headers = array(
            'X-Api-Key' => $this->api_key,
            'Content-Type' => 'application/json'
        );
        return $c->post($this->url.'/api/v1/payments', array(), $data, $headers);
    }

    public function checkInvoicePaid($payment_hash) {
        $c = new CurlWrapper();
        $headers = array(
            'X-Api-Key' => $this->api_key,
            'Content-Type' => 'application/json'
        );
        return $c->get($this->url.'/api/v1/payments/'.$payment_hash, array(), $headers);
    }
}
