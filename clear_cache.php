<?php
/****************************************************************************************************
** clear_cache.php
**
** Clears the PHP session cache and nothing more.
*****************************************************************************************************/


session_start();

if (isset($_SESSION['__concept_search__'])) {
	unset($_SESSION['__concept_search__']);
}

?>
<html>
<body>
<p>Session cache cleared...</p>
<p>Return to <a href="search.php">MCL:Search</a></p>
</body>
</html>