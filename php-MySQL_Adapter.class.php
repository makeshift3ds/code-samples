<?php
/** NOTE: June, 2017
 *
 * This code was written 5 years ago. Which was the last time I really used raw PHP - I've used Laravel since then. 
 * This is a database wrapper that was intended for market, but eloquent came out around the same time
 * and I used that orm instead. Thanks for looking though :)
 **/

/**
 * MySQL Wrapper
 *
 * This class contains a uniform wrapper for communicating with a MySQL database.
 * Now build classes with the same interface for additional DBMS's ;)
 *
 * @author      Kenneth Elliott <ken@devken.com>
 * @copyright   Copyright &copy; 2012 Kenneth Elliott <ken@devken.com>
 * @package     MySQL_Adapter
 *
 *
 * Documenter Rules
 * ;; means ; and newline
 * .; means . and newline
 * @'s need to be double tabbed
 * [R] = required
 * [D:default_value] = default value
 * @params without $ can be whatever the key is
 * **BOLD**
 **/
class MySQL_Adapter {
	public $db;
	public $link_id;
	public $queries;
	public $queryCount;
	public $allow_modifiers = TRUE;
	public $_profile = FALSE;
	private $last_insert_id = 0;
	
	/**
	* Constructor - Set all the default values
	*
	* @param  	string   $host		[R] mysql server hostname
	* @param  	string   $user		[R] mysql server username
	* @param  	string   $db		mysql server db
	* @param  	string   $pass		mysql server password
	* @return 	null
	* @access 	public
	**/
	public function __construct($params) {
		$host = isset($params['hostname']) ? $this->required($params['hostname'],'Hostname is missing from new '.get_class($this).'()') ? $params['hostname'] : $this->error(get_class($this).' construct error','Hostname is missing from new '.get_class($this).'()') : NULL;
		$user = isset($params['username']) ? $this->required($params['username'],'Username is missing from new '.get_class($this).'()') ? $params['username'] : $this->error(get_class($this).' construct error','Username is missing from new '.get_class($this).'()') : NULL;
		$pass = $this->not_null(isset($params['password']) ? $params['password'] : NULL) ? $params['password'] : '';
		$this->db = $this->not_null(isset($params['database']) ? $params['database'] : NULL) ? $params['database'] : '';
		
		$this->connect_db(array('hostname'=>$host, 'username'=>$user, 'password'=>$pass));
		if($this->not_null($this->db))$this->select_db($this->db);
		$this->queries = array();
		$this->queryCount = 0;
		
		if(!function_exists('walk_table_columns')) {
			function walk_table_columns(&$col,$key,$table) {
				$col = $table.".".$col['column_name']." ".$table.'_'.$col['column_name'];
			}
		}
	}

	/**
	* Connect to a database
	*
	* @param  	string   $host		[R] mysql server hostname
	* @param  	string   $user		[R] mysql server username
	* @param  	string   $pass		mysql server password
	* @return 	null
	* @access 	public
	**/
	public function connect_db($params) {
		$host = $this->required(isset($params['hostname']) ? $params['hostname'] : NULL,'Hostname is missing from new '.get_class($this).'->connect_db') ? $params['hostname'] : '';
		$user = $this->required(isset($params['username']) ? $params['username'] : NULL,'Username is missing from new '.get_class($this).'->connect_db') ? $params['username'] : '';
		$pass = $this->not_null(isset($params['password']) ? $params['password'] : NULL) ? $params['password'] : '';
		
		$this->link_id = new mysqli($host,$user,$pass);
		if($this->link_id->connect_error || $this->link_id === FALSE) $this->error(NULL,'Unable to establish MySQLi Connection','This error is normally caused by an invalid password, wrong username or invalid hostname. An invalid port would also cause this error.');
		return;
	}

	/**
	* Select database
	*
	* @param  	string		$db			[R]database name
	* @return 	object		$dbh		database handle
	* @access 	public
	**/
	public function select_db($db){
		$this->link_id->select_db($db);		
		if($this->link_id->connect_error) $this->error(NULL,'Unable to select the database','This error is caused by an invalid database name. Check the name and try again.');
		return $this->link_id;		
	}

	/**
	* perform a query
	*
	* @param  	string		$query				[R] mysql query string
	* @return 	object		$resource_id		mysql resource
	* @access	public
	**/
	public function query($query){
		$host = $this->required(isset($query) ? $query : NULL,get_class($this).'->query was passed an empty query');
		$start = microtime();
		if($this->_profile) $logdata['prestatus'] = $this->res2array($this->link_id->query('show status'));
		if($this->_profile && substr(ltrim($query),0,6) == 'insert') $this->last_insert_id = $this->insert_id();
		$resource = $this->link_id->query($query);
		if($this->_profile) $logdata['poststatus'] = $this->res2array($this->link_id->query('show status'));
		if($this->_profile) $logdata['sql'] = $query;
		if($this->_profile) $logdata['time'] = (microtime()-$start)*1000;
		if($this->_profile) $logdata['debug_backtrace'] = debug_backtrace();
		if($this->_profile) array_push($this->queries, $logdata);
		if($this->_profile) $this->queryCount++;
		if($this->link_id->errno) echo 'error : '.$this->link_id->error.' :: '.$query.'<br /><hr /><br />';
		return $resource;
	}

	/**
	* mysql_fetch_array wrapper
	*
	* @param  	object		$resource_id		[R] mysql resource
	* @param 	string		$type				mysql result type MYSQL_ASSOC | MYSQL_NUM | MYSQL_BOTH
	* @return 	array		$result				result of mysql_fetch array using the defined type
	* @access 	public
	**/
	public function fetch_array($resource_id, $type = MYSQL_ASSOC){
		return mysqli_fetch_assoc($resource_id);	
	}

	/**
	* mysqli_fetch_row wrapper
	*
	* @param  	object		$resource_id		[R] mysql resource
	* @param 	string		$type				mysql result type MYSQL_ASSOC | MYSQL_NUM | MYSQL_BOTH
	* @return 	array		$result				result of mysql_fetch array using the defined type
	* @access 	public
	**/
	public function fetch_row($resource_id){
		return mysqli_fetch_row($resource_id);	
	}

	/**
	* mysql_num_rows wrapper
	*
	* @param  	object		$resource_id		[R] mysql resource
	* @return 	integer		$count				the number of rows found in last sql query
	* @access 	public
	**/
	public function num_rows($resource_id){
		return $this->link_id->num_rows;
	}

	/**
	* mysql_insert_id wrapper - returns the last id that was inserted
	*
	* @return	integer		$id			inserted primary key identifier
	* @access	public
	**/
	public function insert_id() {
		return ($this->_profile) ? $this->last_insert_id : $this->link_id->insert_id;
	}

	/**
	* mysql_free_result wrapper
	*
	* @param		object		$resource_id			[R] mysql resource
	* @return		boolean		$success				true = success
	* @access		public
	**/
	public function free_result($resource_id){
		return mysqli_free_result($resource_id);
	}

	/**
	* mysql_close wrapper disconnect database connection.
	*
	* @return		boolean		$success			true = success
	* @access	public
	**/
	public function disconnect() {
		return mysqli_close();
	}

	/**
	* Perform an update or insert to a table.
	*
	* @param		string   	$table			[R] name of the table that is affected
	* @param		array   	$data			[R] array of data where the key = column name
	* @param		string   	$action			insert or update
	* @param		string   	$parameters		where portion of sql query
	* @param		boolean   	$debug			[D=FALSE]output debugging information
	* @return		boolean		$result			true = success
	* @access		public
	**/
	public function perform($params) {
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->perform did not receive a table parameter.') ? $params['table'] : '';
		//echo $table.'<br />';
		$data = $this->required(isset($params['data']) ? $params['data'] : NULL,get_class($this).'->perform did not receive a data parameter.') ? $params['data'] : array();
		$action = $this->not_null(isset($params['action']) ? $params['action'] : NULL) ? $params['action'] : 'insert';
		$parameters = $this->not_null(isset($params['parameters']) ? $params['parameters'] : NULL) ? $params['parameters'] : '';
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;
				
		reset($data);
		$query = '';
		if ($action == 'insert') {
			$query = 'INSERT INTO `'.$table.'` (';
			while (list($columns, ) = each($data)) {
				$query .= '`'.$columns.'`, ';
			}
			$query = rtrim($query, ', ').') values (';
			reset($data);
			while (list(, $value) = each($data)) {
				switch ((string)$value) {
					case 'now()':
						$query .= 'now(), ';
						break;
					case 'null':
						$query .= 'null, ';
						break;
					default:
						$query .= "'".$this->sanitize($value)."', ";
						break;
				} 
			}
			$query = rtrim($query, ', ').')';
		} elseif ($action == 'update') {
			$query = 'UPDATE `'.$table.'` SET ';
			while (list($columns, $value) = each($data)) {
				switch ((string)$value) {
					case 'now()':
						$query .= '`' .$columns.'`=now(), ';
						break;
					case 'null':
						$query .= '`' .$columns .= '`=null, ';
						break;
					default:
						if(strpos($value,'(select') !== FALSE) {
							$query .= '`' .$columns."`=".$this->sanitize($value).", ";
						} else {
							$query .= '`' .$columns."`='".$this->sanitize($value)."', ";
						}
						break;
				}
			}
			$query = rtrim($query, ', ').' WHERE '.$parameters;
		}
		if($debug) {
			echo "---output from ".get_class($this)."->---<br />";
			echo $query.'<br />';
			echo "---END OUTPUT (QUERY WAS PERFORMED)---<br />";
			//return;
		}
		return $this->query($query);
	}
	
	/**
	* Return an array of rows from a mysql table
	*
	* @param		string	   	$table				[R] mysql table name
	* @param		string		$where				mysql where portion of query
	* @param		boolean   	$list				[D=FALSE] true if you want to return the result using mysql_fetch_array instead of mysql_fetch_assoc
	* @param		string		$join				table you would like to join to the query
	* @param		string		$join_id			id to join the two tables together default $table_$join_table_primary_key ie(product_id,category_id)
	* @param		integer   	$limit				number of results to return [per page with pagination=true]
	* @param		string		$order				mysql order portion of query
	* @param		integer   	$page				page number to return [pagination]
	* @param		mixed		$cols				string or array of columns to return ie(''title,description' or array('title','description'))
	* @param		string		$query				search string normally direct user input ie($_REQUEST['query'])
	* @param		string		$group				column name to group results on
	* @param		boolean   	$pagination			turning this on changes the return value to array('results','pagination') where pagination is an array of (nextpage,prevpage,pages,page,total_results) also limit acts as a per page limiter
	* @param		boolean   	$auto_namespace		[D=FALSE] changes returned column names to $table_$column_name. Useful when doing a join and two column names match ie(title). A join on the tables products and categories would return products_title and categories_title respectively. Otherwise these the primary table would overload the join table.
	* @param		mixed		$search_config		array(array('column_name'=>'title','score'=>100),...) array of column names and scores to use in ranked searching.
	* @param		boolean		$debug				[D=FALSE] output debugging information
	* @return		array		$mixed				can return 3 array formats depending on situation.;list=true and result_count=1 returns array('column_name'=>'column_value');;	pagination=true and limit='Number' returns array('results'=>array(*array of rows*);; 'pagination'=>array('page','pages','next','prev','items'));; default returns array(array(*row*),array(*row*);;
	* @access		public
	**/						
	public function getTable($params) {
		if(is_string($params)) {
			$p = $params;
			$params = array();
			$params['table'] = $p;
		}
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->getTable did not receive a table parameter.') ? $params['table'] : '';
		$where = $this->not_null(isset($params['where']) ? $params['where'] : NULL) ? $params['where'] : '';
		$list = $this->not_null(isset($params['list']) ? $params['list'] : NULL) ? $params['list'] : FALSE;
		$join = $this->not_null(isset($params['join']) ? $params['join'] : NULL) ? $params['join'] : '';
		$join_id = $this->not_null(isset($params['join_id']) ? $params['join_id'] : NULL) ? $params['join_id'] : NULL;
		$limit = $this->not_null(isset($params['limit']) ? $this->sanitize($params['limit']) : NULL) ? $this->sanitize($params['limit']) : '';
		$order = $this->not_null(isset($params['order']) ? $params['order'] : NULL) ? $params['order'] : '';
		$page = $this->not_null(isset($params['page']) ? $this->sanitize($params['page']) : NULL) ? $this->sanitize($params['page']) : '';
		$cols = $this->not_null(isset($params['cols']) ? $params['cols'] : NULL) ? $params['cols'] : '';
		$search_config = $this->not_null(isset($params['search_config']) ? $params['search_config'] : NULL) ? $params['search_config'] : '';
		$query = $this->not_null(isset($params['query']) ? $this->sanitizeQuery($params['query']) : NULL) ? $this->sanitizeQuery($params['query']) : '';
		$group = $this->not_null(isset($params['group']) ? $params['group'] : NULL) ? $params['group'] : '';
		$pagination = $this->not_null(isset($params['pagination']) ? $params['pagination'] : NULL) ? $params['pagination'] : '';		
		$auto_namespace = $this->not_null(isset($params['auto_namespace']) ? $params['auto_namespace'] : FALSE) ? $params['auto_namespace'] : FALSE;
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;
		
		if(!$this->not_null($cols) && $auto_namespace) {
			$cols = $this->getTableColumns($table);
			array_walk($cols,'walk_table_columns',$table);
			if($this->not_null($join)) {
				$join_cols = $this->getTableColumns($join);
				array_walk($join_cols,'walk_table_columns',$join);
				$cols = array_merge($cols,$join_cols);
			}
			$cols = implode(',',$cols);
		}
		$page_skip=null;
		if($this->not_null($where)) $where = 'where '.$where;
		if($this->not_null($search_config) && $this->not_null($query)) {
			$where .= ($where) ? ' and '.$this->buildSearch($search_config,$query).' > 0' : 'where '.$this->buildSearch($search_config,$query).' > 0';
			$cols = '*,'.$this->buildSearch($search_config,$query).' as search_relevance';
			$order = $this->not_null($order) ? $order : 'search_relevance desc';
		} elseif($this->not_null($cols))  {
			// do nothing it is already set
		} else {
			$cols = '*';
		}
		if($this->not_null($page) && $this->not_null($limit)) $page_skip = ($page-1)*$limit.',';
		if($this->not_null($limit)) $limit = 'limit '.$page_skip.$limit;
		if($this->not_null($order)) $order = 'order by '.$order;
		if($this->not_null($join)) {
			$primary_key = $this->getPrimaryKey($table);
			if(is_null($join_id)) $join_id = $join.'_'.$primary_key;
			$join = $auto_namespace || $primary_key != $join_id ? 'join '.$join.' on '.$table.'.'.$primary_key.' = '.$join.'.'.$join_id : 'join '.$join.' using('.$join_id.')';
		}
		if($this->not_null($group)) {
			$group = 'group by '.$group;
		}
		$sql = "select {$cols} from {$table} {$join} {$where} {$group} {$order} {$limit}";
		if($this->not_null($debug))	$this->dump('getTable SQL', $sql);
		$res = $this->query($sql);
		if(!$res) return FALSE;
		if($this->not_null($list)) return mysqli_num_rows($res) == 1 ? mysqli_fetch_assoc($res) : array_map(create_function('$item', 'return array_shift($item);'),$this->res2array($res));
		if($this->not_null($pagination)) {
			$ret['results'] = $this->res2array($res);		
			$ret['pagination'] = $this->getPagination($params);
			return $ret;
		}
		return $this->res2array($res);
	}
	
	public function getPagination2($params) {
		$params['pages'] = ceil($params['items']/$params['limit']);
		if($params['page']+1 > $params['pages']) {
			$params['next'] = 0;
			$params['prev'] = $params['pages']-1;
		} else {
			$params['next'] = $params['page']+1;
			$params['prev'] = ($params['page']-1 < 1) ? 0 : $params['page']-1;
		}
		return $params;
	}
	
	/**
	* Get the pagination information [page,pages,items,next page,previous page]
	*
	* @param		string		$table				[R] mysql table name
	* @param		string		$where				mysql where portion of query
	* @param		string		$join				table you would like to join to the query
	* @param		string		$join_id			id to join the two tables together default $table_$join_table_primary_key ie(product_id,category_id)
	* @param		integer		$limit				[R] number of results per page
	* @param		string		$order				mysql order portion of query
	* @param		integer   	$page				page number to return [pagination]
	* @param		mixed		$cols				string or array of columns to return ie(''title,description' or array('title','description'))
	* @param		string		$query				search string normally direct user input ie($_REQUEST['query'])
	* @param		string		$group				column name to group results on
	* @param		boolean   	$auto_namespace		[D=FALSE] changes returned column names to $table_$column_name. Useful when doing a join and two column names match ie(title). A join on the tables products and categories would return products_title and categories_title respectively. Otherwise these the primary table would overload the join table.
	* @param		array		$search_config		array(array('column_name'=>'title','score'=>100),..) array of column names and scores to use in ranked searching.
	* @param		boolean   	$debug				[D=FALSE] output debugging information
	* @return		array		$pagination			array('page','pages','items','next','prev');
	* @access		public
	**/
	public function getPagination($params) {
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->getPagination did not receive a table parameter.') ? $params['table'] : '';
		$where = $this->not_null(isset($params['where']) ? $params['where'] : NULL) ? $params['where'] : '';
		$join = $this->not_null(isset($params['join']) ? $params['join'] : NULL) ? $params['join'] : '';
		$join_id = $this->not_null(isset($params['join_id']) ? $params['join_id'] : NULL) ? $params['join_id'] : '';
		$limit = isset($params['limit']) ? $this->sanitize($params['limit']) : 2;
		$page = $this->not_null(isset($params['page']) ? $this->sanitize($params['page']) : 1) ? $this->sanitize($params['page']) : '';
		$cols = $this->not_null(isset($params['cols']) ? $params['cols'] : NULL) ? $params['cols'] : '';
		$search_config = $this->not_null(isset($params['search_config']) ? $params['search_config'] : NULL) ? $params['search_config'] : '';
		$query = $this->not_null(isset($params['query']) ? $this->sanitizeQuery($params['query']) : NULL) ? $this->sanitizeQuery($params['query']) : '';
		$group = $this->not_null(isset($params['group']) ? $params['group'] : NULL) ? $params['group'] : '';
		$auto_namespace = $this->not_null(isset($params['auto_namespace']) ? $params['auto_namespace'] : FALSE) ? $params['auto_namespace'] : FALSE;
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;
		if($this->not_null($where)) $where = 'where '.$where;
		if(!$this->not_null($cols) && $this->not_null($auto_namespace)) {
			$cols = $this->getTableColumns($table);
			array_walk($cols,'walk_table_columns',$table);
			if($this->not_null($join)) {
				$join_cols = $this->getTableColumns($join);
				array_walk($join_cols,'walk_table_columns',$join);
				$cols = array_merge($cols,$join_cols);
			}
			$cols = implode(',',$cols);
		}
		
		if($this->not_null($search_config) && $this->not_null($query)) {
			$where .= $this->not_null($where) ? ' and '.$this->buildSearch($search_config,$query).' > 0' : 'where '.$this->buildSearch($search_config,$query).' > 0';
		}
		if($this->not_null($join)) {
			$primary_key = $this->getPrimaryKey($table);
			if(is_null($join_id)) $join_id = $join.'_'.$primary_key;
			$join = $auto_namespace || $primary_key != $join_id ? 'join '.$join.' on '.$table.'.'.$primary_key.' = '.$join.'.'.$join_id : 'join '.$join.' using('.$join_id.')';
		}
		$cols = 'count(*) as cnt'; 
		if($this->not_null($group)) {
			$cols .= ','.$group;
			$group = 'group by '.$group;
		}
		$sql = "select {$cols} from {$table} {$join} {$where} {$group}";
		
		if($this->not_null($debug)) $this->dump('getPagination SQL', $sql);
		$res = $this->query($sql);
		
		list($cnt) = $res->num_rows>1 ? array($res->num_rows) : mysqli_fetch_array($res);
		$pages = ceil($cnt/$limit);

		$data['items'] = $cnt;
		$data['pages'] = $pages;
		$data['page'] = $page;

		if($page+1 > $pages) {
			$data['next'] = 0;
			$data['prev'] = $pages-1;
		} else {
			$data['next'] = $page+1;
			$data['prev'] = ($page-1 < 1) ? 0 : $page-1;
		}
		return $data;
	}
	
	/**
	* remove : remove a table row completely using $id as the identifier
	*
	* @param 	string		$table 				[R] table name
	* @param 	integer		id  				[R] table primary column name ie(id,product_id,category_id) what ever the primary key of the table is
	* @param 	mixed		$where_override		override the standard identification (where primary_key = {$id}) and use your own ie(where type_id='2' and mode="error") or send an array('type_id'=>2,'mode'=>'error')
	* @param 	boolean		$debug  			[D=FALSE] show debug information
	* @access 	public
	* @return 	boolean		$result				true = success
	**/
	public function remove($params) {
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->remove did not receive a table parameter.') ? $params['table'] : '';
		$primary_key = $this->getPrimaryKey($table);
		$id = $this->required(isset($params[$primary_key]) ? $this->sanitize($params[$primary_key]) : NULL,get_class($this).'->remove did not receive a primary key parameter. ["'.$primary_key.'"]') ? $this->sanitize($params[$primary_key]) : '';
		
		$where_override = $this->not_null(isset($params['where_override']) ? $params['where_override'] : NULL) ? $params['where_override'] : '';
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;
		
		$where = is_array($id) ? $this->arrayToWhere(array('data'=>$id)) : 'where '.$primary_key.' = "'.$this->sanitize($id).'"';
		if($this->not_null($where_override)) $where = 'where '.$where_override;
		$sql = "delete from {$table} {$where}";
		if($debug) {
			$this->dump('Remove Debug',$sql);
			return;
		}
		return $this->query($sql);
	}

	/**
	* insert : add a new row to a table
	*
	* TODO: refactor file uploads
	*
	* @param 	string		$table 				[R] table name
	* @param 	array		$data  				[R] array of data to be added to the table ('id'=>1,'title'=>'test')
	* @param 	string		$download 			$_FILE['my_download'] from html form
	* @param 	string		$destination 		destination url for file uploads
	* @param 	string		$name_override		use this string instead of automatic naming
	* @param 	boolean		$debug 				[D=FALSE] output sql and do not execute query
	* @return 	boolean		$insert_id			primary key value of inserted row
	* @access 	public
	**/
	public function insert($params,$data=NULL) {
		if(!is_array($params)) $params = array('table'=>$params,'data'=>$data,'debug'=>isset($data['debug']) && $data['debug'] != FALSE ? TRUE : FALSE);
		if(isset($params['data']['debug'])) unset($params['data']['debug']);
		$table = isset($params['table']) ? $params['table'] : NULL;
		$data = isset($params['data']) ? $params['data'] : NULL;
		$debug = isset($params['debug']) && $params['debug'] == TRUE ? TRUE : FALSE;
		if(is_null($table) || is_null($data)) $this->dump('mysqlInsert //Error',$params);
		//$download = $this->not_null(isset($params['download']) ? $params['download'] : NULL) ? $params['download'] : '';
		$destination = $this->not_null(isset($params['destination']) ? $params['destination'] : NULL) ? $params['destination'] : '';
		$name_override = $this->not_null(isset($params['name_override']) ? $params['name_override'] : NULL) ? $params['name_override'] : '';
		
		//if($this->not_null($download)) $data['download'] = $this->uploadFile(array('download'=>$download,'destination'=>$destination,'name_override'=>$name_override));
		if($debug) $this->dump('insert dump',array('table'=>$table,'data'=>$data));
		$this->perform(array('table'=>$table,'data'=>$data,'debug'=>$debug));
		return $this->insert_id();
	}

	/**
	* update the information in a table using $data[id] as the identifier
	*
	* TODO: refactor file uploads
	*
	* @param 	string		$table 				[R] table name
	* @param 	integer		$data  				[R] data array of k=>v pairs. primary key is required to identify row.
	* @param 	string		$download 			$_FILE['my_download']
	* @param 	string		$destination 		destination url for file uploads
	* @param 	string		$name_override		use this string instead of automatic naming
	* @param 	boolean		$debug 				[D=FALSE] output sql and do not execute query
	* @return 	boolean		$result				true = success
	* @access 	public
	**/
	public function update($params) {
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->update did not receive a table parameter.') ? $params['table'] : '';
		$id = $this->not_null(isset($params['data']['id']) ? $params['data']['id'] : NULL) ? $params['data']['id'] : '';
		$data = $this->required(isset($params['data']) ? $params['data'] : NULL,get_class($this).'->update did not receive a data parameter.') ? $params['data'] : array();
		//$download = $this->not_null(isset($params['download']) ? $params['download'] : NULL) ? $params['download'] : array();
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;
		$destination = $this->not_null(isset($params['destination']) ? $params['destination'] : NULL) ? $params['destination'] : '';
		$name_override = $this->not_null(isset($params['name_override']) ? $params['name_override'] : NULL) ? $params['name_override'] : '';
		$where_override = $this->not_null(isset($params['where_override']) ? $params['where_override'] : NULL) ? $params['where_override'] : '';
		$primary_key = $this->getPrimaryKey($table);
		
		$where_override = is_array($where_override) ? $this->arrayToWhere(array('data'=>$where_override,'no_where'=>TRUE)) : $where_override;
		$parameters = $this->not_null($where_override) ? $where_override : $primary_key.'="'.$id.'"';
				
		unset($data['id']);
		//if($this->not_null($download) && $this->not_null($download['name'])) {
			//if($debug) $this->dump('Upload Initiated',$download);
			//$data['download'] = $this->uploadFile(array('download'=>$download,'destination'=>$destination,'name_override'=>$name_override));
		//}
		return $this->perform(array('table'=>$table,'data'=>$data,'action'=>'update','parameters'=>$parameters,'debug'=>$debug));
	}

	/**
	* [EXPERIMENTAL] upload a file to the server. Append a random number to discourage conflicting files. 
	*
	* @param 	file    	$file  				[R] $_FILE['id']
	* @param 	string    	$dest  				[R] destination folder
	* @param 	string    	$name_override		use a new name for the file
	* @param 	boolean		$debug 				[D=FALSE] output debug information for uploading file
	* @return 	string		$file_name			name of the saved file
	* @access 	public
	**/
	public function uploadFile($params) {
		$file = $this->required(isset($params['file']) ? $params['file'] : NULL,get_class($this).'->uploadFile did not receive a table parameter.') ? $params['file'] : '';
		$destination = $this->required(isset($params['destination']) ? $params['destination'] : NULL,get_class($this).'->uploadFile did not receive a destination parameter.') ? $params['table'] : '';
		$name_override = $this->not_null(isset($params['name_override']) ? $params['name_override'] : NULL) ? $params['name_override'] : NULL;
		$debug = $this->not_null(isset($params['debug']) ? $params['debug'] : NULL) ? $params['debug'] : FALSE;

		list($f1,$f2) = explode('.',$file['name']);
		$fname = !is_null($name_override) ? $name_override : $this->slugCreate($f1).'.'.$f2;
		$dest = substr($dest,-1) == '/' ? $dest : $dest.'/';
		if(file_exists($dest.$fname) && is_null($name_override)) {
			list($p1,$p2) = explode('.',$fname);
			$fname = $p1.'-'.rand(0,10000000).'.'.$p2;
		} elseif(file_exists($dest.$fname) && !is_null($name_override)) {
			$res = rename($dest.$fname,$dest.$fname.rand(0,10000));
		}

		if (move_uploaded_file($file['tmp_name'], $dest.$fname)) {
			return $fname;
		}
		return false;
	}
	
	/**
	* See if the information is already present in the table. If all of the information in $data is in the table already then we should not add it again. Even if the db takes care of this already we should run a check
	*
	* @param 	string		$table 		[R] table name
	* @param 	array		$data  		[R] array of data that will be checked against the database
	* @param 	boolean		$debug 		output sql and do not execute query
	* @return 	boolean		$result		true = values already exist in database
	* @access 	public
	**/
	public function isDuplicate($params) {
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->isDuplicate did not receive a table parameter.') ? $params['table'] : '';
		$data = $this->required(isset($params['data']) ? $params['data'] : NULL,get_class($this).'->isDuplicate did not receive a data parameter.') ? $params['data'] : array();
		
		$where = $this->arrayToWhere(array('data'=>$data));
		$sql = "select 1 from {$table} {$where}";
		$res = $this->res2array($this->query($sql));
		if(count($res)) return 1;
		return 0;
	}	
	
	/**
	* get columns of a table
	*
	* @param		string		$table				[R] name of the table to get columns from
	* @param		string		$database			[D=Current Database] name of the database that the table is in
	* @return		array		$table_columns		array of column names
	* @access		public
	**/	
	public function getTableColumns($params) {
		if(!is_array($params)) $params = array('table'=>$params);
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->getTableColumns did not receive a table parameter.') ? $params['table'] : '';
		$database = $this->not_null(isset($params['database']) ? $params['database'] : NULL) ? $params['database'] : $this->db;
		
		return $this->res2array($this->query('select column_name,column_type from information_schema.columns where table_name = "'.$table.'" and table_schema="'.$database.'"'));
	}
	
	/**
	* change an array to a where clause in an sql query
	*
	* @param		array   	$data			[R] associative array of data array('id'=>1,'category_id'=>2)
	* @param		boolean		$no_where		do not add the 'where ' prefix to return value
	* @return		boolean		$where			string in mysql where format ie(where 'id'='1' and 'category_id'=2)
	* @access		public
	**/	
	public function arrayToWhere($params) {
		$data = $this->required(isset($params['data']) ? $params['data'] : NULL,get_class($this).'->arrayToWhere did not receive a data parameter.') ? $params['data'] : '';
		$no_where = $this->not_null(isset($params['no_where']) ? $params['no_where'] : NULL) ? $params['no_where'] : '';
		
		$i=0;
		$where = !$no_where ? 'where ' : '';
		foreach($data as $k=>$v) {
			if($i==0) {
				$where .= $k.' = "'.$this->sanitize($v).'"';
			} else {
				$where .= ' and '.$k.' = "'.$this->sanitize($v).'"';
			}
			$i++;
		}
		return $where;
	}
	
	/**
	* convert an array of database rows to a string of ids
	*
	* TODO: make it work with nested arrays
	*
	* @param 	array    	$data  		[R] array of data returned from res2array (which is used in most methods except getList)
	* @param 	string    	$column_id  [D=id] column to turn into a comma seperated list
	* @return	string		$ids		comma seperated list of column values like an id list ie(1,5,6,12,etc) useful for where in queries
	* @access 	public
	**/
	public function ids($params) {
		$data = isset($params['data']) ? $params['data'] : FALSE;
		$col = isset($params['column_id']) ? $params['column_id'] : 'id';
		if(!$data) //Error::program//Error('data missing from database->ids',$params);
		
		if(!count($data)) return;
		foreach($data as $row) $return[] = $row[$col];
		return implode(',',$return);
	}	
			
	public function getValues($ar,$keys) {
		$data = array();
		$keys = is_array($keys) ? $keys : array($keys);
		foreach($ar as $k=>$v) {
			foreach($keys as $v2) {
				$temp[$v2] = $v[$v2];
			}
			$data[] = $temp;
		}
		return $data;
	}
	
	/**
	* return nested array from mysql adjacency list. parent_id is assumed to be the relational column. If it is not, edit this function, you obviously know what you are doing :)
	*
	* TODO: add alert for stranded items. We can tell by the orphaned children and the 40 year olds in the club.
	*
	* @param		array    	$table				[R] table to nest on parent_id
	* @param		string    	$where				sql where
	* @param		string    	$parent_id			[D=0] array of parents with nested parent-children arrays
	* @param		string		$column_name		override the default parent_id
	* @return		array		$parents			array of parent child relationships
	* @access		public
	**/
	public function getAdjacencyList($params) {
		if(is_string($params)) {
			$p = $params;
			$params = array();
			$params['table'] = $p;
		}
		$table = $this->required(isset($params['table']) ? $params['table'] : NULL,get_class($this).'->getList did not receive a table parameter.') ? $params['table'] : '';
		$primary_key = $this->getPrimaryKey($table);
		//$column_name = $this->not_null(isset($params['column_name']) ? $params['column_name'] : 'parent_id') ? $params['column_name'] : 'parent_id';
		$column_name = isset($params['column_name']) ? $this->not_null($params['column_name']) ? $params['column_name'] : 'parent_id' : 'parent_id';
		$parent_id = $this->not_null(isset($params['parent_id']) ? $params['parent_id'] : NULL) ? $params['parent_id'] : NULL;
		$full_list = $this->getTable($params);
		$parents = array();
		$children = array();
		$primary_key = $this->getPrimaryKey($table);
		foreach($full_list as $c) {
			if(($parent_id == 0 && $c['parent_id'] == 0) || count($full_list) == 1) {
                $parents[$c[$primary_key]] = $c;
            } elseif($parent_id != 0 && $c[$primary_key] == $parent_id) {
				$parents[$c['parent_id']] = $c;
			} else {
                $children[$c['parent_id']][] = $c;
            }
		}
		foreach($parents as $k=>$p) $parents[$k]['children'] = $this->addChildArrays($p,$children,$primary_key);
		return $parents;
	}
	
	/**
	* recursive function to add children to their parent
	*
	* @param  	array   	$p					[R] parents array
	* @param  	array   	$children			[R] children array
	* @param  	integer   	$primary_key		[D=id] key to match children to
	* @return	array		$data				array of children for the parent
	* @access 	public
	**/
	public function addChildArrays(&$p,&$children,$primary_key='id') {
		$data = array();
		if(isset($children[$p[$primary_key]])) {
			foreach($children[$p[$primary_key]] as $k=>$c) {
				$data[$c[$primary_key]] = $c;
				$data[$c[$primary_key]]['children'] = $this->addChildArrays($c,$children,$primary_key);
				unset($children[$p[$primary_key]][$k]);
			}
		}
		return $data;
	}	
	
	/**
	* dump : echo debug information
	*
	* @param string    $id  identifier
	* @param mixed    $vals  information to dump
	* @access public
	* @return array
	**/
	public function dump($title=null,$data=null,$config_override=FALSE) {
		if(is_array($title)) {
			$data = $title;
			$title = 'Unnamed';
		}
		
		echo '<div class="debug">
				<div class="debug-title">
					'.$title.'
				</div>
				<div class="debug-content">'."\n";
		if(is_array($data)) {
			echo '<pre>'."\n";
			var_dump($data);
			echo '</pre>'."\n";
		} else {
			echo nl2br($data);
		}
		echo '<br /><h3>BackTrace</h3>'."\n";
		$bt = debug_backtrace();
		echo '<ul class="picto-list">'."\n";
		foreach($bt as $b) {
			echo '<li><strong>'.basename($b['file']).'</strong> : line #'.$b['line'].' -> '.$b['function'].'</li>'."\n";	
		}
		echo '</ul>'."\n";
		echo '</div></div>';
	}
	
	/**
	* Change a mysql result into an array so that it can be accessed by smarty
	*
	* @param  	resource   	$res		[R] mysql resource
	* @return	array		$result		arrays of column=>value pairs, one per row
	* @access	public
	**/
	public function res2array($res) {
		if(!is_object($res)) return array();
		$ar = array();
		while ($row = $res->fetch_array(MYSQLI_ASSOC)) $ar[] = $row;
		return $ar;
	}
	
	/**
	* santize data of sql injection attacks
	*
	* @param  	mixed   	$input		[R] array or string to be sanitized
	* @param  	mixed   	$def		array or string to be returned if sanitization returns an empty string
	* @return 	mixed		$clean		array or string of sanitized data
	* @access 	public
	**/
	public function sanitize($input,$def=null){
		if(is_array($input)){
			foreach($input as $k=>$i) $output[$k]=self::sanitize($i);
		} else{
			if(get_magic_quotes_gpc()) $input=stripslashes($input);
			$output=mysqli_real_escape_string($this->link_id,$input);
		}
		return $output ? $output : $def;
	}
	
	/**
	* require parameter
	*
	* @param  	mixed   	$param		[R] any value to check for null - if null halt script execution
	* @param  	mixed   	$msg		message to display
	* @return 	mixed		$result		exit or success / do or die!
	* @access 	public
	**/
	public function required($param,$msg='') {
    	if(!$this->not_null($param)) {
			$this->dump('Missing Required Parameter',$msg);
			exit;	
		}
		return 1;
	}
	
	/**
	* TODO: finish building profile display
	*
	* @return 	output		output profile information
	* @access 	public
	**/
	public function profile() {
		if(!$this->_profile) return array("error",'Profiler is turned off. $db->_profile=TRUE');
		function convert($size) {
			$unit=array('b','kb','mb','gb','tb','pb');
			return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
		}
		$html = '';
		$memory = convert(memory_get_usage(true));
		$peak_memory = convert(memory_get_peak_usage(true));
		$html .= '<ul class="picto-list">';
		$html .= '<li><strong>Memory:</strong> '.$memory.'</li>';
		$html .= '<li><strong>Peak Memory:</strong> '.$peak_memory/*.print_r(array_keys($q),TRUE)*/.'</li>';
		$html .= '</ul>';
		foreach($this->queries as $q) {
			$temp_id = md5($q['sql']);
			$html .= '<div class="corners border-grey bg-tan medium-margin with-medium-padding">';
			$html .= '<div class="float-right corners with-small-padding small" style="width:720px;font-family:Courier;line-height:1.75em;">'.$q['sql'].'</div>';
			$html .= '<div class="float-left corners bg-blue with-small-padding text-white small">'.sprintf('%0.2f',$q['time']).'</div>';
			$html .= '<div class="clear"></div>';
			$html .= '<a class="button toggle-ref float-right small" ref="#explain-'.$temp_id.'">Explain</a> ';
			$html .= '<a class="button toggle-ref float-right small margin-right" ref="#status-'.$temp_id.'">Status</a> ';
			$html .= '<a class="button toggle-ref float-right small margin-right" ref="#backtrace-'.$temp_id.'">Backtrace</a> ';
			$html .= '<div class="clear"></div>';
			$html .= '<div class="hidden default-toggle-hidden float-left margin-right" id="explain-'.$temp_id.'" >';
			$res = $this->res2array($this->query('explain '.$q['sql']));
			if(count($res)) {
				$cols = array('table','type','possible_keys','key','ref','rows','extra');
				$html .= '<table class="table full-width"><thead><tr><th colspan="10">Explain:</th></tr></thead><tbody>';
				foreach($cols as $c) {
					$html .= '<tr><th>'.$c.'</th>';
					foreach($res as $r) {
						if(!isset($r[$c])) continue;
						$html .= '<td>'.str_replace(array('factproject',','),array('FP','<br />'),$r[$c]).'</td>';
					}
					$html .= '</tr>';
				}
				$html .= '</tbody></table>';
			}
			$html .= '</div>';
			
			$html .= '<div class="hidden default-toggle-hidden float-left margin-right" id="backtrace-'.$temp_id.'" >';
			$html .= '<ul class="picto-list">'."\n";
			foreach($q['debug_backtrace'] as $b) {
				$html .= '<li><strong>'.basename($b['file']).'</strong> : line #'.$b['line'].' -> '.$b['function'].'</li>'."\n";	
			}
			$html .= '</ul>'."\n";
			$html .= '</div>';
			
			$html .= '<div class="hidden default-toggle-hidden float-left" id="status-'.$temp_id.'" >';
			if(count($q['poststatus'])) {			
				$html .= '<table class="table"><thead><tr><th colspan="10">Post-Status:</th></tr></thead><tbody>';
				foreach($q['poststatus'] as $k=>$v) {
					if($v['Value'] != $q['prestatus'][$k]['Value']) $html .= '<tr><th>'.$v['Variable_name'].'</th><td>'.($v['Value']-$q['prestatus'][$k]['Value']).'</td></tr>';
				}
				$html .= '</tbody></table>';
			}
			$html .= '</div>';
			$html .= '<div class="clear"></div>';
			$html .= '</div>';
		}
		$html .= '<div>';
		$html .= '<table class="table"><thead><tr><th colspan="10">Total Status:</th></tr></thead><tbody>';
		foreach($this->queries[0]['prestatus'] as $k=>$v) {
			if($v['Value'] != $this->queries[count($this->queries)-1]['prestatus'][$k]['Value']) $html .= '<tr><th>'.$v['Variable_name'].'</th><td>'.($this->queries[count($this->queries)-1]['poststatus'][$k]['Value']-$v['Value']).'</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= '</div>';
		$this->dump('Profile',$html);
	}
	
	/**
	* get the tables primary key
	*
	* @param  	mixed   	$table				[R] name of table that contains the primary key we want
	* @return 	string		$primary_key		name of primary key
	* @access 	public
	**/
	public function getPrimaryKey($table) {
		$first = $this->fetch_array($this->query("SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'"));
		return $first['Column_name'];
	}
	
	
	
	/*** MY PRIVATES ***/

	/**
	* recursive function to output adjacency list
	*
	* @param 	mixed    	$key  	[R] array key - usually column name could be an index though
	* @param 	mixed    	$vals  	[R] array or array value
	* @return 	null
	* @access 	private
	**/
	private function outputArrayElements($key,$vals) {	
		$this->outputArray($key,$vals);
		if(isset($vals['children']) && $this->not_null($vals['children'])) {
			foreach($vals['children'] as $k=>$c) {
				$this->outputArrayElements($k,$c);		
			}
		}
	}
	
	/**
	* remove special characters from the query
	*
	* @param  	mixed   	$q		query
	* @access 	public
	**/
	private function sanitizeQuery($q) {
		$data['before'] = $q;
		$q = preg_replace('/[^A-Za-z0-9_-\s]/','',$q);
		$data['after'] = $q;
		return $this->sanitize($q);
	}
	
	/**
	* dump : echo debug information
	*
	* @param 	mixed    	$key		[R] array key - usually column name could be an index though
	* @param 	mixed    	$vals		[R] array value
	* @return 	output		$html		prints array in a table
	* @access 	private
	**/
	private function outputArray($key,$vals) {
		if(is_array($vals)) {
			foreach($vals as $k=>$v) {
				$v = is_array($v) ? 'Array('.count($v).')' : $v;
				echo 	'<div class="border-black small-corners float-left small-padding">
							<div class="light-grey-gradient small-padding full-width small-bottom-margin">
								['.$k.']
							</div>'.
							$v.
						'</div>';			
			}
		} else {
				echo 	'<div class="border-black small-corners float-left small-padding">
							<div class="light-grey-gradient small-padding full-width small-bottom-margin">
								['.$key.']-
							</div>'.
							$vals.
						'</div>';			
		}
	}
	
	/**
	* output error information
	*
	* @param		string   	$title				[R] title of error
	* @param		string   	$description		[R] error description
	* @param		mixed   	$halt				[D=FALSE] true = stop execution because the error is that bad
	* @return		output		exit				do or die. Print error, if halt enabled it will exit the script
	* @access		private
	**/
	private function error($query,$title,$halt=FALSE) {
		if(is_null($query)) {
			//Error::website//Error($title);
		} else {
			//Error::mysql//Error($query,$title);
		}
		if($halt) exit;
	}
	
	/**
	* build match and like lists for ranked mysql fulltext searching
	*
	* @param  	array   	$columns		[R] array of columns and scores to use in the search.
	* @param  	array   	$query			[R] query string. normally from user input form
	* @return 	string		$where			string of where information
	* @access 	private
	**/
	private function buildSearch($columns,$query) {	
		$where = '';
		$terms = explode(" ", preg_replace(array('/\s{2,}/', '/[\t\n]/','/[^\w\d -]/si'),array(' ',' ',''),trim($query)));
		$likes = array();
		$matches = array();
		$like = '';
		
		if(count($terms) > 1 || (count($terms)==1 && strlen($terms[0]) > 1)) {
			foreach($terms as $t) {
				if(!$this->not_null($this->allow_modifiers)) $t = preg_replace("/[^a-z\d]/i", "", $t);
				if(strlen($t) <= 3) {
					if(in_array($t,array('about','after','all','also','an','and','another','any','are','as','at','be','because','been','before
								being','between','both','but','by','came','can','come','could','did','do','each','for','from','get
								got','has','had','he','have','her','here','him','himself','his','how','if','in','into','is','it','like
								make','many','me','might','more','most','much','must','my','never','now','of','on','only','or','other
								our','out','over','said','same','see','should','since','some','still','such','take','than','that
								the','their','them','then','there','these','they','this','those','through','to','too','under','up
								very','was','way','we','well','were','what','where','which','while','who','with','would','you','your','a
								b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','$',
								'1','2','3','4','5','6','7','8','9','0','_'))) continue;	
					$likes[] = $t;
				} else {
					$matches[] = $t;
				}
			}
			
			$likes = array_unique($likes);
			$matches = array_unique($matches);
			
			if(count($matches)) {
				$matchlist = implode(" ",$matches);
				foreach($columns as $col) $wheres[] = '(match('.$col['column_name'].') against ("'.$matchlist.'" IN BOOLEAN MODE)*'.$col['score'].')';
				$where .= implode('+',$wheres);
			}
			
			if(count($likes)) {
				foreach($likes as $l) {
					foreach($columns as $c) {
						$like_list[] = $c['column_name']." like '%".$l."%' ";
					}
					$like = implode(' or ',$like_list);
				}
			}
			if($like != '') $like = '('.$like.')';
		} 
		if($where != '' && $like != '') $like = ' and '.$like;
		if($like != '') $where .= $like;
		return $where;
	}
	
	/**
	* check if a value is really, really null
	*
	* @param  	mixed   	$value			[R] check to see what type of value was supplied.
	* @return 	boolean		$success		true = success
	* @access 	private
	**/
	private function not_null($value) {
		switch(gettype($value)){
			case 'boolean':
			case 'object':
			case 'resource':
			case 'integer':
			case 'double':
				return $value;
				break;
			case 'string':
				if (($value != '') && (strtolower($value) != 'null') && (strlen(trim($value)) > 0)){
				    return true;
				} else {
				    return false;
				}
				break;
			case 'array':
				if (sizeof($value) > 0){
				    return true;
				} else {
				    return false;
				}
				break;
			case 'NULL':
			default:
				return false;
				break;
		}
	}
	
	public function alterTableColumn($params) {
		if(!isset($params['table']) || !isset($params['column']) || !isset($params['new_column'])) return 'ERROR BIGTIME : You might have seriously damaged the datbase tables if it werent for this 2 minutes of typing';
		$table = $params['table'];
		$column = $params['column'];
		$new_column = $params['new_column'];
		$table_columns = $this->getTableColumns(array('table'=>$table));
		$flag=0;
		foreach($cols as $c) if($c['column_name'] == 'status_id') $flag = 1;
		if(!$flag) return;
		$Supernova->query('alter ignore table `'.$table.'` change column `status_id` `status_id` tinyint(4) unsigned default null');
	}
	
	public function getForeignKeysToTable($params) {
		if(!is_array($params)) $params = array('table'=>$params);
		return $this->res2array($this->query('select table_name,column_name,constraint_name,referenced_table_name,referenced_column_name from information_schema.key_column_usage where referenced_table_name = "'.$params['table'].'"'));	
	}
	
	public function getForeignKeysForTable($params) {
		if(!is_array($params)) $params = array('table'=>$params);
		return $this->res2array($this->query('select table_name,column_name,constraint_name,referenced_table_name,referenced_column_name from information_schema.key_column_usage where referenced_table_name is not null and table_name = "'.$params['table'].'"'));	
	}
	
	public function tableHasColumn($params) {
		$cols = $this->getTableColumns($params['table']);
		$this->dump('cols',$params);
		$flag=0;
		foreach($cols as $c) {
			if($c['column_name'] == $params['column']) $flag = 1;
		}
		return $flag;
	}
	
	public function addForeignKey($params) {
		if(!isset($params['table']) || !isset($params['table_column']) || !isset($params['foreign_table']) || !isset($params['foreign_table_column'])) return 'ERROR missing required data in addForeignKey';
		if(!$this->tableHasColumn(array('table'=>$params['table'],'column'=>$params['table_column']))) return 'foreign key not added because table column does not exist';
		if(!$this->tableHasColumn(array('table'=>$params['foreign_table'],'column'=>$params['foreign_table_column']))) return 'foreign key not added because foreign table column does not exist';
		
		$fks = $this->getForeignKeysForTable($params['table']);
		$cnt=1;
		foreach($fks as $fk) {
			$name_parts = explode('_',$fk['constraint_name']);
			$cnt = $cnt > $name_parts[sizeof($name_parts)-1] ? $cnt : $name_parts[sizeof($name_parts)-1];
			if($fk['column_name'] == $params['table_column']) {
				$this->query('alter table '.$params['table'].' drop foreign key '.$fk['constraint_name']);
				$cnt = $cnt-1;
			}
		}
		$cnt++;
		$this->query('alter table '.$params['table'].' add constraint '.$params['table'].'_ibfk_'.$cnt.' foreign key ('.$params['table_column'].') references '.$params['foreign_table'].' ('.$params['foreign_table_column'].') on update cascade on delete set null');
		echo 'key added.. I think.<br />';
		echo '<em>'.'alter table '.$params['table'].' add constraint '.$params['table'].'_ibfk_'.$cnt.' foreign key ('.$params['table_column'].') references '.$params['foreign_table'].' ('.$params['foreign_table_column'].') on update cascade on delete set null';
		echo '<p />';
	}
	
	public function encrypt($unencrypted,$key) {
		if(!is_string($unencrypted)) $unencrypted = serialize($unencrypted);
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $unencrypted, MCRYPT_MODE_CBC, md5(md5($key))));	
	}
	
	public function decrypt($encrypted,$key) {
		$decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($encrypted), MCRYPT_MODE_CBC, md5(md5($key))), "\0");	
		return $this->is_serialized($decrypted) ? unserialize($decrypted) : $decrypted;
	}
	
	public function is_serialized($string) {
    	return (@unserialize($string) !== false);
	}
	
	public function affected_rows() {
		return $this->link_id->affected_rows;
	}
}
?>