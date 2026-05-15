<?php
    //api/backend.php = fichier central qui gère toute l'authentification de l'app : reçoit les requêtes de app.js et traite trois actions : la connexion (login), la déconnexion (logout) et la vérification de session.
    session_start();
    
    // CONFIGURATION DE LA BASE DE DONNEES
        // code repris de Moodle 3.PDO => JSON
    define('USER','vy44dy72oodv'); 
    define('PASSWD','d3-d2d!4oo'); 
    define('SERVER','localhost'); 
    define('BASE','ebus2_projet04_viiy78'); 

    // 1. CONNEXION A LA BASE DE DONNEES 
    try {
        // Construction de l'identifiant de la source de données (DNS) 
        $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
        // Tentative d'ouverture de la connexion avec les identifiants 
        $connexion = new PDO($dsn, USER, PASSWD);
    } catch (PDOException $e) {
        // Gestion sécurisée de l'échec de connexion 
        echo json_encode(['error' => 'Échec de la connexion à la base de données']);
        http_response_code(500);
        // code 500 : erreur interne du serveur
        exit;
    }

    // 2. VÉRIFICATION DE L'ACTION
        // L'action arrive dans l'URL : backend.php?action=login. Code 422 = "requête mal formée". Si aucune action n'est précisée, on arrête tout.
    if (!isset($_GET['action'])) {
        echo json_encode(['error' => 'Action manquante']);
        http_response_code(422);
        exit;
    }
    
    // MÉTHODE GET (Vérification de session)
        // Appelé automatiquement par app.js au chargement de la page pour savoir si l'utilisateur est déjà connecté.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($_GET['action'] == 'whoami') {
            //Si $_SESSION['user'] existe → renvoie loggedIn: true avec les infos de l'utilisateur
            if (isset($_SESSION['user'])) {
                echo json_encode([
                    'loggedIn' => true,
                    'user'     => $_SESSION['user'],
                ]);
            } else {
                // Sinon → vérifie s'il y a un cookie remember_user et le renvoie 
                // ?? = opérateur "null coalescing" —> si le cookie n'existe pas, $remembered est null
                $remembered = $_COOKIE['remember_user'] ?? null;
                echo json_encode([
                    'loggedIn'        => false,
                    'remembered_user' => $remembered,
                ]);
            }
            http_response_code(200);
            // code 200 : succès 
            exit;
        }
    }

    // MÉTHODE POST (Login / Logout)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ACTION : LOGIN
        if($_GET['action'] === 'login') {
            //Vue.js envoie les données en JSON dans le corps de la requête. php://input lit lit corps brut, et json_decode() le convertit en tableau PHP.
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['email']) || !isset($data['password'])) {
            //isset() vérifie qu'une variable existe et n'est pas null. ! inverse la condition —> donc si l'email ou le mot de passe est absent, on renvoie une erreur et on arrête avec exit

                echo json_encode(['error' => 'Champs manquants']);
                http_response_code(422);
                // code 422 : requête mal formée (données manquantes)
                exit;
            }
            // Récupération et nettoyage des données
            $email = trim($data['email']);
            //trim() : supprime les espaces accidentels au début et à la fin
            $password = $data['password'];
            $remember = $data['remember'];
            //$remember : vaut true ou false selon si la case "Se souvenir de moi" était cochée dans Vue.js


            // RECHERCHE EN BASE DE DONNÉES
                //On cherche l'utilisateur par son email. fetch() retourne une seule ligne (ou false si non trouvé)
            $stmt = $connexion->prepare("SELECT * FROM users WHERE email_address = :email");
            $stmt->execute(['email' => $email]);
            $user_from_db = $stmt->fetch(PDO::FETCH_ASSOC);

            // VERIFICATION DU MDP
            if (!$user_from_db || !password_verify($password, $user_from_db['password_hash'])) {
                //password_verify() : compare le mdp tapé avec le hash stocké en BD —> on ne stocke jamais un mot de passe en clair
                sleep(1); /// Délai volontaire pour limiter le brute-force (incrémental ce serait mieux)
                echo json_encode(['error' => 'Identifiants incorrects']);
                http_response_code(401);
                //Code 401 : "non autorisé"
                exit;
            }

            // CREATION DE LA SESSION
            // On stocke les infos de l'utilisateur dans la session PHP. C'est ce tableau qu'on retrouve dans tous les autres fichiers avec $_SESSION['user']
                $_SESSION['user'] = [
                'id'          => $user_from_db['id'],
                'email'       => $user_from_db['email_address'],
                'displayName' => $user_from_db['first_name'] . ' ' . $user_from_db['last_name'],
                'loginAt'     => date('d-m-Y H:i:s')
                ];

            // COOKIE "Se souvenir de moi" 
            if ($remember) {
                $expires = time() + (30 * 24 * 60 * 60); // 30 jours
                setcookie('remember_user', $user_from_db['first_name'], [
                    'expires' => $expires,
                    'path' => '/'
                    //setcookie() : crée un cookie dans le navigateur qui expire dans 30 jours
                ]);
            }

            echo json_encode([
                'success' => true, 
                'user' => $_SESSION['user']]);
            http_response_code(200);
            // code 200 : succès 
            exit;
        }
    // arret ici 

        // ACTION : LOGOUT
        if ($_GET['action'] === 'logout') {
            $_SESSION = []; 
            session_destroy();
            //$_SESSION = [] : vide toutes les variables de session, session_destroy() : détruit la session côté serveur

            // SUPPRESSION DES COOKIES DE LA SESSION 
            // Pour supprimer un cookie, on lui donne une date d'expiration dans le passé (time() - 42000). Le navigateur le supprime automatiquement. 
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), 
                    '', // valeur vide
                    time() - 42000, // date dans le passé => supression 
                    $params['path']);
            }
            // SUPPRESSION DU COOKIE "rememner me"
            // On l'écrase avec une valeur vide et une date passée comme pour le cookie de la session
            setcookie(
                'remember_user', 
                '', 
                time() - 42000,
                '/');

            echo json_encode(['success' => true, 'message' => 'Déconnecté avec succès']);
            http_response_code(200);
            // code 200 : succès 
            exit;
        }
    }

    // Si aucune condition n'est remplie
    http_response_code(405);
    // code 405 :  Method Not Allowed : la méthode HTTP utilisée n'est pas autorisée (requête ni POST ni GET)
?>

