<?php
session_start();

// Function to load profiles from JSON
function loadProfiles() {
    $profiles = json_decode(file_get_contents('profiles.json'), true);
    return $profiles ? $profiles : ['profiles' => []]; // Return empty array if decoding fails or file is empty
}

// Function to save profiles to JSON
function saveProfiles($profiles) {
    file_put_contents('profiles.json', json_encode($profiles, JSON_PRETTY_PRINT));
}

// Load profiles ONCE, at the very beginning
$profiles = loadProfiles();

// Initialize variables for editing and section display
$editProfile = null;
$sectionToShow = 'list';

// Handle editing
if (isset($_GET['edit'])) {
    $profileName = $_GET['edit'];
    foreach ($profiles['profiles'] as $profile) {
        if ($profile['name'] === $profileName) {
            $editProfile = $profile;
            $sectionToShow = 'create';
            break;
        }
    }
}

// Handle showing settings
if (isset($_GET['show_settings'])) {
    $sectionToShow = 'settings';
}

// Handle form submission (add/modify profile)
if (isset($_POST['submit_profile'])) {
    $profileName = $_POST['profile_name'];
    $m3uFile = $_POST['m3u_file'];
    $isNewProfile = true;

    foreach ($profiles['profiles'] as $index => $profile) {
        if ($profile['name'] === $profileName) {
            $profiles['profiles'][$index] = [
                'name' => $profileName,
                'm3uFile' => $m3uFile
            ];
            $isNewProfile = false;
            break;
        }
    }

    if ($isNewProfile) {
        $profiles['profiles'][] = [
            'name' => $profileName,
            'm3uFile' => $m3uFile
        ];
    }

    saveProfiles($profiles);
    header("Location: " . $_SERVER["REQUEST_URI"]); // Redirect to refresh the page
    exit();
}

// Handle profile deletion
if (isset($_GET['delete'])) {
    $profileName = $_GET['delete'];
    foreach ($profiles['profiles'] as $index => $profile) {
        if ($profile['name'] === $profileName) {
            unset($profiles['profiles'][$index]);
            saveProfiles($profiles);
            break;
        }
    }
    header("Location: manage_profiles.php");
    exit();
}

// Handle profile launching
if (isset($_GET['launch'])) {
    $profileName = $_GET['launch'];
    foreach ($profiles['profiles'] as $profile) {
        if ($profile['name'] === $profileName) {
            header("Location: index.php?profile=" . urlencode($profileName));
            exit();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Profils</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1><img src="app-icon.png" alt="App Icon"></h1>

    <div class="main-buttons">
        <button onclick="showSection('create')">Créer un Profil</button>
        <button onclick="showSection('list')">Afficher la Liste</button>
        <button onclick="showSection('settings')">Paramètres</button>
    </div>

    <div id="create-section" class="section" style="display: <?= $sectionToShow === 'create' ? 'block' : 'none' ?>">
        <h2><?= $editProfile ? 'Modifier un Profil' : 'Ajouter un Profil' ?></h2>
        <form method="POST">
            <label for="profile_name">Nom du Profil :</label>
            <input type="text" id="profile_name" name="profile_name" value="<?= $editProfile ? htmlspecialchars($editProfile['name']) : '' ?>" required>
            <label for="m3u_file">Fichier M3U :</label>
            <input type="text" id="m3u_file" name="m3u_file" value="<?= $editProfile ? htmlspecialchars($editProfile['m3uFile']) : '' ?>" required>
            <button type="submit" name="submit_profile"><?= $editProfile ? 'Modifier' : 'Ajouter' ?></button>
        </form>
    </div>

    <div id="list-section" class="section" style="display: <?= $sectionToShow === 'list' ? 'block' : 'none' ?>">
        <h2>Profils Existants</h2>
        <div class="profile-cards">
            <?php if (isset($profiles['profiles'])): ?>
                <?php foreach ($profiles['profiles'] as $profile): ?>
                    <div class="profile-card">
                        <div class="profile-info" onclick="launchProfile('<?= htmlspecialchars($profile['name']) ?>')">
                            <span><?= htmlspecialchars($profile['name']) ?></span>
                        </div>
                        <div class="profile-actions">
                            <button onclick="editProfile('<?= htmlspecialchars($profile['name']) ?>')">Modifier</button>
                            <button onclick="deleteProfile('<?= htmlspecialchars($profile['name']) ?>')">Supprimer</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="settings-section" class="section" style="display: <?= $sectionToShow === 'settings' ? 'block' : 'none' ?>">
        <h2>Paramètres</h2>
        <p>Configurer les paramètres de l'application ici.</p>
    </div>

    <script>
        function showSection(sectionId) {
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => section.style.display = 'none');
            document.getElementById(sectionId + '-section').style.display = 'block';
        }

        function launchProfile(profileName) {
            window.location.href = "?launch=" + encodeURIComponent(profileName);
        }

        function editProfile(profileName) {
            window.location.href = "?edit=" + encodeURIComponent(profileName);
        }

        function deleteProfile(profileName) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')) {
                window.location.href = "?delete=" + encodeURIComponent(profileName);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            showSection('<?= $sectionToShow ?>');
        });
    </script>
</body>
</html>