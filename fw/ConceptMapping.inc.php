<?php

/**
 * Object to store a single concept mapping to SNOMED, ICD-10, etc. for a parent concept object.
 */
class ConceptMapping
{
	public $concept_map_id  =  null  ;
	public $source          =  null  ;
	public $source_code     =  null  ;
	public $source_name     =  null  ;

	public function __construct($concept_map_id, $source, $source_code, $source_name)
	{
		$this->concept_map_id  =  $concept_map_id  ;
		$this->source          =  $source          ;
		$this->source_code     =  $source_code     ;
		$this->source_name     =  $source_name     ;
	}
}

?>