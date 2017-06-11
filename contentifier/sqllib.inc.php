<?
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
    if (stristr($cmd,"select ")!==false && stristr($cmd," limit ")===false) // not exactly nice but it'll help
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

  /*
  UpdateRowMulti allows batched updates on multiple rows at once.

  Syntax:
  $tuples = array(
    array( "keyColumn" => 1, "col1" => "abc", "col2" => "def" ),
    array( "keyColumn" => 2, "col1" => "ghi", "col2" => "jkl" ),
  );
  $key = "keyColumn";

  NOTE: the first tuple defines keys. If your tuples are uneven, you're on your own.
  */
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
?>