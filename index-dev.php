<?php
//header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");

require_once("include/parsedown/Parsedown.php");

/*ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);
error_reporting(E_ALL);*/

/*
//echo "<!--" . $_SERVER['HTTP_HOST'] . "-->";
if(isset($_SERVER['HTTP_REFERER']))
    echo "<!--" . $_SERVER['HTTP_REFERER'] . "-->";
*/
$content_folder = "./content";  // content folder
$static_folder = "./static";
$request = "thats-me";       // init page, keep empty for the last post
$title = "fl0rian.sch&uuml;tte";
$theme = "dark";

$content = "";
$id = 0;
$is_welcome_page = false;
$is_first_visit = true;

if(isset($_SERVER['HTTP_REFERER']))
    $is_first_visit = !preg_match("/https?:\/\/" . $_SERVER['HTTP_HOST'] . "/i",$_SERVER['HTTP_REFERER']);

$pages = list_files($content_folder,".md");
$static_pages = list_files($static_folder,".md");

if(isset($_GET['theme']) && !empty($_GET['theme'])){
    $tmp_theme = preg_replace('/[^A-Z\-a-z0-9_]+/', "", $_GET['theme']);
    if(file_exists("./include/themes/$tmp_theme.css"))
        $theme = $tmp_theme;
}

if(isset($_GET['p']) && !empty($_GET['p']))
    $request = $_GET['p']; //format_link($_GET['p']);
else
    $is_welcome_page = true;

if(!empty($request))
    $id = find_entry($pages,$request);

if($id !== false){  // dynamic page found!
    $page = $pages[array_keys($pages)[$id]];
    $file = $page['file'];
    $subDir = $page['dir'] ? $page['dir'].'/' : '';
    $title = htmlentities($page['basename']);
    $content = parse_md_file($content_folder, $subDir.$file);
}
else{   // dynamic page not found
    $static_id = find_entry($static_pages,$request);
    if($static_id !== false){  // static page found!
        $page = $static_pages[array_keys($static_pages)[$static_id]];
        $file = $page['file'];
        $content = parse_md_file($static_folder, "/$file");
    }
    else{
        http_response_code(404);
        $content = parse_md_file($static_folder, "/404.md");
    }
}

?>

<!doctype html>
<html>
    <head>
        <title><?php echo "$title"; ?></title>
        <link rel="stylesheet" href="/include/themes/<?php echo $theme; ?>.css">
        <link rel="stylesheet" href="/include/default.css">
        <link rel="stylesheet" href="/include/prism.css">
    </head>
    <body class="line-numbers">
        <div class="sidebar">
            <div class="logo typewriter ">
                <div class="typewriter-text <?php echo($is_welcome_page || $is_first_visit ? "typewriter-animation" : ""); ?>">
                    <a rel="keep-params" href="/">fl<span class="accent">0</span>rian.sch<span class="accent">&uuml;</span>tte</a>
                </div>
            </div>
        <?php 

            $subMenus = array();
            $selected = '';

            if (strpos($request, '/') !== false)
                list($selected) = explode('/', $request);

            foreach ($pages as $keyTop => $valueTop){

                if (!in_array($valueTop['dir'], $subMenus)) {
                    array_push($subMenus, $valueTop['dir']);

                    if($valueTop['dir'] !== ""){
                        $sel = !boolval(strcmp($valueTop['dir'], $selected));
                        echo "\t\t<a id=\"".$valueTop['dir']."-head\" rel=\"keep-params\" class=\"menu-head ".($sel? 'selected' : '')."\" href=\"#\" onclick=\"showMenu('".$valueTop['dir']."')\">".$valueTop['dir']."</a>\n";
                        echo "\t\t<div id=\"".$valueTop['dir']."-list\" style=\"display:".($sel?"block":"none").";\" class=\"sub-menu\">";
                    }

                    foreach ($pages as $key => $value){

                        if($value['dir'] == $valueTop['dir']) {
                            $text = $value["basename"];
                            $link = $value["link"];
                            $time = $value["time"];
                            echo "\t\t<a rel=\"keep-params\" " . ($request == $link ? "class=\"active\"" : "") . " href=\"/$link\">$text</a>\n";
                            echo "\t\t<span class=\"nav-time\">" . date ("d.m.Y H:i", $time) . "</span>\n";
                        }

                    }

                    if($valueTop['dir'] != "")
                        echo "\t\t</div>\n";
                }
                
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
        <script src="/include/prism.js"></script>
        <script>
            Prism.plugins.autoloader.languages_path = '/include/prism_lang/';

            setTimeout(function(){
                els = document.getElementsByClassName('typewriter-text');
                els[0].classList.add("glitch-animation");
            },  2500);

            function showMenu(name) {
                els = document.getElementsByClassName('sub-menu');
                [].forEach.call(els, function (el) {
                    el.style.display = "none";
                });
                
                el = document.getElementById(name+'-head');

                if(el.classList.contains("selected")) {
                    el.classList.remove("selected");
                }
                else {
                    document.getElementById(name+'-list').style.display="block";
                    els = document.getElementsByClassName('menu-head');
                    [].forEach.call(els, function (el) {
                        el.classList.remove("selected");
                    });
                    el.classList.add("selected"); 
                }
                
            }
        </script>
    </body>
</html>
<?php
function list_files($path, $filter, $subDir=""){
    $dir = opendir($path);
    $list = array();
    while($file = readdir($dir)){
        if(!starts_with($file,'.') && is_dir($path.'/'.$file)){
            $subList = list_files($path.'/'.$file, $filter, ($subDir? $subDir.'/'.$file : $file));
            $list = array_merge($list, $subList);
        }
        if(!starts_with($file,'.') && ends_with($file,$filter)){
            $basename = basename($file, $filter);
            $cache_file = "$path/cache/" . basename($file). ".cache";
            $link = ($subDir? $subDir.'/' : '').format_link($basename);
            $time = filemtime("$path/$file");

            if(file_exists($cache_file))
                $time = filemtime($cache_file);
            
            $list[$time . ',' . $link] = array(
                "file"=>$file,
                "basename"=>$basename,
                "link"=>$link,
                "time"=>$time,
                "dir"=>$subDir
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

function parse_md_file($content_folder, $file){
    $text = file_get_contents("$content_folder/$file");
    if(preg_match("/^\[(github|softlink)\]\((.*)\)$/",$text, $matches, PREG_UNMATCHED_AS_NULL)) {
        $type = $matches[1];
        $url = $matches[2];
        $basename = basename($file);
        $cache_file = "$content_folder/cache/$basename.cache" ;
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
            if($cache === false) {
                http_response_code(404);
                $cache = file_get_contents("$content_folder/static/404.md");
            }
            else {
                if($type === "github")
                    $dt = get_last_commit_time_from_github_api($url);
                
                file_put_contents($cache_file, $cache);
                touch($cache_file,$dt);
            }
        }

        $text = $cache;
    }
    return Parsedown::instance()
        ->setMarkupEscaped(false) # safe mode
        ->setBreaksEnabled(true) # enables automatic line breaks
        ->text($text); 
}

function get_last_commit_time_from_github_api($url){ 
    preg_match("/^(.*:\/\/).*?\/(?<user>.*?)\/(?<repo>.*?)\/(?<branch>.*?)\/(?<path>.*?)$/",$url,$m);
    //var_dump($m);
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
