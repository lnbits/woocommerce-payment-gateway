<?php


function get_woocommerce_currency()
{
	return 'USD';
}

class MockOrder {
	public function get_id() {
		return 23;
	}

	public function get_total() {
		return 12;
	}
}


class SampleTest extends WP_UnitTestCase {


	public function test_order_memo_formating() {
		$order = new MockOrder();
		$memo = "WordPress Order=".$order->get_id()." Total=".$order->get_total().get_woocommerce_currency();
		$this->assertEquals('WordPress Order=23 Total=12USD', $memo);
	}
}
