<?php

require_once(MCL_ROOT . 'fw/iConceptListDefinition.inc.php');

/**
 * Object representing the OpenMRS concept_source table using the 
 * iConceptListDefinition interface, allowing OpenMRS map sources to be 
 * compatible with MCL's ConceptList functions.
 */
class MapSourceDefinition implements iConceptListDefinition
{
	/**
	 * Corresponds to [openmrs.concept_source.concept_source_id]
	 */
	private $concept_source_id = null;

	/**
	 * Corresponds to [openmrs.concept_source.name]
	 */
	private $name = null;

	/**
	 * List description. [mcl.concept_list.description]
	 */
	private $description = '';

	/**
	 * Corresponds to [mcl.concept_dict.dict_id]
	 */
	private $dict_id = null;

	/**
	 * Corresponds to [mcl.concept_dict.dict_name]
	 */
	private $source_name = null;

	/**
	 * Corresponds to mcl.concept_dict.db_name
	 */
	private $source_db = null;


	/**
	 * Constructor
	 */
	function MapSourceDefinition() {
		// do nothing
	}

	public function getListType() {
		return MCL_CLTYPE_MAP_SOURCE;
	}

	public function getListId() {
		return $this->concept_source_id;
	}
	public function setListId($list_id) {
		$this->concept_source_id = $list_id;
	}

	public function getName() {
		return $this->name;
	}
	public function setName($name) {
		$this->name = $name;
	}

	public function getDescription() {
		return $this->description;
	}
	public function setDescription($desc) {
		$this->description = $desc;
	}

	public function setDictionary($dict_id, $dict_name, $dict_db) {
		$this->dict_id = $dict_id;
		$this->source_name = $dict_name;
		$this->source_db = $dict_db;
	}
	public function getDictionaryId() {
		return $this->dict_id;
	}
	public function getDictionaryName() {
		return $this->source_name;
	}
	public function getDictionaryDatabase() {
		return $this->source_db;
	}
}

?>