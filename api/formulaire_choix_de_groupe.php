<?php
// Page "Mes groupes" - affiche les groupes de l'utilisateur connecté

// 1. DÉMARRAGE DE LA SESSION
// Nécessaire pour récupérer l'ID de l'utilisateur connecté
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers la page de login
if (!isset($_SESSION['user'])) {
    header('Location: https://divvyo.hepl-e-business.be/index.html');
    exit;
}

// 2. CONNEXION À LA BASE DE DONNÉES
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

// 3. RÉCUPÉRATION DES GROUPES DE L'UTILISATEUR
// On cherche tous les groupes dont l'utilisateur connecté est membre
// via la table group_users qui fait le lien entre users et account_groups
$user_id = $_SESSION['user']['id'];

$stmt = $connexion->prepare("
    SELECT ag.id, ag.name, ag.description, ag.currency
    FROM account_groups ag
    INNER JOIN group_users gu ON gu.account_group_id = ag.id
    WHERE gu.user_id = :user_id
    ORDER BY ag.created_at DESC
");
$stmt->execute(['user_id' => $user_id]);
$groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title> Mes groupes </title>
        <!-- CSS : framework Bulma -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body>
        <section class="section">
            <div class="container" style="max-width: 500px;">
                <div class="box">

                    <h1 class="has-text-centered title has-text-primary-bold"> Mes groupes de dépense </h1>

                    <?php if (empty($groupes)): ?>
                        <!-- Affiché si l'utilisateur n'appartient à aucun groupe -->
                        <p class="has-text-centered has-text-grey">
                            Vous n'avez pas encore de groupe.<br>
                            <a href="formulaire_creation_groupe.html">Créer un groupe</a>
                        </p>

                    <?php else: ?>
                        <!-- Formulaire de sélection d'un groupe -->
                        <!-- action : page qui affichera les dépenses du groupe sélectionné -->
                        <form action="../api/consultation_groupe.php" method="post">

                            <div class="field">
                                <label class="label" for="id_selection_de_groupe">
                                    Quel groupe souhaitez-vous consulter ?
                                </label>
                            </div>

                            <div class="control">
                                <div class="select is-fullwidth">
                                    <!-- Boucle PHP : on génère une option par groupe trouvé en BD -->
                                    <select name="group_id" id="id_selection_de_groupe" required>
                                        <option value="">-- Veuillez choisir un groupe --</option>
                                        <?php foreach ($groupes as $groupe): ?>
                                            <!-- value = ID du groupe en BD, texte = nom du groupe -->
                                            <option value="<?= htmlspecialchars($groupe['id']) ?>">
                                                <?= htmlspecialchars($groupe['name']) ?> (<?= htmlspecialchars($groupe['currency']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="button is-primary is-fullwidth has-text-white mt-4">
                                Afficher les dépenses du groupe
                            </button>

                        </form>
                    <?php endif; ?>

                    <!-- Lien retour vers la page d'accueil -->
                    <br>
                    <p class="has-text-centered">
                        <a href="../index.html">← Retour à l'accueil</a>
                    </p>

                </div>
            </div>
        </section>
    </body>
</html>
