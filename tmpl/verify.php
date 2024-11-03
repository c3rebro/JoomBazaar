<?php
defined('_JEXEC') or die;

// Include the helper file
require_once __DIR__ . '/../helper.php'; // Use relative path to include helper.php

// Initialize parameters
modBazaarHelper::getParams();

if (isset($_GET['token']) && isset($_GET['hash'])) {
    $token = $_GET['token'];
    $hash = $_GET['hash'];

    $conn = modBazaarHelper::get_db_connection();
    $sql = "SELECT id, email FROM sellers WHERE verification_token='$token' AND verified=0 AND hash='$hash'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $seller = $result->fetch_assoc();
        $seller_id = $seller['id'];
        $email = $seller['email'];

        // Verify the hash
        $expected_hash = hash('sha256', $email . $seller_id . SECRET);
        if ($hash === $expected_hash) {
            // Retrieve the current bazaar's ID
            $sql = "SELECT id FROM bazaar ORDER BY id DESC LIMIT 1";
            $bazaar_result = $conn->query($sql);

            if ($bazaar_result->num_rows > 0) {
                $current_bazaar = $bazaar_result->fetch_assoc();
                $current_bazaar_id = $current_bazaar['id'];

                // Mark the seller as verified and set the bazaar_id
                $sql = "UPDATE sellers SET verified=1, verification_token=NULL, bazaar_id='$current_bazaar_id' WHERE id='$seller_id'";
                if ($conn->query($sql) === TRUE) {
                    // Redirect to product creation page
			header("Location: " . BASE_URI . "?page=seller_products&seller_id=$seller_id&hash=$hash");
                    exit();
                } else {
                    echo "Fehler beim Verifizieren des Kontos: " . $conn->error;
                }
            } else {
                echo "Kein aktueller Bazaar gefunden.";
            }
        } else {
            echo "Ungültiger Hash.";
        }
    } else {
        echo "Ungültiger oder abgelaufener Token.";
    }

    $conn->close();
} else {
    echo "Kein Token oder Hash angegeben.";
}
?>