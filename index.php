<?php

header('Content-Type: text/html; charset=UTF-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once('./cfg.php');
if(isset($_GET['_pjax']) || isset($_POST['_pjax'])){ $cfg['pj'] = true; }

// Get DB
require_once(ROOT.'classes/db.php');
$db = new db();
$res = $db->connect($cfg['host'],$cfg['user'],$cfg['pass'],$cfg['base']);

// Get Skin
require_once(ROOT.'classes/skin.php');
$skin = new skin();

// Get Functions
require_once(ROOT.'classes/func.php');
$f = new func();

// Get local counters for top menu
$lc = $db->query_row("SELECT * FROM vk_counters");

if(!$cfg['pj']){
	print $skin->header(array('extend'=>''));
	print $skin->navigation($lc);
}

print <<<E
    <div class="container-fluid">
      <div class="row">
	  <div class="col-sm-3 col-md-2 sidebar indexbar">
          <ul class="nav nav-sidebar">
		  <li class="col-xs-6 col-sm-12" style="padding:10px 20px;">
E;

// Include VK.API
require_once(ROOT.'classes/VK/VK.php');

// Check token
$q = $db->query("SELECT * FROM vk_session WHERE `vk_id` = 1");
$vk_session = $row = $db->return_row($q);
$token_valid = false;

// Empty counters
$counters_show = array(
	'albums' => 0,
	'photos' => 0,
	'audios' => 0,
	'videos' => 0,
	'docs'   => 0
);

if($vk_session['vk_token']){
	$vk = new VK($cfg['vk_id'], $cfg['vk_secret'], $vk_session['vk_token']);
	$token_valid = $vk->checkAccessToken($vk_session['vk_token']);
} else {
	$vk = new VK($cfg['vk_id'], $cfg['vk_secret']);
}
// Set API version
$vk->setApiVersion(5.45);

if($vk_session['vk_token'] != '' && $token_valid == true){
	
	try {
			// We logged in, get personal info
			$user = $vk->api('users.get', array(
				'user_id' => $vk_session['vk_user'],
				'fields' => 'first_name,last_name,has_photo,photo_200_orig,counters,nickname'
			));
			
			$u = $user['response'][0];
			
	print "<center>";
			if($u['has_photo']){
print <<<E
<img class="img-thumbnail" alt="200x200" style="width: 200px;" src="{$u['photo_200_orig']}" data-holder-rendered="true">
E;
			}
print <<<E
			<h4><a href="https://vk.com/id{$u['id']}" target="_blank">{$u['nickname']}</a> <a href="ajax/auth.php?do=logout" data-pjauth><i class="fa fa-sign-out"></i></a></h4>
			{$u['first_name']} {$u['last_name']}
		    </center>
		  </li>
		  <li class="col-xs-6 col-sm-12">
		    <ul class="nav nav-pills">
				<li style="width:100%;text-align:center;">Данные из ВК:</li>
E;
			
			// GET REAL ALBUMS
			$albums = $vk->api('photos.getAlbums', array(
				'owner_id' => $vk_session['vk_user'],
				'need_system' => '1'
			));
			
			$counters_show['albums'] = $albums['response']['count'];
			$counters_show['photos'] = 0;
			foreach($albums['response']['items'] as $k => $v){
				$counters_show['photos'] += $v['size'];
			}

			// GET AUDIO Count
			$music = $vk->api('audio.getCount', array(
				'owner_id' => $vk_session['vk_user']
			));
		if(isset($music['response'])){
				$counters_show['audios'] = $music['response'];
		} else {
			$counters_show['audios'] = 0;
			}
			// GET VIDEO Count
			$video = $vk->api('video.get', array(
				'owner_id' => $vk_session['vk_user'],
				'count' => 0,
				'offset' => 0,
				'extended' => 0
			));
			if(isset($video['response']) && $video['response']['count']){
				$counters_show['videos'] = $video['response']['count'];
			} else {
				$counters_show['videos'] = 'n/a';
			}
		
		// GET DOCUMENTS Count
		$docs = $vk->api('docs.get', array(
			'owner_id' => $vk_session['vk_user'],
			'count' => 1,
			'offset' => 0
		));
		if(isset($docs['response'])){
			$counters_show['docs'] = $docs['response']['count'];
		} else {
			$counters_show['docs'] = 0;
		}

			foreach($counters_show as $k => $v){
				if($k == 'albums') { $k = '<i class="fa fa-folder"></i> Альбомы'; }
				if($k == 'photos') { $k = '<i class="fa fa-image"></i> Фото'; }
				if($k == 'audios') { $k = '<i class="fa fa-music"></i> Музыка'; }
				if($k == 'videos') { $k = '<i class="fa fa-film"></i> Видео'; }
			if($k == 'docs') { $k = '<i class="fa fa-file"></i> Документы'; }
			print '<li style="width:100%;"><a href="javascript:;">'.$k.': <span class="badge">'.$v.'</span></a></li>';
			}
			
		print '</ul>';

	} catch (Exception $error) {
		echo $error->getMessage();
	}

} else {

	try {
    
    if (!isset($_REQUEST['code'])) {
        /**
         * If you need switch the application in test mode,
         * add another parameter "true". Default value "false".
         * Ex. $vk->getAuthorizeURL($api_settings, $callback_url, true);
         */
        $authorize_url = $vk->getAuthorizeURL('offline,status,photos,audio,video,docs',$cfg['vk_uri']);
        echo '<a href="' . $authorize_url . '" class="btn btn-success" role="button">Авторизация</a>';
    } else {
        $access_token = $vk->getAccessToken($_REQUEST['code'], $cfg['vk_uri']);

		// If we get token, save it!
		if($access_token['access_token']){
			$q = $db->query("REPLACE INTO vk_session (`vk_id`,`vk_token`, `vk_expire`, `vk_user`) VALUES (1,'{$access_token['access_token']}','{$access_token['expires_in']}','{$access_token['user_id']}')");
		}
		
		print '<h4><span class="label label-success">Авторизация пройдена</span></h4>';
    }
	} catch (Exception $error) {
    echo $error->getMessage();
	}
} // end if token else

print <<<E
				</li>
          </ul>
        </div>
E;

// Get LOCAL counters for media
$counters = $db->query_row("SELECT * FROM vk_counters");
$music_albums = $db->query_row("SELECT count(id) as count FROM vk_music_albums");
$wall_attachments = $db->query_row("SELECT count(uid) as count FROM vk_attach");

print <<<E
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
          <div class="row placeholders">
            <div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['album'])}</h1>
              <span class="text-muted">Альбомы&nbsp;&nbsp;<a href="sync.php?do=albums"><i class="fa fa-refresh"></i></a></span>
            </div>
            <div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['photo'])}</h1>
              <span class="text-muted">Фотографии&nbsp;&nbsp;
			  
				<div class="dropdown" style="display:inline-block;"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-refresh"></i></a>
					<ul class="dropdown-menu">
						<li><a href="sync.php?do=photo"><i class="fa fa-hourglass"></i> Полная</a></li>
						<li><a href="sync.php?do=photo&fast=true"><i class="fa fa-hourglass-end"></i> Быстрая</a></li>
					</ul>
				</div>
				
			  </span>
            </div>
			<div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($music_albums['count'])}</h1>
              <span class="text-muted">Альбомы музыки&nbsp;&nbsp;<a href="sync.php?do=musicalbums"><i class="fa fa-refresh"></i></a></span>
            </div>
            <div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['music'])}</h1>
              <span class="text-muted">Музыка&nbsp;&nbsp;<a href="sync.php?do=music"><i class="fa fa-refresh"></i></a></span>
            </div>
			<div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['video'])}</h1>
              <span class="text-muted">Видео&nbsp;&nbsp;<a href="sync.php?do=video"><i class="fa fa-refresh"></i></a></span>
            </div>
            <div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['wall'])}</h1>
              <span class="text-muted">Стена&nbsp;&nbsp;
			  
				<div class="dropdown" style="display:inline-block;"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-refresh"></i></a>
					<ul class="dropdown-menu">
						<li><a href="sync-wall.php?offset=0"><i class="fa fa-hourglass"></i> Полная</a></li>
						<li><a href="sync-wall.php?offset=0&fast=true"><i class="fa fa-hourglass-end"></i> Быстрая</a></li>
					</ul>
				</div>
				
			  </span>
            </div>
            <div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($counters['docs'])}</h1>
              <span class="text-muted">Документы&nbsp;&nbsp;<a href="sync.php?do=docs"><i class="fa fa-refresh"></i></a></span>
            </div>
			<div class="col-xs-6 col-sm-3 placeholder">
              <h1>{$f->human_thousand($wall_attachments['count'])}</h1>
              <span class="text-muted">Вложения</span>
            </div>
          </div>
          
		  <div class="row white-box">
			<div class="table-responsive">
				<h4 class="vkhead"><i class="fa fa-info-circle"></i> Уведомления</h4>
	            <table class="table table-striped" style="margin-bottom:0;">
		          <tbody>
E;

if($counters_show['albums'] != 0 && $counters_show['albums'] > $counters['album']){
print <<<E
<tr><td>Количество <b>альбомов</b> изменилось, необходима синхронизация. <a href="sync.php?do=albums">Синхронизировать</a> сейчас?</td></tr>
E;
}
if($counters_show['photos'] != 0 && $counters_show['photos'] > $counters['photo']){
	$d = $counters_show['photos'] - $counters['photo'];
	if($d > 0){ $d = '(+<b>'.$d.'</b>)'; }
print <<<E
<tr><td>Количество <b>фотографий</b> изменилось {$d}, необходима синхронизация. <a href="sync.php?do=photo">Синхронизировать</a> сейчас?</td></tr>
E;
}
if($counters_show['audios'] != 0 && $counters_show['audios'] > $counters['music']){
	$d = $counters_show['audios'] - $counters['music'];
	if($d > 0){ $d = '(+<b>'.$d.'</b>)'; }
print <<<E
<tr><td>Количество <b>аудиозаписей</b> изменилось {$d}, необходима синхронизация. <a href="sync.php?do=music">Синхронизировать</a> сейчас?</td></tr>
E;
}
if($counters_show['videos'] != 0 && $counters_show['videos'] > $counters['video']){
	$d = $counters_show['videos'] - $counters['video'];
	if($d > 0){ $d = '(+<b>'.$d.'</b>)'; }
print <<<E
<tr><td>Количество <b>видеозаписей</b> изменилось {$d}, необходима синхронизация. <a href="sync.php?do=video">Синхронизировать</a> сейчас?</td></tr>
E;
}
if($counters_show['docs'] != 0 && $counters_show['docs'] > $counters['docs']){
	$d = $counters_show['docs'] - $counters['docs'];
	if($d > 0){ $d = '(+<b>'.$d.'</b>)'; }
print <<<E
<tr><td>Количество <b>документов</b> изменилось {$d}, необходима синхронизация. <a href="sync.php?do=docs">Синхронизировать</a> сейчас?</td></tr>
E;
}

print <<<E
			      </tbody>
		        </table>
	          </div>
		  </div><br/>
		  <div class="row white-box">
E;

// Get LOCAL queue (photo,music,video,docs)
$queue_count = array('p'=>0,'m'=>0,'v'=>0,'dc'=>0);
$queue_photo = $db->query_row("SELECT COUNT(*) as count FROM vk_photos WHERE `in_queue` = 1");
$queue_count['p'] = $queue_photo['count'];
$queue_music = $db->query_row("SELECT COUNT(*) as count FROM vk_music WHERE `in_queue` = 1");
$queue_count['m'] = $queue_music['count'];
$queue_video = $db->query_row("SELECT COUNT(*) as count FROM vk_videos WHERE `in_queue` = 1");
$queue_count['v'] = $queue_video['count'];
$queue_docs = $db->query_row("SELECT COUNT(*) as count FROM vk_docs WHERE `in_queue` = 1");
$queue_count['dc'] = $queue_docs['count'];
$queue_total = $queue_count['p']+$queue_count['m']+$queue_count['v']+$queue_count['dc'];

print <<<E
          <div class="table-responsive">
		  <h4 class="vkhead"><i class="fa fa-cloud-download"></i> Очередь закачки - <b>{$queue_total}</b></h4>
            <table class="table table-striped" style="margin-bottom:0;">
              <thead>
                <tr>
                  <th>#</th>
				  <th>URL</th>
				  <th>Добавлено</th>
                </tr>
              </thead>
              <tbody>
E;

if($queue_count['p'] > 0){
	$r = $db->query("SELECT * FROM vk_photos WHERE `in_queue` = 1 ORDER BY date_added DESC LIMIT 0,5");
	while($row = $db->return_row($r)){
		$row['date_added'] = date("Y-m-d H:i:s",$row['date_added']);
print <<<E
<tr>
  <td>{$row['id']}</td>
  <td><i class="fa fa-file-image-o"></i> <a href="{$row['uri']}" target="_blank">{$row['uri']}</a></td>
  <td>{$row['date_added']}</td>
</tr>
E;
	}
}

if($queue_count['m'] > 0) {
	$r = $db->query("SELECT * FROM vk_music WHERE `in_queue` = 1 ORDER BY date_added DESC LIMIT 0,5");
	while($row = $db->return_row($r)){
		$row['date_added'] = date("Y-m-d H:i:s",$row['date_added']);
		$row['uri_a'] = preg_replace("/\?extra\=.*/","",$row['uri']);
print <<<E
<tr>
  <td>{$row['id']}</td>
  <td><i class="fa fa-file-audio-o"></i> <a href="{$row['uri']}" target="_blank">{$row['uri_a']}</a></td>
  <td>{$row['date_added']}</td>
</tr>
E;
	}
}

if($queue_count['v'] > 0) {
	$r = $db->query("SELECT * FROM vk_videos WHERE `in_queue` = 1 ORDER BY date_added DESC LIMIT 0,5");
	while($row = $db->return_row($r)){
		$row['date_added'] = date("Y-m-d H:i:s",$row['date_added']);
print <<<E
<tr>
  <td>{$row['id']}</td>
  <td><i class="fa fa-file-video-o"></i> <a href="{$row['preview_uri']}" target="_blank">{$row['preview_uri']}</a></td>
  <td>{$row['date_added']}</td>
</tr>
E;
	}
}

if($queue_count['dc'] > 0) {
	$r = $db->query("SELECT * FROM vk_docs WHERE `in_queue` = 1 ORDER BY date DESC LIMIT 0,5");
	while($row = $db->return_row($r)){
		$row['date'] = date("Y-m-d H:i:s",$row['date']);
print <<<E
<tr>
  <td>{$row['id']}</td>
  <td><i class="fa fa-file-o"></i> <a href="{$row['uri']}" target="_blank">{$row['title']}</a></td>
  <td>{$row['date']}</td>
</tr>
E;
	}
}

if($queue_total == 0) {
	print '<tr><td colspan="3" style="text-align:center;color:#bbb;">Очередь закачки пуста</td></tr>';
}

if($queue_total){
print <<<E
<tr>
  <td colspan="3" style="text-align:right;"><a href="queue.php">посмотреть всю очередь &raquo;</a></td>
</tr>
E;
}

print <<<E
              </tbody>
            </table>
          </div>
		  </div>
        </div>
      </div>
	  
    </div>

E;

if(!$cfg['pj']){
	print $skin->footer(array('extend'=>''));
}

$db->close($res);

?>