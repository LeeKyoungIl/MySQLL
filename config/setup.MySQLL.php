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


#-> dbConnect info - master
$dbInfo['master'][0]['host'] = '';
$dbInfo['master'][0]['user'] = '';
$dbInfo['master'][0]['pass'] = '';
$dbInfo['master'][0]['db'  ] = '';
$dbInfo['master'][0]['port'] = '';
$dbInfo['master'][0]['sock'] = ''; // /var/lib/mysql/mysql.sock

/*
$dbInfo['master'][1]['host'] = '';
$dbInfo['master'][1]['user'] = '';
$dbInfo['master'][1]['pass'] = '';
$dbInfo['master'][1]['db'  ] = '';
$dbInfo['master'][1]['port'] = '';
$dbInfo['master'][1]['sock'] = '';
*/

#-> dbConnect info - slave
$dbInfo['slave'][0]['host'] = '';
$dbInfo['slave'][0]['user'] = '';
$dbInfo['slave'][0]['pass'] = '';
$dbInfo['slave'][0]['db'  ] = '';
$dbInfo['slave'][0]['port'] = '';
$dbInfo['slave'][0]['sock'] = '';

/*
$dbInfo['slave'][1]['host'] = '';
$dbInfo['slave'][1]['user'] = '';
$dbInfo['slave'][1]['pass'] = '';
$dbInfo['slave'][1]['db'  ] = '';
$dbInfo['slave'][1]['port'] = '';
$dbInfo['slave'][1]['sock'] = '';
*/

#-> DB composition type
#
# s   : Single 
# sr  : Single Replication master(Write) -> slave(Read) (1 : N)
# dmr : Dual Replication master(Write) -> slave(Read) (N : N)
# om  : Only Master
$config['composition'] = 's';

#-> use stored procdure : true or false  
$config['storedPROC'] = false;

#-> MySQL class type
#
# mysql  : basic MySQL connect class 
# mysqlp : MySQL persistent connect class
# mysqli : Improved MySQL connect class
$config['mysqlClassType'] = 'mysqli';

#-> use connection pool : true or false
$config['connectionPool'] = false;

#-> db encoding 
$config['encoding'] = 'utf8';

#-> use query debug : true or false
$config['queryDebug'] = true;

?>
