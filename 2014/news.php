<?php
include __DIR__.'/src/Savant3.php';

$config = [
    'template_path' => array(__DIR__.'/tpl/')
];
$tpl = new Savant3($config);

$tpl->title = "最新公告";
$tpl->mainClass = 'timeline';

$feedData = getFacdbookFeedData();
$tpl->articles = $feedData['entries'];

// Facebook 會惡搞連結元素，把連結元素弄回來原本的 URL 並拔掉奇怪的 hook
$tpl->footer_script = <<<FS
\$(function(){

function getActURL(url) {
    var regex = new RegExp("[\\\\?&]u=([^&#]*)"),
    results = regex.exec(url);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

$('.news-content a').each(function(){
    \$a = $(this);
    \$a.attr('onclick','').attr('onmouseover', ''); // 拔掉 facebook script hook
    var url = \$a.attr('href');
    if (!url) {
        return;
    }
    if (-1 != url.indexOf('http://l.facebook.com/l.php')) {
        var actURL = getActURL(url);
        \$a.attr('href', actURL);
    }
});

});
FS;

// 輸出畫面
$tpl->content = $tpl->getOutput('news.tpl.php');
$tpl->display('main.tpl.php');



//////////////////////////////////////////////////////////////////////////////////////////

function getFacdbookFeedData(){
    $memcache_ok = function_exists("memcache_connect");
    if (!$memcache_ok) {
        $json = fetchFacebookFeed();
        return json_decode($json, $as_array = true);
    }

    $mc = memcache_connect('localhost', 11211);
    $json = memcache_get($mc, 'fb_feed');

    if (!$json) {
        $json = fetchFacebookFeed();
        memcache_set($mc, 'fb_feed', $json, 0, 60*60);
    }

    return json_decode($json, $as_array = true);
}

function fetchFacebookFeed(){
    // set request header
    // Facebook feed.php is not working if it thinks you are not a good browser
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>
            "Host: www.facebook.com\r\n".
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:29.0) Gecko/20100101 Firefox/29.0\r\n".
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n".
            "Accept-Language: zh-tw,zh;q=0.8,en-us;q=0.5,en;q=0.3\r\n"
      )
    );
    $ctx = stream_context_create($opts);

    // fetch the feed
    $a = file_get_contents('http://www.facebook.com/feeds/page.php?format=json&id=219967208129003', false, $ctx);
    return $a;
}
