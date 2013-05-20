<?php

################################################################
#                                                              #
# ● CJ O Shopping E-commerce Development Team                  #
# ● Author - LeeKyoungIl / kyoungil_lee@cj.net / 02-2107-7270  #
#                                                              #
# ● Module Name - MySQL Connect Module                         #
#                                                              #
# ● 최소 사용 버전 - PHP 5.X 이상, MySQL 5.X 이상                    #
#                                                              #
# ● 용도 - DB 접속 종료 및 디버그 출력 파일                            #
#                                                              #
################################################################

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