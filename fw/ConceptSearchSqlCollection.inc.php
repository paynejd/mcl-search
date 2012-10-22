<?php

require_once(MCL_ROOT . 'fw/ConceptSearchSql.inc.php');
require_once(MCL_ROOT . 'fw/Collection.inc.php');

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