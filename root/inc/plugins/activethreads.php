<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.
 * If not, see <http://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

define('C_ACT', str_replace('.php', '', basename(__FILE__)));

if (defined('IN_ADMINCP')) {
	$plugins->add_hook('admin_formcontainer_end'             , 'act_hookin__limits_usergroup_permission'       );
	$plugins->add_hook('admin_user_groups_edit_commit'       , 'act_hookin__limits_usergroup_permission_commit');
	$plugins->add_hook('admin_config_plugins_activate_commit', 'act_hookin__plugins_activate_commit'           );
} else	$plugins->add_hook('global_start'                        , 'activethreads_hookin__global_start'            );

function activethreads_hookin__global_start() {
	global $lang;

	// Load the language file so that the 'act_view_act_thr' message is available for the 'header_welcomeblock_guest' template
	// on every page.
	$lang->load(C_ACT);
}

function activethreads_info() {
	global $lang, $db, $mybb, $plugins_cache, $admin_session;

	if (!isset($lang->activethreads)) {
		$lang->load(C_ACT);
	}

	$ret = array(
		'name'          => $lang->act_name,
		'description'   => $lang->act_desc,
		'website'       => 'https://github.com/lairdshaw/MyBB-active-threads-plugin',
		'author'        => 'Laird Shaw',
		'authorsite'    => 'https://github.com/lairdshaw',
		'version'       => '1.2.10',
		// Constructed by converting each component of 'version' above into two digits (zero-padded if necessary),
		// then concatenating them, then removing any leading zero(es) to avoid the value being interpreted as octal.
		'version_code'  => '10210',
		'guid'          => '',
		'codename'      => C_ACT,
		'compatibility' => '18*'
	);

	if (is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active'][C_ACT]) {
		if (!empty($admin_session['data']['act_plugin_info_upgrade_message'])) {
			$msg = $admin_session['data']['act_plugin_info_upgrade_message'].' '.$msg;
			update_admin_session('act_plugin_info_upgrade_message', '');
			$ret['description'] = "<ul><li style=\"list-style-image: url(styles/default/images/icons/success.png)\"><div class=\"success\">$msg</div></li></ul>".PHP_EOL.$ret['description'];
		}
	}

	$ret['description'] .= <<<EOF
<div style="float: right;">
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick" />
<input type="hidden" name="hosted_button_id" value="UDYQF5HJKQDUU" />
<input type="image" src="https://www.paypalobjects.com/en_AU/i/btn/btn_donate_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_AU/i/scr/pixel.gif" width="1" height="1" />
</form>
</div>
EOF;

	return $ret;
}

function activethreads_install() {
	global $mybb, $db, $lang, $cache;

	$lang->load(C_ACT);

	$res = $db->simple_select('settinggroups', 'MAX(disporder) as max_disporder');
	$disporder = $db->fetch_field($res, 'max_disporder') + 1;

	// Insert the plugin's settings into the database.
	$setting_group = array(
		'name'         => C_ACT.'_settings',
		'title'        => $db->escape_string($lang->act_name),
		'description'  => $db->escape_string($lang->act_desc),
		'disporder'    => intval($disporder),
		'isdefault'    => 0
	);
	$db->insert_query('settinggroups', $setting_group);

	act_update_create_settings();

	// Insert the plugin's templates into the database.
	$templateset = array(
		'prefix' => 'activethreads',
		'title' => 'Active Threads',
	);
	$db->insert_query('templategroups', $templateset);

	act_install_upgrade_common();

}

function activethreads_uninstall() {
	global $db, $cache;

	$db->delete_query('templates', "title LIKE 'activethreads_%'");
	$db->delete_query('templategroups', "prefix in ('activethreads')");

	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_ACT."_settings'", array('limit' => 1));
	$group = $db->fetch_array($res);
	if (!empty($group['gid'])) {
		$db->delete_query('settinggroups', "gid='{$group['gid']}'");
		$db->delete_query('settings', "gid='{$group['gid']}'");
		rebuild_settings();
	}

	if ($db->field_exists('act_max_interval_in_mins', 'usergroups')) {
		$db->drop_column('usergroups', 'act_max_interval_in_mins');
	}

	$lrs_plugins = $cache->read('lrs_plugins');
	unset($lrs_plugins[C_ACT]);
	$cache->update('lrs_plugins', $lrs_plugins);
}

function activethreads_is_installed() {
	global $db;

	$res = $db->simple_select('templates', '*', "title LIKE '".C_ACT."_%'");
	return ($db->affected_rows() > 0);
}

function act_upgrade($old_version_code) {
	global $db;

	// Transition renamed template
	$db->update_query('templates', array('title' => 'activethreads_threadauthor_avatar'), "title='activethreads_threadstarter_avatar'");

	// Update the master templates.
	act_install_upgrade_common($old_version_code);

	// Save existing values for the plugin's settings.
	$existing_setting_values = array();
	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_ACT."_settings'", array('limit' => 1));
	$group = $db->fetch_array($res);
	if (!empty($group['gid'])) {
		$res = $db->simple_select('settings', 'value, name', "gid='{$group['gid']}'");
		while ($setting = $db->fetch_array($res)) {
			$existing_setting_values[$setting['name']] = $setting['value'];
		}
	}

	act_update_create_settings($existing_setting_values);
}


function act_update_create_settings($existing_setting_values = array()) {
	global $db, $lang;

	$lang->load(C_ACT);

	// Get the group ID for activethreads settings.
	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_ACT."_settings'", array('limit' => 1));
	$row = $db->fetch_array($res);
	$gid = $row['gid'];

	// Delete existing activethreads settings, without deleting their group
	$db->delete_query('settings', "gid='{$gid}'");

	// The settings to (re)create in the database.
	$settings = array(
		'max_interval_in_mins' => array(
			'title'       => $lang->act_max_interval_in_mins_title,
			'description' => $lang->act_max_interval_in_mins_desc,
			'optionscode' => 'numeric',
			'value'       => '0'
		),
		'per_usergroup' => array(
			'title'       => $lang->act_per_usergroup_title,
			'description' => $lang->act_per_usergroup_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'default_days' => array(
			'title'       => $lang->act_default_days_title,
			'description' => $lang->act_default_days_desc,
			'optionscode' => 'numeric',
			'value'       => '7'
		),
		'default_hours' => array(
			'title'       => $lang->act_default_hours_title,
			'description' => $lang->act_default_hours_desc,
			'optionscode' => 'numeric',
			'value'       => '0'
		),
		'default_mins' => array(
			'title'       => $lang->act_default_mins_title,
			'description' => $lang->act_default_mins_desc,
			'optionscode' => 'numeric',
			'value'       => '0'
		),
		'default_sort_field' => array(
			'title'       => $lang->act_default_sort_field_title,
			'description' => $lang->act_default_sort_field_desc,
			'optionscode' => "select\nnum_posts={$lang->act_default_sort_field_num_posts}\nmin_dateline={$lang->act_default_sort_field_date_earliest}\nmax_dateline={$lang->act_default_sort_field_date_latest}\nthread_subject={$lang->act_default_sort_field_thread_subject}\nthread_username={$lang->act_default_sort_field_thread_username}\nthread_dateline={$lang->act_default_sort_field_thread_dateline}\nforum_name={$lang->act_default_sort_field_forum_name}\nmin_username={$lang->act_default_sort_field_min_username}\nmax_username={$lang->act_default_sort_field_max_username}",
			'value'       => 'num_posts'
		),
		'default_sort_direction' => array(
			'title'       => $lang->act_default_sort_direction_title,
			'description' => $lang->act_default_sort_direction_desc,
			'optionscode' => "select\nascending={$lang->act_ascending}\ndescending={$lang->act_descending}",
			'value'       => 'descending'
		),
		'display_thread_avatar' => array(
			'title'       => $lang->act_display_thread_avatar_title,
			'description' => $lang->act_display_thread_avatar_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'display_earliestpost_avatar' => array(
			'title'       => $lang->act_display_earliestpost_avatar_title,
			'description' => $lang->act_display_earliestpost_avatar_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'display_latestpost_avatar' => array(
			'title'       => $lang->act_display_latestpost_avatar_title,
			'description' => $lang->act_display_latestpost_avatar_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'format_threadauthor_username' => array(
			'title'       => $lang->act_format_threadauthor_username_title,
			'description' => $lang->act_format_threadauthor_username_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'format_earliestposter_username' => array(
			'title'       => $lang->act_format_earliestposter_username_title,
			'description' => $lang->act_format_earliestposter_username_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'format_latestposter_username' => array(
			'title'       => $lang->act_format_latestposter_username_title,
			'description' => $lang->act_format_latestposter_username_desc,
			'optionscode' => 'yesno',
			'value'       => '0'
		),
		'max_displayed_subject_chars' => array(
			'title'       => $lang->act_max_displayed_subject_chars_title,
			'description' => $lang->act_max_displayed_subject_chars_desc,
			'optionscode' => 'numeric',
			'value'       => 0,
		),
	);

	if (isset($existing_setting_values[C_ACT.'_max_interval_in_secs']) && !isset($existing_setting_values[C_ACT.'_max_interval_in_mins'])) {
		$settings['max_interval_in_mins']['value'] = floor($existing_setting_values[C_ACT.'_max_interval_in_secs'] / 60);
	}

	// (Re)create the settings, retaining the old values where they exist.
	$x = 1;
	foreach ($settings as $name => $setting) {
		$value = isset($existing_setting_values[C_ACT.'_'.$name]) ? $existing_setting_values[C_ACT.'_'.$name] : $setting['value'];
		$insert_settings = array(
			'name' => $db->escape_string(C_ACT.'_'.$name),
			'title' => $db->escape_string($setting['title']),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value' => $value,
			'disporder' => $x,
			'gid' => $gid,
			'isdefault' => 0
		);
		$db->insert_query('settings', $insert_settings);
		$x++;
	}

	rebuild_settings();
}

function act_install_upgrade_common($old_version_code = null, $new_version_code = null) {
	global $mybb, $db, $lang, $cache, $groupscache;

	$templates = array(
		'activethreads_page' => array(
			'template_data'       => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->act_act_recent_threads_title_short}</title>
{$headerinclude}
<style type="text/css">
table, td, th {
	text-align: center;
	border-spacing: 0;
}
</style>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/activethreads.js"></script>
</head>
<body>
{$header}
<a href="misc.php?action=markread{$post_code_string}" class="smalltext">{$lang->act_mark_all_read}</a>
{$multipage}
{$results_html}
{$multipage}

<form method="get" action="activethreads.php">
<table class="tborder clear">
<thead>
	<tr>
		<th class="thead" colspan="5" title="{$act_set_period_of_interest_tooltip}">{$act_set_period_of_interest}</th>
	</tr>
	<tr>
		<th class="tcat"><span class="smalltext">{$lang->act_num_days}</span></th>
		<th class="tcat"><span class="smalltext">{$lang->act_num_hours}</span></th>
		<th class="tcat"><span class="smalltext">{$lang->act_num_mins}</span></th>
		<th class="tcat" title="{$act_before_date_tooltip}"><span class="smalltext">{$lang->act_before_date} [*]</span></th>
		<th class="tcat"><span class="smalltext">{$lang->act_sort_by}</span></th>
	</tr>
</thead>
<tbody>
	<tr>
		<td><input type="text" name="days" value="{$days}" size="5" style="text-align: right;"/></td>
		<td><input type="text" name="hours" value="{$hours}" size="5" style="text-align: right;" /></td>
		<td><input type="text" name="mins" value="{$mins}" size="5" style="text-align: right;" /></td>
		<td><input type="text" name="date" value="{$date}" size="16" style="text-align: right;" title="{$act_before_date_tooltip}" /></td>
		<td>
			<input type="radio" name="order" value="ascending" id="sort.asc"{$asc_checked} /><label for ="sort.asc">{$lang->act_asc}</label><br />
			<input type="radio" name="order" value="descending" id="sort.desc"{$desc_checked} /><label for ="sort.desc">{$lang->act_desc}</label>
			<br />
			<select name="sort">
				<option value="num_posts"{$num_posts_selected}>{$lang->act_sort_by_num_posts}</option>
				<option value="min_dateline"{$min_dateline_selected}>{$lang->act_sort_by_earliest}</option>
				<option value="max_dateline"{$max_dateline_selected}>{$lang->act_sort_by_latest}</option>
				<option value="thread_subject"{$thread_subject_selected}>{$lang->act_sort_by_thread_subject}</option>
				<option value="thread_username"{$thread_username_selected}>{$lang->act_sort_by_thread_username}</option>
				<option value="thread_dateline"{$thread_dateline_selected}>{$lang->act_sort_by_thread_dateline}</option>
				<option value="forum_name"{$forum_name_selected}>{$lang->act_sort_by_forum_name}</option>
				<option value="min_username"{$min_username_selected}>{$lang->act_sort_by_min_username}</option>
				<option value="max_username"{$max_username_selected}>{$lang->act_sort_by_max_username}</option>
			</select>
		</td>
	</tr>
</tbody>
</table>
<p style="text-align: center">[*] {$lang->act_before_date_footnote}<br /><input type="submit" name="go" value="{$lang->act_go}" class="button" /></p>
</form>
{$footer}
</body>
</html>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_result_row' => array(
			'template_data'       => '
	<tr class="inline_row">
		<td align="center" class="{$bgcolor}" width="2%"><span class="thread_status {$folder}" title="{$folder_label}">&nbsp;</span></td>
		<td align="center" class="{$bgcolor}" width="2%">{$icon}</td>
		<td class="{$bgcolor} forumdisplay_regular" style="text-align: left;"><div style="float: left;">{$threadauthor_avatar}</div><div style="margin-left: {$margin_thread}px;">{$prefix} {$gotounread}{$threadprefix_disp}<span class="{$new_class}">{$thread_link}</span><div><span class="smalltext author">{$threadauthor_link}</span> <span class="smalltext" style="float: right;">{$thread_date}</span></div></div></td>
		<td class="{$bgcolor}"><a href="{$mybb->settings[\'bburl\']}/activethreads.php?action=whoposted&amp;tid={$tid}&amp;min_dateline={$row[\'min_dateline\']}&amp;max_dateline={$row[\'max_dateline\']}" onclick="activethreads_whoPosted({$tid}, {$row[\'min_dateline\']}, {$row[\'max_dateline\']}); return false;">{$num_posts_fmt}</a></td>
		<td class="{$bgcolor}" style="text-align: left;">{$forum_links}</td>
		<td class="{$bgcolor}" style="text-align: right;"><div style="float: right">{$earliestpost_avatar}</div><div style="margin-right: {$margin_earliest}px;">{$earliestpost_date_link}<div class="smalltext"><span class="author">{$earliestposter_username_link}</span></div></div></td>
		<td class="{$bgcolor}" style="text-align: right;"><div style="float: right">{$latestpost_avatar}</div><div style="margin-right: {$margin_latest}px;">{$latestpost_date_link}<div class="smalltext"><span class="author">{$latestposter_username_link}</span></div></div></td>
	</tr>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_results' => array(
			'template_data'       =>
'<table class="tborder clear">
<thead>
	<tr>
		<th class="thead" colspan="7">{$lang_act_recent_threads_title}</th>
	</tr>
	<tr>
		<th class="tcat" style="text-align: left;" colspan="3"><span class="smalltext">{$thread_subject_heading} / {$thread_username_heading} / {$thread_dateline_heading}</span></th>
		<th class="tcat"><span class="smalltext">{$num_posts_heading}</span></th>
		<th class="tcat"><span class="smalltext">{$forum_name_heading}</span></th>
		<th class="tcat" style="text-align: right;"><span class="smalltext">{$min_dateline_heading} / {$min_author_heading}</span></th>
		<th class="tcat" style="text-align: right;"><span class="smalltext">{$max_dateline_heading} / {$max_author_heading}</span></th>
	</tr>
</thead>
<tbody>
{$result_rows}
</tbody>
</table>',
			'version_at_last_mod' => 10206,
		),
		// Largely copied from core code's misc_whoposted but with support added for a date range
		// by calling activethreads_whoPosted() when sorting rather than MyBB.whoPosted().
		'activethreads_whoposted' => array(
			'template_data'       => '<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">
<table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" border="0" align="center" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->total_posts} {$numposts}</strong></td>
</tr>
<tr>
<td class="tcat"><span class="smalltext"><strong><a href="javascript:void(0)"  onclick="activethreads_whoPosted({$thread[\'tid\']}, {$min_dateline}, {$max_dateline}, \'username\'); return false;">{$lang->user}</a></strong></span></td>
<td class="tcat"><span class="smalltext"><strong><a href="javascript:void(0)"  onclick="activethreads_whoPosted({$thread[\'tid\']}, {$min_dateline}, {$max_dateline}); return false;">{$lang->num_posts}</a></strong></span></td>
</tr>
{$whoposted}
</table>
</div>
</div>',
			'version_at_last_mod' => 10206,
		),
		// Largely copied from core code's misc_whoposted_page but with support added for a date range
		// by requesting activethreads.php when sorting rather than misc.php.
		'activethreads_whoposted_page' => array(
			'template_data'       => '<html>
<head>
<title>{$thread[\'subject\']} - {$lang->who_posted}</title>
{$headerinclude}
</head>
<body>
{$header}
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->total_posts} {$numposts}</strong></td>
</tr>
<tr>
<td class="tcat"><span class="smalltext"><strong><a href="{$mybb->settings[\'bburl\']}/activethreads.php?action=whoposted&amp;tid={$thread[\'tid\']}&amp;min_dateline={$min_dateline}&amp;max_dateline={$max_dateline}&amp;sort=username">{$lang->user}</a></strong></span></td>
<td class="tcat"><span class="smalltext"><strong><a href="{$mybb->settings[\'bburl\']}/activethreads.php?action=whoposted&amp;tid={$thread[\'tid\']}&amp;min_dateline={$min_dateline}&amp;max_dateline={$max_dateline}">{$lang->num_posts}</a></strong></span></td>
</tr>
{$whoposted}
</table>
{$footer}
</body>
</html>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_threadauthor_avatar'  => array(
			'template_data'       => '<a href="{$threadauthor_username_url}"><img src="{$useravatar[\'image\']}" alt="" {$useravatar[\'width_height\']} /></a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_earliestposter_avatar' => array(
			'template_data'       => '<a href="{$earliestposter_username_url}"><img src="{$useravatar[\'image\']}" alt="" {$useravatar[\'width_height\']} /></a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_latestposter_avatar'   => array(
			'template_data'       => '<a href="{$latestposter_username_url}"><img src="{$useravatar[\'image\']}" alt="" {$useravatar[\'width_height\']} /></a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_threadauthor_username_link'   => array(
			'template_data'       => '<a href="{$threadauthor_username_url}">{$threadauthor_username}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_earliestposter_username_link' => array(
			'template_data'       => '<a href="{$earliestposter_username_url}">{$earliestposter_username}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_latestposter_username_link'   => array(
			'template_data'       => '<a href="{$latestposter_username_url}">{$latestposter_username}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_thread_link'                  => array(
			'template_data'       => '<a href="{$thread_url}">{$thread_subject}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_earliestpost_date_link'       => array(
			'template_data'       => '<a href="{$earliestpost_date_url}">{$earliestpost_date}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_latestpost_date_link'         => array(
			'template_data'       => '<a href="{$latestpost_date_url}">{$latestpost_date}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_forum_separator'              => array(
			'template_data'       => '&raquo;',
			'version_at_last_mod' => 10206,
		),
		'activethreads_forum_link'                   => array(
			'template_data'       => '<a href="{$forum_url}">{$forum_name}</a>',
			'version_at_last_mod' => 10206,
		),
		'activethreads_forum_separator_last'         => array(
			'template_data'       => '<br /><img src="images/nav_bit.png" alt="" />',
			'version_at_last_mod' => 10206,
		),
	);

	foreach ($templates as $template_title => $arr) {
		$template_data      = $arr['template_data'      ];
		$template_vers_code = $arr['version_at_last_mod'];

		// First, if the plugin is already installed and this template has changed
		// since the user last upgraded this plugin, then set the to zero the version
		// of modified template for this plugin (i.e., those with an sid of other than -2).
		// This ensures that Find Updated Templates detects them.
		if (!empty($old_version_code) && $old_version_code < $template_vers_code) {
			$db->update_query('templates', array('version' => 0), "title='{$template_title}' and sid <> -2");
		}

		// Now insert/update master templates with SID -2.
		$template_row = array(
			'title'    => $db->escape_string($template_title),
			'template' => $db->escape_string($template_data),
			'sid'      => '-2',
			'version'  => '1',
			'dateline' => TIME_NOW
		);

		$res = $db->simple_select('templates', 'tid', "sid='-2' AND title='".$db->escape_string($template_title)."'");
		$existing = $db->fetch_array($res);
		if ($existing['tid']) {
			unset($template_row['sid']);
			unset($template_row['title']);
			$db->update_query('templates', $template_row, "title='".$db->escape_string($template_title)."' AND sid='-2'");
		} else {
			$db->insert_query('templates', $template_row);
		}
	}

	if (!$db->field_exists('act_max_interval_in_mins', 'usergroups')) {
		$db->add_column('usergroups', 'act_max_interval_in_mins', "int(10) NOT NULL DEFAULT '10080'"); // Default interval of one week.
		$cache->update_usergroups();
		$groupscache = $cache->read('usergroups');
	}
}

function activethreads_activate() {
	global $cache, $db, $lang, $act_plugin_upgrade_message;

	$lrs_plugins = $cache->read('lrs_plugins');
	$info = activethreads_info();

	$old_version_code = $lrs_plugins[C_ACT]['version_code'];
	$new_version_code = $info['version_code'];

	// Perform necessary upgrades.
	if ($new_version_code > $old_version_code) {
		act_upgrade($old_version_code);
		$act_plugin_upgrade_message = $lang->sprintf($lang->act_successful_upgrade_msg, $lang->act_name, $info['version']);
		update_admin_session('act_plugin_info_upgrade_message', $lang->sprintf($lang->act_successful_upgrade_msg_for_info, $info['version']));
	}

	// Update the version in the permanent cache.
	$lrs_plugins[C_ACT] = array(
		'version'      => $info['version'     ],
		'version_code' => $info['version_code'],
	);
	$cache->update('lrs_plugins', $lrs_plugins);

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('header_welcomeblock_guest', '(</script>)', '</script>
				<div class="lower">
					<div class="wrapper">
						<ul class="menu user_links">
							<li><a href="{$mybb->settings[\'bburl\']}/activethreads.php">{$lang->act_view_act_thr}</a></li>
							<li><a href="{$mybb->settings[\'bburl\']}/search.php?action=getdaily">{$lang->welcome_todaysposts}</a></li>		</ul>
					</div>
					<br class="clear" />
				</div>'
	);
	find_replace_templatesets('header_welcomeblock_member_search', '('.preg_quote('<li><a href="{$mybb->settings[\'bburl\']}/search.php?action=getnew">{$lang->welcome_newposts}</a></li>').')', '<li><a href="{$mybb->settings[\'bburl\']}/activethreads.php">{$lang->act_view_act_thr}</a></li>
<li><a href="{$mybb->settings[\'bburl\']}/search.php?action=getnew">{$lang->welcome_newposts}</a></li>');


	// Attach the core code's thread_status.css file to this plugin's page.
	$res = $db->simple_select('themestylesheets', 'attachedto,tid', "name='thread_status.css'");
	$made_changes = false;
	while ($row = $db->fetch_array($res)) {
		$tid = $row['tid'];
		$attachedto = explode('|', $row['attachedto']);
		if (!in_array('activethreads.php', $attachedto)) {
			if (count($attachedto) == 1 && $attachedto[0] == '') {
				$attachedto = array();
			}
			$attachedto[] = 'activethreads.php';
			$db->update_query('themestylesheets', array('attachedto' => implode('|', $attachedto)), "name='thread_status.css' AND tid=$tid");
		}
		$made_changes = true;
	}
	if ($made_changes) {
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		$tids = $db->simple_select('themes', 'tid');
		while ($theme = $db->fetch_array($tids)) {
			update_theme_stylesheet_list($theme['tid']);
		}
	}
}

function activethreads_deactivate() {
	global $db;

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('header_welcomeblock_guest', '('.preg_quote('
				<div class="lower">
					<div class="wrapper">
						<ul class="menu user_links">
							<li><a href="{$mybb->settings[\'bburl\']}/activethreads.php">{$lang->act_view_act_thr}</a></li>
							<li><a href="{$mybb->settings[\'bburl\']}/search.php?action=getdaily">{$lang->welcome_todaysposts}</a></li>		</ul>
					</div>
					<br class="clear" />
				</div>').')', '', 0
	);
	find_replace_templatesets('header_welcomeblock_member_search', '('.preg_quote('<li><a href="{$mybb->settings[\'bburl\']}/activethreads.php">{$lang->act_view_act_thr}</a></li>
').')', '', 0);


	// Dettach the core code's thread_status.css file from this plugin's page.
	$res = $db->simple_select('themestylesheets', 'attachedto,tid', "name='thread_status.css'");
	$made_changes = false;
	while ($row = $db->fetch_array($res)) {
		$tid = $row['tid'];
		$attachedto = explode('|', $row['attachedto']);
		if (($idx = array_search('activethreads.php', $attachedto)) !== false) {
			unset($attachedto[$idx]);
			$db->update_query('themestylesheets', array('attachedto' => implode('|', $attachedto)), "name='thread_status.css' AND tid=$tid");
		}
		$made_changes = true;
	}
	if ($made_changes) {
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		$tids = $db->simple_select('themes', 'tid');
		while ($theme = $db->fetch_array($tids)) {
			update_theme_stylesheet_list($theme['tid']);
		}
	}
}

function act_hookin__plugins_activate_commit() {
	global $message, $act_plugin_info_upgrade_message;

	if (!empty($act_plugin_info_upgrade_message)) {
		$message = $act_plugin_info_upgrade_message;
	}
}

function act_hookin__limits_usergroup_permission() {
	global $mybb, $lang, $form, $form_container, $groupscache;
	$lang->load('activethreads');

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$usergroup = $groupscache[$gid];

	if ($mybb->settings[C_ACT.'_per_usergroup'] == 1) {
		if (!empty($form_container->_title) && !empty($lang->users_permissions) && $form_container->_title == $lang->users_permissions) {
			$act_per_usergroup_options = array(
				"{$lang->act_max_interval_in_mins_title}<br /><small class=\"input\">{$lang->act_max_interval_in_mins_desc}</small><br />".$form->generate_numeric_field('act_max_interval_in_mins', $usergroup['act_max_interval_in_mins'], array('id' => 'id_act_max_interval_in_mins', 'class' => 'field50', 'min' => 0)),
			);

			$form_container->output_row($lang->act_per_usergroup_permissions_heading, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $act_per_usergroup_options)."</div>");
		}
	}

}

function act_hookin__limits_usergroup_permission_commit() {
	global $db, $mybb, $updated_group;
	$updated_group['act_max_interval_in_mins'] = $db->escape_string((int)$mybb->input['act_max_interval_in_mins']);
}
