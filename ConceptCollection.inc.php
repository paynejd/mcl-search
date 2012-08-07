<?php

require_once (MCL_ROOT . 'Concept.inc.php');


/**
 * Stores a collection of concept objects.
 */
class ConceptCollection 
{
	/**
	 * Array of Concept objects of the format:
	 * 		[<dict_db>][<concept_id>]
	 */
	private $arr_concept = array();

	/**
	 * Array of ConceptSearchResultsGroup objects.
	 */
	private $arr_results_group = array();


	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing
	}


/***************************************************************************************************
 * CONCEPT FUNCTIONS
 **************************************************************************************************/

	/**
	 * Add a Concept to the collection
	 */
	public function addConcept(Concept $c) 
	{
		// Concepts are internally partitioned by their dictionary source
		$dict_db = '';
		if ($c->css_dict) $dict_db = strtolower($c->css_dict->dict_db);

		// Add the concept
		$this->arr_concept[$dict_db][$c->concept_id] = $c;
	}

	/**
	 * Get the specified concept.
	 * @param int $concept_id
	 * @param mixed $css_dict ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY or a string of the name of a dictionary database
	 * @return Concept
 	 */
	public function getConcept($concept_id, $css_dict = '') 
	{
		// Determine the dictionary
		if ($css_dict instanceof ConceptSearchSource) {
			$dict_db = strtolower($css_dict->dict_db);
		} else {
			$dict_db = $css_dict;
		}

		// Get the concept
		$c = null;
		if (  isset($this->arr_concept[  $dict_db  ][  $concept_id  ])  ) {
			$c = $this->arr_concept[  $dict_db  ][  $concept_id  ];
		}
		return $c;
	}

	/**
	 * Get the number of concepts stored in this collection.
	 * @param mixed $css_dict ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY, a string of the name of a dictionary database, or null to indicate all dictionary sources.
	 * @return int
	 */
	public function getCount($css_dict = null) 
	{
		// Determine the dictionary
		if ($css_dict instanceof ConceptSearchSource) {
			$dict_db = strtolower($css_dict->dict_db);
		} else {
			$dict_db = $css_dict;
		}

		// Count
		$num = 0;
		if (is_null($dict_db)) {
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
		$num = 0;
		foreach (array_keys($this->arr_concept) as $key_dict) {
			foreach (array_keys($this->arr_concept[$key_dict]) as $concept_id) {	
				if ($this->arr_concept[$key_dict][$concept_id]->display) $num++;
			}
		}
		return $num;
	}


/***************************************************************************************************
 * CSRG FUNCTIONS
 **************************************************************************************************/

	/**
	 * Add ConceptSearchResultsGroup object to this ConceptCollection.
	 * NOTE: This does not add the group's concepts. This must be done indepedently.
	 */
	public function addGroup(ConceptSearchResultsGroup $csrg) 
	{
		$this->arr_results_group[]  =  $csrg;
	}

	/**
	 * Returns an array with IDs of the ConceptSearchResultsGroup objects.
	 */
	public function getSearchResultsGroupIds() 
	{
		return array_keys($this->arr_results_group);
	}

	/**
	 * Get the specified ConceptSearchResultsGroup
	 */
	public function getSearchResultsGroup($csrg_id) 
	{
		$csrg  =  null;
		if (  isset($this->arr_results_group[$csrg_id])  ) 
		{
			$csrg  =  $this->arr_results_group[$csrg_id];
		}
		return $csrg;
	}

	/**
	 * Get the number of ConceptSearchResultsGroup objects stored in this collection.
	 * @return int
	 */
	public function getSearchResultsGroupCount() {
		return count($this->arr_results_group);
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
		} else {
			trigger_error('Unrecognized dictionary source: ' . $dict_db, E_USER_ERROR);
		}
	}

	/**
	 * Returns a string of comma-separated IDs for all concepts that are members of the specified dictionary source.
	 * @param ConceptSearchSource $css_dict
	 * @return string
	 */
	public function getConceptIdCsv(ConceptSearchSource $css_dict = null) 
	{
		return implode(',', $this->getConceptIds($css_dict));
	}


	/**
	 * Returns a string of comma-separated IDs of all visible concepts in this collection.
	 * @param ConceptSearchSource $css_dict
	 */
	public function getVisibleConceptIds(ConceptSearchSource $css_dict = null) 
	{
		// Set the dictionary source
		if ($css_dict) $dict_db = $css_dict->dict_db;
		else $dict_db = '';

		// Get the IDs
		$arr_concept_id = array();
		if (  isset($this->arr_concept[$dict_db])  ) 
		{
			$arr_concept_id = array();
			foreach (array_keys($this->arr_concept[$dict_db]) as $concept_id) {	
				if ($this->arr_concept[$dict_db][$concept_id]->display) {
					$arr_concept_id[$concept_id] = $concept_id;
				}
			}
		}
		return $arr_concept_id; 
	}

	/**
	 * Returns a string of comma-separated IDs of all visible concepts in this collection.
	 * @param ConceptSearchSource $css_dict
	 */
	public function getVisibleConceptIdCsv(ConceptSearchSource $css_dict = null) 
	{
		return implode(',', $this->getVisibleConceptIds($css_dict));
	}
 
	/**
	 * Returns a string containing comma-separated concept ids for all questions 
	 * and answers referred to by the concepts in this collection.
	 */
	public function getQAIdCsv(ConceptSearchSource $css_dict = null) 
	{
		// Set the dictionary source
		if ($css_dict) $dict_db = $css_dict->dict_db;
		else $dict_db = '';

		// Build the CSV
		$arr_qa_id = array();
		if (  !isset($this->arr_concept[  $dict_db  ])  )  return '';
		foreach (array_keys($this->arr_concept[$dict_db]) as $_concept_id) 
		{
			$c = $this->arr_concept[  $dict_db  ][  $_concept_id  ];
			foreach ($c->getAnswerIds  () as $_id)  $arr_qa_id[$_id]  =  $_id;
			foreach ($c->getQuestionIds() as $_id)  $arr_qa_id[$_id]  =  $_id;
		}
		return implode(',', $arr_qa_id);
	}

}

?>