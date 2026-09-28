<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';

    if (!empty($descricao) && $valor > 0 && !empty($data)) {
        $stmt = $pdo->prepare("INSERT INTO transacoes (tipo, descricao, valor, data) VALUES (:tipo, :descricao, :valor, :data)");
        $stmt->execute([
            'tipo' => $tipo,
            'descricao' => $descricao,
            'valor' => $valor,
            'data' => $data
        ]);
        $_SESSION['mensagem_sucesso'] = "Lançamento registrado com sucesso!";
    } else {
        $_SESSION['mensagem_erro'] = "Preencha todos os campos corretamente.";
    }
}

header('Location: index.php');
exit;