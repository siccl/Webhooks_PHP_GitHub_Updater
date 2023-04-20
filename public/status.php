<?php
/**
 * Monitor status of all repositories
 */
// start session
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Dotenv();
$dotenv->load('../.env');
// validate session
if (empty($_SESSION['token'])){
  header('Location: login.php');
  exit();
}
// validate token 64 alphanumeric characters
if (ctype_alnum($_SESSION['token']) === false || strlen($_SESSION['token']) != 64) {
  header('Location: login.php');
  exit();
}
// validate user email
if (filter_var($_SESSION['user'], FILTER_VALIDATE_EMAIL) === false) {
  header('Location: login.php');
  exit();
}
// connect database
$db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
if ($db->connect_errno) {
  echo "Error: ".$db->connect_error;
  exit;
}
// validate token and user
$sql = "SELECT count(*) exist FROM tokens WHERE email = '".$_SESSION['user']."' AND token = '".$_SESSION['token']."'";
$validate = $db->query($sql)->fetch_assoc();
if ($validate['exist'] == 0){
  header('Location: login.php');
  exit();
}
// show status
include '../templates/status_header.html';
// for all repo and branch combinations in repos table show last commit from logs table
// get all repos
$sql = "SELECT * FROM repos";
$repos = $db->query($sql);
// for each repo
while ($repo = $repos->fetch_assoc()) {
  // get last commit
  $sql = "SELECT * FROM logs WHERE repo = ".$repo['name']." ORDER BY id DESC LIMIT 1";
  $commit = $db->query($sql)->fetch_assoc();
  // show last commit
  // generate subtitles with repo name and branch
  echo "<table class='table table-striped table-bordered table-hover table-sm'>";
    echo "<thead class='thead-dark'>";
        echo "<tr>";
            echo "<th scope='col'>Repositorio</th>";
            echo "<th scope='col'>Rama</th>";
            echo "<th scope='col'>Commit</th>";
            echo "<th scope='col'>Usuario</th>";
            echo "<th scope='col'>Fecha</th>";
        echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
        echo "<tr>";
            echo "<td><b>".$repo['name']."</b></td>";
            echo "<td><b>".$repo['branch']."</b></td>";
            echo "<td>".$commit['commitName']."</td>";
            echo "<td>".$commit['commitUser']."</td>";
            echo "<td>".$commit['created']."</td>";
        echo "</tr>";
    echo "</tbody>";
    echo "</table>";
}
include '../templates/status_footer.html';