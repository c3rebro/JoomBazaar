<?php defined('_JEXEC') or die; 
?>

<div class="container">
    <h1 class="mt-5">Willkommen</h1>
    <p class="lead">Verkäufer können hier Verkäufernummern anfordern und Artikellisten erstellen.</p>

    <?php if ($bazaarOver): ?>
        <div class="alert alert-info">Der Bazaar ist geschlossen. Bitte kommen Sie wieder, wenn der nächste Bazaar stattfindet.</div>
    <?php elseif ($maxSellersReached): ?>
        <div class="alert alert-info">Wir entschuldigen uns, aber die maximale Anzahl an Verkäufern wurde erreicht. Die Registrierung für eine Verkäufer-ID wurde geschlossen.</div>
    <?php elseif (!$canRequestSellerId): ?>
        <div class="alert alert-info">Anfragen für neue Verkäufer-IDs sind derzeit noch nicht freigeschalten. Die nächste Nummernvergabe startet am: <?php echo htmlspecialchars($formattedDate); ?></div>
    <?php else: ?>
        <h2 class="mt-5">Verkäufer-ID anfordern</h2>
        <?php
        $session = JFactory::getSession();
        $messageData = $session->get('messageBoxBazaar', null);
        if ($messageData): 
            $messageText = htmlspecialchars($messageData['text']);
            $messageType = $messageData['type'];
        ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $messageText; ?>
            </div>
            <?php $session->clear('messageBoxBazaar'); // Clear the message after displaying it ?>
        <?php endif; ?>
        <form action="?page=default" method="post">
            <div class="row form-row">
                <div class="form-group col-md-6">
                    <label for="family_name" class="required">Nachname: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="family_name" name="family_name" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="given_name">Vorname:</label>
                    <input type="text" class="form-control" id="given_name" name="given_name">
                </div>
            </div>
            <div class="row form-row">
                <div class="form-group col-md-8">
                    <label for="street">Straße:</label>
                    <input type="text" class="form-control" id="street" name="street">
                </div>
                <div class="form-group col-md-4">
                    <label for="house_number">Hausnummer:</label>
                    <input type="text" class="form-control" id="house_number" name="house_number">
                </div>
            </div>
            <div class="row form-row">
                <div class="form-group col-md-4">
                    <label for="zip">PLZ:</label>
                    <input type="text" class="form-control" id="zip" name="zip">
                </div>
                <div class="form-group col-md-8">
                    <label for="city">Stadt:</label>
                    <input type="text" class="form-control" id="city" name="city">
                </div>
            </div>
            <div class="form-group">
                <label for="phone" class="required">Telefonnummer: <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="email" class="required">E-Mail: <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="reserve">Verkäufer-ID:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="use_existing_number" id="use_existing_number_yes" value="yes" onclick="toggleSellerIdField()">
                    <label class="form-check-label" for="use_existing_number_yes">
                        Ich habe bereits eine Nummer und möchte diese erneut verwenden
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="use_existing_number" id="use_existing_number_no" value="no" onclick="toggleSellerIdField()" checked>
                    <label class="form-check-label" for="use_existing_number_no">
                        Ich möchte eine neue Nummer erhalten
                    </label>
                </div>
            </div>
            <div class="form-group" id="seller_id_field" style="display: none;">
                <label for="seller_id">Verkäufer-ID:</label>
                <input type="text" class="form-control" id="seller_id" name="seller_id">
            </div>
            <div class="form-group">
                <label for="consent" class="required">Einwilligung zur Datenspeicherung: <span class="text-danger">*</span></label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="consent" id="consent_yes" value="yes" required>
                    <label class="form-check-label" for="consent_yes">
                        Ja: Ich möchte, dass meine persönlichen Daten bis zum nächsten Bazaar gespeichert werden. Ich kann meine Etiketten beim nächsten Mal wiederverwenden.
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="consent" id="consent_no" value="no" required>
                    <label class="form-check-label" for="consent_no">
                        Nein: Ich möchte nicht, dass meine persönlichen Daten gespeichert werden. Wenn ich das nächste Mal am Bazaar teilnehme, muss ich neue Etiketten drucken.
                    </label>
                </div>
            </div>
            <p>Weitere Hinweise zum Datenschutz und wie wir mit Ihren Daten umgehen, erhalten Sie in unserer <a href='https://www.basar-horrheim.de/index.php/datenschutzerklaerung'>Datenschutzerklärung</a>. Bei Nutzung unserer Dienste erklären Sie sich mit den dort aufgeführen Bedingungen einverstanden.</p>
            <button type="submit" class="btn btn-primary btn-block" name="request_seller_id">Verkäufer-ID anfordern</button>
        </form>
        <p class="mt-3 text-muted">* Diese Felder sind Pflichtfelder.</p>
    <?php endif; ?>
</div>

<script>
    function toggleSellerIdField() {
        const useExistingNumber = document.getElementById('use_existing_number_yes').checked;
        document.getElementById('seller_id_field').style.display = useExistingNumber ? 'block' : 'none';
    }
</script>