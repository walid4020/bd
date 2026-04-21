<?php
// Formulaire de création d'une dépense dans un groupe

// 1. DÉMARRAGE DE LA SESSION
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers le login
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// 2. VÉRIFICATION QUE LE GROUP_ID EST BIEN PASSÉ DANS L'URL
// Ex : formulaire_creation_depense.php?group_id=3
if (!isset($_GET['group_id']) || empty($_GET['group_id'])) {
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

// 4. SÉCURISATION DU GROUP_ID
$group_id = (int) $_GET['group_id'];
$user_id  = $_SESSION['user']['id'];

// 5. VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN À CE GROUPE
$stmt_check = $connexion->prepare("
    SELECT account_group_id FROM group_users
    WHERE account_group_id = :group_id AND user_id = :user_id
");
$stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
if (!$stmt_check->fetch()) {
    die('Accès refusé : vous n\'êtes pas membre de ce groupe.');
}

// 6. RÉCUPÉRATION DU NOM DU GROUPE (pour l'afficher dans le titre)
$stmt_groupe = $connexion->prepare("
    SELECT name, currency FROM account_groups WHERE id = :group_id
");
$stmt_groupe->execute(['group_id' => $group_id]);
$groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);

// 7. RÉCUPÉRATION DES MEMBRES DU GROUPE (pour le champ "Payé par")
// On joint group_users et users pour avoir les prénoms et noms des membres
$stmt_membres = $connexion->prepare("
    SELECT u.id, u.first_name, u.last_name
    FROM users u
    INNER JOIN group_users gu ON gu.user_id = u.id
    WHERE gu.account_group_id = :group_id
    ORDER BY u.first_name ASC
");
$stmt_membres->execute(['group_id' => $group_id]);
$membres = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title> Créer une dépense </title>
        <!-- CSS : framework Bulma -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body>
        <section class="section">
            <div class="container" style="max-width: 500px;">
                <div class="box">

                    <!-- Titre avec le nom du groupe concerné -->
                    <h1 class="has-text-centered title has-text-primary">
                        💸 Nouvelle dépense
                    </h1>
                    <p class="has-text-centered has-text-grey mb-5">
                        Groupe : <?= htmlspecialchars($groupe['name']) ?> (<?= htmlspecialchars($groupe['currency']) ?>)
                    </p>

                    <!-- Formulaire d'ajout de dépense -->
                    <!-- action : fichier PHP qui va insérer la dépense en base de données -->
                    <form action="../api/creation_depense_reception_des_donnees.php" method="post">

                        <!-- Champ caché : transmet l'ID du groupe au fichier PHP sans que l'utilisateur le voie -->
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">

                        <!-- Description de la dépense -->
                        <div class="field">
                            <label class="label" for="id_description"> Description : </label>
                            <div class="control">
                                <input class="input" type="text" name="description" id="id_description"
                                    placeholder="Ex : Restaurant, Courses, Loyer..." required>
                            </div>
                        </div>

                        <!-- Montant de la dépense -->
                        <div class="field">
                            <label class="label" for="id_montant"> Montant (<?= htmlspecialchars($groupe['currency']) ?>) : </label>
                            <div class="control">
                                <!-- step="0.01" permet d'entrer des centimes (ex : 12.50) -->
                                <input class="input" type="number" step="0.01" min="0.01" name="amount" id="id_montant"
                                    placeholder="Ex : 45.00" required>
                            </div>
                        </div>

                        <!-- Qui a payé ? Liste dynamique des membres du groupe -->
                        <div class="field">
                            <label class="label" for="id_payer"> Payé par : </label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="payer_id" id="id_payer" required>
                                        <option value="">-- Choisir un membre --</option>
                                        <?php foreach ($membres as $membre): ?>
                                            <!-- On pré-sélectionne l'utilisateur connecté par défaut -->
                                            <option value="<?= $membre['id'] ?>"
                                                <?= $membre['id'] == $user_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($membre['first_name'] . ' ' . $membre['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Date de la dépense -->
                        <div class="field">
                            <label class="label" for="id_date"> Date : </label>
                            <div class="control">
                                <!-- value = date du jour par défaut -->
                                <input class="input" type="date" name="expense_date" id="id_date"
                                    value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <!-- Bouton d'envoi -->
                        <button type="submit" class="button is-primary is-fullwidth has-text-white mt-4">
                            ✅ Ajouter la dépense
                        </button>

                    </form>

                    <!-- Lien retour vers la liste des groupes -->
                    <br>
                    <p class="has-text-centered">
                        <a href="../api/formulaire_choix_de_groupe.php">← Retour à mes groupes</a>
                    </p>

                </div>
            </div>
        </section>
    </body>
</html>
