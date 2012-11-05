<?php
/****************************************************************************************************
** ConceptSearchFactory.inc.php
**
** CSF performs a concept search based on the query defined in a ConceptSearch object. 
** A ConceptSearchResults object is returned.
** --------------------------------------------------------------------------------------------------
** TODO:
**	- Support OpenMRS v1.6 and v1.9 simultaneously (currently only supports v1.6)
*****************************************************************************************************/


require_once(MCL_ROOT . 'fw/Collection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchGroup.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchTermCollection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchTerm.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchResultsGroup.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchResults.inc.php');
require_once(MCL_ROOT . 'fw/ConceptCollection.inc.php');
require_once(MCL_ROOT . 'fw/Concept.inc.php');
require_once(MCL_ROOT . 'fw/ConceptMapping.inc.php');
require_once(MCL_ROOT . 'fw/ConceptDescription.inc.php');
require_once(MCL_ROOT . 'fw/ConceptName.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchSqlCollection.inc.php');


/**
 * ConceptSearchFactory is used to search the concept dictionary.
 */
class ConceptSearchFactory
{
	/**
	 * Whether to display debug code.
	 */
	public $debug = false;

	/**
	 * Whether to display verbose information.
	 */
	public $verbose = false;

	/**
	 * ConceptSearchSourceCollection object that contains definitions of all 
	 * concept sources that ConceptSearch can search. 
	 */
	private $coll_source = null;

	/**
	 * Array of search term objects.
	 */
	private $arr_search_term = array();


	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing
	}
 
	/**
	 * Set ConceptSearchSourceCollection object
	 * @param ConceptSearchSourceCollection $coll_source
	 */
	public function setConceptSearchSourceCollection(ConceptSearchSourceCollection $coll_source)
	{
		$this->coll_source  =  $coll_source;
	}

	/**
	 * Execute the search described in the ConceptSearch object.
	 * @param ConceptSearch $cs
	 * @return ConceptSearchResults
	 */
	public function search(ConceptSearch $cs) 
	{
		// Display the search groups if in debug mode
		if ($this->debug) {
			echo '<pre style="font-size:8pt;">', var_dump($cs->arr_search_group), '</pre>';
		}

		// Start the stopwatch
		$start_time  =  microtime(true);

		// Perform the base concept search - this queries just the concept table based on the search criteria
		$cc  =  $this->_loadConcepts($cs);

		// TODO: Pagination - possibly through use of temp table on the MCL or default database

		// Load meta-data for the concepts returned by _loadConcepts 
		if (  $cc->getCount()  )
		{
			// Iterate through dictionary sources in the collection
			foreach (  $cc->getDictionarySources() as $dict_db  ) 
			{
				$css_dict  =  $cs->getAllSources()->getDictionary($dict_db);

				// Load all the concept data for this search
				if (  $cs->load_descriptions        )  $this->_loadConceptDescriptions (  $css_dict  ,  $cs  ,  $cc  );
				if (  $cs->load_concept_attributes  )  $this->_loadConceptAttributes   (  $css_dict  ,  $cs  ,  $cc  );
				if (  $cs->load_qa_sets             )  $this->_loadQASets              (  $css_dict  ,  $cs  ,  $cc  );
				if (  $cs->load_mappings            )  $this->_loadMappings            (  $css_dict  ,  $cs  ,  $cc  );
				if (  $cs->load_concept_sets        )  $this->_loadConceptSets         (  $css_dict  ,  $cs  ,  $cc  );
				if (  $cs->load_concept_list_names  )  $this->_loadConceptListNames    (  $css_dict  ,  $cs  ,  $cc  );

				// Load concept names last - this is last so that it can get names for all the dependencies as well
				$this->_loadConceptNames(  $css_dict  ,  $cs  ,  $cc  );
			}

			// Assign relevancies - after all names, descriptions, mappings loaded
			foreach ($cc->getSearchResultsGroupIds() as $csrg_id)
			{
				$csrg  =  $cc->getSearchResultsGroup(  $csrg_id  );
				$this->_assignRelevancy($csrg);
				//echo $csrg->toString();
			}
		}
		else
		{
			// Set debug info
			if (  $this->debug  ||  $this->verbose  )  {
				echo '<p>No concepts returned in this search</p>';
			}
		}

		// Stop the stopwatch
		$stop_time = microtime(true);

		// Creat the search results object
		$csr = new ConceptSearchResults(  $cs  ,  $cc  );
		$csr->start_time  =  $start_time  ;
		$csr->stop_time   =  $stop_time   ;

		return $csr;
	}

	/**
	 * Get a ConceptSearchSourceCollection of sources for the specified search group
	 * @param ConceptSearchGroup $csg
	 * @param ConceptSearch $cs
	 * @return ConceptSearchSourceCollection
	 */
	private function getSearchGroupSources(ConceptSearch $cs, ConceptSearchGroup $csg)
	{
		if (  $arr_source = $csg->getInlineSources()  )
		{
			// TODO: Get ConceptSearchSource objects for the referenced inline sources
			return null;
		}
		else 
		{
			return $cs->getSelectedSources();
		}
	}

	/**
	 * Return a ConceptCollection containing the concepts that match the search
	 * criteria described in the passed ConceptSearch object. It iterates through 
	 * each search group. This function only returns fields from the concept table. 
	 * It is intended to be followed by other functions to retrieve additional 
	 * concept info.
	 * @param ConceptSearch $cs
	 * @access private
	 */
	private function _loadConcepts(ConceptSearch $cs)
	{
		$cc       =  new ConceptCollection();
		$csrg     =  null;
		$group_i  =  0;

		// Iterate through ConceptSearchGroup objects, perform base query and
		// create the concepts for each
		//var_dump($cs);
		foreach (  array_keys($cs->arr_search_group) as $group_key  ) 
		{
			$group_i++;
			$csg   =  $cs->arr_search_group[  $group_key  ];
			$csrg  =  new ConceptSearchResultsGroup(  $csg  );
			$ctsc  =  $csg->getSearchTermCollection();
			$cc->addGroup(  $csrg  );

			// Get sql statements for this search group
			$coll_csg_sql        =  $this->_buildSqlFromConceptSearchGroup(  $cs  ,  $csg  );
			$csrg->coll_sql_obj  =  $coll_csg_sql;

			// Debug info for the search group
			if (  $this->debug  ||  $this->verbose  )  {
				echo '<p><b>Concept Search Group ' . $group_i . ':</b> ' . $csg->query . '<br><ul>';
			}

			// Iterate through the sql collection and execute
			foreach ($coll_csg_sql->getKeys() as $key)
			{
				$sql_obj  =  $coll_csg_sql->Get($key);
 
				// Debug info for sql statements
				if (  $this->debug  ||  $this->verbose  )  
				{
					echo '</li><strong>' . $sql_obj->source->getKey();
					if ($sql_obj->css_sub_list_dict) echo ' - ' . $sql_obj->css_sub_list_dict->getKey(); 
					echo ' : </strong> ';
					if ($sql_obj->sql)  echo htmlentities($sql_obj->sql);
					else  echo '<em>[[ EMPTY SEARCH QUERY ]]</em></li>';
				}

				// Skip if no sql
				if (  !$sql_obj->sql  )  continue;

				// Get the connection and execute
				$css_dict    =  $sql_obj->getDictionarySource();
				$conn        =  $css_dict->getConnection();
				$rsc_search  =  mysql_query(  $sql_obj->sql  ,  $conn  );
				if (  !$rsc_search  ) {
					trigger_error("could not query db in ConceptSearchFactory::_loadConcepts: " . mysql_error());
				}

				// Create/get the concepts and add to the collection
				while (  $row = mysql_fetch_assoc($rsc_search)  ) 
				{
					// Create the concept if it does not already exist
					$c  =  null;
					if (  !($c  =  $cc->getConcept($row['concept_id'], $css_dict))  ) 
					{
						$c = new Concept(
								$row[  'concept_id'     ],
								$row[  'retired'        ],
								$row[  'is_set'         ],
								$row[  'class_id'       ],
								$row[  'class_name'     ],
								$row[  'datatype_id'    ],
								$row[  'datatype_name'  ]
							);
						$c->uuid    =  $row['uuid'];		// keeping this separate to add version compatibility later
						if (  $row['date_created' ]  )  $c->setAttribute(  'Date created'  ,  $row['date_created' ]  );
						if (  $row['retired_by'   ]  )  $c->setAttribute(  'Date Retired'  ,  $row['retired_by'   ]  );
						if (  $row['date_retired' ]  )  $c->setAttribute(  'Retired by'    ,  $row['date_retired' ]  );
						if (  $row['retire_reason']  )  $c->setAttribute(  'Retire reason' ,  $row['retire_reason']  );
						$c->css_dict  =  $css_dict;
						$cc->addConcept(  $c  );
					}					

					// Relevancy - assign Fulltext Search relevancy only for now
					$relevancy  =  null;
					if (  isset($row['relevancy'])  )  $relevancy = $row['relevancy'];

					// Add to the search results group 
					$csrg->addConcept(  $c  ,  $relevancy  );
				}

				// Debug/verbose info
				if ($this->debug || $this->verbose) {
					echo '<ul><li><strong>' . $csrg->getCount($css_dict) . ' concept(s) returned:</strong> ' . 
							implode(', ', $csrg->getConceptIds($css_dict));
					echo '</li></ul></li>';
				}

			}	// end ConceptSearchSqlCollection loop

			// End debug output
			if (  $this->debug  ||  $this->verbose  )  {
				echo '</ul></p>';
			}

		}	// end ConceptSearchGroup loop

		return $cc;
	}

	/**
	 * Assigns relevancy to all visible terms
	 * NOTE: This is inefficient because it requires an additional pass through ALL concepts.
	 * Probably better to replace this with individual SQL queries 
	 */
	private function _assignRelevancy(ConceptSearchResultsGroup $csrg)
	{
		// Prepare arrays of search terms used to assign concept relevancy
		$ctsc  =  $csrg->csg->getSearchTermCollection();
		$arr_numeric_term_type    =  array(
				MCL_SEARCH_TERM_TYPE_UUID,
				MCL_SEARCH_TERM_TYPE_CONCEPT_ID,
				MCL_SEARCH_TERM_TYPE_CONCEPT_ID_RANGE,
				MCL_SEARCH_TERM_TYPE_MAP_CODE
			);
		$arr_numeric_search_term  =  $ctsc->getSearchTerms($arr_numeric_term_type, null, true);
		$arr_text_term_type       =  array(
				MCL_SEARCH_TERM_TYPE_TEXT,
				MCL_SEARCH_TERM_TYPE_CONCEPT_NAME
			);
		$arr_text_search_term     =  $ctsc->getSearchTerms($arr_text_term_type, null, true);

		// Iterate through each dictionary source in the group
		foreach ($csrg->getDictionarySources() as $dict_key) 
		{
			// Iterate through concepts in the current dictionary source
			$arr_concept_id  =  $csrg->getVisibleConceptIds(  $dict_key  );
			foreach ($arr_concept_id as $_concept_id)
			{
				// Get the concept and relevancy
				$c  =  $csrg->getConcept(  $_concept_id  ,  $dict_key  );
				$relevancy  =  $csrg->getRelevancy($c);

				// Bump up relevancy for exact matches: CONCEPT_ID, ID RANGES, MAP_CODE, UUID
				if ($relevancy < MCL_MAX_RELEVANCY) {
					foreach ($arr_numeric_search_term as $search_term) {
						if ($search_term->isMatch($c)) {
							$relevancy = MCL_MAX_RELEVANCY;
							break;
						}
					}
				}

				// Bump up exact name matches: TEXT, NAME
				if ($relevancy < MCL_MAX_RELEVANCY) 
				{
					// loop through each concept name
					foreach ($c->getConceptNameIds() as $concept_name_id) {
						$concept_name = $c->getConceptName($concept_name_id)->name;
						if (str_word_count($concept_name) !== count($arr_text_search_term))  break;
						$match = true;
						foreach ($arr_text_search_term as $search_term) {
							if (!$search_term->isMatch($c, MCL_SEARCH_TERM_TYPE_CONCEPT_NAME)) {
								$match = false;
								break;
							}
						}
						if ($match) {
							$relevancy = MCL_MAX_RELEVANCY;
							break;
						}
					}
				}

				// Set relevancy
				$csrg->setRelevancy($c, $relevancy);

			}	// End concept loop

		}	// End dictionary source loop

	}

	/**
	 * Load the names of the concept lists that are associated with the concepts
	 * in the concept collection.
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptListNames(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// TODO: This function needs the MCL connection and should only run if in enhanced mode

		// build the sql to determine list membership
		$csv_concept_id = $cc->getVisibleConceptIdCsv($css_dict);
		$sql_concept_lists = 
			'select clm.concept_list_id, clm.concept_id ' . 
			'from mcl.concept_list_map clm ' . 
			'where clm.concept_id in (' . $csv_concept_id . ')';
		if ($this->debug) {
			echo '<p><b>Loading parent concept lists for ' . $css_dict->dict_db . ':</b><br> ' . $sql_concept_lists . '</p>';
		}

		// do the query
		$rsc_concept_lists = mysql_query($sql_concept_lists, $css_dict->getConnection());
		if (!$rsc_concept_lists) {
			trigger_error("could not query db in ConceptSearchFactory::_loadConceptListNames: " . mysql_error(), E_USER_ERROR);
		}

		// Add concept lists to the concepts
		$arr_concept_list_id = array();		// stores unique ids for just the current dictionary source
		while ($row = mysql_fetch_assoc($rsc_concept_lists)) 
		{
			if (  $c  =  $cc->getConcept($row['concept_id'], $css_dict)  ) 
			{
				$_concept_list_id  =  $row['concept_list_id']; 
				$c->addConceptList($_concept_list_id);
				$arr_concept_list_id[$_concept_list_id] = $_concept_list_id;
			}
		}
	}

	/**
	 * Load concept attributes, such as numeric ranges.
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptAttributes(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// build the sql to load concept numeric ranges
		$csv_concept_id = $cc->getVisibleConceptIdCsv($css_dict);
		$sql_attr = 
			'select cnum.concept_id, cnum.hi_absolute, cnum.hi_critical, cnum.hi_normal, ' .
				'cnum.low_absolute, cnum.low_critical, cnum.low_normal, ' .
				'cnum.units, cnum.precise ' .
			'from concept_numeric cnum ' . 
			'where cnum.concept_id in (' . $csv_concept_id . ')';
		if ($this->debug) {
			echo '<p><b>Loading concept attributes for ' . $css_dict->dict_db . ':</b><br> ' . $sql_attr . '</p>';
		}
		$rsc_attr = mysql_query($sql_attr, $css_dict->getConnection());
		if (!$rsc_attr) {
			echo "could not query db in ConceptSearchFactory::_loadConceptAttributes: " . mysql_error();
		}

		// Add numeric range to the concepts
		while ($row = mysql_fetch_assoc($rsc_attr)) {
			$_concept_id = $row['concept_id'];
			$c  =  $cc->getConcept($_concept_id, $css_dict);
			if ($c) {
				$range = new ConceptNumericRange(
						$row[  'hi_absolute'   ], 
						$row[  'hi_critical'   ], 
						$row[  'hi_normal'     ],
						$row[  'low_absolute'  ], 
						$row[  'low_critical'  ], 
						$row[  'low_normal'    ],
						$row[  'units'         ], 
						$row[  'precise'       ]
					);
				$c->setNumericRange($range);
			}
		}
	}

	/**
	 * Load concept sets. 
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptSets(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// load the ENTIRE concept set hierarchy for this dictionary source 
		$sql_concept_set = 
			'select concept_id, concept_set ' . 
			'from concept_set ' . 
			'order by sort_weight';
		if ($this->debug) {
			echo '<p><b>Loading concept set hierarchy for ' . $css_dict->dict_db . ':</b><br> ' . $sql_concept_set . '</p>';
		}
		$rsc_concept_set = mysql_query($sql_concept_set, $css_dict->getConnection());
		if (!$rsc_concept_set) {
			echo "could not query db in ConceptSearchFactory::_loadConceptSets: " . mysql_error();
		}

		// build the ENTIRE concept set hierarchy for this dictionary source
		$arr_child_parent = array();
		$arr_parent_child = array();
		while ($row = mysql_fetch_assoc($rsc_concept_set)) 
		{
			$child_id   =  $row[  'concept_id'  ];
			$parent_id  =  $row[  'concept_set' ];
			$arr_child_parent[  $child_id   ][]  =  $parent_id;
			$arr_parent_child[  $parent_id  ][]  =  $child_id;
		}

		// add parents to the children for the concepts in this ConceptCollection
		foreach (array_keys($arr_child_parent) as $_child_id) 
		{
			if (  !($_child_concept = $cc->getConcept($_child_id, $css_dict))  ) 
			{
				$_child_concept           =  new Concept($_child_id);
				$_child_concept->display  =  false;
				$_child_concept->css_dict =  $css_dict;
				$cc->addConcept($_child_concept);
			}
			foreach ($arr_child_parent[$_child_id] as $_parent_id) {
				$_child_concept->addParent($_parent_id);
			}
		}

		// add children to the parents for the concepts in this ConceptCollection
		foreach (array_keys($arr_parent_child) as $_parent_id) 
		{
			if (  !($_parent_concept = $cc->getConcept($_parent_id, $css_dict))  ) 
			{
				$_parent_concept            =  new Concept($_parent_id);
				$_parent_concept->display   =  false;
				$_parent_concept->css_dict  =  $css_dict;
				$cc->addConcept($_parent_concept);
			}
			foreach ($arr_parent_child[$_parent_id] as $_child_id) {
				$_parent_concept->addChild($_child_id);
			}
		}
	}

	/**
	 * Load mappings.
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadMappings(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// Get CSV of visible concept IDs that are members of the current dictionary source
		$csv_concept_id = $cc->getVisibleConceptIdCsv($css_dict);

		// Build the SQL based on the version of the dictionary
		if ($css_dict->version == MCL_OMRS_VERSION_1_6)
		{
			// build the sql
			$sql_maps = 
				'select ' .
					'cm.concept_map_id AS concept_map_id, ' .
					'cm.concept_id AS concept_id, ' .
					'cm.source AS source_id, ' . 
					'cm.source_code AS map_code, ' .
					'cs.name AS source_name ' . 
				'from concept_map cm ' . 
				'left join concept_source cs on cs.concept_source_id = cm.source ' .
				'where cm.concept_id in (' . $csv_concept_id . ')';
		}
		elseif ($css_dict->version == MCL_OMRS_VERSION_1_9)
		{
			$sql_maps =
					'select  ' .
						'crm.concept_map_id AS concept_map_id, ' .
						'crm.concept_id AS concept_id, ' .
						'crt.concept_source_id AS source_id, ' .
						'crt.code AS map_code, ' .
						'crs.name AS source_name ' .
					'from openmrs19.concept_reference_map crm ' .
					'left join openmrs19.concept_reference_term crt on crt.concept_reference_term_id = crm.concept_reference_term_id ' .
					'left join openmrs19.concept_reference_source crs on crs.concept_source_id = crt.concept_source_id ' .
					'where crm.concept_id in (' . $csv_concept_id . ')';
		}
		else
		{
			trigger_error('Dictionary "' . $css_dict->dict_name . 
					'" version must be 1.6 or 1.9', E_USER_ERROR);
		}
		if ($this->debug) {
			echo '<p><b>Loading concept mappings for ' . $css_dict->dict_db . ':</b><br> ' . $sql_maps . '</p>';
		}

		// Perform the query
		$rsc_maps = mysql_query($sql_maps, $css_dict->getConnection());
		if (!$rsc_maps) {
			echo "could not query db in ConceptSearchFactory::_loadMappings: " . mysql_error();
		}

		// Add mappings to the concepts
		while ($row = mysql_fetch_assoc($rsc_maps)) 
		{
			$_concept_id = $row['concept_id'];
			$c  =  $cc->getConcept($_concept_id, $css_dict);
			if ($c) {
				$c->addMapping(new ConceptMapping(
						$row[  'concept_map_id'  ],
						$row[  'source_id'       ], 
						$row[  'map_code'        ], 
						$row[  'source_name'     ]
					));
			}
		}
	}

	/**
	 * Load question/answer sets
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadQASets(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// build the sql
		$csv_concept_id = $cc->getVisibleConceptIdCsv($css_dict);
		$sql_qa =
			'select ca.concept_id, ca.answer_concept ' . 
			'from concept_answer ca ' . 
			'where ca.concept_id in (' . $csv_concept_id . ') ' . 
			'or ca.answer_concept in (' . $csv_concept_id . ')';
		if ($this->debug) {
			echo '<p><b>Loading Question/Answer Sets for ' . $css_dict->dict_db . ':</b><br> ' . $sql_qa . '</p>';
		}

		// do the query
		$rsc_qa = mysql_query($sql_qa, $css_dict->getConnection());
		if (!$rsc_qa) {
			echo "could not query db in ConceptSearchFactory::_loadQASets: " . mysql_error();
		}

		// add questions and answers to the concepts
		while ($row = mysql_fetch_assoc($rsc_qa)) 
		{
			$_question_id  =  $row[  'concept_id'      ];
			$_answer_id    =  $row[  'answer_concept'  ];
			
			if (  $question  =  $cc->getConcept(  $_question_id  ,  $css_dict  )  )  
			{
				$question->addAnswer($_answer_id);
			}
			if (  $answer    =  $cc->getConcept(  $_answer_id    ,  $css_dict  )  )  
			{
				$answer->addQuestion($_question_id);
			}
		}
	}

	/**
	 * Load the descriptions for only the base concepts (not the dependencies).
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptDescriptions(ConceptSearchSource $css_dict, ConceptSearch $cs, ConceptCollection $cc)
	{
		// build sql
		$csv_concept_id = $cc->getVisibleConceptIdCsv($css_dict);
		$sql_desc = 
			'select cd.concept_description_id, cd.concept_id, cd.description, cd.locale ' .
			'from concept_description cd ' .
			'where cd.concept_id in (' . $csv_concept_id . ')';
		if ($this->debug) {
			echo '<p><b>Loading concept descriptions for ' . $css_dict->dict_db . ':</b><br> ' . $sql_desc . '</p>';
		}

		// do the query
		$rsc_desc = mysql_query($sql_desc, $css_dict->getConnection());
		if (!$rsc_desc) {
			trigger_error("could not query db in ConceptSearchFactory::_loadConceptDescriptions: " . mysql_error(), E_USER_ERROR);
			exit();
		}
		
		// add descriptions to the concepts
		while ($row = mysql_fetch_assoc($rsc_desc)) 
		{
			$_concept_id = $row['concept_id'];
			$c  =  $cc->getConcept($_concept_id, $css_dict);
			if ($c) {
				$c->addDescription(new ConceptDescription(
						$row[  'concept_description_id'  ],
						$row[  'description'             ], 
						$row[  'locale'                  ]
					));
			}
		}
	}

	/**
	 * Loads the concept names for the base concepts, and any of the concept dependencies.
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptSearch $cs
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptNames(
			ConceptSearchSource $css_dict, 
			ConceptSearch $cs, 
			ConceptCollection $cc
		)
	{
		// Get concept IDs for all concepts (not just visible ones)
		$csv_concept_id     =  $cc->getConceptIdCsv(  $css_dict  )  ;
		$csv_qa             =  $cc->getQAIdCsv     (  $css_dict  )  ;

		// Build SQL
		$sql_concept_names  =
			'select cn.concept_name_id, cn.concept_id, cn.name, ' . 
				'cn.locale, cntm.concept_name_tag_id, cn.uuid ' .
			'from concept_name cn ' .
			'left join concept_name_tag_map cntm on cntm.concept_name_id = cn.concept_name_id ' .
			'where cn.concept_id in (' . $csv_concept_id . ') or ' . 
				'cn.concept_id in (' . 
					'select cs1.concept_id from concept_set cs1 ' .
					'union ' .
					'select cs2.concept_set from concept_set cs2 ' .
				') ';
		if (  $csv_qa       )  {
			$sql_concept_names  .=  'or cn.concept_id in (' . $csv_qa . ') ';
		}
		if (  $this->debug  )  {
			echo '<p><b>Loading concept names and synonyms for ' . $css_dict->dict_db . 
					':</b><br> ' . $sql_concept_names . '</p>';
		}

		// do the query
		$rsc_concept_names  =  mysql_query(  $sql_concept_names  ,  $css_dict->getConnection()  );
		if (  !$rsc_concept_names  )  {
			trigger_error("could not query db in ConceptSearchFactory::_loadConceptNames: " . mysql_error(), E_USER_ERROR);
			exit();
		}

		// add names to the ConceptCollection
		while (  $row = mysql_fetch_assoc($rsc_concept_names)  )
		{
			$_concept_id  =  $row[  'concept_id'  ];
			$cn  =  new ConceptName(
					$row[  'concept_name_id'      ], 
					$row[  'name'                 ], 
					$row[  'locale'               ], 
					$row[  'concept_name_tag_id'  ]
				);
			$cn->uuid  =  $row[  'uuid'  ];	// keeping this separate to add version compatibility later
			$c  =  $cc->getConcept(  $_concept_id  ,  $css_dict  );
			if (  !$c  ) {
				$c            =  new Concept($_concept_id)  ;
				$c->display   =  false                      ;
				$c->css_dict  =  $css_dict                  ;
				$cc->addConcept($c);
			}
			$c->addName($cn);
		}
	}

	/**
	 * Recursive function used by ConceptSearchFactory::_buildSqlFromConceptSource
	 * for each ConceptSearchTermCollection to create the criteria (the sql where clause).
	 * If $apply_cs_filter is true, ConceptSearch filter will still only be applied if 
	 * filter_scope is TEXT and if a filter has been set in ConceptSearch. If false, the 
	 * filter will not be applied regardless of settings, which is the case for recursive 
	 * calls to _buildSqlCriteria.
	 */
	private function _buildSqlCriteria(
			ConceptSearch                $cs, 
			ConceptSearchTermCollection  $cstc,
			ConceptSearchSource          $css, 
			ConceptSearchSource          $css_sub_list_dict = null, 
			$apply_cs_filter = true
		)
	{
		$arr_criteria    =  array();	// array of sql criteria to be glued together by cstc->glue
		$arr_csg_filter  =  array();	// explicit CSG level filters, e.g. "in" and "notin" operators


		// Set the source dictionary and get the database connection
		if ($css_sub_list_dict) $css_dict = $css_sub_list_dict;
		else $css_dict = $css;
		$conn  =  $css_dict->getConnection();

		// ALL - get all concepts
		$is_all_search = false;
		// TODO: The 'is_all_search' variable is a complete hack used to apply global filters to
		// full list/mapsource queries. There needs to be a better way to split between 
		// terms that should be filtered and those that should not.
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_ALL)) 
		{
			$is_all_search = true;
		}

		// Child Collections
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_COLLECTION)) 
		{
			foreach ($arr_search_term as $search_object) {
				$child_apply_cs_filter = false;
				if ($search_object->glue == MCL_CONCEPT_SEARCH_GLUE_AND) $child_apply_cs_filter = true;
				// TODO: The $child_apply_cs_filter logic is a HUGE hack and needs to be fixed
				$arr_criteria[] = $this->_buildSqlCriteria($cs, $search_object, 
						$css, $css_sub_list_dict, $child_apply_cs_filter);
			}
		}

		// Concept ID - 
		//   Glue = OR : c.concept_id in (...) 
		//   Glue = AND : c.concept_id = ... and c.concept_id = ...
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_CONCEPT_ID)) 
		{
			if ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_OR) {
				$arr_concept_id = array();
				foreach ($arr_search_term as $search_object) {
					$arr_concept_id[] = $search_object->needle;
				}
				$arr_criteria[] = 'c.concept_id in (' . implode(',',$arr_concept_id) . ')';
			} 
			elseif ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_AND) {
				foreach ($arr_search_term as $search_object) {
					$arr_criteria[] = 'c.concept_id = ' . $search_object->needle;
				}
			}
		}

		// Concept ID Range
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_CONCEPT_ID_RANGE))
		{
			foreach ($arr_search_term as $search_object) {
				list($min, $max) = $search_object->getRange();
				$arr_criteria[] = 'c.concept_id >= ' . $min . ' and c.concept_id <= ' . $max;
			}
		}

		// Map Code - Numeric only
		//   Glue = OR : c.concept_id in (select cm.concept_id from concept_map cm
		//					where cm.source_code in (...))
		//   Glue = AND : c.concept_id in (select cm.concept_id from concept_map cm
		//					where cm.source_code = ... )
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_MAP_CODE, true)) 
		{
			if ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_OR) {
				$arr_map_code_numeric = array();
				foreach ($arr_search_term as $search_object) {
					$arr_map_code_numeric[] = $search_object->needle;
				}
				$arr_criteria[] = 'c.concept_id in (select cm.concept_id from concept_map cm ' . 
						'where cm.source_code in (' . implode(',',$arr_map_code_numeric) . '))';
			} 
			elseif ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_AND) {
				foreach ($arr_search_term as $search_object) {
					$arr_criteria[] = 'c.concept_id in (select cm.concept_id from concept_map cm ' . 
							'where cm.source_code = ' . $search_object->needle . ')';
				}
			}
		}

		// Map Code - Text only
		//   Glue = OR : c.concept_id in (select cm.concept_id from concept_map cm
		//					where cm.source_code in (...))
		//   Glue = AND : c.concept_id in (select cm.concept_id from concept_map cm
		//					where cm.source_code = ... )
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_MAP_CODE, false)) 
		{
			foreach ($arr_search_term as $search_term) {
				$arr_criteria[] = 
					"c.concept_id in (select cm.concept_id from concept_map cm " . 
					"where cm.source_code regexp '[[:<:]]" .
					mysql_real_escape_string($search_term->needle, $conn) . "')";
			}
		}

		// Map Code Range
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_MAP_CODE_RANGE)) 
		{
			list($min, $max) = $search_object->getRange();
			$arr_map_code_numeric = array();
			for ($i = $min; $i <= $max; $i++) $arr_map_code_numeric[] = $i;
			$arr_criteria[] = 'c.concept_id in (select cm.concept_id from concept_map cm ' . 
					'where cm.source_code in (' . implode(',',$arr_map_code_numeric) . '))';
		}

		// Text - concept names AND descriptions (does not use concept_word)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_TEXT)) 
		{
			// If FTS, combine terms and do a single boolean search
			if (  $css_dict->dict_fulltext_mode == MCL_FULLTEXT_MODE_ON  )  
			{
				$_criteria = '';
				foreach ($arr_search_term as $search_term) {
					if ($_criteria) $_criteria .= ' ';
					$_criteria .= '+' . mysql_real_escape_string($search_term->needle, $conn);
				}
				if ($_criteria) {
					$arr_criteria[] = 
						"c.concept_id in (select cfts.concept_id from concept_fts cfts " . 
						"where match(name, description, source_code) against('" . $_criteria . "' in boolean mode) )";
				}
			}

			// Use the concept_name and concept_description tables directly if no fulltext index
			// NOTE: each search term becomes 2 criteria: 1 for concept_name and one for concept_description
			else {
				
				foreach ($arr_search_term as $search_term)
				{
					$arr_criteria[] = 
						"(" . 
							"c.concept_id in (select cn.concept_id from concept_name cn where cn.name regexp '[[:<:]]" .
							mysql_real_escape_string($search_term->needle, $conn) . "')" . 
						") OR (" . 
							"c.concept_id in (select cd.concept_id from concept_description cd where cd.description regexp '[[:<:]]" .
							mysql_real_escape_string($search_term->needle, $conn) . "')" . 
						")";
				}
			}
		}

		// Concept Name
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_CONCEPT_NAME)) 
		{
			foreach ($arr_search_term as $search_term) 
			{
				$arr_criteria[] = 
					"c.concept_id in (select cn.concept_id from concept_name cn where cn.name regexp '[[:<:]]" .
						mysql_real_escape_string($search_term->needle, $conn) . "')";
			}
		}

		// Concept Description
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION)) 
		{
			foreach ($arr_search_term as $search_term) {
				$arr_criteria[] = 
					"c.concept_id in (select cdesc.concept_id from concept_description cdesc " . 
					"where cdesc.description regexp '[[:<:]]" .
					mysql_real_escape_string($search_term->needle, $conn) . "')";
			}
		}

		// UUID
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_UUID))
		{
			if ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_OR)
			{
				$arr_uuid_full = array();
				$arr_uuid_like = array();
				foreach ($arr_search_term as $search_object) {
					$_needle = mysql_real_escape_string($search_object->needle, $conn);
					if (strlen($search_object->needle) < MCL_UUID_LENGTH) {
						$arr_criteria[] = "c.uuid LIKE '" . $_needle . "%'";
					} else {
						$arr_uuid_full[] = "'" . $_needle . "'";
					}
					if ($arr_uuid_full) $arr_criteria[] = 'c.uuid in (' . implode(',',$arr_uuid_full) . ')';
				}
			} elseif ($cstc->glue == MCL_CONCEPT_SEARCH_GLUE_AND) {
				foreach ($arr_search_term as $search_object) {
					if (strlen($search_object->needle) < MCL_UUID_LENGTH) {
						$arr_criteria[] = "c.uuid LIKE '" . $_needle . "%'";
					} else {
						$arr_criteria[] = "c.uuid = '" . $_needle . "'";
					}
				}
			}
		}

		// IN inline filter (generically handles concept lists and map sources)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_IN)) 
		{
			foreach ($arr_search_term as $search_term) 
			{
				$css = $cs->getAllSources()->resolveSourceIdentifier(
						$search_term->needle,
						$cs->getSelectedSources()->getDictionaries(),
						MCL_SOURCE_TYPE_LIST | MCL_SOURCE_TYPE_MAP 
					);
				if (!$css) {
					continue;
				}
				elseif ($css->type == MCL_SOURCE_TYPE_LIST) 
				{
					// Treat search_term as concept_list.concept_list_id
					$arr_csg_filter[] = 
						"c.concept_id in (" .
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = " . $css->list_id .
							" and clm.dict_id = " . $css_dict->dict_id . 
						")";
				}
				elseif ($css->type == MCL_SOURCE_TYPE_MAP)
				{
					// Treat search_term as concept_source.concept_source_id
					$arr_csg_filter[] = 
						"c.concept_id in (" . 
							"select cm.concept_id from concept_map cm " . 
							"where cm.source = " . $css->map_source_id .
						")";
				}
			}
		}

		// NOT IN inline filter (generically handles concept lists and map sources)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_NOT_IN)) 
		{
			foreach ($arr_search_term as $search_term) 
			{
				$css = $cs->getAllSources()->resolveSourceIdentifier(
						$search_term->needle,
						$cs->getSelectedSources()->getDictionaries(),
						MCL_SOURCE_TYPE_LIST | MCL_SOURCE_TYPE_MAP 
					);
				if (!$css) {
					continue;
				}
				elseif ($css->type == MCL_SOURCE_TYPE_LIST) 
				{
					// Treat search_term as concept_list.concept_list_id
					$arr_csg_filter[] = 
						"c.concept_id not in (" .
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = " . $css->list_id . 
							" and clm.dict_id = " . $css_dict->dict_id .
						")";
				}
				elseif ($css->type == MCL_SOURCE_TYPE_MAP)
				{
					// Treat search_term as concept_source.concept_source_id
					$arr_csg_filter[] = 
						"c.concept_id not in (" . 
							"select cm.concept_id from concept_map cm " .
							"where cm.source = " . $css->map_source_id . 
						")";
				}
			}
		}

		// IN LIST inline filter (concept lists)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_IN_LIST)) 
		{
			foreach ($arr_search_term as $search_term) 
			{
				if ($search_term->isInteger()) {
					// Treat search_term as concept_list.concept_list_id
					$arr_csg_filter[] = 
						"c.concept_id in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = " . $search_term->needle .
							" and clm.dict_id = " . $css_dict->dict_id .  
						")";
				} else {
					// Treat search_term as concept_list.list_name
					$arr_csg_filter[] = 
						"c.concept_id in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = (" . 
								"select concept_list_id from mcl.concept_list " . 
								"where lower(list_name) = '" . mysql_real_escape_string($search_term->needle, $conn) . "' " . 
							") " . 
							"and clm.dict_id = " . $css_dict->dict_id .
						")";
				}
			}
		}

		// NOT IN LIST inline filter (concept lists)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_NOT_IN_LIST)) 
		{
			foreach ($arr_search_term as $search_term) {
				if ($search_term->isInteger()) {
					// Treat search_term as concept_list.concept_list_id
					$arr_csg_filter[] = 
						"c.concept_id not in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = " . $search_term->needle .
							" and clm.dict_id = " . $css_dict->dict_id . 
						")";
				} else {
					// Treat search_term as concept_list.list_name
					$arr_csg_filter[] = 
						"c.concept_id not in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = (" . 
								"select concept_list_id from mcl.concept_list " . 
								"where lower(list_name) = '" . mysql_real_escape_string($search_term->needle, $conn) . "' " . 
							") " .
							"and clm.dict_id = " . $css_dict->dict_id .
						")";
				}
			}
		}

		// IN SOURCE inline filter (map sources)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_IN_SOURCE))
		{
			foreach ($arr_search_term as $search_term) {
				if ($search_term->isInteger()) {
					// Treat search_term as concept_source.concept_source_id
					$arr_csg_filter[] = 
						"c.concept_id in (select cm.concept_id from concept_map cm where cm.source = " . 
							$search_term->needle . ")";
				} else {
					// Treat search_term as concept_source.name
					$arr_csg_filter[] = 
						"c.concept_id in (select cm.concept_id from concept_map cm where cm.source = (" . 
							"select cs.concept_source_id from concept_source cs where lower(name) = '" . 
							mysql_real_escape_string($search_term->needle, $conn) . "'))";
				}
			}
		}

		// NOT IN SOURCE inline filter (map sources)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_NOT_IN_SOURCE)) 
		{
			foreach ($arr_search_term as $search_term) {
				if ($search_term->isInteger()) {
					// Treat search_term as concept_source.concept_source_id
					$arr_csg_filter[] = 
						"c.concept_id not in (select cm.concept_id from concept_map cm where cm.source = " . 
							$search_term->needle . ")";
				} else {
					// Treat search_term as concept_source.name
					$arr_csg_filter[] = 
						"c.concept_id not in (select cm.concept_id from concept_map cm where cm.source = (" . 
							"select cs.concept_source_id from concept_source cs where lower(name) = '" . 
							mysql_real_escape_string($search_term->needle, $conn) . "'))";
				}
			}
		}

		// Concept List (only supported in enhanced mode, but that needs to be caught before it gets in here)
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_LIST)) 
		{

			foreach ($arr_search_term as $search_term) 
			{
				// Build the criteria
				$_sql_criteria = '';
				if ($search_term->isInteger()) {
					// Treat search_term as concept_list.concept_list_id
					$_sql_criteria = 
						"c.concept_id in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = " . $search_term->needle .
							" and clm.dict_id = " . $css_dict->dict_id . 
						")";
				} else {
					// Treat search_term as concept_list.list_name
					$_sql_criteria = 
						"c.concept_id in (" . 
							"select clm.concept_id from mcl.concept_list_map clm " . 
							"where clm.concept_list_id = (" . 
								"select concept_list_id from mcl.concept_list " . 
								"where lower(list_name) = '" . mysql_real_escape_string($search_term->needle, $conn) . "' " .
							") " .
							"and clm.dict_id = " . $css_dict->dict_id . 
						")";
				}


				/* Apply CS filter directly to this criteria, otherwise it will not be applied to 
				 * map sources and concept lists. 
				 */
				if (  $_sql_criteria  &&  $cs->hasFilter()  )
				{
					if (  $_sql_filter = $this->_buildSqlFilter($cs, $css_dict)  )  {
						$_sql_criteria = '( ' . $_sql_criteria . ' ) AND ( ' . $_sql_filter . ' )';
					}
				}

				// Add the criteria
				$arr_criteria[] = $_sql_criteria;

			}
		}

		// Map Source - pulls all concepts from a map source
		if ($arr_search_term = $cstc->getSearchTerms(MCL_SEARCH_TERM_TYPE_MAP_SOURCE)) 
		{

			foreach ($arr_search_term as $search_term) 
			{
				// Build the criteria
				$_sql_criteria = '';
				if ($search_term->isInteger()) {
					// Treat search_term as concept_map.source
					$_sql_criteria = 
						"c.concept_id in (" . 
							"select cm.concept_id from concept_map cm " . 
							"where cm.source = " . $search_term->needle . 
						")";
				} else {
					// Treat search_term as concept_source.name
					$_sql_criteria = 
						"c.concept_id in (" . 
							"select cm.concept_id from concept_map cm " . 
							"where cm.source = (" . 
								"select cs.concept_source_id from concept_source cs " . 
								"where lower(cs.name) = '" . mysql_real_escape_string($search_term->needle, $conn) . "' " . 
							") " . 
						")";
				}


				/* Apply CS filter directly to this criteria, otherwise it will not be applied to 
				 * map sources and concept lists. 
				 */
				if (  $_sql_criteria  &&  $cs->hasFilter()  ) 
				{
					if (  $_sql_filter = $this->_buildSqlFilter($cs, $css_dict)  )  {
						$_sql_criteria = '( ' . $_sql_criteria . ' ) AND ( ' . $_sql_filter . ' )';
					}
				}

				// Add the criteria
				$arr_criteria[] = $_sql_criteria;
			}
		}

		
		// Combine all the where criteria (but not the filters) using the CSTC glue (OR or AND)
		$sql_where = '';
		if ($arr_criteria) {
			$sql_where = '( ' . implode(' ) ' . $cstc->glue . ' ( ', $arr_criteria) . ' )';
		}

		/* Apply CS filter if filter scope is text (i.e. this applies to the AND searches)
		 * NOTE: Filter scope is typically text so that the filter does not apply to the OR searches, 
		 * such as hard coded IDs, mapcodes, etc.
		 */
		if ( ( $is_all_search || ($apply_cs_filter && $cs->filter_scope == MCL_FILTER_SCOPE_TEXT) )  && 
			$cs->hasFilter()) 
		{
			$sql_filter = $this->_buildSqlFilter($cs, $css_dict);
			if ($sql_filter) {
				if ($sql_where) $sql_where = '( ' . $sql_where . ' ) AND ( ' . $sql_filter . ' )';
				else $sql_where = '( ' . $sql_filter . ' )';
			}
		}

		// Apply CSG filter (IN and NOTIN operators)
		if ($arr_csg_filter) {
			$sql_csg_filter = '( ' . implode(' ) and ( ', $arr_csg_filter) . ' )';
			if ($sql_where) $sql_where = '( ' . $sql_where . ' ) and ' . $sql_csg_filter;
			else $sql_where = $sql_csg_filter;
		}

		return $sql_where;
	}


	/**
	 * Build the filter sql based on ConceptSearch filter settings.
	 * Handles class, datatype, and retired.
	 * @param ConceptSearch $cs
	 * @param ConceptSearchSource $css_dict Source of type MCL_SOURCE_TYPE_DICTIONARY 
	 * @return string
	 */
	private function _buildSqlFilter(ConceptSearch $cs, ConceptSearchSource $css_dict)
	{
		$sql_filter = '';
		$arr_filter_id = array();

		// class
		foreach ($cs->coll_class_filter->getKeys() as $key)   {
			// skip if not for the current dictionary
			$filter = $cs->coll_class_filter->Get($key);
			if ($filter->css_dict->dict_id != $css_dict->dict_id) continue;

			// Build the filter sql
			$arr_filter_id[] = $filter->concept_class_id;
		}
		if (count($arr_filter_id))  {
			if ($sql_filter) $sql_filter .= ' and ';
			$sql_filter .= 'c.class_id in (' . implode(',', $arr_filter_id) . ')';
		}

		// datatype
		$arr_filter_id = array();
		foreach ($cs->coll_datatype_filter->getKeys() as $key)   {
			// skip if not for the current dictionary
			$filter = $cs->coll_datatype_filter->Get($key);
			if ($filter->css_dict->dict_id != $css_dict->dict_id) continue;

			// Build the filter sql
			$arr_filter_id[] = $filter->concept_datatype_id;
		}
		if (count($arr_filter_id))  {
			if ($sql_filter) $sql_filter .= ' and ';
			$sql_filter .= 'c.datatype_id in (' . implode(',', $arr_filter_id) . ')';
		}

		// retired
		if (!$cs->include_retired) {
			if ($sql_filter) $sql_filter .= ' and ';
			$sql_filter .= 'c.retired != 1';
		}

		return $sql_filter;
	}

	/**
	 * Builds a single ConceptSearchSql object for a single ConceptSearchSource. Note that
	 * this can be called one or more times per ConceptSearchGroup.
	 * @param ConceptSearch $cs
	 * @param ConceptSearchGroup $csg
	 * @param ConceptSearchSource $css
	 * @param ConceptSearchSource $css_sub_list_dict
	 * @return ConceptSearchSql
	 */
	private function _buildSqlFromSource(
			ConceptSearch        $cs                        , 
			ConceptSearchGroup   $csg                       , 
			ConceptSearchSource  $css                       , 
			ConceptSearchSource  $css_sub_list_dict = null
		)
	{
		// Create the sql object
		$sql_obj     =  new ConceptSearchSql(  $cs  ,  $csg->getSearchTermCollection()  ,  $css  ,  $css_sub_list_dict  );

		// Build concept criteria based on selected search terms
		$sql_where   =  $this->_buildSqlCriteria(
				$cs, 
				$csg->getSearchTermCollection(), 
				$css, 
				$css_sub_list_dict, 
				false
			);

		// Set the dictionary source
		$css_dict  =  null;
		if ($css_sub_list_dict) $css_dict = $css_sub_list_dict;
		else $css_dict = $css;

		// Apply filters (class, datatype, and retired)
		$sql_filter  =  '';
		if ($cs->filter_scope == MCL_FILTER_SCOPE_GLOBAL && $cs->hasFilter()) {
			$sql_filter = $this->_buildSqlFilter($cs, $css_dict);
		}

		// Concept Source Filter - if results restricted to a map source or concept list
		$sql_source_filter = '';
		if ($css->type == MCL_SOURCE_TYPE_LIST || $css->type == MCL_SOURCE_TYPE_MAP) 
		{
			if ($css->type == MCL_SOURCE_TYPE_MAP) 
			{
				$sql_source_filter =
					"c.concept_id in (select cm.concept_id from concept_map cm where cm.source = " . 
						$css->map_source_id . ')';
			} 
			elseif ($css->type == MCL_SOURCE_TYPE_LIST) 
			{
				// TODO: Sub-query will only work if in on same db server as the dictionary being queried...
				// Meaning that the concept IDs should actually be inline 
				$sql_source_filter =
					"c.concept_id in (select clm.concept_id from mcl.concept_list_map clm where clm.concept_list_id = " . 
						$css->list_id . ' and clm.dict_id = ' . $css_sub_list_dict->dict_id . ')';
			}
		}

		// Combine all the where clauses
		if ($sql_filter) {
			if ($sql_where)  $sql_where = '( ' . $sql_where . ' ) AND ';
			$sql_where .= '( ' . $sql_filter . ' )';
		}
		if ($sql_source_filter) {
			if ($sql_where)  $sql_where = '( ' . $sql_where . ' ) AND ';
			$sql_where .= '( ' . $sql_source_filter . ' )';
		}

		// Setup full-text search if text terms are being used and if the current source supports it
		$use_fts_index   =  false;
		$fts_text_terms  =  '';
		if ($css_dict->dict_fulltext_mode) 
		{
			$ctsc = $csg->getSearchTermCollection();
			$arr_possible_types = array(
					MCL_SEARCH_TERM_TYPE_TEXT,
					MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION,
					MCL_SEARCH_TERM_TYPE_CONCEPT_NAME,
					MCL_SEARCH_TERM_TYPE_MAP_SOURCE
				);
			$fts_text_terms   =  '';
			$arr_search_term  =  $ctsc->getSearchTerms(  $arr_possible_types  ,  null  ,  true  );
			foreach ($arr_search_term as $search_term) {
				if (  $fts_text_terms  )  $fts_text_terms  .=  ' ';
				$fts_text_terms  .=  mysql_escape_string(  $search_term->needle  );
			}
			if ($fts_text_terms)  $use_fts_index = true;
		}

		// Put it all together
		// NOTE: If the current dictionary source uses a full-text index, that is applied here
		$sql_search = 
			'select c.concept_id, c.retired, c.is_set, ' . 
				'c.class_id, cc.name class_name, ' . 
				'c.datatype_id, cd.name datatype_name, ' . 
				'c.uuid, c.date_created, c.retired_by, c.date_retired, c.retire_reason ';
		if ($use_fts_index) {
			$sql_search .= ", match(cfts.name, cfts.description, cfts.source_code) against('" . 
					$fts_text_terms . "') as relevancy ";
		}
		$sql_search .=
			'from concept c ' . 
			'left join concept_datatype cd on cd.concept_datatype_id = c.datatype_id ' .
			'left join concept_class cc on cc.concept_class_id = c.class_id ';
		if ($use_fts_index) {
			$sql_search .= 'left join concept_fts cfts on cfts.concept_id = c.concept_id ';
		}
		if ($sql_where)  $sql_search .= 'where ' . $sql_where;

		$sql_obj->sql = $sql_search;
		return $sql_obj;
	}

	/**
	 * Returns a ConceptSearchSqlCollection object containing one or more
	 * ConceptSearchSql objects that represent a single ConceptSearchGroup. 
	 * These sql statements are only the base concept searches; meaning, only
	 * concept records are returned, not any of the other meta-data. SQL built 
	 * in this function queries the concept, concept_word, and concept_map tables.
	 * NOTE: There are filters at the CS and CSG levels. CS filters (e.g. include_retired)
	 * can be applied only to text searches (the default) or globally. CSG filters
	 * (e.g. "immunization in:core")
	 * @param ConceptSearch $cs
	 * @param ConceptSearchGroup $csg
	 * @return ConceptSearchSqlCollection
	 */
	private function _buildSqlFromConceptSearchGroup(ConceptSearch $cs, ConceptSearchGroup $csg)
	{
		$coll_sql     =   new ConceptSearchSqlCollection($csg);

		// Just return if no sql
		if (  $csg->isEmpty()  ) return $coll_sql;

		// Create SQL for each of the sources for this search group
		$coll_source  =   $this->getSearchGroupSources($cs, $csg);
		$group_i      =   0;
		foreach ($coll_source->getAllSources() as $key => $css) 
		{
			// Create the sql objects
			if ($css->type == MCL_SOURCE_TYPE_LIST) {
				foreach ($css->getDictionarySources()->getDictionaries() as $css_sub_list_dict) {
					$sql_obj = $this->_buildSqlFromSource($cs, $csg, $css, $css_sub_list_dict);
					$coll_sql->Add(++$group_i, $sql_obj);
				}
			} elseif ($css->type == MCL_SOURCE_SEARCH_ALL) {
				foreach ($cs->getAllSources()->getDictionaries() as $css_sub_dict) {
					$sql_obj = $this->_buildSqlFromSource($cs, $csg, $css_sub_dict);
					$coll_sql->Add(++$group_i, $sql_obj);
				}
			} elseif ($css->type == MCL_SOURCE_TYPE_MAP) {
				$arr_css_dict = $css->getDictionarySources()->getAllSources();
				if (count($arr_css_dict) == 1) {
					reset($arr_css_dict);
					$css_dict = current($arr_css_dict);
				} else {
					trigger_error('Dictionary sources for this map source object not set', E_USER_ERROR);
				}
				$sql_obj = $this->_buildSqlFromSource($cs, $csg, $css, $css_dict);
				$coll_sql->Add(++$group_i, $sql_obj);
			} else {
				$sql_obj = $this->_buildSqlFromSource($cs, $csg, $css);
				$coll_sql->Add(++$group_i, $sql_obj);
			}
		}

		return $coll_sql;
	}
}

?>