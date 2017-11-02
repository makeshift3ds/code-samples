<?php
// include the cart class
include("Cart_Calculator.class.php");

// set up a test array of products. 
$cart[0]['title'] = 'Product 1'; 	
$cart[0]['price'] = isset($_REQUEST['cart'][0]['price']) ? $_REQUEST['cart'][0]['price'] : '12.99';
$cart[0]['qty'] = isset($_REQUEST['cart'][0]['qty']) ? $_REQUEST['cart'][0]['qty'] : '3';
$cart[0]['discount'] = isset($_REQUEST['cart'][0]['discount']) ? $_REQUEST['cart'][0]['discount'] : '0';
$cart[0]['tax'] = isset($_REQUEST['cart'][0]['tax']) ? $_REQUEST['cart'][0]['tax'] : '0';
$cart[0]['handling'] = isset($_REQUEST['cart'][0]['handling']) ? $_REQUEST['cart'][0]['handling'] : '0';
$cart[1]['title'] = 'Product 2';
$cart[1]['price'] = isset($_REQUEST['cart'][1]['price']) ? $_REQUEST['cart'][1]['price'] : '25.50';
$cart[1]['qty'] = isset($_REQUEST['cart'][1]['qty']) ? $_REQUEST['cart'][1]['qty'] : '3';
$cart[1]['discount'] = isset($_REQUEST['cart'][1]['discount']) ? $_REQUEST['cart'][1]['discount'] : '0';
$cart[1]['tax'] = isset($_REQUEST['cart'][1]['tax']) ? $_REQUEST['cart'][1]['tax'] : '0';
$cart[1]['handling'] = isset($_REQUEST['cart'][1]['handling']) ? $_REQUEST['cart'][1]['handling'] : '0';
$cart[2]['title'] = 'Product 3';
$cart[2]['price'] = isset($_REQUEST['cart'][2]['price']) ? $_REQUEST['cart'][2]['price'] : '100.00';
$cart[2]['qty'] = isset($_REQUEST['cart'][2]['qty']) ? $_REQUEST['cart'][2]['qty'] : '1';
$cart[2]['discount'] = isset($_REQUEST['cart'][2]['discount']) ? $_REQUEST['cart'][2]['discount'] : '0';
$cart[2]['tax'] = isset($_REQUEST['cart'][2]['tax']) ? $_REQUEST['cart'][2]['tax'] : '0';
$cart[2]['handling'] = isset($_REQUEST['cart'][2]['handling']) ? $_REQUEST['cart'][2]['handling'] : '0';

// create a Cart_Calculator object
$Cart_Calculator = new Cart_Calculator(array(
							'shipping' => isset($_REQUEST['shipping']) ? $_REQUEST['shipping'] : '10.00',
							'tax' => isset($_REQUEST['tax']) ? $_REQUEST['tax'] : 6.5,
							'discount' => isset($_REQUEST['discount']) ? $_REQUEST['discount'] : 0,
							'handling' => isset($_REQUEST['handling']) ? $_REQUEST['handling'] : 0,
							'prefix' => '$'
						));
						
// calculate the cart
$results = $Cart_Calculator->getTotals($cart);

// Disregard any html in this demo page - I never claimed to be a front end developer :)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head lang="en">
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<html>
<head>
<title>DevKen Cart Calculator Class Demo</title>
<style type='text/css'>
	body {background-color:#CCC;line-height:30px;font-size:12px;font-family:"Trebuchet MS", Arial, Helvetica, sans-serif}
	h1 {text-decoration:underline;}
	h2 {color:#FFF;margin:0;padding:0;}
	#main {width:1120px;min-height:400px;position:relative;margin:0 auto;}
	#left {width:640px;float:left;padding:5px;background-color:#68ad42;position:relative;}
	#right {width:450px;float:right;padding:5px;background-color:#68ad42;}
	#comments {width:400px;float:left;}
	#features {width:200px;float:right;background-color:#387019;font-size:11px;line-height:15px;padding:5px;}
	#features ul, #products ul, .headers, .item {list-style-type:none;margin:0;padding:0;}
	#features li {margin:0;padding:5px;background-color:#9ec18b;margin-top:2px;}
	#products {width:640px;background-color:#387019;clear:both;}
	#products ul {list-style-type:none;margin:0;padding:10px;}
	#products li {width:164px;float:left;margin:2px;background-color:#9ec18b;padding:2px;}
	.qty {width:20px;}
	.headers li {width:60px;float:left;background-color:#387019;font-weight:bold;text-align:center;color:#FFF;margin-right:1px;}
	.item li {width:60px;height:30px;float:left;background-color:#9ec18b;margin-right:1px;text-align:center;}
	.empty {border:1px solid #9ec18b; font-style:italic;text-align:center;font-size:10px;clear:all;}
	.total, .unique {width:150px;float:left;}
	.cart_headers, .cart_item, .totals {margin:0;padding:0;list-style-type:none;clear:both;}
	.cart_headers li, .cart_item li {margin:0;padding:2px;background-color:#CCC;width:50px;list-style-type:none;float:left;font-size:10px;height:20px;}
	.cart_headers li {font-weight:bold;border:1px solid #DDD;text-align:center;}
	.cart_item li {background-color:#DDD;border:1px solid #CCC;}
	.totals li {width:440px;background-color:#DDD;margin: 2px 0 2px 0;padding:4px;font-size:10px;}
	.small {font-size:10px;}
	.total_value {color:#588e10;font-size:14px;font-weight:bold;}
	.red {background-color:#d57979;}
	.modifier {float:left;margin:0;padding:0;}
</style>
</head>
<body>
    <div id='main'>
        <div id='left'>
            <div id='comments'>
                <h1>DevKen Cart Calculator Class</h1>
                <p>
                    The Cart Calculator class does all the calculations that you need on a cart. It works on the individual item level and the entire cart level. It recognizes qty, handling, discount, tax and price for each item. If tax or a discount is applied to an item
                    it does not get included in the cart level tax or discount calculations. This is useful for discounted items that can not be included in additional promotions and taxing people twice is just plain wrong. On the cart level you can add in the
                    cost of shipping also. Discount can be either a percent or an integer so Cart Calculator will recognize 20% and 20 differently.
                </p>
                <p>
                	Handling and Discounts applied at the item level are on a per item basis. So a $5 discount on 3 items would result in a total discount of $15.
                </p>
                <p>
                	All of the information below would generally be created by a shopping cart. Here you can edit it manually to review the functionality.
                </p>
            </div>
            <div id='features'>
                <h2>Features</h2>
                <ul>
                    <li><strong>Attribute Pass Through</strong> - attributes that are in the cart but not related to calculations are passed through.</li>
                    <li><strong>Item Level Modifiers</strong> - apply single item discounts, tax or handling.</li>
                    <li><strong>Fast Deployment</strong> - as a stand alone component it is easy to integrate into projects.</li>
                    <li><strong>Easy to learn</strong> - the Cart Calculator class is commented, documented, unit tested and comes with this working demo.</li>
                    <li><strong>AJAX Ready</strong> - I use this class as part of an AJAX shopping cart suite. It is perfectly suited.</li>
                    <li><strong>Superb Flavor</strong> - pairs well with the <a href='http://www.binpress.com/app/devken-cart-class/442'>DevKen Cart Class</a> and an aromatic red wine.</li>
                </ul>
            </div>
            <div id='products'>
            	<h2>Products</h2>
            	<form method='post' action='?'>
            	<ul class='headers'>
            		<li style='width:150px;'>Title</li>
            		<li style='width:40px;'>Qty</li>
            		<li style='width:95px;'>Price</li>
            		<li style='width:95px;'>Tax</li>
            		<li style='width:95px;'>Handling</li>
            		<li style='width:95px;'>Discount</li>
            	</ul>
            	<ul class='product'>
            		<li style='width:150px;font-weight:bold;'>Product 1</li>
            		<li style='width:40px;'><input type='text' name="cart[0][qty]" value="<?php echo $results['cart'][0]['qty']; ?>" style='width:36px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[0][price]" value="<?php echo $results['cart'][0]['price'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[0][tax]" value="<?php echo $results['cart'][0]['tax'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[0][handling]" value="<?php echo $results['cart'][0]['handling'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[0][discount]" value="<?php echo $results['cart'][0]['discount'];?>" style='width:91px;' /></li>
            	</ul>
            	<ul class='product'>
            		<li style='width:150px;font-weight:bold;'>Product 2</li>
            		<li style='width:40px;'><input type='text' name="cart[1][qty]" value="<?php echo $results['cart'][1]['qty']; ?>" style='width:36px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[1][price]" value="<?php echo $results['cart'][1]['price'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[1][tax]" value="<?php echo $results['cart'][1]['tax'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[1][handling]" value="<?php echo $results['cart'][1]['handling'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[1][discount]" value="<?php echo $results['cart'][1]['discount'];?>" style='width:91px;' /></li>
            	</ul>
            	<ul class='product'>
            		<li style='width:150px;font-weight:bold;'>Product 3</li>
            		<li style='width:40px;'><input type='text' name="cart[2][qty]" value="<?php echo $results['cart'][2]['qty']; ?>" style='width:36px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[2][price]" value="<?php echo $results['cart'][2]['price'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[2][tax]" value="<?php echo $results['cart'][2]['tax'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[2][handling]" value="<?php echo $results['cart'][2]['handling'];?>" style='width:91px;' /></li>
            		<li style='width:95px;'><input type='text' name="cart[2][discount]" value="<?php echo $results['cart'][2]['discount'];?>" style='width:91px;' /></li>
            	</ul>
            	<h2>Cart Modifiers</h2>
            	<ul class='modifier'>
            		<li style='width:88px;font-weight:bold;'>Shipping</li>
            		<li style='width:auto;'><input type='text' name="shipping" value="<?php echo $Cart_Calculator->getShipping(); ?>" style='width:46px;' /></li>
            		<li style='width:88px;font-weight:bold;'>Tax</li>
            		<li style='width:auto'><input type='text' name="tax" value="<?php echo $Cart_Calculator->getTax(); ?>" style='width:46px;' /></li>
            		<li style='width:88px;font-weight:bold;'>Handling</li>
            		<li style='width:auto;'><input type='text' name="handling" value="<?php echo $Cart_Calculator->getHandling(); ?>" style='width:46px;' /></li>
            		<li style='width:88px;font-weight:bold;'>Discount</li>
            		<li style='width:auto;'><input type='text' name="discount" value="<?php echo $Cart_Calculator->getDiscount(); ?>" style='width:46px;' /></li>
            	</ul><br clear='all' />
            	<input type='submit' class='btn' value='Update Calculations' />
            	</form>
                <br clear='all' />
            </div>
        </div>
        <div id='right'>
            <h2>Cart</h2>
            <ul class='cart_headers'>
            	<li>Title</li>
            	<li>Qty</li>
            	<li>Price</li>
            	<li>Subtotal</li>
            	<li>Handling</li>
            	<li>Tax</li>
            	<li>Discounted</li>
            	<li>Total</li>
            </ul>
            <?php
            
            	foreach($results['cart'] as $index=>$item) {
		   echo "<ul class='cart_item'>"
			."<li>{$item['title']}</li>"
			."<li>{$item['qty']}</li>"
			."<li>".$Cart_Calculator->moneyFormat($item['price'])."</li>"
			."<li>".$Cart_Calculator->moneyFormat($item['subtotal'])."</li>"
			."<li>".$Cart_Calculator->moneyFormat($item['handling_charge'])."</li>"
			."<li>".$Cart_Calculator->moneyFormat($item['taxed'])."</li>"
			."<li style='background-color:#e8a1a3;'>".$Cart_Calculator->moneyFormat($item['discounted'])."</li>"
			."<li>".$Cart_Calculator->moneyFormat($item['total'])."</li>"
		    ."</ul><br clear='all' />";
            	}
            	
            	echo "<h2>Totals</h2>"
            		."<ul class='totals'>"
            		."<li><strong>Subtotal : </strong> before taxes, shipping, handling and cart discount<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['subtotal'])."</span></li>"
            		."<li><strong>Handling : </strong>includes handling for each item and cart handling<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['handling'])."</span></li>"
            		."<li><strong>Shipping : </strong>this is passed through unchanged and is used in the total calculation<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['shipping'])."</span></li>"
            		."<li><strong>Taxable : </strong>subtotal minus handling, shipping, discounts and items that have already been taxed.<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['taxable'])."</span>"
            		."<li><strong>Tax : </strong>includes taxes for each item and cart wide taxes<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['tax'])."</span></li>"
            		."<li><strong>Discountable : </strong>individually discounted items are not discounted again<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['discountable'])."</span>";
            		
            		if($_REQUEST['discount'] > $results['totals']['discountable']) {
            			echo '<br /><span class="red">The discount provided exceeded the discountable amount.</span>';
            		}
            		
            	echo "</li>"
            		."<li><strong>Discounted : </strong>includes individual item discounts and cart wide discounts<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['discounted'])."</span></li>"
            		."<li><strong>Total : </strong>after taxes, shipping, handling and discounts<br /><span class='total_value'>".$Cart_Calculator->moneyFormat($results['totals']['total'])."</li>"
            		."</ul>";
            ?>            
        </div>
    <br clear='all' />
    </div>
</body>
</html>
