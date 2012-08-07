<?php 

/**
 * Object to store a single concept name (or synonym) for a concept object.
 */
class ConceptName
{
	public $concept_name_id      =  null;
	public $name                 =  null;
	public $locale               =  null;
	public $concept_name_tag_id  =  null;
	public $uuid                 =  null;

	public function __construct($concept_name_id, $name, 
		$locale, $concept_name_tag_id = null)
	{
		$this->concept_name_id      =  $concept_name_id      ;
		$this->name                 =  $name                 ;
		$this->locale               =  $locale               ;
		$this->concept_name_tag_id  =  $concept_name_tag_id  ;
	}
	
	public function isPreferred() 
	{
		if ($this->concept_name_tag_id == 4) return true;
		return false;
	}
}

?>