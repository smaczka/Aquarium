<?php
	$action_get = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
	$id_get = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
	$request_id_get = filter_input(INPUT_GET, 'request_id', FILTER_SANITIZE_NUMBER_INT);
	
	if (isset($action_get)){
		require_once "connection.php";
		include('../lib/php-pushover/Pushover.php');
		
		$push = new Pushover();
		$push->setToken('apegdi3cuqavjcmscsdya6kap2ymnm');
		$push->setUser('usye171nxcuvgmbppn1t6eyhe75z1k');
		
		$connection = @mysql_connect($host, $user, $password);
		
		if (!$connection){
			$debug_string = date('Y-m-d H:i:s').'\t\tBrak połączenia z serwerem MySQL! Błąd: '.mysql_error();
			file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
		}else{
			$db = @mysql_select_db($databse, $connection) or die('Nie mogę połączyć się z bazą danych<br />Błąd: '.mysql_error());
			
			if (!$db){
				$debug_string = date('Y-m-d H:i:s')."\t\tProblem z wyborem bazy danych! Błąd: ".mysql_error()."\n";
				file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
			}else{
				mysql_query("SET NAMES '$font'");
				
				if ($action_get == 'temperature'){
					$query_temperature = mysql_query("SELECT temperature.temperature_1, temperature.temperature_2, temperature.datetime FROM temperature ORDER BY datetime DESC LIMIT 1;");
					
					if (!$query_temperature){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę pobrać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						$temperature1_get = filter_input(INPUT_GET, 'temp1', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
						$temperature2_get = filter_input(INPUT_GET, 'temp2', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
						$note_get = filter_input(INPUT_GET, 'note', FILTER_SANITIZE_STRING);
						$request_id_get = filter_input(INPUT_GET, 'request_id', FILTER_SANITIZE_NUMBER_INT);
						
						if (!empty($_SERVER['HTTP_CLIENT_IP'])){
							$from_get = $_SERVER['HTTP_CLIENT_IP'];
						}else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
							$from_get = $_SERVER['HTTP_X_FORWARDED_FOR'];
						}else{
							$from_get = $_SERVER['REMOTE_ADDR'];
						}
						
						while($result = mysql_fetch_array($query_temperature)){
							$temperature_1 = $result['temperature_1'];
							$temperature_2 = $result['temperature_2'];
						}
						
						$query_temperature_add = mysql_query("INSERT INTO temperature (temperature_1, temperature_2, ip, note) VALUES ('".$temperature1_get."', '".$temperature2_get."', '".$from_get."', '".$note_get."')");
						
						if (!$query_temperature_add){
							$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę dodać rekordów! Błąd: '.mysql_error();
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							echo "Received request from: ".$from_get."\r\n";
							echo "Temperature #1: ".$temperature1_get."\r\n";
							echo "Temperature #2: ".$temperature2_get."\r\n";
							echo "Request note: ".$note_get."\r\n";
							echo "Request ".$request_id_get." has been added successfully!\r\n";
						
							
							if ($temperature_1 - $temperature1_get < 0){
								$temperature_1_value = $temperature_1 - $temperature1_get;
								
								$temperature_1_value = round(abs($temperature_1_value), 2);
								
								$temp_info = "\n"."Temperatura #1: ".$temperature1_get."°C (wzrost o ".$temperature_1_value." °C)";
							} else if ($temperature_1 - $temperature1_get > 0){
								$temperature_1_value = $temperature_1 - $temperature1_get;
								
								$temperature_1_value = round(abs($temperature_1_value), 2);
								
								$temp_info = "\n"."Temperatura #1: ".$temperature1_get."°C (spadek o ".$temperature_1_value." °C)";
							}else if ($temperature_1 - $temperature1_get == 0){
								$temp_info = "";
							}
							
							if ($temperature_2 - $temperature2_get < 0){
								$temperature_2_value = $temperature_2 - $temperature2_get;
								
								$temperature_2_value = round(abs($temperature_2_value), 2);
								
								$temp_info = $temp_info."\n"."Temperatura #2: ".$temperature2_get."°C (wzrost o ".$temperature_2_value." °C)";
							} else if ($temperature_2 - $temperature2_get > 0){
								$temperature_2_value = $temperature_2 - $temperature2_get;
								
								$temperature_2_value = round(abs($temperature_2_value), 2);
								
								$temp_info = $temp_info."\n"."Temperatura #2: ".$temperature2_get."°C (spadek o ".$temperature_2_value." °C)";
							}else if ($temperature_2 - $temperature2_get == 0){
								$temp_info = $temp_info."";
							}
							
							/*
							$push->setTitle('Akwarium');
							$push->setMessage("Nowa wartość temperatury!".$temp_info);
							$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
							$push->setUrlTitle('Sprawdź na stronie');

							$push->setDevice('xt1572');
							$push->setPriority(-1);
							$push->setCallback('http://szymonmaczka.2ap.pl/test/test.html');
							$push->setTimestamp(time());
							$push->setDebug(true);
							*/
						}
					}
				}else if ($action_get == 'change_state'){
					$state_get = filter_input(INPUT_GET, 'state', FILTER_SANITIZE_NUMBER_INT);
					$request_id_get = filter_input(INPUT_GET, 'request_id', FILTER_SANITIZE_NUMBER_INT);
					$note_get = filter_input(INPUT_GET, 'note', FILTER_SANITIZE_STRING);
					
					if (!empty($_SERVER['HTTP_CLIENT_IP'])){
						$from_get = $_SERVER['HTTP_CLIENT_IP'];
					}else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
						$from_get = $_SERVER['HTTP_X_FORWARDED_FOR'];
					}else{
						$from_get = $_SERVER['REMOTE_ADDR'];
					}
					
					$query_controls_add = mysql_query("INSERT INTO controls (state, ip, note) VALUES ('".$state_get."', '".$from_get."', '".$note_get."')");
					
					if (!$query_controls_add){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę dodać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						$last_state_file_curr = fopen($last_state_file, "w+");
						
						echo "Received request from: ".$from_get."\r\n";
						
						if ($state_get == 0){
							echo "Set state to OFF\r\n";
						}else if ($state_get == 1){
							echo "Set state to ON\r\n";
						}
						
						echo "Request note: ".$note_get."\r\n";
						echo "Request ".$request_id_get." has been added successfully!\r\n";
						
						if (!$last_state_file_curr){
							$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę otworzyć pliku last_state.txt!';
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							$values_file = $state_get.";".date('Y-m-d H:i:s').";".$from_get.";".$note_get;
							fwrite($last_state_file_curr, $values_file);
							fclose($last_state_file_curr);
							
							/*
							$push->setTitle('Akwarium');
							
							if ($state_get == 1){
								$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: wyłączony.");
							}else{
								$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: włączony.");
							}
							
							$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
							$push->setUrlTitle('Sprawdź na stronie');
							$push->setDevice('xt1572');
							$push->setPriority(-1);
							$push->setCallback('http://chris.schalenborgh.be/');
							$push->setTimestamp(time());
							$push->setDebug(true);
							*/
						}
					}
				}else if ((isset($id_get)) && ($action_get == 'scheduler')){
					$list_name_get = filter_input(INPUT_GET, 'list_name', FILTER_SANITIZE_STRING);
					$device_action_get = filter_input(INPUT_GET, 'device_action', FILTER_SANITIZE_NUMBER_INT);
					$state_get = filter_input(INPUT_GET, 'state', FILTER_SANITIZE_NUMBER_INT);
					$datetime_get = filter_input(INPUT_GET, 'datetime', FILTER_SANITIZE_STRING);
					$execute_get = filter_input(INPUT_GET, 'execute_state', FILTER_SANITIZE_STRING);
					
					$query_scheduler_update = mysql_query("UPDATE scheduler SET list_name = '".$list_name_get."', device_action ='".$device_action_get."', datetime = '".$datetime_get."', execute_state = '".$execute_get."' WHERE id = '".$id_get."';");
					
					if (!$query_scheduler_update){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę zaktualizować rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						$push->setTitle('Akwarium');
						
						if ($state_get == 1){
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: wyłączony.");
						} else{
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: włączony.");
						}
						
						$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
						$push->setUrlTitle('Sprawdź na stronie');
						$push->setDevice('xt1572');
						$push->setPriority(-1);
						$push->setCallback('http://chris.schalenborgh.be/');
						$push->setTimestamp(time());
						$push->setDebug(true);
					}
				}else if ($action_get == 'scheduler'){
					$list_name_get = filter_input(INPUT_GET, 'list_name', FILTER_SANITIZE_STRING);
					$device_action_get = filter_input(INPUT_GET, 'device_action', FILTER_SANITIZE_NUMBER_INT);
					$state_get = filter_input(INPUT_GET, 'state', FILTER_SANITIZE_NUMBER_INT);
					$datetime_get = filter_input(INPUT_GET, 'datetime', FILTER_SANITIZE_STRING);
					$execute_get = filter_input(INPUT_GET, 'execute_state', FILTER_SANITIZE_STRING);
					
					$query_scheduler_add = mysql_query("INSERT INTO scheduler (list_name, device_action, datetime, execute_state) VALUES ('".$list_name_get."', '".$device_action_get."', '".$datetime_get."', '".$execute_get."')");
		
					if (!$query_scheduler_add){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę dodać rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						$push->setTitle('Akwarium');
						
						if ($state_get == 1){
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: wyłączony.");
						} else{
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: włączony.");
						}
						
						$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
						$push->setUrlTitle('Sprawdź na stronie');
						$push->setDevice('xt1572');
						$push->setPriority(-1);
						$push->setCallback('http://chris.schalenborgh.be/');
						$push->setTimestamp(time());
						$push->setDebug(true);
					}
				}else if ($action_get == 'scheduler_delete'){
					$query_scheduler_delete = mysql_query("DELETE FROM scheduler WHERE id = '".$id_get."'");
					
					if (!$query_scheduler_delete){
						$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę usunąć rekordów! Błąd: '.mysql_error();
						file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}else{
						$push->setTitle('Akwarium');
						
						if ($state_get == 1){
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: wyłączony.");
						}else{
							$push->setMessage("Zmiana stanu urządzenia!"."\n"."Aktualny stan: włączony.");
						}
						
						$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
						$push->setUrlTitle('Sprawdź na stronie');
						$push->setDevice('xt1572');
						$push->setPriority(-1);
						$push->setCallback('http://chris.schalenborgh.be/');
						$push->setTimestamp(time());
						$push->setDebug(true);
					}
				}else if ($action_get == 'uptime'){
					$crc_get = filter_input(INPUT_GET, 'crc', FILTER_SANITIZE_NUMBER_INT);
					$polynomical = 37 * date('G') * date('G') + 59 * date('i') - 11 * date('s') + 1099;
					
					if ($crc_get == $polynomical){
						echo "Dobry znacznik czasu!".'<br>';
						
						$query_uptime_add = mysql_query("INSERT INTO uptime (datetime) VALUES (CURRENT_TIMESTAMP)");
						
						if (!$query_uptime_add){
							$debug_string = date('Y-m-d H:i:s').'\t\tNie mogę dodać rekordów! Błąd: '.mysql_error();
							file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
						}else{
							$push->setTitle('Akwarium');
							$push->setMessage("Urządzenie zostało włączone!");
							$push->setUrl('http://szymonmaczka.2ap.pl/test/test.html');
							$push->setUrlTitle('Sprawdź na stronie');
							$push->setDevice('xt1572');
							$push->setPriority(-1);
							$push->setCallback('http://chris.schalenborgh.be/');
							$push->setTimestamp(time());
							$push->setDebug(true);
						}
					}else{
						echo "Zly znacznik czasu!".'<br>';
						//$debug_string = date('Y-m-d H:i:s').'\t\tZnaczniki wielomianu czasu są niezgodne!';
						//file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
					}
				}else if ($action_get == 'debug'){
					$string1 = preg_replace("/;/","n",$_GET['debug_info']);
					$string1 = preg_replace("/^/"," ",$string1);
					
					$query_uptime_add = mysql_query("INSERT INTO log (log, timestamp) VALUES ('".$string1."', CURRENT_TIMESTAMP)");
					
					if (!$query_uptime_add){
						echo "Cannot insert new row of id ".$request_id_get."!";
					}else{
						echo "New row of id ".$request_id_get." has been added!";
					}
				}
				
				$go = $push->send();
			}
		}
		
		mysql_close($connection); 
	}
?>