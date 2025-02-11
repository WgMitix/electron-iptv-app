<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['channel_name'])) {
    $channelName = $_POST['channel_name'];
    $file = 'favorites.txt';

    // Ajouter le nom de la chaîne dans le fichier si ce n'est pas déjà présent
    $favorites = file($file, FILE_IGNORE_NEW_LINES);
    if (!in_array($channelName, $favorites)) {
        file_put_contents($file, $channelName . PHP_EOL, FILE_APPEND);
    }
}
?>
