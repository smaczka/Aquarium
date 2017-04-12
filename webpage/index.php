<?php
	session_start();
	
	if ((isset($_SESSION['logged'])) && ($_SESSION['logged'] == true))
	{
		header('Location: home.php');
		exit();
	}
?>

<!DOCTYPE HTML>
<html lang="pl">
<head>
	<title>Akwarium</title>
	<link rel="icon" href="images/ico.png">
	<link rel="stylesheet" href="index.css" type="text/css" media="screen" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js" charset="utf-8"></script>
	<link href="http://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<script src="http://code.jquery.com/jquery-1.10.2.js"></script>
	<script src="http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
</head>
<body>
	<form action="backend/login_check.php" method="post">
		Login: <input type="text" name="login" /> <br /><br />
		Hasło: <input type="password" name="password" /> <br /><br />
		<input type="submit" value="login_btn" />
	</form>
	
<?php
	if(isset($_SESSION['error']))
		echo $_SESSION['error'];
?>