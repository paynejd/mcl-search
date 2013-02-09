<?php

require_once(MCL_ROOT . 'fw/ConceptDescription.inc.php');
require_once(MCL_ROOT . 'fw/ConceptName.inc.php');
require_once(MCL_ROOT . 'fw/ConceptMapping.inc.php');
require_once(MCL_ROOT . 'fw/ConceptNumericRange.inc.php');


/**
 * The main concept object.
 * NOTE: Concept->$display is a variable used by the renderer object.
 */
class Concept 
{
	public $concept_id        =  null  ;
	public $retired           =  null  ;
	public $is_set            =  null  ;
	public $class_id          =  null  ;
	public $class_name        =  null  ;
	public $datatype_id       =  null  ;
	public $datatype_name     =  null  ;
	public $uuid              =  null  ;

	private $_range           =  null  ;	// numeric range

	private $arr_name         =  array()  ;
	private $arr_description  =  array()  ;
	private $arr_question     =  array()  ;
	private $arr_answer       =  array()  ;
	private $arr_mapping      =  array()  ;
	private $arr_children     =  array()  ;
	private $arr_parents      =  array()  ;
	private $arr_attr         =  array()  ;

	/**
	 * ConceptSearchSource object of type MCL_SOURCE_TYPE_DICTIONARY
	 */
	public $css_dict          =  null;

	/**
	 * Array of concept_list_ids that this concept is a member of.
	 */
	private $arr_concept_list_id  =  array();

	/**
	 * Whether the concept should be displayed in the primary search results.
	 * True if queried by one of the search terms. False if it only referenced 
	 * in a concept relationship (q/a or hierarchy).
	 */
	public $display  =  true;

	
	/**
	 * Constructor
	 */
	public function __construct(
			$concept_id = null,
			$retired = null, 
			$is_set = null,
			$class_id = null, 
			$class_name = null,
			$datatype_id = null, 
			$datatype_name = null, 
			$is_core = false
		)
	{
		$this->concept_id     =  $concept_id;
		$this->retired        =  $retired;
		$this->is_set         =  $is_set;
		$this->class_id       =  $class_id;
		$this->class_name     =  $class_name;
		$this->datatype_id    =  $datatype_id;
		$this->datatype_name  =  $datatype_name;
		$this->is_core        =  $is_core;
	}

	/**
	 * Add ConceptName object to this Concept.
	 */
	public function addName($concept_name) 
	{
		$this->arr_name[$concept_name->concept_name_id] = $concept_name;
	}
	
	/**
	 * Add ConceptDescription object to this Concept.
	 */
	public function addDescription($concept_description) 
	{
		$this->arr_description[$concept_description->concept_description_id] = $concept_description;
	}
	
	/**
	 * Add a concept_id as an answer to this Concept
	 */
	public function addAnswer($answer_id) 
	{
		$this->arr_answer[$answer_id] = $answer_id;
	}
	
	/**
	 * Add a concept_id as a question to this Concept
	 */
	public function addQuestion($question_id) 
	{
		$this->arr_question[$question_id] = $question_id;
	}

	/**
	 * Add ConceptMapping object to this Concept.
	 */
	public function addMapping($concept_map) 
	{
		$this->arr_mapping[$concept_map->source . '_' . $concept_map->source_code] = $concept_map;
	}
	
	/**
	 * Add concept_id as a concept set child to this Concept.
	 */
	public function addChild($child_id) {
		$this->arr_children[$child_id] = $child_id;
	}
	
	/** 
	 * Add concept_id as a concept set parent to this Concept.
	 */
	public function addParent($parent_id) {
		$this->arr_parents[$parent_id] = $parent_id;
	}

	/**
	 * Add concept_list_id to this concept, indicating that this 
	 * concept is a member of the list.
	 */
	public function addConceptList($concept_list_id) 
	{
		$this->arr_concept_list_id[$concept_list_id] = $concept_list_id;
	}
	
	/**
	 * Get the preferred ConceptName object for this Concept.
	 *
	 * TODO: Update this to properly handle OMRS versions and the new concept_name_type field
	 */
	public function getPreferredConceptName($default_locale = 'en') 
	{
		$return_cn  =  null;
		foreach ($this->getConceptNameIds() as $_id) 
		{
			$cn  =  $this->getConceptName($_id);

				/*
			 * TODO: Preferred name method that works in OpenMRS v1.6 and v1.9?
			 * NOTE: In OpenMRS v1.6, generic preferred 
			 * concept_name_tag_id == MCL_PREFERRED_CONCEPT_NAME_TAG_ID.
			 * This is not used in the CIEL Dictionary v1.9, not sure about 
			 * PIH or AMPATH. Also note that this is both a data issue and a 
			 * process issue. Need to take locale into consideration as well.
			 */
			if (  $cn->concept_name_tag_id == MCL_PREFERRED_CONCEPT_NAME_TAG_ID || 
				  $cn->concept_name_type   == MCL_PREFERRED_CONCEPT_NAME_TYPE   ) 
			{
				// go ahead and return this since it is preferred
				$return_cn  =  $cn;
				return $return_cn;
			} 
			elseif ($cn->locale == $default_locale && !$return_cn) 
			{
				// default to default_locale if no preferred set
				$return_cn  =  $cn;
			}
		}
		return $return_cn;
	}
	
	/**
	 * Get the preferred concept name as a string.
	 */
	public function getPreferredName($default_locale = 'en') 
	{
		$cn  =  $this->getPreferredConceptName($default_locale);
		if ($cn) return $cn->name;
		return '***NONE***';
	}

	/** 
	 * Get the preferred locale as a string.
	 *
	 * TODO: Update to handle OpenMRS v1.9
	 */
	public function getPreferredLocale($default_locale = 'en') 
	{
		$cn  =  $this->getPreferredConceptName($default_locale);
		if ($cn) return $cn->locale;
		return '-';
	}

	/**
	 * Get the id of the preferred concept name.
	 */
	public function getPreferredNameId($default_locale = 'en') 
	{
		$cn = $this->getPreferredConceptName($default_locale);
		if ($cn) return $cn->concept_name_id;
		return null;
	}

	/**
	 * True if at least one ConceptDescription object has been added to this Concept.
	 */
	public function hasDescriptions() 
	{
		return (bool)count($this->arr_description);
	}
	
	/**
	 * Returns an array of ids for the ConceptDescription objects.
	 */
	public function getConceptDescriptionIds() 
	{
		return array_keys($this->arr_description);
	}
	
	/**
	 * Returns the ConceptDescription object of the specified id.
	 */
	public function getConceptDescription($concept_description_id) 
	{
		if (isset($this->arr_description[$concept_description_id])) {
			return $this->arr_description[$concept_description_id];
		}
		return null;
	}

	public function hasSynonyms() {
		return (bool)count($this->arr_name);
	}
	public function getNumberSynonyms() {
		return count($this->arr_name);
	}
	public function getConceptNameIds() {
		return array_keys($this->arr_name);
	}
	public function getConceptName($concept_name_id) {
		if (isset($this->arr_name[$concept_name_id])) {
			return $this->arr_name[$concept_name_id];
		}
		return null;
	}

	public function hasMappings() {
		return (bool)count($this->arr_mapping);
	}
	public function getConceptMappingIds() {
		return array_keys($this->arr_mapping);
	}
	public function getConceptMapping($concept_map_id) {
		if (isset($this->arr_mapping[$concept_map_id])) {
			return $this->arr_mapping[$concept_map_id];
		}
		return null;
	}
	public function getMappingsBySourceName($source_name) {
		$arr_mapping = array();
		foreach (array_keys($this->arr_mapping) as $concept_map_id) 
		{
			if ($this->arr_mapping[$concept_map_id]->source_name == $source_name) {
				$arr_mapping[$concept_map_id] = $this->arr_mapping[$concept_map_id];
			}
		}
		return $arr_mapping;
	}
	
	public function hasAnswers() {
		return (bool)count($this->arr_answer);
	}
	public function getAnswerIds() {
		return array_keys($this->arr_answer);
	}
	public function getNumberAnswers() {
		return count($this->arr_answer);
	}
	
	public function hasQuestions() {
		return (bool)count($this->arr_question);
	}
	public function getQuestionIds() {
		return array_keys($this->arr_question);
	}
	public function getNumberQuestions() {
		return count($this->arr_question);
	}
	
	public function hasParents() {
		return (bool)count($this->arr_parents);
	}
	public function getNumberParents() {
		return count($this->arr_parents);
	}
	public function getParentIds() {
		return array_keys($this->arr_parents);
	}
	public function hasChildren() {
		return (bool)count($this->arr_children);
	}
	public function getNumberChildren() {
		return count($this->arr_children);
	}
	public function getChildrenIds() {
		return array_keys($this->arr_children);
	}

	public function hasConceptLists() {
		return (bool)count($this->arr_concept_list_id);
	}
	public function getConceptListIds() {
		return $this->arr_concept_list_id;
	}


	/**
	 * Numeric Range functions
	 */
	public function setNumericRange($range) {
		$this->_range = $range;
	}
	public function getNumericRange() {
		if ($this->_range instanceof ConceptNumericRange) {
			return $this->_range;
		}
		return null;
	}


	/**
	 * Attribute Collection Methods - these store any additional data that
	 * is displayed in the "details" section of the concepts.
	 */
	public function setAttribute($name, $value) {
		$this->arr_attr[$name] = $value;
	}
	public function setAttributes($arr_attr) {
		if (is_array($arr_attr)) {
			$this->arr_attr = array_merge($this->arr_attr, $arr_attr);
		}
	}
	public function getAttribute($name) {
		if (isset($this->arr_attr[$name])) return $this->arr_attr[$name];
		return null;
	}
	public function isAttribute($name) {
		return isset($this->arr_attr[$name]);
	}
	public function hasAttributes() {
		return (bool)count($this->arr_attr);
	}
	public function getNumberAttributes() {
		return count($this->arr_attr);
	}
	public function getAttributesArray() {
		return $this->arr_attr;
	}
}

?>