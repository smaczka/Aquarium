<?php
	function codeCrc(){
		return (137 * pow(date('H'), 2)) + (date('i') * pow((date('s')), 3)) + 1051;
	}

	//validate basic GET variables
	$action_get = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
	
	if (isset($action_get)){
		require_once "connection.php";
		
		$connection = @mysql_connect($host, $user, $password) or die('Brak połączenia z serwerem MySQL.<br />Błąd: '.mysql_error());
		
		//check db connection
		if (!$connection){
			$debug_string = date('Y-m-d H:i:s').'\t\tBrak połączenia z serwerem MySQL! Błąd: '.mysql_error();
			file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
		}else{
			$db = @mysql_select_db($databse, $connection);
			
			//check db selection
			if (!$db){
				$debug_string = date('Y-m-d H:i:s')."\t\tProblem z wyborem bazy danych! Błąd: ".mysql_error()."\n";
				file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
			}else{
				mysql_query("SET NAMES '$font'");
				
				//validate GET variables
				$note_get = filter_input(INPUT_GET, 'note', FILTER_SANITIZE_STRING);
				$period_get = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_STRING);
				
				//get device data
				if ($action_get == 'get_device'){
					$array = array();
					$iter = 0;
					
					//get device data by period query
					if ($period_get == 'last_day'){
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls WHERE datetime >= SUBTIME(NOW(),'24:00:00') ORDER BY id;");
					} else if ($period_get == 'last_week'){
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls WHERE datetime >= SUBTIME(NOW(),'168:00:00') ORDER BY id;");
					} else if ($period_get == 'last_month'){
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls WHERE datetime >= SUBTIME(NOW(),'744:00:00') ORDER BY id;");
					} else if ($period_get == 'last_year'){
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls WHERE datetime >= SUBTIME(NOW(),'8760:00:00') ORDER BY id;");
					} else if ($period_get == 'whole_period'){
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls ORDER BY id");
					} else {
						$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls WHERE datetime >= \"". substr($period_get, 0, 19)."\" AND datetime <= \"".substr($period_get, strlen($period_get)-19)."\" ORDER BY id;");
					}
					
					if (!$query_devices){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						while($result = mysql_fetch_array($query_devices)){
							$array['state'][$iter] = $result['state'];
							$array['timestamp'][$iter] = $result['datetime'];
							
							$iter++;
						}
						
						//if selected time window is empty...
						if (sizeof($array) == 0){
							//get all data from db
							$query_devices = mysql_query("SELECT controls.state, controls.datetime FROM controls ORDER BY id DESC LIMIT 1;") or die("Błąd w zapytaniu!");
						
							if (!$query_devices){
								$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
								file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
							}else{
								$result = mysql_fetch_row($query_devices);
								
								$array['state'][0] = $result[0];
								$array['state'][1] = $result[0];
								
								
								//calculate time diffrence
								$array['timestamp'][1] = date('Y-m-d H:i:s');
								
								if ($period_get == 'last_day'){
									$date_temp = new DateTime(date('Y-m-d H:i:s'));
									$date_temp->sub(new DateInterval('P1D'));
									
									$array['timestamp'][0] = $date_temp->format('Y-m-d H:i:s');
								}else if ($period_get == 'last_week'){
									$date_temp = new DateTime(date('Y-m-d H:i:s'));
									$date_temp->sub(new DateInterval('P7D'));
									
									$array['timestamp'][0] = $date_temp->format('Y-m-d H:i:s');
								}else if ($period_get == 'last_month'){
									$date_temp = new DateTime(date('Y-m-d H:i:s'));
									$date_temp->sub(new DateInterval('P1M'));
									
									$array['timestamp'][0] = $date_temp->format('Y-m-d H:i:s');
								}else if ($period_get == 'last_year'){
									$date_temp = new DateTime(date('Y-m-d H:i:s'));
									$date_temp->sub(new DateInterval('P1Y'));
									
									$array['timestamp'][0] = $date_temp->format('Y-m-d H:i:s');
								}
							}
						
						//if selected time window returns one row
						}else if (sizeof($array) == 1){
							$array['state'][$iter] = $array['state'][$iter - 1];
							$array['timestamp'][$iter] = date('Y-m-d H:i:s');
							
						//if selected time window returns more than one row
						}else{
							$array['state'][$iter] = $array['state'][$iter - 1];
							$array['timestamp'][$iter] = date('Y-m-d H:i:s');
						}
						
						echo json_encode($array);
					}
				
				//get device data
				}else if ($action_get == 'get_temperature'){
					$array = array();
					$array_test = array();
					$iter = 0;
					
					//get device data by period query
					if ($period_get == 'last_day'){
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature WHERE datetime >= SUBTIME(NOW(),'24:00:00') ORDER BY id;");
					} else if ($period_get == 'last_week'){
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature WHERE datetime >= SUBTIME(NOW(),'168:00:00') ORDER BY id;");
					} else if ($period_get == 'last_month'){
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature WHERE datetime >= SUBTIME(NOW(),'744:00:00') ORDER BY id;");
					} else if ($period_get == 'last_year'){
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature WHERE datetime >= SUBTIME(NOW(),'8760:00:00') ORDER BY id;");
					} else if ($period_get == 'whole_period'){
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature ORDER BY id;");
					} else {
						$query_devices = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature WHERE datetime >= \"". substr($period_get, 0, 19)."\" AND datetime <= \"".substr($period_get, strlen($period_get)-19)."\" ORDER BY id;");
					}
					
					if (!$query_devices){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						while($result = mysql_fetch_array($query_devices)){
							$array['temperature_1'][$iter] = $result['temperature_1'];
							$array['temperature_2'][$iter] = $result['temperature_2'];
							$array['timestamp'][$iter] = $result['datetime'];
							
							echo $array['temperature_1'][$iter].'<br>';
							$iter++;
						}
						
						$array['temperature_1'][$iter] = $array['temperature_1'][$iter - 1];
						$array['temperature_2'][$iter] = $array['temperature_2'][$iter - 1];
						$array['timestamp'][$iter] = date('Y-m-d H:i:s');
						
						$query_temperature = mysql_query("SELECT AVG(temperature_1), AVG(temperature_2), MAX(temperature_1), MAX(temperature_2), MIN(temperature_1), MIN(temperature_2), (SELECT datetime FROM temperature WHERE temperature_1 = (SELECT MAX(temperature_1) FROM temperature)) AS max_temp1_datetime, (SELECT datetime FROM temperature WHERE temperature_2 = (SELECT MAX(temperature_2) FROM temperature)) AS max_temp2_datetime, (SELECT datetime FROM temperature WHERE temperature_1 = (SELECT MIN(temperature_1) FROM temperature)) AS min_temp1_datetime, (SELECT datetime FROM temperature WHERE temperature_2 = (SELECT MIN(temperature_2) FROM temperature)) AS min_temp2_datetime FROM temperature;");
						
						if (!$query_temperature){
							$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							$row_temperature = mysql_fetch_row($query_temperature);
							
							if ($period_get == 'last_day'){
								$date = new DateTime(date('Y-m-d H:i:s'));
								$date->modify('-1 day');
								$temp_day = $date->format('Y-m-d H:i:s');
								
								array_unshift ($array['temperature_1'], $array['temperature_1'][1]);
								array_unshift ($array['temperature_2'], $array['temperature_2'][1]);
								array_unshift ($array['timestamp'], $temp_day);
							}else if ($period_get == 'last_week'){
								$date = new DateTime(date('Y-m-d H:i:s'));
								$date->modify('-7 day');
								$temp_day = $date->format('Y-m-d H:i:s');
								
								array_unshift ($array['temperature_1'], $array['temperature_1'][1]);
								array_unshift ($array['temperature_2'], $array['temperature_2'][1]);
								array_unshift ($array['timestamp'], $temp_day);
							}else if ($period_get == 'last_month'){
								$date = new DateTime(date('Y-m-d H:i:s'));
								$date->modify('-1 month');
								$temp_day = $date->format('Y-m-d H:i:s');
								
								array_unshift ($array['temperature_1'], $array['temperature_1'][1]);
								array_unshift ($array['temperature_2'], $array['temperature_2'][1]);
								array_unshift ($array['timestamp'], $temp_day);
							}else if ($period_get == 'last_year'){
								$date = new DateTime(date('Y-m-d H:i:s'));
								$date->modify('-1 year');
								$temp_day = $date->format('Y-m-d H:i:s');
								
								array_unshift ($array['temperature_1'], $array['temperature_1'][1]);
								array_unshift ($array['temperature_2'], $array['temperature_2'][1]);
								array_unshift ($array['timestamp'], $temp_day);
							}
							
							for ($i = 0; $i < count($row_temperature) - 4; $i++){
								array_push($array['temperature_1'], round($row_temperature[$i], 2));
							}
						}
						echo json_encode($array);
					}
					
				//get last temperature data
				}else if ($action == 'get_last_temperature'){
					$array = array();
					
					//get last temperature data query
					$query_temperature = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature ORDER BY datetime DESC LIMIT 1;");
					
					if (!$query_temperature){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						while($result = mysql_fetch_array($query_temperature)){
							$array['temperature_1'][0] = $result['temperature_1'];
							$array['temperature_2'][0] = $result['temperature_2'];
							
							if (substr($result['datetime'], 0, 10) == date('Y-m-d')){
								$array['timestamp'][0] = substr($result['datetime'], 11);
							} else {
								$array['timestamp'][0] = $result['datetime'];
							}
						}
						
						echo json_encode($array);
					}
					
				//get scheduler data
				}else if ($action_get == 'get_scheduler'){
					$array = array();
					$iter = 0;
					
					//get scheduler data query
					$query_scheduler = mysql_query("SELECT scheduler.id, scheduler.list_name, scheduler.device_action, scheduler.datetime, scheduler.execute_state FROM scheduler;");
					
					if (!$query_scheduler){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						while($result = mysql_fetch_array($query_scheduler)){
							$array['id'][$iter] = $result['id'];
							$array['list_name'][$iter] = $result['list_name'];
							$array['device_action'][$iter] = $result['device_action'];
							$array['datetime'][$iter] = $result['datetime'];
							$array['execute_state'][$iter] = $result['execute_state'];
							
							$iter++;
						}
						
						echo json_encode($array);
					}
				
				//change state of device
				}else if (($action_get == 'change_state') && ($note_get == 'web_page')){
					$message = 'change_state&Strona%20WWW&'.codeCrc();
					
					$array = array();
					
					/*udp response codes:
					1 - timestamp mismatch,  new row has not been added
					2 - cant create socket
					3 - cant enter sql data
					4 - row added succesfull
					*/
					$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
					
					//try to open socket
					if ($socket){
						socket_sendto($socket, $message, strlen($message), 0, $server_ip, $server_port);
						
						$start_timestamp = date('Y-m-d H:i:s');
						
						sleep(2);
						
						//get last state from db
						$query_devices = mysql_query("SELECT controls.state, controls.datetime, controls.ip, controls.note FROM controls ORDER BY id DESC LIMIT 1;") or die("Błąd w zapytaniu!");
						
						if(!$query_devices){
							$sql_error = mysql_error();
							$array['change_state'][0] =  3;
							$array['change_state_sql_error'][0] =  $sql_error;
							
							$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.$sql_error;
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							while($result = mysql_fetch_array($query_devices)){
								$array['state'][0] = $result['state'];
								$array['timestamp'][0] = $result['datetime'];
								$array['ip'][0] = $result['ip'];
								$array['note'][0] = $result['note'];
							}
							
							$array['note'][0] = str_replace("_", " ", $result['note']);
							$end_timestamp = $array['timestamp'][0];
							$end_timestamp = $start_date = date('Y-m-d H:i:s');
							$timestamp_diff = strtotime($end_timestamp) - strtotime($start_timestamp);
							
							if (($timestamp_diff > 3) || ($timestamp_diff < 2)){
								$array['change_state'][0] =  1;
								
								$debug_string = date('Y-m-d H:i:s').'\t\tNie zgadzają się znaczniki czasu!';
								file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
							}else{
								$array['change_state'][0] =  4;
							}
						}
					}else{
						$array['change_state'][0] =  2;
						$socket_error = socket_last_error($socket);
						
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę utworzyć gniazda! Błąd: '.$socket_error;
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}
					
					echo json_encode($array);
					
				//get last device state from cache file
				}else if (($action_get == 'get_state') && ($note_get == 'web_page')){
					$array = array();
					$last_state = explode(";", fread(fopen($last_state_file, "r"), filesize($last_state_file)));
					
					$array['state'][0] = $last_state[0];
					
					if (substr($last_state[1],0,10) == date('Y-m-d')){
						$array['timestamp'][0] = substr($last_state[1], 11);
					} else {
						$array['timestamp'][0] = $last_state[1];
					}
					
					$array['ip'][0] = $last_state[2];
					$array['note'][0] = str_replace("_", " ", $last_state[3]);
					
					echo json_encode($array);
				}
			}
			
			mysql_close($connection);
		}
	}
?>