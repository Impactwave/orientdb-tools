#!/usr/bin/env php
<?php
use PhpOrient\PhpOrient;
use PhpOrient\Protocols\Binary\Data\Record;
use PhpOrient\Protocols\Binary\Data\ID;
use PhpOrient\Exceptions\PhpOrientException;

require 'vendor/autoload.php';

define("BATCH_SIZE", 1000);

echo "
--------------------------------------------------
OrientDB Bulk Data Import
--------------------------------------------------
";

//--------------------------------------------------------------------------------------------------------------------
// Handle command-line arguments
//--------------------------------------------------------------------------------------------------------------------

$set = null;
$limit = 0;
$clear = false;
$fetching = true;
$env = isset($_SERVER['ENV_NAME']) ? $_SERVER['ENV_NAME'] : 'local';
if ($argc > 1)
  do {
    switch ($argv[1]) {
      case '--env':
        $env = @array_splice ($argv, 1, 2)[1];
        break;
      case '--clear':
        @array_splice ($argv, 1, 1);
        $clear = true;
        break;
      case '--limit':
        $limit = @array_splice ($argv, 1, 2)[1];
        break;
      case '--set':
        $setArg = @array_splice ($argv, 1, 2)[1];
        $set = json_decode ($setArg, true);
        if (!$set)
          fatal ("Invalid JSON for --set switch: $setArg");
        break;
      default:
        $fetching = false;
    }
  } while ($fetching && isset($argv[1]));

$file = @file_get_contents ('env-config.json');
if (!$file) fatal ("env-config.json not found");
$envConfig = json_decode ($file);
if (!$envConfig) fatal ("Invalid env-config.json");
if (!isset($envConfig->$env))
  fatal ("Invalid environment: $env");

$envCfg = $envConfig->$env;

echo "Environment:     $env
Target server:   $envCfg->host:$envCfg->port
";

$argc = count ($argv);

if ($argc != 4) {
  $envs = implode ('|', array_keys ((array)$envConfig));
  $me = array_slice (explode ('/', $argv[0]), -1)[0];
  fatal ("Syntax: $me [--env $envs] [--clear] [--limit N] [--set {json}] database class input-file.csv");
}

list (, $database, $className, $file) = $argv;

//--------------------------------------------------------------------------------------------------------------------
// Setup
//--------------------------------------------------------------------------------------------------------------------

echo "
Database:        $database
Class:           $className
Input file:      $file
Batch size:      " . BATCH_SIZE . " records

";

if (!file_exists ($file))
  fatal ("File not found.");

$client = new PhpOrient();
$client->configure ([
  'username' => $envCfg->user,
  'password' => $envCfg->pass,
  'hostname' => $envCfg->host,
  'port' => $envCfg->port,
]);
$client->connect ();

$databases = array_keys ($client->dbList ()['databases']);
if (!$client->dbExists ($database, PhpOrient::DATABASE_TYPE_DOCUMENT)) {
  echo ("Database doesn't exist.\nAvailable databases are:\n\t- ");
  echo implode ("\n\t- ", $databases);
  fatal ("");
}

//--------------------------------------------------------------------------------------------------------------------
// Upload data
//--------------------------------------------------------------------------------------------------------------------

$client->dbOpen ($database);

if ($clear) {
  try {
    $client->command ("TRUNCATE CLASS $className");
  } catch (PhpOrientException $e) {
    fatal ($e->getMessage ());
  }
  echo "Cleared all records of $className.\n\n";
}

echo "Uploading data... ";
$startTime = time ();

$row = 0;
$handle = fopen ($file, "r");
$header = fgetcsv ($handle, 0, ",");

try {
  while (($data = fgetcsv ($handle, 0, ",")) !== FALSE) {
    ++$row;
    if (!isset($tx)) {
      $tx = $client->getTransactionStatement ();
      $tx = $tx->begin ();
    }

    foreach ($data as &$v) {
      if (is_numeric ($v)) $v = floatval ($v);
      else if ($v == 'true') $v = true;
      else if ($v == 'false') $v = false;
    }

    $content = array_combine ($header, $data);
    if (isset($set))
      $content = array_merge ($content, $set);

    $rec = new Record();
    $rec->setOClass ($className)
      ->setOData ($content)
      ->setRid (new ID());
    // setRid( new ID(12)); //9 /* set only the cluster ID */ ) );
    $recordCommand = $client->recordCreate ($rec);
    $tx->attach ($recordCommand);

    if (($row % BATCH_SIZE) == 0) {
      echo ($row / BATCH_SIZE);
      $result = $tx->commit ();
      echo " ";
      $tx = null;
    }
    if ($limit && $row == $limit) break;
  }
  if ($tx) $tx->commit ();
  fclose ($handle);
} catch (PhpOrientException $e) {
  fclose ($handle);
  fatal ($e->getMessage ());
}

$client->dbClose ();

$delta = time () - $startTime;
$x = floor ($row / ($delta ?: 1));

$time = gmdate ("H:i:s", $delta);
echo "| $row records.
Import completed.
Total duration: $time
Speed: $x records/second.

";
exit;

//--------------------------------------------------------------------------------------------------------------------
// Private
//--------------------------------------------------------------------------------------------------------------------

function fatal ($msg)
{
  global $client;
  if (substr ($msg, 0, 4) == 'com.')
    $msg = explode (' ', $msg, 2)[1];
  if (substr ($msg, 0, 16) == 'Error on parsing')
    $msg = explode (': ', $msg, 2)[1];
  try {
    if (isset($client)) $client->dbClose ();
  } catch (Exception $e) {
  }
  die ("\n$msg\n\n");
}
