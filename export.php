<?php
/****************************************************************************************************
** export.php
**
** UNDER CONSTRUCTION
**
** Exports the passed concept IDs using the OpenMRS Metadata Sharing module. Note that
** this is currently hardcoded, although there are plans to expand to support other
** export formats in the future. This page accepts GET or POST parameters. If an
** unrecognized format is requested, the page simply displays a dump of the passed url 
** parameters for debugging purposes.
** --------------------------------------------------------------------------------------------------
** GET or POST parameters:
**		export_format (required)	Currently only 'openmrs-meta-data-zip' is supported
**		export_values (required)	Comma-separated list of concept IDs
*****************************************************************************************************/


$arr_param = array_merge($_POST, $_GET);

if ($arr_param['export_format'] == 'openmrs-meta-data-zip' && 
	isset($arr_param['export_values'])) 
{
	$url_meta_data_export =
		'/openmrs/module/metadatasharing/getMetadata.form?' . 
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