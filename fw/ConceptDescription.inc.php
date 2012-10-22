<?php

/**
 * Object to store a single concept definition for a parent concept object.
 */
class ConceptDescription
{
	public $concept_description_id  =  null;
	public $description             =  null;
	public $locale                  =  null;

	public function __construct($concept_description_id, $description, $locale)
	{
		$this->concept_description_id = $concept_description_id;
		$this->description = $description;
		$this->locale = $locale;
	}
}

?>