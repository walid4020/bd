<!-- PARTIE PHP --> 

<?php
    // Formulaire de modification d'une dépense existante

    // DÉMARRAGE DE LA SESSION
    session_start();

    // Si l'utilisateur n'est pas connecté, on le redirige vers le login
    if (!isset($_SESSION['user'])) {
        header('Location: https://divvyo.hepl-e-business.be/index.html');
        exit;
    }

    // VÉRIFICATION QUE LES PARAMÈTRES SONT BIEN DANS L'URL
    if (!isset($_GET['expense_id']) || !isset($_GET['group_id'])) {
        header('Location: https://divvyo.hepl-e-business.be/api/formulaire_choix_de_groupe.php');
        exit;
    }

    // CONNEXION À LA BASE DE DONNÉES
    define('USER', 'vy44dy72oodv');
    define('PASSWD', 'd3-d2d!4oo');
    define('SERVER', 'localhost');
    define('BASE', 'ebus2_projet04_viiy78');
    // message d'erreur 
    try {
        $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
        $connexion = new PDO($dsn, USER, PASSWD);
    } catch (PDOException $e) {
        die('Échec de la connexion à la base de données');
    }

    // SÉCURISATION DES PARAMÈTRES
    $expense_id = (int) $_GET['expense_id'];
    $group_id   = (int) $_GET['group_id'];
    $user_id    = $_SESSION['user']['id'];

    // RÉCUPÉRATION DE LA DÉPENSE
    $stmt = $connexion->prepare("
        SELECT id, description, amount, expense_date, payer_id, account_group_id
        FROM expenses
        WHERE id = :expense_id AND payer_id = :user_id AND account_group_id = :group_id
    ");
    /* requête SQL : Sélectionne l'id, la description, le amount, la expense_date, le payer_id et l'account_group_id depuis 
    la table expenses où l'id est égal à l'id de la dépense ET où le payer_id est égal à l'id de l'utilisateur connecté ET 
    où l'account_group_id est égal à l'id du groupe */ 
    $stmt->execute([
        'expense_id' => $expense_id,
        'user_id'    => $user_id,
        'group_id'   => $group_id,
    ]);
    $depense = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si la dépense n'existe pas ou n'appartient pas à cet utilisateur, accès refusé
    if (!$depense) {
        die('Accès refusé : vous ne pouvez modifier que vos propres dépenses.');
    }

    // RÉCUPÉRATION DU NOM DU GROUPE
    $stmt_groupe = $connexion->prepare("SELECT name, currency FROM account_groups WHERE id = :group_id");
    $stmt_groupe->execute(['group_id' => $group_id]);
    $groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);
?>

<!-- PARTIE HTML --> 
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <!-- Permet d'adapter la largeur de l'écran pour la version mobile sans que la page ne s'affiche en tout petit -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title> Modifier une dépense </title>
        <!-- Charge le framework CSS Bulma depuis internet -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body style="background-color: var(--bulma-success-dark);">
        <!-- En-tête : logo cliquable qui ramène au dashboard, bouton déconnexion à droite -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem;">
            <a href="dashboard.php">
                <img src="../assets/logo.png" alt="Divvyo" style="max-height: 50px;">
            </a>
            <!-- Bouton se déconnecter --> 
            <a href="deconnexion.php" class="button is-danger is-light is-small">Se déconnecter</a>
        </div>
        <section class="section" style="min-height: calc(100vh - 70px);">
            <div class="container" style="max-width: 500px;">
                <div class="box">

                    <h1 class="has-text-centered title has-text-success-bold">
                        Modifier une dépense
                    </h1>
                    <p class="has-text-centered has-text-grey mb-5">
                        Groupe : <?= htmlspecialchars($groupe['name']) ?> (<?= htmlspecialchars($groupe['currency']) ?>)
                    </p>

                    <!-- Formulaire pré-rempli avec les données existantes -->
                    <form action="../api/modification_depense_reception_des_donnees.php" method="post">

                        <!-- Champs cachés pour transmettre les IDs au fichier PHP -->
                        <input type="hidden" name="expense_id" value="<?= $depense['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">

                        <!-- Description pré-remplie -->
                        <div class="field">
                            <label class="label" for="id_description"> Description : </label>
                            <div class="control">
                                <input class="input" type="text" name="description" id="id_description"
                                    value="<?= htmlspecialchars($depense['description']) ?>" required>
                            </div>
                        </div>

                        <!-- Montant pré-rempli -->
                        <div class="field">
                            <label class="label" for="id_montant"> Montant (<?= htmlspecialchars($groupe['currency']) ?>) : </label>
                            <div class="control">
                                <input class="input" type="number" step="0.01" min="0.01" name="amount" id="id_montant"
                                    value="<?= $depense['amount'] ?>" required>
                            </div>
                        </div>

                        <!-- Date pré-remplie -->
                        <div class="field">
                            <label class="label" for="id_date"> Date : </label>
                            <div class="control">
                                <input class="input" type="date" name="expense_date" id="id_date"
                                    value="<?= $depense['expense_date'] ? date('Y-m-d', strtotime($depense['expense_date'])) : '' ?>">
                            </div>
                        </div>
                        <!-- Bouton : enregistere les modifs --> 
                        <button type="submit" class="button is-success is-soft is-fullwidth mt-4">
                            Enregistrer les modifications
                        </button>

                    </form>

                    <br>
                    <p class="has-text-centered">
                        <a href="dashboard.php">← Retour à l'accueil</a>
                    </p>

                </div>
            </div>
        </section>
    </body>
</html>