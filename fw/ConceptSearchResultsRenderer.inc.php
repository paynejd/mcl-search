<?php

require_once (MCL_ROOT . 'fw/Concept.inc.php');
require_once (MCL_ROOT . 'fw/ConceptCollection.inc.php');
require_once (MCL_ROOT . 'fw/ConceptList.inc.php');


/**
 * HTML Renderer for a ConceptSearchResults object.
 */
class ConceptSearchResultsRenderer
{
	/**
	 * DEV: Enable display of icons on the right side of display.
	 */
	public $show_icons         =  false;

	/**
	 * DEV: Enable display of checkboxes and toolbar.
	 */
	public $show_checkbox      =  false;

	/**
	 * DEV: Enable display of the relevancy rating for a term.
	 */
	public $show_relevancy     =  false;

	/**
	 * DEV: Enable sorting of terms by relevancy
	 */
	public $sort_by_relevancy  =  false;

	/**
	 * Array of default url parameters used by CSRR::getSearchUrl()
	 */
	public $arr_url_param = array();

	/**
	 * The ConceptSearchResults object containing the search results to display.
	 */
	public $csr = null;

	/**
	 * The ConceptCollection object containing the concepts to display.
	 */
	public $cc = null;

	// TODO: ********** Remove highlighting??
	/**
	 * Whether to highlight concepts that are part of $cl.
	 */
	public $highlight_list = true;

	// TODO: ********** Remove highlighting??
	/**
	 * The ConceptList object containing info about concepts to highlight.
	 */
	public $cl = null;

	/**
	 * Number of columns in the renderer (used to make updates easier). Set the default
	 * value here. The number is updated automatically based on other attributes.
	 */
	public $num_columns = 4;

	/**
	 * Number of Questions/Answers to display before minimizing. Set to zero to
	 * always show all.
	 */
	public $num_display_qa_results = 5;

	/**
	 * Number of concept set items to display before minimizing. Set to zero to 
	 * always show all.
	 */
	public $num_display_concept_set_results = 0;

	/**
	 * Maximum displayed length of the CSRG header text. 
	 */
	public $csrg_text_cutoff_length = 120;


	/**
	 * Constructor - requires a ConceptSearchResults object
	 */
	public function ConceptSearchResultsRenderer(ConceptSearchResults $csr)
	{
		$this->csr  =  $csr;
		$this->cc   =  $csr->cc;
	}

	/**
	 * Used to render the attached concept list. Set all attributes
	 * before calling the function. Private functions are called in this order:
	 *
	 * _header
	 * _group_header   |  repeated once per group
	 * _empty_group    |  called once per group if no concepts
	 * _start_concept  |  |  these are repeated once per concept
	 * _concept        |  |  
	 * _end_concept    |  |
	 * _group_footer   |
	 * _footer
	 */
	public function render()
	{
		// Set the number of columns
		if (  $this->show_checkbox  )  $this->num_columns++;
		if (  $this->show_icons     )  $this->num_columns++;

		// Display the header
		$this->_header();

		// Render each ConceptSearchResultsGroup
		$group_i  =  0     ;
		$c        =  null  ;
		$csrg     =  null  ;
		$arr_search_result_group_id = $this->cc->getSearchResultsGroupIds();
		foreach ($arr_search_result_group_id as $csrg_id)
		{
			// Render the group header
			$csrg  =  $this->cc->getSearchResultsGroup(  $csrg_id  );
			$this->_group_header(  $csrg  ,  $group_i  );

			// Skip to next group if no concepts
			if (  !$csrg->getVisibleCount()  )  
			{
				$this->_empty_group (  $csrg  ,  $group_i  );
				$this->_group_footer(  $csrg  ,  $group_i  );
				$group_i++;
				continue;
			}

			// Iterate through concepts
			// NOTE: Handled very differently if sorting is enabled
			$concept_i  =  0;
			$source_i   =  0;
			if ($this->sort_by_relevancy) 
			{
				// Get sorted concept array (from all dictionary sources)
				$arr_concept_sort  =  $csrg->getRelevancySortedConceptArray();

				// Loop once per concept
				foreach ($arr_concept_sort as $arr_concept)
				{
					$concept_id   =   $arr_concept[  'concept_id'  ];
					$dict_db      =   $arr_concept[  'dict_db'     ];
					$c            =   $csrg->getConcept(  $concept_id  ,  $dict_db  );
					$concept_i++;
					$this->_start_concept(  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
					$this->_concept      (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
					$this->_end_concept  (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
				}
			}
			else
			{
				// Iterate through each dictionary source in the group
				foreach (  $csrg->getDictionarySources() as $dict_key  ) 
				{
					$css_dict  =  $this->csr->cs->getAllSources()->getDictionary($dict_key);
					$this->_start_source(  $css_dict  ,  $source_i  );
		
					$arr_concept_id  =  $csrg->getVisibleConceptIds(  $css_dict  );
					foreach ($arr_concept_id as $_concept_id)
					{
						$c  =  $csrg->getConcept(  $_concept_id  ,  $css_dict  );
						$concept_i++;
						$this->_start_concept(  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
						$this->_concept      (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
						$this->_end_concept  (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
					}

					// End the source
					$this->_end_source(  $css_dict  ,  $source_i  );
				}
			}

			// End this group
			$this->_group_footer(  $csrg  ,  $group_i  );
			$group_i++;
		}

		// Display the footer
		$this->_footer();
	}

	/**
	 * Called once per group if no search results in the group.
	 */
	protected function _empty_group(ConceptSearchResultsGroup $csrg, $group_i)
	{
		echo '<tr class="row1"><td colspan="' . $this->num_columns . 
				'"><em>No results in this search group</em></td></tr>';
	}

	/**
	 * Returns a formatted url string with the specified parameters. 
	 * @param string $search_term
	 * @param array $arr_url_param
	 * @param bool $merge_with_default
	 */
	public function getSearchUrl($search_term, array $arr_url_param = null, $merge_with_default = true) 
	{
		$url = 'search.php?q=' . $search_term;
		if ($merge_with_default) $arr_url_param = array_merge($this->arr_url_param, $arr_url_param);
		if ($arr_url_param) {
			$param = '';
			foreach ($arr_url_param as $name => $value) {
				if ($param) $param .= '&amp;';
				$param .= urlencode($name) . '=' . urlencode($value);	
			}
		}
		if ($param) $url .= '&amp;' . $param;
		return $url;
	}

	/**
	 * Private function called once at the beginning of the rendering.
	 */
	protected function _header()
	{

		if ($this->show_checkbox) {
			echo <<<END
<div id="divSearchResultsToolbar">
	<form id="form_action" method="get" action="action.php" onsubmit="return false;">
		<label for="a_select_all">Select:</label>
			<a id="a_select_all" href="javascript:selectAllConcepts();">All</a>,
			<a id="a_deslect_all" href="javascript:deselectAllConcepts();">None</a>
			&nbsp;&nbsp;&nbsp;
		<label for="action">Action:</label>
			<select id="action">
				<option value="">[Select Action]</option>
				<option value="new">New List from Selected Concepts</option>
				<option value="add">Add Selected Concepts to List</option>
				<option value="remove">Remove Selected Concepts from List</option>
				<option>--</option>
				<option value="hide">Hide Selected Concepts</option>
				<option value="unhide">Unhide All Concepts</option>
			</select>
			<button id="button_action">Go</button>
			&nbsp;&nbsp;&nbsp;
		<label>Search Groups:</label>
			<a id="a_expand_all_search_groups" href="javascript:expandAllSearchGroups();">Expand All</a>,
			<a id="a_collapse_all_search_groups" href="javascript:collapseAllSearchGroups();">Collapse All</a>
	</form>
</div>
END;
		}
		echo '<table width="100%" id="tblResults" cellpadding="2" cellspacing="1">';
	}

	/**
	 * Private function called once at the beginning of each ConceptSearchResultsGroup
	 */
	protected function _group_header(ConceptSearchResultsGroup $csrg, $group_i)
	{
		// Determine whether group starts expanded or collapsed
		$group_num_results = $csrg->getCount();
		if ($this->cc->getSearchResultsGroupCount() == 1) {
			$tbody_style = 'display:table-row-group;';
			$toggle_image = 'images/box_minus.jpg';
		} else {
			$tbody_style = 'display:none;';
			$toggle_image = 'images/box_plus.jpg';
		}

		// Group header
		echo "\n<thead id=\"csrg_header_" . $group_i . "\">";
		echo "<tr class=\"row_group_header\">";
		echo '<th colspan="' . $this->num_columns . 
				'" onclick="javascript:toggleSearchGroup(' . $group_i . ')">';
		if ($this->show_checkbox) {
			echo '<input type="checkbox" id="csrg_checkbox_' . $group_i . 
				'" onclick="csrgCheckboxClick(event, this);"';
			if (!$csrg->getCount()) echo ' disabled="disabled"';
			echo ' />&nbsp;&nbsp;';
		}
		echo '<img id="img_group_toggle_' . $group_i . '" src="' . 
				$toggle_image . '" border="0" alt="Toggle" />&nbsp;&nbsp;';
		echo 'Search Group ' . ($group_i+1) . 
			':&nbsp;&nbsp;<span style="font-weight:normal;">' .
			substr($csrg->csg->query, 0, $this->csrg_text_cutoff_length);
		if (strlen($csrg->csg->query) > $this->csrg_text_cutoff_length) {
			echo '...';
		}
		echo '</span>&nbsp;&nbsp;';
		echo '<span style="font-weight:bold;float:right;">' . $group_num_results;
		echo ' result';
		if ($group_num_results > 1) echo 's';
		echo "</span></th></tr>";
		echo "</thead>\n";

		// Concept column headers
		echo "<tbody id=\"csrg_" . $group_i . "\" class=\"tbody_group\" style=\"" . $tbody_style . "\">\n";
		echo "<tr id=\"csrg_header_row_" . $group_i . "\" class=\"row_table_header\">";
		if ($this->show_checkbox) {
			echo '<th class="col_check"></th>';
		}
		echo "<th class=\"col_1\">Concept</th>";
		echo "<th class=\"col_2\">Definitions</th>";
		echo "<th class=\"col_3\">Concept Sets</th>";
		echo "<th class=\"col_4\">Mappings</th>";
		if ($this->show_icons) {
			echo '<th class="col_icons"></th>';
		}
		echo "</tr>\n";

	}

	/**
	 * Private function called once at the end of each ConceptSearchResultsGroup
	 */
	protected function _group_footer(ConceptSearchResultsGroup $csrg, $group_i)
	{
		echo "</tbody>\n";
	}

	protected function _start_source(ConceptSearchSource $css_dict, $source_i)
	{
		/*
		echo '<tr style="background-color: #999;color:White;font-weight:bold;">';
		echo '<td style="padding:3px 12px;" colspan="' . $this->num_columns . '">' . $css_dict->dict_db; 
		echo '</td></tr>';
		 */
	}

	protected function _end_source(ConceptSearchSource $css_dict, $source_i)
	{
		// do nothing
	}

	/**
	 * Called once before each concept is rendered.
	 */
	protected function _start_concept(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $i)
	{
		// set formatting for odd/even/highlight row
		$class_name  =  '';
		// TODO: ********** concept list highlighting??
		/* 
		if ($this->highlight_list && $this->cl && $this->cl->isMember($c->concept_id)) {
			$class_name = 'highlight';
		} else
		*/
		if ($i % 2) {
			$class_name  =  'row1';
		} else {
			$class_name  =  'row2';
		}
		echo "\n" . '<tr id="tr_concept|' . $group_i . '|' . $c->css_dict->dict_db . '|' . $c->concept_id . '" class="' . $class_name . '">';
	}

	/**
	 * Called once to render each concept.
	 */
	protected function _concept(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $concept_i)
	{
		if ($this->show_checkbox) {
			$this->_renderColumn_Checkbox(  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
		}

		$this->_renderColumn_Concept     (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
		$this->_renderColumn_Definitions (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
		$this->_renderColumn_ConceptSets (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
		$this->_renderColumn_Mappings    (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );

		if ($this->show_icons) {
			$this->_renderColumn_Icons   (  $csrg  ,  $c  ,  $group_i  ,  $concept_i  );
		}
	}


	/**
	 * Checkbox column
	 */
	protected function _renderColumn_Checkbox(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $concept_i)
	{
		$key = $group_i . '|' . $c->css_dict->dict_db . '|' . $c->concept_id;
		echo '<td class="col_check">';
		echo '<input type="checkbox" name="concept[' . $group_i . '][' . $c->css_dict->dict_db . '][' . $c->concept_id . ']" ' . 
				'id="concept_checkbox|' . $key . '" value="' . $key . '" onclick="updateSearchGroupCheckbox(' . $group_i . ');" />';
		echo '</td>';
	}

	protected function _renderColumn_Icons(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $concept_i)
	{
		$js_concept_key = $c->css_dict->dict_db . '|' . $c->concept_id;
		echo '<td class="col_icon">';

		// Comment
		echo '<a href="javascript:showComment(' . $js_concept_key . ');" title="Leave a Comment"><img src="images/comment_icon.gif"  /></a><br />';

		// Visualize
		echo '<a href="visualize.php?id=' . $js_concept_key . '" target="_new" title="Visualize Concept"><img src="images/visualize.gif" /></a><br />';

		echo '</td>';
	}

	/**
	 * Render the mappings column
	 */
	protected function _renderColumn_Mappings(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $concept_i)
	{
		echo "\n<td class=\"col_4\">\n\t";

		echo '<div class="content">';

		// Get the search terms
		$arr_term_type = array(
				MCL_SEARCH_TERM_TYPE_MAP_CODE,
			);
		$arr_search_term = $csrg->csg->getSearchTermCollection()->getSearchTerms($arr_term_type, null, true); 

		// Concept Mappings
		echo "<div style=\"margin-bottom:6px;\">";
		foreach ($c->getConceptMappingIds() as $_mapping_i)
		{
			// Setup display of mapping
			$url_mapcode    =   '';
			$target         =   '';
			$cm             =   $c->getConceptMapping($_mapping_i);
			$url_mapsource  =   'search.php?q=' . urlencode("source:'" . $cm->source_name . "'") . '&source=' . urlencode($c->css_dict->dict_db);

			// SNOMED
			if ($cm->source_name == 'SNOMED CT' || $cm->source_name == 'SNOMED NP') {
				$url_mapcode = 'http://nciterms.nci.nih.gov/ncitbrowser/ConceptReport.jsp?dictionary=' . 
						'SNOMED%20Clinical%20Terms' . 
						'&amp;type=all' . 
						'&amp;code=' . urlencode($cm->source_code);
				$target = '_blank';
			}

			// ICD-10-WHO 
			elseif (substr($cm->source_name, 0, 10) == 'ICD-10-WHO') {
				//$url_mapcode = 'http://www.icd10data.com/Search.aspx?codebook=AllCodes&search=' . urlencode($cm->source_code);
				$url_mapcode = 'http://apps.who.int/classifications/icd10/browse/2010/en#/' . urlencode($cm->source_code);
				$target = '_blank';
			} 

			// PIH
			elseif ($cm->source_name == 'PIH') {
				$url_mapcode = $this->getSearchUrl('id:' . $cm->source_code, array('source'=>'pih_concept_dict'));
			} 

			// AMPATH
			elseif ($cm->source_name == 'AMPATH') {
				$url_mapcode = $this->getSearchUrl('id:' . $cm->source_code, array('source'=>'ampath_concept_dict'));
			}

			// LOINC
			elseif ($cm->source_name == 'LOINC') {
				$url_mapcode = 'http://search.loinc.org/LOINC/regular/' . trim($cm->source_code) . '.html';
				$target = '_blank';
			}

			// Display the mapping
			echo '<strong><a href="' . $url_mapsource . '">' . htmlentities($cm->source_name) . '</a></strong>: ';
			
			// Display the source code
			if ($url_mapcode) {
				echo '<a href="' . $url_mapcode . '"';
				if ($target) echo ' target="' . $target . '"';
				echo '>';
			}
			$source_code = htmlentities($cm->source_code);
			foreach ($arr_search_term as $search_term) {
				$source_code = preg_replace('/\b(' . addslashes($search_term->needle) . ')/i', '<span class="h">$1</span>', $source_code);
			}
			echo $source_code;
			if ($url_mapcode) echo '</a>';
			echo "<br />";
		}
		echo "</div>";

		// Concept lists
		if ($c->hasConceptLists()) {
			echo "<div style=\"margin-bottom:6px;\">Member of: ";
			$i = 0;
			foreach ($c->getConceptListIds() as $_concept_list_id) 
			{
				if ($i) echo ', ';
				//$_list_name = $this->cc->getConceptListName($_concept_list_id);
				if ($css_list = $this->csr->cs->getAllSources()->getConceptList($_concept_list_id)) {
					$_list_name = $css_list->list_name;
				} else {
					$_list_name = $_concept_list_id;
				}
				$_url = "search.php?q=" . urlencode("list:'" . $_list_name . "'");
				echo '<a href="' . $_url . '">' . htmlentities($_list_name) . '</a>';
				$i++;
			}
			echo "</div>";
		}

		echo "</div>\n";
		echo "\n</td>\n";
	}

	/**
	 * Render the concept column
	 */
	protected function _renderColumn_Concept(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $i)
	{
		echo "\n<td class=\"col_1\">\n\t";
		
		// Concept ID, name, datatype/class
		echo '<table class="tblConceptHeader" cellpadding="0" cellspacing="1">' . "\n\t\t<tr>";

		// Concept ID
		$concept_key = $group_i . '|' . $c->css_dict->dict_db . '|' . $c->concept_id;
		echo '<td class="td_concept_id" nowrap="nowrap">';
		echo '<a id="concept_id|' . $concept_key . '" href="' . 
			$this->getSearchUrl('id:' . $c->concept_id, array('source'=>$c->css_dict->dict_db)) . 
			'">' . $c->concept_id . '</a>&nbsp;-&nbsp;</td>' . "\n\t\t";
		echo '<td class="td_concept_name">';
		$concept_name_class = 'concept_name';
		if ($c->retired) $concept_name_class .= ' spanRetired';

		// Concept name
		$concept_name = htmlentities($c->getPreferredName());
		$arr_term_type = array(
				MCL_SEARCH_TERM_TYPE_TEXT,
				MCL_SEARCH_TERM_TYPE_CONCEPT_NAME,
			);
		$arr_search_term = $csrg->csg->getSearchTermCollection()->getSearchTerms($arr_term_type, null, true); 
		foreach ($arr_search_term as $search_term) {
			$concept_name = preg_replace('/\b(' . addslashes($search_term->needle) . ')/i', '<span class="h">$1</span>', $concept_name);
		}
		echo "<span class=\"" . $concept_name_class . "\">" . $concept_name . '</span> ';

		// Language
		echo '<span class="language">[' . $c->getPreferredLocale() . ']</span>';

		// Class and datatype
		echo '<br /><span class="concept_class">' . $c->class_name . '</span> / ';
		echo '<span class="concept_datatype">' . $c->datatype_name . '</span>';
		echo '</td></tr></table>' . "\n";

		// Start the content div
		echo '<div class="content">';

		// Dictionary
		echo '<div style="margin-top:4px;"><em>Dictionary:</em> ';
		echo '<span class="span_concept_dictionary mcl_dict_color_' . $c->css_dict->dict_id . '">';
		echo $c->css_dict->dict_name . ' (' . $c->css_dict->dict_db . ')';
		echo '</span></div>';

		// Relevancy
		if ($this->show_relevancy) {
			echo '<p>Rating: ' . number_format($csrg->getRelevancy($c), 2) . '</p>';
		}

		// Concept synonyms
		if ($c->getNumberSynonyms() > 1) {
			echo '<div style="margin-top:4px;"><em>Synonyms:</em>'; 
			echo '<ul class="concept_synonyms">';
			foreach ($c->getConceptNameIds() as $_name_i) 
			{
				$cn  =  $c->getConceptName($_name_i);
				if ($c->getPreferredNameId() != $cn->concept_name_id) {
					$concept_name = htmlentities($cn->name);
					foreach ($arr_search_term as $search_term) {
						$concept_name = preg_replace('/\b(' . addslashes($search_term->needle) . ')/i', '<span class="h">$1</span>', $concept_name);
					}
					echo '<li>' . $concept_name . ' <em>[' . $cn->locale . ']</em></li>';
				}
			}
			echo '</ul></div>';
		}

		// Concept Attributes
		// Start the concept attributes table
		echo '<div style="cursor:pointer;padding-top:8px;" ' . 
			"onclick=\"javascript:toggleConceptDetailsPane('" . $concept_key . "');\">" .
			'<img id="img_toggle_' . $concept_key . '" src="images/box_plus.jpg" border="0" alt="Toggle" />&nbsp;&nbsp;' . 
			'<span style="color:black;">Concept Attributes</span></div>';
		echo '<table id="tbl_attr_' . $concept_key . '" class="tbl_numeric_range" cellspacing="0" style="display:none;">';

		// Numeric Range
		$range = null;
		if ($range = $c->getNumericRange()) {
			echo '<tr class="attr_header"><th>&nbsp;</th><th>Absolute</th><th>Critical</th><th>Normal</th></tr>';
			echo '<tr class="attr"><th>High</th><td>' . $range->absolute_high . '</td><td>' . 
				$range->critical_high . '</td><td>' . $range->normal_high . '</td></tr>';
			echo '<tr class="attr"><th>Low</th><td>' . $range->absolute_low . '</td><td>' . 
				$range->critical_low . '</td><td>' . $range->normal_low . '</td></tr>';
		}

		// Other attributes
		echo '<tr class="attr_header"><th>Attr</th><th colspan="3">Value</th></tr>';
		if ($range) {
			if ($range->units) {
				echo '<tr class="attr"><th>Units</th><td colspan="3">' . $range->units . '</td></tr>';
			}
			echo '<tr class="attr"><th>Precise</th><td colspan="3">';
			if (is_null($range->precise)) echo 'null';
			elseif (!$range->precise) echo 'No';
			else echo 'Yes';
			echo '</td></tr>';
		}
		if ($c->uuid) {		// concept uuid div - this is only displayed on mouseover of the concept name
			echo '<tr class="attr"><th>UUID</th><td colspan="3">' . 
				'<pre style="font-size:8pt;margin:0;padding:0;">' . 
				substr($c->uuid,0,16) . "\n" . substr($c->uuid,16,16) . 
				'</pre></td></tr>';
		}
		echo '<tr class="attr"><th>Is Set</th><td colspan="3">';
		if ($c->is_set) echo 'Yes'; else echo 'No';
		echo '</td></tr>';
		if ($c->hasAttributes()) {
			foreach ($c->getAttributesArray() as $attr_name => $attr_value) {
				if (!$attr_value) continue;
				echo '<tr class="attr"><th>' . $attr_name . '</th><td colspan="3">' . $attr_value . '</td></tr>';
			}
		}

		echo '</table>';

		// end the content div
		echo '</div>';

		echo '</td>';
	}

	/**
	 * Shrinks text to specified number of characters and appends ellipses. Otherwise,
	 * just returns the text.
	 */
	private function _shrinkText($text, $max_char = 70)
	{
		if (strlen($text) > $max_char) {
			$text = trim(substr($text, 0, $max_char)) . '...';
		}
		return $text;
	}

	/**
	 * Render the concept sets column
	 */
	protected function _renderColumn_ConceptSets(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $i)
	{
		$num_open_lists = 0;

		/*
		 * Show direct parents (not all ancestors), siblings, and children of the current concept.
		 */

		// Start column
			echo "\n<td class=\"col_3\">\n\t";
			echo "<div class=\"hierarchy\">\n";

		// If it has parents, loop through them
			if ($c->hasParents()) 
			{
				foreach ($c->getParentIds() as $_parent_id) 
				{
					// start list
						echo "<ul class=\"hierarchy_top\">\n";

					// render the parent
						$parent_c  =  $this->cc->getConcept($_parent_id, $c->css_dict);
						if (!$parent_c) continue;
						$_name = $parent_c->getPreferredName();
						$_display_name = $this->_shrinkText($_name);
						echo '<li class="concept_parent"><span class="concept_name"';
						if ($_name != $_display_name) echo ' title="' . htmlentities($_name) . '"';
						echo '>' . htmlentities($_display_name);
						echo '</span> (<a href="' . 
						$this->getSearchUrl('id:'.$parent_c->concept_id, array('source'=>$parent_c->css_dict->dict_db)) . 
						'">' . $_parent_id . "</a>)</li>\n";

					// Render current and its children
						echo "<li><ul class=\"hierarchy_not_top\">\n";
						$this->_renderConceptSetCurrent($c, true);

					// Show siblings of the current
						$_i = 0;
						$num_results = $parent_c->getNumberChildren();
						foreach ($parent_c->getChildrenIds() as $_sibling_id) 
						{
							// skip if same as the current concept
							if ($_sibling_id == $c->concept_id) continue;

							// Display
							$sibling_concept  =   $this->cc->getConcept($_sibling_id, $parent_c->css_dict);
							$_name            =   $sibling_concept->getPreferredName();
							$_display_name    =   $this->_shrinkText($_name);
							echo '<li class="concept_sibling"><span class="concept_name"';
							if ($_name != $_display_name) echo ' title="' . htmlentities($_name) . '"';
							echo '>' . htmlentities($_display_name);
							echo '</span> (<a href="' . 
								$this->getSearchUrl('id:'.$_sibling_id, array('source'=>$sibling_concept->css_dict->dict_db)) . 
								'">' . $_sibling_id . '</a>)<br />';
							echo '</li>';

							// Hide lengthy results
							$_i++;
							if ($_i ==  $this->num_display_concept_set_results) 
							{
								$ul_toggle_id      =  'ul_cs_toggle_' . $c->css_dict->dict_id . '_' . $_parent_id . '_' . $c->concept_id;
								$ul_more_id        =  'ul_cs_more_' . $c->css_dict->dict_id . '_' . $_parent_id . '_' . $c->concept_id;
								$num_more_results  =  $num_results - $this->num_display_concept_set_results - 1;
								echo "</ul>\n" . '<ul id="' . $ul_toggle_id . '" style="padding-left:24px;margin-top:6px;padding-top:0;list-style-type:none;">';
								echo '<li class="concept_sibling"><a href="javascript:toggleElementVisibility(\'' . $ul_toggle_id . '\');toggleElementVisibility(\'' . $ul_more_id . '\');">' . 
									'See ' . $num_more_results . ' more siblings...</a></li></ul>' . "\n";
								echo '<ul class="hierarchy_not_top" id="' . $ul_more_id . '" style="display:none;">';
							}
						}

					// End list
						echo "</ul></li></ul>\n";
				}
			}

		// If it does not have parents, then just show the current and its children
			elseif ($c->hasChildren()) {
				echo "<ul class=\"hierarchy_top\">\n";
				$this->_renderConceptSetCurrent($c, true);
				echo "</ul>\n";
			}

		// end column
			echo "</div></td>";
	}

	/**
	 * Used by _renderColumn_ConceptSets to render the current concept and its children.
	 */
	private function _renderConceptSetCurrent(Concept $c, $show_children = true)
	{
		// Show current
			if ($c->retired) $_class = ' concept_retired'; else $_class = '';
			$_name = $c->getPreferredName();
			$_display_name = $this->_shrinkText($_name);
			echo '<li class="concept_current"><span class="concept_name' . $_class . '"';
			if ($_name != $_display_name) echo ' title="' . htmlentities($_name) . '"';
			echo '>' . htmlentities($_display_name);
			echo '</span> (<a href="' . 
				$this->getSearchUrl('id:'.$c->concept_id, array('source'=>$c->css_dict->dict_db)) . 
				'">' . $c->concept_id . '</a>)</li>';

		// Show children
			if (  $show_children  &&  $c->hasChildren()  ) 
			{
				echo "<li><ul class=\"hierarchy_not_top\">\n";
				foreach ($c->getChildrenIds() as $_child_id) 
				{
					$child_concept  =  $this->cc->getConcept($_child_id, $c->css_dict);
					$_name = $child_concept->getPreferredName();
					$_display_name = $this->_shrinkText($_name);
					echo '<li class="concept_sibling"><span class="concept_name"';
					if ($_name != $_display_name) echo ' title="' . htmlentities($_name) . '"';
					echo '>' . htmlentities($_display_name);
					echo '</span> (<a href="' . 
							$this->getSearchUrl('id:'.$_child_id, array('source'=>$child_concept->css_dict->dict_db)) . 
							'">' . $_child_id . '</a>)</li>';
				}
				echo "</ul></li>\n";
			}
	}
	
	/**
	 * Render the definitions column
	 */
	protected function _renderColumn_Definitions(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $i)
	{
		echo "\n<td class=\"col_4\">\n\t";

		// Get the search terms		
		$arr_term_type = array(
				MCL_SEARCH_TERM_TYPE_TEXT,
				MCL_SEARCH_TERM_TYPE_CONCEPT_DESCRIPTION,
			);
		$arr_search_term = $csrg->csg->getSearchTermCollection()->getSearchTerms($arr_term_type, null, true); 

		// Definitions
		foreach ($c->getConceptDescriptionIds() as $_desc_i) 
		{
			$cd  =  $c->getConceptDescription($_desc_i);
			$desc = htmlentities($cd->description);
			foreach ($arr_search_term as $search_term) {
				$desc = preg_replace(
						'/\b(' . addslashes($search_term->needle) . ')/i', 
						'<span class="h">$1</span>', 
						$desc
					);
			}
			echo '<div class="concept_def"><span class="concept_def_header">[' . 
				$cd->locale . ']</span> ' . $desc . "</div>";
		}

		/**
		 * Answers - Only the first CSRR::num_display_qa_results answers are visible; set to zero to make 
		 * all visible by default.
		 */
		if ($c->hasAnswers()) {
			echo "<div style=\"margin-bottom:15px;\">This concept is a question with the following answer(s):\n";
			echo '<ul class="qa_set" style="margin-bottom:0;padding-bottom:0;">';
			$_i = 0;
			$num_results = $c->getNumberAnswers();
			foreach ($c->getAnswerIds() as $_answer_id) 
			{
				$ca  =  $this->cc->getConcept($_answer_id, $c->css_dict);
				echo '<li>' . htmlentities($ca->getPreferredName()) . 
					' (<a href="' . $this->getSearchUrl('id:'.$_answer_id, array('source'=>$c->css_dict->dict_db)) . 
					'">' . $_answer_id . '</a>)</li>';
				$_i++;
				if (($_i == $this->num_display_qa_results) && 
					($num_results > $this->num_display_qa_results)) 
				{
					$ul_toggle_id = 'ul_answer_toggle_' . $c->css_dict->dict_id . '_' . $group_i . '_' . $c->concept_id;
					$ul_more_id = 'ul_answer_more_' . $c->css_dict->dict_id . '_' . $group_i . '_' . $c->concept_id;
					$num_more_results = $num_results - $this->num_display_qa_results;
					echo "</ul>\n" . '<ul id="' . $ul_toggle_id . '" style="padding-left:24px;margin-top:6px;padding-top:0;list-style-type:none;">' . 
						'<li><a href="javascript:toggleElementVisibility(\'' . $ul_toggle_id . '\');toggleElementVisibility(\'' . $ul_more_id . '\');">' . 
						'See ' . $num_more_results . ' more...</a></li></ul>' . "\n";
					echo '<ul class="qa_set" id="' . $ul_more_id . '" style="display:none;margin-top:0;padding-top:0;margin-bottom:0;padding-bottom:0;">';
				}
			}
			echo '</ul></div>';
		}

		/**
		 * Questions - Only the first CSRR::num_display_qa_results questions are visible; 
		 * set to zero to make all visible by default.
		 */
		if ($c->hasQuestions()) {
			echo "<div style=\"margin-bottom:15px;\">This concept is an answer for the following question(s):\n";
			echo '<ul class="qa_set" style="margin-bottom:0;padding-bottom:0;">';
			$_i = 0;
			$num_results = $c->getNumberQuestions();
			foreach ($c->getQuestionIds() as $_question_id) 
			{
				$cq  =  $this->cc->getConcept($_question_id, $c->css_dict);
				echo '<li>' . htmlentities($cq->getPreferredName()) . 
					' (<a href="' . $this->getSearchUrl('id:'.$_question_id, array('source'=>$c->css_dict->dict_db)) . 
					'">' . $_question_id . '</a>)</li>';
				$_i++;
				if (($_i == $this->num_display_qa_results) && 
					($num_results > $this->num_display_qa_results)) 
				{
					echo "</ul>\n";
					$ul_toggle_id = 'ul_question_toggle_' . $c->css_dict->dict_id . '_' . $c->concept_id;
					$ul_more_id = 'ul_question_more_' . $c->css_dict->dict_id . '_' . $c->concept_id;
					$num_more_results = $num_results - $this->num_display_qa_results;
					echo '<ul id="' . $ul_toggle_id . '" style="padding-left:24px;margin-top:6px;padding-top:0;list-style-type:none;">' . 
						'<li><a href="javascript:toggleElementVisibility(\'' . $ul_toggle_id . '\');toggleElementVisibility(\'' . $ul_more_id . '\');">' . 
						'See ' . $num_more_results . ' more...</a></li></ul>' . "\n";
					echo '<ul class="qa_set" id="' . $ul_more_id . '" style="display:none;margin-top:0;padding-top:0;margin-bottom:0;padding-bottom:0;">';
				}
			}
			echo '</ul></div>';
		}

		// End of column 3
		echo '</td>';
	}


	protected function _end_concept(ConceptSearchResultsGroup $csrg, Concept $c, $group_i, $i) {
		echo "</tr>\n";
	}


	protected function _footer() {
		echo "</table>\n";
	}
}

?>