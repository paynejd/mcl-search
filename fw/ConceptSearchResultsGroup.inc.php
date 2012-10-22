<?php

/**
 * Object to represent the search results associated with a single 
 * ConceptSearchGroup object.
 */
class ConceptSearchResultsGroup
{
	/**
	 * Reference to the ConceptSearchGroup used to create this object.
	 */
	public $csg = null;

	/**
	 * Collection of sql objects executed to populate this results group.
	 */
	public $coll_sql_obj = null;

	/**
	 * Array of references to Concept objects linked to this results group. Of the format:
	 * 		[<dict_db>][<concept_id>]
	 */
	private $arr_concept = array();

	/**
	 * Array of relevancy ratings
	 */
	private $arr_relevancy = array();


	/**
	 * Constructor - requires a ConceptSearchGroup
	 */
	public function __construct(ConceptSearchGroup $csg) 
	{
		$this->csg  =  $csg;
	}

	/**
	 * Add Concept to this search results group.
	 * @param Concept $c Concept object
	 * @param mixed $relevancy Integer of the concept's relevancy rating or null
	 */
	public function addConcept(Concept $c, $relevancy = null) 
	{
		// Concepts are internally partitioned by their dictionary source
		$dict_db  =  '';
		if (  $c->css_dict  )  $dict_db  =  strtolower($c->css_dict->dict_db);

		$this->arr_concept  [  $dict_db  ][  $c->concept_id   ]  =  $c          ;
		$this->arr_relevancy[  $dict_db  ][  $c->concept_id   ]  =  $relevancy  ;
	}

	public function setRelevancy(Concept $c, $relevancy)
	{
		$dict_db  =  '';
		if (  $c->css_dict  )  $dict_db  =  strtolower($c->css_dict->dict_db);
		$this->arr_relevancy[  $dict_db  ][  $c->concept_id   ]  =  $relevancy  ;
	}
	public function getRelevancy(Concept $c)
	{
		$dict_db  =  '';
		if (  $c->css_dict  )  $dict_db  =  strtolower($c->css_dict->dict_db);
		if (  isset($this->arr_relevancy[  $dict_db  ][  $c->concept_id   ])  ) {
			return $this->arr_relevancy[  $dict_db  ][  $c->concept_id   ];
		}
		return null;
	}
	public function getMaxRelevancy(ConceptSearchSource $css_dict = null)
	{
		// Set the dictionary source
		if ($css_dict) $dict_db = $css_dict->dict_db;
		else $dict_db = '';

		// Calculate the max for this dictionary
		$max = 0;
		if (isset($this->arr_relevancy[$dict_db])) 
		{
			foreach (array_keys($this->arr_relevancy[$dict_db]) as $concept_id)
			{
				if (!is_null($this->arr_relevancy[$dict_db][$concept_id]) &&
					$this->arr_relevancy[$dict_db][$concept_id] > $max)
				{
					$max = $this->arr_relevancy[$dict_db][$concept_id];
				}
			}
		}
		return $max;
	}


	/**
	 * Get the specified concept
	 * @param int $concept_id
	 * @param mixed $dict ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY or a 
	 * 							string of the name of a dictionary database
	 * @return Concept
	 */
	public function getConcept($concept_id, $dict = '') 
	{
		// Determine the dictionary
		if ($dict instanceof ConceptSearchSource) {
			$dict_db  =  strtolower($dict->dict_db);
		} else {
			$dict_db  =  $dict;
		}

		// Get the concept
		$c = null;
		if (  isset($this->arr_concept[  $dict_db  ][  $concept_id  ]  )) 
		{
			$c  =   $this->arr_concept[  $dict_db  ][  $concept_id  ];
		}
		return $c;
	}

	/**
	 * Get the number of concepts stored in this search results group.
	 * @param mixed $css_dict ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY, a string of the name of a dictionary database, or null to indicate all dictionary sources.
	 * @return int
	 */
	public function getCount($css_dict = null) 
	{
		// Determine the dictionary
		if ($css_dict instanceof ConceptSearchSource) {
			$dict_db  =  strtolower(  $css_dict->dict_db  );
		} else {
			$dict_db  =  $css_dict;
		}

		// Count
		$num = 0;
		if (is_null($dict_db)) 
		{
			// Return concepts for all dictionaries if null
			foreach (array_keys($this->arr_concept) as $key_dict) {
				$num += count($this->arr_concept[$key_dict]);
			}
		} elseif (isset($this->arr_concept[$dict_db])) {
			// Return concepts for only the specified dictionary if anything other null
			$num = count($this->arr_concept[$dict_db]);
		}

		return $num;
	}


	/**
	 * Get the number of visible concepts stored in this collection. The collection
	 * may store a number of "invisible" concepts (i.e. concept sets or Q/A sets) that
	 * were not part of the base search query.
	 * @return int 
	 */
	public function getVisibleCount() 
	{
		$num  =  0;
		foreach (  array_keys($this->arr_concept) as $key_dict  )
		{
			foreach (  array_keys($this->arr_concept[  $key_dict  ]) as $concept_id  )
			{	
				if (  $this->arr_concept[  $key_dict  ][  $concept_id  ]->display  )  $num++;
			}
		}
		return $num;
	}


/***************************************************************************************************
 * HELPER FUNCTIONS
 **************************************************************************************************/

 	/**
	 * Get an arary of keys for dictionary sources used in this collection.
	 * @return array
	 */
 	public function getDictionarySources()
	{
		return array_keys($this->arr_concept);
	}

	/**
	 * Get array of concept IDs contained in the search results group
	 * @param ConceptSearchSource $css_dict
	 * @return array
	 */
	public function getConceptIds(ConceptSearchSource $css_dict = null)
	{
		// Set the dictionary source
		if ($css_dict) $dict_db = $css_dict->dict_db;
		else $dict_db = '';

		// Get the IDs
		if (isset($this->arr_concept[$dict_db])) {
			return array_keys($this->arr_concept[$dict_db]);
		} 
		return array();
	}

	/**
	 * Returns multidimensional array of concepts sorted by relevancy. The returned array is of the form:
	 * 		[n] = array(
	 * 				'concept_id' => #
	 * 				'dict_db' => ''
	 * 			)
	 * @param mixed $dict ConceptSearchSource object of type dictionary, the dictionary database name, or null for all dictionary sources
	 */
	public function getRelevancySortedConceptArray($dict = null)
	{
		// Create array of dictionary keys
		if (  $dict instanceof ConceptSearchSource  )  {
			$arr_dict_db  =  array($dict->dict_db);
		} elseif (  is_null($dict)  ) {
			$arr_dict_db  =  array_keys($this->arr_concept);
		} else {
			$arr_dict_db  =  array($dict);
		}

		// Build the unsorted relevancy array
		$key_delimiter = '~~~~~';
		$arr_relevancy  =  array();
		foreach ($arr_dict_db as $dict_db) 
		{
			// Get visible concept id array for the current source
			$arr_concept_id = $this->getVisibleConceptIds($dict_db);

			// Get relevancies for the current source
			foreach ($arr_concept_id as $concept_id) 
			{
				$key        =  $dict_db . $key_delimiter . $concept_id;
				$relevancy  =  null;
				if (  isset($this->arr_relevancy[$dict_db][$concept_id])  )
				{
					$relevancy  =  $this->arr_relevancy[$dict_db][$concept_id];
				}
				$arr_relevancy[  $key  ]  =  $relevancy;
			}
		}

		// Build sorted concept array
		arsort($arr_relevancy, SORT_NUMERIC);
		$arr_sort_concept_id = array();
		foreach ($arr_relevancy as $key => $relevancy) {
			list($dict_db, $concept_id)  =  explode($key_delimiter, $key, 2);
			$arr_sort_concept_id[]  =  array(
					'concept_id'  =>  $concept_id,
					'dict_db'     =>  $dict_db
				);
		}

		return $arr_sort_concept_id;
	}

	/**
	 * Returns a string of comma-separated IDs for all concepts that are members of the specified dictionary source.
	 * @param ConceptSearchSource $css_dict
	 * @return string
	 */
	public function getConceptIdCsv(ConceptSearchSource $css_dict = null) 
	{
		return implode(  ','  ,  $this->getConceptIds($css_dict)  );
	}


	/**
	 * Returns a string of comma-separated IDs of all visible concepts in this collection.
	 * @param mixed $css_dict ConceptSearchSource object of type dictionary or the dictionary database name
	 */
	public function getVisibleConceptIds($dict) 
	{
		// Set the dictionary source
		if ($dict instanceof ConceptSearchSource) {
			$dict_db = $dict->dict_db;
		} else {
			$dict_db = $dict;
		}

		// Get the IDs
		if (isset($this->arr_concept[$dict_db])) 
		{
			$arr_concept_id = array();
			foreach (array_keys($this->arr_concept[$dict_db]) as $concept_id) 
			{	
				if ($this->arr_concept[$dict_db][$concept_id]->display) {
					$arr_concept_id[$concept_id] = $concept_id;
				}
			}
			return $arr_concept_id;
		}
		else
		{
			trigger_error('Unrecognized dictionary source: ' . $dict_db, E_USER_ERROR);
		}
	}

	/**
	 * Returns a string of comma-separated IDs of all visible concepts in this collection.
	 * @param ConceptSearchSource $css_dict
	 */
	public function getVisibleConceptIdCsv(ConceptSearchSource $css_dict = null) 
	{
		return implode(',', $this->getVisibleConceptIds($css_dict));
	}
	
	public function toString()
	{
		$s = '<pre>Concepts:<br>';
		foreach (array_keys($this->arr_concept) as $dict_db) {
			foreach (array_keys($this->arr_concept[$dict_db]) as $concept_id) {
				$c = $this->getConcept($concept_id, $dict_db);
				$s .= $dict_db . ':' . $concept_id . ' - ' . $c->getPreferredName() . "\n";
				ob_start();
				var_dump($c);
				$s .= ob_get_clean();
			}
		}
		$s .= '</pre>';
		return $s;
	}
}

?>