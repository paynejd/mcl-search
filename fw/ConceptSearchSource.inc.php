<?php

/****************************************************************************
 * class ConceptSearchSource
 * 
 * Defines a concept source, which can be an entire dictionary, a 
 * subset of a dictionary defined by a map source, or a concept list (which
 * can have concepts in multiple dictionaries). The parse function will
 * take a properly formatted string and break it into the correct parts
 * according to this regex format:
 *
 * 		*|<dictionary_name>[:map(<map_source_id>)]|list(<list_id>)
 *
 ***************************************************************************/
class ConceptSearchSource 
{
	/**
	 * One of 4 pre-defined constants: 
	 * 	 MCL_SOURCE_TYPE_DICTIONARY  -  concept dictionary  
	 *	 MCL_SOURCE_TYPE_LIST        -  concept list
	 * 	 MCL_SOURCE_TYPE_MAP         -  map source of a concept dictionary
	 *   MCL_SOURCE_SEARCH_ALL       -  searches all concept dictionaries
	 */
	public $type = null; 

	/**
	 * Numeric dictionary id from the concept_dict in the MCL db schema.
	 * @type int
	 */
	public $dict_id = null;

	/**
	 * Database schema name.
	 * @type string
	 */
	public $dict_db = null;

	/**
	 * Display name of the dictionary source.
	 * @type string
	 */
	public $dict_name = null;
	
	/**
	 * Whether dictionary uses fulltext index for concept name and description tables.
	 * @type bool
	 */
	public $dict_fulltext_mode = null;
	 
	/**
	 * Last updated date of the dictionary.
	 * @type string
	 */
	public $dict_last_updated = null;

	/**
	 * OMRS concept dictionary version. Only applicable if source type is dictionary.
	 * @type string
	 */
	public $version = null;

	/**
	 * Map source numeric ID.
	 * @type int
	 */
	public $map_source_id = null;
	
	/**
	 * Map source name.
	 * @type string
	 */
	public $map_source_name = null;
	
	/**
	 * Concept list numeric ID.
	 * @type int
	 */
	public $list_id = null;
	
	/**
	 * Concept list name.
	 * @type string
	 */
	public $list_name = null;

	/**
	 * Database connection variables
	 */
	private $db_host = null;
	private $db_uid  = null;
	private $db_pwd  = null;

	/**
	 * Database connection. Automatically created on calls to getConnection.
	 */
	private $conn = null;

	/**
	 * Collection of ConceptSearchSource objects of type MCL_SOURCE_TYPE_DICTIONARY
	 * that are sources of a ConceptSearchSource object of type MCL_SOURCE_TYPE_LIST
	 * or MCL_SOURCE_TYPE_MAP.
	 * @type ConceptSearchSourceCollection
	 */
	private $coll_dict_source = null;


	public function __construct($source_text = null) 
	{
		$this->coll_dict_source = new ConceptSearchSourceCollection();
		if ($source_text) $this->parse($source_text);
	}

	/**
	 * Set connection parameters
	 * @param string $db_host
	 * @param string $db_uid
	 * @param string $db_pwd
	 */
	public function setConnectionParameters($db_host, $db_uid, $db_pwd)
	{
		$this->db_host	=	$db_host	;
		$this->db_uid   =	$db_uid		;
		$this->db_pwd   =	$db_pwd		;
	}

	/**
	 * Get the database connection for type = MCL_SOURCE_TYPE_DICTIONARY
	 * @return connection resource
	 */
	public function getConnection($new_link = false)
	{
		// DICTIONARY: Get the connection from this object
		if ($this->type == MCL_SOURCE_TYPE_DICTIONARY)  
		{
			if ($this->conn && !$new_link) {
				mysql_select_db($this->dict_db);
				return $this->conn;
			} elseif ($this->db_host && $this->db_uid) {
				$this->conn = mysql_connect($this->db_host, $this->db_uid, $this->db_pwd, $new_link);
				mysql_select_db($this->dict_db);
				return $this->conn;
			} else {
				trigger_error('Database connection information not set.', E_USER_ERROR);
			}
		}
		
		// MAP: Get the connection from the linked DICTIONARY object
		elseif ($this->type == MCL_SOURCE_TYPE_MAP)
		{
			$arr_css_dict = $this->coll_dict_source->getAllSources();
			if (count($arr_css_dict) == 1) {
				reset($arr_css_dict);
				$css_dict = current($arr_css_dict);
				return $css_dict->getConnection($new_link);
			} else {
				trigger_error('Dictionary source for this map source object not set', E_USER_ERROR);
			}
		}
		
		// Throw an error
		else
		{
			trigger_error('getConnection is only valid for objects of type MCL_SOURCE_TYPE_DICTIONARY or MCL_SOURCE_TYPE_MAP', E_USER_ERROR);
		}
	}

	/**
	 * Parse a source text.
	 * @param string $source_text
	 */
	public function parse($source_text) 
	{
		if ($source_text == '*') 
		{
			$this->setSourceAllDictionaries();
		}
		elseif (preg_match('/^(.+):map\((\d+)\)/', $source_text, $matches)) 
		{
			$this->setSourceMap(null, $matches[1], null, $matches[2], null);
		} 
		elseif (preg_match('/^list\((\d+)\)/', $source_text, $matches)) 
		{
			$this->setSourceList($matches[1], null);
		} 
		else 
		{
			// TODO: Validate the source text
			$this->setSourceDictionary(null, $source_text, null, null, null);
		}
	}
	public function setSourceAllDictionaries()
	{
		$this->type                =  MCL_SOURCE_SEARCH_ALL       ;
	}
	public function setSourceList($list_id, $list_name)
	{
		$this->type                =  MCL_SOURCE_TYPE_LIST        ;
		$this->list_id             =  $list_id                    ;
		$this->list_name           =  $list_name                  ;
	}
	public function setSourceDictionary($dict_id, $dict_db, $dict_name, 
			$dict_fulltext_mode, $dict_last_updated, $version)
	{
		$this->type                =  MCL_SOURCE_TYPE_DICTIONARY  ;
		$this->dict_id             =  $dict_id                    ;
		$this->dict_db             =  $dict_db                    ;
		$this->dict_name           =  $dict_name                  ;
		$this->dict_fulltext_mode  =  $dict_fulltext_mode         ;
		$this->dict_last_updated   =  $dict_last_updated          ;
		$this->version             =  $version                    ;
	}
	public function setSourceMap($dict_id, $dict_db, $dict_name, 
		$map_source_id, $map_source_name)
	{
		$this->type                =  MCL_SOURCE_TYPE_MAP         ;
		$this->dict_id             =  $dict_id                    ;
		$this->dict_db             =  $dict_db                    ;
		$this->dict_name           =  $dict_name                  ;
		$this->map_source_id       =  $map_source_id              ;
		$this->map_source_name     =  $map_source_name            ;
	}

	/**
	 * Get collection of ConceptSearchSource objects of type MCL_SOURCE_TYPE_DICTIONARY that are sources
	 * for this object of type MCL_SOURCE_TYPE_MAP or MCL_SOURCE_TYPE_LIST.
	 * @return ConceptSearchSourceCollection
	 */
	public function getDictionarySources()
	{
		return $this->coll_dict_source;
	}

	/**
	 * Add a dictionary source for a ConceptSearchSource of type MCL_SOURCE_TYPE_LIST or MCL_SOURCE_TYPE_MAP.
	 * @param ConceptSearchSource $css
	 */
	public function addDictionarySource(ConceptSearchSource $css)
	{
		if ($this->type != MCL_SOURCE_TYPE_LIST && $this->type != MCL_SOURCE_TYPE_MAP) {
			trigger_error('ConceptSearchSource::addListDictionarySource can only be used if type is MCL_SOURCE_TYPE_LIST', E_USER_ERROR);
		} elseif ($css->type != MCL_SOURCE_TYPE_DICTIONARY) {
			trigger_error('Parameter $css must be of type MCL_SOURCE_TYPE_DICTIONARY', E_USER_ERROR);
		}
		$this->coll_dict_source->add($css);
	}

	public function toString()
	{
		$s = '';
		if (      $this->type  ==  MCL_SOURCE_SEARCH_ALL       ) 
		{
			$s .= '* [All Dictionaries]';
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_DICTIONARY  ) 
		{
			$s .= $this->dict_name . ' [' . $this->dict_id . ']';
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_MAP         ) 
		{
			$s .= $this->dict_name . ' [' . $this->dict_id . ']:map(' . $this->map_source_name . ' [' . $this->map_source_id .'])';
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_LIST        ) 
		{
			$s .= 'list(' . $this->list_name . ' [' . $this->list_id . '])';
		}
		return $s;
	}

	public function getKey()
	{
		if (      $this->type  ==  MCL_SOURCE_SEARCH_ALL       ) 
		{
			return '*';
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_DICTIONARY  ) 
		{
			return $this->dict_db;
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_MAP         ) 
		{
			return $this->dict_db . ':map(' . $this->map_source_id . ')';
		} 
		elseif (  $this->type  ==  MCL_SOURCE_TYPE_LIST        ) 
		{
			return 'list(' . $this->list_id . ')';
		}
		return '';
	}
}

?>