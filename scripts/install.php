<?php
/** 
 * Install cli mode
 * connect to database
 * create tables if not exists
 */
// make sure we are running from the command line
if (php_sapi_name() !== 'cli') {
    exit;
}
// load env variables
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Dotenv();
$dotenv->load('../.env');
// check env variables for database
if (!$_ENV['db_host'] || !$_ENV['db_name'] || !$_ENV['db_user'] || !$_ENV['db_password']) {
    echo "Error: Missing database credentials information \n";
    notInstalled();
}
// validate other vars secret sender site_url
if (!$_ENV['secret']){
    echo "Error: Missing secret\n";
    echo "Please set a secret in the .env file\n";
    notInstalled();
}
if (!$_ENV['sender']){
    echo "Error: Missing sender \n";
    echo "Please set a sender in the .env file\n";
    notInstalled();
}
if (!$_ENV['site_url']){
    echo "Error: Missing site_url \n";
    echo "Please set a site_url in the .env file\n";  
    notInstalled();
}
// check if already installed
if ($_ENV['status'] == 'installed') {
    echo "Error: Already installed \n";
    echo "If you need reinstall change .env file manually \n";
    exit;
}

// connect to database
try {
    $db = new PDO('mysql:host='.$_ENV['db_host'].';dbname='.$_ENV['db_name'].';charset=utf8', $_ENV['db_user'], $_ENV['db_password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error: Could not connect to database \n";
    echo "Please check your database credentials in the .env file \n";
    notInstalled();
}
// all good message
echo "Database connection successful \n";
echo "Checking database tables \n";
// validate table authorized exists
$sql = "SELECT 1 FROM authorized LIMIT 1";
$result = $db->query($sql);
$row = $result->fetch();
if ($row != false) {
    echo "Updating Database \n";
    $dbexist = true;
} else {
    echo "Creating Database \n";
    $dbexist = false;
}
// create database tables using sql file
$sql = file_get_contents('scripts/install.sql');
try {
    $db->exec($sql);
    if ($dbexist) {
        echo "Database tables updated successfully \n";
    } else {
        echo "Database tables created successfully \n";
    }
} catch (PDOException $e) {
    echo "Error: Could not create database tables \n";
    echo "Please check your database credentials in the .env file \n";
    notInstalled();
}

// change .env status to installed
$env = file_get_contents('../.env');
$env = str_replace('status=uninstalled', 'status=installed', $env);
file_put_contents('../.env', $env);
echo "Installation complete \n";
exit;

function notInstalled(){
    // change .env status to uninstalled
    $env = file_get_contents('../.env');
    $env = str_replace('status=installed', 'status=uninstalled', $env);
    file_put_contents('../.env', $env);
    exit;
}