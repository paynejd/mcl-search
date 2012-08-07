<?php

$arr_param = array_merge($_POST, $_GET);

if ($arr_param['export_format'] == 'openmrs-meta-data-zip' && 
	isset($arr_param['export_values'])) 
{
	$url_meta_data_export =
		'/openmrs/ws/rest/metadatasharing/package/new.form?' . 
		'key=5b635b8d02812d2e1c97691cd71fc05a&' . 
		'compress=true&' .
		'type=org.openmrs.Concept' . 
		'&ids=' . $arr_param['export_values'];
	header('Location:'.$url_meta_data_export);
	exit();	
} else {
	echo '<pre>', var_dump($arr_param), '</pre>';
}

?>
