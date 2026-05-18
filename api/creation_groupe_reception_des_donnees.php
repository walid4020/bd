<?php
// Réception des données du formulaire de création de groupe

// ÉMARRAGE DE LA SESSION
// Nécessaire pour récupérer l'ID de l'utilisateur connecté
session_start();

// Vérifie que l'utilisateur est bien connecté avant de continuer
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// CONNEXION À LA BASE DE DONNÉES
define('USER', 'vy44dy72oodv');
define('PASSWD', 'd3-d2d!4oo');
define('SERVER', 'localhost');
define('BASE', 'ebus2_projet04_viiy78');

try {
    $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
    $connexion = new PDO($dsn, USER, PASSWD);
    // gestion de l'erreur 
} catch (PDOException $e) {
    die('Échec de la connexion à la base de données');
}

// RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// trim() supprime les espaces inutiles en début/fin de chaîne
$group_name        = trim($_POST['group_name'] ?? '');
$group_description = trim($_POST['group_description'] ?? '');
$currency          = trim($_POST['currency'] ?? 'EUR');

// VALIDATION
// Vérifie que les champs obligatoires sont remplis
if (empty($group_name) || empty($group_description)) {
    die('Veuillez remplir tous les champs obligatoires.');
}

// INSERTION DU GROUPE dans account_groups
// created_at est géré automatiquement par la BD (valeur par défaut)
$stmt = $connexion->prepare("
    INSERT INTO account_groups (name, description, currency)
    VALUES (:name, :description, :currency)
");
/* requête SQL : insère une nouvelle ligne dans la table account_groups en remplissant les colonnes name, 
description et currency avec les valeurs fournies */ 
$stmt->execute([
    'name'        => $group_name,
    'description' => $group_description,
    'currency'    => $currency,
]);

// Récupère l'ID du groupe qu'on vient de créer
    /* Après un INSERT, lastInsertId() retourne l'id généré automatiquement par la BD pour la ligne qu'on vient d'insérer.
     On en a besoin pour lier les membres à ce groupe */ 
$group_id = $connexion->lastInsertId();

// AJOUT DES MEMBRES dans group_users
// On récupère l'ID de l'utilisateur connecté depuis la session
$creator_id = $_SESSION['user']['id'];

// On ajoute d'abord le créateur du groupe comme membre
$stmt = $connexion->prepare("
    INSERT INTO group_users (account_group_id, user_id)
    VALUES (:group_id, :user_id)
");
    /* requête SQL :insère une nouvelle ligne dans la table group_users en remplissant les colonnes account_group_id et 
    user_id avec les valeurs fournies */ 
$stmt->execute([
    'group_id' => $group_id,
    'user_id'  => $creator_id,
]);

// On ajoute ensuite les membres saisis par email 
// Pour chaque email rempli, on cherche l'utilisateur dans la table users
// On récupère le tableau de membres envoyé depuis le formulaire
$members = $_POST['members'] ?? [];

foreach ($members as $email) {
    $email = trim($email);

    // On ignore les champs vides
    if (empty($email)) continue;

    // On cherche l'utilisateur par son email dans la table users
    $stmt = $connexion->prepare("SELECT id FROM users WHERE email_address = :email");
    $stmt->execute(['email' => $email]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si l'utilisateur existe et n'est pas déjà dans le groupe, on l'ajoute
    if ($member && $member['id'] !== $creator_id) {
        $stmt = $connexion->prepare("
            INSERT INTO group_users (account_group_id, user_id)
            VALUES (:group_id, :user_id)
        ");
        // requête SQL : insère dans la table group_users les colonnes account_group_id et user_id les valeurs group_id et user_id
        $stmt->execute([
            'group_id' => $group_id,
            'user_id'  => $member['id'],
        ]);
    }
}

// REDIRECTION VERS L'ACCUEIL
// Une fois le groupe créé, on renvoie l'utilisateur à l'accueil
header('Location: https://divvyo.hepl-e-business.be/index.html');
exit;
?>
