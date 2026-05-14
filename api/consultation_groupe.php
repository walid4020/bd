<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

// 4. SÉCURISATION DE L'ID DU GROUPE (entier uniquement)
$group_id = (int) $_GET['group_id'];
$user_id  = $_SESSION['user']['id'];

// 5. VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN À CE GROUPE
// Sécurité : un utilisateur ne peut pas consulter un groupe dont il n'est pas membre
$stmt_check = $connexion->prepare("
    SELECT account_group_id FROM group_users
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
// Colonnes utilisées : description, amount, payer_id, expense_date
$stmt_depenses = $connexion->prepare("
    SELECT
        e.id,
        e.description,
        e.amount,
        e.expense_date,
        e.payer_id,
        u.first_name    AS payer_first_name,
        u.last_name     AS payer_last_name
    FROM expenses e
    LEFT JOIN users u ON u.id = e.payer_id
    WHERE e.account_group_id = :group_id
    ORDER BY e.expense_date DESC
");
$stmt_depenses->execute(['group_id' => $group_id]);
$depenses = $stmt_depenses->fetchAll(PDO::FETCH_ASSOC);

// 8. CALCUL DES SOLDES PAR MEMBRE
// On additionne tout ce que chaque membre a payé dans ce groupe
$stmt_soldes = $connexion->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        COALESCE(SUM(e.amount), 0) AS total_paye
    FROM users u
    INNER JOIN group_users gu ON gu.user_id = u.id
    LEFT JOIN expenses e ON e.payer_id = u.id AND e.account_group_id = :group_id
    WHERE gu.account_group_id = :group_id
    GROUP BY u.id, u.first_name, u.last_name
");
$stmt_soldes->execute(['group_id' => $group_id]);
$membres_soldes = $stmt_soldes->fetchAll(PDO::FETCH_ASSOC);

// Calcul de la part équitable : total de toutes les dépenses ÷ nombre de membres
$total_depenses = array_sum(array_column($membres_soldes, 'total_paye'));
$nb_membres = count($membres_soldes);
$part_par_personne = $nb_membres > 0 ? $total_depenses / $nb_membres : 0;

// Calcul du solde de chaque personne : ce qu'il a payé - ce qu'il aurait dû payer
// Positif = on lui doit de l'argent / Négatif = il doit de l'argent
foreach ($membres_soldes as &$m) {
    $m['solde'] = $m['total_paye'] - $part_par_personne;
}
unset($m);

// Algorithme de simplification des dettes
// On sépare ceux qui ont trop payé (créanciers) de ceux qui n'ont pas assez payé (débiteurs)
$crediteurs = [];
$debiteurs  = [];
foreach ($membres_soldes as $m) {
    if ($m['solde'] > 0.01) {
        $crediteurs[] = ['nom' => $m['first_name'] . ' ' . $m['last_name'], 'solde' => $m['solde']];
    } elseif ($m['solde'] < -0.01) {
        $debiteurs[] = ['nom' => $m['first_name'] . ' ' . $m['last_name'], 'solde' => $m['solde']];
    }
}

// On calcule les remboursements à faire (qui doit combien à qui)
$remboursements = [];
$i = 0; $j = 0;
while ($i < count($debiteurs) && $j < count($crediteurs)) {
    $montant = min(abs($debiteurs[$i]['solde']), $crediteurs[$j]['solde']);
    $remboursements[] = [
        'de'     => $debiteurs[$i]['nom'],
        'a'      => $crediteurs[$j]['nom'],
        'montant'=> $montant,
    ];
    $debiteurs[$i]['solde'] += $montant;
    $crediteurs[$j]['solde'] -= $montant;
    if (abs($debiteurs[$i]['solde']) < 0.01) $i++;
    if ($crediteurs[$j]['solde'] < 0.01) $j++;
}
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title> Dépenses du groupe </title>
        <!-- CSS : framework Bulma -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
        <style>
            body { overflow-x: hidden; }
        </style>
    </head>
    <body style="background-color: var(--bulma-success-dark);">
        <!-- Barre de navigation principale, fond vert foncé sur toute la largeur de l'écran -->
        <nav class="navbar is-success" style="background-color: var(--bulma-success-dark); align-items: center;" role="navigation" aria-label="navigation principale">

            <!-- Partie gauche : logo cliquable qui ramène à l'accueil -->
            <div class="navbar-brand">
                <a class="navbar-item" href="../index.html">
                    <img src="../assets/logo.png" alt="Divvyo" style="max-height: 56px;">
                </a>

                <!-- Bouton hamburger : visible uniquement sur mobile, remplace les liens du menu.
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
                    <a href="deconnexion.php" class="navbar-item has-text-white" style="font-weight: 600;">Se déconnecter</a>
                </div>
            </div>
        </nav>
        <header style="background-color: #257942; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem;">
                <a href="dashboard.php">
                    <img src="../assets/logo.png" alt="Divvyo" style="max-height: 50px;">
                </a>
                <a href="deconnexion.php" class="button is-danger is-light is-small">Se déconnecter</a>
            </div>
        </header>
        <section class="section" style="min-height: 100vh;">
            <div class="container" style="max-width: 700px;">
                <div class="box">

                    <!-- Titre de la page avec le nom du groupe -->
                    <h1 class="title has-text-success-bold has-text-centered">
                         <?= htmlspecialchars($groupe['name']) ?>
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
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                        <table class="table is-fullwidth is-striped is-hoverable" style="min-width: 500px;">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Montant</th>
                                    <th>Devise</th>
                                    <th>Payé par</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($depenses as $depense): ?>
                                    <tr>
                                        <!-- Nom de la dépense -->
                                        <td><?= htmlspecialchars($depense['description']) ?></td>

                                        <!-- Montant formaté avec 2 décimales -->
                                        <td><?= number_format($depense['amount'], 2) ?></td>

                                        <!-- Devise de la dépense -->
                                        <td><?= htmlspecialchars($groupe['currency']) ?></td>

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
                                        <!-- Pouvoir modifier la dépense encodée -->
                                        <td>
                                            <?php if ($depense['payer_id'] == $user_id): ?>
                                                <a href="formulaire_modification_depense.php?expense_id=<?= $depense['id'] ?>&group_id=<?= $group_id ?>"
                                                class="button is-success is-soft is-small">
                                                    Modifier
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <!-- SECTION REMBOURSEMENTS : qui doit combien à qui -->
                    <h2 class="subtitle has-text-success-bold has-text-centered mt-4">
                        Remboursements à faire
                    </h2>
                    <?php if (empty($remboursements)): ?>
                        <p class="has-text-centered has-text-success-bold">Tout le monde est quitte ! </p>
                    <?php else: ?>
                        <?php foreach ($remboursements as $r): ?>
                            <div class="notification is-success is-light">
                                <strong><?= htmlspecialchars($r['de']) ?></strong>
                                doit
                                <strong><?= number_format($r['montant'], 2) ?> <?= htmlspecialchars($groupe['currency']) ?></strong>
                                à
                                <strong><?= htmlspecialchars($r['a']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Boutons de navigation -->
                    <div class="buttons is-flex is-flex-direction-column mt-5">

                        <!-- Bouton pour exporter toutes les dépenses du groupe au format PDF -->
                        <a href="export_pdf.php?group_id=<?= $group_id ?>" class="button is-danger is-fullwidth">
                        EXPORT PDF
                        </a>

                        <!-- Lien vers la création d'une dépense dans ce groupe -->
                        <a href="formulaire_creation_depenses.php?group_id=<?= $group_id ?>" class="button is var(--bulma-success-dark)is-fullwidth">
                             💸 Ajouter une dépense
                        </a>

                        <!-- Lien retour vers la liste des groupes -->
                        <a href="dashboard.php" class="button is-light is-fullwidth">
                            ← Retour à l'accueil
                        </a>

                    </div>

                </div>
            </div>
        </section>
    </body>
</html>
