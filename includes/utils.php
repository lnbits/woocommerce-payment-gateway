<?php
namespace LNBitsPlugin;


class Utils {
    public static function convert_to_satoshis($amount, $currency) {
        if(strtolower($currency) !== 'btc') {
            error_log($amount . " " . $currency);
            $c    = new CurlWrapper();
            $resp = $c->get('https://blockchain.info/tobtc', array(
                'currency' => $currency,
                'value'    => $amount
            ), array());

            if ($resp['status'] != 200) {
                throw new \Exception('Blockchain.info request for currency conversion failed. Got status ' . $resp['status']);
            }

            return (int) round($resp['response'] * 100000000);
        }
        else {
            return intval($amount * 100000000);
        }
    }
}

class CurlWrapper {

    private function request($method, $url, $params, $headers, $data) {
        $url = add_query_arg($params, $url);
        $r = wp_remote_request($url, array(
            'method' => $method,
            'headers' => $headers,
            'body' => $data ? json_encode($data) : ''
        ));

        if (is_wp_error($r)) {
            error_log('WP_Error: '.$r->get_error_message());
            return array(
                'status' => 500,
                'response' => $r->get_error_message()
            );
        }

        return array(
            'status' => $r['response']['code'],
            'response' => json_decode($r['body'], true)
        );
    }

    public function get($url, $params, $headers) {
        return $this->request('GET', $url, $params, $headers, null);
    }

    public function post($url, $params, $data, $headers) {
        return $this->request('POST', $url, $params, $headers, $data);
    }
}
