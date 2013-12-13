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
* ● version - 1.5 (2013/12/13)
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

//error handler
set_error_handler(create_function('$p1, $p2, $p3, $p4', 'throw new ErrorException($p2, 0, $p1, $p3, $p4);'), E_ALL);

Class MySQLL {
	private $connectObj = Array();
	private $objMySQL = Array();
	
	private $masterCnt = 0;
	private $slaveCnt = 0;
	
	private $startTime = null;
	private $endTime = null;
	private $queryLog = Array();
	
	private $phpVersion = null;
	private $queryErrorLogPath = 'your log path /query_log/';
	
	/**
     * Construct Class
     *
     * @param Array $dbInfo reference DbConnect info
     * @param Array $config reference Class config info
     *
     * @return void
     */
	public function __construct () {
		#-> DB config
		require_once '/MySQLL/config/setup.MySQLL.php';	
	
		$this->phpVersion = explode('.', phpversion());
		
		$this->connectObj['dbconnect'] = $dbInfo;
		$this->connectObj['config'] = $config;
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
	public function selectDbHost () {
		$tmpDbObj = $this->connect();
		
		if (!is_array($tmpDbObj)) {
			$this->objMySQL['read'] = null;
			$this->objMySQL['write'] = null;
		} else {
			$tmpCheck = Array();
			
			$phpChk = ( ($this->connectObj['config']['mysqlClassType'] == 'mysqli' && $this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) ? true : false );
		
			$actArray = Array('read', 'write');
			
			$actArrayCnt = Array();
			$actArrayCnt['read'] = $this->slaveCnt;
			$actArrayCnt['write'] = $this->masterCnt;
			
			for ($k=0; $k<count($actArray); $k++) {
				for ($i=0; $i<$actArrayCnt[$actArray[$k]]; $i++) {
					$tmpCheck[$actArray[$k]][$i] = explode("  ", ( ($phpChk) ? $tmpDbObj[$actArray[$k]][$i]->stat() : ($this->connectObj['config']['mysqlClassType'] == 'mysqli') ? mysqli_stat($tmpDbObj[$actArray[$k]][$i]) : mysql_stat($tmpDbObj[$actArray[$k]][$i]) ));
					$tmpCheck[$actArray[$k]][$i][7] = explode(": ", $tmpCheck[$actArray[$k]][$i][7]);
					$tmpCheck[$actArray[$k]][$i]['time'][1] = $i;
					$tmpCheck[$actArray[$k]][$i]['time'][0] = $tmpCheck[$actArray[$k]][$i][7][1];
				}
			}
			
			unset($actArrayCnt);
			
			unset($actArray);
	
			$writeObj = $this->quickSort($tmpCheck['write']);
	
			if ($this->slaveCnt == 0) {
				$readObj = &$writeObj;
			}  else {
				$readObj = $this->quickSort($tmpCheck['read']);
			}

			unset($tmpCheck);
			
			$this->objMySQL['read'] = $tmpDbObj['read'][$readObj[0][1]];
			$this->objMySQL['write'] = $tmpDbObj['write'][$writeObj[0][1]];	

			unset($writeObj);
			unset($readObj);
			unset($tmpDbObj);
		}
	}

	/**
     * DB composition switch 
     *
     * @return dbObj
     */
	private function connect () {
		$dbObj = Array();
		
		$this->masterCnt = count($this->connectObj['dbconnect']['master']);
		$this->slaveCnt = (array_key_exists('slave', $this->connectObj['dbconnect'])) ? count($this->connectObj['dbconnect']['slave']) : 0;
		
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
				break;
		}
		
		$writeCheck = false;
		$readCheck = false;
		
		foreach ($dbObj['write'] as $key => $var) {
			if (!$dbObj['write'][$key]) {
				unset($dbObj['write'][$key]);
			} else {
				$writeCheck = true;
			}
		}
		
		foreach ($dbObj['read'] as $key => $var) {
			if (!$dbObj['read'][$key]) {
				unset($dbObj['read'][$key]);
			} else {
				$readCheck = true;
			}
		}
		
		$this->masterCnt = count($dbObj['write']);
		$this->slaveCnt = count($dbObj['read']);
		
		if (!$writeCheck && !$readCheck) {
			return null;
		} else {
			if ($writeCheck && !$readCheck) {
				$dbObj['read'] = &$dbObj['write'];
			} else if (!$writeCheck && $readCheck) {
				$dbObj['write'] = &$dbObj['read'];
			}
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
		
		try {
			switch ($config['mysqlClassType']) {
				case 'mysql' :
					if (!$config['connectionPool']) {
						$dbObj = mysql_connect(
									$dbInfo['host'].':'.$dbInfo['sock'], 
									$dbInfo['user'], 
									$dbInfo['pass']
									) or $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'The connection to the server has failed!');
						
						mysql_select_db($dbInfo['db'], $dbObj) or $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'Data base select failed!');
						$this->charsetProcess($dbObj, $config['encoding'], 'mysql');
						
						break;
					}
					
				case 'mysqlp' :
					$dbObj = mysql_pconnect(
								$dbInfo['host'].':'.$dbInfo['sock'], 
								$dbInfo['user'], 
								$dbInfo['pass']
								) or $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'The connection to the server has failed!');
								
					mysql_select_db($dbInfo['db'], $dbObj) or $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'Data base select failed!');
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
							return $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'The connection to the server has failed! (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
						}
					} else {
						$dbObj = mysqli_connect(
									$dbInfo['host'], 
									$dbInfo['user'], 
									$dbInfo['pass'], 
									$dbInfo['db'],
									$dbInfo['port'], 
									$dbInfo['sock']
									);
						
						if ($errorCode = mysqli_connect_errno($dbObj)) {
							return $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'The connection to the server has failed! ('.$errorCode.')');
						}
					}
					
					$this->charsetProcess($dbObj, $config['encoding'], 'mysqli');
					break;
			}
		} catch (Exception $e) {
			return null;
		}
		
		if ($dbObj) {
			return $dbObj;
		} else {
			return $this->saveErrorQueryLog($dbInfo['host'], $config['mysqlClassType'].' connect', 'The connection to the server has failed!');
		}
	}
	
	/**
	 * DB selectDb
	 *
	 * @param dbName $dbName DB name
	 *
	 * @return void
	 */
	public function selectDb ($dbName) {
		if (!$this->objMySQL['read'] || !$this->objMySQL['write']) {
			return $this->saveErrorQueryLog('all DB', 'select db', 'Data Object is null');;
		}
		
		switch ($this->connectObj['config']['mysqlClassType']) {
			case 'mysql' :
			case 'mysqlp' :
				mysql_select_db($dbName, $this->objMySQL['read']) or $this->saveErrorQueryLog($this->objMySQL['read']['host'], 'select db', 'Data base select failed!');
				mysql_select_db($dbName, $this->objMySQL['write']) or $this->saveErrorQueryLog($this->objMySQL['write']['host'], 'select db', 'Data base select failed!');
				break;
		
			case 'mysqli' :
				if ($this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) {
					$this->objMySQL['read']->select_db($dbName);
					$this->objMySQL['write']->select_db($dbName);
				} else {
					mysqli_select_db($this->objMySQL['read'], $dbName);
					mysqli_select_db($this->objMySQL['write'], $dbName);
				}
				break;
		}
	}
	
	/**
     * DB charsetProcess
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

		unset($methodType);
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
	private function dbReturn_MySQLError ($print, &$mysqlObj) {
		if (!$mysqlObj) {
			return false;
		}
		
		$verChk = ($this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) ? true : false;
		
		if ($print) {
			print "<pre>";
        	print_r(($this->connectObj['config']['mysqlClassType'] == 'mysqli') 
					? (($verChk) ? $mysqlObj->error : mysqli_error($mysqlObj))
					: (($verChk) ? $mysqlObj->error : mysql_error($mysqlObj)));
			print "</pre>";
		} else {
			return ($this->connectObj['config']['mysqlClassType'] == 'mysqli') 
					? (($verChk) ? $mysqlObj->error : mysqli_error($mysqlObj))
					: (($verChk) ? $mysqlObj->error : mysql_error($mysqlObj));
		}
	}
	
	/**
	 * MySQL next result check
	 *
	 * @param dbObj $mysqlObj
	 *
	 * @return void
	 */
	private function dbReturn_NextResult (&$mysqlObj) {
		if (!$mysqlObj) {
			return false;
		}
	
		$verChk = ($this->phpVersion[0] >= 5 && $this->phpVersion[1] >= 3) ? true : false;
	
		if ($verChk) {
			if ($mysqlObj->more_results()) {
				$mysqlObj->next_result();
			}
		} else {
			if ($mysqlObj->more_results()) {
				mysqli_next_results($mysqlObj);
			}
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
		if (!$dbObj) {
			return Array('dbConnectError' => 'The connection to the server has failed!');
		}
		
		if ($this->connectObj['config']['queryDebug']) {
			$queryTime = explode(' ', microtime());
			$queryTime = $queryTime[0] + $queryTime[1];
	
			$this->startTime = $queryTime;

			unset($queryTime);
		}
	
		$result = null;
	
		if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') { $result = mysqli_query($dbObj, $sql); }
		else { $result = mysql_query($sql, $dbObj); }
	
		if ($this->connectObj['config']['queryDebug']) {
			$queryTime = explode(' ', microtime());
			$queryTime = $queryTime[0] + $queryTime[1];
	
			$this->endTime = $queryTime;

			unset($queryTime);
	
			$timeName = explode(' ', $sql);
			$timeName = trim($timeName[0]);
			
			$serverName = ($dbObj != null && is_a($dbObj, $this->connectObj['config']['mysqlClassType']) && property_exists($this->connectObj['config']['mysqlClassType'], 'host_info')) ? $dbObj->host_info.' : ' : '';
			$this->queryLog[] = $serverName.$timeName." : (".substr($this->endTime-$this->startTime, 0, 6)." sec) ".$sql;

			unset($timeName);
			unset($serverName);
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
	
		unset($where);
		unset($group);
		unset($order);

		$result = $this->resultReturn($sql, $this->objMySQL['read']);

		unset($sql);
		
		if (is_array($result) && array_key_exists('dbConnectError', $result)) {
			return $result;
		}
		
		if (!$result) {
			return $this->dbReturn_MySQLError(false, $this->objMySQL['read']);
		}

		$returnRows = null;	
		$rows = null;

		if ($multiple == false) {
			switch ($type) {
				case 'Assoc' : $rows = $this->dbReturn_fetchAssoc($result); break;
				case 'Field' : $rows = $this->dbReturn_fetchField($result); break;
				case 'Rows'  : $rows = $this->dbReturn_numRows($result);    break;
				default      : $rows = $this->dbReturn_fetchAssoc($result); break;
			}

			$returnRows = $rows;
		} else {
			$returnRows = Array();
			
			switch ($type) {
				case 'Assoc' : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
				case 'Field' : while ($rows = $this->dbReturn_fetchField($result)) $returnRows[] = $rows; break;
				default      : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
			}
		}

		unset($rows);
	
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
	function callStoredProc ($spName, $type=null, $vars=null, $multiple=false, $output=false, $outResult=null, $input=false, $select=false) {
		$runDbObj = (($select == true) ? $this->objMySQL['read'] : $this->objMySQL['write']);
		//print_r($runDbObj);
		if (is_array($vars)) {
			$vars = implode(', ', $vars);
			$sql  = 'call ' . $spName . '('.$vars.')';
		} else {
			if ($vars == null) { $sql = 'call ' . $spName . '()'; }
			else { $sql = 'call ' . $spName . '('.$vars.')'; }
		}
	
		$returnRows = null;
		
		if ($output) {
			$result = $this->resultReturn($sql, $runDbObj);

			unset($sql);
	
			if (is_array($result) && array_key_exists('dbConnectError', $result)) {
				return $result;
			}
			
			if (!is_a($result, $this->connectObj['config']['mysqlClassType'].'_result') || $this->dbReturn_MySQLError(false, $runDbObj)) {
				return $this->dbReturn_MySQLError(false, $runDbObj);
			}

			$returnRows = null;
			$rows = null;

			if (!$multiple) {
				switch ($type) {
					case 'Assoc' : $rows = $this->dbReturn_fetchAssoc($result); break;
					case 'Field' : $rows = $this->dbReturn_fetchField($result); break;
					case 'Rows'  : $rows = $this->dbReturn_numRows($result);    break;
					default      : $rows = $this->dbReturn_fetchAssoc($result); break;
				}
	
				$returnRows = $rows;
			} else {
				$returnRows = Array();

				switch ($type) {
					case 'Assoc' : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
					case 'Field' : while ($rows = $this->dbReturn_fetchField($result)) $returnRows[] = $rows; break;
					default      : while ($rows = $this->dbReturn_fetchAssoc($result)) $returnRows[] = $rows; break;
				}
			}

			unset($rows);
	
			$this->dbReturn_NextResult($runDbObj);
	
			if ($outResult != null) {
				$sql    = 'SELECT ' . $outResult;
				$result =  $this->resultReturn($sql, $runDbObj);

				unset($sql);
				
				if (is_array($result) && array_key_exists('dbConnectError', $result)) {
					return $result;
				}
				
				if (!is_a($result, $this->connectObj['config']['mysqlClassType'].'_result') || $this->dbReturn_MySQLError(false, $runDbObj)) {
					return $this->dbReturn_MySQLError(false, $runDbObj);
				}

				unset($result);
	
				$this->dbReturn_NextResult($runDbObj);
			}
	
			return $returnRows;
		} else {
			if ($this->connectObj['config']['composition'] == 's') {
				$result = $this->resultReturn($this->objMySQL['read']);

				unset($result);

				$this->dbReturn_NextResult($this->objMySQL['read']);
			}
			else {
				$actDb = ($input != true) ? $this->objMySQL['read'] : $this->objMySQL['write'];
	
				$result = $this->resultReturn($sql, $actDb);
				$this->dbReturn_NextResult($actDb);

				unset($sql);
				unset($actDb);
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

        unset($where);
        unset($group);

        $result = $this->resultReturn($sql, $this->objMySQL['read']);

        unset($sql);

        if (is_array($result) && array_key_exists('dbConnectError', $result)) {
        	return $result;
        }
        
		if (!$result) {
			return $this->dbReturn_MySQLError(false, $this->objMySQL['read']);
		}

		$returnRows = null;
		$row = null;

        if ($group)  { $returnRows = Array(); while($row = $this->dbReturn_fetchRow($result)) { $returnRows[] = $row; } }
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

        unset($where);

        $result = $this->resultReturn($sql, $this->objMySQL['read']);

        unset($sql);
        
        if (is_array($result) && array_key_exists('dbConnectError', $result)) {
        	return $result;
        }
        
		if (!$result) {
			return $this->dbReturn_MySQLError(false, $this->objMySQL['read']);
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

		$result = $this->resultReturn($sql, $actDb);

		unset($sql);
		
		if (is_array($result) && array_key_exists('dbConnectError', $result)) {
			return $result;
		}
		
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
        return $this->resultReturn('update ' . $table . ' set ' . $values . ' where ' . $where, (($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write']));
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

        return $this->resultReturn('delete from ' . $table . $where, (($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write']));
    }
    
    /**
     * createTable
     *
     * @param String $tableName table name
     * @param Array $fields
     * @param Array $dataTypes
     * @param Array $dataSizes
     *
     * @return int or boolean
     */
    function createTable ($tableName, $fields, $dataTypes, $dataSizes) {
    	if (is_array($fields) && is_array($dataTypes) && is_array($dataSizes)) {
    		$createTable = Array();
    		
    		$dataSizeNone = Array('timestamp', 'text');
    		
    		foreach ($fields as $key => $var) {
    			if (!$fields[$key] ||  $fields[$key] == '') {
    				continue;
    			}
    			
    			$fields[$key] = trim($fields[$key]);
    			$dataTypes[$key] = trim($dataTypes[$key]);
    			
    			$creataTable[] = $fields[$key].' '.$dataTypes[$key]. (in_array($dataTypes[$key], $dataSizeNone) ? ' ' : '('.$dataSizes[$key].')') . (($fields[$key] == 'id') ? ' NOT NULL AUTO_INCREMENT PRIMARY KEY' : (($dataTypes[$key] != 'enum') ? ' NULL' : ' '));
    		}
    		
    		if (count($creataTable) > 0) {
    			$creataTable = 'CREATE TABLE ' .$tableName. '(' . implode(',', $creataTable) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';
    		}
    		
    		$actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

    		unset($dataSizeNone);
    		
        	return $this->resultReturn($creataTable, $actDb);
    	}
    	
    	return false;
    }
    
    /**
     * alterTable
     *
     * @param String $type alter type
     * @param String $tableName table name
     * @param Array $fields
     * @param Array $dataTypes
     * @param Array $dataSizes
     *
     * @return int or boolean
     */
    function alterTable ($type, $tableName, $fieldName=null, $dataType=null, $dataSize=null, $fields=null) {
    	$actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

    	switch ($type) {
    		case 'RENAME' :
    			if (is_array($tableName) && array_key_exists('old', $tableName) && array_key_exists('new', $tableName)) {
    				return $this->resultReturn('ALTER TABLE '.$tableName['old'].' '.$type.' '.$tableName['new'].';', $actDb);
    			}
    			break;
    			
    		case 'CHANGE' :
    			if ($tableName && is_array($fieldName) && $dataType && $dataSize) {
    				return $this->resultReturn('ALTER TABLE '.$tableName.' '.$type.' '.$fieldName['old'].' '.$fieldName['new'].' '.$dataType.'('.$dataSize.');', $actDb);
    			}
    			
    			break;
    			
    		case 'ADD' :
    			if ($tableName && $fieldName && $dataType && $dataSize) {
    				return $this->resultReturn('ALTER TABLE '.$tableName.' '.$type.' '.$fieldName.' '.$dataType.'('.$dataSize.');', $actDb);
    			}
    			break;
    			
    		case 'ADD INDEX' :
    		case 'ADD UNIQUE INDEX' :
    			if ($tableName && $fieldName && $fields) { 
    				return $this->resultReturn('ALTER TABLE '.$tableName.' '.$type.' '.$fieldName.' ('.$fields.');', $actDb);
    			}
    			break;
    			
    		case 'DROP' :
    		case 'DROP INDEX' :
    			if ($tableName && $fieldName) {
    				return $this->resultReturn('ALTER TABLE '.$tableName.' '.$type.' '.$fieldName.';', $actDb);
    			}
    			break;
    			
    		case 'TRUNCATE' :
    			if ($tableName) {
    				return $this->resultReturn($type.' TABLE '.$tableName.';', $actDb);
    			}
    			break;
    			
    		case 'DROP TABLE' :
    			if ($tableName) {
    				return $this->resultReturn($type.' '.$tableName.';', $actDb);
    			}
    	}
    }

    /**
     * show table status
     *
     * @param String $table table name
     *
     * @return result set
     */
    function tableStatus ($table) {
        $result = $this->resultReturn("SHOW TABLE STATUS LIKE '" . $table . "'", $this->objMySQL['read']);
        
        if (is_array($result) && array_key_exists('dbConnectError', $result)) {
        	return $result;
        }
        
        if (!$result) {
        	return $this->dbReturn_MySQLError(false, $this->objMySQL['read']);
        }

        $returnRows = Array();
        $rows = null;

        while($rows = $this->dbReturn_fetchAssoc($result)) { $returnRows[] = $rows; unset($rows); }

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
        $result = $this->resultReturn("OPTIMIZE TABLE '" . $table . "'", $this->objMySQL['read']);
        
        if (is_array($result) && array_key_exists('dbConnectError', $result)) {
        	return $result;
        }
        
   	 	if (!$result) {
        	return $this->dbReturn_MySQLError(true, $this->objMySQL['read']);
        }
    }

    /**
     * lock table
     *
     * @param String $table table name
     * @param String $types lock type
     * @param String $option option
     *
     * @return void
     */
	function lockTable ($table, $types, $option) {
		$actDb = ($this->connectObj['config']['composition'] == 's') ? $this->objMySQL['read'] : $this->objMySQL['write'];

        $result = $this->resultReturn($types." TABLE ".$table.' '.$option, $actDb);
        
        if (is_array($result) && array_key_exists('dbConnectError', $result)) {
        	return $result;
        }
        
		if (!$result) {
        	return $this->dbReturn_MySQLError(false, $actDb);
        }

        unset($actDb);
        
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
     * save error query log
     *
     * @param string $server db serverInfo
     * @param string $type query type
     * @param string $query query string
     *
     * @return boolean
     */
    private function saveErrorQueryLog ($server, $type, $query) {
    	$fp = fopen($this->queryErrorLogPath.'error_query_log_'.date('Ymd').'.log', 'a');
    	$logMessage = $server. ' : ('.$type.') '. $query."\r\n";
    	fwrite($fp, $logMessage);
    	fclose($fp);
    	
    	unset($logMessage);
    	unset($fp);
    	
    	return false;
    }

    /**
     * MySQL Connect close
     *
     * @return void
     */
    function closeMySQL () {
    	if ($this->connectObj['config']['queryDebug']) {
    		$this->printQueryLog(true);
    	}
    	
    	$chekLength = 0;
    	 
        if ($this->connectObj['config']['mysqlClassType'] == 'mysqli') {
        	if ($this->objMySQL['read']) {
        		mysqli_close($this->objMySQL['read']);
        	}
        	
        	if ($this->objMySQL['write']) {
        		try {
        			$chekLength = strlen($this->objMySQL['write']->host_info);
        		} catch (Exception $e) {
        			$chekLength = 0;
        		}
        		
        		if ($this->connectObj['config']['composition'] != 's' && ($chekLength > 0)) { mysqli_close($this->objMySQL['write']); }
        	}
        }
        else {
        	if ($this->objMySQL['read']) {
        		mysqli_close($this->objMySQL['read']);
        	}
        	
        	if ($this->objMySQL['write']) {
        		try {
        			$chekLength = strlen($this->objMySQL['write']->host_info);
        		} catch (Exception $e) {
        			$chekLength = 0;
        		}
        		
        		if ($this->connectObj['config']['composition'] != 's' && ($chekLength > 0)) { mysql_close($this->objMySQL['write']); }
        	}
        }
    }
}

?>
