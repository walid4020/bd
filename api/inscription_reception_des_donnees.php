<?php
// Réception des données du formulaire d'inscription

// 1. CONNEXION À LA BASE DE DONNÉES
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

// 2. RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
$prenom   = trim($_POST['firstname'] ?? '');
$nom      = trim($_POST['lastname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 3. VALIDATIONS
if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
    die('Veuillez remplir tous les champs obligatoires.');
}

if ($password !== $password_confirm) {
    die('Les mots de passe ne correspondent pas.');
}

if (strlen($password) < 8) {
    die('Le mot de passe doit contenir au moins 8 caractères.');
}

// 4. VÉRIFIER QUE L'EMAIL N'EST PAS DÉJÀ UTILISÉ
$stmt = $connexion->prepare("SELECT id FROM utilisateurs WHERE email = :email");
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    die('Cet email est déjà utilisé.');
}

// 5. HASHER LE MOT DE PASSE
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 6. INSERTION EN BASE DE DONNÉES
$stmt = $connexion->prepare("
    INSERT INTO utilisateurs (prenom, nom, email, password)
    VALUES (:prenom, :nom, :email, :password)
");
$stmt->execute([
    'prenom'   => $prenom,
    'nom'      => $nom,
    'email'    => $email,
    'password' => $password_hash,
]);

// 7. REDIRECTION VERS LA PAGE DE CONNEXION
header('Location: ../index.html');
exit;
?>
