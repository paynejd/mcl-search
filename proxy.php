<?php

// Set the url
	$url = $_GET['url'];
	unset($_GET['url']);

// Convert the search query (if necessary)
	//if (isset($_GET['q'])) {
	//	$url .
	//}
	//unset($_GET['q']);

// Append the rest of the parameters
	if (count($_GET)) {
		$url .= '?';
		foreach ($_GET as $k => $v) {
			$url .= urlencode($k) . '=' . urlencode($v) . '&';
		}
	}

// Perform the query
	$ch   =  curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$json_search =  curl_exec($ch);	// Website returns json
	curl_close($ch);
	if (!$json_search) {
		var_dump($json_search);
		trigger_error('curl 1 failed');
		exit();
	}
	echo $json_search;

?>