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
// get body
$body = file_get_contents("php://input");
$decodedBody = json_decode($body);
// verify signature
if ($body!=""){
  if (verifySignature($body,$secret) !== false) {
      // verified
      echo "authorized";
      // identify webhook event
      $headers = getallheaders();
      // identify repository
      $repo = $decodedBody->repository->name;
        
      $files = @$decodedBody->commits[0]->modified;
      $committer = @$decodedBody->commits[0]->committer->name;
      // obtener nombre de la rama
      $branch = $decodedBody->ref;
      $branch = str_replace("refs/heads/", "", $branch);
      if (!empty($files)||!empty($committer)) {
        // obtener el hash del commit
        $commit = $decodedBody->after;
        // obtener el nombre del commit
        $commitName = $decodedBody->commits[0]->message;
        // obtener el nombre del usuario que hizo el commit
        $commitUser = $decodedBody->commits[0]->committer->name;
      }
      // DDL table repos (id, name, branch, path)
      // DDL table logs (id, event, repo, branch, commit, commitName, commitUser, created)
      if ($headers["X-Github-Event"] == "push") {
        // connect database
        $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
        if ($db->connect_errno) {
          $log = fopen("../logs/".$dateNum.".log", "a");
          fwrite($log, "Status: Error "."Event: ".$headers["X-Github-Event"]." Committer: ".$committer." Repo: ".$repo." Time: ".date("Y-m-d H:i:s")."\n");
          fwrite($log, $db->connect_error."\n");
          fclose($log);
          http_response_code(400);
          exit;
        }
        // find repo data in database where name = $repo and branch = $branch
        $sql = "SELECT ID, path FROM repos WHERE name = '".$repo."' AND branch = '".$branch."'";
        $result = $db->query($sql);
        if ($result->num_rows > 0) {
          // output data of each row
          while($row = $result->fetch_assoc()) {
            $id = $row["ID"];
            $path = $row["path"];
            // execute git pull in path
            $result = shell_exec("cd ".$path."; git pull");
            // log to file
            $log = fopen("../logs/".$dateNum.".log", "a");
            fwrite($log, "Status: OK "."Event: ".$headers["X-Github-Event"]." Committer: ".$committer." Repo: ".$repo." Execution: ". $result ." Time: ".date("Y-m-d H:i:s")."\n");
            // if files is array
            if (is_array($files)) {
              fwrite($log, "Files: ".implode(", ", $files)."\n");
            }
            fclose($log);
            // log to database
            $sql = "INSERT INTO logs (event, repo, branch, commit, commitName, commitUser, created) VALUES ('".$headers["X-Github-Event"]."', '".$repo."', '".$branch."', '".$commit."', '".$commitName."', '".$commitUser."', '".date("Y-m-d H:i:s")."')";
            if ($db->query($sql) === TRUE) {
              $log = fopen("../logs/".$dateNum.".log", "a");
              fwrite($log, "Status: OK "."Event Loged into DataBase");
              fclose($log);
            } else {
              $log = fopen("../logs/".$dateNum.".log", "a");
              fwrite($log, "Error: " . $sql . "<br>" . $db->error."\n");
              fclose($log);
            }
            http_response_code(200);
          }
        } else {
          $log = fopen("../logs/".$dateNum.".log", "a");
          fwrite($log, "Status: Error "."Event: ".$headers["X-Github-Event"]." Committer: ".$committer." Repo: ".$repo." Time: ".date("Y-m-d H:i:s")."\n");
          fwrite($log, "Repo not found in database"."\n");
          fclose($log);
          http_response_code(400);
          exit;
        }
      }else{
        // log to file
        $log = fopen("../logs/".$dateNum.".log", "a");
        fwrite($log, "Status: Error "."Event: ".$headers["X-Github-Event"]." Committer: ".$committer." Repo: ".$repo." Time: ".date("Y-m-d H:i:s")."\n");
        fwrite($log, $decodedBody."\n");
        fwrite($log, "Headers: ".json_encode($headers)."\n");
        fclose($log);
        http_response_code(400);
      }
  } else {
    http_response_code(403);
    echo "unauthorized";
    exit;
  }
}else{
    http_response_code(400);
    echo "nothing to do";
    exit;
}
function verifySignature($body,$secret){
  $headers = getallheaders();
  if (!isset($headers['X-Hub-Signature-256'])) {
    return false;
  }
  return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $headers['X-Hub-Signature-256']); 
}