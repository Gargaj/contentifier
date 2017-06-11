<?php
function parse_php( $filename, $minify = true, $include = false )
{
  $file = file($filename);
  $out = "";
  foreach($file as $v)
  {
    if ($minify)
    {
      $v = trim($v);
    }
    else
    {
      $v = rtrim($v);
    }
    $v = preg_replace("/\/\/.*/","",$v);
    if ($v == "<?")
    {
      if ($include) $v = "";
      else $v = "<?php ";      
    }
    else if ($v == "?>")
    {
      if ($include) $v = "";
    }
    else if (preg_match("/(include|require|include_once|require_once)\((.*)\);/",$v,$m))
    {
      if (preg_match("/^['\"].*['\"]$/",trim($m[2])))
      {
        $includeFile = trim($m[2],"'\"");
        $v = parse_php($includeFile, $minify, true);
      }
    }
    if ($minify)
    {
      $out .= $v." ";
    }
    else
    {
      $out .= $v."\n";
    }
  }
  $out = preg_replace("/\/\*.*?\*\//ims","",$out);
  if ($minify)
  {
    $out = preg_replace("/\s+/"," ",$out);
  }
  else
  {
    $out = preg_replace("/\n+/","\n",$out);
  }
  
  return $out;
}

chdir("contentifier");

header("Content-type: text/plain; charset=utf-8");

$out = parse_php( "contentifier.php", true );
//echo $out;
file_put_contents("../contentifier.min.php",$out);

$out = parse_php( "contentifier.php", false );
echo $out;
file_put_contents("../contentifier.php",$out);
?>