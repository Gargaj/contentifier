<?php
include_once("sqllib.inc.php");
include_once("contentifier-admin.inc.php");

abstract class ContentifierPlugin
{
  abstract public function id();
  abstract public function adminmenuitem();
  public function admin() { return null; }
}

abstract class ContentifierPagePlugin extends ContentifierPlugin
{
  abstract public function slugregex();
  abstract public function content($match);
}

abstract class ContentifierShortCodePlugin extends ContentifierPlugin
{
  abstract public function shortcode();
  abstract public function content($params);
}

abstract class Contentifier
{
  // API FUNCTIONS
  public function sqldsn() { return "mysql:host=localhost;dbname=contentifier"; }
  public function sqluser() { return "contentifier"; }
  abstract public function sqlpass();
  public function sqloptions() { return array(); }

  public function mediadir() { return "media"; }
  public function thumbdir($res) { return $this->mediadir()."/thumb".(int)$res."px"; }
  public function thumb($fn,$res) { return $this->mediadir()."/thumb".(int)$res."px/".basename($fn); }

  public function templatefile() { return "template.html"; }
  public function rewriteenabled() { return false; }
  public function rooturl() { return $this->rootURL; }
  public function slug() { return $this->slug; }

  private $plugins = array();
  private $sql;
  private $rootURL;
  private $slug;
  private $url;
  private $rootRelativeURL;
  private $rootRelativePath;

  use ContentifierAdmin;

  function escape($s)
  {
    return htmlspecialchars($s,ENT_QUOTES);
  }
  function sanitize($s)
  {
    return preg_replace("/([^a-zA-Z0-9\.]+)/im","_",$s);
  }
  function buildurl($slug,$params=array())
  {
    if ($slug=="/") $slug = "";
    if ($this->rewriteenabled())
    {
      return $this->rooturl().($slug?$slug."/":"").($params?"?".http_build_query($params):"");
    }
    else
    {
      $params["page"] = $slug;
      return $this->rooturl()."?".http_build_query($params);
    }
  }
  function redirect($url)
  {
    header("Location: ".$url);
    exit();
  }
  function initurls()
  {
    $this->url = ($_SERVER["HTTPS"]=="on"?"https":"http").":/"."/".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];

    $dir = dirname($_SERVER["PHP_SELF"]);

    $this->rootRelativeURL = $_SERVER['REQUEST_URI'];
    if (strlen($dir) > 1)
    {
      $this->rootRelativeURL = substr($this->rootRelativeURL,strlen($dir));
    }
    list($this->rootRelativePath,) = explode("?",$this->rootRelativeURL);
    $this->rootURL = substr($this->url,0,-strlen($this->rootRelativeURL))."/";
  }
  function extractslug()
  {
    $this->slug = "";
    if ($this->rewriteenabled())
    {
      $this->slug = trim($this->rootRelativePath,'/');
    }
    else
    {
      $this->slug = @$_GET["page"];
    }
    if (!$this->slug)
    {
      $this->slug = "index";
    }
  }
  function menu()
  {
    $rows = $this->sql->selectRows($this->sql->escape("select * from menu order by `order`"));
    if ($rows)
    {
      $out = "<ul>";
      foreach($rows as $row)
      {
        $url = $row->url;
        if (strstr($url,":/"."/")===false)
        {
          $url = $this->buildurl(trim($url,"/"));
        }
        $out .= "<li>";
        $out .= "<a href='".$this->escape($url)."'>".$this->escape($row->label)."</a>";
        $out .= "</li>";
      }
      $out .= "</ul>";
    }
    return $out;
  }
  function addplugin( $instance )
  {
    if (is_a($instance,"ContentifierPlugin"))
    {
      $this->plugins[] = $instance;
    }
  }
  function content( $slug = null )
  {
    $slug = $slug?:$this->slug;
    foreach($this->plugins as $plugin)
    {
      if (is_a($plugin,"ContentifierPagePlugin") && preg_match("/".$plugin->slugregex()."/",$slug,$match))
      {
        return $plugin->content($match);
      }
    }
    $row = $this->getpagebyslug($slug);
    if ($row)
    {
      $content = $row->content;
      foreach($this->plugins as $plugin)
      {
        if (is_a($plugin,"ContentifierShortCodePlugin"))
        {
          $content = preg_replace_callback("/\{\{".$plugin->shortcode()."\|?(.*)\}\}/",function($matches)use($plugin){
            return $plugin->content(explode("|",$matches[1]));
          },$content);
        }
      }
      switch($row->format)
      {
        case "text": $content = nl2br($this->escape($content)); break;
      }
      return $content;
    }
    return false;
  }
  function contenttokens()
  {
    $content = $this->content();
    if ($content === false || $content === null)
    {
      header("HTTP/1.1 404 Not Found");
      $content = "<h1>404</h1><p>Page '".$this->escape($this->slug)."' not found</p>";
    }
    return array(
      "{%MENU%}" => $this->menu(),
      "{%CONTENT%}" => $content,
      "{%ROOTURL%}" => $this->rooturl(),
      "{%SLUG%}" => $this->slug(),
    );
  }
  function template()
  {
    return file_exists($this->templatefile()) ? file_get_contents($this->templatefile()) : "<!DOCTYPE html>\n<html><body><nav>{%MENU%}</nav>{%CONTENT%}</body></html>";
  }
  function render()
  {
    $tokens = $this->contenttokens();
    $template = $this->template();
    $template = str_replace(array_keys($tokens),array_values($tokens),$template);
    echo $template;
  }
  public function getpagebyslug($slug)
  {
    return $this->sql->selectRow($this->sql->escape("select * from pages where slug='%s'",$slug));
  }
  public function bootstrap()
  {
    if (!class_exists("PDO")) die("Contentifier runs on PDO - please install it");
    $this->sql = new SQLLib();
    $this->sql->Connect($this->sqldsn(),$this->sqluser(),$this->sqlpass(),$this->sqloptions());
  }
  public function install()
  {
    $this->bootstrap();
    $output = "<!DOCTYPE html>\n<html>".
    "<head>".
    "<title>Contentifier Install</title>".
    "<style type=\"text/css\">".
    "*{margin:0;padding:0;}".
    "body {font-family:'segoe ui',sans-serif;background:#ccc;}".
    "a {color:black;}".
    "h1 {background:#444;color:white;border:0;border-bottom:1px solid #888;padding:5px;}".
    "input{display:block;width:100%;padding:5px;margin:5px 0px;}".
    "textarea{height:calc(100vh - 400px);}".
    "input.submit{width:90%;display:inline-block;}".
    "input.delete{width:10%;display:inline-block;}".
    "label{color:#888;font-size:60%;}".
    "label.radio{display:inline-block;margin-right:10px;vertical-align:sub;color:#444;font-size:80%;}".
    "label.radio input{display:inline;width:auto;}".
    "form{width:300px;margin:30px auto;}".
    "p{width:300px;margin:30px auto;}".
    "code{font-family;monospace;background:#eee;color:black;}".
    "footer, footer a{color:#999;margin:5px;font-size:10px;text-align:right;}".
    "</style>".
    "</head>".
    "<body>".
    "<h1>Contentifier Installation</h1>";
    if (@$_POST["username"] && @$_POST["password"])
    {
      $isMysql = strstr($this->sqldsn(),"mysql")!==false;
      $primary = $isMysql ? "int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT" : "INTEGER PRIMARY KEY AUTOINCREMENT";
      $enum = $isMysql ? "enum('text','html','wiki') NOT NULL DEFAULT 'text'" : "text";
      $init = array(
        "CREATE TABLE `menu` ( `id` ".$primary.", `order` int(11) NOT NULL DEFAULT '0', `label` varchar(128) NOT NULL, `url` varchar(256) NOT NULL);",
        "CREATE TABLE `pages` ( `id` ".$primary.", `slug` varchar(128) NOT NULL UNIQUE, `title` text NOT NULL, `content` text NOT NULL, `format` ".$enum.");",
        "CREATE TABLE `users` ( `id` ".$primary.", `username` varchar(64) NOT NULL UNIQUE, `password` varchar(128) NOT NULL);"
      );
      foreach($init as $v) $this->sql->Query($v);
      $this->sql->insertRow("users",array(
        "username" => $_POST["username"],
        "password" => password_hash($_POST["password"],PASSWORD_DEFAULT)
      ));
      $output .= "<p>Installation successful; now replace the <code>\$...-&gt;install()</code> in your main entry point".
      " file with <code>\$...-&gt;run()</code> and <a href='".$this->buildurl("admin")."'>load the admin to add some content</a>.</p>";
    }
    else
    {
      $output .=
      "<form method='post'>".
      "<h2>Create your first user</h2>".
      "<label>Username:</label>".
      "<input name='username' required='yes'/>".
      "<label>Password:</label>".
      "<input name='password' required='yes' type='password'/>".
      "<input type='submit' name='createUser' value='Create'/>".
      "</form>";
    }
    $output .=
    "</body>".
    "</html>";
    echo $output;
  }
  public function run()
  {
    $this->bootstrap();
    $this->initurls();
    $this->extractslug();
    if ($this->slug == "admin")
    {
      $this->renderadmin();
    }
    else
    {
      $this->render();
    }
  }
}
?>