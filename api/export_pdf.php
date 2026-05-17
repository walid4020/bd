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

// message d'erreur 
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
// requête SQL : Sélectionne le name et la currency depuis la table account_groups où l'id est égal à l'id du groupe

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
/* requête SQL : Sélectionne la description, le amount, la expense_date depuis la table expenses, ainsi que le 
first_name et le last_name depuis la table users, fais une jointure avec users pour relier chaque dépense à la personne 
qui a payé via le payer_id, filtre pour ne garder que les dépenses de ce groupe */ 

$stmt_depenses->execute([
    'group_id' => $group_id
]);

$depenses = $stmt_depenses->fetchAll(PDO::FETCH_ASSOC);

// Création PDF
$pdf = new FPDF(); // Crée un nouveau document PDF

$pdf->AddPage(); // Ajoute une page au PDF

// Choix de la police : Arial, gras, taille 18
$pdf->SetFont('Arial', 'B', 18);

// Création du titre centré
$pdf->Cell(0, 10, 'Recapitulatif des depenses', 0, 1, 'C');

// Ajoute un espace vertical
$pdf->Ln(8);

// Police pour les informations du groupe
$pdf->SetFont('Arial', 'B', 12);

// Affiche le nom du groupe
$pdf->Cell(0, 8, 'Groupe : ' . utf8_decode($groupe['name']), 0, 1);

// Affiche la devise du groupe
$pdf->Cell(0, 8, 'Devise : ' . utf8_decode($groupe['currency']), 0, 1);

// Ajoute un espace
$pdf->Ln(8);

// Création Tableau

// Police du tableau
$pdf->SetFont('Arial', 'B', 11);

// Création des colonnes du tableau
$pdf->Cell(70, 10, 'Description', 1);
$pdf->Cell(35, 10, 'Montant', 1);
$pdf->Cell(50, 10, 'Paye par', 1);
$pdf->Cell(35, 10, 'Date', 1);

// Retour à la ligne
$pdf->Ln();

// Police normale pour le contenu
$pdf->SetFont('Arial', '', 10);

// Variable qui sert à calculer le total
$total = 0;

// Boucle sur toutes les dépenses
foreach ($depenses as $depense) {

    // Récupération de la description
    $description = utf8_decode($depense['description']);

    //Montant avec 2 décimales
    $montant = number_format($depense['amount'], 2)
        . ' '
        . $groupe['currency'];

    // Nom de la personne qui a payé
    $payeur = utf8_decode(
        $depense['first_name']
        . ' '
        . $depense['last_name']
    );

    // Formatage de la date
    $date = $depense['expense_date']
        ? date('d/m/Y', strtotime($depense['expense_date']))
        : '-';

    // Affichage de la description
    $pdf->Cell(70, 10, substr($description, 0, 35), 1);

    // Affichage du montant
    $pdf->Cell(35, 10, $montant, 1);

    // Affichage du payeur
    $pdf->Cell(50, 10, substr($payeur, 0, 25), 1);

    // Affichage de la date
    $pdf->Cell(35, 10, $date, 1);

    // Retour à la ligne
    $pdf->Ln();

    // Ajout du montant au total
    $total += $depense['amount'];
}

// Espace avant le total
$pdf->Ln(8);

// Police du total
$pdf->SetFont('Arial', 'B', 12);

// Affiche le total des dépenses aligné à droite
$pdf->Cell(
    0,
    10,
    'Total : '
    . number_format($total, 2)
    . ' '
    . $groupe['currency'],
    0,
    1,
    'R'
);

// Télécharge du PDF
$pdf->Output('D', 'depenses_groupe.pdf');

exit;
?>