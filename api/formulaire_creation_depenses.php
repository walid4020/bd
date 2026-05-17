<!-- PARTIE PHP --> 
<?php
    // Formulaire de création d'une dépense dans un groupe

    // DÉMARRAGE DE LA SESSION
    session_start();

    // Si l'utilisateur n'est pas connecté, on le redirige vers le login
    if (!isset($_SESSION['user'])) {
        header('Location: https://divvyo.hepl-e-business.be/index.html');
        exit;
    }

    // VÉRIFICATION QUE LE GROUP_ID EST BIEN PASSÉ DANS L'URL
    // Ex : formulaire_creation_depense.php?group_id=3
    if (!isset($_GET['group_id']) || empty($_GET['group_id'])) {
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

    // SÉCURISATION DU GROUP_ID
    $group_id = (int) $_GET['group_id'];
    $user_id  = $_SESSION['user']['id'];

    // VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN À CE GROUPE
    $stmt_check = $connexion->prepare("
        SELECT account_group_id FROM group_users
        WHERE account_group_id = :group_id AND user_id = :user_id
    ");
    /* requête SQL : Sélectionne l'account_group_id depuis la table group_users où l'account_group_id est égal à l'id du groupe 
    ET où l'user_id est égal à l'id de l'utilisateur connecté */ 
    $stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
    if (!$stmt_check->fetch()) {
        die('Accès refusé : vous n\'êtes pas membre de ce groupe.');
    }

    // RÉCUPÉRATION DU NOM DU GROUPE (pour l'afficher dans le titre)
    $stmt_groupe = $connexion->prepare("
        SELECT name, currency FROM account_groups WHERE id = :group_id
    ");
    // requête SQL : Sélectionne le name et la currency depuis la table account_groups où l'id est égal à l'id du groupe.
    $stmt_groupe->execute(['group_id' => $group_id]);
    $groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);

    // RÉCUPÉRATION DES MEMBRES DU GROUPE (pour le champ "Payé par")
    $stmt_membres = $connexion->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        INNER JOIN group_users gu ON gu.user_id = u.id
        WHERE gu.account_group_id = :group_id
        ORDER BY u.first_name ASC
    ");
    /* requête SQL : Sélectionne l'id, le first_name et le last_name depuis la table users, fais une jointure avec group_users 
    où l'user_id de group_users correspond à l'id de users, filtre pour ne garder que les membres de ce groupe, et trie les
    résultats par prénom dans l'ordre alphabétique */ 
    $stmt_membres->execute(['group_id' => $group_id]);
    $membres = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- PARTIE HTML --> 
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <!-- Permet d'adapter la largeur de l'écran pour la version mobile sans que la page ne s'affiche en tout petit -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title> Créer une nouvelle dépense </title>
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

                    <!-- Titre avec le nom du groupe concerné -->
                    <h1 class="has-text-centered title has-text-success-bold">
                        Nouvelle dépense
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
                        <button type="submit" class="button is-success is-soft is-fullwidth mt-4">
                            Ajouter la dépense
                        </button>

                    </form>

                    <!-- Lien retour vers la liste des groupes -->
                    <br>
                    <p class="has-text-centered">
                        <a href="dashboard.php">← Retour à l'accueil</a>
                    </p>

                </div>
            </div>
        </section>
    </body>
</html>
