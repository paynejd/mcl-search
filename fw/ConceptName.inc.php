<?php 

/**
 * Object to store a single concept name (or synonym) for a concept object.
 */
class ConceptName
{
	public $concept_name_id      =  null  ;
	public $name                 =  null  ;
	public $locale               =  null  ;
	public $concept_name_tag_id  =  null  ;
	public $uuid                 =  null  ;
	public $concept_name_type    =  null  ;		// OpenMRS v1.9 only

	public function __construct($concept_name_id, $name, 
		$locale, $concept_name_tag_id = null, $concept_name_type = null)
	{
		$this->concept_name_id      =  $concept_name_id      ;
		$this->name                 =  $name                 ;
		$this->locale               =  $locale               ;
		$this->concept_name_tag_id  =  $concept_name_tag_id  ;
		$this->concept_name_type    =  $concept_name_type    ;
	}

	/**
	 * Returns true if this is tagged as the preferred concept name; false otherwise.
	 * NOTE: OpenMRS v1.6 uses concept name tags to indicate preferred status while
	 * v1.9 uses the concept_name_type field, while retaining concept_name_tag_id for
	 * compatibility.
	 */
	public function isPreferred() 
	{
		// TODO: Update this function to properly handle preferred concept name status
		if ($this->concept_name_tag_id == MCL_PREFERRED_CONCEPT_NAME_TAG_ID) return true;
		return false;
	}
}

?>