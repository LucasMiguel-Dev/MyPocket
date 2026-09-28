<?php
require_once 'conexao.php';

$id = $_GET['id'] ?? null;

if ($id) {
    // D - DELETE: Remove a transação do banco de dados
    $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

// Volta para a página principal
header('Location: index.php');
exit;
?>