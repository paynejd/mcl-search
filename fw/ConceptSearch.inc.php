<?php

require_once(MCL_ROOT . 'fw/ConceptSearchGroup.inc.php');
require_once(MCL_ROOT . 'fw/ConceptDatatypeCollection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptClassCollection.inc.php');


/**
 * Object to setup a search query.
 */
class ConceptSearch
{
	/**
	 * Full multi-line search query
	 */
	public $query = '';

	/**
	 * Array of ConceptSearchGroup objects
	 */
	public $arr_search_group = array();

	/**
	 * Array of ConceptSearchSource objects for all possible sources
	 */
	private $coll_all_sources = null;
	
	/**
	 * Array of ConceptSearchSource objects for selected sources
	 */
	private $coll_selected_sources = null;

	/**
	 * Whether filters apply globally (MCL_FILTER_SCOPE_GLOBAL) or 
	 * only to the text component of queries (MCL_FILTER_SCOPE_TEXT).
	 * Default is text. For example: if someone queried concept id:10 but also
	 * selected to only see concepts of type boolean, the global settings
	 * would filter out id:10 if it was not of type boolean.
	 */
	public $filter_scope = MCL_FILTER_SCOPE_TEXT;

	/**
	 * Collection of class filters.
	 */
	public $coll_class_filter = null;
	
	/**
	 * Collection of datatype filters.
	 */
	public $coll_datatype_filter = null;

	/**
	 * Whether to include retired concepts in the search results.
	 * This variable does not apply to concept id or map code search terms.
	 */
	public $include_retired = false;

	/**
	 * Number of search results to show per page. Set to a false value (0, null, false, etc)
	 * to retrieve all results.
	 * NOTE: Something (I think MySQL) freaks out if too many results and just errors out.
	 */
	public $num_results_per_page = 100;

	/**
	 * Zero-indexed page number to retrieve.
	 */
	public $current_page_num = 0;

	/**
	 * Whether to return each of items in the concept results.
	 */
	public $load_descriptions         =  true;
	public $load_concept_attributes   =  true;
	public $load_qa_sets              =  true;
	public $load_mappings             =  true;
	public $load_concept_sets         =  true;
	public $load_concept_list_names   =  true;


	/**
	 * Constructor
	 */
	public function __construct($q = null) 
	{
		$this->coll_class_filter     =  new ConceptClassCollection();
		$this->coll_datatype_filter  =  new ConceptDatatypeCollection();
		if ($q) $this->parse($q);
	}

	/**
	 * Set the ConceptSearch to request detailed search results.
	 */
	public function setDetailResults() 
	{
		$this->load_descriptions = true;
		$this->load_concept_attributes = true;
		$this->load_qa_sets = true;
		$this->load_mappings = true;
		$this->load_concept_sets = true;
		$this->load_concept_list_names = true;
	}


	/** 
	 * Parses the passed search query into search groups. Groups are denoted 
	 * by the newline character.Returns true if one or more search groups are 
	 * present in the search query; false otherwise.
	 */
	public function parse($q)
	{
		$this->query = $this->preprocess($q);
		$arr_split = explode("\n", $this->query);
		foreach ($arr_split as $str_search_group) {
			if (trim($str_search_group)) $this->arr_search_group[] = new ConceptSearchGroup($str_search_group);
		}
		return (bool)count($arr_split);
	}

	/**
	 * Preprocesses the full search query. This is the first step in ConceptSearch::parse().
	 */
	protected function preprocess($q) {
		return strtolower(trim($q));
	}

	/**
	 * Return whether any filter settings are contained in this object.
	 */
	public function hasFilter() 
	{
		// NOTE: SQL needs to be added in order to exclude retired, so invert the include retired variable
		if (  !$this->include_retired               || 
			  $this->coll_datatype_filter->Count()  || 
			  $this->coll_class_filter->Count()     ) 
		{
			return true;
		}
		return false;
	}

	/**
	 * Adds a filter to either the class or datatype filter array.
	 * @param $filter_criteria ConceptDatatype or ConceptClass
	 */
	public function addFilter($filter_criteria)
	{
		if ($filter_criteria instanceof ConceptClass) 
		{
			$this->coll_class_filter   ->Add(  $filter_criteria->getKey()  ,  $filter_criteria  );
		} 
		elseif ($filter_criteria instanceof ConceptDatatype) 
		{
			$this->coll_datatype_filter->Add(  $filter_criteria->getKey()  ,  $filter_criteria);
		} 
		else 
		{
			trigger_error('Unrecognized filter criteria: ' . var_export($filter_criteria, true), E_USER_ERROR);
		}
	}

	/**
	 * Returns the number of ConceptSearchGroup objects in this ConceptSearch object.
	 */
	public function Count() {
		return count($this->arr_search_group);
	}

	/**
	 * True if one or more ConceptSearchGroup objects are empty.
	 */
	public function hasEmptyConceptSearchGroup() 
	{
		foreach (array_keys($this->arr_search_group) as $k) {
			if ($this->arr_search_group[$k]->isEmpty()) return true;
		}
		return false;
	}

	/**
	 * Set selected global sources
	 * @param ConceptSearchSourceCollection $coll_selected_sources
	 */
	public function setSelectedSources(ConceptSearchSourceCollection $coll_selected_sources) {
		$this->coll_selected_sources = $coll_selected_sources;
	}

	/**
	 * Set all sources (used to parse inline source statements)
	 * @param ConceptSearchSourceCollection $coll_selected_sources
	 */
	public function setAllSources(ConceptSearchSourceCollection $coll_all_sources) {
		$this->coll_all_sources = $coll_all_sources;
	} 

	/**
	 * Get selected global sources
	 * @return ConceptSearchSourceCollection of selected sources
	 */
	public function getSelectedSources()
	{
		return $this->coll_selected_sources;
	}

	/**
	 * Get all sources
	 * @return ConceptSearchSourceCollection of all sources
	 */
	public function getAllSources()
	{
		return $this->coll_all_sources;
	}
}

?>