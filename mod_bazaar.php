<?php
// No direct access
defined('_JEXEC') or die;

// Define the path to the bazaar directory within the module
$bazaarPath = __DIR__ . '/bazaar/';

// Change the working directory to the bazaar directory
chdir($bazaarPath);

// Determine which page to load based on a query parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'index';
$allowedPages = ['index', 'admin_login', 'first_time_setup', 'seller_products']; // Add allowed page names here
$file_path = in_array($page, $allowedPages) ? $page . '.php' : 'index.php';

if (file_exists($file_path)) {
    // Include the main PHP file
    include $file_path;
} else {
    echo '<p>File not found: ' . htmlspecialchars($bazaarPath . $file_path) . '</p>';
}
?>