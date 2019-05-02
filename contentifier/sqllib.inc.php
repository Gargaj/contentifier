<?php
global $SQLLIB_ARRAYS_CLEANED;
$SQLLIB_ARRAYS_CLEANED = false;

class SQLLibException extends Exception 
{ 
  public function __construct($message = null, $code = 0, $query = "")
  {
    parent::__construct($message, $code);
    $this->query = $query;
  }
  public function __toString()
  {
    $e .= date("Y-m-d H:i:s");
    $e .= "\nError: ".$this->getMessage()."\n";
    $e .= "\nQuery: ".$this->query."\n";
    $e .= "\nTrace: ".$this->getTraceAsString();
    return $e;
  }
}

class SQLLib {
  public static $link;
  public static $debugMode = false;
  public static $charset = "";
  public $queries = array();

  public function Connect($dsn, $username = null, $password = null, $options = null)
  {
    try
    {
      $this->link = new PDO($dsn, $username, $password, $options);
    }
    catch (PDOException $e) 
    {
      die("Unable to connect SQLite with PDO: ".$e->getMessage());
    }
    $this->link->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);  
    $this->link->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);  
  }

  public function Disconnect()
  {
    $this->link = null;
  }
  
  public function Query($cmd) 
  {
    global $SQLLIB_QUERIES;

    if ($this->debugMode)
    {
      $start = microtime(true);
      $r = @$this->link->query($cmd);
      if(!$r) throw new SQLLibException(implode("\n",$this->link->errorInfo()),0,$cmd);
      $end = microtime(true);
      $SQLLIB_QUERIES[$cmd] = $end - $start;
    }
    else
    {
      $r = @$this->link->query($cmd);
      if(!$r) throw new SQLLibException(implode("\n",$this->link->errorInfo()),0,$cmd);
      $SQLLIB_QUERIES[] = "*";
    }

    return $r;
  }

  public function Fetch($r) 
  {
    return $r->fetchObject();
  }

  public function SelectRows($cmd) 
  {
    $r = $this->Query($cmd);
    $a = Array();
    while($o = $this->Fetch($r)) $a[]=$o;
    return $a;
  }

  public function SelectRow($cmd) 
  {
    if (stristr($cmd,"select ")!==false && stristr($cmd," limit ")===false) // not exactly nice but i'll help
      $cmd .= " LIMIT 1";
    $r = $this->Query($cmd);
    $a = $this->Fetch($r);
    return $a;
  }

  public function InsertRow($table,$o) 
  {
    global $SQLLIB_ARRAYS_CLEANED;
    if (!$SQLLIB_ARRAYS_CLEANED)
      trigger_error("Arrays not cleaned before InsertRow!",E_USER_ERROR);

    if (is_object($o)) $a = get_object_vars($o);
    else if (is_array($o)) $a = $o;
    $keys = Array();
    $values = Array();
    foreach($a as $k=>$v) {
      $keys[]=$this->Quote($k);
      if ($v!==NULL) $values[]="'".$this->Quote($v)."'";
      else           $values[]="null";
    }

    $cmd = sprintf("insert into %s (%s) values (%s)",
      $table,implode(", ",$keys),implode(", ",$values));

    $r = $this->Query($cmd);

    return $this->link->lastInsertId();
  }

  public function UpdateRow($table,$o,$where) 
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
        $set[] = sprintf("%s=null",$this->Quote($k));
      }
      else
      {
        $set[] = sprintf("%s='%s'",$this->Quote($k),$this->Quote($v));
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
  public function UpdateRowMulti( $table, $key, $tuples )
  {
    if (!count($tuples))
      return;
    if (!is_array($tuples[0]))
      throw new Exception("Has to be array!");
  
    $fields = array_keys( $tuples[0] );
  
    $sql = "UPDATE ".$table;
    $keys = array();
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

  public function UpdateOrInsertRow($table,$o,$where) 
  {
    if ($this->SelectRow(sprintf("SELECT * FROM %s WHERE %s",$table,$where)))
      return $this->UpdateRow($table,$o,$where);
    else
      return $this->InsertRow($table,$o);
  }
  
  public function StartTransaction() 
  {
  }
  public function FinishTransaction() 
  {
  }
  public function CancelTransaction() 
  {
  }
  public function Quote($string)
  {
    return substr($this->link->quote($string),1,-1);
  }
  public function Escape($string)
  {
    $args = func_get_args();
    for ($key = 1; $key < count($args); $key++) 
    {
      $args[$key] = $this->Quote($args[$key]);
    }
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

  function __construct()
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
    $this->limit = (int)$limit;
    if ($offset !== NULL)
      $this->offset = (int)$offset;
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

function sprintf_esc()
{
  $args = func_get_args();
  reset($args);
  next($args);
  while (list($key, $value) = each($args))
    $args[$key] = $this->Quote( $args[$key] );

  return call_user_func_array("sprintf", $args);
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