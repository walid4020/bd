<?php
define('USER', 'vy44dy72oodv');
define('PASSWD', 'd3-d2d!4oo');
define('SERVER', 'localhost');
define('BASE', 'ebus2_projet04_viiy78'); 

$dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';

try {
    $connexion = new PDO($dsn, USER, PASSWD);
} catch (PDOException $e) {
    echo 'Échec de la connexion : ' . $e->getMessage();
    exit();
}
