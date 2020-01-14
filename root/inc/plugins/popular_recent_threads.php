<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

define('C_PRT', str_replace('.php', '', basename(__FILE__)));

function popular_recent_threads_info() {
	global $lang, $db, $mybb;

	if (!isset($lang->popular_recent_threads)) {
		$lang->load('popular_recent_threads');
	}

	$ret = array(
		'name'          => $lang->prt_name,
		'description'   => $lang->prt_desc,
		'website'       => '',
		'author'        => 'Laird Shaw',
		'authorsite'    => '',
		'version'       => '0.0.1',
		// Constructed by converting each digit of 'version' above into two digits (zero-padded if necessary),
		// then concatenating them, then removing any leading zero(es) to avoid the value being interpreted as octal.
		'version_code'  => '1',
		'guid'          => '',
		'codename'      => C_PRT,
		'compatibility' => '18*'
	);

	return $ret;
}
