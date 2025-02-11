<?php
session_start(); // Démarrer une session pour gérer les favoris

// Charger les profils depuis le fichier JSON
$profilesJson = file_get_contents('profiles.json');
$profiles = json_decode($profilesJson, true);

// Définir le profil sélectionné, par défaut au premier profil si aucun paramètre n'est passé
$selectedProfile = isset($_GET['profile']) ? $_GET['profile'] : $profiles['profiles'][0]['name'];
$selectedFile = '';

// Trouver le fichier M3U associé au profil sélectionné
foreach ($profiles['profiles'] as $profile) {
    if ($profile['name'] === $selectedProfile) {
        $selectedFile = $profile['m3uFile'];
        break;
    }
}

$filePath = '' . basename($selectedFile);

// Ajout d'une fonction pour récupérer les sous-titres
function parseM3U($filePath)
{
    $channels = [];
    if (file_exists($filePath)) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentChannel = [];
        $id = 1;
        foreach ($lines as $line) {
            if (strpos($line, '#EXTINF:') === 0) {
                preg_match('/tvg-name="(.*?)"/', $line, $nameMatch);
                preg_match('/tvg-logo="(.*?)"/', $line, $logoMatch);
                preg_match('/group-title="(.*?)"/', $line, $groupMatch);
                preg_match('/subtitles="(.*?)"/', $line, $subtitlesMatch); // Gérer les sous-titres ici
                $currentChannel = [
                    'id' => $id++,
                    'tvg_name' => $nameMatch[1] ?? 'Sans nom',
                    'tvg_logo' => $logoMatch[1] ?? '',
                    'group_title' => $groupMatch[1] ?? 'Autres',
                    'subtitles' => $subtitlesMatch[1] ?? [] // Ajouter un tableau pour les sous-titres
                ];
            } elseif (filter_var($line, FILTER_VALIDATE_URL)) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $currentChannel = [];
            }
        }
    }
    return $channels;
}

// Récupérer les chaînes depuis le fichier M3U
$channels = parseM3U($filePath);

// Vérifier si un ID de chaîne a été passé
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $channel = null;

    // Rechercher la chaîne correspondante à l'ID
    foreach ($channels as $chan) {
        if ($chan['id'] === $id) {
            $channel = $chan;
            break;
        }
    }

    // Vérifier si la chaîne a été trouvée
    if (!$channel) {
        die("Chaîne introuvable.");
    }
} else {
    die("Aucun ID de chaîne spécifié.");
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($channel['tvg_name']) ?> - Lecteur</title>
    <link rel="stylesheet" href="https://releases.flowplayer.org/7.2.7/skin/skin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.6.4/plyr.css" integrity="sha512-bMLolM8mWTXYQSC2gQOLyDdkmodSAbbRFbDoISUCRS7mFJrP3fBHJo3YR8+2Yy9n7+iVGawVpCe6KVd/E5+TNA==" crossorigin="anonymous" />
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Votre CSS ici */
        #videoPlayer {
            width: 100%;
            height: 100vh;
            background: black;
            position: relative;
        }

        .plyr,
        .flowplayer {
            width: 1920px;
            height: 1080px;
            max-height: 100vh;
            margin: auto;
            display: block;
            background-color: black;
            object-fit: cover;
        }

        #backButton,
        #fullscreenButton {
            position: absolute;
            z-index: 10;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        #backButton {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background-color: red;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        #fullscreenButton {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        html,
        body {
            overflow: hidden;
            margin: 0;
            padding: 0;
            height: 100%;
        }
    </style>
</head>

<body>
    <div id="videoPlayer">
        <button id="backButton" onclick="window.history.back()">Retour</button>

        <?php if (strpos($channel['url'], '.m3u8') !== false): ?>
            <div class="flowplayer" data-ratio="16:9">
                <video>
                    <source type="application/x-mpegurl" src="<?= htmlspecialchars($channel['url']) ?>">
                </video>
            </div>
        <?php else: ?>
            <video id="player" playsinline controls>
                <source src="<?= htmlspecialchars($channel['url']) ?>" type="video/mp4">
                <?php if (!empty($channel['subtitles'])): ?>
                    <?php
                    $subtitlesArray = explode(',', $channel['subtitles']);
                    foreach ($subtitlesArray as $subtitle): ?>
                        <track kind="subtitles" label="Sous-titres" src="<?= htmlspecialchars($subtitle) ?>" srclang="fr">
                    <?php endforeach; ?>
                <?php endif; ?>
                Votre navigateur ne supporte pas la lecture vidéo.
            </video>
        <?php endif; ?>

        <button id="fullscreenButton">Plein écran</button>
    </div>

    <!-- Import Flowplayer et HLS.js -->
    <script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
    <script src="https://releases.flowplayer.org/7.2.7/flowplayer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
        let retryCount = 0;
        const maxRetries = 5;
        let lastPlaybackTime = 0;

        function initPlayer() {
            const isHLS = <?= json_encode(strpos($channel['url'], '.m3u8') !== false) ?>;
            const playerElement = document.getElementById('player');

            if (isHLS) {
                const flowPlayerInstance = flowplayer(".flowplayer", {
                    clip: {
                        autoplay: true,
                        sources: [{
                            type: "application/x-mpegurl",
                            src: "<?= htmlspecialchars($channel['url']) ?>"
                        }]
                    }
                });

                // Vérifier l'état du lecteur Flowplayer
                flowPlayerInstance.on("error", function(e, api) {
                    retryCount++;
                    if (retryCount >= maxRetries) {
                        window.location.href = "error.php"; // Rediriger vers une page d'erreur
                        return;
                    }
                    console.warn("Relancement du lecteur...");
                    initPlayer();
                });
            } else if (playerElement) {
                const plyrInstance = new Plyr(playerElement, {
                    captions: {
                        active: true,
                        update: true
                    },
                    controls: [
                        'play-large', 'play', 'progress', 'current-time', 'duration',
                        'mute', 'volume', 'captions', 'settings', 'fullscreen'
                    ],
                    settings: ['captions', 'quality'],
                    quality: {
                        default: 1080,
                        options: [1080, 720, 480]
                    },
                });

                // Passer en plein écran
                document.getElementById("fullscreenButton").addEventListener("click", function() {
                    playerElement.requestFullscreen().catch(err => {
                        console.warn("Impossible de passer en plein écran:", err);
                    });
                });
            }

            // Ajouter un événement pour passer en plein écran
            const fullscreenButton = document.getElementById("fullscreenButton");
            fullscreenButton.addEventListener("click", function() {
                const playerElement = document.querySelector(".flowplayer");
                if (playerElement.requestFullscreen) {
                    playerElement.requestFullscreen();
                } else if (playerElement.mozRequestFullScreen) { // Firefox
                    playerElement.mozRequestFullScreen();
                } else if (playerElement.webkitRequestFullscreen) { // Chrome et Safari
                    playerElement.webkitRequestFullscreen();
                } else if (playerElement.msRequestFullscreen) { // Internet Explorer
                    playerElement.msRequestFullscreen();
                }
            });
        }


        function checkPlayerStatus() {
            const playerElement = document.getElementById('player');
            const flowPlayerElement = document.querySelector(".flowplayer video");

            if (playerElement) {
                if (playerElement.currentTime === lastPlaybackTime) {
                    retryCount++;
                    if (retryCount >= maxRetries) {
                        window.location.href = "error.php"; // Rediriger vers une page d'erreur
                        return;
                    }
                    console.warn("Relancement du lecteur...");
                    initPlayer();
                } else {
                    retryCount = 0; // Réinitialiser si la lecture fonctionne
                }
                lastPlaybackTime = playerElement.currentTime;
            } else if (flowPlayerElement) {
                if (flowPlayerElement.currentTime === lastPlaybackTime) {
                    retryCount++;
                    if (retryCount >= maxRetries) {
                        window.location.href = "error.php";
                        return;
                    }
                    console.warn("Relancement du lecteur Flowplayer...");
                    initPlayer();
                } else {
                    retryCount = 0;
                }
                lastPlaybackTime = flowPlayerElement.currentTime;
            }
        }

        // Vérifier toutes les 5 secondes si la vidéo est bloquée
        document.addEventListener('DOMContentLoaded', function() {
            initPlayer();
            setInterval(checkPlayerStatus, 5000);
        });
    </script>
</body>

</html>