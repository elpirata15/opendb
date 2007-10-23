<?php
/* 	
	Open Media Collectors Database
	Copyright (C) 2001,2006 by Jason Pell

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

$_OVRD_OPENDB_THEME = 'default';
$_OVRD_OPENDB_LANGUAGE = 'english';

// This must be first - includes config.php
require_once("./include/begin.inc.php");

include_once("./functions/database.php");
include_once("./functions/auth.php");
include_once("./functions/logging.php");

include_once("./functions/http.php");
include_once("./functions/install.php");
include_once("./functions/widgets.php");

function install_header($title)
{
	echo("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">".
		"\n<html>".
		"\n<head>".
		"\n	<title>".get_opendb_title_and_version()." - $title"."</title>".
		"\n<link rel=\"icon\" href=\"images/icon.gif\" type=\"image/gif\" />".
		get_theme_css('install', 'no-menu').
		get_theme_javascript('install').
		"\n</head>".
		"\n<body>");
	
	echo("<div id=\"content\" class=\"installContent\">");
}

function install_footer()
{
	echo("</div>");
	echo("</body></html>");
}

$_opendb_install_required_writedirs = array(
		'log', 'include', 'itemcache', 'httpcache', 'importcache');

$TICK_IMAGE = _theme_image('tick.gif', '', NULL, 'absmiddle');
$CROSS_IMAGE = _theme_image('cross.gif', '', NULL, 'absmiddle');
	
function install_check_missing_081_upload_item_attributes()
{
	$buffer = '';
	
	$missing_files_r = fetch_missing_081_upload_item_attributes($errors);
	if(is_not_empty_array($missing_files_r))
	{
		$buffer .= "<h3>0.8X Cached (./upload directory) Images missing</h3>\n";
		
		$buffer .= "<p class=\"installwarning\">The following File item attributes refer to upload images which do not
			physically exist, this may be due to the absence of the ./upload directory (in which case you should copy it over)</p>";
		
		if(is_array($errors))
			$buffer .= format_error_block($errors);
			
		$buffer .= "<div id=\"missing-files\" class=\"toggleContainer\">";
		$buffer .= "<span id=\"missing-files-toggle\" class=\"toggleHidden\" onclick=\"return toggleVisible(document.getElementById('missing-files-toggle'), getChildElementByTagName(document.getElementById('missing-files'), 'div'));\">File Item Attributes</span>";
		$buffer .= "<div class=\"elementHidden\" id=\"missing-files-content\">";
		
		$buffer .= "<ul>";
		$count = 0;
		$total_count = count($missing_files_r);
		while(list(,$filename) = each($missing_files_r))
		{
			if($count > 100)
			{
				$buffer .= "<li>(".($total_count - $count)." more)</li>";
				break;
			}
			
			$buffer .= "<li>$filename</li>";
			$count++;
		}
		
		$buffer .= "</ul></div></div>";
	}
	
	return $buffer;
} 

function install_check_directories(&$doContinue)
{
	global $_opendb_install_required_writedirs, $TICK_IMAGE, $CROSS_IMAGE;
	
	$buffer = "<h3>Directories</h3>";
	
	$buffer .= "<table class=\"installDirList\">";
	
	$buffer .= "<tr class=\"navbar\"><th>Directory</th>
					<th>Exists</th>
					<th>Writable</th>
				</tr>";

    reset($_opendb_install_required_writedirs);
	while(list(,$directory) = each($_opendb_install_required_writedirs))
	{
		$buffer .= "<tr><th class=\"prompt\">".$directory."</td>";
		
		if (is_dir('./'.$directory))
		{
			$buffer .= "<td class=\"data\">$TICK_IMAGE</td>";
			
			if (is_writable('./'.$directory))
			{
				$buffer .= "<td class=\"data\">$TICK_IMAGE</td>";
			}
			else
			{
				$buffer .= "<td class=\"data\">$CROSS_IMAGE</td>";
				$doContinue = FALSE;
			}
		}
		else
		{
			$buffer .= "<td class=\"data\">$CROSS_IMAGE</td>
						<td class=\"data\">$CROSS_IMAGE</td>";
			
			$doContinue = FALSE;
		}
		
		$buffer .= "</tr>";
	}
    
	$buffer .= "</table>";
	
	if(!$doContinue)
	{
		$buffer .= "<p class='error'>All the directories above must be writable in order for OpenDb to function correctly.</p>";
		
		$buffer .= "<p><em>The following command can be executed from the OpenDb installation directory on a unix or linux operating system:</em></p>
			<p><code>chmod ugo+w include log itemcache httpcache importcache</code></p>";
	}
	
	return $buffer;
}

function install_check_install_dir()
{
	$buffer = '';
	
	// installed in 0.81
    if(file_exists("./patch") || 
    			file_exists("./lang") || 
    			file_exists("./include/config.php"))
    {
    	$buffer .= "<h3>Installed in a new directory?</h3>";
    	
    	$buffer .= "<p class=\"installwarning\">This OpenDb release must <strong>not</strong> be installed into the same directory as a existing 0.80 or 0.81 release.
					If you have cached images in the upload/ directory, copy the upload/ directory into the new 
					installation directory instead.</p>";
    }
    
    return $buffer;
}

function install_check_php_settings()
{
	$buffer = "<h3>PHP Settings</h3>\n";
    
	$buffer .= "\n<form action=\"$PHP_SELF\" method=\"GET\">";
	$buffer .= "<table>";
	
	if (opendb_version_compare(phpversion(), "4.3.0", ">="))
	{
		$buffer .= format_field("PHP Version", NULL, phpversion());
	}
	else
	{
		$buffer .= format_field("PHP Version", NULL, "<span class='error'>".phpversion()." (must be >= 4.3.0)</span>");
	}

	if(preg_match("/([0-9]+)M/", ini_get('memory_limit'), $matches))
	{
		if(is_numeric($matches[1]) && $matches[1] >= 8)
		{
			$buffer .= format_field("Memory Limit", NULL, $matches[0]);
		}
		else
		{
			$buffer .= format_field("Memory Limit", NULL, "<span class='error'>".$matches[0]." (should be >= 8M)</span>");
		}
	}
	
	if(ini_get('safe_mode') == 0 || strtolower(ini_get('safe_mode')) == 'off')
	{
		$buffer .= format_field("Safe Mode", NULL, "off");
	}
	else
	{
		$buffer .= format_field("Safe Mode", NULL, "<span class='error'>on (item / http cache, file uploads, and potentially other parts of the system do not function well with safe mode enabled.)</span>");
	}
	
	$max_execution_time = ini_get('max_execution_time');
	if(is_numeric(ini_get('max_execution_time')) && 
					(ini_get('max_execution_time') >= 600 || (@set_time_limit('600') && ini_get('max_execution_time') >= 600)))
	{
		if($max_execution_time < 600)
		{
			$max_execution_time = 600; // set_time_limit can be activated, so for display purposes, up it to the 600, which is what we use
		}
		
		$buffer .= format_field("Max Execution Time", NULL, $max_execution_time);
	}
	else
	{
		$buffer .= format_field("Max Execution Time", NULL, "<span class='error'>".$max_execution_time." (set_time_limit disabled - set_time_limit should be enabled or max_execution_time >= 600)</span>");
	}

	if(ini_get('register_globals') == 0 || strtolower(ini_get('register_globals')) == 'off')
	{
		$buffer .= format_field("Register Globals", NULL, "off");
	}
	else
	{
		$buffer .= format_field("Register Globals", NULL, "<span class='warn'>on (register globals is not required)</span>");
	}
	
	if(ini_get('file_uploads') == 1 || strtolower(ini_get('file_uploads')) == 'on')
	{
		$buffer .= format_field("File Uploads", NULL, "on");
	}
	else
	{
		$buffer .= format_field("File Uploads", NULL, "<span class='error'>off (import functionality will be disabled)</span>");
	}

	$buffer .= "</table>";
	
	return $buffer;
}

function install_check_mysql_collation_mismatch()
{
	$buffer = '';
	
	$default_collation = get_opendb_table_column_collation_mismatches($table_colation_mismatch, $table_column_colation_mismatch);
	if(is_not_empty_array($table_colation_mismatch) || is_not_empty_array($table_column_colation_mismatch))
    {
		$buffer .= "<h3>MYSQL Collation Mismatch</h3>\n";
		
		$buffer .= "<p class=\"installwarning\">This OpenDb installation has table and/or table column collation mismatches.  This may or may not
				be indicative of a problem.  Check out the <a href=\"http://opendb.iamvegan.net/wiki/index.php?title=Mysql_Collation_Mismatch_Error\">OpenDb website topic</a> 
				for more information.</p>";
		
		$buffer .= "<p>The database default or prevalent table collation (where the database default cannot be derived) 
				is <strong>$default_collation</strong>.  The following table and/or table columns do not match:</p>"; 

		$buffer .= "<table>";
		$buffer .= "<tr class=\"navbar\"><th>Table</th><th>Column</th><th>Collation</th></tr>";
		$table_r = fetch_opendb_table_list_r();
		while(list(,$table) = each($table_r))
		{
			if(isset($table_colation_mismatch[$table]))
			{
				$buffer .= "<tr><td class=\"prompt\">$table</td><td class=\"prompt\">&nbsp;</td><td class=\"data\">".$table_colation_mismatch[$table]."</td></tr>";
			}
			
			if(isset($table_column_colation_mismatch[$table]))
			{
				while(list($column,$collation) = each($table_column_colation_mismatch[$table]))
				{
					$buffer .= "<tr><td class=\"prompt\">$table</td><td class=\"prompt\">$column</td><td class=\"data\">".$collation."</td></tr>";
				}
			}
		}
		$buffer .= "</table>";
    }
    
    return $buffer;
}

function install_current_db_configuration()
{
	$buffer = '';
	
	$dbserver_conf_r = get_opendb_config_var('db_server');
	if(is_array($dbserver_conf_r))
	{
		$buffer .= "<h3>OpenDb Database Configuration</h3>\n";
		
		$buffer .= "<p>The following are the database connection details configured in the <code>./include/local.config.php</code>.  If you would like to force the
				installer to have the details re-entered, please delete or rename the <code>./include/local.config.php</code> file.</p>";
		
		$buffer .= "<table class=\"databaseDetails\">
			<tr><td class=\"prompt\">MySQL Database Hostname:</td><td class=\"data\">".$dbserver_conf_r['host']."</td></tr>
			<tr><td class=\"prompt\">MySQL Database Name:</td><td class=\"data\">".$dbserver_conf_r['dbname']."</td></tr>
			<tr><td class=\"prompt\">MySQL User Name:</td><td class=\"data\">".$dbserver_conf_r['username']."</td></tr>
			<tr><td class=\"prompt\">Table Prefix:</td><td class=\"data\">".(strlen($dbserver_conf_r['table_prefix'])>0?$dbserver_conf_r['table_prefix']:"(none)")."</td></tr>
		</table>";
	}
	
	return $buffer;
}

function install_check_user_permissions()
{
	global $TICK_IMAGE, $CROSS_IMAGE;
	
	$buffer = '';
	
	$privileges_rs = get_dbuser_privileges();
	
	// if no privileges can be ascertained don't bother displaying anything.
	if(is_not_empty_array($privileges_rs))
	{
		$buffer .= "<h3>OpenDb Database User Privileges</h3>\n";
		
		$buffer .= "<p>The following table lists selected user privileges that may cause issues in OpenDb, not
					all privileges are listed for the moment, only known problematic ones.</p>";
		
		$buffer .= "<table class=\"userPrivilegesList\">";
	
		$buffer .= "<tr class=\"navbar\"><th>Privilege</th>
					<th>Granted</th>
				</tr>";

    	reset($privileges_rs);
		while(list(,$privileges_r) = each($privileges_rs))
		{
			$buffer .= "<tr class=\"prompt\"><th>".$privileges_r['privilege']."</th>";
			if($privileges_r['granted'])
			{
				$buffer .= "<td class=\"data\">$TICK_IMAGE</td>";
			}
			else
			{
				$buffer .= "<td class=\"data\">$CROSS_IMAGE</td>";
			}
					
			$buffer .= "</tr>";
		}
		
		$buffer .= "</table>";
	}
	
	return $buffer;
}
		
function install_pre_check($next_step)
{
	$doContinue = TRUE;
		
    $buffer = "<h2>Pre Installation</h2>\n";
    
    $buffer .= "<h3>Have you backed up?</h3>
		<p>OpenDb installer is about to make changes to your database, and although in <strong>almost</strong> 
		all cases there will be no issues, there is <strong>always</strong> the remote possibility that a failed upgrade would leave your
		database in an inconsistent state.  You should always perform (at the very least) a PHPMyAdmin Database Export or OpenDb Backup 
		(of all tables) before running the installer.</p>";
    
    $buffer .= install_check_install_dir();
    
    $buffer .= install_check_php_settings();
	
	$buffer .= install_check_mysql_collation_mismatch();
	
	$buffer .= install_current_db_configuration();
	
	$buffer .= install_check_user_permissions();
	
	$buffer .= install_check_directories($doContinue);
	
	$buffer .= install_check_missing_081_upload_item_attributes();
	
	if($doContinue)
	{
		$buffer .= "<input type=\"hidden\" name=\"step\" value=\"$next_step\">".
		"<input type=\"button\" value=\"Next\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">";
	}
	else
	{
		$buffer .= "<input type=\"hidden\" name=\"step\" value=\"\">".
		"<input type=\"button\" value=\"Retry\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">";
	}
	$buffer .= "</form>\n";
	
	return $buffer;
}

function install_opendb_user_and_database_form($HTTP_VARS, $errors)
{
  	global $PHP_SELF;
  	
	$buffer = "<h3>OpenDb Database Configuration</h3>\n";
    
    if(is_array($errors))
		echo format_error_block($errors);
							
	$buffer .= "\n<form action=\"$PHP_SELF\" method=\"GET\">".
		"<input type=\"hidden\" name=\"step\" value=\"pre-install\">";
	
    $dbserver_conf_r = get_opendb_config_var('db_server');

    $buffer .= "<p>What database do you want to use for your OpenDb installation?</p>";
    
	$buffer .= "<table>";

	$buffer .= get_input_field("host",
		NULL, // s_attribute_type
		"MySQL Database Hostname",
        "text(32,32)", //input type.
        "Y", //compulsory!
        strlen($HTTP_VARS['host'])>0
        	? $HTTP_VARS['host']
        	: ifempty($dbserver_conf_r['host'], 'localhost'),
		TRUE);

    $buffer .= get_input_field("dbname",
		NULL, // s_attribute_type
		"MySQL Database Name",
        "text(32,32)", //input type.
        "Y", //compulsory!
        strlen($HTTP_VARS['dbname'])>0
        	? $HTTP_VARS['dbname']
        	: ifempty($dbserver_conf_r['dbname'], 'opendb'),
		TRUE);

    $buffer .= get_input_field("username",
		NULL, // s_attribute_type
		"MySQL User Name",
        "text(32,32)", //input type.
        "Y", //compulsory!
        strlen($HTTP_VARS['username'])>0
        	? $HTTP_VARS['username']
        	: ifempty($dbserver_conf_r['username'], 'lender'),
		TRUE);

	$buffer .= get_input_field("passwd",
		NULL, // s_attribute_type
		"MySQL User Password",
        "text(32,32)", //input type.
        "Y", //compulsory!
        strlen($HTTP_VARS['passwd'])>0
        	? $HTTP_VARS['passwd']
        	: ifempty($dbserver_conf_r['passwd'], 'test'),
		TRUE);

    $buffer .= get_input_field("table_prefix",
		NULL, // s_attribute_type
		"Table Prefix",
        "text(32,32)", //input type.
		"N", //compulsory!
        strlen($HTTP_VARS['table_prefix'])>0
        	? $HTTP_VARS['table_prefix']
        	: $dbserver_conf_r['table_prefix'],
		TRUE);

	$buffer .= "</table>";
	
	$buffer .= "<h3>MySQL Root User</h3>";

	$buffer .= "<p>If the specified database and/or user do not already exist, the installer can create them, but only if MySQL 'Root' User credentials are provided that have
	 	permission to CREATE DATABASE and GRANT ALL PRIVILEGES.</p>";
	
	$buffer .= "<table>";
	
	$buffer .= get_input_field("db_username",
		NULL, // s_attribute_type
		"MySQL Root User Name",
        "text(32,32)", //input type.
        "N", //compulsory!
        $HTTP_VARS['db_username'],
		TRUE);

    $buffer .= get_input_field("db_passwd",
		NULL, // s_attribute_type
		"MySQL Root User Password",
        "password(32,32)", //input type.
        "N", //compulsory!
        $HTTP_VARS['db_passwd'],
		TRUE);

    $buffer .= "</table>";
	
    if(get_opendb_config_var('widgets', 'show_prompt_compulsory_ind')!==FALSE)
	{
		$buffer .= format_help_block(array('img'=>'compulsory.gif', 'text'=>'Compulsory Field'));
	}
    
	$buffer .= "<input type=\"button\" value=\"Next\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">\n";
	$buffer .= "</form>\n";
	
	return $buffer;
}

/**
	Assumes database does not exist when this function is called
		
	@return TRUE if step completed
*/
function install_create_opendb_user_and_database($HTTP_VARS, &$db_details_r, &$errors)
{
  	global $PHP_SELF;
  	
	$HTTP_VARS['db_username'] = trim($HTTP_VARS['db_username']);
	$HTTP_VARS['db_passwd'] = trim($HTTP_VARS['db_passwd']);
	$HTTP_VARS['host'] = trim($HTTP_VARS['host']);
	$HTTP_VARS['dbname'] = trim($HTTP_VARS['dbname']);
	$HTTP_VARS['username'] = trim($HTTP_VARS['username']);
	$HTTP_VARS['passwd'] = trim($HTTP_VARS['passwd']);
	
	// if info provided, then we can move onto next screen
	if(strlen($HTTP_VARS['host'])>0 &&
			strlen($HTTP_VARS['dbname'])>0 &&
			strlen($HTTP_VARS['username'])>0 &&
			strlen($HTTP_VARS['passwd'])>0)
	{
	  	// set database config array
		$db_details_r =
			array('host'=>$HTTP_VARS['host'],
				'dbname'=>$HTTP_VARS['dbname'],
				'username'=>$HTTP_VARS['username'],
				'passwd'=>$HTTP_VARS['passwd'],
				'table_prefix'=>$HTTP_VARS['table_prefix']);
		
		$error = NULL;
	    $check_result = check_opendb_database($HTTP_VARS['host'], $HTTP_VARS['dbname'], $HTTP_VARS['username'], $HTTP_VARS['passwd'], $error);
	    if($check_result === TRUE)
		{
			return 'DATABASE_ALREADY_EXISTS';
		}
		else if (strlen($HTTP_VARS['db_username'])>0 && strlen($HTTP_VARS['db_passwd'])>0)
		{
			if(create_opendb_user_and_database(
					$HTTP_VARS['db_username'],
					$HTTP_VARS['db_passwd'],
					$HTTP_VARS['host'],
					$HTTP_VARS['dbname'],
					$HTTP_VARS['username'],
					$HTTP_VARS['passwd'],
					$error))
			{
				return TRUE;
			}
			else
			{
				$errors[] = array('error'=>'Database '.$HTTP_VARS['dbname'].'@'.$HTTP_VARS['host'].' not created.', 'detail'=>$error);
				return FALSE;
			}
		}
		else
		{
			$errors[] = array('error'=>'Database '.$HTTP_VARS['dbname'].'@'.$HTTP_VARS['host'].' not found or accessible.', 'detail'=>'You must provide appropriate MySQL Username and MySQL Password credentials.');
			return FALSE;
		}
	}
	else
	{
	  	return FALSE;
	}
}

/**
	Assumes that this function would not be called unless config file
	should be written, however will do quick check to see if info
	passed through is equal to what is already in the config file,
	and return TRUE if this is the case, otherwise it will return FALSE.
*/
function install_write_config_file($db_details_r, &$config_file, &$errors)
{
    if(strlen($db_details_r['host'])>0 &&
			strlen($db_details_r['dbname'])>0 &&
			strlen($db_details_r['username'])>0 &&
			strlen($db_details_r['passwd'])>0)
	{
	    $dbserver_conf_r = get_opendb_config_var('db_server');
		if(is_array($dbserver_conf_r) &&
				$db_details_r['host'] == $dbserver_conf_r['host'] &&
				$db_details_r['dbname'] != $dbserver_conf_r['dbname'] &&
				$db_details_r['username'] != $dbserver_conf_r['username'] &&
				$db_details_r['passwd'] != $dbserver_conf_r['passwd'] &&
				$db_details_r['table_prefix'] != $dbserver_conf_r['table_prefix'])
		{
		    return TRUE;
		}
	    else
	    {
    		$config_file = "<?php\n".
    				"\$CONFIG_VARS['db_server'] = array(\n".
					"	'host'=>'{$db_details_r['host']}',		//OpenDb database host\n".
					"	'dbname'=>'{$db_details_r['dbname']}',		//OpenDb database name\n".
					"	'username'=>'{$db_details_r['username']}',		//OpenDb database user name\n".
					"	'passwd'=>'{$db_details_r['passwd']}',		//OpenDb user password\n".
					"	'table_prefix'=>'{$db_details_r['table_prefix']}', 	//Table prefix.\n".
					"	'debug-sql'=>FALSE);\n\n".	
					"\$CONFIG_VARS['session_handler']['enable'] = FALSE;\n".
					"?>\n";
	
			if(file_put_contents('./include/local.config.php', $config_file))
			{
   			    return TRUE;
	   		}
   			else
   			{
   				if(!is_writable('./include/'))
   				{
   					$errors[] = 'Directory (./include/) is not writable.';
   				}
   				else if(!is_writable('./include/local.config.php'))
   				{
   					$errors[] = 'File (./include/local.config.php) is not writable.';
   				}
   				
	   		    return FALSE;
			}
		}
	}
	else
	{
	    return FALSE;
	}
}

function install_opendb_new_install($HTTP_VARS, &$errors)
{
  	global $PHP_SELF;
  	
	if($HTTP_VARS['confirmed'] === 'true')
  	{
  	  	// insert first record.
		if(exec_install_sql_file("./install/new/tables.sql", $errors))
	   	{
	   	  	if(exec_install_sql_file("./install/new/systemdata.sql", $errors) && 
				 	exec_install_sql_file("./admin/s_language/sql/english.sql", $errors))
	   		{
	   			exec_install_sql_file("./admin/s_status_type/sql/A-Available.sql", $errors);
				exec_install_sql_file("./admin/s_status_type/sql/N-Inactive.sql", $errors);
				exec_install_sql_file("./admin/s_status_type/sql/H-Hidden.sql", $errors);
				exec_install_sql_file("./admin/s_status_type/sql/X-External.sql", $errors);
				exec_install_sql_file("./admin/s_status_type/sql/W-Wishlist.sql", $errors);
				exec_install_sql_file("./admin/s_status_type/sql/R-Related.sql", $errors);
				
				exec_install_sql_file("./install/new/guestuser.sql", $errors);
				exec_install_sql_file("./install/new/adminuser.sql", $errors);

				// no steps to complete, its all in one, so we can insert release record with 
				// NULL step (indicating complete) straight away.
				if(insert_opendb_release(get_opendb_version(), 'New Installation', NULL)!==FALSE)
				{
			   	    return TRUE;
				}
				else
				{
				  	$errors[] = 'Failed to insert OpenDb release record (Version '.get_opendb_version().')';
				  	return FALSE;
				}
			}
		}

		//else
	    return FALSE;
  	}
  	else if($HTTP_VARS['confirmed'] !== 'false')
	{
		echo("<p>The database tables, system data and admin user need to be installed to the OpenDb database.</p>");
		
	  	echo("<form action=\"$PHP_SELF\" method=\"GET\">".
			"<input type=\"hidden\" name=\"step\" value=\"install\">".
			"<input type=\"hidden\" name=\"confirmed\" value=\"true\">".
			"<input type=\"button\" value=\"Install\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">".
			"</form>");
		
		return FALSE;
	}
}

function install_opendb_upgrade($HTTP_VARS, &$errors)
{
	// get last record added to database, regardless of whether complete or not.
	$opendb_release_r = fetch_opendb_release_version_r(FALSE);

	// if its numeric, then we are in the middle of an upgrade
 	if(is_array($opendb_release_r) && is_numeric($opendb_release_r['upgrade_step']))
 	{
 	    $upgraders_r = get_upgrader_r($opendb_release_r['release_version']);
 	    $HTTP_VARS['upgrader_plugin'] = $upgraders_r['upgrader_plugin'];
 	}
 	else
	{
		$latest_to_version = NULL;
	
		// $latest_to_version is out param!
		$upgraders_rs = get_upgraders_rs(is_array($opendb_release_r)?$opendb_release_r['release_version']:NULL, get_opendb_version(), $latest_to_version);
		if(is_array($upgraders_rs)) {
			if(count($upgraders_rs) == 1)
			{
				$HTTP_VARS['upgrader_plugin'] = $upgraders_rs[0]['upgrader_plugin'];
		    	$upgraders_rs = NULL;
			}
			else if(count($upgraders_rs) > 1)
			{
				$errors[] = "More than one upgrader is available for this version, this is an error, please contact the author."; 
				return FALSE; // more than one upgrade step possible is an error!
			}
		} else {
			return FALSE;	
		}
		
    }//else - already defined, so should already have record.

	if(is_upgrader_plugin($HTTP_VARS['upgrader_plugin']))
	{
		return perform_upgrade_step($HTTP_VARS, $opendb_release_r, $latest_to_version);
	}
	else
	{
		// no steps required
	  	insert_opendb_release(get_opendb_version(), 'Upgrade from '.$opendb_release_r['release_version'].' to '.get_opendb_version(), NULL);
	  	
	  	return TRUE;
	}	  
}

function perform_upgrade_step($HTTP_VARS, $opendb_release_r, $latest_to_version = NULL)
{
  	if(is_upgrader_plugin($HTTP_VARS['upgrader_plugin']))
	{
      	include_once('./install/upgrade/'.$HTTP_VARS['upgrader_plugin'].'.class.php');
        $upgraderRef = $HTTP_VARS['upgrader_plugin'];
        $upgraderPlugin = new $upgraderRef();
        
        if(!is_array($opendb_release_r) || !is_numeric($opendb_release_r['upgrade_step']))
 		{
 			if($latest_to_version == $upgraderPlugin->getToVersion())
 			{
				$to_version = $latest_to_version;
 				$description = 'Upgrade from '.$opendb_release_r['release_version'].' to '.$to_version;
 			}
 			else
 			{
 				$to_version = $upgraderPlugin->getToVersion();
 				$description = $upgraderPlugin->getDescription();
 			}
 			
 			// insert release record now.
			if(!is_exists_opendb_release_version($to_version))
	        {
	            insert_opendb_release($to_version, $description, '0');
	            
	            $opendb_release_r = fetch_opendb_release_version_r(FALSE);
	        }
        }
        
		$upgrade_step = $opendb_release_r['upgrade_step'];
		
		// first step
		if($upgrade_step == 0)
		{
			$upgrade_step = 1;
		}
		
		if(is_numeric($opendb_release_r['upgrade_step_part']) && $opendb_release_r['upgrade_step_part'] > 0)
		{
			$upgrade_step_part = $opendb_release_r['upgrade_step_part'];
		}
		else
		{
			$upgrade_step_part = 1;
		}
		
		$remaining_count = -1;
		
		if($HTTP_VARS['confirm_step'] == $upgrade_step)
		{
			if($upgraderPlugin->isStepSkippable($upgrade_step) && strcasecmp($HTTP_VARS['skipStep'], 'true') === 0)
			{
				$step_result = TRUE;
			}
			else
			{
				$step_result = $upgraderPlugin->executeStep($upgrade_step, $upgrade_step_part-1);
			}
									
			if($step_result === TRUE)
			{
				$upgrade_step += 1;
				$upgrade_step_part = 1;
				
				if($upgrade_step <= $upgraderPlugin->getNoOfSteps())
				{
					// complete step and move onto next one.
					update_opendb_release_step($opendb_release_r['release_version'], $upgrade_step);
				}
				else
				{
					// we have finished the installation process with this call
					update_opendb_release_step($opendb_release_r['release_version'], NULL);
				}
			}
			else if(is_numeric($step_result))
			{
				$upgrade_step_part += 1;
				
				update_opendb_release_step($opendb_release_r['release_version'], $upgrade_step, $upgrade_step_part);
				
				// specified number of steps to complete
				if($step_result > 0)
				{
					$remaining_count = $step_result;
				}
			}
		}

		if($upgrade_step <= $upgraderPlugin->getNoOfSteps())
		{
			echo("<h2>".$opendb_release_r['description']."</h2>");
       
			echo("\n<form action=\"$PHP_SELF\" method=\"GET\">".
				"<input type=\"hidden\" name=\"step\" value=\"upgrade\">");
				
			echo("<input type=\"hidden\" name=\"upgrader_plugin\" value=\"".$HTTP_VARS['upgrader_plugin']."\">");
			
			$step_title = $upgraderPlugin->getStepTitle($upgrade_step);
			$step_notes = NULL;
			
			if(is_numeric($upgrade_step_part) && $upgrade_step_part > 1)
			{
				if($remaining_count > 0)
				{
					$step_title .= " (Part ".($upgrade_step_part)." of ".($upgrade_step_part+$remaining_count).")";
				}
				else
				{
					$step_title .= " (Part ".($upgrade_step_part)." of ?)";
					$step_notes = "Due to limitations in the installer there are an unknown number of parts for this step, please continue
									executing the step until it is complete.";
				}
			}
			
			echo("<h3>".$step_title."</h3>");
			
			if(strlen($step_notes)>0)
			{
				echo("<p class=\"help\">$step_notes</p>");
			}
			
			if(is_array($upgraderPlugin->getErrors()))
			{
			    echo format_error_block($upgraderPlugin->getErrors());
			}
			
			$description = $upgraderPlugin->getStepDescription($upgrade_step);
			if(strlen($description)>0)
			{
				echo('<p>'.$description.'</p>');
			}
			
			echo("<input type=\"hidden\" name=\"confirm_step\" value=\"$upgrade_step\">
			<input type=\"button\" name=\"execute\" value=\"Execute Step\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">\n");
			
			if($upgraderPlugin->isStepSkippable($upgrade_step))
			{
				echo("<input type=\"checkbox\" name=\"skipStep\" value=\"true\" onclick=\"if(this.checked){this.form['execute'].value='Skip Step';}else{this.form['execute'].value='Execute Step';}}\">Skip Step");
			}

			echo("</form>");
			
			return 'INCOMPLETE';
			
		}//if($upgrade_step <= $upgraderPlugin->getNoOfSteps())
		else
		{
			return TRUE;
		}
	}
	else
	{
		return FALSE;
	}
}

if(strlen($HTTP_VARS['step'])==0)
{
	echo install_header('OpenDb Installation', FALSE);
	echo("<h1>OpenDb ".get_opendb_version()." Installation</h1>\n");
	
  	$next_step = 'pre-install';
	
	if(is_db_connected())
	{
		// make sure there are actually pre-install changes that are required.  	
		if(check_opendb_table('s_opendb_release') && count_opendb_table_columns('s_opendb_release') == 6 && 
				// require a release record if opendb database has tables other than s_opendb_release already extant!
				(!is_opendb_partially_installed() || count_opendb_table_rows('s_opendb_release')>0))
		{
			$next_step = 'upgrade';
		}  
	}
	else
	{
		$errors = db_error();
		if(strlen($errors)>0)
		{
			echo("<p class=\"error\">".$errors."</p>");
		}
	}
    
	echo install_pre_check($next_step);
	
	echo install_footer();
}
else if($HTTP_VARS['step'] == 'pre-install')
{
	@set_time_limit(600);
  	
  	$errors = NULL;
  	
	if(!is_db_connected()) // database does not exist or cannot be connected to
	{
		echo install_header('OpenDb Installation', FALSE);
		echo("<h1>OpenDb ".get_opendb_version()." Installation</h1>\n");
	
		echo("<h2>Pre Installation</h2>");
	
		$db_created = install_create_opendb_user_and_database($HTTP_VARS, $db_details_r, $errors);
		
		if($db_created === 'DATABASE_ALREADY_EXISTS')
		{
		  	if($HTTP_VARS['confirmed'] === 'true')
		  	{
		  	  	$db_created = TRUE;
		  	}
		  	else if($HTTP_VARS['confirmed'] !== 'false')
			{
				echo("<h3>OpenDb Database Configuration</h3>");
				
				echo("<p>The following are the OpenDb database details you have specified.  Please double check these details and if they
				are correct, click <strong>Continue</strong> to begin the Upgrade / Installation, or click <strong>Go Back</strong> to correct
				any mistakes.</p>");
				
				echo("<table class=\"databaseDetails\">
					<tr><td class=\"prompt\">MySQL Database Hostname:</td><td class=\"data\">".$HTTP_VARS['host']."</td></tr>
					<tr><td class=\"prompt\">MySQL Database Name:</td><td class=\"data\">".$HTTP_VARS['dbname']."</td></tr>
					<tr><td class=\"prompt\">MySQL User Name:</td><td class=\"data\">".$HTTP_VARS['username']."</td></tr>
					<tr><td class=\"prompt\">Table Prefix:</td><td class=\"data\">".(strlen($HTTP_VARS['table_prefix'])>0?$HTTP_VARS['table_prefix']:"(none)")."</td></tr>
				</table>");
				
				$db_status = install_determine_opendb_database_status($db_details_r, $db_version);
				
				if($db_status === 'DATABASE_WITH_NO_TABLES_EXISTS')
				{
					echo("<p>The database you have specified already exists.   <strong>This database does not have an existing OpenDb 
						installation</strong>.  If you expected to be upgrading an existing OpenDb database, you should double 
						check the parameters you provided.</p>");
				}
				else if($db_status === 'DATABASE_WITH_TABLES_EXISTS')
				{
					echo("<p>The database you have specified already exists.  <strong>This database does not have an existing OpenDb 
						installation</strong>.  If you expected to be upgrading an existing OpenDb database, you 
						should double check the parameters you provided, especially the table prefix.</p>");
				}
				else if($db_status === 'OPENDB_DATABASE_WITH_NO_PREFIX_EXISTS')
				{
					echo("<p>The database you have specified already exists.  <strong>This database does not have an existing OpenDb 
						installation with the specified table prefix</strong>.  However there is a OpenDb $db_version installation in this 
						database without any table prefixing.</p>");
				}
				else if($db_status === 'OPENDB_DATABASE_WITH_MULTIPLE_PREFIXES_EXISTS')
				{
					echo("<p>The database you have specified already exists.  <strong>This database does not have an existing OpenDb 
						installation with the specified table prefix</strong>.  However there does appear to be multiple installations of
						OpenDb with different prefixes.  You may want to consider revising your Table Prefix.</p>");
				}
				else if($db_status === 'OPENDB_DATABASE_WITH_PREFIX_EXISTS')
				{
					echo("<p>The database you have specified already exists.  <strong>This database does not have an existing OpenDb 
						installation with the specified table prefix</strong>.  However there does appear to be a installation of
						OpenDb with a prefix of <strong>".$db_details_r['table_prefix']."</strong></p>");
				}
				else if($db_status === 'OPENDB_DATABASE_EXISTS')
				{
					echo("<p>The database you have specified already exists.  There is an existing OpenDb 
						$db_version installation".(strlen($db_details_r['table_prefix'])>0?" with the specified table prefix":"").".</p>");
				}
				
				echo("\n<form action=\"$PHP_SELF\" method=\"POST\">");
				echo(get_url_fields($HTTP_VARS, array('confirmed'=>'false')));
				echo("\n<input type=\"button\" value=\" Go Back \" onclick=\"this.form['confirmed'].value='false'; this.form.submit();\">
					<input type=\"button\" value=\" Continue \" onclick=\"this.form['confirmed'].value='true'; this.form.submit();\">");
				echo("</form>\n");
			}
			else
			{
			  	echo install_opendb_user_and_database_form($HTTP_VARS, $errors);
			}
		}
		else if($db_created === FALSE)
		{
			echo install_opendb_user_and_database_form($HTTP_VARS, $errors);  	
		}
		
		// if return val is now TRUE, continue
		if($db_created === TRUE)
		{
			if(install_write_config_file($db_details_r, $config_file_contents, $errors))
			{
				echo("<p>OpenDb configuration was written to <code>./include/local.config.php</code></p>");
				
				echo("\n<form action=\"$PHP_SELF\" method=\"GET\">");
				echo("<input type=\"hidden\" name=\"step\" value=\"pre-install\">\n");
				echo("<input type=\"button\" value=\"Next\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">\n");
				echo("</form>");
			}
			else
			{
				echo("<p class=\"error\">OpenDb configuration was not written to <code>./include/local.config.php</code></p>");
				
				if(is_array($errors))
				{
					echo("<ul class=\"error\">");
					while( list(,$error) = each($errors) )
					{
						echo("<li>$error</li>");
					}
					echo("</ul>");
				}
				
				echo("\n<form action=\"$PHP_SELF\" method=\"POST\">");
				echo(get_url_fields($HTTP_VARS));
				echo("\n<input type=\"button\" value=\"Retry\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">");
				echo("</form>\n");
				
				echo("<p>If you cannot resolve the issue, you can save the configuration file manually.  The contents of the textarea should be saved to  
				<code>./include/local.config.php</code>.  Once this is done click <strong>Retry</strong>.");
				
				echo("<form>");
				echo("<textarea rows=\"12\" cols=\"100\">".htmlspecialchars($config_file_contents)."</textarea>");
				echo("</form>");
			}
		}
		
		echo install_footer();
	}
	else
	{
		// if the s_opendb_release table does not exist, we need to install it, and populate with
		// a specific version record.
			
		echo install_header('OpenDb Installation', FALSE);
		echo("<h1>OpenDb ".get_opendb_version()." Installation</h1>\n");

		echo("<h2>Pre Installation</h2>");
		
		$preinstall_details = NULL;
		$db_version = NULL;

		if(!check_opendb_table('s_opendb_release') || 
						count_opendb_table_rows('s_opendb_release')==0 || 
						count_opendb_table_columns('s_opendb_release') != 6)
		{
			if(!check_opendb_table('s_opendb_release') || count_opendb_table_columns('s_opendb_release') != 6)
			{
				if(exec_install_sql_file('./install/upgrade/0.81/s_opendb_release.sql', $errors))
				{
					$preinstall_details[] = 'OpenDb release table created';	
				}
			}
			
	  		if(is_opendb_partially_installed())
	  		{
				$db_version = install_determine_opendb_database_version();
				
				if($db_version !== FALSE)
				{
					// we are inserting a placeholder record for whatever the users current version is, so we can proceed with
					// any upgrades.  Its NULL already, because there are no steps for the old install, but the nominated_version
					// information will be used by installer for any additional upgrades.
					if(insert_opendb_release($db_version, 'New Install', NULL)!==FALSE)
					{
			  			$preinstall_details[] = 'Inserted OpenDb release record (Version '.$db_version.')';
					}
					else
					{
			  			$errors[] = 'Failed to insert OpenDb release record (Version '.$db_version.')';
					}
				}
			}
		}
		
		// Display results of all previous operations
		if(is_array($preinstall_details))
		{
			echo("The following pre-install steps were completed successfully:");
			echo("<ul>");
			for($i=0; $i<count($preinstall_details); $i++)
			{
			  	echo("<li>".$preinstall_details[$i]."</li>");
			}
			echo("</ul>");
		}
	
		if(is_array($errors))
		{
		  	echo("<p class=\"error\">The following pre-install errors occurred: </p>");
			echo format_error_block($errors);	
		}

		if($db_version!==FALSE)
		{
		    echo("\n<form action=\"$PHP_SELF\" method=\"GET\">");
		    
		    if(check_opendb_table('s_opendb_release') && 
		    			count_opendb_table_columns('s_opendb_release') == 6 && 
		    			(!is_opendb_partially_installed() || count_opendb_table_rows('s_opendb_release')>0))
			{
		    	echo("<input type=\"hidden\" name=\"step\" value=\"install\">\n");
		
				echo("<p>Pre install checks have been completed.  Your OpenDb database will now be checked to see if upgrades are required.</p>");
			}
			else
			{
				echo("<input type=\"hidden\" name=\"step\" value=\"pre-install\">\n");
			}
			
			echo("<input type=\"button\" value=\"Next\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">\n");
				
			echo("</form>");
		}
		else
		{
			echo("<p class=\"error\">There is no support to upgrade from versions of OpenDb prior to 0.80.  You will have to
				download and install an older version and upgrade to at least 0.80 first.</p>");
		}
		echo install_footer();
	}
}
else
{ 
	@set_time_limit(600);
	
	echo install_header('OpenDb Installation', FALSE);
	echo("<h1>OpenDb ".get_opendb_version()." Installation</h1>\n");
	
	$is_new_install = FALSE;
	
	if(is_db_connected() && 
				check_opendb_table('s_opendb_release') && count_opendb_table_columns('s_opendb_release') == 6 && 
				// require a release record if opendb database has tables other than s_opendb_release already!
				(!is_opendb_partially_installed() || count_opendb_table_rows('s_opendb_release')>0))
	{
		if(is_opendb_partially_installed())
		{
		  	// upgrade
		    if(!check_opendb_version())
			{
				$result = install_opendb_upgrade($HTTP_VARS, $errors);
				
				if($result == FALSE)
				{
				  	if(is_array($errors))
					{
					  	echo("<p class=\"error\">The following upgrade errors occurred: </p>");
						echo format_error_block($errors);	
					}
				}
				else if($result === TRUE && !check_opendb_version())
				{
					echo("The upgrade was completed successfully.  There are still additional upgrades required.</p>");
					echo("\n<form action=\"$PHP_SELF\" method=\"GET\">");
					echo("<input type=\"hidden\" name=\"step\" value=\"upgrade\">\n");
					echo("<input type=\"button\" value=\"Next\" onclick=\"this.value='Working...'; this.disabled=true; this.form.submit(); return true;\">\n");
					echo("</form>");	
				}
			}
		}//if(is_opendb_partially_installed())
		else
		{
			$is_new_install = TRUE;
			
		  	echo("<h2>New Install</h2>");
		  	
			if(install_opendb_new_install($HTTP_VARS, $errors))
			{
				// do nothing
			}
			else
			{
				if(is_array($errors))
				{
				  	echo("<p class=\"error\">The following install errors occurred: </p>");
					echo format_error_block($errors);	
				}
			}
		}
		
		if(is_opendb_partially_installed() && check_opendb_version())
		{
			echo("Your OpenDb installation is up to date.");

			// do not show this section unless a new install
			if($is_new_install)
			{
				echo("<p class=\"installwarning\">A 'admin' user (Password: admin) has been created for you, however you must change the admin user <strong>password</strong> and <strong>email address</strong> immediately.  The admin
				email address will receive all administrative email, so its important to set it to an email address you can access.</p>");
			
				echo("<p><strong>The following additional steps are required for OpenDb to function correctly:</strong>
				<ul>
				<li>Install required Item Types via the Item Types system admin tool.  A new install of OpenDb is not setup with any Item Types by default.</li>
				<li>Install required Site Plugins via the Site Plugins system admin tool.  A new install of OpenDb is not setup with any Site Plugins by default.</li>
				</ul>");
			}
			
			echo("<p><a href=\"login.php\">Login to OpenDb</a></p>");
		}
	}//database exists, and opendb release table has at least 1 record in it
	echo install_footer();
}

// Cleanup after begin.inc.php
require_once("./include/end.inc.php");
?>	