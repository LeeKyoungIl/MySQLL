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

if ($ObjMySQLL) { 
	if ($config['queryDebug']) { 
		#-> query debug area
		print '<br><div id="query_debug_layer" style="display:block;width:100%;position:relative;height:auto;border:2px solid #ACACAC;"><br>';
		
		$queryLog = $ObjMySQLL->printQueryLog(false);
		
		for ($i=0; $i<count($queryLog); $i++) {
			print ($i+1) . '. '.$queryLog[$i] . '<br>';
		}
		
		print '<br></div><br>';
	}
	
	$ObjMySQLL->closeMySQL();
}
?>
