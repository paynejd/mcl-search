<?php

require_once(MCL_ROOT . 'fw/Collection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptDatatype.inc.php');

class ConceptDatatypeCollection extends Collection
{
	private $css_dict = null;

	public function merge(ConceptDatatypeCollection $cdc)
	{
		foreach ($cdc->getKeys() as $key) {
			$this->Add($key, $cdc->Get($key));
		}
	}
	public function setDefaultDictionary(ConceptSearchSource $css_dict)
	{
		$this->css_dict = $css_dict;
	}

	/**
	 * Selector is a string key or an array of keys. Keys may be in one of these formats:
	 * 
	 * Fully specified Integer:     <dictionary_name>:datatype(<concept_datatype_id>)
	 * Fully specified name:		<dictionary_name>:datatype(<concept_datatype_name>)
	 * Default dictionary:  		<concept_datatype_id>
	 * Only name:					<concept_datatype_name>
	 * 
	 * <concept_datatype_name> must be quoted if it contains non-word characters.
	 * Note that setDefaultDictionary() must be called if using the default dictionary format.
	 */
	public function getDatatypes($selector)
	{
		$cdc = new ConceptDatatypeCollection();
		if (!is_array($selector)) $selector = array($selector);
		foreach ($selector as $key)
		{
			$key = strtolower(trim($key));

			// <dictionary_name>:datatype(<concept_datatype_id>) OR <dictionary_name>:datatype(<concept_datatype_name>)
			if (  (  strpos(  $key  ,  ':datatype('  ,  0               )  !== false  )  &&  
				  (  strpos(  $key  ,  ')'        ,  strlen($key)-1  )  !== false  )  ) 
			{
				// Parse out dict_name and datatype_identifier
				$dict_name = trim(  substr($key, 0, strpos($key, ':datatype')  )  );
				$datatype_identifier = trim(  substr(
						$key, 
						strlen($dict_name) + strlen(':datatype('),
						strlen($key) - strlen($dict_name) - strlen(':datatype(') - 1
					)  );
				if (substr($datatype_identifier, 0, 1) == '"' && 
					substr($datatype_identifier, strlen($datatype_identifier) - 1, 1) == '"')
				{
					$datatype_identifier = trim(substr($datatype_identifier, 1, strlen($datatype_identifier) - 2));
				}

				// <dictionary_name>:datatype(<concept_datatype_id>)
				if ($this->isInteger($datatype_identifier)) {
					$new_key = $dict_name . ':datatype(' . $datatype_identifier . ')';
						if ($this->IsMember($new_key)) $cdc->Add($new_key, $this->Get($new_key));
				} 

				// <dictionary_name>:datatype(<concept_datatype_name>)
				else
				{
					foreach ($this->getKeys() as $datatype_key) {
						$datatype = $this->Get($datatype_key);
						if (  (  strtolower($datatype->name)  ==  $datatype_identifier   )  && 
							  (  strtolower($datatype->getSourceDictionary()->dict_db)  ==  $dict_name  )  ) 
						{
							$cdc->Add($datatype_key, $datatype);
							break;
						}
					}
				}

			}

			// <concept_datatype_id> OR <concept_datatype_name>
			else 
			{
				// <concept_datatype_id> - this uses the default dictionary setting
				if (  $this->isInteger($key)  )
				{
					// Default dictionary key
					if (!$this->css_dict) {
						trigger_error('Default dictionary must be set in ConceptDatatypeCollection if using default dictionary format', E_USER_ERROR);
					}
					$key = $this->css_dict->dict_db . ':datatype(' . $key . ')';
					if ($this->IsMember($key)) $cdc->Add($key, $this->Get($key));
				}

				// <concept_datatype_name> - a single term canmatch multiple dictionaries
				else
				{
					foreach ($this->getKeys() as $datatype_key) {
						$datatype = $this->Get($datatype_key);
						if (  (  strtolower($datatype->name)  ==  $key   )  ) 
						{
							$cdc->Add($datatype_key, $datatype);
						}
					}
				}
			}
		}
		return $cdc;
	}

	/**
	 * Get an array of all concept classes formatted for an HTML select dropdown.
	 * This method automatically combines datatypes of the same name across dictionaries
	 * into a single select box.
	 * @return array
	 */
	public function getHtmlChecklistArray(ConceptDatatypeCollection $coll_selected = null)
	{
		/**
		 * $arr_display has 3 fields: value, display, source. Source is another array of 
		 * dictionary sources in which the object is present.
		 */
		$arr_display = array();

		// Combine 
		foreach ($this->getKeys() as $key) 
		{
			$o = $this->Get($key);
			if (  !isset($arr_display[strtolower($o->name)])  ) {
				$arr_display[strtolower($o->name)] = array(
						'value'     =>  strtolower($o->name)  ,
						'display'   =>  $o->name              ,
						'hint'      =>  ''                    ,
						'selected'  =>  false                 ,
						'source'    =>  array()
					);
			}
			if (  ($arr_display[strtolower($o->name)]['selected'] === false)  &&
				  ($coll_selected->IsMember($key))                            )
			{
				$arr_display[strtolower($o->name)]['selected'] = true;
			}
			$arr_display[strtolower($o->name)]['source'][] = $o->getSourceDictionary()->dict_db;
		}

		// Set hint column to indicate the list of dictionaries
		foreach (array_keys($arr_display) as $key) {
			$hint = '';
			foreach ($arr_display[$key]['source'] as $source_text) {
				if ($hint) $hint .= ', ';
				$hint .= $source_text;
			}
			if ($hint) $hint = '"' . $arr_display[$key]['display'] . '" is in the following dictionaries: ' . $hint;
			$arr_display[$key]['hint'] = $hint;
		}

		return $arr_display;
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