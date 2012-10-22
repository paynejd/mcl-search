<?php

class ConceptClass
{
	public $concept_class_id  =  null;
	public $name              =  null;
	public $description       =  null;
	public $uuid              =  null;

	public $css_dict          =  null;

	public function __construct($concept_class_id = null, $name = null,
		$description = null, $uuid = null)
	{
		$this->concept_class_id  =  $concept_class_id  ;
		$this->name              =  $name              ;
		$this->description       =  $description       ;
		$this->uuid              =  $uuid              ;
	}

	public function setSourceDictionary(ConceptSearchSource $css_dict)
	{
		$this->css_dict = $css_dict;
	}

	public function getSourceDictionary()
	{
		return $this->css_dict;
	}

	public function getKey()
	{
		if (!$this->css_dict) {
			trigger_error('$css_dict must be set to use this method', E_USER_ERROR);
		}
		return $this->css_dict->dict_db . ':' . $this->concept_class_id;
	}
}

?>