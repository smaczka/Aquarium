<?php
	require_once "connection.php";
	
	$message = 'change_state&scheduler';
	
	$connection = @mysql_connect($host, $user, $password);
	
	if (!$connection){
		$debug_string = date('Y-m-d H:i:s')."\t\tProblem z połączeniem z bazą danych! Błąd: ".mysql_error()."\n";
		file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
	}else{
		$db = mysql_select_db($databse, $connection);
		
		if (!$db){
			$debug_string = date('Y-m-d H:i:s')."\t\tProblem z wyborem bazy danych! Błąd: ".mysql_error()."\n";
			file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
		}else{
			mysql_query("SET NAMES '$font'");
			
			$array_scheduler = array();
			$iter = 0;
			
			$query_scheduler = mysql_query("SELECT scheduler.id, scheduler.device_action, scheduler.datetime, scheduler.execute_state FROM scheduler ORDER BY id;");
			
			if (!$query_scheduler){
				$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
				file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
			}else{
				while($result_scheduler = mysql_fetch_array($query_scheduler)){
					$array_scheduler['id'][$iter] = $result_scheduler['id'];
					$array_scheduler['device_action'][$iter] = $result_scheduler['device_action'];
					$array_scheduler['datetime'][$iter] = $result_scheduler['datetime'];
					$array_scheduler['execute_state'][$iter] = $result_scheduler['execute_state'];
					
					if(preg_match('/[0-1]{7}\s[0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $array_scheduler['datetime'][$iter])){
						if (substr($array_scheduler['datetime'][$iter], date(N), 1) == "1"){
							if ((time() >= strtotime(substr($array_scheduler['datetime'][$iter], 8))) && (substr($array_scheduler['execute_state'][$iter], date(N), 1) == "0")){
								$query_state = mysql_query("SELECT MAX(id), state FROM filter_controls;");
								
								if (!$query_state){
									$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
									file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
								}else{
									$device_state = mysql_fetch_row($query_state);
									
									if ($array_scheduler['device_action'][$iter] != $device_state[1]){
										$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
										
										if ($socket !== false){
											socket_sendto($socket, $message, strlen($message), 0, $server_ip, $server_port);
										}else{
											$debug_string = date('Y-m-d H:i:s')."\t\tNie mogę utworzyć gniazda! Błąd: ".socket_strerror(socket_last_error())."\n";
											file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
										}
										
										$temp_string = substr_replace($array_scheduler['execute_state'][$iter], "1", date(N), 1);
										
										$query_execute_state = mysql_query("UPDATE scheduler SET execute_state = '$temp_string' WHERE id = " . $array_scheduler['id'][$iter]);
										
										if (!$query_execute_state){
											$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
											file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
										}
										
										if (substr($array_scheduler['datetime'][$iter], 0, 7) == $array_scheduler['execute_state'][$iter]){
											$query_execute_state = mysql_query("UPDATE scheduler SET execute_state = '00000000' WHERE id = " . $array_scheduler['id'][$iter]);
											
											if (!$query_execute_state){
												$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
												file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
											}
										}
									}
								}
							}
						}
					}else if ((preg_match('/[2][0][0-9]{2}-[0-1][0-9]-[0-3][0-9]\s[0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $array_scheduler['datetime'][$iter])) && ($array_scheduler['execute_state'][$iter] == '0')){
						$query_state = mysql_query("SELECT MAX(id), state FROM filter_controls;");
						
						if (!$query_state){
							$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							$device_state = mysql_fetch_row($query_state);
							
							if ((strtotime(date('Y-m-d H:i:s')) >= strtotime($array_scheduler['datetime'][$iter])) && ($array_scheduler['device_action'][$iter] != $device_state[1])){
								$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
								
								if ($socket !== false){
									socket_sendto($socket, $message, strlen($message), 0, $server_ip, $server_port);
									$query_execute_state = mysql_query("UPDATE scheduler SET execute_state = '1' WHERE id = " . $array_scheduler['id'][$iter]);
									
									if (!$query_execute_state){
										$debug_string = date('Y-m-d H:i:s')."\t\tProblem z zapytaniem do bazy danych! Błąd: ".mysql_error()."\n";
										file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
									}
								}else{
									$debug_string = date('Y-m-d H:i:s')."\t\tNie mogę utworzyć gniazda! Błąd: ".socket_strerror(socket_last_error())."\n";
									file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
								}
							}
						}
					}
					
					$iter++;
				}
			}
		}
		
		mysql_close($connection); 
	}
?>