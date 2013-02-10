<?php
error_reporting(E_ALL);


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
require_once(MCL_ROOT . 'fw/search_common.inc.php');
require_once(MCL_ROOT . 'fw/ConceptCollectionFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptListFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchSourceFactory.inc.php');

session_start();

define(  'OCL_COMPARE_COLUMN_TYPE_UNION'       ,  10  );
define(  'OCL_COMPARE_COLUMN_TYPE_CONCEPT_ID'  ,  20  );
define(  'OCL_COMPARE_COLUMN_TYPE_MAPCODE'     ,  30  );
define(  'OCL_COMPARE_COLUMN_TYPE_SUBTOTAL'    ,  40  );
define(  'OCL_COMPARE_STATE_SUMMARY'           ,  'SUMMARY'  );


/****************************************************************************************
**  GET THE USER
****************************************************************************************/

// Get the user - this provides access to private/shared concept collections
	/*
	$user = null;
	if (MclUser::isLoggedIn()) {
		$user = MclUser::getLoggedInUser();
	}
	*/


/****************************************************************************************
**  INITIALIZE PARAMETERS
****************************************************************************************/

// Set defaults
	$collection_ids  =  null                       ;		// No collections
	$debug           =  false                      ;		// Displays debug information
	$min_elements    =  2                          ;		// Minimum # of collections in a comparison state

// Set Parameters
	if (  isset($_GET[ 'collection'   ])  )  $collection_ids  =       $_GET[ 'collection'   ]  ;
	if (  isset($_GET[ 'debug'        ])  )  $debug           = (bool)$_GET[ 'debug'        ]  ;
	if (  isset($_GET[ 'minelements'  ])  )  $min_elements    =       $_GET[ 'minelements'  ]  ;

// Verify parameters
	$arr_collection_id  =  array();
	if ($collection_ids) $arr_collection_id = explode(',',$collection_ids);
	if (count($arr_collection_id) <= 1) {
		die('Parameter "collection" must contain 2 or more collection IDs separated by commas');
	}

// Open DB connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);

// Load all dictionary sources
	$cssf          =  new ConceptSearchSourceFactory();
	$cssf->debug   =  $debug;
	$coll_source   =  new ConceptSearchSourceCollection();
	$coll_source->add($cssf->loadDictionaryDefinitions($cxn));


/****************************************************************************************
**  Setup the Grid
****************************************************************************************/

// Create columns
 	$arrColGroup = array(
 			new OclComparisonColumn('Union','Union',OCL_COMPARE_COLUMN_TYPE_UNION),
 		);
 	$arrGroup[] = new OclComparisonGroup(  $arrColGroup, 'Union', false, false );

 	$arrColGroup = array(
 			new OclComparisonColumn('IdentityConceptId','Concept ID',OCL_COMPARE_COLUMN_TYPE_CONCEPT_ID),
 			new OclComparisonColumn('IdentitySnomedCt','SNOMED CT',OCL_COMPARE_COLUMN_TYPE_MAPCODE, 'SNOMED CT'),
 			new OclComparisonColumn('IdentityLoinc','LOINC',OCL_COMPARE_COLUMN_TYPE_MAPCODE, 'LOINC'),
 		);
 	$arrGroup[] = new OclComparisonGroup(  $arrColGroup, 'Identity', true  );

 	$arrColGroup = array(
 			new OclComparisonColumn('IdentityIcd10','ICD-10-WHO',OCL_COMPARE_COLUMN_TYPE_MAPCODE, 'ICD-10-WHO'),
 			new OclComparisonColumn('RelatedSnomedCt','SNOMED CT (d<=1)',OCL_COMPARE_COLUMN_TYPE_MAPCODE, array('SNOMED CT','SNOMED NP')),
 		);
 	$arrGroup[] = new OclComparisonGroup(  $arrColGroup, 'Related', true  );


/****************************************************************************************
**  Load Collections
****************************************************************************************/

// Load Concept Lists - this is just the concept IDs
	$clf = new ConceptListFactory();
	$clf->setConnection($cxn);
	$arr_collection = array();
	foreach ($arr_collection_id as $collection_id) {
		$cl = $clf->loadConceptList($collection_id, MCL_CLTYPE_CONCEPT_LIST);
		if ($cl) {
			$arr_collection[$collection_id] = $cl;
		} else {
			echo '<p>Could not load list "' . $collection_id . '"</p>';
		}
	}
	$arr_collection_id = array_keys($arr_collection);

	if ($debug) echo "<pre>", var_dump($arr_collection), "</pre><hr />";

// Load Concept Collections - this gets the rest of the metadata that we want (just mappings for this script)
	$cc                =  null  ;
	$cc_merge          =  null  ;
	$ccf               =  new ConceptCollectionFactory()  ;
	$ccf->coll_source  =  $coll_source  ;
	$ccf->conn         =  $cxn          ;
	$ccf->debug        =  $debug        ;
	$ccf->load_concept_attributes  =  false  ;
	$ccf->load_qa_sets             =  false  ;
	$ccf->load_concept_sets        =  false  ;
	$ccf->load_concept_list_names  =  false  ;

	foreach ($arr_collection_id as $collection_id)
	{
		$cc_merge  =  $ccf->NewCollectionFromListId($collection_id)  ;
		echo '<p><b>Collection ID ' . $collection_id . '</b>: ' . $cc_merge->getVisibleCount() . ' of ' . 
				$arr_collection[$collection_id]->getCount() . ' concept(s) loaded...</p>';
		if (  $cc  )  $cc->merge(  $cc_merge  )  ;
		else  $cc  =  $cc_merge  ;
	}
	echo '<p><b>Merged Collection: </b>Metadata loaded for ' . $cc->getVisibleCount() . ' concept(s)...</p>';

// Build Mapping Array for each of the mapping sources of interest for each collection
	$arr_mapping = array();
	foreach ($arr_collection_id as $collection_id) {
		$arr_mapping[$collection_id]['SNOMED CT' ] = OclCompare::getMappings($coll_source, $cc, $arr_collection[$collection_id], 'SNOMED CT'  );
		$arr_mapping[$collection_id]['SNOMED NP' ] = OclCompare::getMappings($coll_source, $cc, $arr_collection[$collection_id], 'SNOMED NP'  );
		$arr_mapping[$collection_id]['LOINC'     ] = OclCompare::getMappings($coll_source, $cc, $arr_collection[$collection_id], 'LOINC'      );
		$arr_mapping[$collection_id]['ICD-10-WHO'] = OclCompare::getMappings($coll_source, $cc, $arr_collection[$collection_id], 'ICD-10-WHO' );
	}

// Setup comparison states
	$arr_state = array();
	if (count($arr_collection) < 2) {
		die('Need 2 or more collections to compare!');
	} else {
		$arr_state = OclCompare::getCollectionCombinations($arr_collection_id,$min_elements);
	}
	if ($debug) echo "<pre>", var_dump($arr_state), "</pre><hr />";


/****************************************************************************************
**  Analyze Collection Relationships for each Comparison State
****************************************************************************************/

// Iterate through column groups
$colGrandTotal = new OclComparisonColumn('grandtotal','Grand Total',OCL_COMPARE_COLUMN_TYPE_SUBTOTAL);
foreach ($arrGroup as $group) 
{
	// Iterate through comparison states
	foreach (array_keys($arr_state) as $state_id) 
	{
		// Iterate through columns
		$c_subtotal = null;
		foreach ($group->arr_column as $column) 
		{
			// Perform action for this cell
			$c = null;
			if ($column->column_type == OCL_COMPARE_COLUMN_TYPE_UNION) 
			{
				foreach ($arr_state[$state_id] as $collection_id) {
					if (!$c) $c = $arr_collection[$collection_id];
					else $c = $c->union(  $arr_collection[$collection_id]  );
				}
				$column->setStateConceptList($state_id, $c);
			} elseif ($column->column_type == OCL_COMPARE_COLUMN_TYPE_CONCEPT_ID) {
				foreach ($arr_state[$state_id] as $collection_id) {
					if (!$c) $c = $arr_collection[$collection_id];
					else $c = $c->intersect(  $arr_collection[$collection_id]  );
				}
				$column->setStateConceptList($state_id, $c);
			} elseif ($column->column_type == OCL_COMPARE_COLUMN_TYPE_MAPCODE) {
				foreach ($arr_state[$state_id] as $collection_id) {
					if (!$c) $c = $arr_collection[$collection_id];
					else $c = OclCompare::matchMappings($coll_source, $cc, $c, $arr_collection[$collection_id], $column->mapsource);
				}
				$column->setStateConceptList($state_id, $c);
			} else {
				echo 'uh oh! ';
				var_dump($group);
				exit();
			}

			// Add concept list from this cell to the subtotal concept list
			if (!$c_subtotal) $c_subtotal = $c;
			else $c_subtotal = $c_subtotal->union($c);

		}	/* end column foreach */

		// Set concept list for group subtotal (regardless of whether it is displayed)
		$group->col_subtotal->setStateConceptList($state_id, $c_subtotal);

		// Add subtotal concept list to the grand total (unless group settings say otherwise)
		if ($group->include_in_total) {
			$c_state_grandtotal = $colGrandTotal->getStateConceptList($state_id);
			if (!$c_state_grandtotal) $c_state_grandtotal = $c_subtotal;
			else $c_state_grandtotal = $c_state_grandtotal->union($c_subtotal);
			$colGrandTotal->setStateConceptList($state_id, $c_state_grandtotal);
		}

	}	/* end state foreach */

}	/* end group foreach */



/****************************************************************************************
**  Build Summary Collections for each Column
****************************************************************************************/

// Iterate through column groups
foreach ($arrGroup as $group) 
{
	// Iterate through columns
	foreach ($group->arr_column as $column) 
	{
		// Iterate through comparison states
		$c = null;
		foreach (array_keys($arr_state) as $state_id) 
		{
			if (!$c) $c = $column->getStateConceptList($state_id);
			else $c = $c->union($column->getStateConceptList($state_id));
		}
		$column->setStateConceptList(OCL_COMPARE_STATE_SUMMARY, $c);
	}

	// Subtotal column - Iterate through comparison states
	$c = null;
	foreach (array_keys($arr_state) as $state_id) 
	{
		if (!$c) $c = $group->col_subtotal->getStateConceptList($state_id);
		else $c = $c->union($group->col_subtotal->getStateConceptList($state_id));
	}
	$group->col_subtotal->setStateConceptList(OCL_COMPARE_STATE_SUMMARY, $c);

}	/* end group foreach */

// Grand total column
	$c = null;
	foreach (array_keys($arr_state) as $state_id) 
	{
		if (!$c) $c = $colGrandTotal->getStateConceptList($state_id);
		else $c = $c->union($colGrandTotal->getStateConceptList($state_id));
	}
	$colGrandTotal->setStateConceptList(OCL_COMPARE_STATE_SUMMARY, $c);

?>
<html>
<head>
<style type="text/css">
table.compare {
	background: #ccc;
}
table.compare th, 
table.compare td {
	background-color: White;
	padding: 5px;
	text-align: center;
	font-family: Arial,Sans-Serif;
	font-size:10pt;
}
table.compare th {
	font-weight: bold;
	background-color: #ddd;
}
table.compare td.rowheader {
	font-style: italic;
	text-align: left;
	background-color: #afc;
}
table.compare td.subtotal {
	background-color: #acf;
}
table.compare td.grandtotal {
	background-color: #9af;
	font-size: 11pt;
	font-weight: bold;
}
</style>
</head>
<body>
<?php
/****************************************************************************************
**  DISPLAY - HEADER
****************************************************************************************/

// Start
	echo '<table class="compare">';

// Header 1
	echo "<tr>";
	echo '<thead>';
	echo '<th rowspan="2">Set:</th>';
	echo '<th colspan="' . count($arr_collection) . '">Collections</th>';
	foreach ($arrGroup as $group) {
		echo '<th colspan="' . $group->getColumnDisplayCount() . '">' . $group->group_name . '</th>';
	}
	echo '<th rowspan="2">Grand Total</th>';
	echo '</tr>';

// Header 2
	echo "<tr>";
	// Skip "Set" column (rowspan)
	foreach (array_keys($arr_collection) as $collection_id) {
		echo "<th>" . $collection_id . "</th>";
	}
	foreach ($arrGroup as $group) {
		foreach ($group->arr_column as $column) {
			echo '<th id="' . $column->key . '">' . $column->column_name . '</th>';
		}
		if ($group->display_subtotal_column) echo '<th>Subtotal</th>';
	}
	// Skip "Grand Total" column (rowspan)
	echo "</tr>";

	echo '</thead>';


/****************************************************************************************
**  DISPLAY - COMPARISON STATES
****************************************************************************************/

echo '<tbody>';

// Iterate through each comparison state
	foreach (array_keys($arr_state) as $state_id) 
	{
		// Start
		echo '<tr>';
		echo '<td class="rowheader">Comparison State: ' . $state_id . '</td>';

		// State indicators
		foreach ($arr_collection_id as $collection_id) {
			echo '<td>';
			if (array_search($collection_id, $arr_state[$state_id]) !== false)  echo 'x';
			echo '</td>';
		}

		// Iterate through column groups	
		foreach ($arrGroup as $group) 
		{
			// Iterate through columns
			foreach ($group->arr_column as $column) 
			{
				$c = $column->getStateConceptList($state_id);
				echo '<td>' . $c->getCount() . '</td>';
			}

			// Subtotal
			if ($group->display_subtotal_column) {
				$c = $group->col_subtotal->getStateConceptList($state_id);
				echo '<td class="subtotal">' . $c->getCount() . '</td>';
			}
		}

		// Grand total
		$c = $colGrandTotal->getStateConceptList($state_id);
		echo '<td class="grandtotal">' . $c->getCount() . '</td>';

		// End
		echo '</tr>';
	}

echo '</tbody>';

/****************************************************************************************
**  DISPLAY - COLUMN TOTALS
****************************************************************************************/

echo '<tfoot>';

// Start
	echo '<tr>';

// Total number of concepts in each collection
	echo '<td class="rowheader">Number Concepts in Set</td>';
	foreach (array_keys($arr_collection) as $collection_id) 
	{
		echo '<td>';
		echo $arr_collection[$collection_id]->getCount() . '<br />';
		echo '</td>';
	}

// Iterate through column groups
	foreach ($arrGroup as $group) 
	{
		// Iterate through columns
		foreach ($group->arr_column as $column) 
		{
			$c = $column->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
			echo '<td>' . $c->getCount() . '</td>';
		}

		// Subtotal
		if ($group->display_subtotal_column) {
			$c = $group->col_subtotal->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
			echo '<td class="subtotal">' . $c->getCount() . '</td>';
		}
	}

// TODO: Grant Total
	$c = $colGrandTotal->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
	echo '<td class="grandtotal">' . $c->getCount() . '</td>';

// End
	echo '</tr>';


/****************************************************************************************
**  DISPLAY - MAPPING TOTALS
****************************************************************************************/
$arr_map_summary = array(
		'SNOMED CT'   => 'SNOMED CT',
		'SNOMED NP'   => 'SNOMED CT (Not Preferred)',
		'LOINC'       => 'LOINC',
		'ICD-10-WHO'  => 'ICD-10-WHO',
	);

// Iterate through the map sources we want to summarize
foreach ($arr_map_summary as $mapsource => $mapsource_name) 
{
	echo '<tr>';
	echo '<td class="rowheader">' . $mapsource_name . '</td>';

	// Iterate through the collection
	foreach (array_keys($arr_collection) as $collection_id) 
	{
		$c = $arr_collection[$collection_id];
		$count_mapped_concepts = OclCompare::countConceptsWithMapping($coll_source, $cc, $c, $mapsource);
		$count_unique_mapcodes = OclCompare::countUniqueMappings($coll_source, $cc, $c, $mapsource);
		echo '<td>' . $count_mapped_concepts . ' / ' . $count_unique_mapcodes . '</td>';
	}

	// Iterate through the column groups
	foreach ($arrGroup as $group) 
	{
		// Iterate through columns
		foreach ($group->arr_column as $column) 
		{
			$c = $column->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
			$count_mapped_concepts = OclCompare::countConceptsWithMapping($coll_source, $cc, $c, $mapsource);
			$count_unique_mapcodes = OclCompare::countUniqueMappings($coll_source, $cc, $c, $mapsource);
			echo '<td>' . $count_mapped_concepts . ' / ' . $count_unique_mapcodes . '</td>';
		}

		// Subtotal
		if ($group->display_subtotal_column) {
			$c = $group->col_subtotal->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
			$count_mapped_concepts = OclCompare::countConceptsWithMapping($coll_source, $cc, $c, $mapsource);
			$count_unique_mapcodes = OclCompare::countUniqueMappings($coll_source, $cc, $c, $mapsource);
			echo '<td class="subtotal">' . $count_mapped_concepts . ' / ' . $count_unique_mapcodes . '</td>';
		}
	}

	// Grand total
	$c = $colGrandTotal->getStateConceptList(OCL_COMPARE_STATE_SUMMARY);
	$count_mapped_concepts = OclCompare::countConceptsWithMapping($coll_source, $cc, $c, $mapsource);
	$count_unique_mapcodes = OclCompare::countUniqueMappings($coll_source, $cc, $c, $mapsource);
	echo '<td class="grandtotal">' . $count_mapped_concepts . ' / ' . $count_unique_mapcodes . '</td>';
}

echo '</tfoot>';
echo "</table>";


/****************************************************************************************
**  SUPPORT OBJECTS
****************************************************************************************/

class OclCompare
{

	public static function countConceptsWithMapping($coll_source, $cc, $c, $mapsource)
	{
		$arr_mapping = OclCompare::getMappings($coll_source, $cc, $c, $mapsource);
		$arr_count = array();
		foreach (array_keys($arr_mapping) as $mapcode) {
			$arr_count = array_merge($arr_count, $arr_mapping[$mapcode]);
		}
		return count($arr_count);
	}
	public static function countUniqueMappings($coll_source, $cc, $c, $mapsource)
	{
		$arr_mapping = OclCompare::getMappings($coll_source, $cc, $c, $mapsource);
		return count($arr_mapping);
	}

    public static function getCollectionCombinations($arr_collection_id,$min_elements=0)
    {
        // Convert collection_id array into format that can be used by OclCompare::getArrayCombinations
        $arr_combo = array();
        foreach ($arr_collection_id as $id) {
            $arr_combo[] = array( array($id), array('') );
        }

        // Get the combinations
        $combinations = array();
        OclCompare::getArrayCombinations($combinations, $arr_combo, $min_elements);
        return $combinations;
    }
    public static function getArrayCombinations(&$combinations, $elements, $min_elements=0, $batch=array(), $i=0)
    {
        if ($i >= count($elements)) 
        {
            // Only add if more than 2 elements
            $count = 0;
            $arr_add = array();
            foreach ($batch as $item) {
                if ($item) {
                    $count++;
                    $arr_add[] = $item;
                }
            }
            if ($count >= $min_elements) $combinations[] = $arr_add;
        } else {
            foreach ($elements[$i] as $element) { 
                OclCompare::getArrayCombinations($combinations, $elements, $min_elements, array_merge($batch, $element), $i + 1); 
            }
        }
    }


	/**
	 * Get map codes belonging to the specified source name.
	 */
	public static function getMappings($coll_source, $cc, $cl, $source_name)
	{
		if (is_array($source_name)) {
			$arr_source_name = $source_name;
		} else {
			$arr_source_name = array($source_name);
		}

		// Build mapping array for CL
		$arr_mapping_cl  =  array();
		$arr_concept_cl  =  $cl->getArray();
		foreach (array_keys($arr_concept_cl) as $dict_name) 
		{
			$css_dict = $coll_source->getDictionary($dict_name);
			foreach (array_keys($arr_concept_cl[$dict_name]) as $concept_id) {
				if (  ($c = $cc->getConcept($concept_id, $css_dict))  ) 
				{
					foreach ($arr_source_name as $source_name) {
						$arr_mapping = $c->getMappingsBySourceName($source_name);
						foreach ($arr_mapping as $map) {
							$arr_mapping_cl[$map->source_code][$css_dict->dict_id.'_'.$concept_id] = $css_dict->dict_id.'_'.$concept_id;
						}
					}
				}
			}
		}
		return $arr_mapping_cl;
	}


	/**
	 * Match mappings
	 */
	public static function matchMappings($coll_source, $cc, $cl1, $cl2, $source_name)
	{
		$arr_mapping_c1 = OclCompare::getMappings($coll_source, $cc, $cl1, $source_name);
		$arr_mapping_c2 = OclCompare::getMappings($coll_source, $cc, $cl2, $source_name);
		//var_dump($arr_mapping_c1, $arr_mapping_c2);

		// Compare mappings arrays
		$cl_match = new ConceptList(null);
		foreach (array_keys($arr_mapping_c1) as $mapcode) 
		{
			if (isset($arr_mapping_c2[$mapcode])) 
			{
				foreach ($arr_mapping_c1[$mapcode] as $str_dictid_conceptid) 
				{
					//if (  !isset($arr_mapping_c2[$mapcode][$str_dictid_conceptid]) || 
					//	  count($arr_mapping_c2[$mapcode]) > 1  ||
					//	  count($arr_mapping_c1[$mapcode]) > 1
					//   )
					//{

						foreach ($arr_mapping_c1[$mapcode] as $str_dictid_conceptid) {
							$arr_dict_concept = explode('_', $str_dictid_conceptid);
							$css_dict = $coll_source->getDictionary($arr_dict_concept[0]);
							$cl_match->addConcept($css_dict->dict_db, $arr_dict_concept[1]);
						}

						foreach ($arr_mapping_c2[$mapcode] as $str_dictid_conceptid) {
							$arr_dict_concept = explode('_', $str_dictid_conceptid);
							$css_dict = $coll_source->getDictionary($arr_dict_concept[0]);
							$cl_match->addConcept($css_dict->dict_db, $arr_dict_concept[1]);
						}

					//}
				}
			}
		}
		//var_dump($cl_match);
		//exit();

		return $cl_match;
	}
}	/* end class OclCompare */


class OclComparisonGroup {
	public $arr_column;
	public $group_name;
	public $display_subtotal_column;
	public $include_in_total;
	public $col_subtotal;
	public function __construct($arr_column=array(), $group_name='Group Name', 
			$display_subtotal_column=true, $include_in_total=true) 
	{
		$this->arr_column = $arr_column;
		$this->group_name = $group_name;
		$this->display_subtotal_column = $display_subtotal_column;
		$this->include_in_total  = $include_in_total;
		$this->col_subtotal = new OclComparisonColumn( $group_name.'_subtotal','Subtotal',OCL_COMPARE_COLUMN_TYPE_SUBTOTAL );
	}
	public function getColumnDisplayCount() {
		return count($this->arr_column) + ($this->display_subtotal_column ? 1 : 0);
	}
}
class OclComparisonColumn {
	public $key;
	public $column_name;
	public $column_type;
	public $mapsource;
	private $arr_state_concept_list = array();
	public function __construct($key='', $column_name='ColumnName', $column_type='', $mapsource='') {
		$this->key = $key;
		$this->column_name = $column_name;
		$this->column_type = $column_type;
		$this->mapsource = $mapsource;
	}
	public function getStateConceptList($state_id) {
		if (isset($this->arr_state_concept_list[$state_id])) return $this->arr_state_concept_list[$state_id];
		return null;
	}
	public function setStateConceptList($state_id, $c) {
		$this->arr_state_concept_list[$state_id] = $c;
	}
}

?>
</body>
</html>