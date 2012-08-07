<?php

require_once(MCL_ROOT . 'ConceptSearchSql.inc.php');
require_once(MCL_ROOT . 'Collection.inc.php');

/**
 * Corresponds to a CSRG - 
 */
class ConceptSearchSqlCollection extends Collection
{
	public $csrg  =  null;
	public $csg   =  null;
	
	public function __construct(ConceptSearchGroup $csg)
	{
		$this->csg  =  $csg;
	}
}

?>