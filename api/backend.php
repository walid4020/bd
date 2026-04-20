// réalisé suite à la séance 6 d'application web 

<?php
session_start();

// 1. CONFIGURATION DE LA BASE DE DONNÉES
define('USER','vy44dy72oodv'); 
define('PASSWD','d3-d2d!4oo'); 
define('SERVER','localhost'); 
define('BASE','ebus2_projet04_viiy78'); 

// Connexion à la base de données
try {
    // Construction de l'identifiant de la source de données (DNS) 
    $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
    // Tentative d'ouverture de la connexion avec les identifiants 
    $connexion = new PDO($dsn, USER, PASSWD);
} catch (PDOException $e) {
    // Gestion sécurisée de l'échec de connexion 
    echo json_encode(['error' => 'Échec de la connexion à la base de données']);
    http_response_code(500);
    exit;
}

// 2. VÉRIFICATION DE L'ACTION
if (!isset($_GET['action'])) {
    echo json_encode(['error' => 'Action manquante']);
    http_response_code(422);
    exit;
}
// MÉTHODE GET (Vérification de session)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['action'] == 'whoami') {
        if (isset($_SESSION['user'])) {
            echo json_encode([
                'loggedIn' => true,
                'user'     => $_SESSION['user'],
            ]);
        } else {
            // Gestion du cookie "remember me"
            $remembered = $_COOKIE['remember_user'] ?? null;
            echo json_encode([
                'loggedIn'        => false,
                'remembered_user' => $remembered,
            ]);
        }
        http_response_code(200);
        exit;
    }
}

// --- MÉTHODE POST (Login / Logout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION : LOGIN
    if($_GET['action'] === 'login') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            echo json_encode(['error' => 'Champs manquants']);
            http_response_code(422);
            exit;
        }

        $email = trim($data['email']);
        $password = $data['password'];
        $remember = $data['remember'];


        // RECHERCHE EN BASE DE DONNÉES -> autre code que dans le cours (vérifier (3 lignes en dessous))
        $stmt = $connexion->prepare("SELECT * FROM utilisateurs WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user_from_db = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification si l'utilisateur existe et si le mot de passe correspond
        password_verify()
        if (!$user_from_db || $user_from_db['password'] !== $password) {
            sleep(1); /// Délai volontaire pour limiter le brute-force (incrémental ce serait mieux)
            echo json_encode(['error' => 'Identifiants incorrects']);
            http_response_code(401);
            exit;
        }

        // Création de la session avec les vraies données de ta DB
            $_SESSION['user'] = [
            'id'          => $user_from_db['id'],
            'email'       => $user_from_db['email'],
            'displayName' => $user_from_db['prenom'] . ' ' . $user_from_db['nom'],
            'loginAt'     => date('d-m-Y H:i:s')
             ];

        // Gestion du cookie persistant
        if ($remember) {
            $expires = time() + (30 * 24 * 60 * 60); // 30 jours
            setcookie(
                'remember_user', 
                [
                'expires' => $expires,
                'path' => '/'
            ]);
        }

        echo json_encode([
            'success' => true, 
            'user' => $_SESSION['user']]);
        http_response_code(200);
        exit;
    }
// arret ici 
    // ACTION : LOGOUT
    if ($_GET['action'] === 'logout') {
        $_SESSION = []; 
        session_destroy();

        // Suppression des cookies de la session du navigateur
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', // valeur vide
                 time() - 42000, // date dans le passé => supression 
                 $params['path']);
        }
        // Suppression du cookie "rememner me"
        setcookie(
            'remember_user', 
            '', 
            time() - 42000,
             '/');

        echo json_encode(['success' => true, 'message' => 'Déconnecté avec succès']);
        http_response_code(200);
        exit;
    }
}

// Si aucune condition n'est remplie
http_response_code(405);
?>

