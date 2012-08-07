<?php
/**
* Clears the PHP session cache
*/

session_start();

if (isset($_SESSION['__concept_search__'])) {
	unset($_SESSION['__concept_search__']);
}

echo 'Session cache cleared...';

?>