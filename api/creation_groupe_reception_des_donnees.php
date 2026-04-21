<?php
// Réception des données du formulaire de création de groupe

// 1. DÉMARRAGE DE LA SESSION
// Nécessaire pour récupérer l'ID de l'utilisateur connecté
session_start();

// Vérifie que l'utilisateur est bien connecté avant de continuer
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// 2. CONNEXION À LA BASE DE DONNÉES
define('USER', 'vy44dy72oodv');
define('PASSWD', 'd3-d2d!4oo');
define('SERVER', 'localhost');
define('BASE', 'ebus2_projet04_viiy78');

try {
    $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
    $connexion = new PDO($dsn, USER, PASSWD);
} catch (PDOException $e) {
    die('Échec de la connexion à la base de données');
}

// 3. RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// trim() supprime les espaces inutiles en début/fin de chaîne
$group_name        = trim($_POST['group_name'] ?? '');
$group_description = trim($_POST['group_description'] ?? '');
$currency          = trim($_POST['currency'] ?? 'EUR');

// 4. VALIDATION
// Vérifie que les champs obligatoires sont remplis
if (empty($group_name) || empty($group_description)) {
    die('Veuillez remplir tous les champs obligatoires.');
}

// 5. INSERTION DU GROUPE dans account_groups
// created_at est géré automatiquement par la BD (valeur par défaut)
$stmt = $connexion->prepare("
    INSERT INTO account_groups (name, description, currency)
    VALUES (:name, :description, :currency)
");
$stmt->execute([
    'name'        => $group_name,
    'description' => $group_description,
    'currency'    => $currency,
]);

// Récupère l'ID du groupe qu'on vient de créer
// lastInsertId() retourne l'auto_increment du dernier INSERT
$group_id = $connexion->lastInsertId();

// 6. AJOUT DES MEMBRES dans group_users
// On récupère l'ID de l'utilisateur connecté depuis la session
$creator_id = $_SESSION['user']['id'];

// On ajoute d'abord le créateur du groupe comme membre
$stmt = $connexion->prepare("
    INSERT INTO group_users (account_group_id, user_id)
    VALUES (:group_id, :user_id)
");
$stmt->execute([
    'group_id' => $group_id,
    'user_id'  => $creator_id,
]);

// On ajoute ensuite les membres saisis par email (1 à 4)
// Pour chaque email rempli, on cherche l'utilisateur dans la table users
$members = [
    $_POST['member_1'] ?? '',
    $_POST['member_2'] ?? '',
    $_POST['member_3'] ?? '',
    $_POST['member_4'] ?? '',
];

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
        $stmt->execute([
            'group_id' => $group_id,
            'user_id'  => $member['id'],
        ]);
    }
}

// 7. REDIRECTION VERS L'ACCUEIL
// Une fois le groupe créé, on renvoie l'utilisateur à l'accueil
header('Location: https://divvyo.hepl-e-business.be/index.html');
exit;
?>
