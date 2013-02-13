<?php

$arr_param = array_merge($_GET, $_POST);

// Set the url
	$url = $arr_param['url'];
	unset($arr_param['url']);

// Append the rest of the parameters
/*	if (count($arr_param)) {
		$url .= '?';
		foreach ($arr_param as $k => $v) {
			$url .= urlencode($k) . '=' . urlencode($v) . '&';
		}
	}
*/

// Perform the query
	$ch   =  curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $arr_param);
	$json_search =  curl_exec($ch);	// Website returns json
	curl_close($ch);
	if (!$json_search) {
		var_dump($json_search);
		trigger_error('curl 1 failed');
		exit();
	}
	echo $json_search;

?>