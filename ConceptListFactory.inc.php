<?php

require_once (MCL_ROOT . 'ConceptList.inc.php');
require_once (MCL_ROOT . 'ConceptListDefinition.inc.php');
require_once (MCL_ROOT . 'MapSourceDefinition.inc.php');


/**
 * Used to load ConceptList objects from and persist them to the database.
 */
class ConceptListFactory
{
	/**
	 * Database connection resource.
	 */
	private $cxn = null;

	/**
	 * Display debug info if set to true.
	 */
	public $debug = false;

	/**
	 * Display verbose info if set to true.
	 */
	public $verbose = false;


	/**
	 * Constructor
	 */
	public function __construct()
	{
		// do nothing
	}
	
	/**
	 * Set the connection resource used by this object.
	 */
	public function setConnection($cxn)
	{
		$this->cxn  =  $cxn;
	}
	
	/**
	 * Get the connection resource used by this object.
	 */
	public function getConnection()
	{
		return $this->cxn;
	}

	/**
	 * Public router function to load a list ID from the name of any list type.
	 */
	public function loadConceptListId($name, $cl_type)
	{
		$id = null;
		switch ($cl_type) {
			case MCL_CLTYPE_CONCEPT_LIST:
				$id = $this->_loadConceptListId($name);
				break;
			case MCL_CLTYPE_MAP_SOURCE:
				$id = $this->_loadMapSourceId($name);
				break;
			default:
				trigger_error('Unkown CLTYPE in ConceptListFactory::loadConceptListId', E_USER_ERROR);
				die();
		}
		return $id;
	}

	/**
	 * Private function to load mcl.concept_list.concept_list_id from a list name.
	 */
	private function _loadConceptListId($name)
	{
		// TODO
	}

	/**
	 * Private function to load openmrs.concept_source.source_id from a the map source name.
	 */
	private function _loadMapSourceId($name)
	{
		// TODO
	}

	/**
	 * Public router function to load the definition of any type of concept list. 
	 * $id parameter can be a numeric list ID or the list name.
	 */
	public function loadConceptListDefinition($id_or_name, $cl_type)
	{
		// Get the list ID if passed a list name
		if (is_numeric($id_or_name)) {
			$id = $id_or_name;
		} else {
			$id = $this->loadConceptListId($id_or_name, $cl_type);
			if (!$id) return null;
		}

		// Load the definition object
		$cld = null;
		switch ($cl_type) {
			case MCL_CLTYPE_CONCEPT_LIST:
				$cld  = $this->_loadConceptListDefinition($id);
				break;
			case MCL_CLTYPE_MAP_SOURCE:
				$cld  =  $this->loadMapSourceDefinition($id);
				break;
			default:
				trigger_error('Unkown CLTYPE in ConceptListFactory::loadConceptListDefinition', E_USER_ERROR);
				die();
		}
		return $cld;
	}

	/**
	 * Private function to load a MCL Concept List definition object.
	 */
	private function _loadConceptListDefinition($id)
	{
		// Load the concept list definition
		$sql_list = 
			'select cl.concept_list_id, cl.list_name, cd.dict_id, cd.dict_name, cd.db_name ' .
			'from mcl.concept_list cl ' . 
			'left join mcl.concept_dict cd on cd.dict_id = cl.dict_id ' . 
			'where cl.concept_list_id = ' . $id;
		$rsc_list = mysql_query($sql_list, $this->getConnection());
		if (!$rsc_list) {
			echo "could not query db in ConceptListFactory::_loadConceptListDefinition: " . mysql_error();
			exit();
		}

		// Create the ConceptListDefinition object
		$cld = null;
		if ($row = mysql_fetch_assoc($rsc_list)) 
		{
			$cld = new ConceptListDefinition();
			$cld->setListId($row['concept_list_id']);
			$cld->setName($row['list_name']);
			$cld->setDescription('');
			$cld->setDictionary($row['dict_id'], $row['dict_name'], $row['db_name']);
		}

		return $cld;
	}

	/**
	 * Private function to load a map source definition object.
	 */
	private function _loadMapSourceDefinition($id)
	{
		// Load the map source definition
		$sql_map_source = 
			'select cs.concept_source_id, cs.name, cs.description ' .
			'from ' . $dict_name . '.concept_source cs ' . 
			'where cs.concept_source_id = ' . $id;
		$rsc_map_source = mysql_query($sql_map_source, $this->getConnection());
		if (!$rsc_map_source) {
			echo "Could not query db in ConceptListFactory::_loadMapSourceDefinition: " . mysql_error();
			exit();
		}

		// Create the ConceptList object
		$msd = null;
		if ($row = mysql_fetch_assoc($rsc_map_source)) 
		{
			$msd = new MapSourceDefinition();
			$cl->setListId($id);
			$cl->setName($row['name']);
			$cl->setDescription($row['description']);
			$cl->setDictionary(1, $mcl_default_concept_dict_name, 
					$mcl_default_concept_dict_db);
		}

		return $msd;
	}

	/**
	 * Public function to load a ConceptList object corresponding with the specified 
	 * list ID, list name, or iConceptListDefinition. $cl_type is ignored if list definition object
	 * is used, but required if a list ID is used.
	 */
	public function loadConceptList(  $cld  ,  $cl_type = null  )
	{
		// Load the iConceptListDefinition if an ID was passed
		if ($cld instanceof iConceptListDefinition) {
			$cl_type  =  $cld->getListType();
		} else {
			$cld  =  $this->loadConceptListDefinition(  $cld  ,  $cl_type  );
			if (!$cld) return null;
		}

		// Load the list
		$cl = null;
		switch ($cl_type) {
			case MCL_CLTYPE_CONCEPT_LIST:
				$cl  =  $this->_loadConceptList($cld);
				break;
			case MCL_CLTYPE_MAP_SOURCE:
				$cl  =  $this->_loadMapSource($cld);
				break;
			default:
				trigger_error('Unkown CLTYPE in ConceptListFactory::loadConceptList', E_USER_ERROR);
				die();
		}
		return $cl;
	}

	/**
	 * Private function to load a MCL concept list.
	 */
	private function _loadConceptList($cld)
	{
		// Load the concepts
		$sql_concepts = 
			'select clm.concept_id ' .
			'from mcl.concept_list_map clm ' . 
			'where clm.concept_list_id = ' . $cld->getListId() .
			' order by clm.concept_id';
		$rsc_concepts = mysql_query($sql_concepts, $this->getConnection());
		if (!$rsc_concepts) {
			echo "Could not query db in ConceptListFactory::_loadConceptList: " . mysql_error();
			exit();
		}

		// Put the data in to the ConceptList object
		$cl = new ConceptList($cld);
		while ($row = mysql_fetch_assoc($rsc_concepts)) {
			$_id = $row['concept_id'];
			$cl->addConcept($_id);
		}

		return $cl;
	}

	/**
	 * Private function to load an openmrs map source.
	 */
	private function _loadMapSource($cld)
	{
		// Load the concept ids from the concept map source
		$sql_concepts = 
			'select cm.concept_id ' . 
			'from ' . $dict_name . '.concept_map cm ' . 
			'where cm.source = ' . $cld->getListId();
		$rsc_concepts = mysql_query($sql_concepts, $this->getConnection());
		if (!$rsc_concepts) {
			echo "could not query db in ConceptListFactory::_loadMapSource: " . mysql_error();
			exit();
		}

		// Put the data in to the ConceptList object
		$cl = new ConceptList($cld);
		while ($row = mysql_fetch_assoc($rsc_concepts)) {
			$_id = $row['concept_id'];
			$cl->addConcept($_id);
		}

		return $cl;
	}

	/**
	 * Returns an array containing info about each of the Concept Lists in the db.
	 * Columns returned are:
	 *  concept_list_id, list_name, dict_id, dict_name, db_name, list_descriptor
	 * where list_descriptor condenses all these into a single line.
	 */
	public function getConceptListsArray() 
	{
		// get the data
		$sql_concept_lists = 
			'select cl.concept_list_id, cl.list_name, cd.dict_id, cd.dict_name, cd.db_name ' .
			'from mcl.concept_list cl ' . 
			'left join mcl.concept_dict cd on cd.dict_id = cl.dict_id ' . 
			'order by cl.list_name';
		if ($this->debug) {
			echo '<p><b>Loading names of concept lists:</b><br> ' . $sql_concept_lists . '</p>';
		}
		$rsc_concept_lists = mysql_query($sql_concept_lists, $this->getConnection());
		if (!$rsc_concept_lists) {
			die('Could not query db in ConceptListFactory::getConceptListsArray: ' . mysql_error());
		}
		
		// put the data into an array
		$arr_concept_lists = array();
		while ($row = mysql_fetch_assoc($rsc_concept_lists)) {
			$_id = $row['concept_list_id'];
			$row['list_descriptor'] = $row['list_name'] . ' (' . $row['db_name'] . ')';
			$arr_concept_lists[$_id] = $row;
		}
		
		return $arr_concept_lists;
	}

	/**
	 * Returns an array containing info about each of the Concept Sources in the db.
	 * The appropriate database should be set before calling this function.
	 * Columns returned are:
	 *  concept_source_id, name, description, retired, list_descriptor
	 * where list_descriptor condenses all these into a single line.
	 */
	public function getConceptSourcesArray()
	{
		// get the data
		$sql_cs = 
			'select cs.concept_source_id, cs.name, cs.description, cs.retired ' .
			'from concept_source cs ' . 
			'order by cs.name';
		if ($this->debug) {
			echo '<p><b>Loading concept sources:</b><br> ' . $sql_cs . '</p>';
		}
		$rsc_cs = mysql_query($sql_cs, $this->getConnection());
		if (!$rsc_cs) {
			die('Could not query db in ConceptListFactory::getConceptSourcesArray: ' . mysql_error());
		}

		// put the data into an array
		$arr_cs = array();
		while ($row = mysql_fetch_assoc($rsc_cs)) {
			$_id = $row['concept_source_id'];
			$row['list_descriptor'] = $row['name'];
			$arr_cs[$_id] = $row;
		}

		return $arr_cs;
	}

	/**
	 * Get array of available dictionaries. Returned columns:
	 *   dict_id, dict_name, db_name, dict_descriptor
	 * where dict_descriptor combines all of these into a single field,
	 
	 */
	public function getDictionariesArray()
	{
		$arr_dicts = array();
		if (isset($_SESSION['__concept_search__']['arr_dicts'])) 
		{
			$arr_dicts = $_SESSION['__concept_search__']['arr_dicts'];
		} else {
			// get the data
			$sql_dicts = 
				'select cd.dict_id, cd.dict_name, cd.db_name ' .
				'from mcl.concept_dict cd ' . 
				'order by cd.dict_id';
			$rsc_dicts = mysql_query($sql_dicts, $this->getConnection());
			if (!$rsc_dicts) {
				die('Could not query db in ConceptListFactory::getDictionariesArray: ' . mysql_error());
			}

			// put the data into an array
			while ($row = mysql_fetch_assoc($rsc_dicts)) {
				$_id = $row['dict_id'];
				$row['dict_descriptor'] = $row['dict_name'] . ' (' . $row['db_name'] . ')';
				$arr_dicts[$_id] = $row;
			}
		}
		$_SESSION['__concept_search__']['arr_dicts'] = $arr_dicts;
		return $arr_dicts;
	}

	/**
	 * Create a new concept list with the passed name and source concept 
	 * dictionary. Return the new concept_list_id on success, or false on failure.
	 */
	public function insertConceptList(ConceptList $cl)
	{
		// Make sure the concept list name is unique
		if (!$this->isUniqueConceptListName($cl->cld->getName())) {
			trigger_error('Unable to create list, because concept list name is not unique: <strong>' . $list_name . '</strong>', E_USER_ERROR);
		}

		// Insert the list
		$sql_insert_list = 
			'insert into mcl.concept_list (list_name) values (' . 
			"'" . mysql_real_escape_string($cl->cld->getName(), $this->getConnection()) . "')";
		if (!mysql_query($sql_insert_list, $this->getConnection()) ||
			!($new_concept_list_id = mysql_insert_id($this->getConnection()))) 
		{
			trigger_error('Unable to insert record into mcl.concept_list: ' . mysql_error());
			return false;
		}

		// Insert the concepts
		if (  $cl->getCount()  &&  $new_concept_list_id  )
		{
			// Build the sql statement
			$arr_concepts = $cl->getArray();
			$glue = '),(' . $new_concept_list_id . ',';
			$sql_insert_concepts = 
				'insert into mcl.concept_list_map (concept_list_id, concept_id) values ' . 
				'(' . $new_concept_list_id . ',' . implode($glue, $arr_concepts) . ')';
			if ($this->debug) {
				echo '<p>Inserting concept ids into list ' . $new_concept_list_id . 
					':<br />' . $sql_insert_concepts . '</p>';
			}
			if (!mysql_query($sql_insert_concepts, $this->getConnection())) {
				trigger_error('Unable to insert concepts into list ' . $new_concept_list_id . 
					': ' . mysql_error());
			}
		}

		return $new_concept_list_id;
	}

	/**
	 * Returns the dict_id of the concept dictionary that matches the 
	 * passed source_db. Returns null if the dictionary is not found.
	 */
	public function getDictionaryId($source_db)
	{
		$source_db = strtolower(mysql_real_escape_string($source_db, $this->getConnection()));
		$sql_dict_id = 
			'select dict_id from mcl.concept_dict ' . 
			"where db_name = '" . $source_db . "'";
		$rsc_dict_id = mysql_query($sql_dict_id, $this->getConnection());
		if (!$rsc_dict_id) {
			trigger_error("Could not query db in ConceptListFactory::getDictionaryId: " . mysql_error(), E_USER_ERROR);
		}
		if ($row = mysql_fetch_assoc($rsc_dict_id)) {
			return $row['dict_id'];
		}
		return null;
	}

	/**
	 * Return whether the passed concept list name is unique. 
	 * This function is case insensitive.
	 */
	public function isUniqueConceptListName($list_name)
	{
		$list_name = strtolower(mysql_real_escape_string($list_name, $this->getConnection()));
		$sql_unique = 
			'select concept_list_id from mcl.concept_list ' . 
			"where lower(list_name) = '" . strtolower($list_name) . "'";
		$rsc_unique = mysql_query($sql_unique, $this->getConnection());
		if (!$rsc_unique) {
			trigger_error("Could not query db in ConceptListFactory::isUniqueConceptListName: " . mysql_error(), E_USER_ERROR);
		}
		if (mysql_num_rows($rsc_unique)) {
			return false;
		}
		return true;
	}

	/**
	 * Delete the concept list associated with the passed ID.
	 */
	public function deleteConceptList($concept_list_id)
	{
		// Delete the mcl.concept_list_map records
		$sql_delete = 
			'delete from mcl.concept_list_map ' .
			'where concept_list_id = ' . $concept_list_id;
		if ($this->debug) {
			echo '<p>Deleting mcl.concept_list_map records: <br />' . $sql_delete . '</p>';
		}
		if (!mysql_query($sql_delete, $this->getConnection())) {
			trigger_error("Could not delete mcl.concept_list_map records: " . mysql_error(), E_USER_ERROR);
		}

		// Delete the mcl.concept_list record
		$sql_delete = 
			'delete from mcl.concept_list ' .
			'where concept_list_id = ' . $concept_list_id;
		if ($this->debug) {
			echo '<p>Deleting mcl.concept_list record: <br />' . $sql_delete . '</p>';
		}
		if (!mysql_query($sql_delete, $this->getConnection())) {
			trigger_error("Could not delete mcl.concept_list record: " . mysql_error(), E_USER_ERROR);
		}
		return true;
	}

	/**
	 * Update the concept list associated with the passed ID.
	 */
	public function updateConceptList($concept_list_id, $list_name, $source_db, $csv_concept_id)
	{
		$list_name = trim($list_name);

		// Make sure csv_concept_id is just whitespace, commas, and integers
		if (preg_match('/[^\d,\s]/', $csv_concept_id)) {
			trigger_error('$csv_concept_id may only contain integers, commas, and whitespace', E_USER_ERROR);
		}

		// Load the concept list with data currently in db
			$arr_update_concepts = explode(',', $csv_concept_id);
			$cl  =  $this->Load($concept_list_id);

		// Update the mcl.concept_list record if changed
			if ($cl->list_name != $list_name) 
			{
				if (!$this->isUniqueConceptListName($list_name)) {
					trigger_error('List name <strong>' . $list_name . 
						'</strong> is not unique. Update canceled.', E_USER_ERROR);
				}
				$sql_update = 
					"update mcl.concept_list set list_name = '" . 
					mysql_real_escape_string($list_name, $this->getConnection()) . "' " .
					'where concept_list_id = ' . $concept_list_id;
				if ($this->debug) {
					echo '<p>Updating concept name: <br />' . $sql_update . '</p>';
				}
				if (!mysql_query($sql_update, $this->getConnection())) {
					trigger_error('Unable to update concept list name: ' . mysql_error());
				}
			}
		
		// Update the source if changed
			if ($cl->source_db != $source_db)
			{
				$dict_id = $this->getDictionaryId($source_db);
				if (!$dict_id) {
					trigger_error('Source "' . $source_db . '" not found', E_USER_ERROR);
				}
				$sql_update = 
					'update mcl.concept_list set dict_id = ' . $dict_id . 
					' where concept_list_id = ' . $concept_list_id;
				if ($this->debug) {
					echo '<p>Updating source db for the concept list: <br />' .
						$sql_update . '</p>';
				}
				if (!mysql_query($sql_update, $this->getConnection())) {
					trigger_error('Cannot update source db for concept list ' . $concept_list_id . 
						': ' . mysql_error());
				}
			}

		// Delete concepts that were removed
			$sql_delete = 
				'delete from mcl.concept_list_map ' . 
				'where concept_list_id = ' . $concept_list_id . ' ' .
				'and concept_id not in (' . 
				$csv_concept_id . ')';
			if ($this->debug) {
				echo '<p>Deleting concepts that were removed from the list: <br />' .
					$sql_delete . '</p>';
			}
			if (!mysql_query($sql_delete, $this->getConnection())) {
				trigger_error('Unable to delete concepts from list ' . $concept_list_id . 
					': ' . mysql_error());
			}

		// Determine which concepts to add
			$arr_insert_concepts = array();
			foreach ($arr_update_concepts as $concept_id) {
				$concept_id = (int)$concept_id;
				if (!$cl->isMember($concept_id)) {
					$arr_insert_concepts[$concept_id] = $concept_id;
				}
			}

		// Insert concepts that are new
			if (count($arr_insert_concepts)) {
				$glue = '),(' . $concept_list_id . ',';
				$sql_insert_concepts = 
					'insert into mcl.concept_list_map (concept_list_id, concept_id) values ' . 
					'(' . $concept_list_id . ',' . implode($glue, $arr_insert_concepts) . ')';
				if ($this->debug) {
					echo '<p>Inserting concept ids into list ' . $concept_list_id . 
						':<br />' . $sql_insert_concepts . '</p>';
				}
				if (!mysql_query($sql_insert_concepts, $this->getConnection())) {
					trigger_error('Unable to insert concepts into list ' . $concept_list_id . 
						': ' . mysql_error());
				}
			}

		return true;
	}
}

?>