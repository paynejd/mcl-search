<?php

require_once(MCL_ROOT . 'fw/ConceptSearchSource.inc.php');

class ConceptSearchSourceCollection
{
	private $arr_source = array();


	public function __construct() {
		//do nothing
	}

	/**
	 * Add a ConceptSearchSource object or an array of ConceptSearchSource objects to the collection.
	 * @param mixed	$s	ConceptSearchSource object or array of ConceptSearchSource objects to add to the collection
	 */
	public function add($s) 
	{
		if (is_array($s)) {
			foreach ($s as $css) {
				if ($css instanceof ConceptSearchSource) {
					$this->arr_source[] = $css;
				} else {
					trigger_error('All elements of array parameter must be of type ConceptSearchSource in ConceptSearchSourceCollection::add($s)', E_USER_ERROR);
				}
			}
		} else if ($s instanceof ConceptSearchSource) {
			$this->arr_source[] = $s;
		} else {
			var_dump($s);
			trigger_error('ConceptSearchSourceCollection::add($s) parameter must be of type ConceptSearchSource or array', E_USER_ERROR);
		}
	}

	/**
	 * Tries to resolve an integer or string to a source identifier, which is either a source
	 * numeric ID or name. Matches first concept lists, then map sources, then dictionaries. 
	 * Use $flags to prevent matching of a source type. Limit the map source search to specific
	 * dictionaries using the $arr_dict_filter. For example, a source identifier of 
	 * 'HL-7 CVX' will be compared against all concept list names, then all map source names for
	 * all dictionaries (unless limited by $arr_dict_filter). Since it matches a map source, it
	 * will not search dictionary names. In a second example, a source identifier of the integer
	 * 1 matches a list, multiple map sources, and a dictionary, but will match the concept list
	 * because it is always matched first. 
	 * @param	mixed	$source_identifier	Integer or string identifier for a source
	 * @param	array	$arr_dict_filter	Optional array of ConceptSearchSource objects to filter the map source and dictionary search
	 * @param	int	$flags	Optional flags to control which source types are searched
	 * @return ConceptSearchSource
	 */
	function resolveSourceIdentifier($source_identifier, array $arr_dict_filter = null, 
			$flags = MCL_SOURCE_TYPE_ALL)
	{
		if (  (  $flags & MCL_SOURCE_TYPE_LIST  )  && 
			  $css = $this->getConceptList($source_identifier)  )
		{
			return $css;
		}
		if (  $flags & MCL_SOURCE_TYPE_MAP  ) 
		{
			if ($arr_dict_filter) {
				foreach ($arr_dict_filter as $css_dict) {
					if (  $css = $this->getMapSource($source_identifier, $css_dict)  ) {
						return $css;
					}
				}
			} else if (  $css = $this->getMapSource($source_identifier, null)  ) {
				return $css;
			}
		}
		if (  (  $flags & MCL_SOURCE_TYPE_DICTIONARY  )  && 
			  $css = $this->getDictionary($source_identifier)  ) 
		{
			return $css;
		}
		return null;
	}

	/**
	 * Get an array of all ConceptSearchSource objects.
	 * @return Array of ConceptSearchSource objects
	 */
	public function getAllSources()
	{
		return $this->arr_source;
	}

	/**
	 * Get an array of ConceptSearchSource objects of type MCL_SOURCE_TYPE_DICTIONARY
	 * @return Array of ConceptSearchSource objects
	 */
	public function getDictionaries()
	{
		$arr_source = array();
		foreach ($this->arr_source as $s)
		{
			if ($s->type == MCL_SOURCE_TYPE_DICTIONARY) $arr_source[] = $s;
		}
		return $arr_source;
	}
	
	/**
	 * Get an array of ConceptSearchSource objects of type MCL_SOURCE_TYPE_MAP that
	 * are members of the passed dictionary source. Returns all map sources if no 
	 * dictionary is specified.
	 * @param ConceptSearchSource $css_dict (Optional) ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY
	 * @return Array of ConceptSearchSource objects
	 */
	public function getMapSources(ConceptSearchSource $css_dict = null)
	{
		$arr_source = array();
		foreach ($this->arr_source as $s)
		{
			if ($s->type == MCL_SOURCE_TYPE_MAP && 
				(  !$css_dict || ($s->dict_id == $css_dict->dict_id)  )  ) 
			{
				$arr_source[] = $s;
			}
		}
		return $arr_source;
	}

	/**
	 * Get an array of ConceptSearchSource objects of type MCL_SOURCE_TYPE_LIST
	 * @return Array of ConceptSearchSource objects
	 */
	public function getConceptLists()
	{
		$arr_source = array();
		foreach ($this->arr_source as $s)
		{
			if ($s->type == MCL_SOURCE_TYPE_LIST) $arr_source[] = $s;
		}
		return $arr_source;
	}

	/**
	 * Number of items in the collection
	 * @return int Number of items in the collection
	 */
	public function Count()
	{
		return count($this->arr_source);
	}


	/**
	 * Parse a source text and return a ConceptSearchSource object if exists.
	 * @param string $source_text
	 */
	public function parse($source_text) 
	{
		$matches = array();
		if ($source_text == '*') 
		{
			$css = new ConceptSearchSource();
			$css->setSourceAllDictionaries();
			return $css;
		}
		elseif (  preg_match('/^(.+):map\((\d+)\)/', $source_text, $matches)  ) 
		{
			$css = $this->getMapSource($matches[2], $matches[1]);
			return $css;
		} 
		elseif (  preg_match('/^list\((\d+)\)/', $source_text, $matches)  ) 
		{
			$css = $this->getConceptList($matches[1]);
			return $css;
		} 
		else 
		{
			$css = $this->getDictionary($source_text);
			return $css;
		}
		return null;
	}

	/**
	 * Get an array of all sources that is formatted for an HTML select dropdown.
	 * @return array
	 */
	public function getHtmlSelectArray()
	{
		$arr_source_display    =  array();
		$arr_source_display[]  =  array(  'value'=>'-'  ,  'display'=>'--- CONCEPT DICTIONARIES ---'  );
		$arr_source_display[]  =  array(  'value'=>'*'  ,  'display'=>'All Dictionaries'              );
		foreach ($this->getDictionaries() as $css_dict) 
		{
			$arr_source_display[] = array('value'=>'-', 'display'=>'');
			$_value   = $css_dict->dict_db;
			$_display = $css_dict->dict_name . ' (' . $css_dict->dict_db . ')';
			$arr_source_display[] = array('value'=>$_value, 'display'=>$_display);
			foreach ($this->getMapSources($css_dict) as $css_map) {
				$_value   = $css_map->dict_db . ':map(' . $css_map->map_source_id . ')';
				$_display = ' - ' . $css_map->map_source_name;
				$arr_source_display[] = array('value'=>$_value, 'display'=>$_display);
			}
		}
		$arr_source_display[] = array('value'=>'-', 'display'=>'');
		$arr_source_display[] = array('value'=>'-', 'display'=>'--- CONCEPT LISTS ---');
		foreach ($this->getConceptLists() as $css_list) {
			$_value   = 'list(' . $css_list->list_id . ')';
			$_display = $css_list->list_name;
			$arr_source_display[] = array('value'=>$_value, 'display'=>$_display);
		}
		return $arr_source_display;
	}

	/**
	 * Returns a Html version of the collection for display (not coded/structured)
	 * @return string String representation of the collection object
	 */
	public function toHtml()
	{
		$s = '<br>Dictionaries and Map Sources<ul>';
		foreach ($this->getDictionaries() as $css_dict) {
			$s .= '<li>' . $css_dict->toString() . '<ul>';
			foreach ($this->getMapSources($css_dict) as $css_map) {
				$s .= '<li>' . $css_map->toString() . '</li>';
			} 
			$s .= '</ul></li>';
		}
		$s .= '</ul><br>Concept Lists<ul>';
		foreach ($this->getConceptLists() as $css_list) {
			$s .= '<li>' . $css_list->toString() . '</li>';
		}
		$s .= '</ul>';
		return $s;
	}

	/**
	 * Returns an array of all ConceptSearchSource objects of type MCL_SOURCE_TYPE_DICTIONARY
	 * referenced in this collection. This includes any dictionary sources and dictionary 
	 * sources referenced by map sources and concept lists.
	 * @return array 
	 */
	public function getUniqueDictionarySources()
	{
		$arr_css_dict = array();
		foreach ($this->getDictionaries() as $css_dict) {
			if (!isset($arr_css_dict[$css_dict->dict_db])) {
				$arr_css_dict[$css_dict->dict_db] = $css_dict;
			}
		} 
		foreach ($this->getMapSources() as $css_map) {
			$arr_map_dict = $css_map->getDictionarySources()->getAllSources();
			foreach ($arr_map_dict as $css_dict) {
				if (!isset($arr_css_dict[$css_dict->dict_db])) {
					$arr_css_dict[$css_dict->dict_db] = $css_dict;
				}
			}
		}
		foreach ($this->getConceptLists() as $css_list) {
			$arr_map_dict = $css_list->getDictionarySources()->getAllSources();
			foreach ($arr_map_dict as $css_dict) {
				if (!isset($arr_css_dict[$css_dict->dict_db])) {
					$arr_css_dict[$css_dict->dict_db] = $css_dict;
				}
			}
		}
		return $arr_css_dict;
	}

	/**
	 * Whether or not this collections contains a source of type MCL_SOURCE_SEARCH_ALL
	 * @return bool
	 */
	public function containsAllSearch() 
	{
		foreach ($this->arr_source as $css_dict) {
			if ($css_dict->type == MCL_SOURCE_SEARCH_ALL) return true;
		}
		return false;
	}

	/**
	 * Get string describing the dictionaries referenced in this object.
	 */
	public function toString()
	{
		if ($this->containsAllSearch()) {
			return '<b>All Dictionaries</b>';
		} 
		$s = '';
		$arr_css_dict = $this->getUniqueDictionarySources();
		foreach ($arr_css_dict as $css_dict) {
			if ($s) $s .= ', ';
			$s .= '<b>' . $css_dict->dict_name . '</b>';
			if ($css_dict->dict_last_updated)  $s .= ' (last updated ' . $css_dict->dict_last_updated . ')';
		}
		return $s;
	}

	/**
	 * Get the ConceptSearchSource object of type MCL_SOURCE_TYPE_DICTIONARY that matches the passed source identifier.
	 * Identifier can be a map_source_id or map_source_name.
	 * @param string $source_identifier
	 * @return ConceptSearchSource
	 */	
	public function getDictionary($source_identifier)
	{
		if (  $this->isInteger($source_identifier)  ) 
		{
			$source_identifier = (int)$source_identifier;
			foreach (array_keys($this->arr_source) as $key) 
			{
				$css  =  $this->arr_source[$key];
				if (  (  $css->type == MCL_SOURCE_TYPE_DICTIONARY  )  && 
					  (  $css->dict_id == $source_identifier       )  )
				{
					return $css;
				} 
			}
		}
		else
		{
			$source_identifier = strtolower($source_identifier);
			foreach ($this->arr_source as $css) 
			{
				if ($css->type == MCL_SOURCE_TYPE_DICTIONARY && 
					(   (strtolower($css->dict_db) == $source_identifier)  ||
						(strtolower($css->dict_name) == $source_identifier)   
					)
				   )
				{
					return $css;
				} 
			}
		}
		return null;
	}

	/**
	 * Get the ConceptSearchSource object of type MCL_SOURCE_TYPE_MAP that matches the passed source identifier.
	 * Identifier can be a map_source_id or map_source_name. Optionally filter map sources by dictionary.
	 * @param string $source_identifier
	 * @param mixed $dict_db ConceptSearchSource of type MCL_SOURCE_TYPE_DICTIONARY or string of the database name
	 * @return ConceptSearchSource
	 */	
	public function getMapSource($source_identifier, $css_dict = null)
	{
		// Set the dictionary name
		if ($css_dict instanceof ConceptSearchSource) {
			$dict_db = $css_dict->dict_db;
		} else {
			$dict_db = $css_dict;
		}

		// If source identifier is integer
		if (  $this->isInteger($source_identifier)  ) 
		{
			$source_identifier = (int)$source_identifier;
			foreach ($this->arr_source as $css) 
			{
				if (  $css->type == MCL_SOURCE_TYPE_MAP  &&
					  (  is_null($dict_db) || (!is_null($dict_db) && ($css->dict_db == $dict_db))  ) && 
					  $css->map_source_id == $source_identifier  )
				{
					return $css;
				} 
			}
		} 
		else 
		{
			$source_identifier = strtolower($source_identifier);
			foreach ($this->arr_source as $css) 
			{
				if (  $css->type == MCL_SOURCE_TYPE_MAP  &&
					  (  is_null($dict_db) || (!is_null($dict_db) && ($css->dict_db == $dict_db))  ) && 
					  strtolower($css->map_source_name) == $source_identifier  )
				{
					return $css;
				} 
			}
		}
		return null;
	}

	/**
	 * Get the ConceptSearchSource object of type MCL_SOURCE_TYPE_LIST that matches the passed source identifier.
	 * Identifier can be a list_id or list_name.
	 * @param string $source_identifier
	 * @return ConceptSearchSource
	 */
	public function getConceptList($source_identifier)
	{
		if ($this->isInteger($source_identifier)) {
			$source_identifier = (int)$source_identifier;
			foreach ($this->arr_source as $css) 
			{
				if ($css->type == MCL_SOURCE_TYPE_LIST && 
					$css->list_id == $source_identifier)
				{
					return $css;
				}
			}
		}
		else
		{
			$source_identifier = strtolower($source_identifier);
			foreach ($this->arr_source as $css) 
			{
				if ($css->type == MCL_SOURCE_TYPE_LIST && 
					strtolower($css->list_name) == $source_identifier)
				{
					return $css;
				}
			}
		}
		return null;
	}

	private function isInteger($s)
	{
		if (  is_numeric($s)  &&  ((int)$s) == $s  ) {
			return true;
		}
		return false;
	} 
}

?>