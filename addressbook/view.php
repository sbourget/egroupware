<?php
/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org                                              *
* Written by Joseph Engo <jengo@phpgroupware.org>                          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'addressbook',
		'enable_contacts_class' => True,
		'enable_nextmatchs_class' => True
	);

	include('../header.inc.php');

	$this = CreateObject("phpgwapi.contacts");

	// First, make sure they have permission to this entry
	$check = addressbook_read_entry($ab_id,array('owner' => 'owner'));
	$perms = $this->check_perms($this->grants[$check[0]['owner']],PHPGW_ACL_READ);

	if ( (!$perms) && ($check[0]['owner'] != $phpgw_info['user']['account_id']) )
	{
		Header("Location: "
			. $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	if (!$ab_id)
	{
		Header("Location: " . $phpgw->link('/addressbook/index.php'));
	}
	elseif (!$submit && $ab_id)
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();
	}

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_file(array('view_t' => 'view.tpl'));
	$t->set_block('view_t','view_header','view_header');
	$t->set_block('view_t','view_row','view_row');
	$t->set_block('view_t','view_footer','view_footer');
	$t->set_block('view_t','view_buttons','view_buttons');

	$customfields = array();
	while (list($col,$descr) = @each($phpgw_info['user']['preferences']['addressbook']))
	{
		if ( substr($col,0,6) == 'extra_' )
		{
			$field = ereg_replace('extra_','',$col);
			$field = ereg_replace(' ','_',$field);
			$customfields[$field] = ucfirst($field);
		}
	}

	while ($column = each($this->stock_contact_fields))
	{
		if (isset($phpgw_info['user']['preferences']['addressbook'][$column[0]]) &&
			$phpgw_info['user']['preferences']['addressbook'][$column[0]])
		{
			$columns_to_display[$column[0]] = True;
			$colname[$column[0]] = $column[0];
		}
	}

	// No prefs?
	if (!$columns_to_display )
	{
		$columns_to_display = array(
			'n_given'    => 'n_given',
			'n_family'   => 'n_family',
			'org_name'   => 'org_name',
			'tel_work'   => 'tel_work',
			'tel_home'   => 'tel_home',
			'email'      => 'email',
			'email_home' => 'email_home'
		);
		while ($column = each($columns_to_display))
		{
			$colname[$column[0]] = $column[1];
		}
		$noprefs = " - " . lang('Please set your preferences for this app');
	}

	// merge in extra fields
 	$extrafields = array(
		'ophone'   => 'ophone',
		'address2' => 'address2',
		'address3' => 'address3'
	);
	$qfields = $this->stock_contact_fields + $extrafields + $customfields;

	$fields = addressbook_read_entry($ab_id,$qfields);

	$record_owner = $fields[0]['owner'];

	if ($fields[0]["access"] == 'private')
	{
		$access_check = lang('private');
	}
	else
	{
		$access_check = lang('public');
	}

	$t->set_var('lang_viewpref',lang("Address book - view") . $noprefs);

	@reset($qfields);
	while (list($column,$null) = @each($qfields)) // each entry column
	{
		if(display_name($colname[$column]))
		{
			$t->set_var('display_col',display_name($colname[$column]));
		}
		elseif(display_name($column))
		{
			$t->set_var('display_col',display_name($column));
		}
		else
		{
			$t->set_var('display_col',ucfirst($column));
		}
		$ref = $data = "";
		if ($fields[0][$column])
		{
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$t->set_var('th_bg',$tr_color);
			$coldata = $fields[0][$column];
			// Some fields require special formatting.
			if ( ($column == "note" || $column == "label" || $column == "pubkey") && $coldata )
			{
				$datarray = explode ("\n",$coldata);
				if ($datarray[1])
				{
					while (list($key,$info) = each ($datarray))
					{
						if ($key)
						{
							$data .= '</td></tr><tr bgcolor="'.$tr_color.'"><td width="30%">&nbsp;</td><td width="70%">' .$info;
						}
						else
						{	// First row, don't close td/tr
							$data .= $info;
						}
					}
					$data .= "</tr>";
				}
				else
				{
					$data = $coldata;
				}
			}
			elseif ($column == "url" && $coldata)
			{
				$ref = '<a href="' . $coldata . '" target="_new">';
				$data = $coldata . '</a>';
			}
			elseif ( (($column == "email") || ($column == "email_home")) && $coldata)
			{
				if ($phpgw_info["user"]["apps"]["email"])
				{
					$ref='<a href="' . $phpgw->link("/email/compose.php","to="
						. urlencode($coldata)) . '" target="_new">';
				}
				else
				{
					$ref = '<a href="mailto:'.$coldata.'">';
				}
				$data = $coldata."</a>";
			}
			else
			{ // But these do not
				$ref = ""; $data = $coldata;
			}

			if (!$data)
			{
				$t->set_var('ref_data',"&nbsp;");
			}
			else
			{
				$t->set_var('ref_data',$ref . $data);
			}
			$t->parse('cols','view_row',True);
		}
	}
	// Following cleans up view_row, since we were only using it to fill {cols}
	$t->set_var('view_row','');

	$fields['cat_id'] = is_array($cat_id) ? implode(',',$cat_id) : $cat_id;

	$cat = CreateObject('phpgwapi.categories');
	$cats = explode(',',$fields[0]['cat_id']);
	if ($cats[1])
	{
		while (list($key,$thiscat) = each($cats))
		{
			if ($thiscat)
			{
				$catinfo = $cat->return_single($thiscat);
				$catname .= $catinfo[0]['name'] . '; ';
			}
		}
		if (!$cat_id)
		{
			$cat_id = $cats[0];
		}
	}
	else
	{
		$fields[0]['cat_id'] = ereg_replace(',','',$fields[0]['cat_id']);
		$catinfo = $cat->return_single($fields[0]['cat_id']);
		$catname = $catinfo[0]['name'];
		if (!$cat_id)
		{
			$cat_id = $fields[0]['cat_id'];
		}
	}

	if (!$catname) { $catname = lang('none'); }

	// These are in the footer
	$t->set_var('lang_owner',lang('Record owner'));
	$t->set_var('owner',$phpgw->common->grab_owner_name($record_owner));
	$t->set_var('lang_access',lang("Record access"));
	$t->set_var('access',$access_check);
	$t->set_var('lang_category',lang('Category'));
	$t->set_var('catname',$catname);

	$sfields = rawurlencode(serialize($fields[0]));

	function html_input_hidden($vars) {
		if (!is_array($vars)) return '';
		while (list($name,$value) = each($vars)) {
			if ($value != '')					// dont need to send all the empty vars
				$html .= "<input type=hidden name=\"$name\" value=\"$value\">\n";
		}
		return $html;
	}

	function html_submit_button($name,$lang) {
		return "<input type=\"submit\" name=\"$name\" value=\"".lang($lang)."\">\n";
	}

	function phpgw_link($url,$vars='') {
		global $phpgw;
		if (is_array( $vars )) {
			while(list($name,$value) = each($vars)) {
				if ($value != '')				// dont need to send all the empty vars
					$v[] = "$name=$value";
			}
			$vars = implode('&',$v);
		}
		return $phpgw->link($url,$vars);
	}				

	function html_1button_form($name,$lang,$hidden_vars,$url,$url_vars='',$method='POST') {
		$html = "<form method=\"$method\" action=\"".phpgw_link($url,$url_vars)."\">\n";
		$html .= html_input_hidden($hidden_vars);
		$html .= html_submit_button($name,$lang);
		$html .= "</form>\n";
		return $html;
	}
	
	$common_vars = array('sort' => $sort,'order' => $order,'filter' => $filter,'start' => $start); // common vars for all buttons
			
	if (($this->grants[$record_owner] & PHPGW_ACL_EDIT) || ($record_owner == $phpgw_info['user']['account_id']))
	{
		$extra_vars = array('cd' => 16,'query' => $query,'cat_id' => $cat_id);
		
		if ($referer) $extra_vars += array( 'referer' => urlencode($referer));
		
		$t->set_var('edit_button',html_1button_form('edit','Edit',array('ab_id' => $ab_id),'/addressbook/edit.php',
																  $common_vars + $extra_vars));
	}
	$t->set_var('copy_button',html_1button_form('submit','copy',$common_vars+array( 'fields' => rawurlencode(serialize($fields[0]))),
															  '/addressbook/add.php'));
	
	if ($fields[0]['n_family'] && $fields[0]['n_given'])
	{
		$t->set_var('vcard_button',html_1button_form('VCardForm','VCard',$common_vars+array( 'ab_id' => $ab_id),'/addressbook/vcardout.php'));
	}
	else
	{
		$t->set_var('vcard_button',lang('no vcard'));
	}
	
	$t->set_var('done_button',html_1button_form('DoneForm','Done',$common_vars,
																$referer ? ereg_replace('/phpgroupware','',$referer) : '/addressbook/index.php',
																$common_vars + array('cd' => 16,'query' => $query)));
	$t->set_var('access_link',$access_link);

	$t->pfp('out','view_t');

	$phpgw->common->phpgw_footer();
?>
