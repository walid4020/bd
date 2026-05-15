<!-- PARTIE PHP --> 
<?php
    //Active l'affichage de toutes les erreurs PHP —> utile pendant le développement pour déboguer
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // VERIFICATION DE LA SESSION
    session_start();
    // Si l'utilisateur n'est pas connecté, on le redirige vers le login. exit est indispensable pour stopper l'exécution du reste du code
    if (!isset($_SESSION['user'])) {
        header('Location: https://divvyo.hepl-e-business.be/index.html');
        exit;
    }

    // VÉRIFICATION DU GROUP_ID
    // Le group_id arrive dans l'URL (?group_id=3). Si absent ou vide, on redirige.
    if (!isset($_GET['group_id']) || empty($_GET['group_id'])) {    
        header('Location: https://divvyo.hepl-e-business.be/api/formulaire_choix_de_groupe.php');
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

    // SÉCURISATION DE L'ID DU GROUPE (entier uniquement)
    //(int) force la valeur à être un entier —> protège contre les injections SQL si quelqu'un mettait du texte dans l'URL
    $group_id = (int) $_GET['group_id'];
    $user_id  = $_SESSION['user']['id'];

    // VÉRIFICATION QUE L'UTILISATEUR APPARTIENT BIEN UN GROUPE
    // Sécurité importante : vérifie que l'utilisateur connecté est bien membre de ce groupe avant d'afficher quoi que ce soit.
    $stmt_check = $connexion->prepare("
        SELECT account_group_id FROM group_users
        WHERE account_group_id = :group_id AND user_id = :user_id
    ");
    $stmt_check->execute(['group_id' => $group_id, 'user_id' => $user_id]);
    if (!$stmt_check->fetch()) {
        die('Accès refusé : vous n\'êtes pas membre de ce groupe.');
    }

    // a commenter !!!!!
    // 6. RÉCUPÉRATION DU NOM DU GROUPE
    // Pour l'afficher dans le titre de la page 
    $stmt_groupe = $connexion->prepare("
        SELECT name, currency FROM account_groups WHERE id = :group_id
    ");
    $stmt_groupe->execute(['group_id' => $group_id]);
    $groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);

    // RÉCUPÉRATION DES DÉPENSES DU GROUPE
    // On joint la table expenses avec users pour afficher le prénom de la personne qui a payé
    // Colonnes utilisées : description, amount, payer_id, expense_date
    // LEFT JOIN relie la table expenses à la table users pour récupérer le prénom/nom de la personne qui a payé. 
    // LEFT signifie qu'on garde la dépense même si le payeur n'est plus trouvé en BD.
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

    // CALCUL DES SOLDES PAR MEMBRE
    // On additionne tout ce que chaque membre a payé dans ce groupe
    // SUM additionne toutes les dépenses d'un membre. COALESCE(..., 0) remplace NULL par 0 si le membre n'a aucune dépense.
    // INNER JOIN ici (vs LEFT JOIN) : on ne garde que les utilisateurs qui sont bien membres du groupe.
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

    // CALCUL DE LA PART EQUITABLE : total de toutes les dépenses ÷ nombre de membres
    $total_depenses = array_sum(array_column($membres_soldes, 'total_paye'));
    $nb_membres = count($membres_soldes);
    $part_par_personne = $nb_membres > 0 ? $total_depenses / $nb_membres : 0;
        //array_column() : extrait une colonne d'un tableau —> ici tous les total_paye
        // array_sum() : additionne toutes ces valeurs
        // $nb_membres > 0 ? ... : 0 : opérateur ternaire —> évite une division par zéro

    // CALCUL DU SOLDE DE CHAQUE PERSONNE : ce qu'il a payé / ce qu'il aurait dû payer
    // Positif = on lui doit de l'argent / Négatif = il doit de l'argent
    foreach ($membres_soldes as &$m) {
        $m['solde'] = $m['total_paye'] - $part_par_personne;
    }
    unset($m);
        //&$m : le & signifie qu'on modifie le tableau original (passage par référence)
        // unset($m) : obligatoire après un foreach par référence pour éviter des bugs si on réutilise $m plus tard
        // Solde positif = ce membre a trop payé, on lui doit de l'argent
        // Solde négatif = il n'a pas assez payé, il doit de l'argent

    // ALGORITHME DE REMBOURSEMENTS
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
        //On sépare les membres en deux listes : créanciers (solde positif) et débiteurs (solde négatif)
        // min() : on rembourse le minimum entre ce que le débiteur doit et ce que le créancier attend
        // On avance dans la liste ($i++ ou $j++) quand un remboursement est soldé
        // 0.01 comme seuil : ignore les différences de centimes dues aux arrondis

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

<!-- PARTIE HTML --> 
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <!-- Permet d'adapter la largeur de l'écran pour la version mobile sans que la page ne s'affiche en tout petit -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title> Dépenses du groupe </title>
        <!-- Charge le framework CSS Bulma depuis internet -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
        <style>
            /* Empêche un scroll horizontal non voulu sur mobile*/ 
            body { overflow-x: hidden; }
        </style>
    </head>
    <body style="background-color: var(--bulma-success-dark);">
        <!-- Header : logo à gauche qui ramène au dashboard, bouton déconnexion à droite --> 
        <header style="background-color: var(--bulma-success-dark); color: white;">
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

                    <!-- TITRE ET LA DEVISE -->
                    <h1 class="title has-text-success-bold has-text-centered">
                        <?= htmlspecialchars($groupe['name']) ?>
                        <!-- ?= ... ?> : raccourci PHP pour ?php echo ... ?> --> 
                        <!--htmlspecialchars() : convertit les caractères spéciaux (<, >, ") en HTML pour éviter des failles XSS si le nom contient du code malveillant-->
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
                                            <!--Formate le montant avec exactement 2 décimales (ex: 45.5 → 45.50)--> 

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
                                                    <!-- strtotime() : convertit la date SQL (2024-03-15) en timestamp -->
                                                    <!-- date('d/m/Y', ...) : reformate ce timestamp en 15/03/2024 -->
                                                    <!-- Si la date est vide, on affiche — -->
                                        </td>
                                        
                                        <td>
                                            <!-- BOUTON MODIFIER --> 
                                             <!-- Le bouton Modifier n'apparaît que si c'est l'utilisateur connecté qui a payé cette dépense —> on ne peut pas modifier la dépense de quelqu'un d'autre -->
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
                    <!--Affiche chaque remboursement calculé par l'algorithme PHP sous forme de notification Bulma. Si $remboursements est vide, on affiche "Tout le monde est quitte !"-->
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
                        
                        <!-- Lien vers la création d'une dépense dans ce groupe -->
                        <a href="formulaire_creation_depenses.php?group_id=<?= $group_id ?>" class="button is-success is-soft is-fullwidth">
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
