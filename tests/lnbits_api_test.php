<?php

require_once(dirname(__DIR__) . '/includes/init.php');

use LNBitsPlugin\LNBitsAPI;


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