<?php
// Réception des données du formulaire de modification d'une dépense

// 1. DÉMARRAGE DE LA SESSION
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers le login
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
$expense_id  = (int) ($_POST['expense_id'] ?? 0);
$group_id    = (int) ($_POST['group_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$amount      = $_POST['amount'] ?? '';
$expense_date = trim($_POST['expense_date'] ?? '');
$user_id     = $_SESSION['user']['id'];

// 4. VALIDATIONS
if (empty($expense_id) || empty($group_id) || empty($description) || empty($amount)) {
    die('Veuillez remplir tous les champs obligatoires.');
}

if (!is_numeric($amount) || $amount <= 0) {
    die('Le montant doit être un nombre positif.');
}

// 5. VÉRIFICATION QUE LA DÉPENSE APPARTIENT BIEN À L'UTILISATEUR CONNECTÉ
// Sécurité : on empêche quelqu'un de modifier la dépense d'un autre
$stmt_check = $connexion->prepare("
    SELECT id FROM expenses
    WHERE id = :expense_id AND payer_id = :user_id AND account_group_id = :group_id
");
$stmt_check->execute([
    'expense_id' => $expense_id,
    'user_id'    => $user_id,
    'group_id'   => $group_id,
]);
if (!$stmt_check->fetch()) {
    die('Accès refusé : vous ne pouvez modifier que vos propres dépenses.');
}

// 6. MISE À JOUR EN BASE DE DONNÉES
$stmt = $connexion->prepare("
    UPDATE expenses
    SET description = :description,
        amount = :amount,
        expense_date = :expense_date
    WHERE id = :expense_id
");
$stmt->execute([
    'description'  => $description,
    'amount'       => $amount,
    'expense_date' => !empty($expense_date) ? $expense_date : null,
    'expense_id'   => $expense_id,
]);

// 7. REDIRECTION VERS LA LISTE DES GROUPES
header('Location: https://divvyo.hepl-e-business.be/api/formulaire_choix_de_groupe.php');
exit;
?>