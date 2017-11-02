<?php
/**
* DevKen Cart Calculator Class
*
* This class will calculate a shopping cart. It allows for individual taxes, handling fees and discounts. 
* Tax, shipping, discount and handling can be set for the entire cart as well. Items that are individually 
* taxed are not taxed with the cart. Items that are individually discounted are not discounted with the cart.
* Item discounts and handling are on a per item basis.
*
* @author      Kenneth Elliott <kenelliottmc@gmail.com>
* @copyright   Copyright &copy; 2011 Kenneth Elliott <kenelliottmc@gmail.com>
* @category    eCommerce
* @package     Cart_Calculator
* @since       Manataria 1.1.2
*/

class Cart_Calculator {
	protected $total;
	protected $subtotal;
	protected $tax;
	protected $shipping;
	protected $discount;
	protected $handling;
	protected $discounted;
	protected $discountable;

	/**
	* __construct : initiate Cart_Calculator component
	*
	* @param array    $params	set configuration variables - tax, shipping, discount, handling and currency prefix
	* @access public
	* @return null
	**/
	public function __construct($params=null) {
		$this->total = 0;
		$this->subtotal=0;
		$this->tax=isset($params['tax'])?str_replace('%','',$params['tax']):0;
		$this->shipping=isset($params['shipping'])?$params['shipping']:0;
		$this->discount=isset($params['discount'])?$params['discount']:0;
		$this->handling=isset($params['handling'])?$params['handling']:0;
		$this->prefix=isset($params['prefix'])?$params['prefix']:'';
	}
	
	/**
	* setTax : set the tax rate. this rate will be applied to the cart items which are not individually taxed
	*
	* @param integer    $tax	tax rate such as 6.5 or 4
	* @access public
	* @return null
	**/
	public function setTax($tax) { 
		$tax = str_replace('%','',$tax);
		$this->tax = $tax;
	}
	
	/**
	* getTax : get the tax rate
	*
	* @access public
	* @return integer
	**/
	public function getTax() {
		return $this->tax;
	}
	
	/**
	* setShipping : set the shipping cost - this will be added to the subtotal
	*
	* @param integer    $shipping	cost of shipping - should be an integer without any currency symbol such as 12.21 or 5
	* @access public
	* @return null
	**/
	public function setShipping($shipping) {
		$this->shipping = $shipping;
	}

	/**
	* getShipping : get the shipping cost 
	*
	* @access public
	* @return integer
	**/
	public function getShipping() {
		return $this->shipping;
	}
	
	/**
	* setDiscount : set the amount to be discounted from the total. Items that have already been discounted will not be discounted again.
	*
	* @param mixed    $discount	amount of discount to give can be a percent or integer such as 25% or 2.50
	* @access public
	* @return null
	**/
	public function setDiscount($discount) {
		$this->discount = $discount;
	}
	
	/**
	* getDiscount : get the amount to be discounted from the total.
	*
	* @access public
	* @return integer
	**/
	public function getDiscount() {
		return $this->discount;
	}
	
	/**
	* moneyFormat : round long integers to a money friendly format 1.2575 will be rounded to 1.26 and 1.2525 will be rounded to 1.25.
	*
	* @param integer    $number	number to format
	* @param string    $prefix	prefix to use such as $
	* @access public
	* @return null
	**/
	public function moneyFormat($number,$prefix=null) {
		$prefix = is_null($prefix) ? $this->prefix : $prefix; 
		return  $prefix.sprintf('%0.2f', $number);
	}
			
	/**
	* setPrefix : set the prefix to use with currency such as $
	*
	* @param string    $prefix	currency prefix
	* @access public
	* @return null
	**/
	public function setPrefix($prefix='$') {
		$this->prefix = $prefix;
	}

	/**
	* getPrefix : get the currency identifier
	*
	* @access public
	* @return string
	**/
	public function getPrefix() {
		return $this->prefix;
	}
			
	/**
	* setHandling : set the amount of handling to be added to the cart.
	*
	* @param integer    $handling	amount of handling such as 12.50 or 2.95
	* @access public
	* @return null
	**/
	public function setHandling($handling) {
		$this->handling = $handling;
	}
	
	/**
	* getHandling : get the amount for handling.
	*
	* @access public
	* @return integer
	**/
	public function getHandling() {
		return $this->handling;
	}
	
		
	/**
	* getTotals : calculate the totals in an array. Array must be formatted with a price and qty. Optional variables for cart items are handling, discount and tax. All other variables are passed through unchanged.
	*
	* @param array    $cart	list of items 'price' and 'qty' are required
	* @access public
	* @return array	$result['cart'], $result['totals']
	**/
	public function getTotals($cart) {		
		$data=array(
			'taxable' => '',
			'handling' => '',
			'subtotal' => '',
			'tax' => '');
		$prefix = $this->getPrefix();
		$this->setPrefix('');
				
		foreach(array_keys($cart) as $key) {
			if(!isset($cart[$key]['price'])) trigger_error('price is not set in Shopping Cart. Unable to Calculate Costs. - Cart_Calculator.class.php -> getTotals',E_USER_ERROR);
			if(!isset($cart[$key]['qty']) || $cart[$key]['qty'] == 0) continue;
			
			$cart[$key]['discounted'] = 0;
			$cart[$key]['taxed'] = 0;			
			
			if(isset($cart[$key]['discount']) && $cart[$key]['discount'] > 0 && $cart[$key]['discount'] != '0%') {
				if(strpos($cart[$key]['discount'],'%')) {
					$discount = str_replace('%','',$cart[$key]['discount']);
					$cart[$key]['discounted'] = $cart[$key]['discount']*.01*($cart[$key]['qty']*$cart[$key]['price']);
					$this->discounted += $cart[$key]['discounted'];
				} else {
					if($cart[$key]['discount'] > $cart[$key]['price']) $cart[$key]['discount'] = $cart[$key]['price'];
					$cart[$key]['discounted'] = $cart[$key]['discount']*$cart[$key]['qty'];
					$this->discounted += $cart[$key]['discounted'];
				}
			} else {
				$this->discountable += $cart[$key]['price']*$cart[$key]['qty'];
			}
			
			$cart[$key]['subtotal'] = $cart[$key]['price']*$cart[$key]['qty'];
			
			if(isset($cart[$key]['tax']) && $cart[$key]['tax'] > 0) {
				$cart[$key]['taxed'] = ($cart[$key]['tax']*.01)*$cart[$key]['subtotal'];
				$data['tax'] += $cart[$key]['taxed'];
			} else {
				$data['taxable'] += $cart[$key]['price']*$cart[$key]['qty'];
			}
			
			if(isset($cart[$key]['handling'])) {
				$cart[$key]['handling_charge'] = $cart[$key]['handling']*$cart[$key]['qty'];
				$data['handling'] += $cart[$key]['handling_charge'];
			}
			
			$data['subtotal'] += $cart[$key]['subtotal'];
			$cart[$key]['total'] = ($cart[$key]['price']*$cart[$key]['qty'])-$cart[$key]['discounted']+$cart[$key]['taxed']+($cart[$key]['handling']*$cart[$key]['qty']);
			
			$cart[$key]['handling_charge'] = $this->moneyFormat($cart[$key]['handling_charge']);
			$cart[$key]['subtotal'] = $this->moneyFormat($cart[$key]['subtotal']);
			$cart[$key]['taxed'] = $this->moneyFormat($cart[$key]['taxed']);
			$cart[$key]['discounted'] = $this->moneyFormat($cart[$key]['discounted']);
			$cart[$key]['price'] = $this->moneyFormat($cart[$key]['price']);
			$cart[$key]['total'] = $this->moneyFormat($cart[$key]['total']);
			
		}
		if(!is_null($this->discount)) {
		
			if(strpos($this->discount,'%')) {
				$discount = str_replace('%','',$this->discount);
				$discount_amount = ($discount*.01)*$this->discountable;
				$this->discounted += $discount_amount;
			} else {
				if($this->discount > $this->discountable) $this->discount = ($this->discountable>0) ? $this->discountable : 0;
				$this->discounted += $this->discount;
				$discount_amount = $this->discount;
			}
			//$data['taxable'] = $discount_amount;
		}
		$data['subtotal'] = $this->moneyFormat($data['subtotal']);
		$data['shipping'] = $this->moneyFormat($this->shipping);
		$data['discount'] = $this->discount;
		$data['discounted'] = $this->moneyFormat($this->discounted);
		$data['discountable'] = $this->moneyFormat($this->discountable);
		$data['handling'] = $this->moneyFormat($this->handling+$data['handling']);
		$data['taxable'] = $this->moneyFormat($data['taxable']);
		$data['tax'] = $this->moneyFormat((($this->tax*.01)*$data['taxable'])+$data['tax']);
		$data['total'] = $this->moneyFormat($data['tax']+$data['shipping']+$data['handling']+$data['subtotal']-$this->discounted);
		if($data['total'] < 0) $data['total'] = '0.00';
		
		$this->setPrefix($prefix);
		
		return array('cart'=>$cart,'totals'=>$data);
	}
}