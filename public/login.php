<?php
/**
 * Login page and interface
 * no password login, emails and domains authorized
 */
// start session
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Dotenv();
$dotenv->load('../.env');
// if $_POST is not empty
if (!empty($_POST)){
    // connect database
    $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
    // if POST request try generate token
    // check email or domain in database
    $email = $_POST['email'];
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        // show error bootstrap alert
        include '../templates/login_header.html';
        echo '<div class="alert alert-danger" role="alert">';
        // show error message
        echo 'Error: Email no valido';
        echo '</div>';
        include '../templates/login_footer.html';
        exit();
    }
    $domain = explode("@", $email);
    // check email user is valid alphanumeric
    $tempuser = str_replace(".","",$domain[0]);
    if (ctype_alnum($tempuser) === false) {
        // show error bootstrap alert
        include '../templates/login_header.html';
        echo '<div class="alert alert-danger" role="alert">';
        // show error message
        echo 'Error: Email no valido.';
        echo '</div>';
        include '../templates/login_footer.html';
        exit();
    }
    $domain = $domain[1];
    // check domain is valid
    if (checkdnsrr($domain, "MX") === false) {
        // show error bootstrap alert
        include '../templates/login_header.html';
        echo '<div class="alert alert-danger" role="alert">';
        // show error message
        echo 'Error: Email no valido..';
        echo '</div>';
        include '../templates/login_footer.html';
        exit();
    }
    // check table authorized types and text
    $sql = "SELECT * FROM authorized WHERE type = 'domain' AND text = '".$domain."'";
    // query database
    $token = "";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        // generate new token
        $token = bin2hex(random_bytes(32));
    }else{
        $sql = "SELECT * FROM authorized WHERE type = 'email' AND text = '".$email."'";
        // query database
        $result = $db->query($sql);
        if ($result->num_rows > 0) {
            // generate new token
            $token = bin2hex(random_bytes(32));
        }
    }
    if ($token != "") {
        // save token in database
        $sql = "INSERT INTO tokens (email, token) VALUES ('".$email."', '".$token."') ".
                "ON DUPLICATE KEY UPDATE token = '".$token."'";
        // query database
        $result = $db->query($sql);

        // TODO Change to PHPMailer
        // send email with token
        $message = "You have requested access to ". $_ENV['site_url'] . " <br>\n<br>\n".
            "If you not recognize this action please delete this email<br>\n".
            "Click here to login: <br>\n".
            "<a href='". $_ENV['site_url'] .'/login.php?user='. $email .'&token=' . $token . "'>Login</a>";
        $headers = "From: " . $_ENV['sender'] . "\r \n";
        $headers .= "Reply-To: ". $_ENV['sender'] . "\r \n";
        $headers .= "MIME-Version: 1.0\r \n";
        // html utf8
        $headers .= "Content-Type: text/html; charset=UTF-8\r \n";
        $to = $email;
        $subject = "Login to " . $_ENV['site_url'];
        // send email with custom smtp
        mail($to, $subject, $message, $headers);
        include '../templates/login_header.html';
        // show success bootstrap alert
        echo '<div class="alert alert-success" role="alert">';
        echo 'Favor revisar su correo';
        echo '</div>';
        include '../templates/login_footer.html';
    }
}// if $_GET is not empty and token is not empty
elseif(!empty($_GET) && !empty($_GET['token'])){
    // validate token alfanumeric
    if (ctype_alnum($_GET['token']) === false) {
        // show error bootstrap alert
        include '../templates/login_header.html';
        echo '<div class="alert alert-danger" role="alert">';
        // show error message
        echo 'Error: Token no valido';
        echo '</div>';
        include '../templates/login_footer.html';
        exit();
    }
    // connect database
    $db = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_password'], $_ENV['db_name']);
    // check token, email in database
    $sql = "SELECT * FROM tokens WHERE email = '".$_GET['user']."' AND token = '".$_GET['token']."'";
    // query database
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        // check token expiration
        $row = $result->fetch_assoc();
        $updated = new DateTime($row['updated']);
        $now = new DateTime();
        $diff = $updated->diff($now);
        // if token is expired
        if ($diff->days > 30) {
            // show error bootstrap alert
            include '../templates/login_header.html';
            echo '<div class="alert alert-danger" role="alert">';
            // show error message
            echo 'Error: Token expirado';
            echo '</div>';
            include '../templates/login_footer.html';
            exit();
        }

        // save token in session
        $_SESSION['token'] = $_GET['token'];
        $_SESSION['user'] = $_GET['user'];
        // redirect to index.php
        header('Location: index.php');
    }else{
        // show error bootstrap alert
        include '../templates/login_header.html';
        echo '<div class="alert alert-danger" role="alert">';
        // show error message
        echo 'Error: Token no valido';
        echo '</div>';
        include 'login_footer.html';
        exit();
    }

}// else show login form
else{
    include '../templates/login_header.html';
?>
                <form action="/login.php?" method="post" name="login" role="form" id="login-form">
                    <div class="form-group">
                        <input type="email" name="email" class="form-control" id="inputEmail3" placeholder="User email">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit" class="btn btn-info btn-block mb-4" value="Login">Login</button>
                    </div>
                </form>
<?php
    include '../templates/login_footer.html';
}