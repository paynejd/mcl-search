function toggleElementVisibility(element_id) {
	e = document.getElementById(element_id);
	if (e.style.display == 'none') {
		if (e.tagName.toUpperCase() == 'TBODY') { e.style.display = 'table-row-group'; }
		else { e.style.display = 'block'; }
	}
	else e.style.display = 'none';
}
function toggleConceptDetailsPane(concept_id) {
	img = document.getElementById('img_toggle_' + concept_id);
	tbl = document.getElementById('tbl_attr_' + concept_id);
	if (tbl.style.display == 'none') {
		tbl.style.display = 'table';
		img.src = 'images/box_minus.jpg';
	} else {
		tbl.style.display = 'none';
		img.src = 'images/box_plus.jpg';
	}
}


/**
 * Concept checkbox functions
 */
function selectAllConcepts() {
	$('input[id^="concept_checkbox"]').each(function() {
		if (!arr_hidden_checkboxes.hasOwnProperty('#' + this.id)) this.checked = true;
	});
	updateAllSearchGroupCheckboxes();
}
function deselectAllConcepts() {
	$('input[id^="concept_checkbox"]').attr('checked', false);
	updateAllSearchGroupCheckboxes();
}
function csrgCheckboxClick(event, checkbox) {
	newvalue = checkbox.checked;
	csrg_i = checkbox.id.split('_')[2];
	$('input[type="checkbox"][id^="concept_checkbox_' + csrg_i + '_"]').each(function() {
		if (!arr_hidden_checkboxes.hasOwnProperty('#' + this.id)) this.checked = newvalue;
	});
	event.stopPropagation();
}
function updateAllSearchGroupCheckboxes() {
	$('thead[id^="csrg_header_"]').each(function() {
		updateSearchGroupCheckbox(this.id.split('_')[2]);
	});
}
function updateSearchGroupCheckbox(group_id) {
	csrg_checkbox_value = null;
	$('input[id^="concept_checkbox_' + group_id + '_"]').each(function(index) {
		// skip if hidden row
		if (arr_hidden_checkboxes.hasOwnProperty('#' + this.id)) return true;

		// compare non-hidden rows
		if (csrg_checkbox_value == null) {
			csrg_checkbox_value = this.checked;
			return true;
		} else if ( csrg_checkbox_value && !this.checked) {
			csrg_checkbox_value = 3;
			return false;
		} else if (!csrg_checkbox_value &&  this.checked) {
			csrg_checkbox_value = 3;
			return false;
		}
	});
	if (csrg_checkbox_value == 3) {
		$('#csrg_checkbox_' + group_id).prop('indeterminate', true);
	} else {
		$('#csrg_checkbox_' + group_id).attr('checked', csrg_checkbox_value);
		$('#csrg_checkbox_' + group_id).prop('indeterminate', false);
	}
}


/**
 * Search Group functions
 */
function toggleSearchGroup(group_id) {
	img = document.getElementById('img_group_toggle_' + group_id);
	e = document.getElementById('csrg_' + group_id);
	if (e.style.display == 'none') {
		e.style.display = 'table-row-group';
		img.src = 'images/box_minus.jpg';
	} else {
		e.style.display = 'none';
		img.src = 'images/box_plus.jpg';
	}
}
function hideSearchGroup(group_id) {
	img = document.getElementById('img_group_toggle_' + group_id);
	e = document.getElementById('csrg_' + group_id);
	e.style.display = 'none';
	img.src = 'images/box_plus.jpg';
}
function collapseAllSearchGroups() {
	$('tbody[id^="csrg_"]').hide(0, function() {
		csrg_i = this.id.split('_')[1];
		$('#img_group_toggle_' + csrg_i).attr('src', 'images/box_plus.jpg');
	});
}
function expandAllSearchGroups() {
	$('tbody[id^="csrg_"]').each(function() {
		this.style.display = 'table-row-group';
		csrg_i = this.id.split('_')[1];
		$('#img_group_toggle_' + csrg_i).attr('src', 'images/box_minus.jpg');
	});
}


/**
 * Handle expansion/minimization of the search box.
 */
function toggleSearch() {
	if (isSearchExpanded()) minimizeSearch();
	else expandSearch();
}
function isSearchExpanded() {
	q_textinput = document.getElementById('q_textinput');	// minimized search box
	if (q_textinput.style.display == 'none') return true;
	else return false;
}
function isSearchMinimized() {
	q_textinput = document.getElementById('q_textinput');	// minimized search box
	if (q_textinput.style.display == 'none') return false;
	else return true;
}
function expandSearch() {
	q_textinput = document.getElementById('q_textinput');
	a_toggle_search = document.getElementById('a_expand_search');
	q_textarea = document.getElementById('q_textarea');
	q_textarea.value = q_textinput.value;
	q_textinput.style.display = 'none';
	q_textarea.style.display = 'block';
	a_toggle_search.innerHTML = '&minus;';
}
function minimizeSearch() {
	// Warn that lines will be condensed if \n present in expanded textarea
	q_textarea = document.getElementById('q_textarea');
	if (isSearchExpanded()) {
		if (q_textarea.value.match(/\n/)) {
			var answer = confirm('Your search will be condensed into a single line if you continue. Continue?');
			if (answer) q_textarea.value = q_textarea.value.replace(/\n/, ' ');
			else return false;
		}
	}

	// minimize the search
	q_textinput = document.getElementById('q_textinput');
	a_toggle_search = document.getElementById('a_expand_search');
	q_textinput.value = q_textarea.value;
	q_textinput.style.display = 'block';
	q_textarea.style.display = 'none';
	a_toggle_search.innerHTML = '&plus;';
}


/*
 * Handles submission of minimized and expanded search.
 */
function submitSearch()
{
	// Get submittable form elements
	form_search = document.getElementById('form_search');	// form that's actually submitted
	q_submit = document.getElementById('q');				// value that's actually submitted
	txt_export_values = document.getElementById('export_values');	// csv of concept ids to export
	select_export_format = document.getElementById('export_format');// select box for the export format

	// Get non-submittable form elements (no name attribute is set)
	q_textinput = document.getElementById('q_textinput');	// minimized search text
	q_textarea = document.getElementById('q_textarea');		// expanded search text

	// Set the search query based on whether search box is expanded/minimized
	if (isSearchExpanded()) q_submit.value = q_textarea.value;
	else q_submit.value = q_textinput.value;
	q_submit.value = q_submit.value.replace(/^\s+|\s+$/g,"");

	// Confirm submission if blank (replace with asterisk if querying all records)
	if (q_submit.value.length == 0) {
		alert('Cannot search empty string. Use asterisk (*) to query all records.');
		return false;
	}

	// Set which form elements are submitted
	if (txt_export_values) txt_export_values.setAttribute('name', '');
	if (select_export_format) select_export_format.setAttribute('name', '');
	q_submit.setAttribute('name', 'q');

	// Setup the form (use post if large search query)
	form_search.setAttribute('action', 'search.php');
	if (q_submit.value.length > 500) form_search.setAttribute('method', 'post');
	else form_search.setAttribute('method', 'get');

	// TODO: validate the submission

	return true;
}


/**
 * Handles submission of exports
 */
function submitExport()
{
	// Get submittable form elements
	form_search = document.getElementById('form_search');			// form that actually submits
	q_submit = document.getElementById('q');						// search query
	txt_export_values = document.getElementById('export_values');	// csv of concept ids to export
	select_export_format = document.getElementById('export_format');// select box for the export format

	// Set which form elements are submitted
	txt_export_values.setAttribute('name', 'export_values');
	select_export_format.setAttribute('name', 'export_format');
	q_submit.setAttribute('name', '');

	// Setup the form
	form_search.setAttribute('method', 'post');
	form_search.setAttribute('action', 'export.php');

	// TODO: Set concept ids to submit
	// NOTE: Currently just submitting all returned concepts
	
	return true;
}





/**
 * Concept Toolbar Actions
 */
function getSelectedConcepts() {
	/* Returns 2d array of concepts with dictionary ID, CSRG ID, concept ID, and concept name. */ 
	var arr_concepts = {};
	$('input[type="checkbox"][id^="concept_checkbox_"]').each(function(index) {
		if (this.checked) {
			csrg_id = this.id.split('_')[2];
			dict_id = this.id.split('_')[3];
			c_id = this.id.split('_')[4];
			el_id = csrg_id + '_' + dict_id + '_' + c_id;
			c_name = $('#tr_concept_' + el_id + ' .td_concept_name span.concept_name').text();
			if (!arr_concepts[el_id]) {
				arr_concepts[el_id] = { dict_id:dict_id, csrg_id:csrg_id, concept_id:c_id, name:c_name };
			}
		}
	});
	return arr_concepts;
}
function getUniqueConcepts(arr_concepts) {
	/* Returns array (same structure as above) of unique concepts only */
	var arr_unique_concepts = {};
	for (var i in arr_concepts) {
		arr_unique_concepts[arr_concepts[i].dict_id + '_' + arr_concepts[i].concept_id] = arr_concepts[i];
	}
	return arr_unique_concepts;
}
function getHtmlConceptTable(arr_concepts) {
	var html_table = '<table cellspacing="1"><thead><tr><th>Dictionary</th><th width="1">ID</th><th>Concept Name</th></thead><tbody>';
	for (var i in arr_concepts) {
		html_table += '<tr><td>' + arr_concepts[i].dict_id + '</td><td>' + 
				arr_concepts[i].concept_id + '</td><td>' + arr_concepts[i].name + '</td></tr>';
	}
	html_table += '</tbody></table>';
	return html_table;
}
function newConceptList() {
	var arr_concepts = getSelectedConcepts();
	var arr_unique_concepts = getUniqueConcepts(arr_concepts);
	var count = 0;
	for (k in arr_unique_concepts) if (arr_unique_concepts.hasOwnProperty(k)) count++;
	$('#newcl_count').html(count);
	$('#newcl_preview').html(getHtmlConceptTable(arr_unique_concepts));
	$('#newcl_json').val($.toJSON(arr_unique_concepts));
	$dialog_new.dialog('open');
}
function addConceptsToList() {
	var arr_concepts = getSelectedConcepts();
	var arr_unique_concepts = getUniqueConcepts(arr_concepts);
	var count = 0;
	for (k in arr_unique_concepts) if (arr_unique_concepts.hasOwnProperty(k)) count++;
	$('#addcl_count').html(count);
	$('#addcl_preview').html(getHtmlConceptTable(arr_unique_concepts));
	$('#addcl_json').val($.toJSON(arr_unique_concepts));
	$dialog_add.dialog('open');
}
function removeConceptsFromList() {
	var arr_concepts = getSelectedConcepts();
	var arr_unique_concepts = getUniqueConcepts(arr_concepts);
	var count = 0;
	for (k in arr_unique_concepts) if (arr_unique_concepts.hasOwnProperty(k)) count++;
	$('#removecl_count').html(count);
	$('#removecl_preview').html(getHtmlConceptTable(arr_unique_concepts));
	$('#removecl_json').val($.toJSON(arr_unique_concepts));
	$dialog_remove.dialog('open');
}
var arr_hidden_checkboxes = {};
function hideSelectedConcepts() {
	// TODO: Hidden concept checkboxes should not be ignored when determining the value of the csrg 
	// checkbox, or when the csrg checkbox is clicked.

	var arr_concepts = getSelectedConcepts();
	for (var i in arr_concepts) {
		var checkbox_selector = '#concept_checkbox_' + arr_concepts[i].csrg_id + '_' + arr_concepts[i].concept_id;
		var row_selector = '#tr_concept_' + arr_concepts[i].csrg_id + '_' + arr_concepts[i].concept_id;
		$(checkbox_selector).prop('disabled', 'disabled').attr('checked', false);
		$(row_selector).hide('fast');
		arr_hidden_checkboxes[checkbox_selector] = checkbox_selector;
	}
	updateAllSearchGroupCheckboxes();
}
function unhideAllConcepts() {
	for (var i in arr_hidden_checkboxes) {
		var checkbox_selector = arr_hidden_checkboxes[i];
		csrg_id = checkbox_selector.split('_')[2];
		concept_id = checkbox_selector.split('_')[3];
		var row_selector = '#tr_concept_' + csrg_id + '_' + concept_id;
		$(checkbox_selector).removeAttr('disabled');
		$(row_selector).css('display', 'table-row');
	}
	arr_hidden_checkboxes = {};
	updateAllSearchGroupCheckboxes();
}


var $dialog_new;
var $dialog_add;
var $dialog_remove;
var $dialog_comment;
function saveList() {
	$.ajaxSetup ({ cache: false });
	var ajax_load = '<img src="images/load.gif" alt="loading..." />';
	var ajax_url  = 'list.php';

}
$(document).ready(function() {
	var ajax_load = '<div style="text-align:center;margin:0 20px;"><img src="images/load.gif" alt="Loading..." /></div>';
	$dialog_new = $('<div></div>')
			.html(
					'<p><label for="newcl_name">Concept List Name:</label><br />' +
					'<input type="hidden" id="newcl_json" />' +
					'<input style="width:415px;" type="text" id="newcl_name" /></p>' +
					'<div>Preview:<span style="float:right;"><span id="newcl_count">0</span> unique concept(s) selected</span></div>' +
					'<div id="newcl_preview" class="concept_preview"></div>' +
					'<div id="newcl_save"></div>'
				)
			.dialog({
					autoOpen: false,
					title: 'Create New Concept List',
					modal: true,
					resizable: false,
					width: '450px',
					buttons: {
						'Cancel': function() { $(this).dialog('close'); },
						'Create List': function() { 
							$('#newcl_save').html(ajax_load).load('list.php', { 
								action:'new', 
								name:$('#newcl_name').val(),
								concepts:$('#newcl_json').val()
							});
							//$(this).dialog('close');
						}
					}
			});
	$dialog_add = $('<div></div>')
			.html (
					'<p><label for="addcl_name">Concept List Name:</label><br />' +
					'<input type="hidden" id="addcl_json" />' +
					'<select id="addcl_list" style="width:415px;">' +
						'<option value="">lists go here</option>' +
					'<select>' +
					'<div>Preview:<span style="float:right;"><span id="addcl_count">0</span> unique concept(s) selected</span></div>' +
					'<div id="addcl_preview" class="concept_preview"></div>' +
					'<div id="addcl_save"></div>'
				)
			.dialog({
					autoOpen: false,
					title: 'Add Concepts to List',
					modal: true,
					resizable: false,
					width: '450px',
					buttons: {
						'Cancel': function() { $(this).dialog('close'); },
						'Add Concepts': function() { 
							$('#addcl_save').html(ajax_load).load('list.php', { 
								action:'add', 
								list:$('#addcl_list').val(),
								concepts:$('#addcl_json').val()
							});
							//$(this).dialog('close');
						}
					}
			});
	$dialog_remove = $('<div></div>')
			.html (
					'<p><label for="removecl_name">Concept List Name:</label><br />' +
					'<input type="hidden" id="removecl_json" />' +
					'<select id="removecl_list" style="width:415px;">' +
						'<option value="">lists go here</option>' +
					'<select>' +
					'<div>Preview:<span style="float:right;"><span id="removecl_count">0</span> unique concept(s) selected</span></div>' +
					'<div id="removecl_preview" class="concept_preview"></div>' +
					'<div id="removecl_save"></div>'
				)
			.dialog({
					autoOpen: false,
					title: 'Remove Concepts from List',
					modal: true,
					resizable: false,
					width: '450px',
					buttons: {
						'Cancel': function() { $(this).dialog('close'); },
						'Remove Concepts': function() {
							$('#removecl_save').html(ajax_load).load('list.php', {
								action:'remove', 
								list:$('#removecl_list').val(),
								concepts:$('#removecl_json').val()
							});
							//$(this).dialog('close');
						}
					}
			});
	$('#button_action').click(function() {
			var action = $('#action').val();
			if      (action == 'new'   )  newConceptList();
			else if (action == 'add'   )  addConceptsToList();
			else if (action == 'remove')  removeConceptsFromList();
			else if (action == 'hide'  )  hideSelectedConcepts();
			else if (action == 'unhide')  unhideAllConcepts();
			else alert ('Unknown action \'' + action + '\'!');
			return false;
	});
});