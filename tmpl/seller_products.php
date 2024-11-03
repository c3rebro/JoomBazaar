<?php defined('_JEXEC') or die; 

// Include the helper file
require_once __DIR__ . '/../helper.php'; // Use relative path to include helper.php

session_start();

if (!isset($_GET['seller_id']) || !isset($_GET['hash'])) {
    echo "Kein Verkäufer-ID oder Hash angegeben.";
    return;
}

if (isset($_SESSION['messageBox'])) {
    $messageData = $_SESSION['messageBox'];
    $messageText = htmlspecialchars($messageData['text']);
    $messageType = $messageData['type']; // Use this to set the alert class

    // Clear the message after displaying it
    unset($_SESSION['messageBox']);
}

$seller_id = $_GET['seller_id'];
$hash = $_GET['hash'];

$conn = modBazaarHelper::get_db_connection();
$sql = "SELECT * FROM sellers WHERE id='$seller_id' AND hash='$hash' AND verified=1";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "
    <div class='container'>
        <div class='alert alert-warning mt-5'>
            <h4 class='alert-heading'>Ungültige oder nicht verifizierte Verkäufer-ID oder Hash.</h4>
            <p>Bitte überprüfen Sie Ihre Verkäufer-ID und versuchen Sie es erneut.</p>
            <hr>
            <p class='mb-0'>Haben Sie auf den Verifizierungslink in der E-Mail geklickt?</p>
        </div>
    </div>";
    return;
}

// Fetch all products for the seller
$sql = "SELECT * FROM products WHERE seller_id='$seller_id'";
$products_result = $conn->query($sql);

$conn->close();
?>

<div class="container">
	<h1 class="mt-5">Artikel erstellen - Verkäufernummer: <?php echo $seller_id; ?></h1>
	<div class="action-buttons">
	    <?php if ($messageText): ?>
            <?php echo "<div class='alert alert-$messageType'>$messageText</div>"; ?>
		<?php endif; ?>
		<form action="?page=seller_products&seller_id=<?php echo $seller_id; ?>&hash=<?php echo $hash; ?>" method="post" class="w-100">
			<div class="row form-row mb-3">
				<div class="form-group col-md-4">
					<label for="name">Artikelname:</label>
					<input type="text" class="form-control" id="name" name="name" required>
				</div>
				<div class="form-group col-md-4">
					<label for="size">Größe:</label>
					<input type="text" class="form-control" id="size" name="size">
				</div>
				<div class="form-group col-md-4">
					<label for="price">Preis:</label>
					<input type="number" class="form-control" id="price" name="price" step="0.01" required>
				</div>
			</div>
			<button type="submit" class="btn btn-primary w-100" name="create_product">Artikel erstellen</button>
		</form>
	</div>
	<a href="?page=print_barcodes.php&seller_id=<?php echo $seller_id; ?>&hash=<?php echo $hash; ?>" class="btn btn-secondary mt-3 w-100">Etiketten drucken</a>

	<h2 class="mt-5">Erstellte Artikel</h2>
	<div class="table-responsive">
		<table class="table table-bordered mt-3">
			<thead>
				<tr>
					<th>Artikelname</th>
					<th>Größe</th>
					<th>Preis</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ($products_result->num_rows > 0) {
					while ($row = $products_result->fetch_assoc()) {
						$formatted_price = number_format($row['price'], 2, ',', '.') . ' €';
						echo "<tr>
								<td>{$row['name']}</td>
								<td>{$row['size']}</td>
								<td>{$formatted_price}</td>
								<td>
									<button class='btn btn-warning btn-sm' onclick='editProduct({$row['id']}, \"{$row['name']}\", \"{$row['size']}\", {$row['price']})'>Bearbeiten</button>
									<form action='?page=seller_products&seller_id=$seller_id&hash=$hash' method='post' style='display:inline-block'>
										<input type='hidden' name='product_id' value='{$row['id']}'>
										<button type='submit' name='delete_product' class='btn btn-danger btn-sm'>Löschen</button>
									</form>
								</td>
							  </tr>";
					}
				} else {
					echo "<tr><td colspan='4'>Keine Artikel gefunden.</td></tr>";
				}
				?>
			</tbody>
		</table>
		<form action="?page=seller_products&seller_id=<?php echo $seller_id; ?>&hash=<?php echo $hash; ?>" method="post">
			<button type="submit" class="btn btn-danger mb-3" name="delete_all_products">Alle Artikel löschen</button>
		</form>
	</div>

	<!-- Edit Product Modal -->
	<div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="editProductModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="?page=seller_products&seller_id=<?php echo $seller_id; ?>&hash=<?php echo $hash; ?>" method="post">
					<div class="modal-header">
						<h5 class="modal-title" id="editProductModalLabel">Artikel bearbeiten</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<input type="hidden" name="product_id" id="editProductId">
						<div class="form-group">
							<label for="editProductName">Artikelname:</label>
							<input type="text" class="form-control" id="editProductName" name="name" required>
						</div>
						<div class="form-group">
							<label for="editProductSize">Größe:</label>
							<input type="text" class="form-control" id="editProductSize" name="size">
						</div>
						<div class="form-group">
							<label for="editProductPrice">Preis:</label>
							<input type="number" class="form-control" id="editProductPrice" name="price" step="0.01" required>
						</div>
						<div id="editProductAlert" class="alert alert-danger d-none"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
						<button type="submit" class="btn btn-primary" name="update_product">Änderungen speichern</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Price Validation Modal -->
	<div class="modal fade" id="priceValidationModal" tabindex="-1" role="dialog" aria-labelledby="priceValidationModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="priceValidationModalLabel">Preisvalidierung</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<!-- The message will be set dynamically via JavaScript -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- No Bazaar Modal -->
	<div class="modal fade" id="noBazaarModal" tabindex="-1" role="dialog" aria-labelledby="noBazaarModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document" style="margin-top: <?php echo MODAL_MARGIN; ?>;">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="noBazaarModalLabel">Fehlende Basar-ID</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p>Es wurde kein (neuer) Basar gefunden, dem dieser Artikel zugeordnet werden kann. Es muss erst ein neuer Basar aktiviert werden, bevor der Artikel editiert werden kann. Bitte wende Dich an das Team wenn das ein Fehler sein sollte.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
				</div>
			</div>
		</div>
	</div>

</div>

<script>
jQuery(document).ready(function($) {
    window.editProduct = function(id, name, size, price) {
        $('#editProductId').val(id);
        $('#editProductName').val(name);
        $('#editProductSize').val(size);
        $('#editProductPrice').val(price.toFixed(2));
        $('#editProductModal').modal('show');
    };

    $('#editProductModal .btn-secondary').on('click', function() {
        $('#editProductModal').modal('hide');
    });

    $('#noBazaarModal .btn-secondary').on('click', function() {
        $('#noBazaarModal').modal('hide');
    });
});
</script>