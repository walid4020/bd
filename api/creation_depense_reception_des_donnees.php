<?php
    // Document PHP pour réceptionner les données du formulaire de login

    // DÉMARRAGE DE LA SESSION
    // Nécessaire pour vérifier que l'utilisateur est connecté
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
        // gestion de l'erreur
    } catch (PDOException $e) {
        die('Échec de la connexion à la base de données');
    }

    // RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
    $group_id    = (int) ($_POST['group_id'] ?? 0);
        //(int) : force la valeur à être un entier —> sécurité contre les injections
        //$_POST : contient toutes les données envoyées par le formulaire
    $description = trim($_POST['description'] ?? '');
        // trim() supprime les espaces inutiles en début/fin
    $amount      = $_POST['amount'] ?? '';
        // ?? '' signifie : valeur vide par défaut si le champ n'existe pas
    $payer_id    = (int) ($_POST['payer_id'] ?? 0);
    $expense_date = trim($_POST['expense_date'] ?? '');

    // VALIDATIONS
    // Vérifie que tous les champs obligatoires sont bien remplis
    if (empty($group_id) || empty($description) || empty($amount) || empty($payer_id)) {
        die('Veuillez remplir tous les champs obligatoires.');
    }

    // Vérifie que le montant est bien un nombre positif
    if (!is_numeric($amount) || $amount <= 0) {
        die('Le montant doit être un nombre positif.');
    }

    // VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN À CE GROUPE
    // Sécurité : on empêche un utilisateur d'ajouter une dépense dans un groupe dont il n'est pas membre
    $user_id = $_SESSION['user']['id'];
    $stmt_check = $connexion->prepare("
        SELECT account_group_id FROM group_users
        WHERE account_group_id = :group_id AND user_id = :user_id
    ");
        /* requête SQL : cherche dans la table group_users une ligne où l'id du groupe est celui qu'on a reçu ET où l'id de
        l'utilisateur est celui de la personne connectée*/ 
    // si refus : 
    $stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
    if (!$stmt_check->fetch()) {
        die('Accès refusé : vous n\'êtes pas membre de ce groupe.');
    }

    // INSERTION EN BASE DE DONNÉES
    // On insère la nouvelle dépense dans la table expenses
    // Si aucune date n'est fournie, on met NULL (la BD utilisera created_at)
    $stmt = $connexion->prepare("
        INSERT INTO expenses (account_group_id, payer_id, amount, description, expense_date)
        VALUES (:account_group_id, :payer_id, :amount, :description, :expense_date)
    ");
    /* requête SQL : insère une nouvelle ligne dans la table expenses en remplissant les colonnes account_group_id, payer_id, 
    amount, description et expense_date avec les valeurs qu'on lui fournit */ 
    $stmt->execute([
        'account_group_id' => $group_id,
        'payer_id'         => $payer_id,
        'amount'           => $amount,
        'description'      => $description,
        'expense_date'     => !empty($expense_date) ? $expense_date : null,
    ]);

    // REDIRECTION VERS LA LISTE DES GROUPES
    // Une fois la dépense ajoutée, on renvoie l'utilisateur vers ses groupes
    header('Location: https://divvyo.hepl-e-business.be/api/dashboard.php?group_id=' . $group_id);
    exit;
?>
