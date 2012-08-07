<?php

require_once(MCL_ROOT . 'ConceptSearchTerm.inc.php');

/**
 * Object containing an array of ConceptSearchTerm objects or other
 * ConceptSearchTermCollection objects. Objects are "glued" together by
 * either an OR or AND operator.
 */
class ConceptSearchTermCollection
{
	/**
	 * Determines whether search objects are glued together by OR or AND.
	 */
	public $glue = MCL_CONCEPT_SEARCH_GLUE_AND;

	/**
	 * Array of ConceptSearchTermCollection and ConceptSearchTerm objects.
	 */
	public $arr_search_object = array();


	/**
	 * Constructor
	 */
	public function ConceptSearchTermCollection($glue = MCL_CONCEPT_SEARCH_GLUE_AND) 
	{
		$this->glue = $glue;
	}

	/**
	 * Returns the number of search objects contained in this collection.
	 * NOTE: This does count through the hierarchy.
	 */
	public function Count() 
	{
		$i = 0;
		foreach (array_keys($this->arr_search_object) as $k) {
			if ($this->arr_search_object[$k] instanceof ConceptSearchTermCollection) {
				$i += $this->arr_search_object[$k]->Count();
			} else {
				$i++;
			}
		}
		return $i;
	}

	/**
	 * Adds a ConceptSearchTerm object to the collection.
	 */
	public function addSearchTerm(ConceptSearchTerm $search_term) 
	{
		$this->arr_search_object[] = $search_term;
	}

	/**
	 * Adds a ConceptSearchTermCollection object to the collection.
	 */
	public function addSearchTermCollection(ConceptSearchTermCollection $search_term_collection) 
	{
		$this->arr_search_object[] = $search_term_collection;
	}

	/**
	 * Returns an array of references to ConceptSearchTerm objects contained by 
	 * this collection that match the specified criteria. If is_int is null (default),
	 * the criteria is not applied. If recursive is true, matching terms for child
	 * CSTCs are also returned. Recursion is false by default.
	 * @param mixed $arr_term_type MCL_SEARCH_TERM_TYPE constant or an array of such constants
	 * @param mixed $is_integer If bool, the search must be or not be an integer. If null, can be anything.
	 * @param bool $recurive If true, the function recursively calls child ConceptSearchTermCollection objects
	 */
	public function getSearchTerms($arr_term_type, $is_integer = null, $recursive = false)
	{
		if (!is_array($arr_term_type)) $arr_term_type = array($arr_term_type);
		$arr_search_term = array();
		foreach (array_keys($this->arr_search_object) as $key) 
		{
			$search_object  =  $this->arr_search_object[$key];
			if ($search_object instanceof ConceptSearchTermCollection)
			{
				// Add if a match
				if (array_search(MCL_SEARCH_TERM_TYPE_COLLECTION, $arr_term_type, true) !== false) {
					$arr_search_term[]  =  $search_object;
				}

				// If recursion is true, check for matches in the child collection
				if ($recursive) {
					$arr_search_term = array_merge($arr_search_term, 
						$search_object->getSearchTerms($arr_term_type, $is_integer, $recursive));
				}
			} else if (  (  $search_object instanceof ConceptSearchTerm                              ) &&
					     (  array_search($search_object->term_type, $arr_term_type, true) !== false  )  )
			{
				// Add if a match
				if (is_null($is_integer) || $is_integer == $search_object->isInteger()) {
					$arr_search_term[]  =  $search_object;
				}
			}
		}
		return $arr_search_term;
	}

	/**
	 * Returns the search terms that match the passed Concept matches.
	 * @param Concept $c
	 * @return array Array of search term objects that match the passed Concept object
	 */
	public function getMatchingSearchTerms(Concept $c)
	{
		$arr_search_term  =  array();
		foreach (  array_keys($this->arr_search_object) as $key  )
		{
			$search_object  =  $this->arr_search_object[$key];
			if (  $search_object instanceof ConceptSearchTermCollection  )
			{
				// Check for matches in the child collection
				$arr_search_term  =  array_merge(
						$arr_search_term, 
						$search_object->getMatchingSearchTerms( $c  )
					);
			} 
			elseif (  (  $search_object instanceof ConceptSearchTerm  )  &&
					  (  $search_object->isMatch($c)                  )  )
			{
				$arr_search_term[]  =  $search_object;
			}
		}
		return $arr_search_term;
	}
}

?>