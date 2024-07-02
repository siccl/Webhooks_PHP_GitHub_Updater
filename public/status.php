<?php
/**
 * Monitor status of all repositories
 */
// Función para redirigir al login
function redirectToLogin() {
  header('Location: login.php');
  exit();
}
// start session
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Dotenv();
$dotenv->load('../.env');
// validate session
if (empty($_SESSION['token']) || ctype_alnum($_SESSION['token']) === false || strlen($_SESSION['token']) != 64 || filter_var($_SESSION['user'], FILTER_VALIDATE_EMAIL) === false) {
  redirectToLogin();
}

// connect database
$db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
if ($db->connect_errno) {
  die("Error de conexión: " . $db->connect_error);
}

// Validación de token y usuario con consulta preparada
$stmt = $db->prepare("SELECT count(*) exist FROM tokens WHERE email = ? AND token = ?");
$stmt->bind_param("ss", $_SESSION['user'], $_SESSION['token']);
$stmt->execute();
$result = $stmt->get_result();
$validate = $result->fetch_assoc();
if ($validate['exist'] == 0){
  redirectToLogin();
}

// show status
include '../templates/status_header.html';
// for all repo and branch combinations in repos table show last commit from logs table
// get all repos
$sql = "SELECT * FROM repos";
$repos = $db->query($sql);

$stmt = $db->prepare("SELECT * FROM logs WHERE repo = ? and branch = ? ORDER BY id DESC LIMIT 3");

while ($repo = $repos->fetch_assoc()) {
  $stmt->bind_param("ss", $repo['name'], $repo['branch']);
  $stmt->execute();
  $commit = $stmt->get_result()->fetch_assoc();
  // show last commit
  // generate subtitles with repo name and branch
  ?>
  <table class="table table-striped">
      <thead>
          <tr>
              <th scope='col'>Rama</th>
              <th scope='col'>Path</th>
              <th scope='col'>Commit</th>
              <th scope='col'>Usuario</th>
              <th scope='col'>Fecha</th>
          </tr>
      </thead>
      <tbody>
          <tr>
              <td><b><?= htmlspecialchars($repo['name']) ?></b></td>
              <td><b><?= htmlspecialchars($repo['branch']) ?></b></td>
              <td><b><?= htmlspecialchars($repo['path']) ?></b></td>
              <td><?= isset($commit['commitName']) ? htmlspecialchars($commit['commitName']) : '' ?></td>
              <td><?= isset($commit['commitUser']) ? htmlspecialchars($commit['commitUser']) : '' ?></td>
              <td><?= isset($commit['created']) ? htmlspecialchars($commit['created']) : '' ?></td>
          </tr>
      </tbody>
  </table>
  <?php
}
include '../templates/status_footer.html';
// close database
$db->close();