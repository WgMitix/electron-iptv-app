<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['channel_name'])) {
    $channelName = $_POST['channel_name'];
    $file = 'favorites.txt';

    // Lire le fichier et retirer le nom de la chaîne
    $favorites = file($file, FILE_IGNORE_NEW_LINES);
    $favorites = array_filter($favorites, function($favorite) use ($channelName) {
        return $favorite !== $channelName;
    });
    file_put_contents($file, implode(PHP_EOL, $favorites) . PHP_EOL);
}
?>
