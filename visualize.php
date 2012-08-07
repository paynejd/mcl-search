<?php
/****************************************************************************************************
** visualize.php
**
** UNDER CONSTRUCTION
**
** Visually illustrate the relationships of the passed concept(s).
** --------------------------------------------------------------------------------------------------
** POST parameters:
**		id		Comma-separated list of concept IDs to visualize
*****************************************************************************************************/


$arr_param = array_merge($_GET, $_POST);

echo 'visualize ' . $arr_param['id'];

?>