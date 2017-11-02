<?php
require_once('simpletest/autorun.php');
require_once 'Cart_Calculator.class.php';

class TestCart_Calculator extends UnitTestCase {
    	protected $Cart_Calculator = null;
	protected $cart = array(
				0=>array('price'=>12.99,'qty'=>3,'discount'=>'25%','tax'=>'3.5'),
				1=>array('price'=>45.12,'qty'=>2,'discount'=>'20.50','handling'=>'10.00'),
				2=>array('price'=>97.66,'qty'=>6,),
			     );
			     
	public function setUp() {
		$this->Cart_Calculator = new Cart_Calculator(
											array(
												'shipping' => 10.00,
												'tax' => 6.5,
												'discount' => '10%',
												'handling' => 19.95,
												'prefix' => '$'
											)
										);
		$this->Cart_Calculator->setShipping(10.00);
		
	}

	public function tearDown() {
		unset($this->Cart_Calculator);
	}
	
	public function testVerifyCartCalculations() {
		$result = $this->Cart_Calculator->getTotals($this->cart);
		$this->assertIsA($result['cart'],'array');
		$this->assertEqual($result['cart'][0]['discounted'],9.74);
		$this->assertEqual($result['cart'][1]['discounted'],41.00);
		$this->assertEqual($result['cart'][1]['taxed'],0.00);
		$this->assertEqual($result['cart'][0]['subtotal'],38.97);
		$this->assertEqual($result['cart'][1]['subtotal'],90.24);
		$this->assertEqual($result['cart'][1]['handling_charge'],20.00);
	}
	
	public function testVerifyTotals() {
		$result = $this->Cart_Calculator->getTotals($this->cart);
		$this->assertIsA($result['totals'],'array');
		$this->assertEqual($result['totals']['subtotal'],715.17);
		$this->assertEqual($result['totals']['tax'],45.32);
		$this->assertEqual($result['totals']['handling'],39.95);
		$this->assertEqual($result['totals']['shipping'],10.00);
		$this->assertEqual($result['totals']['discountable'],585.96);
		$this->assertEqual($result['totals']['discounted'],109.34);
		$this->assertEqual($result['totals']['discount'],'10%');
		$this->assertEqual($result['totals']['total'],701.10);
	}
	
	public function testMoneyFormat() {
		$result = $this->Cart_Calculator->moneyFormat(123.5675,'$');
		$this->assertEqual($result,'$123.57');
		$result = $this->Cart_Calculator->moneyFormat(123.5625,'$');
		$this->assertEqual($result,'$123.56');
		$result = $this->Cart_Calculator->moneyFormat(123.5675,'£');
		$this->assertEqual($result,'£123.57');
	}
	
}