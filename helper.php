<?php
session_start();

defined('_JEXEC') or die;

class modBazaarHelper
{
    public static function getParams() {
        // Get the module by name
        $module = JModuleHelper::getModule('mod_bazaar');
        
        // Create a registry object to handle the parameters
        $params = new JRegistry($module->params);

        define('DEBUG', $params->get('debug', true));
        define('BASE_URI', $params->get('base_uri', 'https://www.basar-horrheim.de/bazaar'));
        define('DB_SERVER', $params->get('db_server', 'localhost'));
        define('DB_USERNAME', $params->get('db_username', 'bazaaradmin'));
        define('DB_PASSWORD', $params->get('db_password', 'b4zaar4dm1n1strator'));
        define('DB_NAME', $params->get('db_name', 'bazaar_db'));
        define('SECRET', $params->get('secret', 'Of3lG8HGdf452nF653oFG93hGF93hf'));
        define('SMTP_FROM', $params->get('smtp_from', 'borga@basar-horrheim.de'));
        define('SMTP_FROM_NAME', $params->get('smtp_from_name', 'borga@basar-horrheim.de'));
		
		// Add the new parameter
		define('MODAL_MARGIN', $params->get('modal_margin', '100px'));
    }

	function generate_verification_token() {
		return bin2hex(random_bytes(16));
	}
	
	// Allow letters, numbers, space, hyphen, parentheses, and period
	function sanitize_input($input) {
		$input = preg_replace('/[^a-zA-Z0-9 \-\(\)\.]/', '', $input);
		$input = trim($input);
		return $input;
	}

	// allow only decimals
	function sanitize_id($input) {
		$input = preg_replace('/\D/', '', $input);
		$input = trim($input);
		return $input;
	}

	// Function to check for active bazaars
	function has_active_bazaar($conn) {
		$current_date = date('Y-m-d');
		$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bazaar WHERE startDate <= ?");
		$stmt->bind_param("s", $current_date);
		$stmt->execute();
		$result = $stmt->get_result()->fetch_assoc();
		return $result['count'] > 0;
	}

	function get_next_checkout_id($conn) {
		$stmt = $conn->prepare("SELECT MAX(checkout_id) AS max_checkout_id FROM sellers");
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->fetch_assoc()['max_checkout_id'] + 1;
	}

	// Function to generate a hash
	function generate_hash($email, $seller_id) {
		return hash('sha256', $email . $seller_id . SECRET);
	}

    public static function get_db_connection() {
        try {
            $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            self::debug_log("Database connection successful.\n");
            return $conn;
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("A database error occurred. Please try again later.");
        }
    }

    public static function getBazaarData($conn) {
        try {
            self::debug_log("Function getBazaarData(conn) called.\n");
            $sql = "SELECT id, startDate, startReqDate, max_sellers, mailtxt_reqnewsellerid, mailtxt_reqexistingsellerid FROM bazaar ORDER BY id DESC LIMIT 1";
            $result = $conn->query($sql);
            $bazaar = $result->fetch_assoc();

            $currentDate = new DateTime();
            $bazaarOver = true;
            $maxSellersReached = false;
            $canRequestSellerId = false;
            $formattedDate = '';
            $sellerMessage = '';
			$mailtxt_reqnewsellerid = '';
			$mailtxt_reqexistingsellerid = '';
			
            if ($bazaar) {
                $startReqDate = new DateTime($bazaar['startReqDate']);
                $startDate = new DateTime($bazaar['startDate']);
                $bazaarId = $bazaar['id'];
                $maxSellers = $bazaar['max_sellers'];
				$mailtxt_reqnewsellerid = $bazaar['mailtxt_reqnewsellerid'];
				$mailtxt_reqexistingsellerid = $bazaar['mailtxt_reqexistingsellerid'];
				
                $formattedDate = $startReqDate->format('d.m.Y');

                $canRequestSellerId = $currentDate >= $startReqDate && $currentDate <= $startDate;
                $bazaarOver = $currentDate > $startDate;

                $sql = "SELECT COUNT(*) as count FROM sellers WHERE bazaar_id = $bazaarId";
                $result = $conn->query($sql);
                $sellerCount = $result->fetch_assoc()['count'];
                $maxSellersReached = $sellerCount >= $maxSellers;
            }

            return compact('bazaarOver', 'maxSellersReached', 'canRequestSellerId', 'formattedDate', 'sellerMessage', 'mailtxt_reqnewsellerid', 'mailtxt_reqexistingsellerid');
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public static function handlePostRequest($conn) {
        global $mailtxt_reqnewsellerid, $mailtxt_reqexistingsellerid;

		try {
			// Retrieve the seller_id and hash from the URL
			$seller_id = isset($_GET['seller_id']) ? self::sanitize_id($_GET['seller_id']) : null;
			$hash = isset($_GET['hash']) ? self::sanitize_input($_GET['hash']) : null;

			// Debug log for all POST parameters
			$postParams = '';
			foreach ($_POST as $key => $value) {
				$postParams .= "$key: $value,\n";
			}
			self::debug_log("POST parameters: $postParams");
			self::debug_log("GET parameter seller_id: $seller_id");
			self::debug_log("GET parameter hash: $hash");
			
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_seller_id'])) {
				$email = $_POST['email']; // Email is typically validated rather than sanitized
				$family_name = self::sanitize_input($_POST['family_name']);
				$given_name = !empty($_POST['given_name']) ? self::sanitize_input($_POST['given_name']) : 'Nicht angegeben';
				$phone = self::sanitize_input($_POST['phone']);
				$street = !empty($_POST['street']) ? self::sanitize_input($_POST['street']) : 'Nicht angegeben';
				$house_number = !empty($_POST['house_number']) ? self::sanitize_input($_POST['house_number']) : 'Nicht angegeben';
				$zip = !empty($_POST['zip']) ? self::sanitize_input($_POST['zip']) : 'Nicht angegeben';
				$city = !empty($_POST['city']) ? self::sanitize_input($_POST['city']) : 'Nicht angegeben';
				$reserve = isset($_POST['reserve']) ? 1 : 0;
				$use_existing_number = $_POST['use_existing_number'] === 'yes';
				$consent = isset($_POST['consent']) && $_POST['consent'] === 'yes' ? 1 : 0;
				
				// SQL query to check existing seller
				$sql = "SELECT verification_token, verified FROM sellers WHERE email=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("s", $email);
				$stmt->execute();
				$result = $stmt->get_result();
				$existing_seller = $result->fetch_assoc();

                self::debug_log("Existing seller: " . ($existing_seller ? 'true' : 'false') . ".\n");

                if ($use_existing_number) {
                    self::debug_log("Executing: self::process_existing_number(conn, email: $email, consent: $consent, $mailtxt_reqexistingsellerid).\n");
                    self::process_existing_number($conn, $email, $consent, $mailtxt_reqexistingsellerid);
                } else {
                    if ($existing_seller) {
                        if (!empty($existing_seller['verification_token'])) {
							echo "<script>
								alert('Eine Verkäufernr-Anfrage wurde bereits generiert. Pro Verkäufer ist in der Regel nur eine Verkäufernr zulässig. Bitte melden Sie sich per Mail, wenn Sie eine weitere Nummer haben möchten, oder wenn Sie Probleme haben, Ihre bereits angefragte Nummer frei zu Schalten.');
							</script>";
                        } elseif ($existing_seller['verified']) {
							echo "<script>
								alert('Eine Verkäufer Nummer wurde bereits für Sie aktiviert. Pro Verkäufer ist in der Regel nur eine Verkäufernr zulässig. Bitte melden Sie sich per Mail, wenn Sie eine weitere Nummer haben möchten.');
							</script>";
                        } else {
                            self::debug_log("Executing: self::process_new_seller(conn, email: $email, family_name: $family_name, given_name: $given_name, phone: $phone, street: $street, house_number: $house_number, zip: $zip, city: $city, reserve: $reserve, consent: $consent, $mailtxt_reqnewsellerid).\n");
                            self::process_new_seller($conn, $email, $family_name, $given_name, $phone, $street, $house_number, $zip, $city, $reserve, $consent, $mailtxt_reqnewsellerid);
                        }
                    } else {
                        self::debug_log("Executing: self::process_new_seller(conn, email: $email, family_name: $family_name, given_name: $given_name, phone: $phone, street: $street, house_number: $house_number, zip: $zip, city: $city, reserve: $reserve, consent: $consent, $mailtxt_reqnewsellerid).\n");
                        self::process_new_seller($conn, $email, $family_name, $given_name, $phone, $street, $house_number, $zip, $city, $reserve, $consent, $mailtxt_reqnewsellerid);
                    }
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function process_existing_number($conn, $email, $consent, $mailtxt_reqexistingsellerid) {
        try {
			self::debug_log("process existing number.\n");
			
			$seller_id = $_POST['seller_id'];
			$stmt = $conn->prepare("SELECT id FROM sellers WHERE id = ? AND email = ?");
			$stmt->bind_param("is", $seller_id, $email);
			$stmt->execute();
			$result = $stmt->get_result();

            if ($result->num_rows > 0) {
				$hash = self::generate_hash($email, $seller_id);
				$bazaarId = self::get_current_bazaar_id($conn);
				$verification_token = self::generate_verification_token();

				$stmt = $conn->prepare("UPDATE sellers SET verification_token = ?, verified = 0, consent = ?, bazaar_id = ? WHERE id = ?");
				$stmt->bind_param("siii", $verification_token, $consent, $bazaarId, $seller_id);
				self::execute_sql_and_send_email($stmt, $email, $seller_id, $hash, $verification_token, $mailtxt_reqexistingsellerid);
            } else {
                self::showSellerMessage("Ungültige Verkäufer-ID oder E-Mail.");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function process_new_seller($conn,  $email, $family_name, $given_name, $phone, $street, $house_number, $zip, $city, $reserve, $consent, $mailtxt_reqnewsellerid) {
        try {
			self::debug_log("process new seller.\n");
			
			$seller_id = self::generate_unique_seller_id($conn);
			$hash = self::generate_hash($email, $seller_id);
			$bazaarId = self::get_current_bazaar_id($conn);
			$verification_token = self::generate_verification_token();

			$stmt = $conn->prepare("INSERT INTO sellers (id, email, reserved, verification_token, family_name, given_name, phone, street, house_number, zip, city, hash, bazaar_id, consent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
			$stmt->bind_param("isisssssssssis", $seller_id, $email, $reserve, $verification_token, $family_name, $given_name, $phone, $street, $house_number, $zip, $city, $hash, $bazaarId, $consent);
			self::execute_sql_and_send_email($stmt, $email, $seller_id, $hash, $verification_token, $mailtxt_reqnewsellerid);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function execute_sql_and_send_email($stmt, $email, $seller_id, $hash, $verification_token, $mailtxt) {
        global $seller_message, $BASE_URI, $given_name, $family_name;

        try {
			self::debug_log("execute sql and send mail.\n");
			$session = JFactory::getSession();
			
			$verification_link = BASE_URI . "/verify.php?token=$verification_token&hash=$hash";
			$create_products_link = BASE_URI . "/seller_products.php?seller_id=$seller_id&hash=$hash";
			$revert_link = BASE_URI . "/verify.php?action=revert&seller_id=$seller_id&hash=$hash";
			$delete_link = BASE_URI . "/flush.php?seller_id=$seller_id&hash=$hash";
	
            if ($stmt->execute()) {
				self::debug_log("verification link is: " . $verification_link ."\n");
                $subject = "Verifizierung Ihrer Verkäufer-ID: $seller_id";
				$message = str_replace(
					['{BASE_URI}', '{given_name}', '{family_name}', '{verification_link}', '{create_products_link}', '{revert_link}', '{delete_link}', '{seller_id}', '{hash}'],
					[BASE_URI, $given_name, $family_name, $verification_link, $create_products_link, $revert_link, $delete_link, $seller_id, $hash],
					$mailtxt
				);
                $send_result = self::send_email($email, $subject, $message);

                if ($send_result === true) {
					$session->set('messageBoxBazaar', [
						'text' => "Eine E-Mail mit einem Bestätigungslink wurde an " . htmlspecialchars($_POST['email']) . " gesendet.",
						'type' => 'success'
					]);
                } else {
					$session->set('messageBoxBazaar', [
						'text' => "Fehler beim Senden der Bestätigungs-E-Mail: " . send_result . "<br>" . $stmt->error,
						'type' => 'warning'
					]);
                }
            } else {
				$_SESSION['messageBox'] = [
					'text' => "Fehler: " . $stmt->error,
					'type' => 'warning'
				];
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            self::showSellerMessage("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
        }
    }

    public static function get_current_bazaar_id($conn) {
        try {
			self::debug_log("get current bazaar id: ");
            $currentDateTime = date('Y-m-d H:i:s');
            $sql = "SELECT id FROM bazaar WHERE startReqDate <= '$currentDateTime' AND startDate >= '$currentDateTime' LIMIT 1";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
				self::debug_log($row['id'] . " \n");
                return $row['id'];
            } else {
                return null;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public static function generate_unique_seller_id($conn) {
        try {
			self::debug_log("generate random seller id.\n");
            do {
                $seller_id = rand(1, 10000);
                $sql = "SELECT id FROM sellers WHERE id='$seller_id'";
                $result = $conn->query($sql);
            } while ($result->num_rows > 0);
            return $seller_id;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public static function encode_subject($subject) {
		self::debug_log("encode subject.\n");
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

	// Function to send verification email using PHP's mail function
	public static function send_email($to, $subject, $body) {
		self::debug_log("send mail.\n");
		
		$headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n"; 
		$headers .= "Reply-to: " . SMTP_FROM . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=utf-8";

		if (mail($to, self::encode_subject($subject), $body, $headers, "-f " . SMTP_FROM)) {
			return true;
		} else {
			return 'Mail Error: Unable to send email.';
		}
	}
	
	// Function to create debug messages
    public static function debug_log($message) {
        if (DEBUG) {
            echo "<pre>DEBUG: $message</pre>\n";
        }
    }
	
	// Function to show an alert box for the user
	public static function showMessage($message, $type) {
		$_SESSION['messageBox'] = [
			'text' => $message,
			'type' => $type  // Types can be 'success', 'info', 'warning', 'danger', etc.
		];
	}
}
?>