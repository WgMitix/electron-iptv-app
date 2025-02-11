<?php
// Chemin du répertoire contenant les fichiers M3U
$m3uDirectory = '';

// Lister tous les fichiers M3U dans le répertoire
$m3uFiles = glob($m3uDirectory . '*.m3u');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélectionner un fichier M3U</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Sélectionner un fichier M3U</h1>
    <form action="index.php" method="GET">
        <label for="m3uFile">Choisissez un fichier M3U :</label>
        <select name="file" id="m3uFile" required>
            <?php foreach ($m3uFiles as $file): ?>
                <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Charger le fichier M3U</button>
    </form>
</body>
</html>
