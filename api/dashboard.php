<!-- PARTIE PHP -->
<?php
// Dashboard principal - vue globale de l'utilisateur connecté

// DÉMARRAGE DE LA SESSION
session_start();

// Vérifie que l'utilisateur est bien connecté avant de continuer
if (!isset($_SESSION['user'])) {
    header('Location: ../index.html');
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

$user_id = $_SESSION['user']['id'];

// RÉCUPÉRATION DE TOUS LES GROUPES DE L'UTILISATEUR (panneau gauche)
$stmt_groupes = $connexion->prepare("
    SELECT ag.id, ag.name, ag.description, ag.currency
    FROM account_groups ag
    INNER JOIN group_users gu ON gu.account_group_id = ag.id
    WHERE gu.user_id = :user_id
    ORDER BY ag.created_at DESC
");
/* requête SQL : Sélectionne l'id, le nom, la description et la devise depuis la table account_groups, 
fais une jointure avec group_users pour ne garder que les groupes dont l'account_group_id correspond à l'id du groupe, 
filtre pour ne garder que les groupes où l'user_id correspond à celui de l'utilisateur connecté,
 et trie les résultats par date de création du plus récent au plus ancien */ 
$stmt_groupes->execute(['user_id' => $user_id]);
$groupes = $stmt_groupes->fetchAll(PDO::FETCH_ASSOC);

// GROUPE SÉLECTIONNÉ : celui passé dans l'URL, ou le premier de la liste par défaut
$group_id = null;
$groupe_selectionne = null;
$membres = [];
$depenses = [];
$remboursements = [];
    /* Si un group_id est présent dans l'URL (ex: dashboard.php?group_id=3), on l'utilise. Le (int) force la valeur à être 
    un entier pour des raisons de sécurité.*/ 
if (isset($_GET['group_id'])) {
    $group_id = (int) $_GET['group_id'];
    /* Sinon, si l'utilisateur a au moins un groupe, on prend automatiquement le premier de la liste ([0] = premier 
    élément du tableau). */ 
} elseif (!empty($groupes)) {
    $group_id = $groupes[0]['id'];
}

// SI UN GROUPE EST SÉLECTIONNÉ, ON CHARGE SES DONNÉES (panneau droit)
if ($group_id) {

    // Vérification que l'utilisateur appartient bien à ce groupe
    $stmt_check = $connexion->prepare("
        SELECT account_group_id FROM group_users
        WHERE account_group_id = :group_id AND user_id = :user_id
    ");
    /* requête SQL : Sélectionne account_group_id depuis la table group_users où account_group_id est égal à l'id du groupe 
    et user_id est égal à l'id de l'utilisateur connecté */
    $stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);

    if (!$stmt_check->fetch()) {
        $group_id = null;
    } else {

        // Nom, description et devise du groupe sélectionné
        $stmt_detail = $connexion->prepare("
            SELECT name, description, currency FROM account_groups WHERE id = :group_id
        ");
        /* requête SQL : Sélectionne le name, la description et la currency depuis la table account_groups où l'id est égal 
        à l'id du groupe*/ 
        $stmt_detail->execute(['group_id' => $group_id]);
        $groupe_selectionne = $stmt_detail->fetch(PDO::FETCH_ASSOC);

        // Membres du groupe
        $stmt_membres = $connexion->prepare("
            SELECT u.first_name, u.last_name
            FROM users u
            INNER JOIN group_users gu ON gu.user_id = u.id
            WHERE gu.account_group_id = :group_id
            ORDER BY u.first_name ASC
        ");
        /* requête SQL : Sélectionne le first_name et le last_name depuis la table users, fais une jointure avec group_users 
        pour ne garder que les utilisateurs dont le user_id correspond à l'id de l'utilisateur, filtre pour ne garder que les 
        membres de ce groupe, et trie les résultats par prénom dans l'ordre alphabétique.*/ 
        $stmt_membres->execute(['group_id' => $group_id]);
        $membres = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);

        // Dépenses du groupe, triées de la plus récente à la plus ancienne
        $stmt_depenses = $connexion->prepare("
            SELECT e.id, e.description, e.amount, e.expense_date, e.payer_id,
                   u.first_name AS payer_first_name, u.last_name AS payer_last_name
            FROM expenses e
            LEFT JOIN users u ON u.id = e.payer_id
            WHERE e.account_group_id = :group_id
            ORDER BY e.expense_date DESC
        ");
        /* requête SQL : Sélectionne l'id, la description, le amount, la expense_date et le payer_id depuis la table expenses, 
        ainsi que le first_name et le last_name depuis la table users renommés payer_first_name et payer_last_name, 
        fais une jointure avec users pour relier chaque dépense à la personne qui a payé via le payer_id, filtre pour ne 
        garder que les dépenses de ce groupe, et trie les résultats par date de la plus récente à la plus ancienne */ 
        $stmt_depenses->execute(['group_id' => $group_id]);
        $depenses = $stmt_depenses->fetchAll(PDO::FETCH_ASSOC);

        // Calcul du total payé par chaque membre
        $stmt_soldes = $connexion->prepare("
            SELECT u.id, u.first_name, u.last_name,
                   COALESCE(SUM(e.amount), 0) AS total_paye
            FROM users u
            INNER JOIN group_users gu ON gu.user_id = u.id
            LEFT JOIN expenses e ON e.payer_id = u.id AND e.account_group_id = :group_id
            WHERE gu.account_group_id = :group_id
            GROUP BY u.id, u.first_name, u.last_name
        ");
        /* requête SQL : Sélectionne l'id, le first_name et le last_name depuis la table users, ainsi que la somme de tous 
        les montants payés par chaque membre (ou 0 s'il n'a rien payé) renommée total_paye, fais une jointure avec group_users 
        pour ne garder que les membres de ce groupe, fais une jointure avec expenses pour relier les dépenses à chaque membre 
        dans ce groupe, filtre pour ne garder que les membres de ce groupe, et regroupe les résultats par membre pour que 
        la somme soit calculée par personne */ 
        $stmt_soldes->execute(['group_id' => $group_id]);
        $membres_soldes = $stmt_soldes->fetchAll(PDO::FETCH_ASSOC);

        // Part équitable par personne et calcul du solde de chacun
        $total_depenses = array_sum(array_column($membres_soldes, 'total_paye'));
        $nb_membres = count($membres_soldes);
        $part_par_personne = $nb_membres > 0 ? $total_depenses / $nb_membres : 0;

        foreach ($membres_soldes as &$m) {
            $m['solde'] = $m['total_paye'] - $part_par_personne;
        }
        unset($m);

        // Algorithme de simplification des dettes (qui doit combien à qui)
        $crediteurs = [];
        $debiteurs = [];
        foreach ($membres_soldes as $m) {
            if ($m['solde'] > 0.01)      $crediteurs[] = ['nom' => $m['first_name'] . ' ' . $m['last_name'], 'solde' => $m['solde']];
            elseif ($m['solde'] < -0.01) $debiteurs[]  = ['nom' => $m['first_name'] . ' ' . $m['last_name'], 'solde' => $m['solde']];
        }

        $i = 0; $j = 0;
        while ($i < count($debiteurs) && $j < count($crediteurs)) {
            $montant = min(abs($debiteurs[$i]['solde']), $crediteurs[$j]['solde']);
            $remboursements[] = ['de' => $debiteurs[$i]['nom'], 'a' => $crediteurs[$j]['nom'], 'montant' => $montant];
            $debiteurs[$i]['solde'] += $montant;
            $crediteurs[$j]['solde'] -= $montant;
            if (abs($debiteurs[$i]['solde']) < 0.01) $i++;
            if ($crediteurs[$j]['solde'] < 0.01) $j++;
        }
    }
}
?>

<!-- PARTIE HTML --> 
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="color-scheme" content="light">
    <!-- Permet d'adapter la largeur de l'écran pour la version mobile sans que la page ne s'affiche en tout petit -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divvyo – Dashboard</title>
    <!-- Charge le framework CSS Bulma depuis internet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    <style>
        /* Sur mobile, les deux panneaux du dashboard s'empilent verticalement */
        @media (max-width: 768px) {
            .deux-panneaux {
                flex-direction: column !important;
                height: auto !important;
            }
            .panneau-gauche {
                flex: none !important;
                width: 100% !important;
                overflow: visible !important;
            }
            .panneau-droit {
                flex: none !important;
                width: 100% !important;
                overflow: visible !important;
            }
        }
    </style>
</head>
<body style="background-color: var(--bulma-success-dark); min-height: 100vh;">

    
        <!-- En-tête : logo à gauche, bouton déconnexion à droite -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem;">
            <img src="../assets/logo.png" alt="Divvyo" style="max-height: 50px;">
            <!-- Bouton se dédonnecter à droite --> 
            <a href="deconnexion.php" class="button is-danger is-light is-small">Se déconnecter</a>
        </div>

    <!-- MISE EN PAGE DEUX PANNEAUX -->
        <div class="deux-panneaux" style="display: flex; gap: 1rem; padding: 0 1rem 1rem; height: calc(100vh - 70px);">
            <!-- calc(100vh - 70px) : la section prend toute la hauteur de l'écran moins les 70px de l'en-tête, 
             permet d'éviter d'avoir une scrollbar inutile-->
        
        <!-- PANNEAU GAUCHE (1/3) -->
        <div class="panneau-gauche" style="flex: 0 0 32%; display: flex; flex-direction: column; gap: 0.8rem; overflow: hidden;">           
        <!-- Message de bienvenue affiché en haut du panneau gauche -->
        <p style="color: white; font-weight: 600; font-size: 1.4rem; text-align: center;">
            Bienvenu <?= htmlspecialchars(explode(' ', $_SESSION['user']['displayName'])[0]) ?> 👋
        </p>
            <!-- $_SESSION['user']['displayName'] : contient "Prénom Nom" 
            explode(' ', ...) : découpe la chaîne en tableau en coupant à chaque espace 
            [0] : prend uniquement le premier élément (prénom)
h           tmlspecialchars() : sécurise l'affichage contre les failles XSS --> 

        <!-- Bouton créer un nouveau groupe -->
        <a href="../pages/formulaire_creation_groupe.html" class="button is-success is-soft is-fullwidth">
            + Nouveau groupe
        </a>
            

            <!-- Liste des groupes de l'utilisateur -->
            <div class="box" style="flex: 1; overflow-y: auto;">
                <h2 class="title is-5 mb-3" style="color: var(--bulma-success-dark);">Mes groupes</h2>

                <!-- Si aucun groupe : --> 
                <?php if (empty($groupes)): ?>
                    <p class="has-text-grey">Aucun groupe pour l'instant.</p>
                <?php else: ?>

                    <!-- Liste des groupes sur la gauche --> 
                    <?php foreach ($groupes as $g): ?>
                        <!-- Chaque groupe recharge la page avec son ID dans l'URL -->
                        <a href="dashboard.php?group_id=<?= $g['id'] ?>"
                           class="button is-fullwidth mb-2 <?= $g['id'] == $group_id ? 'is-success' : 'is-success is-soft' ?>"
                           style="height: auto; padding: 10px; justify-content: flex-start; white-space: normal; text-align: left;">
                            <div>
                                <strong><?= htmlspecialchars($g['name']) ?></strong><br>
                                <small><?= htmlspecialchars($g['description']) ?> — <?= htmlspecialchars($g['currency']) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANNEAU DROIT (2/3) -->
        <div class="panneau-droit" style="flex: 1; overflow-y: auto;">

            <?php if ($groupe_selectionne): ?>
                <div class="box" style="min-height: 100%;">

                    <!-- Nom du groupe sélectionné -->
                    <h1 class="title mb-1" style="color: var(--bulma-success-dark);">
                        <?= htmlspecialchars($groupe_selectionne['name']) ?>
                    </h1>

                    <!-- Description et devise -->
                    <p class="has-text-grey mb-3">
                        <?= htmlspecialchars($groupe_selectionne['description']) ?>
                        — <?= htmlspecialchars($groupe_selectionne['currency']) ?>
                    </p>

                    <!-- Membres affichés sous forme de tags -->
                    <div class="tags mb-4">
                        <?php foreach ($membres as $membre): ?>
                            <span class="tag is-success is-light">
                                <?= htmlspecialchars($membre['first_name'] . ' ' . $membre['last_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bouton ajouter une dépense -->    
                    <a href="formulaire_creation_depenses.php?group_id=<?= $group_id ?>"
                       class="button is-success is-soft is-fullwidth mb-4">
                        + Dépense
                    </a>

                    <!-- Tableau des dépenses du groupe -->
                    <?php if (empty($depenses)): ?>
                        <p class="has-text-centered has-text-grey mb-4">Aucune dépense enregistrée pour ce groupe.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                            <table class="table is-fullwidth is-striped is-hoverable mb-4" style="min-width: 500px;">
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
                                            <td><?= htmlspecialchars($depense['description']) ?></td>
                                            <td><?= number_format($depense['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($groupe_selectionne['currency']) ?></td>
                                            <td><?= htmlspecialchars($depense['payer_first_name'] . ' ' . $depense['payer_last_name']) ?></td>
                                            <td><?= $depense['expense_date'] ? date('d/m/Y', strtotime($depense['expense_date'])) : '—' ?></td>
                                            <td>
                                                <!-- Seul celui qui a créé la dépense peut la modifier -->
                                                <?php if ($depense['payer_id'] == $user_id): ?>
                                                    <a href="formulaire_modification_depense.php?expense_id=<?= $depense['id'] ?>&group_id=<?= $group_id ?>"
                                                    class="button is-success is-soft is-small">Modifier</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Section remboursements -->
                    <h2 class="subtitle has-text-weight-bold" style="color: var(--bulma-success-dark);">
                        Remboursements à faire
                    </h2>
                    <?php if (empty($remboursements)): ?>
                        <p class="has-text-centered" style="color: var(--bulma-success-dark);">Tout le monde est quitte !</p>
                    <?php else: ?>
                        <?php foreach ($remboursements as $r): ?>
                            <div class="notification is-success is-light">
                                <strong><?= htmlspecialchars($r['de']) ?></strong>
                                doit
                                <strong><?= number_format($r['montant'], 2) ?> <?= htmlspecialchars($groupe_selectionne['currency']) ?></strong>
                                à
                                <strong><?= htmlspecialchars($r['a']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                     <!-- Bouton exporter les dépenses en PDF -->
                        <a href="export_pdf.php?group_id=<?= $group_id ?>"
                         class="button is-success is-soft is-fullwidth mb-4">
                        Exporter les dépenses
                        </a>

                    <!-- Bouton suppression, affiché une seule fois après les remboursements -->
                    <form action="suppression_groupe.php" method="post" class="mt-4"
                          onsubmit="return confirm('Êtes-vous vraiment sûr de vouloir supprimer ce groupe ? Toutes les dépenses associées seront également supprimées. Cette action est irréversible.');">
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">
                        <button type="submit" class="button is-danger is-light is-fullwidth">
                            Supprimer ce groupe
                        </button>
                    </form>

                </div>

            <?php else: ?>
                <!-- Affiché si l'utilisateur n'a encore aucun groupe -->
                <div class="box" style="min-height: 100%; display: flex; align-items: center; justify-content: center;">
                    <p class="has-text-grey has-text-centered">
                        Vous n'avez pas encore de groupe.<br>
                        Commencez par en créer un avec le bouton "+ Nouveau groupe".
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>