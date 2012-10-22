<?php
/****************************************************************************************************
** mapcodeexport.php
**
** Export concept collections in a variety of formats.
** --------------------------------------------------------------------------------------------------
** GET Parameters:
**		collection		Concept collection/list ID
**		sources			CSV of concept source IDs to include in the export
**		debug			0 or 1
**		mode			LIST or CROSSTAB
**		format			CSV, HTML-TABLE, etc.
**		output			SCREEN or FILE
*****************************************************************************************************/


define(  'MCL_EXPORT_MODE_CROSSTAB'     ,  1  );	// One row per concept
define(  'MCL_EXPORT_MODE_LIST'         ,  2  );	// One row per map code
define(  'MCL_EXPORT_MODE_DEFAULT'      ,  2  );
define(  'MCL_EXPORT_FORMAT_HTMLTABLE'  ,  1  );
define(  'MCL_EXPORT_FORMAT_DEFAULT'    ,  1  );
define(  'MCL_EXPORT_FORMAT_CSV'        ,  2  );
define(  'MCL_EXPORT_OUTPUT_SCREEN'     ,  1  );
define(  'MCL_EXPORT_OUTPUT_FILE'       ,  2  );
define(  'MCL_EXPORT_OUTPUT_DEFAULT'    ,  1  );

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
require_once(MCL_ROOT . 'fw/search_common.inc.php');

session_start();


$arr_delimiter[  MCL_EXPORT_FORMAT_HTMLTABLE  ] = array(
		'header'                  =>  '<table border="1">'   , 
		'footer'                  =>  '</table>'  ,
		'column_name_row_header'  =>  '<tr>'      ,
		'column_name_row_footer'  =>  '</tr>'     ,
		'column_name_header'      =>  '<th>'      ,
		'column_name_footer'      =>  '</th>'     ,
		'row_header'              =>  '<tr>'      ,
		'row_footer'              =>  '</tr>'     ,
		'cell_header'             =>  '<td>'      ,
		'cell_footer'             =>  '</td>'     ,
		'cell_separator'          =>  ''          ,
		'string_delimiter'        =>  ''          ,
	);
$arr_delimiter[  MCL_EXPORT_FORMAT_CSV  ] = array(
		'header'                  =>  ''    , 
		'footer'                  =>  ''    ,
		'column_name_row_header'  =>  ''    ,
		'column_name_row_footer'  =>  "\n"  ,
		'column_name_header'      =>  ''    ,
		'column_name_footer'      =>  ''    ,
		'row_header'              =>  ''    ,
		'row_footer'              =>  "\n"  ,
		'cell_header'             =>  ''    ,
		'cell_footer'             =>  ''    ,
		'cell_separator'          =>  ','   ,
		'string_delimiter'        =>  '"'   ,
	);



/****************************************************************************************
**  SETUP
****************************************************************************************/

// Get the user
	$user = null;
	if (MclUser::isLoggedIn()) {
		$user = MclUser::getLoggedInUser();
	}

// Set defaults
	$collection_id  =  null                       ;		// No collection
	$csv_sources    =  ''                         ; 	// All sources
	$debug          =  false                      ;		// Displays debug information
	$mode           =  MCL_EXPORT_MODE_DEFAULT    ;		// LIST or CROSSTAB
	$format         =  MCL_EXPORT_FORMAT_DEFAULT  ;		// 
	$output         =  MCL_EXPORT_OUTPUT_DEFAULT  ;
	$is_error       =  false                      ;
	$display_html_wizard  =  true                 ;

// open db connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);

// Set Parameters
	if (  isset($_GET[ 'collection' ])  )  $collection_id  =       $_GET[ 'collection' ]  ;
	if (  isset($_GET[ 'sources'    ])  )  $csv_sources    =       $_GET[ 'sources'    ]  ;
	if (  isset($_GET[ 'debug'      ])  )  $debug          = (bool)$_GET[ 'debug'      ]  ;
	if (  isset($_GET[ 'mode'       ])  )  $mode           =       $_GET[ 'mode'       ]  ;
	if (  isset($_GET[ 'output'     ])  )  $output         =       $_GET[ 'output'     ]  ;
	if (  isset($_GET[ 'format'     ])  )  $format         =       $_GET[ 'format'     ]  ;

// Verify parameters
	if (  ( $mode != MCL_EXPORT_MODE_LIST )  &&  ( $mode != MCL_EXPORT_MODE_CROSSTAB )  ) {
		die('Invalid mode: ' . $mode);
	}
	if (  ( $output != MCL_EXPORT_OUTPUT_SCREEN )  &&  ( $output != MCL_EXPORT_OUTPUT_FILE )  ) {
		$output = MCL_EXPORT_OUTPUT_SCREEN;
	}
	if (  ( $format != MCL_EXPORT_FORMAT_HTMLTABLE )  &&  ( $format != MCL_EXPORT_FORMAT_CSV )  ) {
		die('Invalid format: ' . $format);
	}



/****************************************************************************************
**  EXECUTE & PROCESS SEARCH QUERY (if url parameters are set)
****************************************************************************************/

$arr_concept  =  null;
$arr_sources  =  null;
if (  !is_null($collection_id)  )  
{

	// Setup sql
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

	// Process query
		$arr_concept  =  array();
		$arr_sources  =  array();
		while (  $row = mysql_fetch_assoc($result)  ) 
		{
			$concept_id  =  $row[  'concept_id'  ];
			$arr_concept[ $concept_id ][ 'data' ][ 'concept_id' ]  =  $concept_id                 ;
			$arr_concept[ $concept_id ][ 'data' ][ 'name'       ]  =  $row[ 'cname'            ]  ;
			$arr_concept[ $concept_id ][ 'data' ][ 'class'      ]  =  $row[ 'concept_class'    ]  ;
			$arr_concept[ $concept_id ][ 'data' ][ 'datatype'   ]  =  $row[ 'concept_datatype' ]  ;
	
			if (  $row['source'] != null  ) 
			{ 
				$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][]  =  $row[ 'source_code' ];
				
				if (  !isset($arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ])  ) 
				{
					$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ] = 0;
				}
				$arr_concept[ $concept_id ][ 'mapcodes' ][ $row['source'] ][ '__count' ]++;
		
				if (  !isset($arr_sources[$row['source']])  ) 
				{
					$arr_sources[$row['source']] = $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'];
				} 
				elseif (  $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'] > $arr_sources[$row['source']]  ) 
				{
					$arr_sources[$row['source']] = $arr_concept[$concept_id]['mapcodes'][$row['source']]['__count'];
				}
			}
		}

}



/****************************************************************************************
**  HTML WIZARD -- Not displayed if exporting to file
****************************************************************************************/

// Determine if HTML Wizard should be displayed
//TODO: Need to handle exceptions, like when there is an error
	if (  $output == MCL_EXPORT_OUTPUT_SCREEN  )  {
		$display_html_wizard = true;
	} else {
		$display_html_wizard = false;
	}


if (  $display_html_wizard  )
{

	// Load concept collections
	//TODO: Need to apply security
	$sql_collection = 
			'select concept_list_id, list_name ' . 
			'from ' . $mcl_enhanced_db_name . '.concept_list ' .
			'where active = 1 ' . 
			'order by list_name';
	$result_collection  =  mysql_query($sql_collection, $cxn);
	if (  !$result_collection  ) {
		die('Could not execute query: ' . $sql_collection . ' -- ' . mysql_error($cxn));
	}
	$arr_collection = array();
	while (  $row = mysql_fetch_assoc($result_collection)  )
	{
		$arr_collection[] = $row;
	}

	// Load map source IDs from 'openmrs' dictionary only
	//TODO: Need to figure out how to handle other dictionaries -- probably a master list of 
	// how map sources relate to each other across dictionaries stored in the MCL Extended schema
	$sql_map_source = 
			'select concept_source_id, name ' . 
			'from openmrs.concept_source ' .
			'where retired = 0 ' . 
			'order by name ';
	$result_map_source  =  mysql_query($sql_map_source, $cxn);
	if (  !$result_map_source  ) {
		die('Could not execute query: ' . $result_map_source . ' -- ' . mysql_error($cxn));
	}
	$arr_map_source = array();
	while (  $row = mysql_fetch_assoc($result_map_source)  )
	{
		$arr_map_source[] = $row;
	}

?>
<html>
<head>
<title>Concept Map Code Export</title>
</head>
<body>
<h1>Map Code Export</h1>
<form action="mapcodeexport.php" method="GET">

<table>
<tr><td valign="top">
<table style="float:left;">
<tr><td><label for="lstCollection">Concept Collection:</label></td>
	<td><?php
			$arr_attr = array('id'=>'lstCollection', 'name'=>'collection');
			echoHtmlSelect($arr_collection, $arr_attr, 'concept_list_id', 'list_name', $collection_id, false);
		?>
	</td></tr>
<tr><td><label for="txtSources">Concept Map Sources:</label></td>
	<td><input type="text" id="txtSources" name="sources" value="<?php echo $csv_sources; ?>" />
		<span class="note">(CSV of map source IDs. Leave blank for all sources)</span>
		</td></tr>
<tr><td><label for="lstMode">Export Mode:</label></td>
	<td><?php
			$arr_mode_dropdown = array(
					array(  'value'=>MCL_EXPORT_MODE_LIST, 'display'=>'List - 1 line per map code'     ),
					array(  'value'=>MCL_EXPORT_MODE_CROSSTAB, 'display'=>'Crosstab - 1 line per concept'  )
				);
			$arr_attr = array('id'=>'lstMode', 'name'=>'mode');
			echoHtmlSelect($arr_mode_dropdown, $arr_attr, 'value', 'display', $mode, false);
		?>
		</td></tr>
<tr><td><label for="lstFormat">Format:</label></td>
	<td><?php
			$arr_format_dropdown = array(
					array(  'value'=>MCL_EXPORT_FORMAT_HTMLTABLE, 'display'=>'HTML'   ),
					array(  'value'=>MCL_EXPORT_FORMAT_CSV, 'display'=>'CSV'  )
				);
			$arr_attr = array('id'=>'lstFormat', 'name'=>'format');
			echoHtmlSelect($arr_format_dropdown, $arr_attr, 'value', 'display', $format, false);
		?>
		</td></tr>
<tr><td><label for="lstOutput">Output:</label></td>
	<td><?php
			$arr_output_dropdown = array(
					array(  'value'=>MCL_EXPORT_OUTPUT_SCREEN, 'display'=>'Web Browser'   ),
					array(  'value'=>MCL_EXPORT_OUTPUT_FILE, 'display'=>'File'  )
				);
			$arr_attr = array('id'=>'lstOutput', 'name'=>'output');
			echoHtmlSelect($arr_output_dropdown, $arr_attr, 'value', 'display', $output, false);
		?>
		</td></tr>
<tr><td><label for="lstDebug">Debug:</label></td>
	<td><?php
			$arr_debug_dropdown = array(
					array(  'value'=>'0', 'display'=>'No'   ),
					array(  'value'=>'1', 'display'=>'Yes'  )
				);
			$arr_attr = array('id'=>'lstDebug', 'name'=>'debug');
			echoHtmlSelect($arr_debug_dropdown, $arr_attr, 'value', 'display', $debug, false);
		?>
		</td></tr>
<tr><td></td>
	<td><input type="submit" value="Submit" /></td></tr>
</table>
</td><td valign="top" style="padding-left:50px;font-size:10pt;">
<div style="border:1px solid #999;background:#eee;padding:20px;padding-top:0;">
<h3>Map Sources:</h3>
<?php
	foreach (  $arr_map_source as $map_source  )
	{
		echo $map_source['concept_source_id'] . ' - ' . $map_source['name'] . '<br />';
	}
?>
</div>
</td></tr></table>

</form>

<?php
}



/****************************************************************************************
**  OUTPUT RESULTS
****************************************************************************************/

if (  !is_null($arr_concept)  &&  !is_null($arr_sources)  )
{



	// Send the headers (unless in debug mode)
	if (  $output == MCL_EXPORT_OUTPUT_FILE  &&  !$is_error  ) 
	{
		if     (  $format == MCL_EXPORT_FORMAT_HTMLTABLE  )  $filename  =  'mapcodeexport.html'  ;
		elseif (  $format == MCL_EXPORT_FORMAT_CSV        )  $filename  =  'mapcodeexport.csv'   ;
		header(  'Content-type: text/csv'                                 );
		header(  'Content-disposition: attachment;filename=' . $filename  );
		header(  'Pragma: public'                                         );
	}

	// Echo some useful info
	if (  $output == MCL_EXPORT_OUTPUT_SCREEN  ) {
		echo '<hr><strong>Info</strong><ul>';
		echo '<li>Concept Collection ID: ' . $collection_id . '</li>';
		echo '<li>Map Sources: ' . $csv_sources . '</li>';
		echo '<li># sources: ' . count($arr_sources) . '</li>';
		echo '<li># concepts: ' . count($arr_concept) . '</li>';
		//echo '<li># mappings: ' . '</li>';
		//echo '<li># unique map codes: ' . '</li>';
		echo '</ul>';
	}

	// Echo results
	if (  $output == MCL_EXPORT_OUTPUT_SCREEN  )  echo '<hr><pre>';
	if (  $mode == MCL_EXPORT_MODE_CROSSTAB  )
	{

		// Start the output
		echo $arr_delimiter[  $format  ][  'header'  ];

		// start the column header row
		echo $arr_delimiter[  $format  ][  'column_name_row_header'  ];
		
		// concept ID
		echo $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_id' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ]; 

		// concept name
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'name' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];

		// concept class
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_class' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ]; 

		// concept datatype
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_datatype' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];

		// map sources
		foreach ($arr_sources as $source_name => $source_num) 
		{
			for ($i = 0; $i < $source_num; $i++) 
			{
				echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
					 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
					 $source_name . '_' . ($i + 1) . 
					 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
					 $arr_delimiter[  $format  ][  'column_name_footer'  ];
			}
		}
		
		// end the column header row
		echo $arr_delimiter[  $format  ][  'column_name_row_footer'  ];
	
		// echo concepts
		foreach (array_keys($arr_concept) as $concept_id) 
		{
			// start the concept row
			echo $arr_delimiter[  $format  ][  'row_header'        ];

			// concept ID
			echo $arr_delimiter[  $format  ][  'cell_header'       ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $concept_id . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
			
			// concept name
			echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
				 $arr_delimiter[  $format  ][  'cell_header'       ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_concept[ $concept_id ][ 'data' ][ 'name'     ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
			
			// concept class
			echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
				 $arr_delimiter[  $format  ][  'cell_header'       ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_concept[ $concept_id ][ 'data' ][ 'class'    ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
			
			// concept datatype
			echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
				 $arr_delimiter[  $format  ][  'cell_header'       ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_concept[ $concept_id ][ 'data' ][ 'datatype' ] . 
				 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
				 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
			
			// mapcodes
			foreach ($arr_sources as $source_name => $source_num) 
			{
				for ($i = 0; $i < $source_num; $i++) 
				{
					$val = '';
					if (isset($arr_concept[$concept_id]['mapcodes'][$source_name][$i])) {
						$val = $arr_concept[$concept_id]['mapcodes'][$source_name][$i];
					}
					echo $arr_delimiter[  $format  ][  'cell_separator'    ] .
						 $arr_delimiter[  $format  ][  'cell_header'       ] . 
						 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
						 $val . 
						 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
						 $arr_delimiter[  $format  ][  'cell_footer'       ];
				}
			}
			
			// end the row
			echo $arr_delimiter[  $format  ][  'row_footer'  ];
		}
		
		// end the output
		echo $arr_delimiter[  $format  ][  'footer'  ];
	
	}
	elseif ($mode == MCL_EXPORT_MODE_LIST)
	{
	
		// Start the output
		echo $arr_delimiter[  $format  ][  'header'  ];

		// start the column header row
		echo $arr_delimiter[  $format  ][  'column_name_row_header'  ];
		
		// concept ID
		echo $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_id' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ]; 

		// concept name
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'name' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];

		// concept class
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_class' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ]; 

		// concept datatype
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'concept_datatype' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];

		// source
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'source' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];

		// mapcode
		echo $arr_delimiter[  $format  ][  'cell_separator'      ] .
			 $arr_delimiter[  $format  ][  'column_name_header'  ] . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 'mapcode' . 
			 $arr_delimiter[  $format  ][  'string_delimiter'    ] .
			 $arr_delimiter[  $format  ][  'column_name_footer'  ];
	
		// end the column name row
		echo $arr_delimiter[  $format  ][  'column_name_row_footer'  ];
	
		// Echo one row per concept/mapcode combination 
		// NOTE: Also 1 row per concept that is not mapped to any map codes
		foreach (array_keys($arr_concept) as $concept_id) 
		{
			// If no mapcodes for this concept, just display the concept info
			if (!isset($arr_concept[ $concept_id ][ 'mapcodes' ])) 
			{
				// start the row
				echo $arr_delimiter[  $format  ][  'row_header'        ];
		
				// concept ID
				echo $arr_delimiter[  $format  ][  'cell_header'       ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $concept_id . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
				
				// concept name
				echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
					 $arr_delimiter[  $format  ][  'cell_header'       ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_concept[ $concept_id ][ 'data' ][ 'name'     ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
				
				// concept class
				echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
					 $arr_delimiter[  $format  ][  'cell_header'       ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_concept[ $concept_id ][ 'data' ][ 'class'    ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
				
				// concept datatype
				echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
					 $arr_delimiter[  $format  ][  'cell_header'       ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_concept[ $concept_id ][ 'data' ][ 'datatype' ] . 
					 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
					 $arr_delimiter[  $format  ][  'cell_footer'       ]; 

				// echo 2 blank cells
				for ($i = 0; $i < 2; $i++) 
				{
					echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
						 $arr_delimiter[  $format  ][  'cell_header'       ] . 
						 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
						 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
						 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
				}

				// end the row
				echo $arr_delimiter[  $format  ][  'row_footer'        ];
			}

			// If the concept has one or more mapcodes....
			else
			{
				foreach (  array_keys($arr_concept[ $concept_id ][ 'mapcodes' ]) as $source_name  ) 
				{
					foreach (  $arr_concept[ $concept_id ][ 'mapcodes' ][ $source_name ] as $k => $source_code  )  
					{
						// Skip if not a mapcode
						if ($k === '__count') continue;

						// start the row
						echo $arr_delimiter[  $format  ][  'row_header'        ];

						// concept ID
						echo $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $concept_id . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
						
						// concept name
						echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
							 $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_concept[ $concept_id ][ 'data' ][ 'name'     ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
						
						// concept class
						echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
							 $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_concept[ $concept_id ][ 'data' ][ 'class'    ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 
						
						// concept datatype
						echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
							 $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_concept[ $concept_id ][ 'data' ][ 'datatype' ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 

						// concept datatype
						echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
							 $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $source_name . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 

						// concept datatype
						echo $arr_delimiter[  $format  ][  'cell_separator'    ] . 
							 $arr_delimiter[  $format  ][  'cell_header'       ] . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $source_code . 
							 $arr_delimiter[  $format  ][  'string_delimiter'  ] .
							 $arr_delimiter[  $format  ][  'cell_footer'       ]; 

						// end the row
						echo $arr_delimiter[  $format  ][  'row_footer'        ];
					}
				}
			}
		}
	
		// End the output
		echo $arr_delimiter[  $format  ][  'footer'  ];
	}
	if (  $output == MCL_EXPORT_OUTPUT_SCREEN  )  echo '</pre>';

	// DEBUG - echo the concept array
	if (  $debug  ) {
		echo '<hr><pre>', var_dump($arr_concept), '</pre>';
	}

	exit();
}



/****************************************************************************************
**  END HTML OUTPUT -- Not displayed if exporting to file
****************************************************************************************/

	if (  $output == MCL_EXPORT_OUTPUT_SCREEN  ) 
	{
		echo '</body>';
		echo '</html>';
	}

?>