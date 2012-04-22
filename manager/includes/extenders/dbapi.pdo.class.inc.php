<?php

/* Datbase API object of MySQL
 * Written by Raymond Irving June, 2005
 *
 */

class DBAPI {

	var $conn;
	var $config;
	var $isConnected=false;
	var $dbtype='mysql';
	var $pdo=null;
	
	/**
	* @name:  DBAPI
	*
	*/
	function DBAPI($host='',$dbase='', $uid='',$pwd='',$prefix=NULL,$charset='',$connection_method='SET CHARACTER SET')
	{
		global $database_server,$dbase,$database_user,$database_password,$table_prefix,$database_connection_charset,$database_connection_method;
		
		$this->config['host']    = $host    ? $host    : $database_server;
		$this->config['dbase']   = $dbase   ? $dbase   : $dbase;
		$this->config['user']    = $uid     ? $uid     : $database_user;
		$this->config['pass']    = $pwd     ? $pwd     : $database_password;
		$this->config['charset'] = $charset ? $charset : $database_connection_charset;
		$this->config['connection_method'] = (isset($database_connection_method) ? $database_connection_method : $connection_method);
		$this->_dbconnectionmethod = &$this->config['connection_method'];
		$this->config['table_prefix'] = ($prefix !== NULL) ? $prefix : $table_prefix;
		$this->initDataTypes();		
	}
		
		/**
		* @name:  initDataTypes
		* @desc:  called in the constructor to set up arrays containing the types
		*         of database fields that can be used with specific PHP types
		*/
	function initDataTypes()
	{
		$this->dataTypes['numeric'] = array (
			'INT',
			'INTEGER',
			'TINYINT',
			'BOOLEAN',
			'DECIMAL',
			'DEC',
			'NUMERIC',
			'FLOAT',
			'DOUBLE PRECISION',
			'REAL',
			'SMALLINT',
			'MEDIUMINT',
			'BIGINT',
			'BIT'
		);
		$this->dataTypes['string'] = array (
			'CHAR',
			'VARCHAR',
			'BINARY',
			'VARBINARY',
			'TINYBLOB',
			'BLOB',
			'MEDIUMBLOB',
			'LONGBLOB',
			'TINYTEXT',
			'TEXT',
			'MEDIUMTEXT',
			'LONGTEXT',
			'ENUM',
			'SET'
		);
		$this->dataTypes['date'] = array (
			'DATE',
			'DATETIME',
			'TIMESTAMP',
			'TIME',
			'YEAR'
		);
	}

	/**
	 * @name:  setDatabaseType
	 * @desc:  Set up Database type(default mysql)
	 *         For the moment, only MySQL can be set up.
	 */
	function setDatabaseType($dbtype)
	{
		if( $dbtype == 'mysql' ){
			$this->dbtype = $dbtype;
		}
	}
		
	/**
	* @name:  connect
	*
	*/
	function connect($host = '', $dbase = '', $uid = '', $pwd = '', $persist = 0)
	{
		global $modx;
		if(!$uid)   $uid   = $this->config['user'];
		if(!$pwd)   $pwd   = $this->config['pass'];
		if(!$host)  $host  = $this->config['host'];
		if(!$dbase) $dbase = $this->config['dbase'];
		$dbase = str_replace('`', '', $dbase); // remove the `` chars
		$tstart = $modx->getMicroTime();
		$safe_count = 0;
		$errmsg='';

		while(!$this->pdo && $safe_count<3)
		{
			try {
				// MySQLƒT[ƒo‚ÖÚ‘±
				$this->pdo = new PDO("{$this->dbtype}:host={$host}; dbname={$dbase}",$uid, $pwd,
														 array(
																	 PDO::MYSQL_ATTR_INIT_COMMAND => "{$this->config['connection_method']} {$this->config['charset']}"
																	 )
														 );
			} catch(PDOException $e){
				sleep(3);
				$safe_count++;
				$errmsg.=($e->getMessage());
				$this->pdo = null;
			}
		}

		if( $this->pdo == null)
		{
			$modx->messageQuit('Failed to create the database connection!');
			exit;
		}

		if($errmsg != '')
		{
			$request_uri = $_SERVER['REQUEST_URI'];
			$request_uri = htmlspecialchars($request_uri, ENT_QUOTES);
			$ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES);
			$referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES);
			$logtitle = 'Became an error several times at DB connection. ';
			$msg = "{$logtitle}<br />{$request_uri}<br />{$ua}<br />{$referer}<br />[Error Message]{$errmsg}";
			$modx->logEvent(0, 2,$msg,$logtitle);
		}
		else
		{
			$tend = $modx->getMicroTime();
			$totaltime = $tend - $tstart;
			if ($modx->dumpSQL)
			{
				$msg = sprintf("Database connection was created in %2.4f s", $totaltime);
				$modx->queryCode .= '<fieldset style="text-align:left;"><legend>Database connection</legend>' . "{$msg}</fieldset>";
			}
			$this->isConnected = true;
			// FIXME (Fixed by line below):
			// this->queryTime = this->queryTime + $totaltime;
			$modx->queryTime += $totaltime;
		}

	}
	
	/**
	* @name:  disconnect
	*
	*/
	function disconnect()
	{
		$this->pdo = null;
		$this->isConnected = false;
	}
	
	function escape($s)
	{
		if( empty($s) ) {return $s;}
		if ( $this->pdo == null )
		{
			$this->connect();
		}
		$s = substr($this->pdo->quote($s),1,-1);

		return $s;
	}
	
	/**
	* @name:  query
	* @desc:  Mainly for internal use.
	* Developers should use select, update, insert, delete where possible
	*/
	function query($sql)
	{
		global $modx;
		if ( $this->pdo == null )
		{
			$this->connect();
		}
		$tstart = $modx->getMicroTime();
		if (!$result = $this->pdo->query($sql) )
		{
			$modx->messageQuit('Execution of a query to the database failed - ' . $this->getLastError(), $sql);
		}
		else
		{
			$tend = $modx->getMicroTime();
			$totaltime = $tend - $tstart;
			$modx->queryTime = $modx->queryTime + $totaltime;
			if ($modx->dumpSQL)
			{
				$backtraces = debug_backtrace();
				$modx->queryCode .= '<fieldset style="text-align:left"><legend>Query ' . ++$this->executedQueries . " - " . sprintf("%2.4f s", $totaltime) . '</legend>' . $sql . '<br />src : ' . $backtraces[0]['file'] . '<br />line : ' . $backtraces[0]['line'] . '</fieldset>';
			}
			$modx->executedQueries = $modx->executedQueries + 1;
			return $result;
		}
	}
	
	/**
	* @name:  delete
	*
	*/
	function delete($from,$where='',$limit='')
	{
		if(!$from) return false;
		else
		{
			$table = $from;
			if($where != '') $where = "WHERE {$where}";
			if($limit != '') $limit = "LIMIT {$limit}";
			return $this->query("DELETE FROM {$table} {$where} {$limit}");
		}
	}
	
	/**
	* @name:  select
	*
	*/
	function select($fields = "*", $from = '', $where = '', $orderby = '', $limit = '')
	{
		if(!$from) return false;
		else
		{
			if($where !== '')   $where   = "WHERE {$where}";
			if($orderby !== '') $orderby = "ORDER BY {$orderby}";
			if($limit !== '')   $limit   = "LIMIT {$limit}";
			return $this->query("SELECT {$fields} FROM {$from} {$where} {$orderby} {$limit}");
		}
	}
	
	/**
	* @name:  update
	*
	*/
	function update($fields, $table, $where = '')
	{
		if(!$table) return false;
		else
		{
			if (!is_array($fields)) $pairs = $fields;
			else 
			{
				foreach ($fields as $key => $value)
				{
					$pair[] = "{$key}='{$value}'";
				}
				$pairs = join(',',$pair);
			}
			if($where != '') $where = "WHERE {$where}";
			return $this->query("UPDATE {$table} SET {$pairs} {$where}");
		}
	}
	
	/**
	* @name:  insert
	* @desc:  returns either last id inserted or the result from the query
	*/
	function insert($fields, $intotable, $fromfields = "*", $fromtable = '', $where = '', $limit = '')
	{
		if(!$intotable) return false;
		else
		{
			if (!is_array($fields)) $pairs = $fields;
			else
			{
				$keys = array_keys($fields);
				$keys = implode(',', $keys) ;
				$values = array_values($fields);
				$values = "'" . implode("','", $values) . "'";
				$pairs = "({$keys}) ";
				if(!$fromtable && $values) $pairs .= "VALUES({$values})";
				if ($fromtable)
				{
					if($where !== '') $where = "WHERE {$where}";
					if($limit !== '') $limit = "LIMIT {$limit}";
					$sql = "SELECT {$fromfields} FROM {$fromtable} {$where} {$limit}";
				}
			}
			$rt = $this->query("INSERT INTO {$intotable} {$pairs} {$sql}");
			$lid = $this->getInsertId();
			return $lid ? $lid : $rt;
		}
	}
	
	/**
	* @name:  getInsertId
	*
	*/
	function getInsertId($name=NULL)
	{
		if( empty($name) )
		{
			return $this->pdo->lastInsertId();
		}
		return $this->pdo->lastInsertId($name);
	}
	
	/**
	* @name:  getAffectedRows
	*
	*/
	function getAffectedRows($ds)
	{
		// same getRecordCount()
		return $this->getRecordCount($ds);
	}
	
	/**
	* @name:  getLastError
	*
	*/
	function getLastError($method='code')
	{
		if( $method == 'code' )
		{
			return $this->pdo->errorCode();
		}
		else
		{
			return $this->pdo->errorInfo();
		}
	}
	
	/**
	* @name:  getRecordCount
	*
	*/
	function getRecordCount($ds)
	{
		return (is_object($ds)) ? $ds->rowCount() : 0;
	}
	
	/**
	* @name:  getRow
	* @desc:  returns an array of column values
	* @param: $dsq - dataset
	*
	*/
	function getRow($ds, $mode = 'assoc')
	{
		if($ds)
		{
			switch($mode)
			{
				case 'assoc':return $ds->fetch(PDO::FETCH_ASSOC); break;
				case 'num'  :return $ds->fetch(PDO::FETCH_NUM); break;
				case 'both' :return $ds->fetch(PDO::FETCH_BOTH); break;
				default     :
					global $modx;
					$modx->messageQuit("Unknown get type ({$mode}) specified for fetchRow - must be empty, 'assoc', 'num' or 'both'.");
			}
		}
	}
	
	/**
	* @name:  getColumn
	* @desc:  returns an array of the values found on colun $name
	* @param: $dsq - dataset or query string
	*/
	function getColumn($name, $dsq)
	{
		if (!is_object($dsq)) $dsq = $this->query($dsq);
		if ($dsq)
		{
			$col = array ();
			while ($row = $this->getRow($dsq))
			{
				$col[] = $row[$name];
			}
			return $col;
		}
	}
	
	/**
	* @name:  getColumnNames
	* @desc:  returns an array containing the column $name
	* @param: $dsq - dataset or query string
	*/
	function getColumnNames($dsq)
	{
		if (!is_object($dsq)) $dsq = $this->query($dsq);
		if ($dsq)
		{
			$names = array ();
			$limit = $dsq->columnCount();
			for ($i = 0; $i < $limit; $i++)
			{
				$md=$dsq->getColumnMeta($i);
				$names[] = $md['name'];
			}
			return $names;
		}

	}
		
	/**
	* @name:  getValue
	* @desc:  returns the value from the first column in the set
	* @param: $dsq - dataset or query string
	*/
	function getValue($dsq)
	{
		if (!is_object($dsq)) $dsq = $this->query($dsq);
		if ($dsq)
		{
			$r = $this->getRow($dsq, 'num');
			return $r[0];
		}
	}
	
	/**
	* @name:  getXML
	* @desc:  returns an XML formay of the dataset $ds
	*/
	function getXML($dsq)
	{
		if (!is_object($dsq)) $dsq = $this->query($dsq);
		$xmldata = "<xml>\r\n<recordset>\r\n";
		while ($row = $this->getRow($dsq, 'both'))
		{
			$xmldata .= "<item>\r\n";
			for ($j = 0; $line = each($row); $j++)
			{
				if ($j % 2)
				{
					$xmldata .= "<{$line[0]}>{$line[1]}</{$line[0]}>\r\n";
				}
			}
			$xmldata .= "</item>\r\n";
		}
		$xmldata .= "</recordset>\r\n</xml>";
		return $xmldata;
	}
	
	/**
	* @name:  getTableMetaData
	* @desc:  returns an array of MySQL structure detail for each column of a
	*         table
	* @param: $table: the full name of the database table
	*/
	function getTableMetaData($table)
	{
		$metadata = false;
		if (!empty ($table))
		{
			$sql = "SHOW FIELDS FROM {$table}";
			if ($ds = $this->query($sql))
			{
				while ($row = $this->getRow($ds))
				{
					$fieldName = $row['Field'];
					$metadata[$fieldName] = $row;
				}
			}
		}
		return $metadata;
	}
	
	/**
	* @name:  prepareDate
	* @desc:  prepares a date in the proper format for specific database types
	*         given a UNIX timestamp
	* @param: $timestamp: a UNIX timestamp
	* @param: $fieldType: the type of field to format the date for
	*         (in MySQL, you have DATE, TIME, YEAR, and DATETIME)
	*/
	function prepareDate($timestamp, $fieldType = 'DATETIME')
	{
		$date = '';
		if (!$timestamp !== false && $timestamp > 0)
		{
			switch ($fieldType)
			{
				case 'DATE' :
				$date = date('Y-m-d', $timestamp);
				break;
				case 'TIME' :
				$date = date('H:i:s', $timestamp);
				break;
				case 'YEAR' :
				$date = date('Y', $timestamp);
				break;
				case 'DATETIME' :
				default :
				$date = date('Y-m-d H:i:s', $timestamp);
				break;
			}
		}
		return $date;
	}
	
	/**
	* @name:  getHTMLGrid
	* @param: $params: Data grid parameters
	*         columnHeaderClass
	*         tableClass
	*         itemClass
	*         altItemClass
	*         columnHeaderStyle
	*         tableStyle
	*         itemStyle
	*         altItemStyle
	*         columns
	*         fields
	*         colWidths
	*         colAligns
	*         colColors
	*         colTypes
	*         cellPadding
	*         cellSpacing
	*         header
	*         footer
	*         pageSize
	*         pagerLocation
	*         pagerClass
	*         pagerStyle
	*
	*/
	function getHTMLGrid($dsq, $params)
	{
		//The PDO version is not write. 
		return false;

		/* 
		global $base_path;
		if (!is_resource($dsq)) $dsq = $this->query($dsq);
		if ($dsq)
		{
			include_once (MODX_MANAGER_PATH . 'includes/controls/datagrid.class.php');
			$grd = new DataGrid('', $dsq);
			
			$grd->noRecordMsg = $params['noRecordMsg'];
			
			$grd->columnHeaderClass = $params['columnHeaderClass'];
			$grd->cssClass = $params['cssClass'];
			$grd->itemClass = $params['itemClass'];
			$grd->altItemClass = $params['altItemClass'];
			
			$grd->columnHeaderStyle = $params['columnHeaderStyle'];
			$grd->cssStyle = $params['cssStyle'];
			$grd->itemStyle = $params['itemStyle'];
			$grd->altItemStyle = $params['altItemStyle'];
			
			$grd->columns = $params['columns'];
			$grd->fields = $params['fields'];
			$grd->colWidths = $params['colWidths'];
			$grd->colAligns = $params['colAligns'];
			$grd->colColors = $params['colColors'];
			$grd->colTypes = $params['colTypes'];
			$grd->colWraps = $params['colWraps'];
			
			$grd->cellPadding = $params['cellPadding'];
			$grd->cellSpacing = $params['cellSpacing'];
			$grd->header = $params['header'];
			$grd->footer = $params['footer'];
			$grd->pageSize = $params['pageSize'];
			$grd->pagerLocation = $params['pagerLocation'];
			$grd->pagerClass = $params['pagerClass'];
			$grd->pagerStyle = $params['pagerStyle'];
			
			return $grd->render();
		}
		*/
	}
	
	/**
	* @name:  makeArray
	* @desc:  turns a recordset into a multidimensional array
	* @return: an array of row arrays from recordset, or empty array
	*          if the recordset was empty, returns false if no recordset
	*          was passed
	* @param: $rs Recordset to be packaged into an array
	*/
	function makeArray($rs='')
	{
		if(!$rs) return false;
		$rsArray = array();
		$qty = $this->getRecordCount($rs);
		for ($i = 0; $i < $qty; $i++)
		{
			$rsArray[] = $this->getRow($rs);
		}
		return $rsArray;
	}
	
	/**
	* @name	getVersion
	* @desc	returns a string containing the database server version
	*
	* @return string
	*/
	function getVersion()
	{
		return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
	}
}
