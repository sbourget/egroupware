<?php
	/**************************************************************************\
	* phpGroupWare - Editable Templates                                        *
	* http://www.phpgroupware.org                                              *
	" Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['etemplate']['name']      = 'etemplate';
	$setup_info['etemplate']['title']     = 'eTemplates';
	$setup_info['etemplate']['version']   = '0.9.15.001';
	$setup_info['etemplate']['app_order'] = 8;	// just behind the developers-tools
	$setup_info['etemplate']['tables']    = array('phpgw_etemplate');
	$setup_info['etemplate']['enable']    = 1;

	$setup_info['etemplate']['author'] = 'Ralf Becker';
	$setup_info['etemplate']['license']  = 'GPL';
	$setup_info['etemplate']['description'] =
		'interactive editor for eTemplates (new template type) and database table-editor (creates tables_current.inc.php and updates autom. tables_update.inc.php)';
	$setup_info['etemplate']['maintainer'] = 'Ralf Becker';
	$setup_info['etemplate']['maintainer_email'] = 'ralfbecker@outdoor-training.de';

	/* The hooks this app includes, needed for hooks registration */
	//$setup_info['etemplate']['hooks'][] = 'preferences';
	//$setup_info['etemplate']['hooks'][] = 'admin';
	//$setup_info['etemplate']['hooks'][] = 'about';

	/* Dependencies for this app to work */
	$setup_info['etemplate']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13','0.9.14','0.9.15')
	);
?>
