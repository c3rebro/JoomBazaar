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

    public static function initialize_database($conn) {
        try {
            self::debug_log("Creating database.\n");
            $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
            if ($conn->query($sql) !== TRUE) {
                throw new Exception("Error creating database: " . $conn->error);
            }

            $conn->select_db(DB_NAME);

            $tables = [
                "bazaar" => "CREATE TABLE IF NOT EXISTS bazaar (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    startDate DATE NOT NULL,
                    startReqDate DATE NOT NULL,
                    max_sellers INT NOT NULL,
                    brokerage DOUBLE,
                    min_price DOUBLE,
                    price_stepping DOUBLE,
                    mailtxt_reqnewsellerid TEXT,
                    mailtxt_reqexistingsellerid TEXT
                )",
                "sellers" => "CREATE TABLE IF NOT EXISTS sellers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    hash VARCHAR(255) NOT NULL,
                    bazaar_id INT(11) DEFAULT 0,
                    email VARCHAR(255) NOT NULL,
                    reserved BOOLEAN DEFAULT FALSE,
                    verified BOOLEAN DEFAULT FALSE,
                    checkout BOOLEAN DEFAULT FALSE,
                    checkout_id INT(6) DEFAULT 0,
                    verification_token VARCHAR(255),
                    family_name VARCHAR(255) NOT NULL,
                    given_name VARCHAR(255) NOT NULL,
                    phone VARCHAR(255) NOT NULL,
                    street VARCHAR(255) NOT NULL,
                    house_number VARCHAR(255) NOT NULL,
                    zip VARCHAR(255) NOT NULL,
                    city VARCHAR(255) NOT NULL,
                    consent BOOLEAN
                )",
                "products" => "CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bazaar_id INT(10) DEFAULT 0,
                    name VARCHAR(255) NOT NULL,
                    size VARCHAR(255) NOT NULL,
                    price DOUBLE NOT NULL,
                    barcode VARCHAR(255) NOT NULL,
                    seller_id INT,
                    sold BOOLEAN DEFAULT FALSE,
                    FOREIGN KEY (seller_id) REFERENCES sellers(id)
                )",
                "users" => "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'cashier') NOT NULL
                )",
                "settings" => "CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    operationMode VARCHAR(50) NOT NULL DEFAULT 'online',
                    wifi_ssid VARCHAR(255) DEFAULT '',
                    wifi_password VARCHAR(255) DEFAULT ''
                )"
            ];

            foreach ($tables as $name => $sql) {
                self::debug_log("Check table: $name.\n");
                if ($conn->query($sql) !== TRUE) {
                    throw new Exception("Error creating $name table: " . $conn->error);
                }
            }

            $sql = "SELECT COUNT(*) as count FROM users";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            return $row['count'] == 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("An error occurred during database initialization.");
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
			$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : null;
			$hash = isset($_GET['hash']) ? $_GET['hash'] : null;

			// Debug log for all POST parameters
			$postParams = '';
			foreach ($_POST as $key => $value) {
				$postParams .= "$key: $value,\n";
			}
			self::debug_log("POST parameters: $postParams");
			self::debug_log("GET parameter seller_id: $seller_id");
			self::debug_log("GET parameter hash: $hash");
			
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_seller_id'])) {
                $email = $_POST['email'];
                $family_name = $_POST['family_name'];
                $given_name = !empty($_POST['given_name']) ? $_POST['given_name'] : 'Nicht angegeben';
                $phone = $_POST['phone'];
                $street = !empty($_POST['street']) ? $_POST['street'] : 'Nicht angegeben';
                $house_number = !empty($_POST['house_number']) ? $_POST['house_number'] : 'Nicht angegeben';
                $zip = !empty($_POST['zip']) ? $_POST['zip'] : 'Nicht angegeben';
                $city = !empty($_POST['city']) ? $_POST['city'] : 'Nicht angegeben';
                $reserve = isset($_POST['reserve']) ? 1 : 0;
                $use_existing_number = $_POST['use_existing_number'] === 'yes';
                $consent = isset($_POST['consent']) && $_POST['consent'] === 'yes' ? 1 : 0;
				
                $sql = "SELECT verification_token, verified FROM sellers WHERE email='$email'";
                $result = $conn->query($sql);
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
			
			// Handle product creation form submission
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_product'])) {
				$bazaar_id = self::get_current_bazaar_id($conn);
				$name = $conn->real_escape_string($_POST['name']);
				$size = $conn->real_escape_string($_POST['size']);
				$price = $conn->real_escape_string($_POST['price']);

				$rules = get_bazaar_pricing_rules($conn, $bazaar_id);
				$min_price = $rules['min_price'];
				$price_stepping = $rules['price_stepping'];

				self::debug_log("Create Product parameters:\n" . 
				"bazaar_id = " . $bazaar_id . "\nname = " . $name . "\nsize = " . $size . "\nprice = " . $price . 
				"\nrules = " . $rules . "\nmin_price = " . $min_price . "\nprice_stepping = " . $price_stepping);

				// Validate price
				if ($price < $min_price) {
					$_SESSION['messageBox'] = [
						'text' => "Der eingegebene Preis ist niedriger als der Mindestpreis von $min_price €.",
						'type' => 'warning'  // Types can be 'success', 'info', 'warning', 'danger', etc.
					];
				} elseif (fmod($price, $price_stepping) != 0) {
					$_SESSION['messageBox'] = [
						'text' => "Der Preis muss in Schritten von $price_stepping € eingegeben werden.",
						'type' => 'warning'  // Types can be 'success', 'info', 'warning', 'danger', etc.
					];
				} else {
					// Generate a unique EAN-13 barcode
					do {
						$barcode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT) . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // Ensure 12-digit barcode as string
						$checkDigit = calculateCheckDigit($barcode);
						$barcode .= $checkDigit; // Append the check digit to make it a valid EAN-13 barcode
						self::debug_log("barcode created (data): " . $barcode);
						$sql = "SELECT id FROM products WHERE barcode='$barcode'";
						$result = $conn->query($sql);
					} while ($result->num_rows > 0);

					// Insert product into the database
					$sql = "INSERT INTO products (name, size, price, barcode, bazaar_id, seller_id) VALUES ('$name', '$size', '$price', '$barcode', '$bazaar_id', '$seller_id')";
					if ($conn->query($sql) === TRUE) {
						echo "<div class='alert alert-success'>Artikel erfolgreich erstellt.</div>";
					} else {
						echo "<div class='alert alert-danger'>Fehler beim Erstellen des Artikels: " . $conn->error . "</div>";
					}
				}
			}

			// Handle product update form submission
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
				$bazaar_id = self::get_current_bazaar_id($conn);
				$product_id = $conn->real_escape_string($_POST['product_id']);
				$name = $conn->real_escape_string($_POST['name']);
				$size = $conn->real_escape_string($_POST['size']);
				$price = $conn->real_escape_string($_POST['price']);

				$rules = get_bazaar_pricing_rules($conn, $bazaar_id);
				$min_price = $rules['min_price'];
				$price_stepping = $rules['price_stepping'];

				// Validate price
				if ($price < $min_price) {
					$update_validation_message = "Der eingegebene Preis ist niedriger als der Mindestpreis von $min_price €.";
				} elseif (fmod($price, $price_stepping) != 0) {
					$update_validation_message = "Der Preis muss in Schritten von $price_stepping € eingegeben werden.";
				} else {
					$sql = "UPDATE products SET name='$name', price='$price', size='$size', bazaar_id='$bazaar_id' WHERE id='$product_id' AND seller_id='$seller_id'";
					if ($conn->query($sql) === TRUE) {
						echo "<div class='alert alert-success'>Artikel erfolgreich aktualisiert.</div>";
					} else {
						echo "<div class='alert alert-danger'>Fehler beim Aktualisieren des Artikels: " . $conn->error . "</div>";
					}
				}
			}

			// Handle product deletion
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
				$product_id = $conn->real_escape_string($_POST['product_id']);

				$sql = "DELETE FROM products WHERE id='$product_id' AND seller_id='$seller_id'";
				if ($conn->query($sql) === TRUE) {
					echo "<div class='alert alert-success'>Artikel erfolgreich gelöscht.</div>";
				} else {
					echo "<div class='alert alert-danger'>Fehler beim Löschen des Artikels: " . $conn->error . "</div>";
				}
			}

			// Handle delete all products
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_all_products'])) {
				$sql = "DELETE FROM products WHERE seller_id='$seller_id'";
				if ($conn->query($sql) === TRUE) {
					echo "<div class='alert alert-success'>Alle Artikel erfolgreich gelöscht.</div>";
				} else {
					echo "<div class='alert alert-danger'>Fehler beim Löschen aller Artikel: " . $conn->error . "</div>";
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
            $sql = "SELECT id FROM sellers WHERE id='$seller_id' AND email='$email'";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                $next_checkout_id = self::get_next_checkout_id($conn);
                $hash = hash('sha256', $email . $seller_id . SECRET);
                $verification_token = bin2hex(random_bytes(16));

                $sql = "UPDATE sellers SET verification_token='$verification_token', verified=0, consent='$consent', checkout_id='$next_checkout_id' WHERE id='$seller_id'";
                self::execute_sql_and_send_email($conn, $sql, $email, $seller_id, $hash, $verification_token, $mailtxt_reqexistingsellerid);
            } else {
                self::showSellerMessage("Ungültige Verkäufer-ID oder E-Mail.");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function process_new_seller($conn, $email, $family_name, $given_name, $phone, $street, $house_number, $zip, $city, $reserve, $consent, $mailtxt_reqnewsellerid) {
        try {
			self::debug_log("process new seller.\n");
			
            $seller_id = self::generate_unique_seller_id($conn);
            $next_checkout_id = self::get_next_checkout_id($conn);
            $hash = hash('sha256', $email . $seller_id . SECRET);
            $verification_token = bin2hex(random_bytes(16));
            $bazaar_id = 0;
				
            $sql = "INSERT INTO sellers (id, email, reserved, verification_token, family_name, given_name, phone, street, house_number, zip, city, hash, bazaar_id, consent, checkout_id) VALUES ('$seller_id', '$email', '$reserve', '$verification_token', '$family_name', '$given_name', '$phone', '$street', '$house_number', '$zip', '$city', '$hash', '$bazaar_id', '$consent', '$next_checkout_id')";
            self::execute_sql_and_send_email($conn, $sql, $email, $seller_id, $hash, $verification_token, $mailtxt_reqnewsellerid);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function execute_sql_and_send_email($conn, $sql, $email, $seller_id, $hash, $verification_token, $mailtxt) {
        global $seller_message, $BASE_URI, $given_name, $family_name;

        try {
			self::debug_log("execute sql and send mail.\n");
			
            if ($conn->query($sql) === TRUE) {
                $verification_link = BASE_URI . "?page=verify&token=$verification_token&hash=$hash";
				self::debug_log("verification link is: " . $verification_link ."\n");
                $subject = "Verifizierung Ihrer Verkäufer-ID: $seller_id";
                $message = str_replace(
                    ['{BASE_URI}', '{given_name}', '{family_name}', '{verification_link}', '{seller_id}', '{hash}'],
                    [$BASE_URI, $given_name, $family_name, $verification_link, $seller_id, $hash],
                    $mailtxt
                );
                $send_result = self::send_email($email, $subject, $message);

                if ($send_result === true) {
					$_SESSION['seller_message'] = "Eine E-Mail mit einem Bestätigungslink wurde an " . htmlspecialchars($_POST['email']) . " gesendet.";
                } else {
					$_SESSION['seller_message'] = "Fehler beim Senden der Bestätigungs-E-Mail: " . send_result . " gesendet.";
                }
            } else {
				$_SESSION['seller_message'] = "Fehler: " . $sql . "<br>" . $conn->error;
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

    public static function get_next_checkout_id($conn) {
        try {
			self::debug_log("function get next checkout id.\n");
			
            $sql = "SELECT MAX(checkout_id) AS max_checkout_id FROM sellers";
            $result = $conn->query($sql);
            return $result->fetch_assoc()['max_checkout_id'] + 1;
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

	// Function to calculate the check digit for EAN-13
	function calculateCheckDigit($barcode) {
		$sum = 0;
		for ($i = 0; $i < 12; $i++) {
			$digit = (int)$barcode[$i];
			$sum += ($i % 2 === 0) ? $digit : $digit * 3;
		}
		$mod = $sum % 10;
		return ($mod === 0) ? 0 : 10 - $mod;
	}

	// Function to get bazaar pricing rules
	function get_bazaar_pricing_rules($conn, $bazaar_id) {
		$sql = "SELECT min_price, price_stepping FROM bazaar WHERE id='$bazaar_id'";
		$result = $conn->query($sql);
		if ($result->num_rows > 0) {
			return $result->fetch_assoc();
		} else {
			return null;
		}
	}
?>