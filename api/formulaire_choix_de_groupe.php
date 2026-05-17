<!-- PARTIE PHP --> 
<?php
    // Page "Mes groupes" - affiche les groupes de l'utilisateur connecté

    // DÉMARRAGE DE LA SESSION
    // Nécessaire pour récupérer l'ID de l'utilisateur connecté
    session_start();

    // Si l'utilisateur n'est pas connecté, on le redirige vers la page de login
    if (!isset($_SESSION['user'])) {
        header('Location: https://divvyo.hepl-e-business.be/index.html');
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

    // RÉCUPÉRATION DES GROUPES DE L'UTILISATEUR
    $user_id = $_SESSION['user']['id'];

    $stmt = $connexion->prepare("
        SELECT ag.id, ag.name, ag.description, ag.currency
        FROM account_groups ag
        INNER JOIN group_users gu ON gu.account_group_id = ag.id
        WHERE gu.user_id = :user_id
        ORDER BY ag.created_at DESC
    ");
    /* requête SQL : Sélectionne l'id, le name, la description et la currency depuis la table account_groups, 
    fais une jointure avec group_users où l'account_group_id de group_users correspond à l'id de account_groups, 
    filtre pour ne garder que les groupes où l'user_id correspond à celui de l'utilisateur connecté, et trie les résultats par 
    date de création du plus récent au plus ancien. */ 
    $stmt->execute(['user_id' => $user_id]);
    $groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- PARTIE HTML --> 
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <!-- Permet d'adapter la largeur de l'écran pour la version mobile sans que la page ne s'affiche en tout petit -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title> Mes groupes </title>
        <!-- Charge le framework CSS Bulma depuis internet -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    </head>
    <body style="background-color: var(--bulma-success-dark);">
            <!-- En-tête : logo à gauche, bouton déconnexion à droite -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem;">
                <a href="dashboard.php">
                    <img src="../assets/logo.png" alt="Divvyo" style="max-height: 50px;">
                </a>
                <!-- Bouton pour se déconnexter à droite --> 
                <a href="deconnexion.php" class="button is-danger is-light is-small">Se déconnecter</a>
            </div>
        </script>

            <section class="section" style="min-height: 100vh;">
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
                            <div class="buttons is-flex is-flex-direction-column">
                                <?php foreach ($groupes as $groupe): ?>
                                    <!-- Chaque groupe est un bouton cliquable qui mène directement à ses dépenses -->
                                    <a href="consultation_groupe.php?group_id=<?= $groupe['id'] ?>" 
                                    class="button is-success is-soft is-fullwidth" 
                                    style="height: auto; padding: 15px; justify-content: flex-start;">
                                        <div>
                                            <strong><?= htmlspecialchars($groupe['name']) ?></strong>
                                            <br>
                                            <small><?= htmlspecialchars($groupe['description']) ?> — <?= htmlspecialchars($groupe['currency']) ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lien retour vers la page d'accueil -->
                        <br>
                        <p class="has-text-centered">
                            <a href="dashboard.php">← Retour à l'accueil</a>
                        </p>

                    </div>
                </div>
            </section>
    </body>
</html>
