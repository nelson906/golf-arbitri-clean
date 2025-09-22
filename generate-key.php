<?php
$key = 'base64:' . base64_encode(random_bytes(32));
echo "Aggiungi questa riga al tuo .env:<br>";
echo "<strong>APP_KEY={$key}</strong>";
?>
