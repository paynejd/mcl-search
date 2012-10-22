<?php

/**
 * One created for each connection required for a ConceptSearchSqlCollection
 */
class ConceptSearchSql
{
	public $cs = null;
	public $cstc = null;
	public $source =  null;
	public $css_sub_list_dict = null;
	public $sql =  '';

	public function __construct($cs, $cstc, $source, $css_sub_list_dict = null)
	{
		$this->cs                 =  $cs                 ;
		$this->cstc               =  $cstc               ;
		$this->source             =  $source             ;
		$this->css_sub_list_dict  =  $css_sub_list_dict  ;
	}

	public function getDictionarySource()
	{
		$css_dict = null;
		if ($this->source->type == MCL_SOURCE_TYPE_DICTIONARY || 
			$this->source->type == MCL_SOURCE_TYPE_MAP) 
		{
			$css_dict = $this->source;
		} 
		elseif ($this->source->type == MCL_SOURCE_TYPE_LIST) 
		{
			$css_dict = $this->css_sub_list_dict;
		}
		return $css_dict;
	}
}

?>