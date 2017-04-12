<?php
	session_start();
	
	if ((!isset($_POST['login'])) || (!isset($_POST['password'])))
	{
		header('Location: ../index.php');
		exit();
	}

	require_once "connection.php";

	$connection = @new mysqli($host, $user, $password, $databse);
	
	if ($connection->connect_errno != 0)
	{
		$debug_string = date('Y-m-d H:i:s')."\t\tProblem z wyborem bazy danych! B³¹d: ".$connection->connect_errno."\n";
		file_put_contents($debug_file, $debug_string, FILE_APPEND | LOCK_EX);
	}else{
		$login = $_POST['login'];
		$password = $_POST['password'];
		
		$login = htmlentities($login, ENT_QUOTES, "UTF-8");
		$password = htmlentities($password, ENT_QUOTES, "UTF-8");
		
		if ($result = @$connection->query(
			sprintf("SELECT * FROM users WHERE login='%s' AND password='%s'",
			mysqli_real_escape_string($connection, $login),
			mysqli_real_escape_string($connection, $password)))){
				
			$users_count = $result->num_rows;
			
			if($users_count>0){
				$_SESSION['logged'] = true;
				
				$row = $result->fetch_assoc();
				$_SESSION['id'] = $row['id'];
				$_SESSION['login'] = $row['login'];
				
				unset($_SESSION['error']);
				$result->free_result();
				header('Location: ../home.php');
			}else{
				$_SESSION['error'] = '<span style="color:red">Nieprawid³owy login lub has³o!</span>';
				header('Location: ../index.php');
			}
		}
		
		$connection->close();
	}
?>