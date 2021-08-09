<?php

require_once(dirname(__DIR__) . '/includes/init.php');

use LNBitsPlugin\LNBitsAPI;


// add_action( 'http_api_debug', 'http_call_debug', 10, 5 );
 
function http_call_debug( $response, $type, $class, $args, $url ) {
    // You can change this from error_log() to var_dump() but it can break AJAX requests
    error_log( 'Request URL: ' . var_export( $url, true ) );
    error_log( 'Request Args: ' . var_export( $args, true ) );
    error_log( 'Request Response : ' . var_export( $response, true ) );
}


function create_api() {
	$url = 'https://lnbits.com';
	$api_key = '15a468cc934f4c95978d66a24ac78333';
	return new LNBitsAPI($url, $api_key);
}


class LNBitsAPITest extends WP_UnitTestCase {


	public function test_create_invoice_and_check() {
		$api = create_api();
		$r = $api->createInvoice(10, "Testing invoice");
		$this->assertEquals(201, $r['status']);
		$this->assertArrayHasKey('payment_hash', $r['response']);
		$this->assertArrayHasKey('payment_request', $r['response']);

		$hash = $r['response']['payment_hash'];
		$r = $api->checkInvoicePaid($hash);
		$this->assertEquals(200, $r['status']);
		$this->assertEquals(false, $r['response']['paid']);
	}
}