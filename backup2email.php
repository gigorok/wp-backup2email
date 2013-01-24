<?php
/*
Plugin Name: Backup2Email
Plugin URI: http://gigorok.name/backup2email/
Description: This plugin make backups for your blog. Include files and database.
Version: 1.0
Author: gigorok
Author URI: http://gigorok.name/
*/
/*  Copyright 2012  gigorok  (email: gigorok@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once dirname(__FILE__).'/zip.php';
require_once dirname(__FILE__).'/../../../wp-includes/pluggable.php';

add_action('admin_menu', 'custom_code_add_admin'); 

$plugname = "Backup your blog to Email";
$shortname = "backup2email";
$plugoptions = array (
	array(
		"name" => "Allow backup files",
		"id" => $shortname."_backup_files",
		"type" => "checkbox"
	),    
	array(
		"name" => "Allow backup database",
	    "id" => $shortname."_backup_database",
	    "type" => "checkbox"
	),
	array(
		"name" => "Administrator e-mail",
	    "id" => $shortname."_admin_email",
	    "type" => "text",
	    "placeholder" => "user@example.com"
	),
	array(
		"name" => "Allow backup periodically",
	    "id" => $shortname."_backup_periodically",
	    "type" => "selectbox",
	    "options" => array(
	    	"" => "None",
	    	"day" => "Daily",
	    	"week" => "Weekly",
	    	"month" => "Monthly"
	    )
	)               
);

function custom_code_add_admin() {
	global $plugname, $shortname, $plugoptions;

	if (isset($_POST['save'])) {
		foreach ($plugoptions as $value) {
			update_option($value['id'], $_REQUEST[$value['id']]);
		}

		echo '<div id="message" class="updated fade"><p><strong>Settigs successfully updated</strong></p></div>';

	} elseif (isset($_POST['reset'])) {
		foreach ($plugoptions as $value)
			delete_option( $value['id'] );
		echo '<div id="message" class="updated fade"><p><strong>Settigs successfully reset</strong></p></div>';
	} elseif (isset($_POST['backupnow'])) {
		if(backupnow($error))
			echo '<div id="message" class="updated fade"><p><strong>Message successfully sent.</strong></p></div>';
		else
			echo '<div id="error" class="updated fade"><p><strong>Message not sent! '.$error.'</strong></p></div>';
	}

	add_options_page($plugname, "Setup Backup2Email", 'edit_themes', basename(__FILE__), 'custom_code_to_admin');
}

function custom_code_to_admin() {
    global $plugname, $shortname, $plugoptions;
    define('BACKUP2EMAIL', true);
    include "settings_template.php";
}

function getTableCreate( )
{
	global $wpdb;
	$result = $wpdb->get_results("SHOW TABLES" , ARRAY_N);
	$return = array();
	foreach ($result as $table) {
		$res = $wpdb->get_results("SHOW CREATE TABLE " . $table[0], ARRAY_N);
		$return[$table[0]] = $res[0][1];
	}
	return $return;
}

function getTableFields($tblval) {
	global $wpdb;
	$result = array();

	$fields = $wpdb->get_results('SHOW FIELDS FROM ' . $tblval , ARRAY_A);

	foreach ($fields as $field) {
		$result[$tblval][$field['Field']] = preg_replace("/[(0-9)]/",'', $field['Type'] );
	}

	return $result;
}

function backupdb($folder) {
	global $wpdb;
	$folder .= '/';
	$filename = $wpdb->dbname.".sql";

	$handle = fopen($folder . $filename, "w+");

	$createTablesSQL = getTableCreate();
	foreach ($createTablesSQL as $key => $value) {
		
	fwrite($handle, 'DROP TABLE IF EXISTS `'.$key.'`;'.PHP_EOL); // write
    fwrite($handle, PHP_EOL.'--'.PHP_EOL.'-- Table structure for table `'.$key.'`'.PHP_EOL.'--'.PHP_EOL); // write
    fwrite($handle, $value . ';'.PHP_EOL); // write

    $tableFields = getTableFields($key);

    $data = $wpdb->get_results('SELECT * FROM `'.$key.'`' , ARRAY_N);

	fwrite($handle, '--'.PHP_EOL.'-- Dumping data for table `'.$key.'`'.PHP_EOL.'--'.PHP_EOL); // write
 	
	if(sizeof($data)) {
		fwrite($handle, 'LOCK TABLES `'.$key.'` WRITE;'.PHP_EOL); // write
    	$fields = array_keys($tableFields);
    	fwrite($handle, 'INSERT INTO `'.$key.'` (`'.implode("`, `", array_keys($tableFields[$fields[0]])).'`) VALUES '.PHP_EOL); // write

		foreach ($data as $row) {
		   foreach ($row as $k => $v){
	        	if(!isset($v))
	            	$row[$k] = 'NULL';
				else
	            	$row[$k] = "'".addslashes($v)."'";
		        }
				fwrite($handle, "\t(".implode(", ", $row)."),".PHP_EOL);
			}
			fseek($handle, -2, SEEK_CUR); // delete coma
			fwrite($handle, ';'.PHP_EOL.'UNLOCK TABLES;'.PHP_EOL.PHP_EOL); // write
		}
	}
	fclose($handle);
	
	Zip($folder . $filename, dirname(__FILE__) . '/backups/'.$wpdb->dbname.'.zip');
	unlink($folder.$filename); // remove sql file

}

function backupfiles($folder) {
	Zip(dirname(__FILE__) . '/../../../', dirname(__FILE__) . '/backups/files.zip');
}

function backupnow(&$error) {
	global $shortname, $wpdb;
	$path = dirname(__FILE__) . '/backups/';
	if(!is_writable(dirname(__FILE__) . '/backups/')) {
		$error = 'Folder '.$path.' must be writable';
		return false;
	}
	//
	if(get_option($shortname . '_backup_database'))
		backupdb($path);
	if(get_option($shortname . '_backup_files'))
		backupfiles($path);

	$attachments = array();
	if(get_option($shortname . '_backup_database'))
		$attachments[] = dirname(__FILE__) . '/backups/'.$wpdb->dbname.'.zip';
	if(get_option($shortname . '_backup_files'))
		$attachments[] = dirname(__FILE__) . '/backups/files.zip';

	$result = wp_mail(
		get_option($shortname . '_admin_email'), 
		'Backup2Email. Backup blog "'.get_bloginfo('name') . '" at ' . date('Y-m-d H:i'), 
		'You can find your blog\'s backups in attachments. 
You may setup your Backup2Email plugin to the link '.get_bloginfo('url').'/wp-admin/options-general.php?page=backup2email.php', 
		'', 
		$attachments);
	if($result) {
		
		foreach ($attachments as $file) {
			unlink($file);
		}
		if(get_option('last_backup_date'))
			update_option('last_backup_date', date('Y-m-d H:i:s'));
		else
			add_option('last_backup_date', date('Y-m-d H:i:s'));

		return true;
	} else {
		return false;
	}
}

// cron task
$periodically = get_option($shortname."_backup_periodically");
$last_backup_date = get_option('last_backup_date');
if($last_backup_date) {
	add_option('last_backup_date', date('Y-m-d H:i:s'));
}
if($periodically) {
	if(strtotime($last_backup_date . " +1 ".$periodically) <= time()) {
		backupnow($error);
	}
}
