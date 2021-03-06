<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2014 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Forum View Topic
 *
*/

require_once ('../../class2.php');
define('NAVIGATION_ACTIVE','forum');

$e107 = e107::getInstance();
$tp = e107::getParser();
$ns = e107::getRender();

if (!$e107->isInstalled('forum'))
{
	header('Location: '.e_BASE.'index.php');
	exit;
}

if (isset($_POST['fjsubmit']))
{
	header('location:' . e107::getUrl()->create('forum/forum/view', array('id'=>(int) $_POST['forumjump']), 'full=1&encode=0'));
	exit;
}

$highlight_search = isset($_POST['highlight_search']);

if (!e_QUERY)
{
	//No parameters given, redirect to forum home
	header('Location:' . e107::getUrl()->create('forum/forum/main', array(), 'full=1&encode=0'));
	exit;
}

include_once(e_PLUGIN.'forum/forum_class.php');

$forum = new e107forum();
$thread = new e107ForumThread();

// check if user wants to download a file 
if(vartrue($_GET['id']) && isset($_GET['dl']))
{
	$forum->sendFile($_GET);
	exit;
}

if(e_AJAX_REQUEST && varset($_POST['action']) == 'quickreply')
{
	$forum->ajaxQuickReply();
}
	
if(e_AJAX_REQUEST && MODERATOR) // see javascript above. 
{
	$forum->ajaxModerate();
}
		
if (isset($_GET['last']))
{
	$_GET['f'] = 'last';
}

if(isset($_GET['f']) && $_GET['f'] == 'post')
{
	$thread->processFunction();
}

$thread->init();

if(isset($_POST['track_toggle']))
{
	$thread->toggle_track();
	exit;
}

if(!empty($_GET['f']))
{
	$retext = $thread->processFunction();

	if($retext)
	{
		require_once(HEADERF);
	//	e107::getMessage()->addWarning($retext);
	//	echo e107::getmessage()->render();
		echo $retext;
		require_once(FOOTERF);
		exit;
	}

	if($_GET['f'] != 'last') { $thread->init(); }
}


e107::getScBatch('view', 'forum')->setScVar('thread', $thread);

$pm_installed = e107::isInstalled('pm');

//Only increment thread views if not being viewed by thread starter
if (USER && (USERID != $thread->threadInfo['thread_user'] || $thread->threadInfo['thread_total_replies'] > 0) || !$thread->noInc)
{
	$forum->threadIncview($thread->threadInfo['thread_id']);
}

define('e_PAGETITLE', strip_tags($tp->toHTML($thread->threadInfo['thread_name'], true, 'no_hook, emotes_off')).' / '.$tp->toHTML($thread->threadInfo['forum_name'], true, 'no_hook, emotes_off').' / '.LAN_FORUM_1001);
$forum->modArray = $forum->forumGetMods($thread->threadInfo['forum_moderators']);
define('MODERATOR', (USER && $forum->isModerator(USERID)));

e107::getScBatch('view', 'forum')->setScVar('forum', $forum);
//var_dump(e107::getScBatch('forum', 'forum'));


if(MODERATOR && isset($_POST['mod']))
{
	require_once(e_PLUGIN."forum/forum_mod.php");
	$thread->message = forum_thread_moderate($_POST);
	$thread->threadInfo = $forum->threadGet($thread->threadId);
}

$num = $thread->page ? $thread->page - 1 : 0;
$postList = $forum->PostGet($thread->threadId, $num * $thread->perPage, $thread->perPage);

// SEO - meta description (auto)
if(count($postList))
{
	define("META_DESCRIPTION", $tp->text_truncate(
		str_replace(
			array('"', "'"), '', strip_tags($tp->toHTML($postList[0]['post_entry']))
	), 250, '...'));
}

$gen = new convert;
if($thread->message)
{
	//$ns->tablerender('', $thread->message, array('forum_viewtopic', 'msg'));
	e107::getMessage()->add($thread->message);
}



//if (isset($thread->threadInfo['thread_options']['poll'])) //XXX Currently Failing - misconfigured thread-options. 
//{
if(e107::isInstalled('poll'))
{
	$_qry = 'SELECT * FROM `#polls` WHERE `poll_datestamp` = ' . $thread->threadId;
	if($sql->gen($_qry))
	{
		if (!defined('POLLCLASS'))
		{
			include_once(e_PLUGIN . 'poll/poll_class.php');
		}
		$poll = new poll;
		$pollstr = "<div class='spacer'>" . $poll->render_poll($_qry, 'forum', 'query', true) . '</div>';
	}
}
//}

//Load forum templates
// FIXME - new template paths!
if(file_exists(THEME.'forum_design.php')) // legacy file
{
	include_once (THEME.'forum_design.php');
}

if (!vartrue($FORUMSTART))
{
	if(file_exists(THEME.'forum_viewtopic_template.php'))
	{
		require_once(THEME.'forum_viewtopic_template.php');
	}
	elseif(file_exists(THEME.'templates/forum/forum_viewtopic_template.php'))
	{
		require_once(THEME.'templates/forum/forum_viewtopic_template.php'); 
	}
	elseif(file_exists(THEME.'forum_template.php'))
	{
		require_once(THEME.'forum_template.php');
	}
	else
	{
		require_once(e_PLUGIN.'forum/templates/forum_viewtopic_template.php');
	}
}


// New in v2.x
if(is_array($FORUM_VIEWTOPIC_TEMPLATE) && deftrue('BOOTSTRAP',false))
{
	$FORUMSTART 			= $FORUM_VIEWTOPIC_TEMPLATE['start'];
	$FORUMTHREADSTYLE		= $FORUM_VIEWTOPIC_TEMPLATE['thread'];
	$FORUMEND				= $FORUM_VIEWTOPIC_TEMPLATE['end'];
	$FORUMREPLYSTYLE 		= $FORUM_VIEWTOPIC_TEMPLATE['replies'];	
}



// get info for main thread -------------------------------------------------------------------------------------------------------------------------------------------------------------------
$tVars = new e_vars;
$forum->set_crumb(true, '', $tVars); // Set $BREADCRUMB (and BACKLINK)
//$tVars->BREADCRUMB = $crumbs['breadcrumb'];
//$tVars->BACKLINK = $tVars->BREADCRUMB;
//$tVars->FORUM_CRUMB = $crumbs['forum_crumb'];
$tVars->THREADNAME = $tp->toHTML($thread->threadInfo['thread_name'], true, 'no_hook, emotes_off');
$tVars->NEXTPREV = "<a class='btn btn-default btn-sm btn-small' href='" . $e107->url->create('forum/thread/prev', array('id' => $thread->threadId)) . "'>&laquo; " . LAN_FORUM_2001 . "</a>";
$tVars->NEXTPREV .= ' | '; // enabled to make it look better on v1 templates
$tVars->NEXTPREV .= "<a class='btn btn-default btn-sm btn-small' href='" . $e107->url->create('forum/thread/prev', array('id' => $thread->threadId)) . "'>" . LAN_FORUM_2002 . " &raquo;</a>";

if ($forum->prefs->get('track') && USER)
{
	$img = ($thread->threadInfo['track_userid'] ? IMAGE_track : IMAGE_untrack);
	$url = $e107->url->create('forum/thread/view', array('id' => $thread->threadId), 'encode=0'); // encoding could break AJAX call
	$tVars->TRACK .= "
			<span id='forum-track-trigger-container'>
			<a class='btn btn-default btn-sm btn-small' href='{$url}' id='forum-track-trigger'>{$img}</a>
			</span>
			<script type='text/javascript'>
			e107.runOnLoad(function(){
				$('forum-track-trigger').observe('click', function(e) {
					e.stop();
					new e107Ajax.Updater('forum-track-trigger-container', '{$url}', {
						method: 'post',
						parameters: { //send query parameters here
							'track_toggle': 1
						},
						overlayPage: $(document.body)
					});
				});
			}, document, true);
			</script>
	";
}


$tVars->MODERATORS = LAN_FORUM_2003.": ". implode(', ', $forum->modArray);

$tVars->THREADSTATUS = (!$thread->threadInfo['thread_active'] ? LAN_FORUM_2004 : '');

if ($thread->pages > 1)
{
	if(!$thread->page) $thread->page = 1;
	$url = rawurlencode(e107::getUrl()->create('forum/thread/view', array('name' => $thread->threadInfo['thread_name'], 'id' => $thread->threadId, 'page' => '[FROM]')));
	$parms = "total={$thread->pages}&type=page&current={$thread->page}&url=".$url."&caption=off&tmpl=default&navcount=4&glyphs=1";
	
	//XXX FIXME - pull-down template not practical here. Can we force another?
	$tVars->GOTOPAGES = $tp->parseTemplate("{NEXTPREV={$parms}}");
/*
	$parms = ($thread->pages).",1,{$thread->page},url::forum::thread::func=view&id={$thread->threadId}&page=[FROM],off";
	$tVars->GOTOPAGES = $tp->parseTemplate("{NEXTPREV={$parms}}");*/
}

$tVars->BUTTONS = '';
if ($forum->checkPerm($thread->threadInfo['thread_forum_id'], 'post') && $thread->threadInfo['thread_active'])
{
	$tVars->BUTTONS .= "<a href='" . $e107->url->create('forum/thread/reply', array('id' => $thread->threadId)) . "'>" . IMAGE_reply . "</a>";
}
if ($forum->checkPerm($thread->threadInfo['thread_forum_id'], 'thread'))
{
	$tVars->BUTTONS .= "<a href='" . $e107->url->create('forum/thread/new', array('id' => $thread->threadInfo['thread_forum_id'])) . "'>" . IMAGE_newthread . "</a>";
}


$tVars->BUTTONSX = forumbuttons($thread);

function forumbuttons($thread)
{
	global $forum; 
	
	
	
	if ($forum->checkPerm($thread->threadInfo['thread_forum_id'], 'post') && $thread->threadInfo['thread_active'])
	{
		$replyUrl = "<a class='btn btn-primary' href='".e107::getUrl()->create('forum/thread/reply', array('id' => $thread->threadId))."'>".LAN_FORUM_2006."</a>";
	}
	if ($forum->checkPerm($thread->threadInfo['thread_forum_id'], 'thread'))
	{
		$options[] = " <a  href='".e107::getUrl()->create('forum/thread/new', array('id' => $thread->threadInfo['thread_forum_id']))."'>".LAN_FORUM_2005."</a>";
	}	
	
	$options[] = "<a href='" . e107::getUrl()->create('forum/thread/prev', array('id' => $thread->threadId)) . "'>".LAN_FORUM_1017." ".LAN_FORUM_2001."</a>"; 
	$options[] = "<a href='" . e107::getUrl()->create('forum/thread/prev', array('id' => $thread->threadId)) . "'>".LAN_FORUM_1017." ".LAN_FORUM_2002."</a>";  

	
	
	
	$text = '<div class="btn-group">
   '.$replyUrl.'
    <button class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
    <span class="caret"></span>
    </button>
    <ul class="dropdown-menu pull-right">
    ';
	
	foreach($options as $key => $val)
	{
		$text .= '<li>'.$val.'</li>';
	}
	
	$jumpList = $forum->forumGetAllowed();
	
	$text .= "<li class='divider'></li>";
	
	foreach($jumpList as $key=>$val)
	{
		$text .= '<li><a href ="'.$key.'">'.LAN_FORUM_1017." ".$val.'</a></li>';	
	}
	
	$text .= '
    </ul>
    </div>';
	
	
	return $text;
	
}



$tVars->POLL = vartrue($pollstr);

$tVars->FORUMJUMP = forumjump();

$tVars->MESSAGE = $thread->message;


$forstr = $tp->simpleParse($FORUMSTART, $tVars);

unset($forrep);
if (!$FORUMREPLYSTYLE) $FORUMREPLYSTYLE = $FORUMTHREADSTYLE;
$alt = false;

$i = $thread->page;
foreach ($postList as $postInfo)
{
	if($postInfo['post_options'])
	{
		$postInfo['post_options'] = unserialize($postInfo['post_options']);
	}
	$loop_uid = (int)$postInfo['post_user'];
	$tnum = $i;
	$i++;

	//TODO: Look into fixing this, to limit to a single query per pageload
	$threadId = $thread->threadInfo['thread_id'];
	$e_hide_query = "SELECT post_id FROM `#forum_post` WHERE (`post_thread` = {$threadId} AND post_user= " . USERID . ' LIMIT 1';
	$e_hide_hidden = LAN_FORUM_2008;
	$e_hide_allowed = USER;
	
	if ($tnum > 1)
	{
		$postInfo['thread_start'] = false;
		$alt = !$alt;

		if($postInfo['post_status'])
		{
			$_style = (isset($FORUMDELETEDSTYLE_ALT) && $alt ? $FORUMDELETEDSTYLE_ALT : $FORUMDELETEDSTYLE);
		}
		else
		{
			$_style = (isset($FORUMREPLYSTYLE_ALT) && $alt ? $FORUMREPLYSTYLE_ALT : $FORUMREPLYSTYLE);
		}

		$forum_shortcodes = e107::getScBatch('view', 'forum')->setScVar('postInfo', $postInfo);
		$forrep .= $tp->parseTemplate($_style, true, $forum_shortcodes) . "\n";
	}
	else
	{
		$postInfo['thread_start'] = true;
		$forum_shortcodes = e107::getScBatch('view', 'forum')->setScVar('postInfo', $postInfo);
		$forthr = $tp->parseTemplate($FORUMTHREADSTYLE, true, vartrue($forum_shortcodes)) . "\n";
	}
}
unset($loop_uid);

if ($forum->checkPerm($thread->threadInfo['thread_forum_id'], 'post') && $thread->threadInfo['thread_active'])
{
	//XXX Show only on the last page??
	if (!vartrue($forum_quickreply))
	{
		$ajaxInsert = ($thread->pages == $thread->page || $thread->pages == 0) ? 1 : 0;
		
	//	echo "AJAX-INSERT=".$ajaxInsert ."(".$thread->pages." vs ".$thread->page.")";
		$frm = e107::getForm();
		
		$tVars->QUICKREPLY = "
		<form action='" . $e107->url->create('forum/thread/reply', array('id' => $thread->threadId)) . "' method='post'>
		<div class='form-group'>
			<textarea cols='80' placeholder='".LAN_FORUM_2007."' rows='4' id='forum-quickreply-text' class='tbox input-xxlarge form-control' name='post' onselect='storeCaret(this);' onclick='storeCaret(this);' onkeyup='storeCaret(this);'></textarea>
		</div>
		<div class='center text-center form-group'>
			<input type='submit' data-token='".e_TOKEN."' data-forum-insert='".$ajaxInsert."' data-forum-post='".$thread->threadInfo['thread_forum_id']."' data-forum-thread='".$threadId."' data-forum-action='quickreply' name='reply' value='".LAN_FORUM_2006. "' class='btn btn-success button' />
			<input type='hidden' name='thread_id' value='$thread_parent' />
		</div>
		
		</form>";
				
		// Preview should be reserved for the full 'Post reply' page. <input type='submit' name='fpreview' value='" . Preview . "' /> &nbsp;
	}
	else
	{
		$tVars->QUICKREPLY = $forum_quickreply;
	}
}



$forend = $tp->simpleParse($FORUMEND, $tVars);

$forumstring = $forstr . $forthr . vartrue($forrep) . $forend;

//If last post came after USERLV and not yet marked as read, mark the thread id as read
$threadsViewed = explode(',', $currentUser['user_plugin_forum_viewed']);
if ($thread->threadInfo['thread_lastpost'] > USERLV && !in_array($thread->threadId, $threadsViewed))
{
	$tst = $forum->threadMarkAsRead($thread->threadId);
}
$mes = e107::getMessage();
require_once (HEADERF);


if ($forum->prefs->get('enclose'))
{
	$ns->tablerender(LAN_FORUM_1001, $forumstring, $mes->render(). array('forum_viewtopic', 'main'));
}
else
{
	echo $mes->render() . $forumstring;
}

// end -------------------------------------------------------------------------------------------------------------------------------------------------------------------

echo "<script type=\"text/javascript\">
	function confirm_(mode, forum_id, thread_id, thread) {
	if (mode == 'Thread') {
	return confirm(\"" . $tp->toJS(LAN_FORUM_2009) . "\");
	} else {
	return confirm(\"" . $tp->toJS(LAN_FORUM_2010) . " [ " . $tp->toJS(LAN_FORUM_0074) . " \" + thread + \" ]\");
	}
	}
	</script>";
require_once (FOOTERF);

function showmodoptions()
{
	global $thread, $postInfo;

	$e107 = e107::getInstance();
	$forum_id = $thread->threadInfo['forum_id'];
	if ($postInfo['thread_start'])
	{
		$type = 'Thread';
		// XXX _URL_ thread name?
		$ret = "<form method='post' action='" . $e107->url->create('forum/thread/view', array('id' => $postInfo['post_thread']))."' id='frmMod_{$postInfo['post_forum']}_{$postInfo['post_thread']}'>";
		$delId = $postInfo['post_thread'];
	}
	else
	{
		$type = 'Post';
		$ret = "<form method='post' action='" . e_SELF . '?' . e_QUERY . "' id='frmMod_{$postInfo['post_thread']}_{$postInfo['post_id']}'>";
		$delId = $postInfo['post_id'];
	}

	$ret .= "
		<div>
		<a href='" . $e107->url->create('forum/thread/edit', array('id' => $postInfo['post_id']))."'>" . IMAGE_admin_edit . "</a>
		<input type='image' " . IMAGE_admin_delete . " name='delete{$type}_{$delId}' value='thread_action' onclick=\"return confirm_('{$type}', {$postInfo['post_forum']}, {$postInfo['post_thread']}, '{$postInfo['user_name']}')\" />
		<input type='hidden' name='mod' value='1'/>
		";
	if ($type == 'Thread')
	{
		$ret .= "<a href='" . $e107->url->create('forum/thread/move', array('id' => $postInfo['post_id']))."'>" . IMAGE_admin_move2 . "</a>";
	}
	else
	{
		$ret .= "<a href='" . $e107->url->create('forum/thread/split', array('id' => $postInfo['post_id']))."'>" . IMAGE_admin_split . '</a>';

	}
	$ret .= "
		</div>
		</form>";
	return $ret;
}

function forumjump()
{
	global $forum;
	$jumpList = $forum->forumGetAllowed();
	$text = "<form method='post' action='".e_SELF."'><p>".LAN_FORUM_1017.": <select name='forumjump' class='tbox'>";
	foreach ($jumpList as $key => $val)
	{
		$text .= "\n<option value='" . $key . "'>" . $val . "</option>";
	}
	$text .= "</select> <input class='btn btn-default button' type='submit' name='fjsubmit' value='" . LAN_GO . "' /></p></form>";
	return $text;
}

function rpg($user_join, $user_forums)
{
	global $FORUMTHREADSTYLE;
	if (strpos($FORUMTHREADSTYLE, '{RPG}') === false)
	{
		return '';
	}
	// rpg mod by Ikari ( kilokan1@yahoo.it | http://artemanga.altervista.org )

	$lvl_post_mp_cost = 2.5;
	$lvl_mp_regen_per_day = 4;
	$lvl_avg_ppd = 5;
	$lvl_bonus_redux = 5;
	$lvl_user_days = max(1, round((time() - $user_join) / 86400));
	$lvl_ppd = $user_forums / $lvl_user_days;
	if ($user_forums < 1)
	{
		$lvl_level = 0;
	}
	else
	{
		$lvl_level = floor(pow(log10($user_forums), 3)) + 1;
	}
	if ($lvl_level < 1)
	{
		$lvl_hp = "0 / 0";
		$lvl_hp_percent = 0;
	}
	else
	{
		$lvl_max_hp = floor((pow($lvl_level, (1 / 4))) * (pow(10, pow($lvl_level + 2, (1 / 3)))) / (1.5));

		if ($lvl_ppd >= $lvl_avg_ppd)
		{
			$lvl_hp_percent = floor((.5 + (($lvl_ppd - $lvl_avg_ppd) / ($lvl_bonus_redux * 2))) * 100);
		}
		else
		{
			$lvl_hp_percent = floor($lvl_ppd / ($lvl_avg_ppd / 50));
		}
		if ($lvl_hp_percent > 100)
		{
			$lvl_max_hp += floor(($lvl_hp_percent - 100) * pi());
			$lvl_hp_percent = 100;
		}
		else
		{
			$lvl_hp_percent = max(0, $lvl_hp_percent);
		}
		$lvl_cur_hp = floor($lvl_max_hp * ($lvl_hp_percent / 100));
		$lvl_cur_hp = max(0, $lvl_cur_hp);
		$lvl_cur_hp = min($lvl_max_hp, $lvl_cur_hp);
		$lvl_hp = $lvl_cur_hp . '/' . $lvl_max_hp;
	}
	if ($lvl_level < 1)
	{
		$lvl_mp = '0 / 0';
		$lvl_mp_percent = 0;
	}
	else
	{
		$lvl_max_mp = floor((pow($lvl_level, (1 / 4))) * (pow(10, pow($lvl_level + 2, (1 / 3)))) / (pi()));
		$lvl_mp_cost = $user_forums * $lvl_post_mp_cost;
		$lvl_mp_regen = max(1, $lvl_user_days * $lvl_mp_regen_per_day);
		$lvl_cur_mp = floor($lvl_max_mp - $lvl_mp_cost + $lvl_mp_regen);
		$lvl_cur_mp = max(0, $lvl_cur_mp);
		$lvl_cur_mp = min($lvl_max_mp, $lvl_cur_mp);
		$lvl_mp = $lvl_cur_mp . '/' . $lvl_max_mp;
		$lvl_mp_percent = floor($lvl_cur_mp / $lvl_max_mp * 100);
	}
	if ($lvl_level < 1)
	{
		$lvl_exp = "0 / 0";
		$lvl_exp_percent = 100;
	}
	else
	{
		$lvl_posts_for_next = floor(pow(10, pow($lvl_level, (1 / 3))));
		if ($lvl_level == 1)
		{
			$lvl_posts_for_this = max(1, floor(pow(10, (($lvl_level - 1)))));
		}
		else
		{
			$lvl_posts_for_this = max(1, floor(pow(10, pow(($lvl_level - 1), (1 / 3)))));
		}
		$lvl_exp = ($user_forums - $lvl_posts_for_this) . "/" . ($lvl_posts_for_next - $lvl_posts_for_this);
		$lvl_exp_percent = floor((($user_forums - $lvl_posts_for_this) / max(1, ($lvl_posts_for_next - $lvl_posts_for_this))) * 100);
	}

	$bar_image = THEME . "images/bar.jpg";
	if (!is_readable($bar_image))
	{
		$bar_image = e_PLUGIN . "forum/images/" . IMODE . "/bar.jpg";
	}

	$rpg_info .= "<div style='padding:2px; white-space:nowrap'>";
	$rpg_info .= "<b>Level = " . $lvl_level . "</b><br />";
	$rpg_info .= "HP = " . $lvl_hp . "<br /><img src='{$bar_image}' alt='' style='border:#345487 1px solid; height:10px; width:" . $lvl_hp_percent . "%'><br />";
	$rpg_info .= "EXP = " . $lvl_exp . "<br /><img src='{$bar_image}' alt='' style='border:#345487 1px solid; height:10px; width:" . $lvl_exp_percent . "%'><br />";
	$rpg_info .= "MP = " . $lvl_mp . "<br /><img src='{$bar_image}' alt='' style='border:#345487 1px solid; height:10px; width:" . $lvl_mp_percent . "%'><br />";
	$rpg_info .= "</div>";
	return $rpg_info;
}

class e107ForumThread
{

	public $message;
	public $threadId;
	public $forumId;
	public $perPage;
	public $noInc;
	public $pages;

	function init()
	{
		global $forum;
		$e107 = e107::getInstance();
		$this->threadId = (int)varset($_GET['id']);
		$this->perPage = (varset($_GET['perpage']) ? (int)$_GET['perpage'] : $forum->prefs->get('postspage'));
		$this->page = (varset($_GET['p']) ? (int)$_GET['p'] : 1);

		
		if(!$this->threadId && e_QUERY) //BC Links fix. 
		{
			list($id,$page) = explode(".",e_QUERY);
			$this->threadId = intval($id);	
			$this->page 	= intval($page);
		}
		
		
		//If threadId doesn't exist, or not given, redirect to main forum page
		if (!$this->threadId || !$this->threadInfo = $forum->threadGet($this->threadId))
		{
			header('Location:' . $e107->url->create('forum/forum/main', array(), 'encode=0&full=1'));
			exit;
		}

		//If not permitted to view forum, redirect to main forum page
		if (!$forum->checkPerm($this->threadInfo['thread_forum_id'], 'view'))
		{
			header('Location:' . $e107->url->create('forum/forum/main', array(), 'encode=0&full=1'));
			exit;
		}
		$this->pages = ceil(($this->threadInfo['thread_total_replies']) / $this->perPage);
		$this->noInc = false;
	}

	function toggle_track()
	{
		global $forum, $thread;
		$e107 = e107::getInstance();
		if (!USER || !isset($_GET['id'])) { return; }
		if($thread->threadInfo['track_userid'])
		{
			$forum->track('del', USERID, $_GET['id']);
			$img = IMAGE_untrack;
		}
		else
		{
			$forum->track('add', USERID, $_GET['id']);
			$img = IMAGE_track;
		}
		if(e_AJAX_REQUEST)
		{
			$url = $e107->url->create('forum/thread/view', array('name' => $this->threadInfo['thread_name'], 'id' => $thread->threadId));
			echo "<a href='{$url}' id='forum-track-trigger'>{$img}</a>";
			exit();
		}
	}

	/**
	 * @return bool|null|string|void
	 */
	function processFunction()
	{



		global $forum, $thread;
	//	$e107 = e107::getInstance();
		$ns = e107::getRender();
		$sql = e107::getDb();
		$tp = e107::getParser();
		$frm = e107::getForm();

		if (empty($_GET['f']))
		{
			return;
		}

		$function = trim($_GET['f']);
		switch ($function)
		{
			case 'post':
				$postId = varset($_GET['id']);
				$postInfo = $forum->postGet($postId,'post');
				$postNum = $forum->postGetPostNum($postInfo['post_thread'], $postId);
				$postPage = ceil($postNum / $forum->prefs->get('postspage'));
				$url = e107::getUrl()->create('forum/thread/view', array('id' => $postInfo['post_thread'], 'name' => $postInfo['thread_name'], 'page' => $postPage), 'full=1&encode=0');
				header('location: '.$url);
				exit;
				break;

			case 'last':
				$pages = ceil(($thread->threadInfo['thread_total_replies']) / $thread->perPage);
				$thread->page = $_GET['p'] = $pages;
				break;

			case 'next': // FIXME - nextprev thread detection not working
				$next = $forum->threadGetNextPrev('next', $this->threadId, $this->threadInfo['forum_id'], $this->threadInfo['thread_lastpost']);
				if ($next)
				{
					$url = e107::getUrl()->create('forum/thread/view', array('id' => $next), array('encode' => false, 'full' => 1)); // no thread name info at this time
					header("location: {$url}");
					exit;
				}
				$this->message = LAN_FORUM_2013;
				break;

			case 'prev': // FIXME - nextprev thread detection not working
				$prev = $forum->threadGetNextPrev('prev', $this->threadId, $this->threadInfo['forum_id'], $this->threadInfo['thread_lastpost']);
				if ($prev)
				{
					$url = e107::getUrl()->create('forum/thread/view', array('id' => $prev), array('encode' => false, 'full' => 1));// no thread name info at this time
					header("location: {$url}");
					exit;
				}
				$this->message = LAN_FORUM_2012;
				break;

			case 'report':
				$threadId 	= (int)$_GET['id'];
				$postId 	= (int)$_GET['post'];
				$postInfo 	= $forum->postGet($postId, 'post');

				if(!empty($_POST['report_thread']))
				{
					$report_add = $tp->toDB($_POST['report_add']);

					if($forum->prefs->get('reported_post_email')) // todo Use e_NOTIFY.
					{
						require_once(e_HANDLER.'mail.php');
						$report = LAN_FORUM_2018." ".SITENAME." : ".(substr(SITEURL, -1) == "/" ? SITEURL : SITEURL."/") . $e107->getFolder('plugins') . "forum/forum_viewtopic.php?" . $this->threadId . ".post\n
						".LAN_FORUM_2019.": ".USERNAME. "\n" . $report_add;
						$subject = LAN_FORUM_2020." ". SITENAME;
						sendemail(SITEADMINEMAIL, $subject, $report);
					}
					// no reference of 'head' $threadInfo['head']['thread_name']

					$insert = array(
						'gen_id'        =>	0,
						'gen_type'      =>	'reported_post',
						'gen_datestamp' =>	time(),
						'gen_user_id'   =>	USERID,
						'gen_ip'        =>	$tp->toDB($postInfo['thread_name']),
						'gen_intdata'   =>	intval($this->threadId),
						'gen_chardata'  =>	$report_add,



					);

					$url = e107::getUrl()->create('forum/thread/post', array('id' => $postId, 'name' => $postInfo['thread_name'], 'thread' => $threadId)); // both post info and thread info contain thread name

					$result = $sql->insert('generic', $insert);

					if($result)
					{
						$text = "<div class='alert alert-block alert-success'><h4>".LAN_FORUM_2021 . "</h4><a href='{$url}'>".LAN_FORUM_2022.'</a></div>';
					}
					else
					{
						$text = "<div class='alert alert-block alert-error'><h4>".LAN_FORUM_2021 . "</h4><a href='{$url}'>".LAN_FORUM_2022.'</a></div>';
					}

					define('e_PAGETITLE', LAN_FORUM_1001 . " / " . LAN_FORUM_2021);

					return $ns->tablerender(LAN_FORUM_2023, $text, array('forum_viewtopic', 'report'), true);
				}
				else
				{
					$thread_name = e107::getParser()->toHTML($postInfo['thread_name'], true, 'no_hook, emotes_off');
					define('e_PAGETITLE', LAN_FORUM_1001.' / '.LAN_FORUM_2024.': '.$thread_name);
					$url = e107::getUrl()->create('forum/thread/post', array('id' => $postId, 'name' => $postInfo['thread_name'], 'thread' => $threadId));
					$actionUrl = e107::getUrl()->create('forum/thread/report', "id={$threadId}&post={$postId}");


					if(deftrue('BOOTSTRAP')) //v2.x
					{
						$text = $frm->open('forum-report-thread','post');
						$text .= "
							<div>
								<div class='alert alert-block alert-warning'>
								<h4>".LAN_FORUM_2025.': '.$thread_name."</h4>
									".LAN_FORUM_2027."<br />".str_replace(array('[', ']'), array('<b>', '</b>'), LAN_FORUM_2028)."
								<a class='pull-right btn btn-xs btn-primary e-expandit' href='#post-info'>View Post</a>
								</div>
								<div id='post-info' class='e-hideme alert alert-block alert-danger'>
									".$tp->toHtml($postInfo['post_entry'],true)."
								</div>
								<div class='form-group' >
									<div class='col-md-12'>
								".$frm->textarea('report_add','',10,35,array('size'=>'xxlarge'))."
									</div>
								</div>
								<div class='form-group'>
									<div class='col-md-12'>
									".$frm->button('report_thread',1,'submit',LAN_FORUM_2029)."
									</div>
								</div>

							</div>";

						$text .= $frm->close();
					//	$text .= print_a($postInfo['post_entry'],true);

					}
					else //v1.x legacy layout.
					{
						$text = "<form action='".$actionUrl."' method='post'>
						<table class='table' style='width:100%'>
						<tr>
							<td  style='width:50%'>
							".LAN_FORUM_2025.': '.$thread_name." <a href='".$url."'><span class='smalltext'>".LAN_FORUM_2026."</span></a>
							</td>
							<td style='text-align:center;width:50%'></td>
						</tr>
						<tr>
							<td>".LAN_FORUM_2027."<br />".str_replace(array('[', ']'), array('<b>', '</b>'), LAN_FORUM_2028)."</td>
						</tr>
						<tr>
							<td style='text-align:center;'><textarea cols='40' rows='10' class='tbox' name='report_add'></textarea></td>
						</tr>
						<tr>
							<td colspan='2' style='text-align:center;'><br /><input class='btn btn-default button' type='submit' name='report_thread' value='".LAN_FORUM_2029."' /></td>
						</tr>
						</table>
						</form>";



					}


					return e107::getRender()->tablerender(LAN_FORUM_2023, $text, array('forum_viewtopic', 'report2'), true);
				}

				exit;
				break;

		}
	}
}

?>