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

$headers = getallheaders();

// Identificar el evento webhook
$eventType = $headers["X-Github-Event"];

// if empty event type, log to file all headers
if ($eventType == "") {
  writeToLog("Status: Error Event: " . $headers["X-Github-Event"] . " Time: " . date("Y-m-d H:i:s"));
  http_response_code(400);
  exit;
}

// Manejar el evento ping
if ($eventType == "ping") {
  http_response_code(200);
  writeToLog("Status: OK Event: " . $headers["X-Github-Event"] . " Time: " . date("Y-m-d H:i:s"));
  echo "Ping received successfully";
  exit;
}

if ($eventType != "push") {
  http_response_code(400);
  echo "Event not supported";
  writeToLog("Status: Error Event: " . $headers["X-Github-Event"] . " Time: " . date("Y-m-d H:i:s") . "\n");
  writeToLog($decodedBody . "\n");
  writeToLog("Headers: " . json_encode($headers) . "\n");
  exit;
}

// verify signature
if ($body != "") {
  if (verifySignature($body, $secret) !== false) {
    // verified
    echo "authorized" . "\n";
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
      $retry = 0;
      $maxRetries = 3;
      while ($retry < $maxRetries) {
          try {
              $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
              break; // Salir del bucle si la conexión es exitosa
          } catch (Exception $e) {
              writeToLog("Intento de conexión a DB fallido: " . $e->getMessage());
              $retry++;
              if ($retry >= $maxRetries) {
                  http_response_code(500);
                  echo "Error al conectar con la base de datos";
                  exit;
              }
              sleep(1); // Esperar antes de reintentar
          }
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
      $sql = "SELECT ID, path FROM repos WHERE name = ? AND branch = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("ss", $repo, $branch);
      $stmt->execute();
      $result = $stmt->get_result();

      $localPath = __DIR__;
      $localPath = explode("/", $localPath);
      $localPath = $localPath[$level];

      if ($debug == 1) {
        error_log("Github Event: " . $headers["X-Github-Event"]);
        error_log($sql);
        error_log($localPath);
      }

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
            writeToLog("Status: OK " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Execution: " . $shell_res . " Time: " . date("Y-m-d H:i:s"));
            // if files is array
            if (is_array($files)) {
              writeToLog("Files: " . implode(", ", $files));
            }
            // log to database
            $sql = "INSERT INTO logs (event, repo, branch, commit, commitName, commitUser, created) VALUES ('" . $headers["X-Github-Event"] . "', '" . $repo . "', '" . $branch . "', '" . $commit . "', '" . $commitName . "', '" . $commitUser . "', '" . date("Y-m-d H:i:s") . "')";
            if ($db->query($sql) === TRUE) {
              writeToLog("Status: OK " . "Event Loged into DataBase" . " Time: " . date("Y-m-d H:i:s"));
            } else {
              writeToLog("Status: Error " . $sql . " Time: " . date("Y-m-d H:i:s"));
            }
            http_response_code(200);
          }
        } else {
          writeToLog("Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s"));
          writeToLog("Repo not found in database");
          http_response_code(400);
          exit;
        }
      } else {
        writeToLog("Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s"));
        writeToLog("Repo not found in database");
        http_response_code(400);
        exit;
      }
    } else {
      // log to file
      writeToLog("Status: Error " . "Event: " . $headers["X-Github-Event"] . " Committer: " . $committer . " Repo: " . $repo . " Time: " . date("Y-m-d H:i:s"));
      writeToLog($decodedBody . "\n");
      writeToLog("Headers: " . json_encode($headers) . "\n");
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

// Mejora en la verificación de firma
function verifySignature($body, $secret) {
  $headers = getallheaders();
  if (!isset($headers['X-Hub-Signature-256'])) {
      writeToLog("Falta el encabezado X-Hub-Signature-256");
      return false;
  }
  $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
  return hash_equals($signature, $headers['X-Hub-Signature-256']);
}

// Función para escribir en el archivo de log
function writeToLog($message) {
  global $dateNum;
  $logFilePath = "../logs/" . $dateNum . ".log";
  $log = fopen($logFilePath, "a");
  if ($log) {
      fwrite($log, $message . "\n");
      fclose($log);
  }
}
