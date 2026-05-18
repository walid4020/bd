<?php
    // Réception des données du formulaire de modification d'une dépense

    // DÉMARRAGE DE LA SESSION
    session_start();

    // Si l'utilisateur n'est pas connecté, on le redirige vers le login
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
    } catch (PDOException $e) {
        die('Échec de la connexion à la base de données');
    }

    // RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
    $expense_id  = (int) ($_POST['expense_id'] ?? 0);
    $group_id    = (int) ($_POST['group_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount      = $_POST['amount'] ?? '';
    $expense_date = trim($_POST['expense_date'] ?? '');
    $user_id     = $_SESSION['user']['id'];

    // VALIDATIONS
    if (empty($expense_id) || empty($group_id) || empty($description) || empty($amount)) {
        die('Veuillez remplir tous les champs obligatoires.');
    }
    // message d'erreur 
    if (!is_numeric($amount) || $amount <= 0) {
        die('Le montant doit être un nombre positif.');
    }

    // ÉRIFICATION QUE LA DÉPENSE APPARTIENT BIEN À L'UTILISATEUR CONNECTÉ
    // Sécurité : on empêche quelqu'un de modifier la dépense d'un autre
    $stmt_check = $connexion->prepare("
        SELECT id FROM expenses
        WHERE id = :expense_id AND payer_id = :user_id AND account_group_id = :group_id
    ");
        /* requête SQL : Sélectionne l'id depuis la table expenses où l'id est égal à l'id de la dépense ET où 
        le payer_id est égal à l'id de l'utilisateur connecté ET où l'account_group_id est égal à l'id du groupe */ 
    $stmt_check->execute([
        'expense_id' => $expense_id,
        'user_id'    => $user_id,
        'group_id'   => $group_id,
    ]);
    // message d'erreur 
    if (!$stmt_check->fetch()) {
        die('Accès refusé : vous ne pouvez modifier que vos propres dépenses.');
    }

    // MISE À JOUR EN BASE DE DONNÉES
    $stmt = $connexion->prepare("
        UPDATE expenses
        SET description = :description,
            amount = :amount,
            expense_date = :expense_date
        WHERE id = :expense_id
    ");
    /* requête SQL : Met à jour la table expenses en modifiant la description, le amount et la expense_date avec les nouvelles
     valeurs fournies, uniquement pour la ligne dont l'id est égal à l'id de la dépense */ 
    $stmt->execute([
        'description'  => $description,
        'amount'       => $amount,
        'expense_date' => !empty($expense_date) ? $expense_date : null,
        'expense_id'   => $expense_id,
    ]);

    // REDIRECTION VERS LA LISTE DES GROUPES
    header('Location: https://divvyo.hepl-e-business.be/api/dashboard.php?group_id=' . $group_id);
    exit;
?>