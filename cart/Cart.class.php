<?php
	/**
	*	Ken's Cart Class v2.1;
	**/
	
	/**
	*	Purpose: Cart who's items can have any number of attributes
	*
	* 	Update 5/25/2011 - Added the ability to create multiple carts ie (cart/wishlist)
	*
	*	$cart = new Cart;		// create a new cart object
	*	$item = array('id'=>132,'qty'=>'1','color'=>'blue','size'=>'large');	// create a new item array - ID is required
	*	$cart->addItem($item); 	// new item will be added (cart has 1 item)
	*	$item['qty'] += 2;		// now the item qty equals 3;
	*	$cart->addItem($item);	// since qty=3 it updates the qty rather than add 3 more items
	*	$item['color']  = 'white';	// now the color is different
	*	$cart->addItem($item); 	// since this does not match any other cart item a new item will be added;
	*	$cart->removeItem(0);	// remove the first item from the cart
	*	$cart->updateItem(0,array('color'=>'yellow')); now the color of the item will be yellow
	*	$cart->clearCart();		// empty the cart
	*	$cart->dumpCart();		// dump the cart for test viewing
	*	$cart_contents = $cart->getCart();		// get the cart for processing
	* 	foreach($cart_contents as $cart_item)
	*		echo $cart_item['id']; // output all the cart id's
	**/

	class Cart {
		private $cart;
		private $cart_name;

		/**
		* __construct : initiate cart object
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return null
		**/
		public function __construct($cart_name=null) {
			$this->setCart($cart_name);
		}

		/**
		* setCart : set the current cart object
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return null
		**/
		public function setCart($cart_name) {
			$this->cart_name = !is_null($cart_name) ? $cart_name : 'cart';
			$this->cart = isset($_SESSION[$this->cart_name]) ? $_SESSION[$this->cart_name] : array();
		}

		/**
		* updateItem : update an existing item - add item will do this by default, but it can be called here as well
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return null
		**/
		public function updateItem($index,$attribs) {
			foreach($attribs as $k => $v) {	
				if(!isset($v)) {					
					unset($this->cart[$index][$k]);	
				} else {
					$this->cart[$index][$k] = $v;
				}
			}
			$this->updateCart();
		}


		/**
		* clearCart : empty the cart
		*
		* @access public
		* @return null
		**/
		public function clearCart() {
			$this->cart = array();
			$this->updateCart();
		}


		/**
		* addItem : add an item to the shopping cart. The only required information is 'id'. Qty, if empty is assumed to be 1.
		*
		* @param string    $attribs	associative array with item information (data['id'=>1,'qty'=>3,'color'=>'red'])
		* @access public
		* @return null
		**/
		public function addItem($attribs) {
			$flag = false;	
			$n = 0;	
			if(!isset($attribs['id'])) return;
			if(!isset($attribs['qty']) || $attribs['qty'] < 1) $attribs['qty'] = 1;

			foreach($this->cart as $k) {
				if($k['id'] == $attribs['id'] && count($k) == count($attribs)) {
					$matched = array_intersect_assoc($attribs,$k);
					if(isset($matched['qty'])) {
						if(count($matched) == count($k)) {
							$flag = true;
							break;
						}
					} else {
						if(count($matched)+1 == count($k)) {
							$flag = true;
							break;
						}
					}
				}
				$n++;
			}

			if($flag) {	
				if($attribs['qty']>=2) {
					$this->cart[$n]['qty'] = $attribs['qty']; 
				} else {
					$this->cart[$n]['qty'] += $attribs['qty'];
				}
				$this->updateCart();
				return;
			}
			
			array_push($this->cart,$attribs);
			$this->updateCart();
			return;	
		}

		/**
		* removeItem : remove an item from the cart - it uses the index of the array as the identifier
		*
		* @param string    $index	array index value for the target item
		* @access public
		* @return null
		**/
		public function removeItem($index) {
			unset($this->cart[$index]);	
			$this->cart = array_values($this->cart);
			$this->updateCart();
			return;
		}

		/**
		* updateCart : a system method that will commit cart changes. This can be used publicly if you decide to manipulate the cart externally
		*
		* @access public
		* @return null
		**/
		public function updateCart() {
			$_SESSION[$this->cart_name] = $this->cart;
		}

		/**
		* getCart : return the cart array - alternatively you can call the variable directly $mycart = $Cart->cart;
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return array
		**/
		public function getCart() {
			return $this->cart;	
		}

		/**
		* dump : expel the cart for inspection
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return null
		**/
		public function dump() {
			echo '<pre>';
			var_dump($this->cart);
			echo '</pre>';
		}

		/**
		* isEmpty : check to see if the cart is empty
		*
		* @access public
		* @return boolean
		**/
		public function isEmpty() {
			return count($this->cart) ? 0 : 1;
		}

		/**
		* itemCount : return the number of items in the cart (the total of all qty's)
		*
		* @access public
		* @return integer
		**/
		public function itemCount() {
			$qty=0;
			foreach($this->cart as $k) $qty += $k['qty'];
			return $qty;
		}

		/**
		* uniqueItemCount : the number of unique items in the cart (the number of array entries)
		*
		* @access public
		* @return integer
		**/
		public function uniqueItemCount() {
			return count($this->cart);
		}
		
		/**
		* getSerialized : return the cart as a serialized array - useful for saving the state of a shopping cart
		*
		* @param string    $cart_name	cart identifier (cart,wishlist,briefcase,etc)
		* @access public
		* @return null
		**/
		public function getSerialized() {
			return serialize($this->cart);
		}
	}
?>