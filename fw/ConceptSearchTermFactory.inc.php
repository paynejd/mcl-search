<?php

require_once(MCL_ROOT . 'fw/ConceptSearchTerm.inc.php');

/**
 * Static class to evaluate and create ConceptSearchTerm objects.
 */
class ConceptSearchTermFactory
{
	public $arr_operators = array(
		'!'			    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_RESERVED),  'standalone'=>true),
		'&'			    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_RESERVED),  'standalone'=>true),
		'*'			    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_ALL),       'standalone'=>true),
		'uuid'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_UUID)),
		'map'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_MAP_CODE)),
		'#'			    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_CONCEPT_ID)),
		'id'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_CONCEPT_ID)),
		'name'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_CONCEPT_NAME)),
		'desc'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION)),
		'text'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_TEXT)),
		'list'		    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_LIST)),
		'source'	    => array('type'=>MCL_OPERATOR_SEARCH_TERM_TYPE, 'value'=>array(MCL_SEARCH_TERM_TYPE_MAP_SOURCE)),
		'in'		    => array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_IN)),
		'not-in'	    => array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_NOT_IN)),
		'in-list'		=> array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_IN_LIST)),
		'not-in-list'	=> array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_NOT_IN_LIST)),
		'in-source'		=> array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_IN_SOURCE)),
		'not-in-source'	=> array('type'=>MCL_OPERATOR_FILTER,           'value'=>array(MCL_SEARCH_TERM_TYPE_NOT_IN_SOURCE)),
	);

	public $arr_functions = array(
		'union',
		'intersect',
		'list',
	);


	/**
	 * This function returns an array of term types of the passed 
	 * needle according to the default rules. NOTE: A needle may be
	 * compatible with more term types than are returned. For example,
	 * a numeric range evaluates to a CONCEPT_ID_RANGE unless the 'map'
	 * operator is used.
	 */
	public function evaluateSearchTermTypes($search_text, $search_operator = null)
	{
		if (!$search_operator && isset($this->arr_operators[$search_text]) && 
			in_array(MCL_SEARCH_TERM_TYPE_ALL, $this->arr_operators[$search_text]['value'], true)) 
		{
			return array(
					MCL_SEARCH_TERM_TYPE_ALL
				);
		}
		if ($search_operator) {
			if (!isset($this->arr_operators[$search_operator])) {
				trigger_error("Ignoring unknown operator '" . $search_operator . "' in search query.", E_USER_WARNING);
			} elseif ($this->arr_operators[$search_operator]['type'] == MCL_OPERATOR_SEARCH_TERM_TYPE) {
				// TODO: Validate to make sure that the needle matches the operator
				return $this->arr_operators[$search_operator]['value'];
			} elseif ($this->arr_operators[$search_operator]['type'] == MCL_OPERATOR_FILTER) {
				// TODO: Does anything need to happen here for filters?
				return $this->arr_operators[$search_operator]['value'];
			}
		}
		if ($this->isIntegerRange($search_text)) {
			return array(
					MCL_SEARCH_TERM_TYPE_CONCEPT_ID_RANGE
				);
		} elseif ($this->isInteger($search_text)) {
			return array(
					MCL_SEARCH_TERM_TYPE_CONCEPT_ID, 
				);
			/*
			 * TODO: Temporarily getting rid of map codes
			return array(
					MCL_SEARCH_TERM_TYPE_CONCEPT_ID, 
					MCL_SEARCH_TERM_TYPE_MAP_CODE
				);
			 */
		} elseif ($this->isUuid($search_text)) {
			return array(
					MCL_SEARCH_TERM_TYPE_UUID
				);
		} else {	// default
			return array(
					MCL_SEARCH_TERM_TYPE_TEXT
				);
			/*
			 * TODO: Temporarily getting rid of map codes
			return array(
					MCL_SEARCH_TERM_TYPE_TEXT,
					MCL_SEARCH_TERM_TYPE_MAP_CODE
				);
			 */
		}
	}

	/**
	 * Parse the search term text and return one or more corresponding
	 * search term objects.
	 */
	public function parse($full_search_term, $term_type = MCL_SEARCH_TERM_TYPE_DEFAULT)
	{
		/*
		 * Split up the search term into operator and text. Quotes are handled properly. 
		 * $arr_matches is setup as follows:
		 *		[0][0] => full search term
		 *		[1][0] => search operator (optional)
		 *		[2][0] => search text if double quotes used (may contain whitespace/symbols)
		 *		[3][0] => search text if single quotes used (may contain whitespace/symbols)
		 *		[4][0] => search text if no quotes used (no whitespace/symbols)
		 *
		 * TODO: Complex expressions such as in:list(5, id:15) are not supported by this regexp
		 * Would instead need a character-by-character parsing script.
		 */
		$term             =  trim($full_search_term);
		$arr_matches      =  array();
		$regexp           =  '~(?:(?:([\w-]+):)?)(?:(?:"([^\v"]*)")|(?:\'([^\v\']*)\')|([\w.-]+|\*))(?=\s|,|$)~';
		$result           =  preg_match_all($regexp, $full_search_term, $arr_matches);
		$search_operator  =  '';
		if ($arr_matches[1][0]) {
			$search_operator = $arr_matches[1][0];
		} else {
			foreach ($this->arr_operators as $k => $o) {
				if (isset($o['standalone']) && $o['standalone']) {
					if ($arr_matches[0][0] == $k) {
						$search_operator = $k;		// Assign the standalone operator
						$arr_matches[4][0] = '';	// Clear the needle
						break;
					}
				}
			}
		}
		if ($arr_matches[2][0]) $search_text = $arr_matches[2][0];
		else if ($arr_matches[3][0]) $search_text = $arr_matches[3][0];
		else $search_text = $arr_matches[4][0];
		$search_function = '';	// NOTE: Not implemented, so simply blanking out


		/*
		 * Validate the search operator
		 */
		if ($search_operator && !isset($this->arr_operators[$search_operator])) {
			trigger_error("Ignoring unknown operator '" . $search_operator . "' in search query.", E_USER_WARNING);
			$search_operator = '';
		}


		/*
		// Split off the operator 
		// TODO: do with a regexp instead, because want to be able to support something like in:list(5, id:15)
		$search_operator = '';
		if (($i = strpos($term, ':')) !== false) 
		{
			list($search_operator, $term) = explode(':', $term);
			if (!isset($this->arr_operators[$search_operator])) {
				trigger_error("Ignoring unknown operator '" . $search_operator . "' in search query.", E_USER_WARNING);
				$search_operator = '';
			}
		}
		*/


		/*
		// TODO: Split off the function
		$search_function = '';
		// TODO: Test for the function format and pull out any functions, whether recognized or not
		foreach ($arr_functions as $fxn) {
			// TODO: if ($term starts with ($fxn . '(') and ends with ')') {
				// $search_function = $fxn;
				// $term = everything in between the parentheses
			// }
		}
		*/


		/*
		// Set the needle to whatever is left
		$needle = $term;
		*/


		/*
		* Validate operator/function combination
		*/
		// Operator and Function cannot be used in the same term
		// TODO: This will probably not be true after implementing 'in' and 'notin'
		/*
		if ($search_operator && $search_function) {
			trigger_error("Search operators and functions cannot both be used in search term: '" . 
				$full_search_term . "'", E_USER_WARNING);
		}
		*/

		//$arr_term_type = array();

		// TODO: Validate needle based on operator and set term types
		/*
		if (isset($this->arr_operators[$search_operator])) {
			if ($this->arr_operators[$search_operator]['type'] == MCL_OPERATOR_SEARCH_TERM_TYPE) {
				$arr_term_type[] = $this->arr_operators[$this->search_operator]['value'];
			} else {
				// TODO: This is a custom field...do anything here?
			}
		}
		*/

		// TODO: Validate needle based on function and set term types
		/*
		if (isset($this->arr_functions[$search_function])) {
		}
		*/

		/* 
		 * Get list of possible term types based on needle and search operator
		 * A single search term can evaluate to more than one term type. Term type
		 * modifications based on operators, functions, or function parameter must
		 * be validated against the possible term types.
		 */
		$arr_term_type = $this->evaluateSearchTermTypes($search_text, $search_operator);

		/*TODO: Determine possible term types
		 * The text of a single search term can evaluate to one or more term types. 
		 * Term types are evaluated in this order of priority:
		 *  - operator (unless 'ignore_operator' attribute is set to true)
		 *  - function
		 *  - specified term type
		 *  - default term type rules
		 */

		// Create ConceptSearchTerm objects
		$arr_cst = array();
		foreach ($arr_term_type as $term_type) {
			$cst = new ConceptSearchTerm($term_type, $search_text, $full_search_term,
					$search_operator, $search_function);
			$arr_cst[] = $cst;
		}
		return $arr_cst;
	}

	/**
	 * True if the search needle evaluates to an integer.
	 */
	public function isInteger($search_text) 
	{
		if (is_numeric($search_text)) {
			if ((int)$search_text == $search_text) {
				return true;
			}
			return false;
		}
		return false;
	}

	/**
	 * True if search term represents an integer range (e.g. 1011-1027); false otherwise.
	 */
	public function isIntegerRange($search_text) 
	{
		if (substr_count($search_text, '-') != 1) return false;
		list($min, $max) = explode('-', $search_text);
		if ((is_numeric($min) && ((int)$min == $min)) && 
			(is_numeric($max) && ((int)$max == $max)) )
		{
			return true;
		}
		return false;
	}

	/**
	 * Whether the passed search needle is a string search term.
	 */
	public function isString($search_text) {
		return true;
	}

	/**
	 * True if the search needle could be a map code. This is always true if the needle 
	 * is not a numeric range.
	 */
	public function isMapCode($search_text) 
	{
		if (!$this->isIntegerRange($search_text)) return true;
		return false;
	}

	/**
	 * True only if search term is exactly 36 characters long.
	 */
	public function isUuid($search_text) {
		if (!$this->isIntegerRange($search_text) && $this->isString($search_text) && strlen($search_text) == MCL_UUID_LENGTH) return true;
		return false;
	}

	public function isFilterOperator($operator) {
		if (isset($this->arr_operators[$operator]) && 
			($this->arr_operators[$operator]['type'] = MCL_OPERATOR_FILTER) )
		{
			return true;
		}
		return false;
	}
}

?>