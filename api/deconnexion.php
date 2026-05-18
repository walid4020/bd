<?php
    session_start();

    // Supprime toutes les données de la session en cours
    session_unset();

    // Détruit la session côté serveur
    session_destroy();

    // Redirige vers la page de connexion
    header('Location: ../index.html');
    exit;
?>