<?php

ini_set('display_errors',1);
error_reporting(E_ALL|E_STRICT);

// Send some headers
	header("Pragma: no-cache");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	ini_set ('error_reporting', E_ALL);
	ini_set ('display_errors', true);

// Parse the code submissions
	if(isset($_POST["code"])) {
		$actual_code = trim ( stripslashes ( $_POST["code"] ) );
		$display_code = htmlentities($actual_code, ENT_QUOTES);
	} else {
		$display_code = '';
	}

?>
<html>
<head>
<title>PHP Evaluator</title>
<LINK href="main.css" rel="stylesheet" type="text/css">
</head>
<body>
<table id="tblToolbar" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td id="tdHeader">PHP Evaluator</td>
	</tr>
</table>

<form method="post" name="eval">
	<textarea name="code" cols="80" rows="18" wrap="off" style="width: 100%;"><?php echo $display_code ?></textarea><br>
	<table border="0" cellpadding="2" cellspacing="1">
		<tr>
			<td><input type="submit" value="Execute" /></td>
			<td>
				<input type="checkbox" name="preformat" id="preformat" value="1" <?php 
					if (isset($_POST['preformat'])) echo "checked=\"checked\""; 
				?> />
				<label for="preformat">Preformatted Results</label>
			</td>
		</tr>
	</table> 
</form>
<?php
	if(isset($actual_code))  {
		$actual_code = trim($actual_code);
		if ( strlen ( $actual_code ) > 0 )
		{
			echo "<h3>Results of execution:</h3>";
			if (isset($_POST['preformat'])) echo "<pre>";
			flush ( );
			echo eval ( $actual_code);
		}
	}
	if (isset($_POST['preformat'])) echo "</pre>";
?>
</body>
</html>