<?php
require_once("include/parsedown/Parsedown.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
<?php
$content_folder = "./content";  // content folder
$request = "hello-world";       // init page, keep empty for the last post

$content = "";
$title = "";
$id = 0;

$pages = list_files($content_folder,".md");
$static_pages = list_files($content_folder . "/static/",".md");

if(isset($_GET['p']) && !empty($_GET['p']))
    $request = format_link($_GET['p']);

if(!empty($request))
    $id = find_entry($pages,$request);

if($id !== false){  // page found!
    $page = $pages[array_keys($pages)[$id]];
    $file = $page['file'];
    $title = htmlentities($page['basename']);
    $content = parse_file("$content_folder/$file");
}
else{   // dynamic page not found
    $static_id = find_entry($static_pages,$request);
    if($static_id !== false){  // static page found!
        $page = $static_pages[array_keys($static_pages)[$static_id]];
        $file = $page['file'];
        $content = parse_file("$content_folder/static/$file");
    }
    else{
        http_response_code(404);
        $content = parse_file($content_folder . "/static/404.md");
    }
}
?>
<!doctype html>
<html>
<head>
    <title><?php echo "d@tenpir.at - $title"; ?></title>
    <link rel="stylesheet" href="/include/dark.css">
</head>
<body>
<div class="sidebar">
    <div class="logo typewriter">
        <div class="typewriter-text"><a href="/">d<span class="red">@</span>tenpir<span class="red">.</span>at</a></div>
    </div>
    
    <?php 
        foreach ($pages as $key => $value){
            $text = $value["basename"];
            $link = $value["link"];
            $time = $value["time"];
            echo "<a " . ($request == $link ? "class=\"active\"" : "") . " href=\"$link\">$text</a>\n";
            echo "<span class=\"nav_time\">" . date ("d.m.Y H:i", $time) . "</span>\n";
        }
    ?>
    <div class="static">
        <?php 
            echo "<a " . ($request == "datenschutz" ? "class=\"active\"" : "") . "href=\"datenschutz\">Datenschutz</a>\n";
            echo "<a " . ($request == "impressum" ? "class=\"active\"" : "") . "href=\"impressum\">Impressum</a>\n";
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
            $link = format_link($basename);
            $time = filemtime("$path/$file");
            $list[$time . ',' . $link] = array("file"=>$file,"basename"=>$basename,"link"=>$link,"time"=>$time);
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
    $result = preg_replace('/[^A-Z\-a-z_]+/', "", $result);
    return $result;
}
function find_entry($haystack, $needle ){
    return array_search( $needle, array_column($haystack, "link"));
}
function parse_file($file){
    $text = file_get_contents($file);
    return Parsedown::instance()
        ->setBreaksEnabled(true) # enables automatic line breaks
        ->text($text); 
}
?>