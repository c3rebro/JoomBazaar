<?php
session_start();
defined('_JEXEC') or die;

// Include the helper file
require_once __DIR__ . '/helper.php';

// Initialize parameters
modBazaarHelper::getParams();

if(DEBUG) {
	modBazaarHelper::debug_log("Get Parameters: success\n");
}

// Get the Joomla application input
$input = JFactory::getApplication()->input;

// Get the 'page' parameter from the URL
$page = $input->get('page', 'default', 'CMD');

// Define allowed pages
$allowedPages = ['default', 'seller_products', 'verify'];

// Determine the file path based on the 'page' parameter
$file_path = in_array($page, $allowedPages) ? __DIR__ . '/tmpl/' . $page . '.php' : __DIR__ . '/tmpl/default.php';

if(DEBUG) {
	modBazaarHelper::debug_log("Call page: " . $page . "\n");
	modBazaarHelper::debug_log("Current Session ID: " . session_id() . "\n");
}

try {
    $conn = modBazaarHelper::get_db_connection();

    // Extract the data into the global scope
    global $bazaarID, $bazaarOver, $maxSellersReached, $canRequestSellerId, $formattedDate, $sellerMessage, $mailtxt_reqnewsellerid, $mailtxt_reqexistingsellerid;
    extract(modBazaarHelper::getBazaarData($conn));

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        modBazaarHelper::handlePostRequest($conn);
    }

    $conn->close();
	
	require JModuleHelper::getLayoutPath('mod_bazaar', $page);
	
	} catch (Exception $e) {
		if (DEBUG) {
			echo "<pre>Error: " . $e->getMessage() . "</pre>";
		} else {
			error_log("An error occurred: " . $e->getMessage());
			echo "An unexpected error occurred. Please try again later.";
		}
		return;
}
?>