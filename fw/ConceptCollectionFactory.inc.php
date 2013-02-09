<?php

require_once(MCL_ROOT . 'fw/ConceptCollection.inc.php');

class ConceptCollectionFactory
{
	public $load_descriptions        =  true   ;
	public $load_concept_attributes  =  true   ;
	public $load_qa_sets             =  true   ;
	public $load_mappings            =  true   ;
	public $load_concept_sets        =  true   ;
	public $load_concept_list_names  =  true   ;

	public $coll_source              =  null   ;
	public $conn                     =  null   ;

	public $debug                    =  false  ;

	public function NewCollectionFromListId($concept_list_id)
	{
		$cc = $this->_loadConceptsFromListId($concept_list_id);
		$this->_populateCollection($cc);
		return $cc;
	}

	function _populateCollection($cc)
	{
		if (  $cc->getCount()  )
		{
			// Iterate through dictionary sources in the collection
			foreach (  $cc->getDictionarySources() as $dict_db  ) 
			{
				$css_dict  =  $this->coll_source->getDictionary($dict_db);

				// Load all the concept data for this search
				if (  $this->load_descriptions        )  $this->_loadConceptDescriptions (  $css_dict  ,  $cc  );
				if (  $this->load_concept_attributes  )  $this->_loadConceptAttributes   (  $css_dict  ,  $cc  );
				if (  $this->load_qa_sets             )  $this->_loadQASets              (  $css_dict  ,  $cc  );
				if (  $this->load_mappings            )  $this->_loadMappings            (  $css_dict  ,  $cc  );
				if (  $this->load_concept_sets        )  $this->_loadConceptSets         (  $css_dict  ,  $cc  );
				if (  $this->load_concept_list_names  )  $this->_loadConceptListNames    (  $css_dict  ,  $cc  );

				// Load concept names last - this is last so that it can get names for all the dependencies as well
				$this->_loadConceptNames(  $css_dict  ,  $cc  );
			}
		}
	}

	function _loadConceptsFromListId($concept_list_id)
	{
		// Load dictionary info
		$sql = 
			'select cd.dict_id, cd.db_name ' .
			'from mcl.concept_dict cd ' .
			'where cd.dict_id in (' .
				'select clm.dict_id ' .
				'from mcl.concept_list_map clm ' .
				"where clm.concept_list_id = '" . mysql_real_escape_string($concept_list_id) . "'" .
				' group by clm.dict_id' .
			')';
		$result        =  mysql_query(  $sql  ,  $this->conn  );
		$arr_dict_all  =  array();
		while (  $row = mysql_fetch_assoc($result)  )  {
			$arr_dict_all[  $row['dict_id']  ]  =  $row  ;
		}
		if ($this->debug) {
			echo '<p><b>Loading dictionary sources for collection ' . $concept_list_id . ':</b><br> ' . $sql . '</p>';
		}


		// Load concepts
		$cc = new ConceptCollection();
		foreach ($arr_dict_all as $arr_dict)
		{
			$css_dict  =  $this->coll_source->getDictionary($arr_dict['dict_id']);
			mysql_select_db($css_dict->dict_db, $css_dict->getConnection());
			$sql =
				'select clm.concept_id clm_concept_id, c.concept_id c_concept_id, ' .
					'c.retired, c.is_set, c.class_id, cc.name class_name,  ' .
					'c.datatype_id, cd.name datatype_name, c.uuid,  ' .
					'c.date_created, c.retired_by, c.date_retired, c.retire_reason  ' .
				'from mcl.concept_list_map clm ' .
				'left join ' . $css_dict->dict_db . '.concept c on c.concept_id = clm.concept_id ' .
				'left join ' . $css_dict->dict_db . '.concept_datatype cd on cd.concept_datatype_id = c.datatype_id  ' .
				'left join ' . $css_dict->dict_db . '.concept_class cc on cc.concept_class_id = c.class_id  ' .
				'where clm.concept_list_id = ' . $concept_list_id . ' and ' .
				'clm.dict_id = ' . $arr_dict['dict_id'];
			if ($this->debug) {
				echo '<p><b>Loading concepts for ' . $css_dict->dict_db . ':</b><br> ' . $sql;
			}

			$result  =  mysql_query(  $sql  ,  $this->conn  );
			if (!$result) {
				trigger_error('Unable to query concepts: ' . mysql_error($this->conn), E_USER_ERROR);
			}
			while (  $row = mysql_fetch_assoc($result)  )
			{
				$c_id = $row['clm_concept_id'];
				if (  !$cc->getConcept($c_id, $arr_dict['db_name'])  )  
				{
					$c = new Concept(
							$row[  'clm_concept_id'  ],
							$row[  'retired'         ],
							$row[  'is_set'          ],
							$row[  'class_id'        ],
							$row[  'class_name'      ],
							$row[  'datatype_id'     ],
							$row[  'datatype_name'   ]
						);
					$c->uuid    =  $row['uuid'];		// keeping this separate to add version compatibility later
					if (  $row['date_created' ]  )  $c->setAttribute(  'Date created'  ,  $row['date_created' ]  );
					if (  $row['retired_by'   ]  )  $c->setAttribute(  'Date Retired'  ,  $row['retired_by'   ]  );
					if (  $row['date_retired' ]  )  $c->setAttribute(  'Retired by'    ,  $row['date_retired' ]  );
					if (  $row['retire_reason']  )  $c->setAttribute(  'Retire reason' ,  $row['retire_reason']  );
					$c->css_dict  =  $css_dict;
					$cc->addConcept(  $c  );
				}

				// Add to the search results group
				//$csrg->addConcept(  $c  ,  $relevancy  );
			}
			if ($this->debug) {
				echo '<br /><ul><li>' . $cc->getVisibleCount() . ' concept(s) loaded...</li></ul></p>';
			}
		}
		return $cc;
	}




	/**
	 * Load the names of the concept lists that are associated with the concepts
	 * in the concept collection.
	 * @param ConceptSearchSource $css_dict
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptListNames(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptAttributes(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptSets(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadMappings(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadQASets(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptDescriptions(ConceptSearchSource $css_dict, ConceptCollection $cc)
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
	 * @param ConceptCollection $cc
	 */
	private function _loadConceptNames(ConceptSearchSource $css_dict, ConceptCollection $cc)
	{
		// Get concept IDs for all concepts (not just visible ones)
		$csv_concept_id     =  $cc->getConceptIdCsv(  $css_dict  )  ;
		$csv_qa             =  $cc->getQAIdCsv     (  $css_dict  )  ;

		// Build SQL (OMRS version specific)
		$sql_concept_names  =
				'select ' . 
					'cn.concept_name_id, ' .
					'cn.concept_id, ' . 
					'cn.name, ' . 
					'cn.locale, ' . 
					'cntm.concept_name_tag_id, ';
		if ($css_dict->version == MCL_OMRS_VERSION_1_6) {
			$sql_concept_names .= 'null as concept_name_type, ';
		}
		elseif ($css_dict->version == MCL_OMRS_VERSION_1_9) {
			$sql_concept_names .= 'cn.concept_name_type, ';
		}
		$sql_concept_names .=
					'cn.uuid ' .
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

		// Perform the query
		$rsc_concept_names  =  mysql_query(  $sql_concept_names  ,  $css_dict->getConnection()  );
		if (  !$rsc_concept_names  )  
		{
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
					$row[  'concept_name_tag_id'  ],
					$row[  'concept_name_type'    ]
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

}

?>