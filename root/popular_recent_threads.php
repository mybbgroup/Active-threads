<?php

define('IN_MYBB', 1);
require_once './global.php';

define('PRT_NUM_DAYS', 14);

if (!isset($lang->popular_recent_threads)) {
	$lang->load('popular_recent_threads');
}

function prt_get_link($url, $text) {
	return '<a href="'.htmlspecialchars_uni($url).'">'.htmlspecialchars_uni($text).'</a>';
}

function prt_get_forumlink($fid, $name) {
	return prt_get_link(get_forum_link($fid), $name);
}

function prt_get_threadlink($tid, $name) {
	return prt_get_link(get_thread_link($tid), $name);
}

function prt_get_usernamelink($uid, $name) {
	return prt_get_link(get_profile_link($uid), $name);
}

function prt_get_postlink($pid, $name) {
	return prt_get_link(get_post_link($pid).'#pid'.$pid, $name);
}

function prt_get_flinks($parentlist, $forum_names) {
	$flinks = '';
	foreach (explode(',', $parentlist) as $fid) {
		if ($flinks ) $flinks .= ' &raquo; ';
		$flinks .= prt_get_forumlink($fid, $forum_names[$fid]);
	}

	return $flinks;
}

if (!is_array($plugins_cache)) {
	$plugins_cache = $cache->read('plugins');
}
$active_plugins = $plugins_cache['active'];

if ($active_plugins && $active_plugins['popular_recent_threads']) {
	$conds = 'p.dateline >= UNIX_TIMESTAMP() - 60*60*24*'.PRT_NUM_DAYS;
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
(SELECT     t.tid,
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
 GROUP BY   p.tid
 ORDER BY   count(p.pid) DESC) AS mainq
INNER JOIN mybb_posts pmax ON mainq.max_pid = pmax.pid
INNER JOIN mybb_users umax ON umax.uid      = pmax.uid
INNER JOIN mybb_posts pmin ON mainq.min_pid = pmin.pid
INNER JOIN mybb_users umin ON umin.uid      = pmin.uid";

	$res = $db->query($sql);
	$rows = $forum_names = array();
	
	$html = '';
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
	foreach ($rows as $row) {
		$i = 1 - $i;
		if (!$html) {
			$lang_pop_recent_threads_title = $lang->sprintf($lang->prt_pop_recent_threads_title, PRT_NUM_DAYS);
			$html =<<<EOF
<table class="tborder tfixed clear">
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
EOF;
		}
		$html .= '<tr class="inline_row">'.
		             '<td class="trow'.($i+1).' forumdisplay_regular" style="text-align: left;">'.prt_get_threadlink($row['tid'], $row['thread_subject']).'<div class="smalltext"><span class="author">'.prt_get_usernamelink($row['thread_uid'], $row['thread_username']).'</span> <span style="float: right;">'.my_date('normal', $row['thread_dateline']).'</span></div></td>'.
		             '<td class="trow'.($i+1).'">'.$row['num_posts'].'</td>'.
		             '<td class="trow'.($i+1).'">'.prt_get_flinks($row['parentlist'], $forum_names).'</td>'.
		             '<td class="trow'.($i+1).'" style="text-align: right;">'.prt_get_postlink($row['min_pid'], my_date('normal', $row['min_dateline'])).'<div class="smalltext"><span class="author">'.prt_get_usernamelink($row['min_uid'], $row['min_username']).'</span></div></td>'.
		             '<td class="trow'.($i+1).'" style="text-align: right;">'.prt_get_postlink($row['max_pid'], my_date('normal', $row['max_dateline'])).'<div class="smalltext"><span class="author">'.prt_get_usernamelink($row['max_uid'], $row['max_username']).'</span></div></td>'.
		         '</tr>';
	}

	if ($html) {
		$html .= '</tbody></table>';
	}
	output_page(<<<EOF
<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang_pop_recent_threads_title}</title>
{$headerinclude}
<style type="text/css">
/**/
table, td, th {
	text-align: center;
	margin: 0;
	border-width: 0;
	border-spacing: 0;
/*	border-collapse: collapse;
	border: 1px solid grey;*/
}
/**/
</style>
</head>
<body>
{$header}
{$html}
{$footer}
</body>
</html>
EOF
);
} else	echo $lang->prt_inactive;