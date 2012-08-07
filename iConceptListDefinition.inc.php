<?php

/**
 * Interface to treat definitions of MCL Concept Lists, OMRS Map Sources, 
 * or others lists of concepts in a generic fashion.
 */
interface iConceptListDefinition
{
	public function getListType();

	public function getListId();
	public function setListId($list_id);

	public function getName();
	public function setName($name);

	public function getDescription();
	public function setDescription($desc);

	public function setDictionary($dict_id, $dict_name, $dict_db);
	public function getDictionaryId();
	public function getDictionaryName();
	public function getDictionaryDatabase();
}

?>