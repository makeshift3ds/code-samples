<?php
// include the cart class
include("Cart.class.php");

// a new session is required before creating the cart object
session_start();

// create a cart object
$Cart = new Cart('cart');

// here I use a trick that I learned about having two submit buttons in a form
// it is possible and the one clicked will submit it's value
// I am checking to see if it was the wishlist that was clicked
// If it was then I set the current cart to wishlist so that the actions will occur on it
if((isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'Add To Wishlist') || (isset($_REQUEST['cart']) && $_REQUEST['cart'] == 'wishlist')) $Cart->setCart('wishlist');

// handle the processes 
if(isset($_REQUEST['process'])) {
	switch($_REQUEST['process']) {
		case 'add' :	// add an item
			$Cart->addItem($_REQUEST['productData']);
			break;
		case 'update' : // update an existing item
			$Cart->updateItem($_REQUEST['index'],$_REQUEST['productData']);
			break;
		case 'remove' :	// delete an item
			$Cart->removeItem($_REQUEST['index']);
			break;
		default :
			break;
	}
}

// DEMO Products table. This should be pulled from a database.
$products = array(
			array('id'=>1,'title'=>'Product 1','img'=>'images/1.jpg','colors'=>array('Red','White','Blue')),
			array('id'=>2,'title'=>'Product 2','img'=>'images/2.jpg','colors'=>array('Orange','Yellow')),
			array('id'=>3,'title'=>'Product 3','img'=>'images/3.jpg','colors'=>array('Black','White','Brown')),
			array('id'=>4,'title'=>'Product 4','img'=>'images/4.jpg','colors'=>array('Pink','Purple','Tan'))
		     );

// Disregard any html in this demo page - I never claimed to be a front end developer :)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head lang="en">
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<html>
<head>
<title>DevKen Shopping Cart Demo</title>
<style type='text/css'>
	body {background-color:#CCC;line-height:30px;font-size:12px;font-family:"Trebuchet MS", Arial, Helvetica, sans-serif}
	h1 {text-decoration:underline;}
	h2 {color:#FFF;}
	#main {width:1020px;min-height:400px;position:relative;margin:0 auto;}
	#left {width:690px;float:left;padding:5px;background-color:#68ad42;position:relative;}
	#right {width:300px;float:right;padding:5px;background-color:#68ad42;}
	#comments {width:450px;float:left;}
	#features {width:200px;float:right;background-color:#387019;font-size:11px;line-height:15px;padding:5px;}
	#features ul, #products ul, .headers, .item {list-style-type:none;margin:0;padding:0;}
	#features li {margin:0;padding:5px;background-color:#9ec18b;margin-top:2px;}
	#products {width:690px;background-color:#387019;clear:both;}
	#products ul {list-style-type:none;margin:0;padding:0;}
	#products li {width:164px;float:left;margin:2px;background-color:#9ec18b;padding:2px;}
	.qty {width:20px;}
	.headers li {width:60px;float:left;background-color:#387019;font-weight:bold;text-align:center;color:#FFF;margin-right:1px;}
	.item li {width:60px;height:30px;float:left;background-color:#9ec18b;margin-right:1px;text-align:center;}
	.btn {margin:2px;width:auto;cursor:pointer;height:20px;padding:0 10px;display:block;float:left;border:1px solid #000;background-color:#06C;color:#FFF;font-weight:bold;text-decoration:none;font-size:10px;line-height:22px;}
	.btn:hover {background-color:#006;}
	.empty {border:1px solid #9ec18b; font-style:italic;text-align:center;font-size:10px;clear:all;}
	.total, .unique {width:150px;float:left;}
</style>
</head>
<body>
    <div id='main'>
        <div id='left'>
            <div id='comments'>
                <h1>DevKen Shopping Cart Class</h1>
                <p>
                    I found that many shopping cart components do more than they should. When I go to the store I just put things into my cart - it does not go beyond that. 
                    It does not calculate my costs or tell me how much everything weighs. There are different systems for those things. So in my mind I felt that a Cart 
                    class should do only that - keep track of my items and leave the calculations up to more specific classes. My PHP cart class will allow you to keep 
                    track of items that your customer wants you to remember. I use it for shopping carts, wishlists, download lists, bookmarks, history, and more.  
                </p>
                <p>
                    <strong>Disclaimer</strong> Do not put prices in an input field and save them to the cart. It is easy for someone to change the input field and submit the form. It is recommended that you get them directly from the database.
                </p>
            </div>
            <div id='features'>
                <h2>Features</h2>
                <ul>
                    <li><strong>Multiple Shopping Carts</strong> - this class lets you create as many shopping carts as you need, and run them concurrently.</li>
                    <li><strong>Multiple Attributes</strong> - keep track of all of the item variables such as size and color.</li>
                    <li><strong>Item Recognition</strong> - identical items are grouped automatically. Small differences (colors,weights,etc) are recognized as a seperate item</li>
                    <li><strong>Fast Deployment</strong> - as a stand alone component it is easy to integrate into projects.</li>
                    <li><strong>Easy to learn</strong> - the Cart class is commented, documented and comes with this working demo.</li>
                    <li><strong>AJAX Ready</strong> - I use this class as part of an AJAX shopping cart suite. It is perfectly suited.</li>
                </ul>
            </div>
            <div id='products'>
                <ul>
                <?php
                        // print out all of our demo products with a form that submits to this page.
                        foreach($products as $product) {
                            echo "<li>"
                                ."<h3><img src='{$product['img']}'>{$product['title']}</h3>"
                                ."<form method='post' action='?'>"
                                    ."<input type='hidden' name='process' value='add' />" // the process value defines what should be done when it is submitted and is handled in this form
                                    ."<input type='hidden' name='productData[id]' value='{$product['id']}' />" // I use an array called productData to keep all the information about a product
                                    ."<strong>Color</strong>"
                                    ."<select name='productData[color]'>";
                            foreach($product['colors'] as $color) {
                                echo "<option value='{$color}'>{$color}</option>";
                            }
                                    
                            echo 	"</select><br />"
                                    ."<strong>Qty</strong> "
                                    ."<input type='text' name='productData[qty]' class='qty'  value='1' /><br />"	// here I use a form for qty - you can omit this and the cart will assume qty=1
                                    ."<input type='submit' name='submit' value='Add To Cart' class='btn' />"	// here i use the multiple submit btn trick - you don't have to do this. I just thought it was cool
                                    ."<input type='submit' name='submit' value='Add To Wishlist' class='btn' />"
                                ."</form>"
                                ."</li>";
                        }
                    ?>
                </ul>
                <br clear='all' />
            </div>
            <div id='example'>
                <pre>
                
                </pre>
            </div>
        </div>
        <div id='right'>
            <h2>Cart</h2>
            <ul class="headers">
                <li style='width:25px;'>ID</li>
                <li>Color</li>
                <li>Qty</li>
                <li style='width:150px;'>Actions</li>
            </ul>
            <?php
                // get the cart array from the Cart object
                $Cart->setCart('cart');	// you might wonder why the setCart - why not just pass the cart name in get cart. A few lines down I call more methods on the cart, and would have to pass the names there as well. Rather keep it simple and explicit.
                $cart = $Cart->getCart();
                
                if($Cart->isEmpty()) {
                    echo "<div class='empty'>Empty</div>";	
                }
                
                // print them all out - the index is used to identify items when they are deleted.
                foreach($cart as $index=>$item) {
                    echo "<ul class='item'>"
                        ."<li style='width:25px;'>{$item['id']}</li>"
                        ."<li>{$item['color']}</li>"
                        ."<li><form method='post' action='?' name='item_{$index}'>"
                        ."<input type='hidden' name='process' value='update' />"
                        ."<input type='hidden' name='index' value='{$index}' />"
                        ."<input type='text' class='qty' name='productData[qty]' value='{$item['qty']}' /></form>"
                        ."</li>"	// here I use a form (POST) to update cart items
                        ."<li style='width:150px;'><a href='#' class='btn' onClick='document.item_{$index}.submit();'>Update</a><a href='?process=remove&index={$index}' class='btn'>Remove</a></li>"	// here I use a link (GET) - you can use either one
                        ."</ul>";
                }
                
                echo '<div class="total"><strong>Total Items =</strong> '.$Cart->itemCount().'</div>';
                echo '<div class="unique"><strong>Unique Items = </strong>'.$Cart->uniqueItemCount().'</div>';
                
            ?>
            <h2>Wishlist</h2>
            <ul class="headers">
                <li style='width:25px;'>ID</li>
                <li>Color</li>
                <li>Qty</li>
                <li style='width:150px;'>Actions</li>
            </ul>
            <?php
                // get the wishlist from the Cart object
                $Cart->setCart('wishlist');	
                $cart = $Cart->getCart();
                
                if($Cart->isEmpty()) {
                    echo "<div class='empty'>Empty</div>";	
                }
                
                // print them all out - the index is used to identify items when they are deleted.
                foreach($cart as $index=>$item) {
                    echo "<ul class='item'>"
                        ."<li style='width:25px;'>{$item['id']}</li>"
                        ."<li>{$item['color']}</li>"
                        ."<li><form method='post' action='?' name='item_{$index}'>"
                        ."<input type='hidden' name='cart' value='wishlist' />"
                        ."<input type='hidden' name='process' value='update' />"
                        ."<input type='hidden' name='index' value='{$index}' />"
                        ."<input type='text' class='qty' name='productData[qty]' value='{$item['qty']}' /></form>"
                        ."</li>"	// here I use a form (POST) to update wishlist items
                        ."<li style='width:150px;'><a href='#' class='btn' onClick='document.item_{$index}.submit();'>Update</a><a href='?process=remove&index={$index}&cart=wishlist' class='btn'>Remove</a></li>"	// here I use a link (GET) - you can use either one
                        ."</ul>";
                }
                
                echo '<div class="total"><strong>Total Items =</strong> '.$Cart->itemCount().'</div>';
                echo '<div class="unique"><strong>Unique Items = </strong>'.$Cart->uniqueItemCount().'</div>';
                
            ?>
        </div>
    <br clear='all' />
    </div>
</body>
</html>
