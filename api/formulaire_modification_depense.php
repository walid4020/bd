<?php
// Formulaire de modification d'une dépense existante

// 1. DÉMARRAGE DE LA SESSION
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers le login
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// 2. VÉRIFICATION QUE LES PARAMÈTRES SONT BIEN DANS L'URL
if (!isset($_GET['expense_id']) || !isset($_GET['group_id'])) {
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

// 4. SÉCURISATION DES PARAMÈTRES
$expense_id = (int) $_GET['expense_id'];
$group_id   = (int) $_GET['group_id'];
$user_id    = $_SESSION['user']['id'];

// 5. RÉCUPÉRATION DE LA DÉPENSE
// On vérifie en même temps que c'est bien l'utilisateur connecté qui a créé cette dépense
$stmt = $connexion->prepare("
    SELECT id, description, amount, expense_date, payer_id, account_group_id
    FROM expenses
    WHERE id = :expense_id AND payer_id = :user_id AND account_group_id = :group_id
");
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

// 6. RÉCUPÉRATION DU NOM DU GROUPE
$stmt_groupe = $connexion->prepare("SELECT name, currency FROM account_groups WHERE id = :group_id");
$stmt_groupe->execute(['group_id' => $group_id]);
$groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title> Modifier une dépense </title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body>
        <!-- Barre de navigation principale : is-success applique le fond vert Bulma, role et aria-label améliorent l'accessibilité -->
        <nav class="navbar is-success" style="background-color: var(--bulma-success-dark); align-items: center;" role="navigation" aria-label="navigation principale">

            <!-- Partie gauche de la navbar : contient le logo et le bouton -->
            <div class="navbar-brand">

                <!-- Logo cliquable qui ramène à l'accueil -->
                <a class="navbar-item" href="../index.html">
                    <img src="../assets/logo.png" alt="Divvyo" style="max-height: 56px;">
                </a>

                <!-- Bouton : visible uniquement sur mobile, remplace les liens du menu.
                    Les 3 spans forment les 3 traits de l'icône hamburger.
                    aria-hidden="true" les masque aux lecteurs d'écran car le bouton parent a déjà un aria-label. -->
                <a role="button" class="navbar-burger" id="burger" aria-label="menu" aria-expanded="false">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </a>
            </div>

            <!-- Menu principal : masqué sur mobile par défaut, affiché quand la classe is-active est ajoutée via JS -->
            <div class="navbar-menu" id="navMenu">

                <!-- navbar-end pousse les liens vers la droite de la barre -->
                <div class="navbar-end">
                    <a href="../index.html" class="navbar-item has-text-white">Accueil</a>
                    <a href="formulaire_choix_de_groupe.php" class="navbar-item has-text-white">Mes groupes</a>
                    <a href="../pages/formulaire_creation_groupe.html" class="navbar-item has-text-white">Créer un groupe</a>
                    <!-- Déconnexion : détruit la session PHP et redirige vers la page de connexion -->
                    <a href="../api/deconnexion.php" class="navbar-item has-text-white" style="font-weight:600;">Se déconnecter</a>
                </div>
            </div>
        </nav>

        <script>
            /* Sur mobile, un clic sur le hamburger ouvre ou ferme le menu
            en ajoutant/retirant la classe is-active sur les deux éléments concernés. */
            document.getElementById('burger').addEventListener('click', function() {
                this.classList.toggle('is-active');
                document.getElementById('navMenu').classList.toggle('is-active');
            });
        </script>
        <section class="section">
            <div class="container" style="max-width: 500px;">
                <div class="box">

                    <h1 class="has-text-centered title has-text-primary">
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

                        <button type="submit" class="button is-warning is-fullwidth mt-4">
                            Enregistrer les modifications
                        </button>

                    </form>

                    <br>
                    <p class="has-text-centered">
                        <a href="../api/formulaire_choix_de_groupe.php">← Retour à mes groupes</a>
                    </p>

                </div>
            </div>
        </section>
    </body>
</html>