<?php
/****************************************************************************************************
** visualize.php
**
** UNDER CONSTRUCTION
**
** Visually illustrate the relationships of the passed concept(s).
** --------------------------------------------------------------------------------------------------
** GET or POST parameters:
**		collection		Collection ID to visualize
*****************************************************************************************************/

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
require_once(MCL_ROOT . 'fw/search_common.inc.php');

session_start();



/****************************************************************************************
**  SETUP
****************************************************************************************/

$arr_param = array_merge($_GET, $_POST);

// Get the user
	$user = null;
	if (MclUser::isLoggedIn()) {
		$user = MclUser::getLoggedInUser();
	}

// Set defaults
	$collection_id  =  1                     	  ;		// No collection
	$debug          =  false                      ;		// Displays debug information
	$csv_sources    =  '1,2'                      ;		// SNOMED CT and NP

// open db connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);

// Set Parameters
	if (  isset($arr_param[ 'collection' ])  )  $collection_id  =       $arr_param[ 'collection' ]  ;
	if (  isset($arr_param[ 'debug'      ])  )  $debug          = (bool)$arr_param[ 'debug'      ]  ;



/****************************************************************************************
**  EXECUTE & PROCESS SEARCH QUERY (if url parameters are set)
****************************************************************************************/

$arr_concept  =  null;
$arr_sources  =  null;
if (  !is_null($collection_id)  )  
{

	// Setup sql to get the concepts
		$sql = 
			"select " .
			  "clm.concept_id, " .
			  "(select cn.name from openmrs.concept_name cn " .
				"join openmrs.concept_name_tag_map cntm on cntm.concept_name_id = cn.concept_name_id AND cntm.concept_name_tag_id = 4 " .
				"where cn.concept_id = clm.concept_id limit 1 " .
			  ") cname, " .
			  "cc.name concept_class, " .
			  "cd.name concept_datatype, " .
			  "cs.name source, " .
			  "cm.source_code " .
			"from mcl.concept_list_map clm " .
			"left join openmrs.concept c on clm.concept_id = c.concept_id " .
			"left join openmrs.concept_class cc on c.class_id = cc.concept_class_id " .
			"left join openmrs.concept_datatype cd on c.datatype_id = cd.concept_datatype_id " .
			"left join openmrs.concept_map cm on clm.concept_id = cm.concept_id ";
		if ($csv_sources) $sql .= "and cm.source in (" . $csv_sources . ") ";
		$sql .= 	
			"left join openmrs.concept_source cs on cm.source = cs.concept_source_id " .
			"where clm.concept_list_id = " . $collection_id .
			" order by clm.concept_id";
		if ($debug) echo $sql, '<hr>';

	// Execute query
		$result  =  mysql_query($sql, $cxn);
		if (  !$result  ) {
			die('Could not execute query: ' . mysql_error());
		}

	// Process concept query
		$arr_concept  =  array();
		$arr_sources  =  array();
		$arr_mapcode  =  array();
		while (  $row = mysql_fetch_assoc($result)  ) 
		{
			$concept_id  =  $row[  'concept_id'  ];
			$arr_concept[ $concept_id ][ 'data' ][ 'concept_id' ]  =  $concept_id                 ;
			$arr_concept[ $concept_id ][ 'data' ][ 'name'       ]  =  $row[ 'cname'            ]  ;
			$arr_concept[ $concept_id ][ 'data' ][ 'class'      ]  =  $row[ 'concept_class'    ]  ;
			$arr_concept[ $concept_id ][ 'data' ][ 'datatype'   ]  =  $row[ 'concept_datatype' ]  ;
	
			if (  $row['source'] != null  ) 
			{
				// Add the map coe
				$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][]  =  $row[ 'source_code' ];

				// Increment the counter
				if (  !isset($arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ])  ) 
				{
					$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ] = 0;
				}
				$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ]++;
		
				// Keep track of the number of columns needed for cross tab mode
				if (  !isset($arr_sources[$row['source']])  ) 
				{
					$arr_sources[$row['source']] = $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'];
				} 
				elseif (  $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'] > $arr_sources[$row['source']]  ) 
				{
					$arr_sources[$row['source']] = $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'];
				}

				// Add to the unique mapcode array
				$arr_mapcode[  $row['source_code']  ]  = $row['source_code'];
			}
		}

	// Setup query to grab the SNOMED hierarchy
	//TODO: This just grabs the entire table, rather than the hierarchy
		$sql_snomed =
				'select * ' . 
				'from snomed.concept ' .
				'order by snomed_id';
	
	// Execute and process query
		$result_snomed  =  mysql_query($sql_snomed, $cxn);
		if (  !$result_snomed  ) {
			die('Could not execute query: ' . mysql_error());
		}
		$arr_snomed = array();
		while (  $row = mysql_fetch_assoc($result_snomed)  ) 
		{
			$arr_snomed[  $row['snomed_id']  ]  =  $row;
		}

}

?>
<html>
<head>
<link rel="stylesheet" href="../css/bootstrap.min.css">
<link rel="stylesheet" href="../css/jquery.jOrgChart.css">
<link rel="stylesheet" href="../css/custom.css">
<link href="../css/prettify.css" type="text/css" rel="stylesheet">
<script type="text/javascript" src="../js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>
<script type="text/javascript" src="../js/jquery.jOrgChart.js"></script>

<script>
    jQuery(document).ready(function() {
        $("#org").jOrgChart({
            chartElement : '#chart',
            dragAndDrop  : true
        });
    });
</script>
</head>
<body>

<?php

foreach (array_keys($arr_concept) as $concept_id)
{
	echo '<p>';
	echo '<strong>' . $concept_id . ' - ' . $arr_concept[$concept_id]['data']['name'] . '</strong>';
	if (isset($arr_concept[$concept_id]['mapcodes'])) 
	{
		echo '<ul>';
		foreach (array_keys($arr_concept[$concept_id]['mapcodes']) as $map_source) 
		{
			foreach ($arr_concept[$concept_id]['mapcodes'][$map_source] as $k => $map_code) 
			{
				if ($k === '__count') continue;
				echo '<li>';
				if ($map_source == 'SNOMED NP') echo '<em>';
				echo $map_code;
				if ($map_source == 'SNOMED NP') echo '</em>';
				
				$recursive_map_code = $map_code;
				while (isset($arr_snomed[$recursive_map_code])) {
					echo ' ---> ' . $arr_snomed[$recursive_map_code]['name'] . 
							' (' . $arr_snomed[$recursive_map_code]['snomed_id'] . ')';
					$recursive_map_code = $arr_snomed[$recursive_map_code]['parent_id'];
				}

				echo '</li>';
			}
		}
		echo '</ul>';
	}
	echo '</p>';
}

?>

<pre>
<?php
/*
var_dump($arr_sources);
echo '<hr>';
var_dump($arr_mapcode);
echo '<hr>';
var_dump($arr_snomed);
echo '<hr>';
var_dump($arr_concept);
*/
?>
</pre>

</body>
</html>