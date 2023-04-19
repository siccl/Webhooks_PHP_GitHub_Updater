<?php
/** 
 * Cli interface
 * create, list, update and delete repos
 * list and delete tokens 
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
// generate menu
$help = "Webhooks PHP GitHub Updater CLI\n";
$help .= "Usage: php console.php [command] [options]\n";
$help .= "Commands:\n";
$help .= "  create [name] [branch] [path] - Create a new repo\n";
$help .= "  list - List all repos\n";
$help .= "  update [name] [branch] [path] - Update a repo\n";
$help .= "  delete [name] [branch] - Delete a repo\n";
$help .= "  list-tokens - List all tokens\n";
$help .= "  delete-token [token] - Delete a token\n";
$help .= "Options:\n";
$help .= "  -h, --help - Show this help\n";
// get command
$command = $argv[1] ?? null;
// get options
$options = $argv[2] ?? null;
// get arguments
$arguments = array_slice($argv, 2);
// validate command and options
if (!$command) {
    echo $help;
    exit;
}elseif ($command == '-h' || $command == '--help') {
    echo $help;
    exit;
}else{
    if ($command == 'create') {
        if (count($arguments) < 4) {
            echo "Error: Missing arguments \n";
            echo "Usage: php console.php create [name] [branch] [path] \n";
            exit;
        }
        $name = $arguments[0];
        $branch = $arguments[1];
        $path = $arguments[2];
        // check if repo exists
        $repo = $db->prepare("SELECT * FROM repos WHERE name = :name AND branch = :branch");
        $repo->execute([':name' => $name, ':branch' => $branch]);
        $repo = $repo->fetch(PDO::FETCH_ASSOC);
        if ($repo) {
            echo "Error: Repo already exists \n";
            exit;
        }
        // check if path exists
        if (!is_dir($path)) {
            echo "Error: Path does not exist \n";
            exit;
        }
        // create repo
        $create = $db->prepare("INSERT INTO repos (name, branch, path) VALUES (:name, :branch, :path)");
        $create->execute([':name' => $name, ':branch' => $branch, ':path' => $path]);
        echo "Repo created \n";
        exit;
    }elseif ($command == 'list') {
        $repos = $db->query("SELECT * FROM repos");
        $repos = $repos->fetchAll(PDO::FETCH_ASSOC);
        if (!$repos) {
            echo "No repos found \n";
            exit;
        }
        echo "Repos: \n";
        foreach ($repos as $repo) {
            echo "  {$repo['name']} {$repo['branch']} {$repo['path']} \n";
        }
        exit;
    }elseif ($command == 'update') {
        if (count($arguments) < 4) {
            echo "Error: Missing arguments \n";
            echo "Usage: php console.php update [name] [branch] [path] \n";
            exit;
        }
        $name = $arguments[0];
        $branch = $arguments[1];
        $path = $arguments[2];
        // check if repo exists
        $repo = $db->prepare("SELECT * FROM repos WHERE name = :name AND branch = :branch");
        $repo->execute([':name' => $name, ':branch' => $branch]);
        $repo = $repo->fetch(PDO::FETCH_ASSOC);
        if (!$repo) {
            echo "Error: Repo does not exist \n";
            exit;
        }
        // check if path exists
        if (!is_dir($path)) {
            echo "Error: Path does not exist \n";
            exit;
        }
        // update repo
        $update = $db->prepare("UPDATE repos SET path = :path WHERE name = :name AND branch = :branch");
        $update->execute([':path' => $path, ':name' => $name, ':branch' => $branch]);
        echo "Repo updated \n";
        exit;
    }elseif ($command == 'delete') {
        if (count($arguments) < 3) {
            echo "Error: Missing arguments \n";
            echo "Usage: php console.php delete [name] [branch] \n";
            exit;
        }
        $name = $arguments[0];
        $branch = $arguments[1];
        // check if repo exists
        $repo = $db->prepare("SELECT * FROM repos WHERE name = :name AND branch = :branch");
        $repo->execute([':name' => $name, ':branch' => $branch]);
        $repo = $repo->fetch(PDO::FETCH_ASSOC);
        if (!$repo) {
            echo "Error: Repo does not exist \n";
            exit;
        }
        // delete repo
        $delete = $db->prepare("DELETE FROM repos WHERE name = :name AND branch = :branch");
        $delete->execute([':name' => $name, ':branch' => $branch]);
        echo "Repo deleted \n";
        exit;
    }elseif ($command == 'list-tokens') {
        $tokens = $db->query("SELECT * FROM tokens");
        $tokens = $tokens->fetchAll(PDO::FETCH_ASSOC);
        if (!$tokens) {
            echo "No tokens found \n";
            exit;
        }
        echo "Tokens: \n";
        foreach ($tokens as $token) {
            echo " {$token['email']}  {$token['token']} {$token['updated']} \n";
        }
        exit;
    }elseif ($command == 'delete-token') {
        if (count($arguments) < 2) {
            echo "Error: Missing arguments \n";
            echo "Usage: php console.php delete-token [token] \n";
            exit;
        }
        $token = $arguments[0];
        // check if token exists
        $token = $db->prepare("SELECT * FROM tokens WHERE token = :token");
        $token->execute([':token' => $token]);
        $token = $token->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            echo "Error: Token does not exist \n";
            exit;
        }
        // delete token
        $delete = $db->prepare("DELETE FROM tokens WHERE token = :token");
        $delete->execute([':token' => $token]);
        echo "Token deleted \n";
        exit;
    }else{
        echo "Error: Invalid command \n";
        echo $help;
        exit;
    }
}
