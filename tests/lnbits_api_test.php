<?php

require_once(dirname(__DIR__) . '/includes/init.php');

use LNbitsSatsPayPlugin\API;

// add_action( 'http_api_debug', 'http_call_debug', 10, 5 );

function http_call_debug( $response, $type, $class, $args, $url ) {
    // You can change this from error_log() to var_dump() but it can break AJAX requests
    error_log( 'Request URL: ' . var_export( $url, true ) );
    error_log( 'Request Args: ' . var_export( $args, true ) );
    error_log( 'Request Response : ' . var_export( $response, true ) );
}


function create_api() {
	$url = 'https://legend.lnbits.com';
	$api_key = '45a26f02b4374f4ea1f0e7312ac51ffb';
	$wallet_id = '60454c8899974f4f928609cd28f7c33e';
	$watch_only_wallet_id = 'igRdhScnfoognuaRu9YTei';
	return new API($url, $api_key, $wallet_id, $watch_only_wallet_id);
}


class LNbitsAPITest extends WP_UnitTestCase {


	public function test_create_invoice_and_check() {
		$api = create_api();
		$r = $api->createCharge(10, "Testing invoice", 1000, 60);
		$this->assertEquals(200, $r['status']);
		$this->assertArrayHasKey('payment_hash', $r['response']);
		$this->assertArrayHasKey('payment_request', $r['response']);
		$this->assertArrayHasKey('id', $r['response']);

		$payment_id = $r['response']['payment_id'];
		$r = $api->checkChargePaid($payment_id);
		$this->assertEquals(200, $r['status']);
		$this->assertEquals(false, $r['response']['paid']);
	}
}
