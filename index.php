<?php
//header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");

require_once("include/parsedown/Parsedown.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/*
//echo "<!--" . $_SERVER['HTTP_HOST'] . "-->";
if(isset($_SERVER['HTTP_REFERER']))
    echo "<!--" . $_SERVER['HTTP_REFERER'] . "-->";

*/
$content_folder = "./content";  // content folder
$request = "hello-world";       // init page, keep empty for the last post
$title = "d@tenpir.at";
$theme = "haxx0r";

$content = "";
$id = 0;
$is_welcome_page = false;
$is_first_visit = true;

if(isset($_SERVER['HTTP_REFERER']))
    $is_first_visit = !preg_match("/https?:\/\/" . $_SERVER['HTTP_HOST'] . "/i",$_SERVER['HTTP_REFERER']);

$pages = list_files($content_folder,".md");
$static_pages = list_files($content_folder . "/static/",".md");

if(isset($_GET['theme']) && !empty($_GET['theme'])){
    $tmp_theme = preg_replace('/[^A-Z\-a-z0-9_]+/', "", $_GET['theme']);
    if(file_exists("./include/themes/$tmp_theme.css"))
        $theme = $tmp_theme;
}

if(isset($_GET['p']) && !empty($_GET['p']))
    $request = format_link($_GET['p']);
else
    $is_welcome_page = true;

if(!empty($request))
    $id = find_entry($pages,$request);

if($id !== false){  // dynamic page found!
    $page = $pages[array_keys($pages)[$id]];
    $file = $page['file'];
    $title = htmlentities($page['basename']);
    $content = parse_md_file("$content_folder/$file");
}
else{   // dynamic page not found
    $static_id = find_entry($static_pages,$request);
    if($static_id !== false){  // static page found!
        $page = $static_pages[array_keys($static_pages)[$static_id]];
        $file = $page['file'];
        $content = parse_md_file("$content_folder/static/$file");
    }
    else{
        http_response_code(404);
        $content = parse_md_file($content_folder . "/static/404.md");
    }
}
?>
<!doctype html>
<html>
<head>
    <title><?php echo "$title"; ?></title>
    <link rel="stylesheet" href="/include/themes/<?php echo $theme; ?>.css">
    <link rel="stylesheet" href="/include/default.css">
</head>
<body>
<div class="sidebar">
    <div class="logo typewriter">
        <div class="typewriter-text <?php echo($is_welcome_page || $is_first_visit ? "typewriter-animation" : ""); ?>">
        <a rel="keep-params" href="/">d<span class="accent">@</span>tenpir<span class="accent">.</span>at</a></div>
    </div>
<?php 
    foreach ($pages as $key => $value){
        $text = $value["basename"];
        $link = $value["link"];
        $time = $value["time"];
        echo "\t\t<a rel=\"keep-params\" " . ($request == $link ? "class=\"active\"" : "") . " href=\"/$link\">$text</a>\n";
        echo "\t\t<span class=\"nav_time\">" . date ("d.m.Y H:i", $time) . "</span>\n";
    }
?>
    <div class="static">
<?php 
        echo "\t\t<a rel=\"keep-params\" " . ($request == "datenschutz" ? "class=\"active\"" : "") . "href=\"datenschutz\">Datenschutz</a>\n";
        echo "\t\t<a rel=\"keep-params\" " . ($request == "impressum" ? "class=\"active\"" : "") . "href=\"impressum\">Impressum</a>\n";
?>
    </div>
</div>
<div class="content">
<?php echo $content; ?>
</div>
</body>
</html>
<?php
function list_files($path, $filter){
    $dir = opendir($path);
    $list = array();
    while($file = readdir($dir)){
        if(!starts_with($file,'.') && ends_with($file,$filter)){
            $basename = basename($file, $filter);
            $cache_file = "$path/cache/" . basename($file). ".cache";
            $link = format_link($basename);
            $time = filemtime("$path/$file");

            if(file_exists($cache_file))
                $time = filemtime($cache_file);
            
            $list[$time . ',' . $link] = array(
                "file"=>$file,
                "basename"=>$basename,
                "link"=>$link,
                "time"=>$time
            );
        }
    }
    closedir($dir);
    krsort($list);
    return $list;
}

function starts_with( $haystack, $needle ) {
    return substr( $haystack, 0, strlen( $needle ) ) === $needle;
}
function ends_with( $haystack, $needle ){
    return substr($haystack, -strlen( $needle )) === $needle;
}
function format_link( $s ){
    $result = preg_replace("/\s+/", "-", strtolower($s));
    $result = preg_replace('/[^A-Z\-a-z0-9_]+/', "", $result);
    return $result;
}
function find_entry($haystack, $needle ){
    return array_search( $needle, array_column($haystack, "link"));
}
function parse_md_file($file){
    $text = file_get_contents($file);
    if(preg_match("/^\[softlink\]\((.*)\)$/",$text, $matches, PREG_UNMATCHED_AS_NULL)) {
        $url = $matches[1];
        $basename = basename($file);
        $cache_file = "./content/cache/$basename.cache" ;
        if(file_exists($cache_file)) {
            if(time() - filemtime($cache_file) > 86400) {
                // too old , re-fetch
                $cache = file_get_contents($url);
                $dt = get_last_commit_time_from_github_api($url);
                file_put_contents($cache_file, $cache);
                touch($cache_file,$dt);
            } else {
                $cache = file_get_contents($cache_file);
            }
        } else {
            // no cache, create one
            $cache = file_get_contents($url);
            $dt = get_last_commit_time_from_github_api($url);
            
            file_put_contents($cache_file, $cache);
            touch($cache_file,$dt);
        }

        $text = $cache;
    }
    return Parsedown::instance()
        ->setMarkupEscaped(true) # safe mode
        ->setBreaksEnabled(true) # enables automatic line breaks
        ->text($text); 
}

function get_last_commit_time_from_github_api($url){
    
    preg_match("/^(.*:\/\/).*?\/(?<user>.*?)\/(?<repo>.*?)\/(?<branch>.*?)\/(?<path>.*?)$/",$url,$m);
    //var_dump($m);
    $user = $m["user"];
    $repo = $m["repo"];
    $path = $m["path"];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, "datenpir.at-browser"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $url = "https://api.github.com/repos/$user/$repo/commits?path=$path";
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = curl_exec($ch);
    $commits = json_decode($response, true);
    curl_close($ch);
    $last_commit = $commits[0]["commit"]["committer"]["date"];
    return DateTime::createFromFormat('Y-m-d\TH:i:s+', $last_commit)->format('U');
}



?>