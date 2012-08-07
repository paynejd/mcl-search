<?php

/*****************************************************************************************
** Common global functions used by the search tools.
*****************************************************************************************/

/**
 * Function used by the search interface to link between different search results.
 */
function getSearchUrl($search_term, $source = '', $include_retired = false, $use_local_db = false) {
	$url = 'search.php?q=' . $search_term;
	if ($use_local_db || (isset($_GET['db']) && $_GET['db'] == 'local')) {
		$url .= '&amp;db=local';
	}
	if ($source) {
		$url .= '&amp;source=' . $source;
	}
	if ($include_retired) {
		$url .= '&amp;retired=1';
	}
	return $url;
}


/**
 * Creates an HTML Select element using an array of data.
 */
function echoHtmlSelect($arr_data, $arr_attr, $value_column = '', $display_column = '', 
		$select_value = null, $add_blank_item = true, $add_blank_text = '') 
{
	// start select element and echo attributes
	echo '<select ';
	foreach ($arr_attr as $k=>$v) { echo $k . '="' . $v . '" '; }
	echo ">\n";
	
	// add blank item
	if ($add_blank_item) {
		echo "\t<option value=\"\">" . $add_blank_text . "</option>\n";
	}

	// echo select options
	foreach (array_keys($arr_data) as $id) {
		$current_value = $arr_data[$id][$value_column];
		echo "\t<option value=\"" . htmlspecialchars($current_value, ENT_QUOTES) . '"';
		if ($select_value == $current_value) { echo ' selected="selected"'; }
		echo '>';
		echo htmlspecialchars($arr_data[$id][$display_column], ENT_QUOTES) . "</option>\n";
	}
	
	// end the select element
	echo "</select>\n";
}


/**
 * Outputs array data as group of html checkboxes.
 */
function echoHtmlChecklist($arr_data, $group_id, 
		$value_column, $display_column, $checked_column = '', 
		$name_column = '', $hint_column = '',
		$start = null, $stop = null) 
{
	// set start/stop
	$c = count($arr_data);
	if (!$start || $start < 0) $start = 0;
	if (!$stop || $stop < $start || $stop >= $c) $stop = $c - 1;
	
	// echo select options
	reset($arr_data);
	$_i = 0;				// number of current position in array
	$_k = key($arr_data);	// key of current position in array
	while ($_i <= $stop) 
	{
		if ($_i >= $start) {
			$id_root = $group_id . '_' . $_i;	// prefixed by chk, span, etc.

			// value of the checkbox
			$value    =  $arr_data[  $_k  ][  $value_column    ];
			
			// text that is shown
			$display  =  $arr_data[  $_k  ][  $display_column  ];
			
			// whether checkbox should be checked
			if (isset($arr_data[$_k][$checked_column])) {
				$is_checked = $arr_data[$_k][$checked_column];
			} else {
				$is_checked = false;
			}
			
			// name of the element
			if (isset($arr_data[$_k][$name_column])) {				
				$name = $group_id . '[' . $arr_data[$_k][$name_column] . ']'; 
			} else {
				$name = $group_id . '[]';
			}

			// hint element
			if (isset($arr_data[$_k][$hint_column])) {
				$title = htmlentities($arr_data[$_k][$hint_column]);
			} else {
				$title = '';
			}

			// Output the checkbox
			echo "\t<span id=\"span_$id_root\">";
			echo '<input type="checkbox" id="chk_' . $id_root . 
					'" name="' . $name . '" value="' . $value . '"';
			if ($is_checked) { echo ' checked="checked"'; }
			echo ' />&nbsp;<label for="chk_' . $id_root . '"';
			if ($title) { echo ' title="' . $title .'"'; } 
			echo '>' .
					$display . "</label></span><br />\n";
		}
		
		next($arr_data);
		$_k = key($arr_data);
		$_i++;
	}
}


?>