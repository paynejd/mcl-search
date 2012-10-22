<?php

require_once(MCL_ROOT . 'fw/iConceptListDefinition.inc.php');

/**
 * Object representing the MCL.CONCEPT_LIST table. This works together
 * with ConceptListInstance and ConceptListFactory.
 */
class ConceptListDefinition implements iConceptListDefinition
{
	/**
	 * Corresponds to [mcl.concept_list.concept_list_id] or [openmrs.concept_source.concept_source_id]
	 */
	private $concept_list_id = null;

	/**
	 * Corresponds to [mcl.concept_list.list_name] or [openmrs.concept_source.name]
	 */
	private $list_name = null;

	/**
	 * List description. Either [openmrs.concept_source.description] or [mcl.concept_list.description]
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
	public function __construct() {
		// do nothing
	}

	public function getListType() {
		return MCL_CLTYPE_CONCEPT_LIST;
	}

	public function getListId() {
		return $this->concept_list_id;
	}
	public function setListId($list_id) {
		$this->concept_list_id = $list_id;
	}

	public function getName() {
		return $this->list_name;
	}
	public function setName($name) {
		$this->list_name = $name;
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