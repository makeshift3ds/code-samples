<?php
	/** NOTE June, 2017
	 * 
	 * This code was written 5 years ago. I wouldn't let this into production these days, and I apologize
	 * that you had to see it.
	 **/

	/*
	//	Ken's Cart Class v2.1a;
	//
	//	Purpose: Cart who's items can have any number of attributes
	// Added the ability to create multiple carts ie (cart/wishlist)
	//
	//	$cart = new Cart;		// create a new cart object
	//	$item = array('id'=>132,'qty'=>'1','color'=>'blue','size'=>'large');	// create a new item array - ID is required
	//	$cart->AddItem($item); 	// new item will be added (cart has 1 item)
	//	$item['qty'] += 2;		// now the item qty equals 3;
	//	$cart->AddItem($item);	// since qty=3 it updates the qty rather than add 3 more items
	//	$item['color']  = 'white';	// now the color is different
	//	$cart->AddItem($item); 	// since this does not match any other cart item a new item will be added;
	//	$cart->RemoveItem(0);	// remove the first item from the cart
	//	$cart->UpdateItem(0,array('color'=>'yellow')); now the color of the item will be yellow
	//	$cart->ClearCart();		// empty the cart
	//	$cart->DumpCart();		// dump the cart for test viewing
	//	$cart_contents = $cart->GetCart();		// get the cart for processing
	// 	foreach($cart_contents as $cart_item)
	//		echo $cart_item['id']; // output all the cart id's
	// 	now you can have multiple carts just by changing the name
	*/

	class Cart {
		private $cart;
		private $cart_name;

		function __construct($cart_name=null) {
			$this->cart_name = !is_null($cart_name) ? $cart_name : 'cart';
			$this->cart = isset($_SESSION[$this->cart_name]) ? $_SESSION[$this->cart_name] : array();	// new cart or retrieve old cart
		}

		public function setCart($cart_name) {
			$this->cart_name = $cart_name;
		}

		public function updateItem($id,$attribs) {
			foreach($attribs as $k => $v) {		// go through all the new attributes
				if(!isset($v))					// if attribute has empty value
					unset($this->cart[$id][$k]);	// remove it from the item
				else
					$this->cart[$id][$k] = $v;	// otherwise update it
			}
			$this->updateCart(); 	// resave session
		}

		public function clearCart() {
			$this->cart = array();		// empty array
			$this->updateCart();		// resave to session
		}

		public function addItem($attribs) {
			$flag = false;	// matching flag - see if it is an update to an item
			$n = 0;		// counter
			if(!isset($attribs['id'])) return;	// an id is required
			if(!isset($attribs['qty']) || $attribs['qty'] < 1) $attribs['qty'] = 1;	// should supply the qty but if not 1 is default

			foreach($this->cart as $k) {	// go through the entire cart
				if($k['id'] == $attribs['id'] && count($k) == count($attribs)) {	// if they have the same id and the same number of attributes
					$matched = array_intersect_assoc($attribs,$k);	// get key value pairs that match
					if(isset($matched['qty'])) {	// if qty was the same in both arrays
						if(count($matched) == count($k)) {		// see if it matches the newly submitted item
							$flag = true;	// if they match set the flag
							break;		// break out of the foreach
						} // end if
					} else {	// is it the same item with a different qty?
						if(count($matched)+1 == count($k)) {	// are they the same size (matched does not have qty though so +1)
							$flag = true;	// if they match set the flag
							break;		// break out of the foreach
						}	// end if
					}	// end if/else
				} // end if
				$n++;	// increment cart position
			} // end foreach

			if($flag) {		// if flag is true
				if($attribs['qty']>=2)		// if the qty is more than one
					$this->cart[$n]['qty'] = $attribs['qty']; 	// just set it to the new qty
				else
					$this->cart[$n]['qty'] += $attribs['qty']; 	// add the new qty
				$this->updateCart();	// save the cart to the session
				return; // return nothing
			}
			

			array_push($this->cart,$attribs);	// add a new item to the cart
			$this->updateCart();	// save the cart to the session
			return;				// at the end but why not return
		}

		public function removeItem($arrayIndex) {
			unset($this->cart[$arrayIndex]);		// remove the item
			$this->cart = array_values($this->cart);	// update the indexes
			$this->updateCart();				// save the cart to the session
			return;
		}

		public function updateCart() {
			$_SESSION[$this->cart_name] = $this->cart;	// resave the cart to the session
		}

		public function getCart() {
			return $this->cart;		// return the cart for outside processing
		}

		public function dumpCart() {
			var_dump($this->cart);		// dump the cart for testing
		}

		public function isEmpty() {
			return count($this->cart) ? 0 : 1;	// whether the cart is empty or not
		}

		public function itemCount() {
			$qty=0;
			foreach($this->cart as $k) $qty += $k['qty'];
			return $qty;
		}

		public function uniqueItemCount() {
			return count($this->cart);
		}
		
		public function getSerialized() {
			return serialize($this->cart);
		}
	}
?>