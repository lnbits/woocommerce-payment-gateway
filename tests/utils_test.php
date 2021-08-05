<?php

require_once(dirname(__DIR__) . '/includes/init.php');

use LNBitsPlugin\CurlWrapper;
use LNBitsPlugin\Utils;



class UtilsTest extends WP_UnitTestCase {


	public function test_currency_conversion() {
		$sats = Utils::convert_to_satoshis(10, 'CZK');
		$this->assertIsInt($sats);
		$this->assertGreaterThan(1200, $sats);
		$this->assertLessThan(2000, $sats);
	}
}
