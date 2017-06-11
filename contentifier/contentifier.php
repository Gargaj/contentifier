<?
include_once("sqllib.inc.php");
include_once("contentifier-admin.inc.php");

abstract class Contentifier
{
  // API FUNCTIONS
  public function sqlhost() { return "localhost"; }
  public function sqluser() { return "contentifier"; }
  public function sqldb() { return "contentifier"; }
  public function templatefile() { return "template.html"; }
  public function rewriteenabled() { return false; }
  abstract public function sqlpass();
  public function rooturl() { return $this->rootURL; }
  public function slug() { return $this->slug; }

  use ContentifierAdmin;  
  
  function escape($s)
  {
    return htmlspecialchars($s,ENT_QUOTES);
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
  function extractslug()
  {
    $this->slug = "";
    if ($_GET["page"])
    {
      $this->slug = $_GET["page"];
    }
    else
    {
      if (preg_match("/^\/([a-zA-Z0-9\-_]*)\/"."/",$this->rootRelativeURL,$m))
      {
        $this->slug = $m[1];
      }
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
  function content()
  {
    $row = $this->getpagebyslug($this->slug);
    if ($row)
    {
      $content = $row->content;
      switch($row->format)
      {
        case "text": $content = nl2br($this->escape($content)); break;
      }
      return $content;
    }
    else
    {
      return "<h1>404</h1><p>Page '".$this->escape($this->slug)."' not found</p>";
    }
  }
  function contenttokens()
  {
    return array(
      "{%MENU%}" => $this->menu(),
      "{%CONTENT%}" => $this->content(),
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
    if ($this->slug == "admin")
    {
      $this->renderadmin();
      return;
    }
    $tokens = $this->contenttokens();
    $template = $this->template();
    $template = str_replace(array_keys($tokens),array_values($tokens),$template);
    echo $template;
  }
  public function getpagebyslug($slug)
  {
    return $this->sql->selectRow($this->sql->escape("select * from pages where slug='%s'",$slug));
  }
  public function run()
  {
    $this->sql = new SQLLib();
    $this->sql->Connect($this->sqlhost(),$this->sqluser(),$this->sqlpass(),$this->sqldb());
    
    $this->url = ($_SERVER["HTTPS"]=="on"?"https":"http").":/"."/".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    
    $dir = dirname($_SERVER["PHP_SELF"]);

    $this->rootRelativeURL = $_SERVER['REQUEST_URI'];
    if (strlen($dir) > 1)
    {
      $this->rootRelativeURL = substr($this->rootRelativeURL,strlen($dir));
    }
    $this->rootURL = substr($this->url,0,-strlen($this->rootRelativeURL))."/";
    
    $this->extractslug();
    $this->render();
  }
}
?>