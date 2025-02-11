<?php
session_start();
session_destroy(); // Détruit toutes les données enregistrées dans une session
header("Location: manage_profiles.php");
exit();
?>
