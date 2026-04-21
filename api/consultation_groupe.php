<?php
// Page "Dépenses du groupe" - affiche toutes les dépenses d'un groupe sélectionné

// 1. DÉMARRAGE DE LA SESSION
// Nécessaire pour vérifier que l'utilisateur est bien connecté
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers la page de login
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// 2. VÉRIFICATION QUE L'ID DU GROUPE A BIEN ÉTÉ ENVOYÉ
// group_id est envoyé via POST depuis formulaire_choix_de_groupe.php
if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
    header('Location: https://divvyo.hepl-e-business.be/api/formulaire_choix_de_groupe.php');
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

// 4. SÉCURISATION DE L'ID DU GROUPE (entier uniquement)
$group_id = (int) $_POST['group_id'];
$user_id  = $_SESSION['user']['id'];

// 5. VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN À CE GROUPE
// Sécurité : un utilisateur ne peut pas consulter un groupe dont il n'est pas membre
$stmt_check = $connexion->prepare("
    SELECT id FROM group_users
    WHERE account_group_id = :group_id AND user_id = :user_id
");
$stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
if (!$stmt_check->fetch()) {
    die('Accès refusé : vous n\'êtes pas membre de ce groupe.');
}

// 6. RÉCUPÉRATION DU NOM DU GROUPE
// Pour l'afficher dans le titre de la page
$stmt_groupe = $connexion->prepare("
    SELECT name, currency FROM account_groups WHERE id = :group_id
");
$stmt_groupe->execute(['group_id' => $group_id]);
$groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);

// 7. RÉCUPÉRATION DES DÉPENSES DU GROUPE
// On joint la table expenses avec users pour afficher le prénom de la personne qui a payé
$stmt_depenses = $connexion->prepare("
    SELECT
        e.id,
        e.name          AS expense_name,
        e.amount,
        e.currency,
        e.expense_date,
        u.first_name    AS payer_first_name,
        u.last_name     AS payer_last_name
    FROM expenses e
    LEFT JOIN users u ON u.id = e.paid_by_user_id
    WHERE e.account_group_id = :group_id
    ORDER BY e.expense_date DESC
");
$stmt_depenses->execute(['group_id' => $group_id]);
$depenses = $stmt_depenses->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title> Dépenses du groupe </title>
        <!-- CSS : framework Bulma -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body>
        <section class="section">
            <div class="container" style="max-width: 700px;">
                <div class="box">

                    <!-- Titre de la page avec le nom du groupe -->
                    <h1 class="title has-text-primary has-text-centered">
                        📋 <?= htmlspecialchars($groupe['name']) ?>
                    </h1>
                    <p class="has-text-centered has-text-grey mb-5">
                        Devise du groupe : <?= htmlspecialchars($groupe['currency']) ?>
                    </p>

                    <?php if (empty($depenses)): ?>
                        <!-- Affiché si le groupe n'a encore aucune dépense -->
                        <p class="has-text-centered has-text-grey">
                            Aucune dépense enregistrée pour ce groupe pour l'instant.
                        </p>

                    <?php else: ?>
                        <!-- Tableau listant toutes les dépenses du groupe -->
                        <table class="table is-fullwidth is-striped is-hoverable">
                            <thead>
                                <tr>
                                    <th>Nom de la dépense</th>
                                    <th>Montant</th>
                                    <th>Devise</th>
                                    <th>Payé par</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($depenses as $depense): ?>
                                    <tr>
                                        <!-- Nom de la dépense -->
                                        <td><?= htmlspecialchars($depense['expense_name']) ?></td>

                                        <!-- Montant formaté avec 2 décimales -->
                                        <td><?= number_format($depense['amount'], 2) ?></td>

                                        <!-- Devise de la dépense -->
                                        <td><?= htmlspecialchars($depense['currency']) ?></td>

                                        <!-- Prénom + Nom de la personne qui a payé -->
                                        <td>
                                            <?= htmlspecialchars($depense['payer_first_name'] . ' ' . $depense['payer_last_name']) ?>
                                        </td>

                                        <!-- Date de la dépense, formatée en DD/MM/YYYY -->
                                        <td>
                                            <?= $depense['expense_date']
                                                ? date('d/m/Y', strtotime($depense['expense_date']))
                                                : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Boutons de navigation -->
                    <div class="buttons is-flex is-flex-direction-column mt-5">

                        <!-- Lien vers la création d'une dépense dans ce groupe -->
                        <a href="../pages/formulaire_creation_depense.html" class="button is-info is-fullwidth">
                            💸 Ajouter une dépense
                        </a>

                        <!-- Lien retour vers la liste des groupes -->
                        <a href="../api/formulaire_choix_de_groupe.php" class="button is-light is-fullwidth">
                            ← Retour à mes groupes
                        </a>

                    </div>

                </div>
            </div>
        </section>
    </body>
</html>
