<?php

/**
 * Represents a single search term. Search terms can be string, numeric,
 * numeric range, or map_code. A single term may be more than one type.
 */
class ConceptSearchTerm
{
	/**
	 * Type of search term.
	 */
	public $term_type = MCL_SEARCH_TERM_TYPE_DEFAULT;

	/**
	 * Full text of the search term before parsing.
	 */
	public $full_search_term = '';

	/**
	 * Search term after the operator has been parsed out.
	 */
	public $needle = '';

	/**
	 * Value of the search operator used, if any.
	 */
	public $search_operator = '';

	/**
	 * Value of the search function used, if any.
	 */
	public $search_function = '';


	/**
	 * Constructor
	 */
	public function ConceptSearchTerm($term_type, $needle, $full_search_term = '',
			$search_operator = '', $search_function = '') 
	{
		$this->term_type = $term_type;
		$this->needle = $needle;
		if ($full_search_term) {
			$this->full_search_term = $full_search_term;
		} else {
			$this->full_search_term = $needle;
		}
		$this->search_operator = $search_operator;
		$this->search_function = $search_function;
	}


	/**
	 * If the search term is a numeric range, returns an array whose first element 
	 * is the range minimum and second element is the range maximum. If this term 
	 * is not a numeric range, returns null.
	 */
	public function getRange() 
	{
		if (substr_count($this->needle, '-') != 1) return null;
		list($min, $max) = explode('-', $this->needle);
		if ((is_numeric($min) && ((int)$min == $min)) && 
			(is_numeric($max) && ((int)$max == $max)) )
		{
			$min = (int)$min;
			$max = (int)$max;
			if ($min <= $max) return array($min, $max);
			else return array($max, $min);
		}
		return null;
	}

	/**
	 * True if the search needle evaluates to an integer 
	 * (meaning, it can be a string representation of an integer).
	 */
	public function isInteger() 
	{
		if (is_numeric($this->needle)) {
			if ((int)$this->needle == $this->needle) {
				return true;
			}
			return false;
		}
		return false;
	}

	/**
	 * Whether this search term has an operator of type MCL_OPERATOR_FILTER
	 */
	public function isFilter() {
		if (!$this->search_operator) return false;
		$cstf = new ConceptSearchTermFactory();
		return $cstf->isFilterOperator($this->search_operator);
	}

	/**
	 * True if this search term object matches the passed Concept; false otherwise.
	 * @param Concept $c
	 * @param mixed $search_term_type One of the MCL_SEARCH_TERM_TYPE_* constants. Use to force a specific type of comparison
	 * @return bool
	 */
	public function isMatch(Concept $c, $search_term_type = null)
	{
		// Prepare the search term type for comparison
		if (is_null($search_term_type)) $search_term_type = $this->term_type;

		// Now do the comparison
		if ($search_term_type == MCL_SEARCH_TERM_TYPE_ALL) {
			return true;
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_ID) {
			if ($this->needle == $c->concept_id) return true;
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_ID_RANGE) {
			list($min, $max) = explode('-', $this->needle);
			if (  ($c->concept_id >= $min) && ($c->concept_id <= $max)  )  return true;
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_MAP_CODE) {
			foreach ($c->getConceptMappingIds() as $mapcode_id) {
				$subject = $c->getConceptMapping($mapcode_id)->source_code;
				if (  preg_match('/\b' . addslashes($this->needle) . '/i', $subject)  ) return true;
			}
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_MAP_CODE_RANGE) {
			// TODO: MCL_SEARCH_TERM_TYPE_MAP_CODE_RANGE
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_TEXT) {
			foreach ($c->getConceptNameIds() as $name_id) {
				$subject = $c->getConceptName($name_id)->name;
				if (  preg_match('/\b' . addslashes($this->needle) . '/i', $subject)  ) return true;
			}
			foreach ($c->getConceptDescriptionIds() as $desc_id) {
				$subject = $c->getConceptDescription($desc_id)->description;
				if (  preg_match('/\b' . addslashes($this->needle) . '/i', $subject)  ) return true;
			}
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_NAME) {
			foreach ($c->getConceptNameIds() as $name_id) {
				$subject = $c->getConceptName($name_id)->name;
				if (  preg_match('/\b' . addslashes($this->needle) . '/i', $subject)  ) return true;
			}
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION) {
			foreach ($c->getConceptDescriptionIds() as $desc_id) {
				$subject = $c->getConceptDescription($desc_id)->description;
				if (  preg_match('/\b' . addslashes($this->needle) . '/i', $subject)  ) return true;
			}
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_UUID) {
			if (  strtolower($this->needle) == strtolower(substr($c->uuid, 0, strlen($this->needle)))  )  return true;
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_LIST) {
			// TODO: MCL_SEARCH_TERM_TYPE_LIST
		} elseif ($search_term_type == MCL_SEARCH_TERM_TYPE_MAP_SOURCE) {
			// TODO: MCL_SEARCH_TERM_TYPE_MAP_SOURCE
		}
		return false;
	}
}

?>