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

// Fonction pour parser un fichier M3U
function parseM3U($filePath)
{
    $channels = [];
    if (file_exists($filePath)) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentChannel = [];
        $id = 1;  // Initialiser un ID pour chaque chaîne
        foreach ($lines as $line) {
            if (strpos($line, '#EXTINF:') === 0) {
                preg_match('/tvg-name="(.*?)"/', $line, $nameMatch);
                preg_match('/tvg-logo="(.*?)"/', $line, $logoMatch);
                preg_match('/group-title="(.*?)"/', $line, $groupMatch);
                $currentChannel = [
                    'id' => $id++,  // Assigner un ID unique
                    'tvg_name' => $nameMatch[1] ?? 'Sans nom',
                    'tvg_logo' => $logoMatch[1] ?? '',
                    'group_title' => $groupMatch[1] ?? 'Autres',
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

// Récupérer les catégories uniques (group_title)
$channels = parseM3U($filePath);

// Fonction pour extraire la qualité (HD, FHD, SD) du nom de la chaîne
function getQuality($channelName)
{
    if (stripos($channelName, 'FHD') !== false) {
        return 'FHD';
    } elseif (stripos($channelName, 'HEVC') !== false) {
        return 'HEVC';
    } elseif (stripos($channelName, 'HD') !== false) {
        return 'HD';
    } elseif (stripos($channelName, 'SD') !== false) {
        return 'SD';
    }

    return 'Autre';
}

// Ajouter la qualité dans chaque chaîne
foreach ($channels as $index => $channel) {
    $channels[$index]['quality'] = getQuality($channel['tvg_name']);
}

// Filtrer les chaînes par qualité si un filtre est appliqué
$selectedQuality = isset($_GET['quality']) ? $_GET['quality'] : 'all';
if ($selectedQuality !== 'all') {
    $channels = array_filter($channels, function ($channel) use ($selectedQuality) {
        return $channel['quality'] === $selectedQuality;
    });
}

// Récupérer les catégories uniques (group_title)
$categories = array_unique(array_column($channels, 'group_title'));
sort($categories);

// Lire les favoris depuis le fichier
$favorites = file('favorites.txt', FILE_IGNORE_NEW_LINES);

// Ajouter une catégorie "Favoris" si des chaînes sont favorites
$favoriteChannels = [];
foreach ($channels as $channel) {
    if (in_array($channel['tvg_name'], $favorites)) {
        $favoriteChannels[] = $channel;
    }
}
$categories[] = 'Favoris'; // Ajouter la catégorie Favoris

// Définir la catégorie sélectionnée, par défaut à "Favoris" si aucune autre catégorie n'est spécifiée
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'Favoris';

// Filtrer les chaînes par catégorie
if ($selectedCategory !== 'all') {
    if ($selectedCategory === 'Favoris') {
        $channels = $favoriteChannels;
    } else {
        $channels = array_filter($channels, function ($channel) use ($selectedCategory) {
            return $channel['group_title'] === $selectedCategory;
        });
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des chaînes TV FHD</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
<h1><img src="app-icon.png" alt="App Icon"></h1>

    <!-- Burger Menu -->
    <div class="burger-menu" onclick="toggleMenu()">&#9776;</div>
    <div id="myDropdown" class="dropdown-content">
        <a href="manage_profiles.php?show_settings=1">Paramètres</a>
        <a href="logout.php">Se déconnecter</a>
    </div>

    <!-- Écran de chargement -->
    <div id="loading">
        <div class="spinner"></div>
        <p id="loading-text">Chargement de <?= htmlspecialchars($selectedCategory) ?>...</p>
    </div>

    <!-- Barre de recherche -->
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Rechercher une chaîne..." onkeyup="searchChannels()">
        <div class="quality-filter">
        <select id="qualitySelect" onchange="filterQuality()">
            <option value="all" <?= $selectedQuality === 'all' ? 'selected' : '' ?>>Toutes les qualités</option>
            <option value="FHD" <?= $selectedQuality === 'FHD' ? 'selected' : '' ?>>FHD</option>
            <option value="HEVC" <?= $selectedQuality === 'HEVC' ? 'selected' : '' ?>>HEVC</option>
            <option value="HD" <?= $selectedQuality === 'HD' ? 'selected' : '' ?>>HD</option>
            <option value="SD" <?= $selectedQuality === 'SD' ? 'selected' : '' ?>>SD</option>
        </select>
    </div>
    </div>


    <!-- Layout à deux colonnes -->
    <div class="layout">
        <!-- Catégories à gauche -->
        <div class="categories-container">
            <a href="?category=all&profile=<?= urlencode($selectedProfile) ?>"><button>Toutes les chaînes</button></a>
            <?php foreach ($categories as $category): ?>
                <a href="?category=<?= urlencode($category) ?>&profile=<?= urlencode($selectedProfile) ?>"><button><?= htmlspecialchars($category) ?></button></a>
            <?php endforeach; ?>
        </div>

        <!-- Liste des chaînes à droite -->
        <div class="cards-container" id="cardsContainer">
            <?php
            if ($selectedCategory === 'Favoris') {
                // Afficher uniquement les chaînes favorites
                if ($favoriteChannels) {
                    foreach ($favoriteChannels as $channel) { ?>
                        <div class="card" data-category="<?= htmlspecialchars($channel['group_title']) ?>" data-quality="<?= htmlspecialchars($channel['quality']) ?>" data-id="<?= $channel['id'] ?>">
                            <span class="favorite-star" onclick="toggleFavorite(<?= $channel['id'] ?>)">★</span>
                            <img src="<?= htmlspecialchars($channel['tvg_logo']) ?>" alt="Logo de <?= htmlspecialchars($channel['tvg_name']) ?>">
                            <div class="card-body">
                                <h3 class="channel-name"><?= htmlspecialchars($channel['tvg_name']) ?></h3>
                                <a href="play_channel.php?id=<?= $channel['id'] ?>&quality=<?= $channel['quality'] ?>&profile=<?= urlencode($selectedProfile) ?>">Afficher la chaîne</a>
                            </div>
                        </div>
                    <?php
                    }
                } else {
                    echo '<p>Aucune chaîne en favori.</p>';
                }
            } else {
                // Afficher toutes les chaînes
                if ($channels) {
                    foreach ($channels as $index => $channel) { ?>
                        <div class="card" data-category="<?= htmlspecialchars($channel['group_title']) ?>" data-quality="<?= htmlspecialchars($channel['quality']) ?>" data-id="<?= $index ?>">
                            <span class="favorite-star" onclick="toggleFavorite(<?= $index ?>)">★</span>
                            <img src="<?= htmlspecialchars($channel['tvg_logo']) ?>" alt="Logo de <?= htmlspecialchars($channel['tvg_name']) ?>">
                            <div class="card-body">
                                <h3 class="channel-name"><?= htmlspecialchars($channel['tvg_name']) ?></h3>
                                <a href="play_channel.php?id=<?= $channel['id'] ?>&quality=<?= $channel['quality'] ?>&profile=<?= urlencode($selectedProfile) ?>">Afficher la chaîne</a>
                            </div>
                        </div>
                    <?php
                    }
                } else {
                    echo '<p>Aucune chaîne trouvée dans le fichier M3U.</p>';
                }
            }
            ?>
        </div>
    </div>

    <button id="quitButton">Quitter</button>

    <script>
        document.getElementById('quitButton').addEventListener('click', () => {
            // Envoyer un message au processus principal pour fermer la fenêtre
            window.electron.quitApp();
        });

        // Fonction pour afficher/masquer le menu déroulant
        function toggleMenu() {
            document.getElementById("myDropdown").classList.toggle("show");
        }

        // Masquer le menu déroulant si l'utilisateur clique en dehors
        window.onclick = function(event) {
            if (!event.target.matches('.burger-menu')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Filtrer les cartes par catégorie
        function filterCategory(category) {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                if (category === 'all' || cardCategory === category || (category === 'Favoris' && card.classList.contains('favorited'))) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Mettre à jour le texte de chargement avec la catégorie sélectionnée
            const category = new URLSearchParams(window.location.search).get("category") || "Favoris";
            document.getElementById("loading-text").textContent = `Chargement de ${category}...`;

            // Masquer l'écran de chargement une fois la page complètement chargée
            setTimeout(() => {
                document.getElementById("loading").style.display = "none";
            }); // Simule un petit délai de chargement
        });

        // Lors du changement de qualité, enregistrer la sélection dans le localStorage
        function filterQuality() {
            const selectedQuality = document.getElementById('qualitySelect').value;
            localStorage.setItem('selectedQuality', selectedQuality); // Sauvegarder le choix
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const cardQuality = card.getAttribute('data-quality');
                if (selectedQuality === 'all' || cardQuality === selectedQuality) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Fonction pour filtrer les chaînes en fonction de la recherche et de la qualité
        function filterChannels() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const selectedQuality = document.getElementById('qualitySelect').value;
            const cards = document.querySelectorAll('.card');

            cards.forEach(card => {
                const channelName = card.querySelector('.channel-name').textContent.toLowerCase();
                const cardQuality = card.getAttribute('data-quality');

                // Afficher la carte si elle correspond à la recherche et au filtre de qualité
                if (
                    (searchInput === '' || channelName.includes(searchInput)) &&
                    (selectedQuality === 'all' || cardQuality === selectedQuality)
                ) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Écouter les événements pour appliquer les filtres en temps réel
        document.getElementById('searchInput').addEventListener('keyup', filterChannels);
        document.getElementById('qualitySelect').addEventListener('change', filterChannels);

        // Charger la qualité sélectionnée depuis le localStorage
        window.onload = function() {
            const savedFavorites = <?= json_encode($favorites); ?>;
            const stars = document.querySelectorAll('.favorite-star');
            stars.forEach(star => {
                const channelName = star.closest('.card').querySelector('.channel-name').textContent;
                if (savedFavorites.includes(channelName)) {
                    star.classList.add('favorited');
                }
            });
        };

        // Réinitialiser le filtre qualité lors du clic sur le bouton "Quitter"
        document.getElementById('quitButton').addEventListener('click', () => {
            localStorage.removeItem('selectedQuality'); // Supprimer le choix du cache
            window.location.reload(); // Recharger la page pour afficher toutes les chaînes
        });

        // Fonction de recherche
        function searchChannels() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const channelName = card.querySelector('.channel-name').textContent.toLowerCase();
                if (channelName.includes(searchInput)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Fonction pour ajouter/retirer des favoris
        function toggleFavorite(channelId) {
            const star = document.querySelector(`.card[data-id="${channelId}"] .favorite-star`);
            const isFavorited = star.classList.contains('favorited');
            const channelName = document.querySelector(`.card[data-id="${channelId}"] .channel-name`).textContent;

            if (isFavorited) {
                star.classList.remove('favorited');
                removeFavoriteFromFile(channelName); // Retirer des favoris
            } else {
                star.classList.add('favorited');
                addFavoriteToFile(channelName); // Ajouter aux favoris
            }
        }

        // Ajouter un favori dans le fichier texte
        function addFavoriteToFile(channelName) {
            fetch('add_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `channel_name=${encodeURIComponent(channelName)}`
            });
        }

        // Retirer un favori du fichier texte
        function removeFavoriteFromFile(channelName) {
            fetch('remove_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `channel_name=${encodeURIComponent(channelName)}`
            });
        }
    </script>
</body>

</html>
