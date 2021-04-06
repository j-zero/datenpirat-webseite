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
$content_folder = "./content/dynamic";  // content folder
$static_folder = "./content/static";
$request = "hello_world";       // init page, keep empty for the last post
$title = "d@tenpir.at";
$theme = "dark2";

$content = "";
$id = 0;
$is_welcome_page = false;
$is_first_visit = true;
$active_category = "";

if(isset($_SERVER['HTTP_REFERER']))
    $is_first_visit = !preg_match("/https?:\/\/" . $_SERVER['HTTP_HOST'] . "/i",$_SERVER['HTTP_REFERER']);

$static_pages = list_files($static_folder,".md");

$pages = list_files($content_folder,".md");

//echo "<!-- \$pages\n";var_dump($pages);echo "-->\n";

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
    $active_category = $page['category'];
    $title = htmlentities($page['basename']);
    $content = parse_md_file($file,$pages);
}
else{   // no dynamic page found
    $static_id = find_entry($static_pages,$request);
    if($static_id !== false){  // static page found!
        $page = $static_pages[array_keys($static_pages)[$static_id]];
        $file = $page['file'];
        $content = parse_md_file($file,$pages);
    }
    else{ // nothing found
        http_response_code(404);
        $content = parse_md_file("$static_folder/404.md",$pages);
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
        $cats = array_unique(array_column($pages, 'category'));
        sort($cats);
        foreach($cats as $cat)
            display_category($cat,$pages,$request,$active_category);
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

function display_category($category,$pages,$request,$active_category){
    $p = get_in_array($category,$pages,"category");
    if(!empty($category))
        echo "\t\t<span class=\"category\">" . highlight($category,1) . "</span>\n";
    echo "\t\t<div class=\"" . ($active_category == $category ? " expanded" : "") ."\" id=\"$category\">\n";
    foreach($p as $key => $value)
        display_entry($value,($request == $value["link"]));
    echo "\t\t</div>\n";
    
}

function display_entry($value,$active = false){
    $text = $value["basename"];
    $link = $value["link"];
    $time = $value["time"];
    $category = $value["category"];
    echo "\t\t\t<div class=\"menu_link" . ($active ? " active" : " inactive") . "\">\n";
    echo "\t\t\t\t<a rel=\"keep-params\" class=\"\" href=\"/$link\">" . highlight_sonderzeichen($text) . "</a>\n";
    echo "\t\t\t\t<span class=\"nav_time\">" . date ("d.m.Y H:i", $time) . "</span>\n";
    echo "\t\t\t</div>\n";
}

function highlight_random($text){
    $r = rand(0,strlen($text)-1);
    if($r == 0)
        return "<span class=\"accent\">" . substr($text, $r, 1) . "</span>". substr($text, $r+1);
    else
        return substr($text, 0, $r) . "<span class=\"accent\">" . substr($text, $r, 1) . "</span>". substr($text, $r+1);
}

function highlight_sonderzeichen($text){
    return preg_replace('/([^a-z0-9 ])/i', "<span class=\"accent\">$1</span>", $text);
}

function highlight($text,$r){
    return substr($text, 0, $r) . "<span class=\"accent\">" . substr($text, $r, 1) . "</span>". substr($text, $r+1);
}

function get_in_array( string $needle, array $haystack, string $column ){
    return array_filter( $haystack, function( $item )use( $needle, $column ){
      return $item[ $column ] === $needle;
    });
}

function list_files($dir, $filter, $basedir = null, &$results = array()) {

    $files = scandir($dir);

    foreach ($files as $key => $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_dir($path) && ends_with($file,$filter)) {       // File

            $basename = basename($file, $filter);
            $cache_file = "./cache/" . basename($file). ".cache";
            $category = basename(dirname(trim_start($path, $basedir == null ? $dir : $basedir)));

            $link = format_link((empty($category) ? "" : "$category-") . $basename);

            $time = filemtime($path);
            $cache = "";
            $name = $basename;

            if(file_exists($cache_file)){
                $time = filemtime($cache_file);
                $cache = $cache_file;
            }

            $filename_file = dirname($path) . DIRECTORY_SEPARATOR . basename($file,$filter) . ".name";
            if(file_exists($filename_file)){
                $name = fgets(fopen($filename_file, 'r'));  // read first line
                
            }

            $results[$time . ',' . $link] = array(         // hinzufügen
                "category"  => $category,
                "file"      => realpath($path),
                "basename"  => $name,
                "link"      => $link,
                "cache"      => $cache,
                "time"      => $time
            );

            krsort($results);

        } 
        else if (is_dir($path) && !starts_with($file,'.')) {          // Folder
            list_files($path, $filter, $basedir == null ? $dir : $basedir, $results);
            //echo "Wat? $file";
        }
        else{
            // ignore
        }
    }
    return $results;
}

function starts_with( $haystack, $needle ) {
    return substr( $haystack, 0, strlen( $needle ) ) === $needle;
}
function ends_with( $haystack, $needle ){
    return substr($haystack, -strlen( $needle )) === $needle;
}
function trim_start( $str, $prefix ){
    if (substr($str, 0, strlen($prefix)) == $prefix)
        return substr($str, strlen($prefix));
    return $str;
}
function format_link( $s ){
    $result = preg_replace("/\s+/", "-", strtolower($s));
    $result = preg_replace('/[^A-Z\-a-z0-9_]+/', "", $result);
    return $result;
}
function find_entry($haystack, $needle ){
    return array_search( $needle, array_column($haystack, "link"));
}
function parse_md_file($file, $pages){
    $text = file_get_contents($file);
    if(preg_match("/^\[(github|softlink)\]\((.*)\)$/",$text, $matches, PREG_UNMATCHED_AS_NULL)) {
        $type = $matches[1];
        $url = $matches[2];
        $basename = basename($file);
        $cache_file = "./cache/$basename.cache" ;
        if(file_exists($cache_file)) {
            if(time() - filemtime($cache_file) > 86400) {
                // too old , re-fetch
                $cache = file_get_contents($url);
                if($type === "github")
                    $dt = get_last_commit_time_from_github_api($url);
                file_put_contents($cache_file, $cache);
                touch($cache_file,$dt);
            } else {
                $cache = file_get_contents($cache_file);
            }
        } else {
            // no cache, create one
            $cache = file_get_contents($url);
            if($type === "github")
                $dt = get_last_commit_time_from_github_api($url);
            
            file_put_contents($cache_file, $cache);
            touch($cache_file,$dt);
        }

        $text = $cache;
    }
    $result = Parsedown::instance()
        ->setMarkupEscaped(true) # safe mode
        ->setBreaksEnabled(true) # enables automatic line breaks
        ->text($text); 
    return $result;
}

function get_last_commit_time_from_github_api($url){
    preg_match("/^(.*:\/\/).*?\/(?<user>.*?)\/(?<repo>.*?)\/(?<branch>.*?)\/(?<path>.*?)$/",$url,$m);
    $user = $m["user"];
    $repo = $m["repo"];
    $path = $m["path"];
    $branch = $m["branch"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, "datenpir.at-browser"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $url = "https://api.github.com/repos/$user/$repo/commits?path=$path&sha=$branch";
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = curl_exec($ch);
    $commits = json_decode($response, true);
    curl_close($ch);
    $last_commit = $commits[0]["commit"]["committer"]["date"];
    return DateTime::createFromFormat('Y-m-d\TH:i:s+', $last_commit)->format('U');
}

?>