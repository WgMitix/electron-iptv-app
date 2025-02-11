<?php
header('Content-Type: application/json');
$channels = [];
$file = 'channels.m3u';
if (file_exists($file)) {
    $lines = file($file);
    foreach ($lines as $line) {
        if (strpos($line, '#EXTINF') !== false) {
            $title = trim(explode(',', $line)[1] ?? 'Chaîne inconnue');
            $channels[] = $title;
        }
    }
}
echo json_encode(["channels" => $channels]);
?>