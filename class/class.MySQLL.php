<?php

/**
* ● author - LeeKyoungIl / leekyoungil@gmail.com
* ● blog - http://blog.leekyoungil.com 
* ● github - https://github.com/LeeKyoungIl/MySQLL
* ● copyright - (c) 2013 - Lee Kyoung Il
* ● class name - MySQLL / Easy PHP-MySQL Connect Module   
*                       
* ● requires PHP 5.3.x and either MySQL 5.x                                                                              
*
* ● version - 0.1 (2013/05/20)
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
* 
*/


Class MySQLL {
	private $connectObj = Array();
	private $objMySQL = Array();
	
	private $masterCnt = 0;
	private $slaveCnt = 0;
	
	private $startTime = null;
	private $endTime = null;
	private $queryLog = Array();
	
	private $phpVersion = null;
	
	/**
     * Construct Class
     *
     * @param Array $dbInfo reference DbConnect info
     * @param Array $config reference Class config info
     *
     * @return void
     */
	public function __construct (&$dbInfo, &$config) {
		$this->phpVersion = explode('.', phpversion());
		
		$this->connectObj['dbconnect'] = $dbInfo;
		$this->connectObj['config'] = $config;
		
		$this->selectDbHost();
	}
	
	/**
	#############################################################################
	* Db Connection                                                             *
	#############################################################################
	*/
	
	/**
     * Quick Sort
     *
     * @param Array $sortObj original array
     *
     * @return Array
     */
	private function quickSort ($sortObj) {
		if (count($sortObj) == 0) {
			return $sortObj;
		}
	
		$high = $low = Array();
	
		$pivot = $sortObj[0]['time'][0];
		$loopCnt = count($sortObj);
	
		for ($i=1; $i<$loopCnt; $i++) {
			if ($sortObj[$i]['time'][0] <= $pivot) {
				$low[]['time'] = $sortObj[$i]['time'];
			} else {
				$high[]['time'] = $sortObj[$i]['time'];
			}
		}
	
		return array_merge($this->quickSort($low), array($sortObj[0]['time']), $this->quickSort($high));
	}
	
	/**
     * Select best condition Db Server (Read, Write)
     * 
     * @return void
     */
	private function selectDbHost () {
		$tmpDbObj = $this->connect();
		$tmpCheck = Array();
		
		$phpChk = ( ($this->connectObj['config']['mysqlClassType'] == 'mysqli' && $this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) ? true : false );
		$loopCnt = ($this->slaveCnt > $this->masterCnt) ? $this->slaveCnt : $this->masterCnt;
	
		for ($i=0; $i<$this->slaveCnt; $i++) {
			$tmpCheck['read'][$i] = explode("  ", ( ($phpChk) ? $tmpDbObj['read'][$i]->stat() : ($this->connectObj['config']['mysqlClassType'] == 'mysqli') ? mysqli_stat($tmpDbObj['read'][0]) : mysql_stat($tmpDbObj['read'][0]) ));
			$tmpCheck['read'][$i][7] = explode(": ", $tmpCheck['read'][$i][7]);
			$tmpCheck['read'][$i]['time'][1] = $i;
			$tmpCheck['read'][$i]['time'][0] = $tmpCheck['read'][$i][7][1];
		}
		
		for ($i=0; $i<$this->masterCnt; $i++) {
			$tmpCheck['write'][$i] = explode("  ", ( ($phpChk) ? $tmpDbObj['write'][$i]->stat() :  ($this->connectObj['config']['mysqlClassType'] == 'mysqli') ? mysqli_stat($tmpDbObj['write'][0]) : mysql_stat($tmpDbObj['write'][0]) ));
			$tmpCheck['write'][$i][7] = explode(": ", $tmpCheck['write'][$i][7]);
			$tmpCheck['write'][$i]['time'][1] = $i;
			$tmpCheck['write'][$i]['time'][0] = $tmpCheck['write'][$i][7][1];
		}

		$readObj = $this->quickSort($tmpCheck['read']);
		$writeObj = $this->quickSort($tmpCheck['write']);

		$this->objMySQL['read'] = $tmpDbObj['read'][$readObj[0][1]];
		$this->objMySQL['write'] = $tmpDbObj['write'][$writeObj[0][1]];	
	}

	/**
     * DB composition switch 
     *
     * @return dbObj
     */
	private function connect () {
		$dbObj = Array();
		
		$this->masterCnt = count($this->connectObj['dbconnect']['master']);
		$this->slaveCnt = count($this->connectObj['dbconnect']['slave']);
		
		switch ($this->connectObj['config']['composition']) {
			case 's' : 
				$dbObj['write'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][0]);
				$dbObj['read'][0] = &$dbObj['write'][0];
				break;
			
			case 'sr' :
				$dbObj['write'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][0]);
				
				if ($this->slaveCnt == 1) {
					$dbObj['read'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['slave'][0]);
				} else {
					for ($i=0; $i<$this->slaveCnt; $i++) {
						$dbObj['read'][$i] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['slave'][$i]);
					}
				}
				
				break;
				
			case 'dmr' :
				if ($this->masterCnt == 1) {
					$dbObj['write'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][0]);
				} else {
					for ($i=0; $i<$this->masterCnt; $i++) {
						$dbObj['write'][$i] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][$i]);
					}
				}
				
				if ($this->slaveCnt == 1) {
					$dbObj['read'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['slave'][0]);
				} else {
					for ($i=0; $i<$this->slaveCnt; $i++) {
						$dbObj['read'][$i] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['slave'][$i]);
					}
				}
				break;
				
			case 'om' :
				if ($this->masterCnt == 1) {
					$dbObj['write'][0] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][0]);
					$dbObj['read'][0] = &$dbObj['write'][0];
				} else {
					for ($i=0; $i<$this->masterCnt; $i++) {
						$dbObj['write'][$i] = $this->connectDb($this->connectObj['config'], $this->connectObj['dbconnect']['master'][$i]);
						$dbObj['read'][$i] = &$dbObj['write'][$i];
					}
				}
				
				$this->slaveCnt = $this->masterCnt;
				
				break;
		}
		
		return $dbObj;
	}
	
	/**
     * DB Connect
     *
     * @param Array $config DbConnect info
     * @param Array $dbInfo Class config info
     *
     * @return dbObj
     */
	private function connectDb ($config, $dbInfo) {
		$dbObj = null;
		
		switch ($config['mysqlClassType']) {
			case 'mysql' :
				if (!$config['connectionPool']) {
					$dbObj = mysql_connect(
								$dbInfo['host'].':'.$dbInfo['sock'], 
								$dbInfo['user'], 
								$dbInfo['pass']
								) or die('The connection to the server has failed!');
					
					mysql_select_db($dbInfo['db'], $dbObj) or die('Data base select failed!');
					$this->charsetProcess($dbObj, $config['encoding'], 'mysql');
					
					break;
				}
				
			case 'mysqlp' :
				$dbObj = mysql_pconnect(
							$dbInfo['host'].':'.$dbInfo['sock'], 
							$dbInfo['user'], 
							$dbInfo['pass']
							) or die('The connection to the server has failed!');
							
				mysql_select_db($dbInfo['db'], $dbObj) or die('Data base select failed!');
				$this->charsetProcess($dbObj, $config['encoding'], 'mysql');
				
				break;
				
			case 'mysqli' :
				if ($config['connectionPool']) {
					$dbInfo['host'] = 'p:'.$dbInfo['host'];
				}
				
				if ($this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) {
					$dbObj = new mysqli(
								$dbInfo['host'], 
								$dbInfo['user'], 
								$dbInfo['pass'], 
								$dbInfo['db'], 
								$dbInfo['port'], 
								$dbInfo['sock']
								);
					
					if (mysqli_connect_error()) {
						die('The connection to the server has failed! (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
					}
				} else {
					$dbObj = mysqli_connect(
								$dbInfo['host'], 
								$dbInfo['user'], 
								$dbInfo['pass'], 
								$dbInfo['db'],
								$dbInfo['port'], 
								$dbInfo['sock']
								) or die('The connection to the server has failed!');
					
					if ($errorCode = mysqli_connect_errno($dbObj)) {
						print 'The connection to the server has failed.<br>';
						print 'error code : '.$errorCode;
					
						exit;
					}
				}
				
				$this->charsetProcess($dbObj, $config['encoding'], 'mysqli');
				break;
		}
		
		if ($dbObj) {
			return $dbObj;
		} else {
			print 'The connection to the server has failed.<br>';		
			exit;
		}
	}
	
	/**
     * DB Connect
     *
     * @param dbObj $dbObj DB Object
     * @param String $dbCharset DB encoding 
     * @param String $type db Class type (mysqli, other)
     *
     * @return void
     */
	private function charsetProcess ($dbObj, $dbCharset, $type) {
		switch ($type) {
			case 'mysqli' : 
				mysqli_query($dbObj, 'SET NAMES ' . $dbCharset); 
				break;
				
			default : 
				mysql_query('SET NAMES ' . $dbCharset);          
				break;
		}
	}
	
	/**
     * The escape string on the would stop a possible SQL injection attack from working.
     *
     * @param dbObj $mDbObj master DB Object
     * @param String $classType db Class type (mysqli, other)
     * @param dbObj $sDbObj slave DB Object
     *
     * @return void
     */
	private function queryInjectionCheck ($mDbObj, $classType, $sDbObj=null) {
		$methodType = Array('_POST', '_GET');
		
		for ($i=0; $i<count($methodType); $i++) {
			if ($$methodType[$i]) {
				foreach ($_POST as $key => $val) {
					$$methodType[$i][$key] = ($classType == 'mysqli')
					? mysqli_real_escape_string($mDbObj, $val)
					: mysql_real_escape_string($val, $mDbObj);
			
					if ($sDbObj) {
						$$methodType[$i][$key] = ($classType == 'mysqli')
						? mysqli_real_escape_string($sDbObj, $val)
						: mysql_real_escape_string($val, $sDbObj);
					}
				}
			}
		}
	}
	
	/**
	#############################################################################
	* Db Query Util                                                             *
	#############################################################################
	*/
	
	/**
     * return to number or index
     *
     * @param dbObj $result result set
     *
     * @return result value
     */
	private function dbReturn_fetchArray ($result) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_fetch_array($result); }
		else { return mysql_fetch_array($result); }
	}
	
	/**
     * return associative array
     *
     * @param dbObj $result result set
     *
     * @return result value
     */
	private function dbReturn_fetchAssoc ($result) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_fetch_assoc($result); }
		else { return mysql_fetch_assoc($result); }
	}
	
	/**
     * return column info
     *
     * @param dbObj $result result set
     *
     * @return result value
     */
	private function dbReturn_fetchField ($result) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_fetch_field($result); }
		else { return mysql_fetch_field($result); }
	}
	
	/**
     * using for count and max
     *
     * @param dbObj $result result set
     *
     * @return result value
     */
	private function dbReturn_fetchRow ($result) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_fetch_row($result); }
		else { return mysql_fetch_row($result); }
	}
	
	/**
     * return num row
     *
     * @param dbObj $result result set
     *
     * @return result value
     */
	private function dbReturn_numRows ($result) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_num_rows($result); }
		else { return mysql_num_rows($result); }
	}
	
	/**
     * return auto_increment value
     *
     * @param dbObj $result result set
     *
     * @return int
     */
	private function dbReturn_InsertId ($dbObj) {
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { return mysqli_insert_id($dbObj); }
		else { return mysql_insert_id($dbObj); }
	}
	
	/**
     * print MySQL Query error message
     *
     * @param boolean $print error message direct print
     *
     * @return String or void
     */
	private function dbReturn_MySQLError ($print) {
		if ($print) {
			print "<pre>";
        	print_r(($this->connectObj['config']['mysqlClassType'] == 'mysqli') 
					? mysqli_error($this->objMySQL['read'])
					: mysql_error($this->objMySQL['read']));
			print "</pre>";
		} else {
			return ($this->connectObj['config']['mysqlClassType'] == 'mysqli') 
					? mysqli_error($this->objMySQL['read'])
					: mysql_error($this->objMySQL['read']);
		}
	}
	
	/**
     * print MySQL Query error message
     *
     * @param String $sql sql query
     * @param dbObj $dbObj DB Object
     *
     * @return result set
     */
	private function resultReturn ($sql, $dbObj) {
		if ($this->connectObj['config']['queryDebug']) {
			$queryTime = explode(' ', microtime());
			$queryTime = $queryTime[0] + $queryTime[1];
	
			$this->startTime = $queryTime;
		}
	
		$result = null;
	
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { $result = mysqli_query($dbObj, $sql); }
		else { $result = mysql_query($sql, $dbObj); }
	
		if ($this->connectObj['config']['queryDebug']) {
			$queryTime = explode(' ', microtime());
			$queryTime = $queryTime[0] + $queryTime[1];
	
			$this->endTime = $queryTime;
	
			$timeName = explode(' ', $sql);
			$timeName = trim($timeName[0]);
	
			$this->queryLog[] = $timeName." : (".substr($this->endTime-$this->startTime, 0, 6)." sec) ".$sql;
		}
	
		return $result;
	}
	
	/**
     * select query
     *
     * @param String $type select return type (Assoc, Field, Rows)
     * @param String $table table name
     * @param String $colums column 
     * @param String $where where query
     * @param String $group group by query
     * @param String $order order by query
     * @param String $etcoptions etc option query ( limit, having)
     * @param boolean $multiple select one result or multiple result
     *
     * @return result row
     */
	public function select ($type, $table, $colums, $where=false, $group=false, $order=false, $etcoptions=false, $multiple=false) {
		$where = ($where) ? ' where ' . $where : Null;
		$group = ($group) ? ' group by ' . $group : Null;
		$order = ($order) ? ' order by ' . $order : Null;
	
		$sql = 'select ' . $colums . ' from ' . $table . ' ' . $where . ' ' . $group . ' ' . $order . ' ' . $etcoptions;
	
		$result = $this->resultReturn($sql, $this->objMySQL['read']);
		
		if (!$result) {
			return $this->dbReturn_MySQLError(false);
		}

		$returnRows = null;	
		
		if ($multiple == false) {
			switch ($type) {
				case 'Assoc' : $rows = $this->dbReturn_fetchAssoc($result); break;
				case 'Field' : $rows = $this->dbReturn_fetchField($result); break;
				case 'Rows'  : $rows = $this->dbReturn_numRows($result);    break;
				default      : $rows = $this->dbReturn_fetchAssoc($result); break;
			}
			$returnRows = $rows;
		}
		else {
			$returnRows = Array();
			
			switch ($type) {
				case 'Assoc' : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
				case 'Field' : while ($rows = $this->dbReturn_fetchField($result)) $returnRows[] = $rows; break;
				default      : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
			}
		}
	
		return $returnRows;
	}
	
	/**
     * call stroed procedure query
     *
     * @param String $spName stored procedure name
     * @param String $type select return type (Assoc, Field, Rows)
     * @param String $vars parameter value
     * @param boolean $multiple select one result or multiple result
     * @param boolean $output whether setting ouput or not 
     * @param String $outResult result row
     * @param boolean $input whether insert procedure excutes or not
     *
     * @return result row
     */
	function callStoredProc ($spName, $type=null, $vars=null, $multiple=false, $output=false, $outResult=null, $input=false) {
		if (is_array($vars)) {
			$vars = implode(', ', $vars);
			$sql  = 'call ' . $spName . '('.$vars.')';
		}
		else {
			if ($vars == null) { $sql = 'call ' . $spName . '()'; }
			else { $sql = 'call ' . $spName . '('.$vars.')'; }
		}
	
		if ($output) {
			$result =  $this->resultReturn($sql, $this->objMySQL['read']);

			if (!$multiple) {
				switch ($type) {
					case 'Assoc' : $rows = $this->dbReturn_fetchAssoc($result); break;
					case 'Field' : $rows = $this->dbReturn_fetchField($result); break;
					case 'Rows'  : $rows = $this->dbReturn_numRows($result);    break;
					default      : $rows = $this->dbReturn_fetchAssoc($result); break;
				}
	
				$returnRows = $rows;
			}
			else {
				switch ($type) {
					case 'Assoc' : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
					case 'Field' : while ($rows = $this->dbReturn_fetchField($result)) $returnRows[] = $rows; break;
					default      : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
				}
			}
	
			mysqli_next_result($this->objMySQL['read']);
	
			if ($outResult != null) {
				unset($rows);
	
				$sql    = 'SELECT ' . $outResult;
				$result =  $this->resultReturn($sql, $this->objMySQL['read']);
	
				switch ($type) {
					case 'Assoc' : $rows = $this->dbReturn_fetchAssoc($result); break;
					case 'Field' : $rows = $this->dbReturn_fetchField($result); break;
					case 'Rows'  : $rows = $this->dbReturn_numRows($result);    break;
					default      : $rows = $this->dbReturn_fetchAssoc($result); break;
				}
	
				$returnRows['resultValue'] = $rows;
	
				mysqli_next_result($this->objMySQL['read']);
			}
	
			return $returnRows;
		}
		else {
			if ($this->connectObj['config']['composition'] == 's') {
				$result = $this->resultReturn($this->objMySQL['read']);
				mysqli_next_result($this->objMySQL['read']);
			}
			else {
				$actDb = ($input != true) ? $this->objMySQL['read'] : $this->objMySQL['write'];
	
				$result = $this->resultReturn($sql, $actDb);
				mysqli_next_result($actDb);
			}
	
			return $result;
		}
	}

	/**
     * column count query
     *
     * @param String $table table name
     * @param String $coloum column name
     * @param String $where where query
     * @param String $group group by query
     *
     * @return int
     */
    function count ($table, $coloum, $where, $group) {
        $where = ($where) ? ' where ' . $where : Null;
        $group = ($group) ? ' group by ' . $group : Null;
        $sql   = 'select count('. $coloum .') from ' . $table . ' '. $where . $group;

        $result = $this->resultReturn($sql, $this->objMySQL['read']);

		if (!$result) {
			return $this->dbReturn_MySQLError(false);
		}

        if ($group)  { while($row = $this->dbReturn_fetchRow($result)) { $returnRows[] = $row; } }
        else { $row = $this->dbReturn_fetchRow($result); }

        return ($group) ? count($returnRows) : $row['0'];
    }

    /**
     * column max query
     *
     * @param String $table table name
     * @param String $coloum column name
     * @param String $where where query
     *
     * @return int
     */
    function max ($table, $coloum, $where) {
        $where = ($where) ? ' where ' . $where : Null;
        $sql = 'select max('. $coloum .') from ' . $table . ' '. $where;

        $result = $this->resultReturn($sql, $this->objMySQL['read']);
        
		if (!$result) {
			return $this->dbReturn_MySQLError(false);
		}

		$row = $this->dbReturn_fetchRow($result);

        return $row['0'];
    }

    /**
     * insert query
     *
     * @param String $table table name
     * @param String $values insert value
     *
     * @return int
     */
    function insert ($table, $values) {
        $sql = 'insert into ' . $table . ' set ' . $values;

		$actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

		$this->resultReturn($sql, $actDb);
		
		return $this->dbReturn_InsertId($actDb);
    }

    /**
     * update query
     *
     * @param String $table table name
     * @param String $values insert value
     * @param String $where where query
     *
     * @return int
     */
    function update ($table, $values, $where) {
        $sql = 'update ' . $table . ' set ' . $values . ' where ' . $where;
        
        $actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

        return $this->resultReturn($sql, $actDb);
    }

    /**
     * delete query
     *
     * @param String $table table name
     * @param String $where where query
     *
     * @return int
     */
    function delete ($table, $where) {
        if ($where != null) { $where = ' where ' . $where; }

        $sql = 'delete from ' . $table . $where;

        $actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

        return $this->resultReturn($sql, $actDb);
    }

    /**
     * show table status
     *
     * @param String $table table name
     *
     * @return result set
     */
    function tableStatus ($table) {
        $sql = "SHOW TABLE STATUS LIKE '" . $table . "'";

        $result = $this->resultReturn($sql, $this->objMySQL['read']);
        
        if (!$result) {
        	return $this->dbReturn_MySQLError(false);
        }

        while($rows = $this->dbReturn_fetchAssoc($result)) { $returnRows[] = $rows; }

        return $returnRows;
    }

    /**
     * optimize table
     *
     * @param String $table table name
     *
     * @return void
     */
    function optimizerTable ($table) {
        $sql = "OPTIMIZE TABLE '" . $table . "'";

        $result = $this->resultReturn($sql, $this->objMySQL['read']);
        
   	 	if (!$result) {
        	$this->dbReturn_MySQLError(true);
        }
    }

	function lockTable ($table, $types, $option) {
		$sql = $types." TABLE ".$table.' '.$option;

		$actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

        $result = $this->resultReturn($sql, $actDb);
        
		if (!$result) {
        	return $this->dbReturn_MySQLError(false);;
        }
        
        return $result;
	}

	/**
     * print query debug
     *
     * @param boolean $print error message direct print
     *
     * @return void or array
     */
    function printQueryLog ($print) { 
    	if ($print) {
    		print "<pre>";
    		print_r($this->queryLog);
    		print "</pre>";
    	} else {
    		return $this->queryLog;
    	}
		
    }

    /**
     * MySQL Connect close
     *
     * @return void
     */
    function closeMySQL ()
    { 
        if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') {
        	mysqli_close($this->objMySQL['read']);
        	if ($this->connectObj['config']['composition'] != 's') { mysqli_close($this->objMySQL['write']); }
        }
        else {
        	mysql_close($this->objMySQL['read']);
        	if ($this->connectObj['config']['composition'] != 's') { mysql_close($this->objMySQL['write']); }
        }
    }
}

?>
