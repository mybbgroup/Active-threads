<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

define('C_PRT', str_replace('.php', '', basename(__FILE__)));

function popular_recent_threads_info() {
	global $lang, $db, $mybb;

	if (!isset($lang->popular_recent_threads)) {
		$lang->load(C_PRT);
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

function popular_recent_threads_install() {
	global $mybb, $db, $lang;

	$lang->load(C_PRT);

	$res = $db->simple_select('settinggroups', 'MAX(disporder) as max_disporder');
	$disporder = $db->fetch_field($res, 'max_disporder') + 1;

	// Insert the plugin's settings into the database.
	$setting_group = array(
		'name'         => C_PRT.'_settings',
		'title'        => $db->escape_string($lang->prt_name),
		'description'  => $db->escape_string($lang->prt_desc),
		'disporder'    => intval($disporder),
		'isdefault'    => 0
	);
	$db->insert_query('settinggroups', $setting_group);
	$gid = $db->insert_id();

	$settings = array(
		'max_interval_in_secs' => array(
			'title'       => $lang->prt_max_interval_in_secs_title,
			'description' => $lang->prt_max_interval_in_secs_desc,
			'optionscode' => 'numeric',
			'value'       => '0'
		),
	);

	$x = 1;
	foreach ($settings as $name => $setting) {
		$insert_settings = array(
			'name' => $db->escape_string(C_PRT.'_'.$name),
			'title' => $db->escape_string($setting['title']),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value' => $db->escape_string($setting['value']),
			'disporder' => $x,
			'gid' => $gid,
			'isdefault' => 0
		);
		$db->insert_query('settings', $insert_settings);
		$x++;
	}

	// Insert the plugin's templates into the database.
	$templateset = array(
		'prefix' => 'popularrecentthreads',
		'title' => 'Popular Recent Threads',
	);
	$db->insert_query('templategroups', $templateset);

	$templates = array(
		'popularrecentthreads_page'
			=> '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->prt_pop_recent_threads_title_short}</title>
{$headerinclude}
<style type="text/css">
table, td, th {
	text-align: center;
	border-spacing: 0;
}
</style>
</head>
<body>
{$header}
<form method="get" action="popular_recent_threads.php">
<table class="tborder tfixed clear">
	<thead>
		<tr>
			<th class="thead" colspan="6" title="{$prt_set_period_of_interest_tooltip}">{$prt_set_period_of_interest}</td>
		</tr>
		<tr>
			<th class="tcat">{$lang->prt_num_days}</th>
			<th class="tcat">{$lang->prt_num_hours}</th>
			<th class="tcat">{$lang->prt_num_mins}</th>
			<th class="tcat">{$lang->prt_num_secs}</th>
			<th class="tcat" title="{$prt_before_date_tooltip}">{$lang->prt_before_date}</th>
			<th class="tcat"></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><input type="text" name="days" value="$days" style="text-align: right;"/></td>
			<td><input type="text" name="hours" value="$hours" style="text-align: right;" /></td>
			<td><input type="text" name="mins" value="$mins" style="text-align: right;" /></td>
			<td><input type="text" name="secs" value="$secs" style="text-align: right;" /></td>
			<td><input type="text" name="date" value="$date" style="text-align: right;" title="{$prt_before_date_tooltip}" /></td>
			<td><input type="submit" name="go" value="{$lang->prt_go}" class="button" /></td>
		</tr>
	</tbody>
</table>
</form>
{$multipage}
{$results_html}
{$multipage}
{$footer}
</body>
</html>',
		'popularrecentthreads_result_row' => '
	<tr class="inline_row">
		<td class="$bgcolor forumdisplay_regular" style="text-align: left;">$thread_link<div class="smalltext"><span class="author">$thread_username_link</span> <span style="float: right;">$thread_date</span></div></td>
		<td class="$bgcolor">$num_posts_fmt</td>
		<td class="$bgcolor">$forum_links</td>
		<td class="$bgcolor" style="text-align: right;">$min_post_date_link<div class="smalltext"><span class="author">$min_post_username_link</span></div></td>
		<td class="$bgcolor" style="text-align: right;">$max_post_date_link<div class="smalltext"><span class="author">$max_post_username_link</span></div></td>
	</tr>',
		'popularrecentthreads_results' =>
'<table class="tborder tfixed clear">
<thead>
	<tr>
		<th class="thead" colspan="5">{$lang_pop_recent_threads_title}</td>
	</tr>
	<tr>
		<th class="tcat" style="text-align: left;">{$lang->prt_thread_author_start}</th>
		<th class="tcat">{$lang->prt_num_posts}</th>
		<th class="tcat">{$lang->prt_cont_forum}</th>
		<th class="tcat" style="text-align: right;">{$lang->prt_earliest_posting}</th>
		<th class="tcat" style="text-align: right;">{$lang->prt_latest_posting}</th>
	</tr>
</thead>
<tbody>
{$result_rows}
</tbody>
</table>',
	);

	$info = popular_recent_threads_info();
	$plugin_version_code = $info['version_code'];
	// Left-pad the version code with any zero that we forbade in popular_recent_threads_info(),
	// where it would have been interpreted as octal.
	while (strlen($plugin_version_code) < 6) {
		$plugin_version_code = '0'.$plugin_version_code;
	}

	// Insert templates into the Master group (sid=-2) with a (string) version set to a value that
	// will compare greater than the current MyBB version_code. We set the version to this value so that
	// the SQL comparison "m.version > t.version" in the query to find updated templates
	// (in admin/modules/style/templates.php) is true for templates modified by the user:
	// MyBB sets the version for modified templates to the value of $mybb->version_code.
	$version = substr($mybb->version_code.'_'.$plugin_version_code, 0, 20);
	foreach ($templates as $template_title => $template_data) {
		$insert_templates = array(
			'title'    => $db->escape_string($template_title),
			'template' => $db->escape_string($template_data),
			'sid'      => '-2',
			'version'  => $version,
			'dateline' => TIME_NOW
		);
		$db->insert_query('templates', $insert_templates);
	}
}

function popular_recent_threads_uninstall() {
	global $db;

	$db->delete_query('templates', "title LIKE 'popularrecentthreads_%'");
	$db->delete_query('templategroups', "prefix in ('popularrecentthreads')");

	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_PRT."_settings'", array('limit' => 1));
	$group = $db->fetch_array($res);
	if (!empty($group['gid'])) {
		$db->delete_query('settinggroups', "gid='{$group['gid']}'");
		$db->delete_query('settings', "gid='{$group['gid']}'");
		rebuild_settings();
	}
}

function popular_recent_threads_is_installed() {
	global $db;

	$res = $db->simple_select('templates', '*', "title LIKE 'popularrecentthreads_%'");
	return ($db->affected_rows() > 0);
}
