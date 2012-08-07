<?php

require_once(MCL_ROOT . 'ConceptSearchSourceCollection.inc.php');
require_once(MCL_ROOT . 'ConceptSearchSource.inc.php');

class ConceptSearchSourceFactory
{
	/**
	 * Whether to display debug info.
	 */
	public $debug = false;

	/**
	 * Whether to display verbose info.
	 */
	public $verbose = false;

	/**
	 * Loads all source definitions for OpenMRS only mode, which includes the 
	 * default OpenMRS dictionary source and all of its map sources.
	 * @return ConceptSearchSourceCollection
	 */
	public function loadOpenmrsOnlySourceDefinitions($cxn_mcl,
		$mcl_default_concept_dict_db, $mcl_default_concept_dict_name,
		$db_host, $db_uid, $db_pwd)
	{
		// $cxn_mcl points to the actual openmrs database. only map sources exist

		// Add definition for the default dictionary
		$cssc      =  new ConceptSearchSourceCollection();
		$css_dict  =  new ConceptSearchSource();
		$css_dict->setSourceDictionary(MCL_SOURCE_DEFAULT_DICT_ID, 
				$mcl_default_concept_dict_db, $mcl_default_concept_dict_name);
		$css_dict->setConnectionParameters($db_host, $db_uid, $db_pwd);
		$cssc->add($css_dict);

		// Add definitions for map sources
		$cssc->add($this->loadMapSourceDefinitions($css_dict));

		return $cssc;
	}

	/**
	 * Loads all source definitions for MCL Enhanced mode, which includes 
	 * concept dictionaries as defined in the MCL concept_dict table, all
	 * of their map sources, and all MCL Concept Lists.
	 * @return ConceptSearchSourceCollection
	 */
	public function loadEnhancedSourceDefinitions($cxn_mcl) 
	{
		// $cxn_mcl points to the MCL db, so load dictionary definitions from there

		// Load dictionary definitions
		$cssc      =  new ConceptSearchSourceCollection();
		$arr_dict  =  $this->loadDictionaryDefinitions($cxn_mcl);
		$cssc->add($arr_dict);

		// Load concept lists from MCL db
		$cssc->add($this->loadConceptListDefinitions($cxn_mcl, $cssc));

		// Load map sources for each dictionary
		foreach ($arr_dict as $css_dict) {
			$cssc->add($this->loadMapSourceDefinitions($css_dict));
		}

		return $cssc;
	}

	/**
	 * Load dictionary source definitions from the MCL database in
	 * enhanced mode.
	 * @return Array of ConceptSearchSource objects
	 */
	public function loadDictionaryDefinitions($cxn_mcl) 
	{
		// load dictionary sources
		$arr_dict = array();
		$sql_dict = 'select * from concept_dict where active = 1 order by sort_order, dict_name';
		if (!($rsc_dict = mysql_query($sql_dict))) {
			trigger_error('Could not load dictionary sources: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
		if (  $this->debug  ) {
			echo '<p><b>Loading dictionary sources:</b><br> ' . $sql_dict . '</p>';
		}
		while ($row = mysql_fetch_assoc($rsc_dict)) 
		{
			$css = new ConceptSearchSource();
			$css->setSourceDictionary(
					$row[  'dict_id'        ], 
					$row[  'db_name'        ], 
					$row[  'dict_name'      ], 
					$row[  'fulltext_mode'  ], 
					$row[  'last_updated'   ]
				);
			$css->setConnectionParameters(
					$row[  'host'  ], 
					$row[  'uid'   ], 
					$row[  'pwd'   ]
				);
			$arr_dict[] = $css;
		}
		return $arr_dict;
	}

	/**
	 * Load concept list definitions from the MCL database in enhanced mode.
	 * @return Array of ConceptSearchSource objects
	 */
	public function loadConceptListDefinitions($cxn_mcl, ConceptSearchSourceCollection $cssc) 
	{
		// Load concept list definitions
		$arr_list  =  array();
		$sql_list  =  'select concept_list_id, list_name from concept_list where active = 1 order by list_name';
		if (  !($rsc_list = mysql_query($sql_list, $cxn_mcl))  ) {
			trigger_error('Could not load concept list definitions: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
		if (  $this->debug  ) {
			echo '<p><b>Loading concept list definitions:</b><br> ' . $sql_list . '</p>';
		}
		while (  $row = mysql_fetch_assoc($rsc_list)  ) 
		{
			// Create the object
			$css = new ConceptSearchSource();
			$css->setSourceList(  $row['concept_list_id']  ,  $row['list_name']  );
			$arr_list[  $row['concept_list_id']  ] = $css;
		}

		// Load dictionary sources for the each list		
		$sql_list_dict_id = 'select distinct concept_list_id, dict_id from concept_list_map';
		if (  !($rsc_list_dict_id = mysql_query($sql_list_dict_id, $cxn_mcl))  ) {
			trigger_error('Could not load concept list definitions: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
		while (  $row_dict_id = mysql_fetch_assoc($rsc_list_dict_id)  )  
		{
			$css_dict  =  $cssc->getDictionary(  $row_dict_id['dict_id']  );
			if ($css_dict && isset($arr_list[$row_dict_id['concept_list_id']])) {
				$arr_list[$row_dict_id['concept_list_id']]->addDictionarySource(  $css_dict  );
			}
		}

		return $arr_list;
	}

	/**
	 * Load the Map Source definitions from the passed concept dictionary
	 * definition.
	 * @param ConceptSearchSource	$css_dict	ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY
	 * @return Array of ConceptSearchSource objects
	 */
	public function loadMapSourceDefinitions(ConceptSearchSource $css_dict) 
	{
		// Make sure the passed source is a dictionary
		if (  $css_dict->type != MCL_SOURCE_TYPE_DICTIONARY  )  {
			trigger_error('Parameter $css_dict must be of type MCL_SOURCE_TYPE_DICTIONARY', E_USER_ERROR);
		}

		// Connect to the database
		$cxn  =  $css_dict->getConnection();
		mysql_select_db(  $css_dict->dict_db  ,  $cxn  );

		// Build the sql
		$sql_cs = 
			'select cs.concept_source_id, cs.name, cs.description, cs.retired ' .
			'from concept_source cs ' . 
			'order by cs.name';
		if (  $this->debug  ) {
			echo '<p><b>Loading map source definitions for ' . $css_dict->dict_db . ':</b><br> ' . $sql_cs . '</p>';
		}

		// Execute the query
		$rsc_cs  =  mysql_query(  $sql_cs  ,  $cxn  );
		if (  !$rsc_cs  ) {
			trigger_error('Could not load map source definitions: ' . mysql_error(), E_USER_ERROR);
		}

		// put the data into an array
		$arr_source  =  array();
		while (  $row = mysql_fetch_assoc($rsc_cs)  )
		{
			$css = new ConceptSearchSource();
			$css->setSourceMap(
					$css_dict->dict_id             , 
					$css_dict->dict_db             , 
					$css_dict->dict_name           , 
					$row[  'concept_source_id'  ]  , 
					$row[  'name'               ]
				);
			$css->addDictionarySource(  $css_dict  );
			$arr_source[] = $css;
		}

		return $arr_source;
	}

	/**
	 * Load concept classes for all dictionaries in the passed source collection.
	 * @return ConceptClassCollection
	 */
	public function loadAllConceptClasses(ConceptSearchSourceCollection $coll_source)
	{
		$ccc = new ConceptClassCollection();
		foreach ($coll_source->getDictionaries() as $css_dict) {
			$ccc->merge($this->loadConceptClasses($css_dict));
		}
		return $ccc;
	}

	/**
	 * Load concept datatypes for all dictionaries in the passed source collection.
	 * @return ConceptDatatypeCollection
	 */
	public function loadAllConceptDatatypes(ConceptSearchSourceCollection $coll_source)
	{
		$cdc = new ConceptDatatypeCollection();
		foreach ($coll_source->getDictionaries() as $css_dict) {
			$cdc->merge($this->loadConceptDatatypes($css_dict));
		}
		return $cdc;
	}

	/**
	 * Loads the concept datatypes from the specified dictionary.
	 * @return ConceptDatatypeCollection 
	 */
	public function loadConceptDatatypes(ConceptSearchSource $css_dict)
	{
		// get the data
		$sql_datatypes = 
			'select concept_datatype_id, name, description, hl7_abbreviation, uuid ' .
			'from concept_datatype ' . 
			'where retired != 1 ' . 
			'order by name';
		if ($this->debug) {
			echo '<p><b>Loading concept datatypes for ' . $css_dict->dict_db . ':</b><br> ' . $sql_datatypes . '</p>';
		}
		$rsc_datatypes = mysql_query($sql_datatypes, $css_dict->getConnection());
		if (!$rsc_datatypes) {
			echo "Could not query db in ConceptSearchFactory::_loadConceptDatatypes: " . mysql_error();
		}

		// Create the datatype collection
		$cdc = new ConceptDatatypeCollection();
		while ($row = mysql_fetch_assoc($rsc_datatypes)) 
		{
			$cd = new ConceptDatatype($row['concept_datatype_id'], $row['name'], 
					$row['description'], $row['hl7_abbreviation'], $row['uuid']);
			$cd->setSourceDictionary($css_dict);
			
			// $key = '<database_name>:datatype(<concept_datatype_id>) 
			$key = $css_dict->dict_db . ':datatype(' . $row['concept_datatype_id'] . ')';

			$cdc->Add($key, $cd);
		}

		return $cdc;
	}

	/**
	 * Loads the concept classes from the specified dictionary.
	 * @return ConceptClassCollection 
	 */
	public function loadConceptClasses(ConceptSearchSource $css_dict)
	{
		// get the data
		$sql_classes = 
			'select concept_class_id, name, description, uuid ' .
			'from concept_class ' . 
			'where retired != 1 ' .
			'order by name';
		if ($this->debug) {
			echo '<p><b>Loading concept classes for <strong>' . $css_dict->dict_db . 
				'</strong>:</b><br> ' . $sql_classes . '</p>';
		}
		$rsc_classes = mysql_query($sql_classes, $css_dict->getConnection());
		if (!$rsc_classes) {
			echo "could not query db: " . mysql_error();
		}
			
		// Create the class collection
		$ccc = new ConceptClassCollection();
		while ($row = mysql_fetch_assoc($rsc_classes)) 
		{
			$cd = new ConceptClass($row['concept_class_id'], $row['name'], 
					$row['description'], $row['uuid']);
			$cd->setSourceDictionary($css_dict);
			
			// $key = '<database_name>:class(<concept_class_id>) 
			$key = $css_dict->dict_db . ':class(' . $row['concept_class_id'] . ')';

			$ccc->Add($key, $cd);
		}

		return $ccc;
	}
}

?>