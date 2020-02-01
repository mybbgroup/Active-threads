<?php

define('IN_MYBB', 1);
require_once './global.php';

define('ACT_NUM_DAYS', 14);
define('ACT_ITEMS_PER_PAGE', 20);

if (!isset($lang->activethreads)) {
	$lang->load('activethreads');
}

function act_get_link($url, $text) {
	return '<a href="'.htmlspecialchars_uni($url).'">'.htmlspecialchars_uni($text).'</a>';
}

function act_get_forumlink($fid, $name) {
	return act_get_link(get_forum_link($fid), $name);
}

function act_get_threadlink($tid, $name) {
	return act_get_link(get_thread_link($tid), $name);
}

function act_get_usernamelink($uid, $name) {
	return act_get_link(get_profile_link($uid), $name);
}

function act_get_postlink($pid, $name) {
	return act_get_link(get_post_link($pid).'#pid'.$pid, $name);
}

function act_get_flinks($parentlist, $forum_names) {
	$flinks = '';
	foreach (explode(',', $parentlist) as $fid) {
		if ($flinks ) $flinks .= ' &raquo; ';
		$flinks .= act_get_forumlink($fid, $forum_names[$fid]);
	}

	return $flinks;
}

function act_make_url($days, $hours, $mins, $date, $sort, $order, $page) {
	return "activethreads.php&days=$days&hours=$hours&mins=$mins&date=".urlencode($date)."&sort=$sort&order=$order&page=$page";
}

if (!is_array($plugins_cache)) {
	$plugins_cache = $cache->read('plugins');
}
$active_plugins = $plugins_cache['active'];

if ($active_plugins && $active_plugins['activethreads']) {
	$max_interval = $mybb->settings[C_ACT.'_max_interval_in_secs'];

	$days = $mybb->get_input('days', MyBB::INPUT_INT);
	$hours = $mybb->get_input('hours', MyBB::INPUT_INT);
	$mins = $mybb->get_input('mins', MyBB::INPUT_INT);
	$date = $mybb->get_input('date');
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$sort = $mybb->get_input('sort');
	$order = $mybb->get_input('order');

	switch ($sort) {
	case 'min_dateline':
	case 'max_dateline':
		break;
	default:
		$sort = 'num_posts';
	}

	if ($order != 'ascending') {
		$order = 'descending';
	}

	if ($days == 0 && $hours == 0 && $mins == 0) {
		if ($max_interval > 0 && ACT_NUM_DAYS * 24*60*60 > $max_interval) {
			$mins = $max_interval * 60;
		} else	$days = ACT_NUM_DAYS;
	}
	if (!$page) $page = 1;

	$secs_before = $days;
	$secs_before = $secs_before * 24 + $hours;
	$secs_before = $secs_before * 60 + $mins;
	$secs_before = $secs_before * 60;

	if ($date) {
		if ($mybb->user['dst'] == 1) {
			$timezone = (float)$mybb->user['timezone']+1;
		} else	$timezone = (float)$mybb->user['timezone'];
		$tz_org = date_default_timezone_get();
		date_default_timezone_set('GMT');
		$ts_epoch = strtotime($date, TIME_NOW + $timezone*60*60) - $timezone*60*60;
		$date_for_title = $date;
		date_default_timezone_set($tz_org);
	} else {
		$ts_epoch = TIME_NOW;
		$date = 'Now';
		$date_for_title = $lang->act_now;
	}
	$date_prior = my_date('normal', $ts_epoch - $secs_before);

	if ($max_interval > 0 && $secs_before > $max_interval) {
		error($lang->sprintf($lang->act_err_excess_int, $secs_before, $max_interval));
	}

	$conds = 'p.dateline >= '.($ts_epoch - $secs_before).' AND p.dateline <= '.$ts_epoch;

	// Make sure the user only sees threads and (counts of) posts s/he is allowed to see.
	$fids = get_unviewable_forums(true);
	if ($inact_fids = get_inactive_forums()) {
		if ($fids) $fids .= ',';
		$fids .= $inact_fids;
	}
	if ($fids) {
		$conds = '('.$conds.') AND f.fid NOT IN ('.$fids.')';
	}
	$onlyusfids = array();
	$group_permissions = forum_permissions();
	foreach ($group_permissions as $fid => $forum_permissions) {
		if ($forum_permissions['canonlyviewownthreads'] == 1) {
			$onlyusfids[] = $fid;
		}
	}
	if ($onlyusfids) {
		$conds .= '('.$conds.' AND ((t.fid IN('.implode(',', $onlyusfids).') AND t.uid="'.$mybb->user['uid'].'") OR t.fid NOT IN('.implode(',', $onlyusfids).')))';
	}
	$conds = '('.$conds.') AND p.visible > 0';

	$inner_sql = "
 SELECT     t.tid,
            t.subject AS thread_subject,
            t.dateline AS thread_dateline,
            uthr.uid AS thread_uid,
            uthr.username AS thread_username,
            f.parentlist,
            f.fid,
            f.name AS forum_name,
            count(p.pid) AS num_posts,
            MIN(p.pid) AS min_pid,
            MAX(p.pid) AS max_pid
 FROM       mybb_threads t
 INNER JOIN mybb_posts p  ON t.tid = p.tid
 INNER JOIN mybb_forums f ON f.fid = t.fid
 INNER JOIN mybb_users uthr ON uthr.uid = t.uid
 WHERE      $conds
 GROUP BY   p.tid";
	$res = $db->query("SELECT count(*) AS cnt FROM ($inner_sql) AS mainq");
	$rows = $db->fetch_array($res);
	$tot_rows = $rows['cnt'];

	$order_by = $sort.' '.($order == 'descending' ? 'DESC' : 'ASC');

	$sql = "
SELECT mainq.tid,
       mainq.thread_subject,
       mainq.thread_dateline,
       mainq.thread_uid,
       mainq.thread_username,
       mainq.parentlist,
       mainq.fid,
       mainq.forum_name,
       mainq.num_posts,
       mainq.min_pid,
       pmin.uid AS min_uid,
       pmin.subject AS min_subject,
       pmin.dateline AS min_dateline,
       umin.username AS min_username,
       mainq.max_pid,
       pmax.uid AS max_uid,
       pmax.subject AS max_subject,
       pmax.dateline AS max_dateline,
       umax.username AS max_username
FROM
($inner_sql) AS mainq
INNER JOIN mybb_posts pmax ON mainq.max_pid = pmax.pid
INNER JOIN mybb_users umax ON umax.uid      = pmax.uid
INNER JOIN mybb_posts pmin ON mainq.min_pid = pmin.pid
INNER JOIN mybb_users umin ON umin.uid      = pmin.uid
ORDER BY   $order_by
LIMIT ".(($page-1) * ACT_ITEMS_PER_PAGE).", ".ACT_ITEMS_PER_PAGE;

	$res = $db->query($sql);
	$rows = $forum_names = array();
	
	$result_rows = '';
	while (($row = $db->fetch_array($res))) {
		$forum_names[$row['fid']] = $row['forum_name'];
		foreach (explode(',', $row['parentlist']) as $fid) {
			if (empty($forum_names[$fid])) {
				$forum_names[$fid] = null;
			}
		}
		$rows[] = $row;
	}

	$missing_fids = array();
	foreach ($forum_names as $fid => $name) {
		if (is_null($name)) {
			$missing_fids[] = $fid;
		}
	}
	if ($missing_fids) {
		$res = $db->simple_select('forums', 'fid,name', 'fid IN ('.implode(',', $missing_fids).')');
		while (($post = $db->fetch_array($res))) {
			$forum_names[$post['fid']] = $post['name'];
		}
	}

	$i = 1;
	if ($rows) {
		foreach ($rows as $row) {
			$i = 1 - $i;
			$bgcolor = 'trow'.($i+1);
			$thread_link = act_get_threadlink($row['tid'], $row['thread_subject']);
			$thread_username_link = act_get_usernamelink($row['thread_uid'], $row['thread_username']);
			$thread_date = my_date('normal', $row['thread_dateline']);
			$num_posts_fmt = my_number_format($row['num_posts']);
			$forum_links = act_get_flinks($row['parentlist'], $forum_names);
			$min_post_date_link = act_get_postlink($row['min_pid'], my_date('normal', $row['min_dateline']));
			$min_post_username_link = act_get_usernamelink($row['min_uid'], $row['min_username']);
			$max_post_date_link = act_get_postlink($row['max_pid'], my_date('normal', $row['max_dateline']));
			$max_post_username_link = act_get_usernamelink($row['max_uid'], $row['max_username']);
			eval("\$result_rows .= \"".$templates->get('activethreads_result_row', 1, 0)."\";");
		}

		$sorter = ' [<a href="'.act_make_url($days, $hours, $mins, $date, $sort, ($order == 'ascending' ? 'descending' : 'ascending'), $page).'">'.($order == 'ascending' ? 'desc' : 'asc').'</a>]';
		$num_posts_heading    = '<a href="'.act_make_url($days, $hours, $mins, $date, 'num_posts', 'descending', $page).'">'.$lang->act_num_posts.'</a>';
		$min_dateline_heading = '<a href="'.act_make_url($days, $hours, $mins, $date, 'min_dateline', 'descending', $page).'">'.$lang->act_earliest_posting.'</a>';
		$max_dateline_heading = '<a href="'.act_make_url($days, $hours, $mins, $date, 'max_dateline', 'descending', $page).'">'.$lang->act_latest_posting.'</a>';
		switch ($sort) {
		case 'num_posts':
			$num_posts_heading    = $lang->act_num_posts.$sorter;
			break;
		case 'min_dateline':
			$min_dateline_heading = $lang->act_earliest_posting.$sorter;
			break;
		case 'max_dateline':
			$max_dateline_heading = $lang->act_latest_posting.$sorter;
			break;
		}
		$lang_act_recent_threads_title = $lang->sprintf($lang->act_act_recent_threads_title, $days, $hours, $mins, $date_prior, $date_for_title);
		$asc_checked           = ($order == 'ascending'    ? ' checked="checked"'   : '');
		$desc_checked          = ($order == 'descending'   ? ' checked="checked"'   : '');
		$num_posts_selected    = ($sort  == 'num_posts'    ? ' selected="selected"' : '');
		$min_dateline_selected = ($sort  == 'min_dateline' ? ' selected="selected"' : '');
		$max_dateline_selected = ($sort  == 'max_dateline' ? ' selected="selected"' : '');
		eval("\$results_html = \"".$templates->get('activethreads_results', 1, 0)."\";");

	} else {
		$results_html = '<p style="text-align: center">'.$lang->act_no_results.'</p>';
	}
	$act_before_date_tooltip = htmlspecialchars_uni($lang->act_before_date_tooltip);
	$act_set_period_of_interest_tooltip = htmlspecialchars_uni($lang->act_set_period_of_interest_tooltip);
	$act_set_period_of_interest = htmlspecialchars_uni($lang->act_set_period_of_interest);
	$multipage = multipage($tot_rows, ACT_ITEMS_PER_PAGE, $page, act_make_url($days, $hours, $mins, $date, $sort, $order, '{page}'));
	add_breadcrumb($lang->act_act_recent_threads_breadcrumb, C_ACT.'.php');
	eval("\$html = \"".$templates->get('activethreads_page', 1, 0)."\";");
	output_page($html);
} else	echo $lang->act_inactive;