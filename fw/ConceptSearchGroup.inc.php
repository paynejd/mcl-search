<?php

require_once(MCL_ROOT . 'fw/ConceptSearchTermFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchTermCollection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchTerm.inc.php');

/**
 * Object to store a single group within a multi-line search query. 
 * Groups are denoted in a search query by a newline character. Search terms and 
 * additional hierarchical collection objects are stored in the top-level 
 * ConceptSearchTermCollection object.
 */
class ConceptSearchGroup
{
	/**
	 * Single-line search query.
	 */
	public $query = '';

	/**
	 * Top-level OR CSTC.
	 */
	public $cstc_top = null;


	/**
	 * Constructor
	 */
	public function __construct($q = null) {
		if ($q) $this->parse($q);
	}

	/**
	 * Returns the number of objects contained in the top-level CSTC.
	 */
	public function Count() {
		if ($this->cstc_top) {
			return $this->cstc_top->Count();
		} else {
			trigger_error('ConceptSearchGroup must be initialized before calling Count.', E_USER_ERROR);
		}
	}

	/**
	 * Whether or not the search group is empty.
	 */
	public function isEmpty() {
		return !((bool)$this->Count());
	}

	/**
	 * Returns the top-level ConceptSearchTermCollection contained in this group.
	 */
	public function getSearchTermCollection() {
		return $this->cstc_top;
	}

	/**
	 * Returns an array of ConceptSearchTerm objects that are inline sources
	 * @return array
	 */
	public function getInlineSources() 
	{
		// TODO: ConceptSearchGroup::getInlineSources()
		//$this->cstc_top->getSearchTerms($term_type);
		return array();
	}

	/**
	 * Parses the search group into ConceptSearchTermCollection 
	 * and ConceptSearchTerm objects. Returns true if the query contains
	 * one or more search terms; false otherwise.
	 */
	public function parse($q)
	{
		$this->query = $q;
		if (!$q) return false;

		/*
		 * Split into search terms. There are 5 types of terms currently supported:
		 *		text_or_integer
		 *		operator:text_or_integer
		 *		"text in, quotes with separators"
		 *		operator:"text in, quotes with separators"
		 * Terms can be split by whitespace or commas. Quotes may be single or double.
		 *
		 * TODO: Support for functions or lists. e.g. fxn_name(fxn_param)
		 */
		$regexp = '~(?:(?:([\w-]+):)?)(?:(?:"([^\v"]*)")|(?:\'([^\v\']*)\')|([\w.-]+|\*))(?=\s|,|$)~';
		$arr_matches = array();
		$result = preg_match_all(  $regexp  ,  $q  ,  $arr_matches  );

		// Create ConceptSearchTermCollection objects
		$this->cstc_top        =  new ConceptSearchTermCollection();	// top-level OR cstc
		$this->cstc_top->glue  =  MCL_CONCEPT_SEARCH_GLUE_OR;
		$cstc_and              =  new ConceptSearchTermCollection();	// child AND cstc for text searches
		$cstc_and->glue        =  MCL_CONCEPT_SEARCH_GLUE_AND;

		/**
		 * Create search terms and add to appropriate CSTC.
		 * Top-level CSTC has 'OR' glue and has one-child 'AND' CSTC for
		 * text/name/desc search terms.
		 */
		$cstf = new ConceptSearchTermFactory();
		$num_search_terms_created = 0;
		foreach ($arr_matches[0] as $str_search_term) 
		{
			$arr_cst = $cstf->parse($str_search_term);
			foreach (array_keys($arr_cst) as $i) 
			{
				$num_search_terms_created++;
				if (  $arr_cst[$i]->term_type == MCL_SEARCH_TERM_TYPE_TEXT                 || 
					  $arr_cst[$i]->term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_NAME         || 
					  $arr_cst[$i]->term_type == MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION      )
				{
					$cstc_and->addSearchTerm(  $arr_cst[$i]  );
				} else {
					$this->cstc_top->addSearchTerm(  $arr_cst[$i]  );
				}
			}
		}

		if (  $cstc_and->Count()  ) {
			$this->cstc_top->addSearchTermCollection($cstc_and);
		}

		return (bool)count($num_search_terms_created);
	}
}

?>