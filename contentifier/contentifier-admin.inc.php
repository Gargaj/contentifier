<?
trait ContentifierAdmin
{
  function thumbnail_cover($srcfile, $dstfile, $limitx=128, $limity=128, $outFormat=IMAGETYPE_PNG)
  {
    list($x,$y,$type,$attr) = getimagesize($srcfile);
  
    $aspThmb = $limitx / (float)$limity;
    $aspOrig = $x / (float)$y;
  
    if (($aspThmb - 1) * ($aspOrig - 1) <= 0)
    {
      // aspects/orientation are different
      if ($aspThmb > $aspOrig)
      {
        $cropx = $x;
        $cropy = floor($x * $limity / $limitx);
      }
      else
      {
        $cropx = floor($y * $limitx / $limity);
        $cropy = $y;
      }
    }
    else
    {
      // aspects match
      if ($aspThmb < $aspOrig)
      {
        $cropx = floor($y * $limitx / $limity);
        $cropy = $y;
      }
      else
      {
        $cropx = $x;
        $cropy = floor($x * $limity / $limitx);
      }
    }
  
    $openfunc = array(
      1 =>"imagecreatefromgif",
      2 =>"imagecreatefromjpeg",
      3 =>"imagecreatefrompng",
    );
  
    $src = $openfunc[$type]($srcfile);
    $dst = imagecreatetruecolor($limitx, $limity);
  
    $result = imagecopyresampled($dst, $src, 0, 0, ($x - $cropx) / 2, ($y - $cropy) / 2, $limitx, $limity, $cropx, $cropy);
    
    switch($outFormat)
    {
      case IMAGETYPE_GIF: imagegif($dst, $dstfile); break;
      case IMAGETYPE_JPEG: imagejpeg($dst, $dstfile); break;
      case IMAGETYPE_PNG: imagepng($dst, $dstfile); break;
    }
    imagedestroy($dst);
    imagedestroy($src);
  }
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
    "#medialist{list-style:none;}".
    "#medialist li{padding:5px;display:inline-block;width:160px;height:200px;font-size:12px;vertical-align:top;overflow:hidden}".
    "#medialist li a{display: block;}".
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
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"media")))."'>Media</a></li>".
      "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>"users")))."'>Users</a></li>";
      foreach($this->plugins as $plugin)
      {
        if ($plugin->adminmenuitem())
        {
          $output .= "<li><a href='".$this->escape($this->buildurl("admin",array("section"=>$plugin->id())))."'>".$this->escape($plugin->adminmenuitem())."</a></li>";
        }
      }
      $output.=
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
                "order"=>(int)$_POST["order"],
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
              $output .= "<label>Menu item order weight:</label>";
              $output .= "<input name='order' type='number' value='".$this->escape($menu->order)."' required='yes'/>";
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
                $output .= "<td>".$this->escape($menu->order)."</td>";
                $output .= "</tr>";
              }
              $output .= "<tr>";
              $output .= "<td colspan='4'><a href='".$this->escape($this->buildurl("admin",array("section"=>"menu","add"=>"new")))."'>Add new menu item</a></td>";
              $output .= "</tr>";
              $output .= "</table>";
            }
          }
          break;
        case "media":
          {
            $defSize = 150;
            if (is_uploaded_file($_FILES["newMediaFile"]["tmp_name"]))
            {
              @mkdir($this->mediadir());
              $baseName = $this->sanitize($_FILES["newMediaFile"]["name"]);
              $newFile = $this->mediadir()."/".$baseName;
              move_uploaded_file($_FILES["newMediaFile"]["tmp_name"],$newFile);
              @mkdir($this->thumbdir($defSize));
              $this->thumbnail_cover($newFile,$this->thumb($baseName,$defSize),$defSize,$defSize,IMAGETYPE_JPEG);
              $this->redirect( $this->buildurl("admin",array("section"=>"media")) );
            }
            $files = glob($this->mediadir() . "/*");
            $output .= "<h2>Media gallery</h2>";
            $output .= "<ul id='medialist'>";
            $n=0;
            $types = array( 1 =>"GIF", 2 =>"JPEG", 3 =>"PNG" );
            foreach($files as $file)
            {
              if (basename($file)=="." || basename($file)==".." || is_dir($file)) continue;
              $output .= "<li>";
              $output .= "<a href='".$this->rooturl().$file."'><img src='".$this->rooturl().$this->thumb($file,$defSize)."'/></a> ";
              $output .= "<a href='".$this->rooturl().$file."'><b>".basename($file)."</b></a>";              
              list($x,$y,$type,$attr) = @getimagesize($file);
              $output .= " (";
              if ($x&&$y)
              {
                $output .= sprintf("%d * %d %s, ",$x,$y,$types[$type]);
              }
              $output .= sprintf("%d bytes)",filesize($file));
              $output .= "</li>";
              $n++;
            }
            if (!$n) $output .= "<li>No files yet.</li>";
            $output .= "</ul>";
            $output .= "<h3>Upload new file</h3>";
            $output .= "<form method='post' enctype='multipart/form-data'>";
            $output .= "<input type='file' name='newMediaFile' required='yes'/>";
            $output .= "<input type='submit' name='uploadMedia' value='Upload'/>";
            $output .= "</form>";
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
            $found = false;
            foreach($this->plugins as $plugin)
            {
              if ($_GET["section"] == $plugin->id())
              {
                $output .= $plugin->admin();
                $found = true;
              }
            }
            if (!$found)
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
                $output .= "<input name='title' value='".$this->escape($page->title)."' required='yes' onkeyup='document.querySelector(\"#form-slug\").value=this.value.replace(/([^a-zA-Z0-9]+)/gim,\"-\").toLowerCase()'/>";
                $output .= "<label>Page slug:</label>";
                $output .= "<input name='slug' value='".$this->escape($page->slug)."' required='yes' id='form-slug'/>";
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
          }
          break;
      }
    }
    $output .= "</div><footer>Built with <a href='https://github.com/Gargaj/contentifier'>Contentifier</a></footer></body></html>";
    echo $output;
  }
};
?>