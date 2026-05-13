<?php
// Suppression d'un groupe et de toutes ses dépenses associées

// 1. DÉMARRAGE DE LA SESSION
session_start();

// Si l'utilisateur n'est pas connecté, on le renvoie au login
if (!isset($_SESSION['user'])) {
    header('Location: ../index.html');
    exit;
}

// 2. VÉRIFICATION QUE LE GROUP_ID A BIEN ÉTÉ ENVOYÉ
if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
    header('Location: dashboard.php');
    exit;
}

// 3. CONNEXION À LA BASE DE DONNÉES
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

$group_id = (int) $_POST['group_id'];
$user_id  = $_SESSION['user']['id'];

// 4. VÉRIFICATION QUE L'UTILISATEUR EST BIEN MEMBRE DE CE GROUPE
// Sécurité : on ne peut pas supprimer un groupe dont on n'est pas membre
$stmt_check = $connexion->prepare("
    SELECT account_group_id FROM group_users
    WHERE account_group_id = :group_id AND user_id = :user_id
");
$stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
if (!$stmt_check->fetch()) {
    header('Location: dashboard.php');
    exit;
}

// 5. SUPPRESSION DANS LE BON ORDRE (respecter les contraintes de clés étrangères)

// On supprime d'abord les dépenses du groupe
$stmt_depenses = $connexion->prepare("
    DELETE FROM expenses WHERE account_group_id = :group_id
");
$stmt_depenses->execute(['group_id' => $group_id]);

// On supprime ensuite les membres du groupe
$stmt_membres = $connexion->prepare("
    DELETE FROM group_users WHERE account_group_id = :group_id
");
$stmt_membres->execute(['group_id' => $group_id]);

// On supprime enfin le groupe lui-même
$stmt_groupe = $connexion->prepare("
    DELETE FROM account_groups WHERE id = :group_id
");
$stmt_groupe->execute(['group_id' => $group_id]);

// 6. REDIRECTION VERS LE DASHBOARD après suppression
header('Location: dashboard.php');
exit;
?>