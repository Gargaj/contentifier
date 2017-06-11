<?php
function parse_php( $filename, $include = false )
{
  $file = file($filename);
  $out = "";
  foreach($file as $v)
  {
    $v = trim($v);
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
        $v = parse_php($includeFile, true);
      }
    }
    $out .= $v." ";
  }
  $out = preg_replace("/\/\*.*?\*\//ims","",$out);
  $out = preg_replace("/\s+/"," ",$out);
  
  return $out;
}

chdir("contentifier");
$out = parse_php( "contentifier.php" );
header("Content-type: text/plain; charset=utf-8");
echo $out;
file_put_contents("../contentifier.php",$out);
?>