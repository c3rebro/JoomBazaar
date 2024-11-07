<?php
// Debug configuration
define('DEBUG', false); // Set to false to disable debug outputs

define('NWND_URI', 'https://www.basar-horrheim.de/bazaar');
define('BASE_URI', 'https://www.basar-horrheim.de/index.php/bazaar/?page=');

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'bazaaradmin');
define('DB_PASSWORD', 'b4zaar4dm1n1strator');
define('DB_NAME', 'bazaar_db');

define('SECRET', 'Of3lG8HGdf452nF653oFG93hGF93hf');

// SMTP configuration
define('SMTP_FROM', 'borga@basar-horrheim.de');
define('SMTP_FROM_NAME', 'Basar Organisation');

// Function to initialize the database connection
function get_db_connection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Function to encode the subject to handle non-ASCII characters
function encode_subject($subject) {
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

// Function to send verification email using PHP's mail function
function send_email($to, $subject, $body) {
	$headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n"; 
	$headers .= "Reply-to: " . SMTP_FROM . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8";

    if (mail($to, encode_subject($subject), $body, $headers, "-f " . SMTP_FROM)) {
        return true;
    } else {
        return 'Mail Error: Unable to send email.';
    }
}

// Function to output debug messages
function debug_log($message) {
    if (DEBUG) {
        echo "<pre>DEBUG: $message</pre>";
    }
}

?>