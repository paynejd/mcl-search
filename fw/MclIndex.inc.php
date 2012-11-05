<?php

class MclIndex
{
	public static function buildIndex($cxn_mcl, $mcl_enhanced_db_name, $index_table_name, $index_mapsource_index_table_name)
	{
		// Create base index table
		mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);
		MclIndex::createBaseIndexTable($cxn_mcl, $index_table_name);

		// Load the map sources
		$cssf         =  new ConceptSearchSourceFactory();
		$cssf->debug  =  true;
		$coll_source  =  $cssf->loadEnhancedSourceDefinitions($cxn_mcl);

		// Get unique map source names across dictionaries
		$arr_map_source_name  =  array();
		foreach (  $coll_source->getMapSources() as $mapsource  )
		{
			$arr_map_source_name[  $mapsource->map_source_name  ]  =  $mapsource->map_source_name  ;
		}
		echo '<p><strong>Map sources:</strong><br />';
		print_r(  array_keys($arr_map_source_name)  );
		echo '</p>';

		// Create map source index table
		mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);
		MclIndex::createMapSourceIndexTable($cxn_mcl, $arr_map_source_name, $index_mapsource_index_table_name);

		// Populate base index table
		foreach (  $coll_source->getDictionaries() as $dict  )
		{
			MclIndex::populateIndexTable($cxn_mcl, $index_table_name, $dict);
		}

		// Populate map source index table for each dictionary
		foreach (  $coll_source->getDictionaries() as $dict  )
		{
			MclIndex::populateMapSourceIndexTable($cxn_mcl, $index_mapsource_index_table_name, $coll_source, $dict);
		}
	}

	public static function createBaseIndexTable($cxn_mcl, $index_table_name)
	{
		// Drop the base index table if exists
		$sql_drop  =  "DROP TABLE IF EXISTS `" . mysql_real_escape_string($index_table_name, $cxn_mcl) . "`";
		echo '<p><strong>Drop base index table (if exists):</strong><br />', $sql_drop, '</p>';
		if (  !mysql_query($sql_drop, $cxn_mcl)  ) 
		{
			trigger_error('Could not drop table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		// Create and populate base index table
		$sql_create  = 
			"CREATE TABLE `mcl_index` ( " .
				"`concept_dict_id` int(11), " .
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

	public static function populateIndexTable($cxn_mcl, $index_table_name, ConceptSearchSource $dict)
	{
		echo '<p><strong>Populating base index table for ' . $dict->dict_db . '...</strong><br />';

		// Build sql
		$sql_insert  =  MclIndex::getPopulateIndexTableSql($cxn_mcl, $index_table_name, $dict);
		if (  !mysql_query($sql_insert, $cxn_mcl)  ) 
		{
			trigger_error('Could not create table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		echo 'DONE!</p>';
	}

	public static function createMapSourceIndexTable($cxn_mcl, $arr_map_source_name, $index_mapsource_index_table_name)
	{
		// Drop the map source index table if exists
		$sql_drop  =  "DROP TABLE IF EXISTS `" . mysql_real_escape_string($index_mapsource_index_table_name, $cxn_mcl) . "`";
		echo '<p><strong>Drop map source index table (if exists):</strong><br />', $sql_drop, '</p>';
		if (  !mysql_query($sql_drop, $cxn_mcl)  ) 
		{
			trigger_error('Could not drop table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}

		// Create map source index table 
		$sql_create  =  MclIndex::getMapSourceTableSql($cxn_mcl, $arr_map_source_name, $index_mapsource_index_table_name);
		echo '<p><strong>Create map source index table:</strong><br />', $sql_create, '</p>';
		if (  !mysql_query($sql_create, $cxn_mcl)  ) 
		{
			trigger_error('Could not create table: ' . mysql_error($cxn_mcl), E_USER_ERROR);
		}
	}

	public static function getMapSourceTableSql($cxn_mcl, $arr_map_source_name, $index_mapsource_index_table_name)
	{
		$sql_create  =  "CREATE TABLE `" . mysql_real_escape_string($index_mapsource_index_table_name, $cxn_mcl) . "` (";
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

	public static function populateMapSourceIndexTable($cxn_mcl, $index_mapsource_index_table_name, 
			ConceptSearchSourceCollection $coll_source, ConceptSearchSource $dict)
	{
		// Load
	}

	public static function getPopulateIndexTableSql($cxn_mcl, $index_table_name, ConceptSearchSource $dict)
	{
		$sql_create  =  "INSERT INTO `" . mysql_real_escape_string($index_table_name, $cxn_mcl) . "` ";
		$sql_create .= 'select ' . $dict->dict_id . ', ';
		$sql_create .=  
<<<EOD
	c.concept_id as conceptID,
	c.date_created,
	cn.name as name,
	c.retired as retired,
	c.uuid as UUID,
	cc.name as class,
	cd.name datatype,
	cas.answers,
	csy.synonyms,
	cqs.questions,
	cdesc.description as description_en

from concept c

left join concept_class cc
	on c.class_id = cc.concept_class_id

join concept_name cn
	on c.concept_id = cn.concept_id

join
(
	select 
		c_s.concept_id, 
		group_concat(cn_s.name SEPARATOR ' ') as synonyms 
	from concept c_s
	left join concept_name cn_s
		on cn_s.concept_id = c_s.concept_id
	group by c_s.concept_id
) as csy
on c.concept_id=csy.concept_id

join concept_datatype cd
	on c.datatype_id = cd.concept_datatype_id

left join concept_description cdesc
	on c.concept_id = cdesc.concept_id

left join
(
	SELECT 
		c_s.concept_id,
		ca_s.answer_concept, 
		group_concat(
			CONCAT(cn_s.name,' '), 
			CAST(ca_s.answer_concept AS CHAR) SEPARATOR ' '
		) as answers
	FROM concept c_s
	join concept_answer ca_s
		on c_s.concept_id = ca_s.concept_id
	join concept_name cn_s
		on cn_s.concept_id = ca_s.answer_concept
	where 
		cn_s.locale='en' and 
		cn_s.concept_name_type='FULLY_SPECIFIED'
	group by c_s.concept_id
) as cas
on c.concept_id=cas.concept_id

left join
(
	SELECT 
		c.concept_id,
		ca.answer_concept, 
		group_concat(
			CONCAT(cn.name,' '), 
			CAST(ca.concept_id AS CHAR) SEPARATOR ' '
		) as questions
 	FROM concept c
	left join concept_answer ca
		on c.concept_id = ca.answer_concept
	left join concept_name cn
		on cn.concept_id = ca.concept_id
	where 
		cn.locale='en' and 
		cn.concept_name_type='FULLY_SPECIFIED'
	group by c.concept_id
) as cqs
on c.concept_id=cqs.concept_id

where  
	cn.locale='en' and 
	cn.concept_name_type='FULLY_SPECIFIED' 

order by c.concept_id
EOD;

		return $sql_create;
	}
}

?>