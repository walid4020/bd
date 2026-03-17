<?php

//bout de code trouvé sur Moodle (3. PDO => JSON) dans la partie Applications web - PHP
// USER, PASSWD,BASE modifiés avec les données données dans le forum de discussion (sujets techniques)

define('USER','vy44dy72oodv'); 
define('PASSWD','d3-d2d!4oo'); 
define('SERVER','localhost'); 
define('BASE','ebus2_projet04_viiy78'); 

// filet de sécurité pour créer la connexion entre le site et la base de données : 

// 1. Construction de l'identifiant de la source de données (DSN)
$dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE;  
try {
// 2. Tentative d'ouverture de la connexion avec les identifiants
  $connexion = new PDO($dsn, USER, PASSWD);
} catch(PDOException $e) {
// 3. Gestion sécurisée de l'échec de connexion
  echo 'Échec de la connexion : ' . $e->getMessage();
  exit();
}
 