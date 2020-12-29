<?php

/*
if ( !@$_GET['keyword'] && @$_POST['keyword'] )
{
	header("Location: /mp3/" . urlencode(trim($_POST['keyword'])) . ".html");
	die();
}
*/

echo '<?xml version="1.0" encoding="UTF-8"?>';

require_once("curl.inc.php");
require_once("queryhist.inc.php");

$keyword = trim($_REQUEST['keyword'], ' /');
//$keyword = '过火';

save_query_hist($keyword);

$status_log = "";


?><!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>搜索下载“<?php echo $keyword; ?>”音乐MP3 - 手机MP3 VIP下载站 - 搜索，然后下载</title>
<meta name="keywords" content="手机歌曲,手机歌曲下载,手机mp4歌曲下载,手机mp3歌曲下载,手机如何下载歌曲,手机怎么下载歌曲,手机怎样下载歌曲" />
<meta name="description" content="本站提供手机歌曲下载,在线试听歌曲,并可以下载到手机上,手机歌曲,手机歌曲下载,手机mp4歌曲下载,手机mp3歌曲下载,手机如何下载歌曲,手机怎么下载歌曲,手机怎样下载歌曲,怎样用手机下载歌曲,怎样下载歌曲到手机,如何下载歌曲到手机" />

<style type="text/css">
body,ul,ol,form{margin:0 0;padding:0 0}
ul,ol{list-style:none}
h1,h2,h3,div,li,p{margin:0 0;padding:2px;font-size:medium}
h2,li,.s{border-bottom:1px solid #ccc}
h1{background:#FF8A00; height:26px;}
h2{background:#EEEEEE}
.n{border:1px solid #ffed00;background:#fffcaa}
.t,.a,.stamp,#ft{color:#999;font-size:small}
a{color:#C55400;}
img{border:0px;}
h1 a{color:#FFFFFF; text-decoration:none;}
</style>
</head>
<body>
<h1><a href="/">手机MP3 VIP下载站</a></h1>

<?php
// only one support.
// google_adsense();
?>

<h2>搜索MP3 - 关键字：<?php echo $keyword; ?></h2>

<?php
$memcache = new Memcache;
$memcache->connect('localhost', 11211) or die ("Could not connect");

$search_url = "http://box.zhangmen.baidu.com/x?op=12&count=1&title=" . urlencode( iconv('UTF-8','GB2312//IGNORE',$keyword) ) . "$$$$$$";

/*
$search_url = "http://box.zhangmen.baidu.com/x?op=12&count=1&title=" . urlencode(iconv('UTF-8','GB2312//IGNORE','大约在冬季'))
																	. "$$" . urlencode(iconv('UTF-8','GB2312//IGNORE','齐秦'))
																	. "$$$$";
$search_url = "http://box.zhangmen.baidu.com/x?op=12&count=1&title=%B4%F3%D4%BC%D4%DA%B6%AC%BC%BE$$%C6%EB%C7%D8$$$$";
*/

$content 	= http_request( $search_url );


//error_log($content);

try
{
	$xml = new SimpleXMLElement( $content );
}catch ( Exception $e ){
	error_log( "search return not xml but: [$content]" );
}
	
//die(var_dump($xml));


//die("[$content]");
//echo $xml->song;

$song_link_list = array();

$song_link_info_cached = array();

foreach ($xml->url as $url) 
{
	$link = preg_replace('#/[^/]+$#', '', $url->encode);
	$link .= '/'.$url->decode;
	$url->link = $link;

	$lrcid		= $url->lrcid;
	$lyric_link = 'http://box.zhangmen.baidu.com/bdlrc/' . floor($lrcid/100) . '/' . $lrcid . '.lrc';
	$url->lyric_link	= $lyric_link;

	$link_key	= "51MP3_LINK_" . md5($link);
	$http_info 	= $memcache->get($link_key);

	if ( !$http_info 
// if conn failed, we also cache it.  //			|| !preg_match('/^\d\d\d$/',$http_info['http_code']) 
		)
	{
		$song_link_list[$link] = $link;
	}else{
		$song_link_info_cached[$link] = $http_info;

		// HIT
		//echo "<!-- http_code of $link - $http_info[http_code] -->\n";
	}

	$link_lyric_key	= "51LYRIC_LINK_" . md5($lyric_link);
	$http_info		= $memcache->get($link_lyric_key);

	if ( !$http_info )
		$lyric_link_list[$lyric_link] = $lyric_link;
	else
		$lyric_link_info_cached[$lyric_link] = $http_info;


//print_r($http_info);
//memcache_debug(true);
	
}

//die(var_dump($song_link_list));
//die(var_dump($xml));

$song_link_info = multiRequestHead($song_link_list);

foreach ( $song_link_list as $link )
{
	$link = ''.$link;

	$link_key	= "51MP3_LINK_" . md5($link);
	

	$memcache->set($link_key, $song_link_info[$link], false, 604800) or die ("Failed to save link data at the server.");
//die(var_dump($song_link_info));
}



$status_log .= "link_hit=" . count($song_link_info_cached) . "|";
$status_log .= "link_unhit=" . count($song_link_info) . "|";

$song_link_info = array_merge($song_link_info,$song_link_info_cached);

//die(var_dump($song_link_info));


/*
 * get lyric link information, then merge with cache
 */

$lyric_link_info = multiRequest( $lyric_link_list );


foreach ( $lyric_link_list as $link )
{
	$link = ''.$link;
	$link_key	= "51LYRIC_LINK_" . md5($link);

	$memcache->set($link_key, $lyric_link_info[$link], false, 604800) or die ("Failed to save link data at the server.");
}

$status_log	.= "lyric_link_hit=" . count($lyric_link_info_cached) . "|";
$status_log	.= "lyric_link_unhit=" . count($lyric_link_info) . "|";

$lyric_link_info = array_merge($lyric_link_info,$lyric_link_info_cached);

//die(var_dump($lyric_link_info));

/*
 *
 * get song detail information
 *

$song[ti-ar-al][title]
$song[ti-ar-al][artist]
$song[ti-ar-al][album]
$song[ti-ar-al][lyric]
$song[ti-ar-al][links] = array ( ( url => '', size => '', format => '' ), ... )

 */

$song	= array();
$songs	= array();

foreach ( $xml->url as $url )
{
	/*
	 * process link info
	 */
	$link			= "" . $url->link;
	$link_http_info	= $song_link_info[$link];


//die($url->lyric_link);

//var_dump($url->lyric_link);
//die(var_dump($lyric_link_info));

	/*
	 * process lyric & title/artist/album
	 */
	$lyric_link	= "" . $url->lyric_link;
	$lyric		= $lyric_link_info[$lyric_link];
	$lyric		= iconv('GB2312','UTF-8//IGNORE',$lyric);

	$title	= 'UNKNOWN';
	if ( preg_match("/\[ti:(.+?)\]/si",$lyric,$matches) )
	{
		$title	= $matches[1];
	}

	$artist	= 'UNKNOWN';
	if ( preg_match("/\[ar:(.+?)\]/si",$lyric,$matches) )
		$artist	= $matches[1];

	$album	= 'UNKNOWN';
	if ( preg_match("/\[al:(.+?)\]/si",$lyric,$matches) )
		$album	= $matches[1];


	/*
	 * store song info
	 */
	$key	= "" . $title . "-" . $artist . "-" . $album;

	$songs[$key][title] 	= $title;
	$songs[$key][artist] 	= $artist;
	$songs[$key][album] 	= $album;
	$songs[$key][lyric]		= $lyric;


	$link_info 			= array();

	$link_info[url]		= "" . $url->link;
	$link_info[size]	= $link_http_info[download_content_length];
	$link_info[code]	= $link_http_info[http_code];

	$url_format			= array ( 1=>'mp3', 2=>'rm', 3=>'wmv', 8=>'baidu' );
	$url_type 			= "" . $url->type;
	$link_info[format]	= $url_format[$url_type];

	if ( !is_array($songs[$key][links]) )
		$songs[$key][links] = array();

	array_push ( $songs[$key][links], $link_info );

//(var_dump($songs[$key][links]));
}


//die(var_dump($songs));

foreach ( $songs as $key => $song )
{
	$valid = false;

	$n = 1;
	foreach ($song[links] as $link_info) 
	{
		$filesize 	= $link_info['size'];
		$format		= $link_info['format'];


		$link_url	= '' . $link_info[url];

		//echo "<a href='$link_url'>#${n}下载地址 $filesize @ $format</a> http_size: $http_size\n";

		//echo "<!-- $link_url $filesize $song->name $http_code-->\n";
		if ( 200!=$link_info['code'] )
		{
			continue;
		}

		if ( !$valid )
		{
			$valid = true;

			echo "<p>\n";
			echo "<a href='/mp3/" . urlencode($song[title]) 	. ".html'>" . htmlspecialchars($song[title]) 	. "</a><br />";
			echo "<a href='/mp3/" . urlencode($song[artist]) 	. ".html'>" . htmlspecialchars($song[artist]) 	. "</a><br />";
			echo "<a href='/mp3/" . urlencode($song[album])		. ".html'>" . htmlspecialchars($song[album])	. "</a><br />";
		}


		$filesize = floor($filesize/1024/1024*100)/100;
		echo "<a rel='nofollow' href='$link_url'>#${n}下载$format(${filesize}MB)</a><br />\n";
		$n++;

/*
		echo $source->filesize, '<br />';
		echo $source->format, '<br />';
		echo $source->content, '<br />';
*/

	}

	if ( $valid )
		echo "</p><hr />\n";
}

?>


<?php
require_once("adsense.inc.php");
?>


<p><strong>手机MP3 VIP下载站：搜索，然后下载</strong></p>
<p>我们致力于帮助手机访问者尽快离开这里</p>

<h2>MP3链接</h2>

<p>5 <a href="http://goby.tv" accesskey="5">重新搜索</a></p>


<div id="ft">&copy; 2008 手机MP3 VIP下载站 
<?php
if ( isset($_SERVER['HTTP_ACCEPT']) 
		&& (strpos($_SERVER['HTTP_ACCEPT'],'vnd.wap.wml')!==FALSE) 
	)
{
	// mobile
}else{
	echo "<a href='http://jiwai.de/goby/thread/12164600/12164600' target='_blank'>留言板</a>\n";
}
?>
</div>


<?php
if ( isset($_SERVER['HTTP_ACCEPT']) 
		&& (strpos($_SERVER['HTTP_ACCEPT'],'vnd.wap.wml')!==FALSE) 
	)
{
	// mobile
}else{
	// pc
?>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
var pageTracker = _gat._getTracker("UA-287835-14");
pageTracker._trackPageview();
</script>
<?php
}
?>

<div><!-- <?php echo $status_log ?> --></div>
</body>
</html>

