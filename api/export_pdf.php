<?php
// Démarrage de la session
session_start();

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    die('Accès refusé');
}

// Vérifie si l'id du groupe existe
if (!isset($_GET['group_id'])) {
    die('Groupe introuvable');
}

// Librairie FPDF
require('fpdf/fpdf.php');

// Connexion base de données
define('USER', 'vy44dy72oodv');
define('PASSWD', 'd3-d2d!4oo');
define('SERVER', 'localhost');
define('BASE', 'ebus2_projet04_viiy78');

try {
    $dsn = 'mysql:host=' . SERVER . ';dbname=' . BASE . ';charset=utf8';
    $connexion = new PDO($dsn, USER, PASSWD);

} catch (PDOException $e) {

    die('Erreur connexion');
}

// Récupération de l'id du groupe
$group_id = (int) $_GET['group_id'];

// Récupération des informations du groupe
$stmt_groupe = $connexion->prepare("
    SELECT name, currency
    FROM account_groups
    WHERE id = :group_id
");

$stmt_groupe->execute([
    'group_id' => $group_id
]);

$groupe = $stmt_groupe->fetch(PDO::FETCH_ASSOC);

// Récupération des dépenses
$stmt_depenses = $connexion->prepare("
    SELECT
        e.description,
        e.amount,
        e.expense_date,
        u.first_name,
        u.last_name

    FROM expenses e

    LEFT JOIN users u
    ON u.id = e.payer_id

    WHERE e.account_group_id = :group_id
");

$stmt_depenses->execute([
    'group_id' => $group_id
]);

$depenses = $stmt_depenses->fetchAll(PDO::FETCH_ASSOC);

//Création pdf

$pdf = new FPDF();

$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);

// Titre
$pdf->Cell(0, 10, 'Depenses du groupe', 0, 1, 'C');

$pdf->Ln(5);

// Nom du groupe
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(
    0,
    10,
    'Groupe : ' . utf8_decode($groupe['name']),
    0,
    1
);

$pdf->Ln(5);

// Liste des dépenses

foreach ($depenses as $depense) {

    $texte =
        $depense['description']
        . ' - '
        . number_format($depense['amount'], 2)
        . ' '
        . $groupe['currency']
        . ' - '
        . $depense['first_name']
        . ' '
        . $depense['last_name'];

    $pdf->Cell(
        0,
        10,
        utf8_decode($texte),
        0,
        1
    );
}

// Téléchargement
$pdf->Output(
    'D',
    'depenses_groupe.pdf'
);

exit;
?>