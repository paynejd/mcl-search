<?php

class MclIndex
{
	public static function buildIndex($cxn_mcl, $mcl_enhanced_db_name, $index_table_name, $mapsource_index_table_name)
	{
		// Set full table names
		$mcl_escaped_db_name  =
				'`' . mysql_real_escape_string($mcl_enhanced_db_name, $cxn_mcl) . '`';
		$index_db_table_name  =  
				$mcl_escaped_db_name . '.`' . 
				mysql_real_escape_string($index_table_name, $cxn_mcl) . '`';
		$mapsource_db_table_name  =
				$mcl_escaped_db_name . '.`' . 
				mysql_real_escape_string($mapsource_index_table_name, $cxn_mcl) . '`';

		// Load the map sources
		mysql_select_db($mcl_escaped_db_name, $cxn_mcl);
		$cssf         =  new ConceptSearchSourceFactory();
		$cssf->debug  =  true;
		$coll_source  =  $cssf->loadEnhancedSourceDefinitions($cxn_mcl);

		// Get unique map source names across dictionaries
		$arr_map_source_name  =  array();
		foreach (  $coll_source->getMapSources() as $mapsource  )  {
			$arr_map_source_name[  $mapsource->map_source_name  ]  =  $mapsource->map_source_name  ;
		}
		echo '<p><strong>Map sources:</strong><br />';
		echo print_r(  array_keys($arr_map_source_name)  );
		echo '</p>';

		// Create base index table
		mysql_select_db($mcl_escaped_db_name, $cxn_mcl);
		MclIndex::createBaseIndexTable($cxn_mcl, $index_db_table_name);

		// Create map source index table
		MclIndex::createMapSourceIndexTable($cxn_mcl, $arr_map_source_name, $mapsource_db_table_name);

		// Populate base index table
		foreach (  $coll_source->getDictionaries() as $css_dict  )  {
			MclIndex::populateIndexTable($cxn_mcl, $index_db_table_name, $css_dict);
		}

		// Populate map source index table for each dictionary
		foreach (  $coll_source->getDictionaries() as $css_dict  )  {
			MclIndex::populateMapSourceIndexTable($cxn_mcl, $mapsource_db_table_name, $coll_source, $css_dict);
		}
	}

	public static function createBaseIndexTable($cxn_mcl, $index_db_table_name)
	{
		// Drop the base index table if exists
		$sql_drop  =  "DROP TABLE IF EXISTS " . $index_db_table_name;
		echo '<p><strong>Drop base index table (if exists):</strong><br />', $sql_drop, '</p>';
		if (  !mysql_query($sql_drop, $cxn_mcl)  ) 
		{
			trigger_error('Could not drop table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		// Create and populate base index table
		$sql_create  = 
			"CREATE TABLE " . $index_db_table_name . " ( " .
				"`dict_id` int(11), " .
				"`conceptID` int(11) NOT NULL DEFAULT '0', " .
				"`date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', " .
				"`name` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '', " .
				"`retired` tinyint(1) NOT NULL DEFAULT '0', " .
				"`UUID` char(38) CHARACTER SET utf8 NOT NULL, " .
				"`class` varchar(255) CHARACTER SET utf8 DEFAULT '', " .
				"`datatype` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '', " .
				"`answers` text CHARACTER SET utf8, " .
				"`synonyms` text CHARACTER SET utf8, " .
				"`questions` text CHARACTER SET utf8, " .
				"`description_en` text CHARACTER SET utf8 " .
				") ENGINE=InnoDB DEFAULT CHARSET=latin1";
		echo '<p><strong>Create base index table:</strong><br />', $sql_create, '</p>';
		if (  !mysql_query($sql_create, $cxn_mcl)  ) 
		{
			trigger_error('Could not create table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
	}

	public static function populateIndexTable($cxn_mcl, $index_db_table_name, ConceptSearchSource $css_dict)
	{
		echo '<p><strong>Populating base index table for ' . $css_dict->dict_db . '...</strong><br />';

		// Build sql
		$sql_insert  =  MclIndex::getPopulateIndexTableSql($cxn_mcl, $index_db_table_name, $css_dict);
		mysql_select_db($css_dict->dict_db, $cxn_mcl);
		if (  !mysql_query($sql_insert, $cxn_mcl)  ) 
		{
			trigger_error('Could not populate index table for ' . $css_dict->dict_db . ': ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		echo 'DONE!</p>';
	}

	public static function createMapSourceIndexTable($cxn_mcl, $arr_map_source_name, $mapsource_db_table_name)
	{
		// Drop the map source index table if exists
		$sql_drop  =  "DROP TABLE IF EXISTS " . $mapsource_db_table_name;
		echo '<p><strong>Drop map source index table (if exists):</strong><br />', $sql_drop, '</p>';
		if (  !mysql_query($sql_drop, $cxn_mcl)  ) 
		{
			trigger_error('Could not drop table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		// Create map source index table 
		$sql_create  =  MclIndex::getMapSourceTableSql($cxn_mcl, $arr_map_source_name, $mapsource_db_table_name);
		echo '<p><strong>Create map source index table:</strong><br />', $sql_create, '</p>';
		if (  !mysql_query($sql_create, $cxn_mcl)  ) 
		{
			trigger_error('Could not create table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
	}

	public static function getMapSourceTableSql($cxn_mcl, $arr_map_source_name, $mapsource_db_table_name)
	{
		$sql_create  =  
				"CREATE TABLE " . $mapsource_db_table_name . " (" . 
				'dict_id int(11), concept_id int(11), ';
		$i = 0;
		foreach (  $arr_map_source_name as $mapsource_name  )
		{
			if (  $i  ) $sql_create  .=  ', ';
			$sql_create  .=  "`" . mysql_real_escape_string($mapsource_name, $cxn_mcl) . "` VARCHAR(255)";
			$i++;
		}
		$sql_create  .=  ')';
		return $sql_create;
	}

	public static function populateMapSourceIndexTable($cxn_mcl, $mapsource_db_table_name, 
			ConceptSearchSourceCollection $coll_source, ConceptSearchSource $css_dict)
	{
		// Open a new link to the passed dictionary
		if (  !(  $cxn_mapcode = $css_dict->getConnection(true)  )  )  {
			trigger_error('Could not connect to "' . $css_dict->dict_db . '"', E_USER_ERROR);
		}

		// Load the map code table from the passed dictionary (version dependent) sorted by concept id
		if ($css_dict->version == MCL_OMRS_VERSION_1_6) 
		{
			$sql_mapcode =
				'select cm.concept_id, cm.source as concept_source_id, cm.source_code as map_code ' .
				'from openmrs.concept_map cm ' .
				'order by cm.concept_id';
		}
		elseif ($css_dict->version == MCL_OMRS_VERSION_1_9) 
		{
			$sql_mapcode =
				'select crm.concept_id, crt.concept_source_id, crt.code as map_code ' .
				'from concept_reference_map crm ' .
				'left join concept_reference_term crt on crt.concept_reference_term_id = crm.concept_reference_term_id ' .
				'order by crm.concept_id';
		}
		else
		{
			trigger_error('Invalid concept dictionary version number in table "concept_dict"--must be 1.6 or 1.9', E_USER_ERROR);
		}
		$rsc_mapcode = mysql_query($sql_mapcode, $cxn_mapcode);
		echo '<p><strong>Loading mapcodes from ' . $css_dict->dict_db . ':</strong><br />' . $sql_mapcode . '</p>';
		if (!$rsc_mapcode) {
			trigger_error('Could not query mapcodes for ' . $css_dict->dict_db . ': ' . mysql_error($cxn_mapcode), E_USER_ERROR);
		}

		/*
		 * Iterate through the returned map codes - 
		 * Map codes are sorted by concept ID. The loop iterates through map codes, 
		 * building an array of map codes for the same concept ID. When a new concep ID 
		 * is retrieved, all the map codes in the array (which belong to the same concept)
		 * are inserted into the database, and then the array is cleared.
		 */
		$current_concept_id  =  null     ;
		$old_concept_id      =  null     ;
		$arr_mapcode         =  array()  ;
		$i_concept           =  0        ;
		$i_mapcode           =  0        ;
		while ($row = mysql_fetch_assoc($rsc_mapcode))
		{
			// Get the new concept ID
			$current_concept_id = $row['concept_id'];

			// If new concept ID is different than previous concept ID, then insert mapcode array into database
			if ($current_concept_id != $old_concept_id && $arr_mapcode)
			{
				// Insert contents of map codes array into table
				$sql_insert = MclIndex::getMapCodeInsertSql($cxn_mcl, $mapsource_db_table_name, 
						$coll_source, $css_dict, $old_concept_id, $arr_mapcode);
				if (  !mysql_query($sql_insert, $cxn_mcl)  )
				{
					var_dump($sql_insert);
					trigger_error('Could not insert map code into index table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
				}
				$i_concept++;
				$arr_mapcode = array();
			}

			// Add current map code to map code array
			$arr_mapcode[  $row['concept_source_id']  ][]  =  $row['map_code']  ;
			$i_mapcode++;

			// Next
			$old_concept_id  =  $current_concept_id;
		}

		// Insert any map codes that are still in the array
		if ($arr_mapcode)
		{
			$sql_insert = MclIndex::getMapCodeInsertSql($cxn_mcl, $mapsource_db_table_name, 
					$coll_source, $css_dict, $old_concept_id, $arr_mapcode);
			if (  !mysql_query($sql_insert, $cxn_mcl)  )
			{
				var_dump($sql_insert);
				trigger_error('Could not insert map code into index table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
			}
			$i_concept++;
			$arr_mapcode = array();
		}

		echo '<p>' . $i_mapcode . ' mapcodes inserted for ' . $i_concept . ' concepts in "' . $css_dict->dict_db . '"</p>';

		// Close the extra connection
		mysql_close($cxn_mapcode);
	}

	public static function getMapCodeInsertSql($cxn_mcl, $mapsource_db_table_name, 
			ConceptSearchSourceCollection $coll_source, ConceptSearchSource $css_dict, 
			$concept_id, $arr_mapcode)
	{
		if (!$arr_mapcode)  return '';

		// Set the mapcode columns and values
		$str_mapcode_columns  =  'dict_id, concept_id';
		$str_mapcode_values   =  $css_dict->dict_id . ', ' . $concept_id;
		foreach ($arr_mapcode as $map_source_id => $arr_code)
		{
			// Map Source ID - add to the columns string
			$css_mapsource  =  $coll_source->getMapSource($map_source_id, $css_dict);
			if ($css_mapsource) {
				$str_mapcode_columns  .=  ', `' . mysql_real_escape_string($css_mapsource->map_source_name, $cxn_mcl) . '`';
			} else {
				var_dump($map_source_id);
				var_dump($arr_code);
				var_dump($css_dict);
				trigger_error('something crazy happened!', E_USER_ERROR);
			}

			// Map - add to the values string
			$str_code = implode(' ', $arr_code);
			$str_mapcode_values  .=  ", '" . mysql_real_escape_string($str_code, $cxn_mcl) . "'";
		}

		// Put it all together in an sql statement
		$sql_insert  =
				'insert into ' . $mapsource_db_table_name . 
				'(' . $str_mapcode_columns . 
				') values (' . $str_mapcode_values . ')';

		return $sql_insert;
	}

	public static function getPopulateIndexTableSql($cxn_mcl, $index_db_table_name, ConceptSearchSource $css_dict)
	{
		$db = '`' . mysql_real_escape_string($css_dict->dict_db, $cxn_mcl) . '`';
		$sql_create  =  
			"INSERT INTO " . $index_db_table_name . " ";
		$sql_create .= 
			"SELECT " . $css_dict->dict_id . ", ";
		$sql_create .= 
<<<EOD
	c.concept_id AS conceptID, 
	c.date_created, 
	cn.name AS name, 
	c.retired AS retired, 
	c.uuid AS UUID, 
	cc.name AS class,
	cd.name AS datatype,
	cas.answers,
	csy.synonyms,
	cqs.questions,
	cdesc.description AS description_en

FROM concept c 

LEFT JOIN concept_class cc 
	ON c.class_id = cc.concept_class_id 

JOIN concept_name cn
	ON c.concept_id = cn.concept_id

JOIN
(
	SELECT 
		c_s.concept_id, 
		GROUP_CONCAT(cn_s.name SEPARATOR ' ') AS synonyms 
	FROM concept c_s
	LEFT JOIN concept_name cn_s
		ON cn_s.concept_id = c_s.concept_id
	GROUP BY c_s.concept_id
) AS csy
ON c.concept_id=csy.concept_id

JOIN concept_datatype cd
	ON c.datatype_id = cd.concept_datatype_id

LEFT JOIN concept_description cdesc
	ON c.concept_id = cdesc.concept_id

LEFT JOIN
(
	SELECT 
		c_s.concept_id,
		ca_s.answer_concept, 
		GROUP_CONCAT(
			CONCAT(cn_s.name,' '), 
			CAST(ca_s.answer_concept AS CHAR) SEPARATOR ' '
		) AS answers
	FROM concept c_s
	JOIN concept_answer ca_s
		ON c_s.concept_id = ca_s.concept_id
	JOIN concept_name cn_s
		ON cn_s.concept_id = ca_s.answer_concept
	WHERE 
		cn_s.locale='en' AND
		cn_s.concept_name_type='FULLY_SPECIFIED'
	GROUP BY c_s.concept_id
) AS cas
ON c.concept_id=cas.concept_id

LEFT JOIN
(
	SELECT 
		c.concept_id,
		ca.answer_concept, 
		GROUP_CONCAT(
			CONCAT(cn.name,' '), 
			CAST(ca.concept_id AS CHAR) SEPARATOR ' '
		) AS questions
 	FROM concept c
	LEFT JOIN concept_answer ca
		ON c.concept_id = ca.answer_concept
	LEFT JOIN concept_name cn
		ON cn.concept_id = ca.concept_id
	WHERE 
		cn.locale='en' AND
		cn.concept_name_type='FULLY_SPECIFIED'
	GROUP BY c.concept_id
) AS cqs
ON c.concept_id=cqs.concept_id

WHERE
	cn.locale = 'en' AND
	cn.concept_name_type = 'FULLY_SPECIFIED' 

ORDER BY c.concept_id
EOD;

		return $sql_create;
	}
}

?>