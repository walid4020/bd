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
// trim() supprime les espaces inutiles en début/fin de chaîne
// ?? '' signifie : si le champ n'existe pas dans $_POST, on met une chaîne vide par défaut
$first_name   = trim($_POST['firstname'] ?? '');
$last_name    = trim($_POST['lastname'] ?? '');
$birth_date   = trim($_POST['birthdate'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$country      = trim($_POST['country'] ?? '');
$email        = trim($_POST['email'] ?? '');
$password     = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? ''; // champ "confirmer le mot de passe"

// 3. VALIDATIONS
// Vérifie que tous les champs obligatoires sont bien remplis
if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($birth_date) || empty($phone_number) || empty($country)) {
    die('Veuillez remplir tous les champs obligatoires.');
}

// Vérifie que les deux mots de passe saisis sont identiques
if ($password !== $password_confirm) {
    die('Les mots de passe ne correspondent pas.');
}

// Vérifie que le mot de passe fait au moins 8 caractères
if (strlen($password) < 8) {
    die('Le mot de passe doit contenir au moins 8 caractères.');
}

// 4. VÉRIFIER QUE L'EMAIL N'EST PAS DÉJÀ UTILISÉ
// On fait une requête en BD pour voir si un utilisateur avec cet email existe déjà
$stmt = $connexion->prepare("SELECT id FROM users WHERE email_address = :email");
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    die('Cet email est déjà utilisé.');
}

// 5. HASHER LE MOT DE PASSE
// password_hash() transforme le mot de passe en une chaîne illisible pour la BD
// On ne stocke JAMAIS un mot de passe en clair — c'est une règle de sécurité de base
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 6. INSERTION EN BASE DE DONNÉES
// On insère le nouvel utilisateur avec toutes ses données dans la table "users"
// Les :variables sont des paramètres liés — ça protège contre les injections SQL
$stmt = $connexion->prepare("
    INSERT INTO users (first_name, last_name, birth_date, phone_number, country, email_address, password_hash)
    VALUES (:first_name, :last_name, :birth_date, :phone_number, :country, :email, :password_hash)
");
$stmt->execute([
    'first_name'    => $first_name,
    'last_name'     => $last_name,
    'birth_date'    => $birth_date,
    'phone_number'  => $phone_number,
    'country'       => $country,
    'email'         => $email,
    'password_hash' => $password_hash, // mot de passe hashé, jamais le mot de passe original
]);

// 7. REDIRECTION VERS LA PAGE DE CONNEXION
// Une fois l'inscription réussie, on renvoie l'utilisateur vers la page de login
// On utilise une URL absolue car les chemins relatifs ne fonctionnent pas toujours avec header()
header('Location: https://divvyo.hepl-e-business.be/index.html');
exit;
?>
