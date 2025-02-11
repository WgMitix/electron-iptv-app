<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        h2{
            font-size: 500%;
        }
    </style>
</head>
<body>
<h1><img src="app-icon.png" alt="App Icon"></h1>
<h2>Error 404</h2>
<button id="quitButton">Quitter</button>

    <script>
        document.getElementById('quitButton').addEventListener('click', () => {
            // Envoyer un message au processus principal pour fermer la fenêtre
            window.electron.quitApp();
        });
    </script>
</body>
</html>