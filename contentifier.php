<?php 
global $SQLLIB_ARRAYS_CLEANED;
$SQLLIB_ARRAYS_CLEANED = false;
class SQLLib {
  public $link;
  public $debugMode = false;
  public $charset = "";
  public $queries = array();
  function Connect($SQL_HOST,$SQL_USERNAME,$SQL_PASSWORD,$SQL_DATABASE)
  {
    $this->link = mysqli_connect($SQL_HOST,$SQL_USERNAME,$SQL_PASSWORD,$SQL_DATABASE);
    if (mysqli_connect_errno($this->link))
      die("Unable to connect MySQL: ".mysqli_connect_error());
    $charsets = array("utf8mb4","utf8");
    $this->charset = "";
    foreach($charsets as $c)
    {
      if (mysqli_set_charset($this->link,$c))
      {
        $this->charset = $c;
        break;
      }
    }
    if (!$this->charset)
    {
      die("Error loading any of the character sets:");
    }
  }
  function Disconnect()
  {
    mysqli_close($this->link);
  }
  function Query($cmd)
  {
    if ($this->debugMode)
    {
      $start = microtime(true);
      $r = @mysqli_query($this->link,$cmd);
      if(!$r) throw new Exception("<pre>\nMySQL ERROR:\nError: ".mysqli_error($this->link)."\nQuery: ".$cmd);
      $end = microtime(true);
      $this->queries[$cmd] = $end - $start;
    }
    else
    {
      $r = @mysqli_query($this->link,$cmd);
      if(!$r) throw new Exception("<pre>\nMySQL ERROR:\nError: ".mysqli_error($this->link)."\nQuery: ".$cmd);
      $this->queries[] = "*";
    }
    return $r;
  }
  function Fetch($r)
  {
    return mysqli_fetch_object($r);
  }
  function SelectRows($cmd)
  {
    $r = $this->Query($cmd);
    $a = Array();
    while($o = $this->Fetch($r)) $a[]=$o;
    return $a;
  }
  function SelectRow($cmd)
  {
    if (stristr($cmd,"select ")!==false && stristr($cmd," limit ")===false) 
      $cmd .= " LIMIT 1";
    $r = $this->Query($cmd);
    $a = $this->Fetch($r);
    return $a;
  }
  function InsertRow($table,$o,$onDup = array())
  {
    global $SQLLIB_ARRAYS_CLEANED;
    if (!$SQLLIB_ARRAYS_CLEANED)
      trigger_error("Arrays not cleaned before InsertRow!",E_USER_ERROR);
    if (is_object($o)) $a = get_object_vars($o);
    else if (is_array($o)) $a = $o;
    $keys = Array();
    $values = Array();
    foreach($a as $k=>$v) {
      $keys[]="`".mysqli_real_escape_string($this->link,$k)."`";
      if ($v!==NULL) $values[]="'".mysqli_real_escape_string($this->link,$v)."'";
      else           $values[]="null";
    }
    $cmd = sprintf("insert %s (%s) values (%s)",
      $table,implode(", ",$keys),implode(", ",$values));
    if ($onDup)
    {
      $cmd .= " ON DUPLICATE KEY UPDATE ";
      $set = array();
      foreach($onDup as $k=>$v) {
        if ($v===NULL)
        {
          $set[] = sprintf("`%s`=null",mysqli_real_escape_string($this->link,$k));
        }
        else
        {
          $set[] = sprintf("`%s`='%s'",mysqli_real_escape_string($this->link,$k),mysqli_real_escape_string($this->link,$v));
        }
      }
      $cmd .= implode(", ",$set);
    }
    $r = $this->Query($cmd);
    return mysqli_insert_id($this->link);
  }
  function InsertMultiRow($table,$arr)
  {
    global $SQLLIB_ARRAYS_CLEANED;
    if (!$SQLLIB_ARRAYS_CLEANED)
      trigger_error("Arrays not cleaned before InsertMultiRow!",E_USER_ERROR);
    $keys = Array();
    $allValues = Array();
    foreach($arr as $o)
    {
      if (is_object($o)) $a = get_object_vars($o);
      else if (is_array($o)) $a = $o;
      $keys = Array();
      $values = Array();
      foreach($a as $k=>$v) {
        $keys[]="`".mysqli_real_escape_string($this->link,$k)."`";
        if ($v!==NULL) $values[]="'".mysqli_real_escape_string($this->link,$v)."'";
        else           $values[]="null";
      }
      $allValues[] = "(".implode(", ",$values).")";
    }
    $cmd = sprintf("insert %s (%s) values %s",
      $table,implode(", ",$keys),implode(", ",$allValues));
    $r = $this->Query($cmd);
  }
  function UpdateRow($table,$o,$where)
  {
    global $SQLLIB_ARRAYS_CLEANED;
    if (!$SQLLIB_ARRAYS_CLEANED)
      trigger_error("Arrays not cleaned before UpdateRow!",E_USER_ERROR);
    if (is_object($o)) $a = get_object_vars($o);
    else if (is_array($o)) $a = $o;
    $set = Array();
    foreach($a as $k=>$v) {
      if ($v===NULL)
      {
        $set[] = sprintf("`%s`=null",mysqli_real_escape_string($this->link,$k));
      }
      else
      {
        $set[] = sprintf("`%s`='%s'",mysqli_real_escape_string($this->link,$k),mysqli_real_escape_string($this->link,$v));
      }
    }
    $cmd = sprintf("update %s set %s where %s",
      $table,implode(", ",$set),$where);
    $this->Query($cmd);
  }
  
  function UpdateRowMulti( $table, $key, $tuples )
  {
    if (!count($tuples))
      return;
    if (!is_array($tuples[0]))
      throw new Exception("Has to be array!");
    $fields = array_keys( $tuples[0] );
    $sql = "UPDATE ".$table;
    $keys = array();
    $cond = "";
    foreach($fields as $field)
    {
      if ($field == $key) continue;
      foreach($tuples as $tuple)
        $cond .= sprintf_esc(" WHEN %d THEN '%s' ",$tuple[$key],$tuple[$field]);
      $sql .= " SET `".$field."` = (CASE `".$key."` ".$cond." END)";
    }
    foreach($tuples as $tuple)
      $keys[] = $tuple[$key];
    $sql .= " WHERE `".$key."` IN (".implode(",",$keys).")";
    $this->Query($sql);
  }
  function UpdateOrInsertRow($table,$o,$where)
  {
    if ($this->SelectRow(sprintf("SELECT * FROM %s WHERE %s",$table,$where)))
      return $this->UpdateRow($table,$o,$where);
    else
      return $this->InsertRow($table,$o);
  }
  function StartTransaction()
  {
    mysqli_autocommit($this->link, FALSE);
  }
  function FinishTransaction()
  {
    mysqli_commit($this->link);
    mysqli_autocommit($this->link, TRUE);
  }
  function CancelTransaction()
  {
    mysqli_rollback($this->link);
    mysqli_autocommit($this->link, TRUE);
  }
  function Escape()
  {
    $args = func_get_args();
    reset($args);
    next($args);
    while (list($key, $value) = each($args))
      $args[$key] = mysqli_real_escape_string( $this->link, $args[$key] );
    return call_user_func_array("sprintf", $args);
  }
}
class SQLTrans
{
  var $rollback;
  function __construct() {
    $this->StartTransaction();
    $rollback = false;
  }
  function Rollback() {
    $this->rollback = true;
  }
  function __destruct() {
    if (!$rollback)
      $this->FinishTransaction();
    else
      $this->CancelTransaction();
  }
}
class SQLSelect
{
  var $fields;
  var $tables;
  var $conditions;
  var $joins;
  var $orders;
  var $groups;
  var $limit;
  var $offset;
  function SQLSelect()
  {
    $this->fields = array();
    $this->tables = array();
    $this->conditions = array();
    $this->joins = array();
    $this->orders = array();
    $this->groups = array();
    $this->limit = NULL;
    $this->offset = NULL;
  }
  function AddTable($s)
  {
    $this->tables[] = $s;
  }
  function AddField($s)
  {
    $this->fields[] = $s;
  }
  function AddJoin($type,$table,$condition)
  {
    $o = new stdClass();
    $o->type = $type;
    $o->table = $table;
    $o->condition = $condition;
    $this->joins[] = $o;
  }
  function AddWhere($s)
  {
    $this->conditions[] = "(".$s.")";
  }
  function AddOrder($s)
  {
    $this->orders[] = $s;
  }
  function AddGroup($s)
  {
    $this->groups[] = $s;
  }
  function SetLimit( $limit, $offset = NULL )
  {
    $this->limit = $limit;
    if ($offset !== NULL)
      $this->offset = $offset;
  }
  function GetQuery()
  {
    if (!count($this->tables))
      throw new Exception("[sqlselect] No tables specified!");
    $sql = "SELECT ";
    if ($this->fields) {
      $sql .= implode(", ",$this->fields);
    } else {
      $sql .= " * ";
    }
    $sql .= " FROM ".implode(", ",$this->tables);
    if ($this->joins) {
      foreach ($this->joins as $v) {
        $sql .= " ".$v->type." JOIN ".$v->table." ON ".$v->condition;
      }
    }
    if ($this->conditions)
      $sql .= " WHERE ".implode(" AND ",$this->conditions);
    if ($this->groups)
      $sql .= " GROUP BY ".implode(", ",$this->groups);
    if ($this->orders)
      $sql .= " ORDER BY ".implode(", ",$this->orders);
    if ($this->offset !== NULL)
    {
      $sql .= " LIMIT ".$this->offset . ", " . $this->limit;
    }
    else if ($this->limit !== NULL)
    {
      $sql .= " LIMIT ".$this->limit;
    }
    return $sql;
  }
}
function nop($s) { return $s; }
function clearArray($a)
{
  $ar = array();
  $qcb = get_magic_quotes_gpc() ? "stripslashes" : "nop";
  foreach ($a as $k=>$v)
    if (is_array($v))
      $ar[$k] = clearArray($v);
    else
      $ar[$k] = $qcb($v);
  return $ar;
}
$_POST = clearArray($_POST);
$_GET = clearArray($_GET);
$_REQUEST = clearArray($_REQUEST);
$SQLLIB_ARRAYS_CLEANED = true;
trait ContentifierAdmin
{
  function isloggedinasadmin()
  {
    @session_start();
    if ($_POST["login"] && $_SESSION["contentifier_token"] == $_POST["token"])
    {
      $user = $this->sql->selectRow($this->sql->escape("select * from users where username='%s'",$_POST["username"]));
      if (password_verify($_POST["password"],$user->password))
      {
        $_SESSION["contentifier_userID"] = $user->id;
        return true;
      }
    }
    return $this->sql->selectRow($this->sql->escape("select * from users where id='%d'",$_SESSION["contentifier_userID"]));
  }
  function renderadmin_loginform()
  {
    $token = rand(1,999999);
    $_SESSION["contentifier_token"] = $token;
    $output .= "<form method='post'>";
    $output .= "<label>Username:</label>";
    $output .= "<input name='username' required='yes'/>";
    $output .= "<label>Password:</label>";
    $output .= "<input name='password' required='yes' type='password'/>";
    $output .= "<input type='hidden' name='token' value='".$this->escape($token)."'/>";
    $output .= "<input type='submit' name='login' value='Log In'/>";
    $output .= "</form>";
    return $output;
  }
  function renderadmin()
  {
    $output = "<!DOCTYPE html>\n<html>".
    "<head>".
    "<title>Contentifier Admin</title>".
    "<style type=\"text/css\">".
    "*{margin:0;padding:0;}".
    "body {font-family:'segoe ui',sans-serif;background:#ccc;}".
    "a {color:black;}".
    "h1 {background:#444;color:white;border:0;border-bottom:1px solid #888;padding:5px;}".
    "nav {width: 120px}".
    "nav ul {list-style: none;}".
    "nav a {color:#ccc;text-decoration:none;font-variant:small-caps;width:100px; display:inline-block;background:#666;margin:3px 0px;padding:1px 8px;}".
    "nav,#content {display:inline-block;vertical-align:top;padding:5px 10px;}".
    "#content {width:calc(100% - 200px);}".
    "table {border-collapse:collapse}".
    "table th, table td {border:1px solid #aaa; padding:2px 6px;}".
    "input,textarea{display:block;width:100%;padding:5px;margin:5px 0px;}".
    "textarea{height:calc(100vh - 400px);}".
    "input.submit{width:90%;display:inline-block;}".
    "input.delete{width:10%;display:inline-block;}".
    "label{color:#888;font-size:60%;}".
    "label.radio{display:inline-block;margin-right:10px;vertical-align:sub;color:#444;font-size:80%;}".
    "label.radio input{display:inline;width:auto;}".
    "#loginform{width:300px;margin:30px auto;}".
    "footer, footer a{color:#999;margin:5px;font-size:10px;text-align:right;}".
    "</style>".
    "</head>".
    "<body>".
    "<h1>Contentifier Admin</h1>";
    if(!$this->isloggedinasadmin())
    {
      $output .= "<div id='loginform'>";
      $output .= $this->renderadmin_loginform();
    }
    else
    {
      $output.=
      "<nav><ul>".
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"pages")))."'>Pages</a></li>".
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"menu")))."'>Menu</a></li>".
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"users")))."'>Users</a></li>".
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"logout")))."'>Log out</a></li>".
      "</ul></nav><div id='content'>";
      switch($_GET["section"])
      {
        case "logout":
          {
            unset($_SESSION["contentifier_userID"]);
            $this->redirect( $this->buildurl("/") );
          }
          break;
        case "menu":
          {
            if ($_POST["deleteMenu"])
            {
              $this->sql->query($this->sql->escape("delete from menu where id=%d",$_POST["menuID"]));
              $this->redirect( $this->buildurl("admin",array("section"=>"menu")) );
            }
            else if ($_POST["submitMenu"])
            {
              $a = array(
                "label"=>$_POST["label"],
                "url"=>$_POST["url"],
              );
              if ($_POST["menuID"])
              {
                $this->sql->updateRow("menu",$a,$this->sql->escape("id=%d",$_POST["menuID"]));
                $this->redirect( $this->buildurl("admin",array("section"=>"menu","menuID"=>$_POST["userID"])) );
              }
              else
              {
                $id = $this->sql->insertRow("menu",$a);
                $this->redirect( $this->buildurl("admin",array("section"=>"menu","menuID"=>$id)) );
              }
            }
            if ($_GET["menuID"] || $_GET["add"]=="new")
            {
              $menu = $this->sql->selectRow($this->sql->escape("select * from menu where id='%d'",$_GET["menuID"]));
              $output .= "<h2>Menu item: ".$this->escape($menu->label)."</h2>";
              $output .= "<form method='post'>";
              $output .= "<label>Menu item label:</label>";
              $output .= "<input name='label' value='".$this->escape($menu->label)."' required='yes'/>";
              $output .= "<label>Menu item URL: (absolute or relative to site root)</label>";
              $output .= "<input name='url' value='".$this->escape($menu->url)."' required='yes'/>";
              if ($_GET["menuID"])
              {
                $output .= "<input type='hidden' name='menuID' value='".$this->escape($_GET["menuID"])."'/>";
              }
              $output .= "<input type='submit' name='submitMenu' class='submit' value='Save'/>";
              $output .= "<input type='submit' name='deleteMenu' class='delete' value='Delete'/>";
              $output .= "</form>";
            }
            else
            {
              $menus = $this->sql->selectRows($this->sql->escape("select * from menu order by `order`"));
              $output .= "<h2>Menu</h2>";
              $output .= "<table>";
              foreach($menus as $menu)
              {
                $output .= "<tr>";
                $output .= "<td>".$this->escape($menu->id)."</td>";
                $output .= "<td><a href='".$this->escape($this->buildurl("admin",array("section"=>"menu","menuID"=>$menu->id)))."'>".$this->escape($menu->label)."</a></td>";
                $output .= "<td>".$this->escape($menu->url)."</td>";
                $output .= "</tr>";
              }
              $output .= "<tr>";
              $output .= "<td colspan='3'><a href='".$this->escape($this->buildurl("admin",array("section"=>"menu","add"=>"new")))."'>Add new menu item</a></td>";
              $output .= "</tr>";
              $output .= "</table>";
            }
          }
          break;
        case "users":
          {
            if ($_POST["deleteUser"])
            {
              $this->sql->query($this->sql->escape("delete from users where id=%d",$_POST["userID"]));
              $this->redirect( $this->buildurl("admin",array("section"=>"users")) );
            }
            else if ($_POST["submitUser"])
            {
              $a = array(
                "username"=>$_POST["username"],
              );
              if ($_POST["userID"])
              {
                if ($_POST["password"])
                {
                  $a["password"] = password_hash($_POST["password"],PASSWORD_DEFAULT);
                }
                $this->sql->updateRow("users",$a,$this->sql->escape("id=%d",$_POST["userID"]));
                $this->redirect( $this->buildurl("admin",array("section"=>"users","userID"=>$_POST["userID"])) );
              }
              else
              {
                $a["password"] = password_hash($_POST["password"],PASSWORD_DEFAULT);
                $id = $this->sql->insertRow("users",$a);
                $this->redirect( $this->buildurl("admin",array("section"=>"users","userID"=>$id)) );
              }
            }
            if ($_GET["userID"] || $_GET["add"]=="new")
            {
              $user = $this->sql->selectRow($this->sql->escape("select * from users where id='%d'",$_GET["userID"]));
              $output .= "<h2>User: ".$this->escape($user->username)."</h2>";
              $output .= "<form method='post'>";
              $output .= "<label>Username:</label>";
              $output .= "<input name='username' value='".$this->escape($user->username)."' required='yes'/>";
              $output .= "<label>Password: (leave blank to keep unchanged)</label>";
              if ($_GET["userID"])
              {
                $output .= "<input name='password' type='password'/>";
                $output .= "<input type='hidden' name='userID' value='".$this->escape($_GET["userID"])."'/>";
              }
              else
              {
                $output .= "<input name='password' type='password' required='yes'/>";
              }
              $output .= "<input type='submit' name='submitUser' class='submit' value='Save'/>";
              $output .= "<input type='submit' name='deleteUser' class='delete' value='Delete'/>";
              $output .= "</form>";
            }
            else
            {
              $users = $this->sql->selectRows($this->sql->escape("select * from users"));
              $output .= "<h2>Users</h2>";
              $output .= "<table>";
              foreach($users as $user)
              {
                $output .= "<tr>";
                $output .= "<td>".$this->escape($user->id)."</td>";
                $output .= "<td><a href='".$this->escape($this->buildurl("admin",array("section"=>"users","userID"=>$user->id)))."'>".$this->escape($user->username)."</a></td>";
                $output .= "</tr>";
              }
              $output .= "<tr>";
              $output .= "<td colspan='2'><a href='".$this->escape($this->buildurl("admin",array("section"=>"users","add"=>"new")))."'>Add new user</a></td>";
              $output .= "</tr>";
              $output .= "</table>";
            }
          }
          break;
        default:
          {
            if ($_POST["deletePage"])
            {
              $this->sql->query($this->sql->escape("delete from pages where id=%d",$_POST["pageID"]));
              $this->redirect( $this->buildurl("admin",array("section"=>"pages")) );
            }
            else if ($_POST["submitPage"])
            {
              $a = array(
                "title"=>$_POST["title"],
                "slug"=>$_POST["slug"],
                "content"=>$_POST["content"],
                "format"=>$_POST["format"],
              );
              if ($_POST["pageID"])
              {
                $this->sql->updateRow("pages",$a,$this->sql->escape("id=%d",$_POST["pageID"]));
                $this->redirect( $this->buildurl("admin",array("section"=>"pages","pageID"=>$_POST["pageID"])) );
              }
              else
              {
                $id = $this->sql->insertRow("pages",$a);
                $this->redirect( $this->buildurl("admin",array("section"=>"pages","pageID"=>$id)) );
              }
            }
            if ($_GET["pageID"] || $_GET["add"]=="new")
            {
              $page = $this->sql->selectRow($this->sql->escape("select * from pages where id='%d'",$_GET["pageID"]));
              $output .= "<form method='post'>";
              $output .= "<h2>Page: ".$this->escape($page->title)."</h2>";
              $output .= "<label>Page title:</label>";
              $output .= "<input name='title' value='".$this->escape($page->title)."' required='yes'/>";
              $output .= "<label>Page slug:</label>";
              $output .= "<input name='slug' value='".$this->escape($page->slug)."' required='yes'/>";
              $output .= "<label>Page contents:</label>";
              $output .= "<textarea name='content'>".$this->escape($page->content)."</textarea>";
              $output .= "<label>Page format:</label>";
              $output .= "<div>";
              $output .= "<label class='radio'><input type='radio' name='format' value='text'".(($page->format=="text"||!$page->format)?"checked='checked'":"")."> Plain text</label>";
              $output .= "<label class='radio'><input type='radio' name='format' value='HTML'".($page->format=="html"?"checked='checked'":"")."> HTML</label>";
              $output .= "</div>";
              if ($_GET["pageID"])
              {
                $output .= "<input type='hidden' name='pageID' value='".$this->escape($_GET["pageID"])."'/>";
              }
              $output .= "<input type='submit' name='submitPage' class='submit' value='Save'/>";
              $output .= "<input type='submit' name='deletePage' class='delete' value='Delete'/>";
              $output .= "</form>";
            }
            else
            {
              $pages = $this->sql->selectRows($this->sql->escape("select * from pages"));
              $output .= "<h2>Pages</h2>";
              $output .= "<table>";
              foreach($pages as $page)
              {
                $output .= "<tr>";
                $output .= "<td>".$this->escape($page->id)."</td>";
                $output .= "<td><a href='".$this->escape($this->buildurl("admin",array("section"=>"pages","pageID"=>$page->id)))."'>".$this->escape($page->slug)."</a></td>";
                $output .= "<td>".$this->escape($page->title)."</td>";
                $output .= "</tr>";
              }
              $output .= "<tr>";
              $output .= "<td colspan='3'><a href='".$this->escape($this->buildurl("admin",array("section"=>"pages","add"=>"new")))."'>Add new page</a></td>";
              $output .= "</tr>";
              $output .= "</table>";
            }
          }
          break;
      }
    }
    $output .= "</div><footer>Built with <a href='https://github.com/Gargaj/contentifier'>Contentifier</a></footer></body></html>";
    echo $output;
  }
};
abstract class Contentifier
{
  
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
      $this->slug = $_GET["page"];
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
    return false;
  }
  function contenttokens()
  {
    return array(
      "{%MENU%}" => $this->menu(),
      "{%CONTENT%}" => $this->content() ?: ("<h1>404</h1><p>Page '".$this->escape($this->slug)."' not found</p>"),
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
  public function install()
  {
    $this->sql = new SQLLib();
    $this->sql->Connect($this->sqlhost(),$this->sqluser(),$this->sqlpass(),$this->sqldb());
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
    if ($_POST["username"] && $_POST["password"])
    {
      $init = array(
        "CREATE TABLE `menu` ( `id` int(11) NOT NULL AUTO_INCREMENT, `order` int(11) NOT NULL DEFAULT '0', `label` varchar(128) NOT NULL, `url` varchar(256) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;",
        "CREATE TABLE `pages` ( `id` int(11) NOT NULL AUTO_INCREMENT, `slug` varchar(128) NOT NULL, `title` text NOT NULL, `content` text NOT NULL, `format` enum('text','html','wiki') NOT NULL DEFAULT 'text', PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB;",
        "CREATE TABLE `users` ( `id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(64) NOT NULL, `password` varchar(128) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`)) ENGINE=InnoDB;"
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
      "<input type='hidden' name='token' value='".$this->escape($token)."'/>".
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
    $this->sql = new SQLLib();
    $this->sql->Connect($this->sqlhost(),$this->sqluser(),$this->sqlpass(),$this->sqldb());
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
