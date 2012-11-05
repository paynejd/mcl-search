<?php
/****************************************************************************************************
** search.php
**
** The primary entry point into searching the dictionary.
** --------------------------------------------------------------------------------------------------
** url parameters:
**		q					search query
**		source				comma-separated list of concept sources for the search. 
**								format: <dictionary_name>[:map(<map_source_id>)]|list(<list_id>)
**		retired				whether to include retired concepts in the search results
**		debug				whether to display debug information
**		verbose				whether to display verbose information
**		class				comma-separated list of ids of concept classes to filter
**		datatype			comma-separated list of ids of concept datatypes to filter
**      sort                [devl] use relevancy sort if true 
**		h					[devl] list of concepts to highlight
**		use_cache			[devl] prevent loading information from cache
*****************************************************************************************************/


set_time_limit(0);
error_reporting(-1);
ini_set('display_errors',1);
ini_set("memory_limit","128M");

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/search_common.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearch.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchResultsRenderer.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchSourceFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptDatatypeCollection.inc.php');
require_once(MCL_ROOT . 'fw/ConceptClassCollection.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');

session_start();


// Get the user (if logged in)
	$user = null;
	if (MclUser::isLoggedIn()) {
		$user = MclUser::getLoggedInUser();
	}

// Apply default page parameters and merge POST and GET page parameters
	$arr_param = array_merge($arr_search_attr_default, $_POST, $_GET);
	$full_search_query = $arr_param['q'];

// Connect to MCL db
	if (!($cxn_mcl = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd))) {
		die('Could not connect to database: ' . mysql_error($cxn_mcl));
 	}

// Set the database - MCL for enhanced mode, default concept dictionary for OMRS only mode
	if ($mcl_mode == MCL_MODE_ENHANCED) {
		mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);
	} elseif ($mcl_mode == MCL_MODE_OPENMRS_ONLY) {
		mysql_select_db($mcl_default_concept_dict_db, $cxn_mcl);
	}


/****************************************************************************
 *	Load source definitions - dictionaries, map sources, concept lists
 ***************************************************************************/
 
// Create ConceptSearchSourceFactory
	$cssf          =  new ConceptSearchSourceFactory();
	$cssf->debug   =  $arr_param[  'debug'    ];
	$cssf->verbose =  $arr_param[  'verbose'  ];
	$coll_source   =  null;
	if ($mcl_mode  ==  MCL_MODE_ENHANCED) 
	{
		$coll_source  =  $cssf->loadEnhancedSourceDefinitions($cxn_mcl);
	} 
	elseif ($mcl_mode  ==  MCL_MODE_OPENMRS_ONLY) 
	{
		$coll_source  =  $cssf->loadOpenmrsOnlySourceDefinitions(
				$cxn_mcl                        , 
				$mcl_default_concept_dict_db    , 
				$mcl_default_concept_dict_name  , 
				$mcl_db_host                    , 
				$mcl_db_uid                     , 
				$mcl_db_pwd                     ,
				$mcl_default_dict_version
			);
	}
	else 
	{
		trigger_error('Invalid value for $mcl_mode in LocalSettings.inc.php: ' . $mcl_mode, E_USER_ERROR);
	}
	$arr_concept_sources  =  $coll_source->getHtmlSelectArray();

// Set default source
	$css_default_source   =  $coll_source->getDictionary($mcl_default_concept_dict_db); 
	if (  !$css_default_source  )  {
		trigger_error('Invalid source "' . $mcl_default_concept_dict_db . 
				'" (Set $mcl_default_concept_dict_db in LocalSettings.inc.php)', E_USER_ERROR);
	}

// TODO: Cache results of ConceptSearchSourceCollection


/****************************************************************************
 *	Setup factory objects
 ***************************************************************************/

// Create ConceptSearchFactory
	$csf  =  new ConceptSearchFactory();
	$csf->setConceptSearchSourceCollection($coll_source);
	$csf->debug    =  $arr_param[  'debug'    ];
	$csf->verbose  =  $arr_param[  'verbose'  ];


/****************************************************************************
 * Set the concept source. Concept sources can be entire dictionaries, subsets 
 * of dictionaries defined by map sources, or concept lists, which can have 
 * concepts in multiple dictionaries). Multiple sources can be used in a single
 * search if separated by commas and all dictionaries can be searched using an 
 * asterisk (*) character. Note that inline sources override global sources. 
 * 
 * Format:
 *
 * 		*|<dictionary_name>[:map(<map_source_id>)]|list(<list_id>)
 *
 ***************************************************************************/

// Parse the source statement
	$coll_source_query = new ConceptSearchSourceCollection();
	if (  isset($arr_param['source']) && $arr_param['source']  )
	{
		$arr_source_text = explode(',', $arr_param['source']);
		foreach ($arr_source_text as $source_text)
		{
			if (  $css = $coll_source->parse($source_text)  )  {
				$coll_source_query->add($css);
			} else {
				trigger_error('Unrecognized source: ' . $source_text, E_USER_ERROR);
			}
		}
	}
	else
	{
		$css = $css_default_source;
		$coll_source_query->add($css);
		$arr_param['source'] = $mcl_default_concept_dict_db;
	}

// If more than one source, add Custom Source element to the displayed source array
	if ($coll_source_query->Count() > 1) {
		array_unshift($arr_concept_sources, array('value'=>'-','display'=>''));
		$arr_custom_source = array(
				'value' => $arr_param['source'],
				'display' => '[Custom Source: ' . $arr_param['source'] . ']'
			);
		array_unshift($arr_concept_sources, $arr_custom_source);
	}


/****************************************************************************
 * Load concept classes/datatypes and apply class/datatype global filters
 ***************************************************************************/

// Load concept classes and datatypes from all dictionaries
	$coll_class              =   $cssf->loadAllConceptClasses  (   $coll_source   );
	$coll_datatype           =   $cssf->loadAllConceptDatatypes(   $coll_source   );
	$coll_class   ->setDefaultDictionary(  $css_default_source  );
	$coll_datatype->setDefaultDictionary(  $css_default_source  );

// Get selected concept classes and datatypes
	$coll_selected_class     =  $coll_class   ->getClasses  (  $arr_param['class']     );
	$coll_selected_datatype  =  $coll_datatype->getDatatypes(  $arr_param['datatype']  );

// Get Html Checklist Arrays for classes and datatypes
	$arr_concept_classes     =  $coll_class   ->getHtmlChecklistArray(  $coll_selected_class     );
	$arr_concept_datatypes   =  $coll_datatype->getHtmlChecklistArray(  $coll_selected_datatype  );


/****************************************************************************
 * User Interface Settings
 ***************************************************************************/

// Set whether search box starts expanded or minimized
	if (strpos($full_search_query, "\n") === false)  {
		// minimized
		$style_q_textarea    =  'display:none;'  ;
		$style_q_textinput   =  ''               ;
		$search_toggle_text  =  '+'              ;
	}  else  {
		// expanded
		$style_q_textarea    =  ''               ;
		$style_q_textinput   =  'display:none;'  ;
		$search_toggle_text  =  '&minus;'        ;
	}

// Determine if using any advanced settings
	$is_advanced_search = false;
	if (  $arr_param[  'debug'     ]  || 
		  $arr_param[  'verbose'   ]  || 
		  $arr_param[  'datatype'  ]  || 
		  $arr_param[  'class'     ]  || 
		  $arr_param[  'retired'   ]  ) 
	{
		$is_advanced_search = true;
	}


/****************************************************************************
 *	Setup and perform search
 ***************************************************************************/

$cs    =  null  ;						// ConceptSearch object
$csr   =  null  ;						// ConceptSearchResults object
$csrr  =  null  ;						// ConceptSearchResultsRenderer object
$enable_search_results_export = false;	// Whether to show "Export" link
if (  $full_search_query  ) 
{

	// Create the ConceptSearch object
		$cs = new ConceptSearch($full_search_query);
		if ($mcl_mode == MCL_MODE_OPENMRS_ONLY) {
			$cs->load_concept_list_names = false;
		}
		$cs->include_retired = (bool) $arr_param[  'retired'  ];
		$cs->setAllSources($coll_source);
		$cs->setSelectedSources($coll_source_query);

	// Apply concept class and datatype filters
		foreach ($coll_selected_class->getKeys() as $key) {
			$cs->addFilter(  $coll_selected_class->Get($key)     );
		}
		foreach ($coll_selected_datatype->getKeys() as $key) {
			$cs->addFilter(  $coll_selected_datatype->Get($key)  );
		}

	// Perform the search
		if (  $cs->Count()  ) 
		{
			$csr  =  $csf->search($cs);
		}

	// Setup the renderer
		if (  $csr  &&  $csr->getVisibleCount()  ) 
		{
			$csrr = new ConceptSearchResultsRenderer($csr);
			// TODO: Add in other url parameters too
			$csrr->arr_url_pararm['source' ]  =  $arr_param['source'];
			$csrr->arr_url_pararm['retired']  =  (int)$cs->include_retired;
			$csrr->highlight_list  =  false;
			if (  isset($arr_param['sort'])  &&  $arr_param['sort']  ) {
				$csrr->sort_by_relevancy      =  true;
				$csrr->show_relevancy         =  true;
				$csrr->arr_url_param['sort']  =  1;
			}

			// Experimental features
			//if (  isset(  $arr_param[  'show_checkbox'  ]  )  ) {
				$csrr->show_checkbox = true;
			//}
			if (  isset(  $arr_param[  'show_icons'     ]  )  )  {
				$csrr->show_icons = true;
			}
		}

	/* 
	 * Setup export
	 * NOTE: Currenty only MDS export for the default dictionary. Export is only enabled
	 * if the search results include 1 or more concepts from the default dictionary.
	 * 
	 * TODO: This is completely a hack - rework along with all the other export functionality
	 */
		$enable_meta_data_export = false;
	 	if ($csr) 
		{
			// Get concepts for the default dictionary
			$csv_all_concept_id = $csr->cc->getVisibleConceptIdCsv($css_default_source);
			if ($csv_all_concept_id) {
				$enable_search_results_export  =  true;
				$enable_meta_data_export       =  true;
			}
		}

}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>MCL:Search</title>
<link href="main.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.1/themes/base/jquery-ui.css">
<script type="text/javascript" src="js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="js/jquery.json-2.3.min.js"></script>
<script type="text/javascript" src="js/jquery.watermark.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
<script type="text/javascript" src="js/search.js"></script>
<script type="text/javascript">
  // google analytics
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-18931025-1']);
  _gaq.push(['_trackPageview']);
  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
</head>
<body>
<div id="divToolbar" style="height:20px;">
	<div id="menu" style="float:left;width:200px;padding:2px;">
		<a href="/">Home</a>&nbsp;&nbsp;&nbsp;
		<strong>Search</strong>&nbsp;&nbsp;&nbsp;
	</div>
<?php if ($user) { ?>
	<div id="user" style="float:right;padding:2px;">
		<strong><?php echo $user->uid; ?></strong>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="signout.php">Sign Out</a>
	</div>
<?php  } else {  ?>
	<div id="signin_mini" style="float:right;">
		<form id="form_user" action="signin.php" method="post">
			<input type="text" id="uid" name="uid" />
			<input type="password" id="pwd" name="pwd" />
			<input type="submit" id="btnsignin" value="Sign In" />&nbsp;&nbsp;|&nbsp;&nbsp;<a href="signup.php">Sign Up</a>
		</form>
	</div>
<?php } ?>
</div>
<div id="divSearch">
	<img id="mcl-search-logo" src="images/mcl-search-logo.png" width="172" height="34" style="float:left;margin-top:4px;margin-right:20px;" />
	<form id="form_search" action="search.php" method="get">
		<input type="hidden" id="q" name="q" />

		<table id="tblSearchBar" cellspacing="0" style="margin-top:4px;">

			<tbody id="tbody_search">
				<tr>
					<td nowrap="nowrap" colspan="2" style="border:1px solid #aaa;width:650px;padding:4px;background-color:#cdf;vertical-align:top;">

						<div style="margin:0;padding:0;">
							<table cellpadding="0" cellspacing="0" width="100%">
								<tr>
									<td width="*" valign="middle" style="border:1px solid #ccc;border-right:none;background-color:#fff;padding-left:5px;padding-right:4px;">
										<input style="<?php echo $style_q_textinput; ?>" type="text" id="q_textinput" value="<?php echo $full_search_query; ?>" onKeyPress="if (event.keyCode==13) return submitSearch();" />
										<textarea style="<?php echo $style_q_textarea; ?>" id="q_textarea" rows="5" cols="50"><?php echo $full_search_query; ?></textarea>
									</td>
									<td width="24" valign="top" style="border:1px solid #ccc;border-left:none;background-color:#fff;">
										<a href="javascript:toggleSearch();" id="a_expand_search" style="text-decoration:none;padding:0 5px;display:inline;" tabindex="-1" title="Expand Search"><?php echo $search_toggle_text; ?></a>
									</td>
								</tr>
							</table>
						</div>


						<div id="divAdvanced" class="toggle_panel" style="display:none;">
							<table cellspacing="0">
							<tr><td><label>Class:</label></td>
							<td><?php 
								// Concept Classes
									echoHtmlChecklist($arr_concept_classes, 'class', 
											'value', 'display', 'selected', 'concept_class_id', 'hint',
											0, ceil(count($arr_concept_classes) / 2) - 1);
								?></td><td>
								<?php
									echoHtmlChecklist($arr_concept_classes, 'class', 
											'value', 'display', 'selected', '', 'hint',
											ceil(count($arr_concept_classes) / 2));
								?></td></tr>

							<tr><td><label>Datatype:</label></td>
							<td><?php
								// Concept Datatypes
									echoHtmlChecklist($arr_concept_datatypes, 'datatype', 
											'value', 'display', 'selected', '', 'hint',
											0, ceil(count($arr_concept_datatypes) / 2) - 1);
								?></td><td>
								<?php
									echoHtmlChecklist($arr_concept_datatypes, 'datatype', 
											'value', 'display', 'selected', '', 'hint',
											ceil(count($arr_concept_datatypes) / 2));
								?></td></tr>

							<tr><td><label for="chkRetired">Include retired:</label></td>
								<td colspan="2"><input type="checkbox" id="chkRetired" name="retired" value="1" <?php
									if ($arr_param['retired']) echo 'checked="checked"';
							?> /></td></tr>

							<tr><td><label for="chkVerbose">Verbose Mode:</label></td>
								<td colspan="2"><input type="checkbox" id="chkVerbose" name="verbose" value="1" <?php 
									if ($arr_param['verbose']) echo 'checked="checked"' 
							?> /></td></tr>

							<!--
							<tr><td><label for="chkSort">Sort Results [Beta]:</label></td>
								<td colspan="2"><input type="checkbox" id="chkSort" name="sort" value="1" <?php 
									if ($arr_param['sort']) echo 'checked="checked"' 
							?> /></td></tr>
							-->

							<?php	if ($arr_param['debug']) { ?>
								<tr><td><label for="chkDebug">Debug Mode:</label></td>
								<td colspan="2"><input type="checkbox" id="chkDebug" name="debug" value="1" checked="checked" /></td></tr>
							<?php } ?>
							</table>
						</div>


						<div style="margin-top:6px;padding:1px;">
							<?php if ($mcl_mode == MCL_MODE_ENHANCED) { ?>
								<label for="source">Source:</label>&nbsp; <?php
								// Concept Dictionary Sources
									$arr_attr = array('id'=>'source', 'name'=>'source');
									echoHtmlSelect($arr_concept_sources, $arr_attr, 'value', 'display', $arr_param['source'], false);
								?>
							<?php } else { echo '&nbsp;'; } ?>
							<div style="float:right;">
								<?php if ($enable_search_results_export) { ?>
								<span style="font-size:8pt;"><a href="javascript:toggleElementVisibility('tbody_search_export');">Export <small>&#9660;</small></a></span>&nbsp;&nbsp;&nbsp;&nbsp;
								<?php } ?>
								<input type="submit" id="btnSubmitSearch" value="Search" onclick="return submitSearch();" />
							</div>
						</div>

					</td>
					<td nowrap="nowrap" style="width:100px;padding-top:4px;padding-left:6px;vertical-align:top;">
						<span style="font-size:8pt;"><a href="javascript:toggleElementVisibility('divAdvanced');">Advanced <small>&#9660;</small></a></span><br />
						<span style="font-size:8pt;"><a href="/wiki/Search_Help" target="_blank">Help <small>&#9660;</small></a></span>
					</td>
				</tr>
			</tbody>

			<?php if ($enable_search_results_export) { ?>

			<tbody id="tbody_search_export" style="display:none;">
				<tr>
					<td colspan="2" style="padding:8px;background-color:#eee;border:1px solid #ccc;border-top:none;">
						<div style="font-weight:bold;margin-bottom:6px;">Export Search Results</div>
						<!--div style="padding:6px;" id="divExportType">
							<input type="radio" name="export_type" id="export_type_all" value="all" checked="checked" />&nbsp;&nbsp; 
								<label for="export_type_all">Export all results</label><br />
							<input type="radio" name="export_type" id="export_type_selected" value="selected" />&nbsp;&nbsp; 
								<label for="export_type_selected">Export selected results only</label>
						</div-->
						<label for="export_format">Format:</label>&nbsp;
						<select id="export_format">
							<?php if ($enable_meta_data_export) { ?>
								<option value="openmrs-meta-data-zip">OpenMRS Meta-Data Package (v1.6.3)</option>
							<?php } ?>
						</select>
						<input style="float:right;" id="btnSubmitExport" type="submit" value="Export" onclick="return submitExport();" />
						<input type="hidden" id="export_values" value="<?php echo $csv_all_concept_id ?>" />
						<p style="padding-left:30px;font-style:italic;">NOTE: Meta-data package only includes concepts from the MVP/MCL Dictionary</p>
					</td>
					<td></td>
				</tr>
			</tbody>
			
			<?php } ?>

<?php 
	/**
	 * Row describing results: num concepts, dictionary, last updated, time elapsed.
	 */
	if ($csr && ($concept_count = $csr->getVisibleCount()))
	{
		// Build text for number of concepts
		// TODO: Create a function in CSR that produces this summary text based on the results - right now its a mix of CSR and CS
		$str_num_concepts   =  '<strong>' . $concept_count . '</strong> unique concept';
		if ($concept_count > 1)  $str_num_concepts  .=  's';
		$str_num_concepts  .=  ' returned from ';
		$str_num_concepts  .=  $coll_source_query->toString();
		$str_num_concepts  .=  ' in ' . number_format($csr->getSearchTime(), 2) . ' seconds.';

		// Build text for additional search details
		$str_search_details = '';
		if (  $is_advanced_search                )  {
			$str_search_details .= ' Advanced search criteria/filter applied.';
		}
		if (  $cs->hasEmptyConceptSearchGroup()  )  {
			$str_search_details .= ' One or more search groups resulted in no query.';
		}
?>
			<tbody id="tbody_num_concepts">
				<tr>
					<td><div id="divResultsCount"><?php 
						echo $str_num_concepts;
						if ($str_search_details) echo '<br />' . $str_search_details;
					?></div></td>
					<td></td>
				</tr>
			</tbody>
<?php } ?>
		</table>

	</form>
</div>

<?php 
if ($csrr) {
	// Render the search results
	echo "<div id=\"divResults\">\n";
	$csrr->render();
	echo "</div>\n";
} elseif ($full_search_query) {
	echo '<div id="divNoResults">Your search did not return any results.</div>';
} 
?>

</body>
</html>