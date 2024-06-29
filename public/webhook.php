<?php

/**
 * End point for GitHub Webhook
 */
// set actual date
$dateNum = date("Ymd");
// load env variables
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('../.env');
$secret = $_ENV['secret'];
$level = $_ENV['proyect_folder_level'];
$debug = $_ENV['debug'];
// get body
$body = file_get_contents("php://input");
$decodedBody = json_decode($body);
// validate content type
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
  http_response_code(400);
  echo "Content type not valid";
  exit;
}

// Identificar el evento webhook
$eventType = $headers["X-Github-Event"];

// Manejar el evento ping
if ($eventType == "ping") {
  http_response_code(200);
  echo "Ping received successfully";
  exit;
}

if ($eventType != "push"){
  http_response_code(400);
  echo "Event not supported";
  exit;
}

// verify signature
if ($body != "") {
  if (verifySignature($body, $secret) !== false) {
    // verified
    echo "authorized" . "\n";
    // identify webhook event
    $headers = getallheaders();
    // identify repository
    $repo = $decodedBody->repository->name;
    // identify files
    $files = @$decodedBody->commits[0]->modified;
    $committer = @$decodedBody->commits[0]->committer->name;
    // obtener nombre de la rama
    $branch = $decodedBody->ref;
    $branch = str_replace("refs/heads/", "", $branch);
    // obtener el hash del commit
    $commit = @$decodedBody->after;
    // obtener el nombre del commit
    $commitName = @$decodedBody->commits[0]->message;
    // obtener el nombre del usuario que hizo el commit
    $commitUser = @$decodedBody->commits[0]->committer->name;
    // DDL table repos (id, name, branch, path)
    // DDL table logs (id, event, repo, branch, commit, commitName, commitUser, created)
    if ($headers["X-Github-Event"] == "push") {
      $retry = 0;
      retry:
      // connect database
      try {
        $retry++;
        $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
      } catch (Exception $e) {
        $log = fopen("../logs/" . $dateNum . ".log", "a");
        fwrite($log, "Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s") . "\n");
        fwrite($log, $e->getMessage() . "\n");
        fclose($log);
        //http_response_code(400);
        //exit;
      }
      if(!$db && $retry < 3){
        sleep(1);
        goto retry;
      }
    /*
      $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
      if ($db->connect_errno) {
        $log = fopen("../logs/" . $dateNum . ".log", "a");
        fwrite($log, "Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s") . "\n");
        fwrite($log, $db->connect_error . "\n");
        fclose($log);
        http_response_code(400);
        exit;
      }
    */
      // find repo data in database where name = $repo and branch = $branch
      $sql = "SELECT ID, path FROM repos WHERE name = '" . $repo . "' AND branch = '" . $branch . "'";
      $localPath = __DIR__;
      $localPath = explode("/", $localPath);
      $localPath = $localPath[$level];

      if ($debug == 1) {
        error_log("Github Event: " . $headers["X-Github-Event"]);
        error_log($sql);
        error_log($localPath);
      }
      $result = $db->query($sql);
      if ($result) {
        if ($result->num_rows > 0) {
          // output data
          if ($row = $result->fetch_all()) {
            // for rows array 
            for ($i = 0; $i < count($row); $i++) {
              $id = $row[$i][0];
              $path = $row[$i][1];
              $repoPath = explode("/", $path);
              $repoPath = $repoPath[$level];
              // if $path is dir
              if ((is_dir($path)) && ($path != "") && ($repoPath == $localPath)) {
                // execute git pull in path
                $shell_res = shell_exec("cd " . $path . "; /usr/bin/git pull 2>&1");
                echo $shell_res . "\n";
              } else {
                $shell_res = 0;
                echo $path . " path not found" . "\n";
              }
            }
            // log to file
            $log = fopen("../logs/" . $dateNum . ".log", "a");
            fwrite($log, "Status: OK " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Execution: " . $shell_res . " Time: " . date("Y-m-d H:i:s") . "\n");
            // if files is array
            if (is_array($files)) {
              fwrite($log, "Files: " . implode(", ", $files) . "\n");
            }
            fclose($log);
            // log to database
            $sql = "INSERT INTO logs (event, repo, branch, commit, commitName, commitUser, created) VALUES ('" . $headers["X-Github-Event"] . "', '" . $repo . "', '" . $branch . "', '" . $commit . "', '" . $commitName . "', '" . $commitUser . "', '" . date("Y-m-d H:i:s") . "')";
            if ($db->query($sql) === TRUE) {
              $log = fopen("../logs/" . $dateNum . ".log", "a");
              fwrite($log, "Status: OK " . "Event Loged into DataBase" . "\n");
              fclose($log);
            } else {
              $log = fopen("../logs/" . $dateNum . ".log", "a");
              fwrite($log, "Error: " . $sql . "<br>" . $db->error . "\n");
              fclose($log);
            }
            http_response_code(200);
          }
        } else {
          $log = fopen("../logs/" . $dateNum . ".log", "a");
          fwrite($log, "Status: Error. " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s") . "\n");
          fwrite($log, "Repo not found in database" . "\n");
          fclose($log);
          http_response_code(400);
          exit;
        }
      } else {
        $log = fopen("../logs/" . $dateNum . ".log", "a");
        fwrite($log, "Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s") . "\n");
        fwrite($log, "Repo not found in database" . "\n");
        fclose($log);
        http_response_code(400);
        exit;
      }
    } else {
      // log to file
      $log = fopen("../logs/" . $dateNum . ".log", "a");
      fwrite($log, "Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s") . "\n");
      fwrite($log, $decodedBody . "\n");
      fwrite($log, "Headers: " . json_encode($headers) . "\n");
      fclose($log);
      http_response_code(400);
    }
  } else {
    http_response_code(403);
    echo "unauthorized";
    exit;
  }
} else {
  http_response_code(400);
  echo "nothing to do";
  exit;
}
function verifySignature($body, $secret)
{
  $headers = getallheaders();
  if (!isset($headers['X-Hub-Signature-256'])) {
    return false;
  }
  return hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $headers['X-Hub-Signature-256']);
}
