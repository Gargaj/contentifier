<?php
function ctype_var($s)
{
  return ctype_alnum($s) || $s == "_";
}

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
    $v = preg_replace_callback("/\/\/.*/",function($i){ return strstr($i[0],'"')===false ? "" : $i[0]; },$v);
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
  
  if ($minify)
  {
    $inString = false;
    for($x=0;$x<strlen($out);$x++)
    {
      if ($out{$x}=='"' && $out{$x-1}!="\\")
      {
        $inString = !$inString;
        continue;
      }
      if ($inString)
      {
        continue;
      }
      if ( $out{$x}==" " && !(ctype_var($out{$x-1}) && ctype_var($out{$x+1})) )
      {
        $out = substr($out,0,$x) . substr($out,$x+1);
        $x--;
      }
    }
    $out = preg_replace("/\"\.\"/","",$out);
  }
  
  return $out;
}

chdir("contentifier");

header("Content-type: text/plain; charset=utf-8");

$out = parse_php( "contentifier.php", true );
echo $out;
file_put_contents("../contentifier.min.php",$out);

$out = parse_php( "contentifier.php", false );
//echo $out;
file_put_contents("../contentifier.php",$out);
?>