<?php
session_start();
require_once 'conexao.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';

    if (!empty($descricao) && $valor > 0 && !empty($data)) {
        $stmt = $pdo->prepare("UPDATE transacoes SET tipo = :tipo, descricao = :descricao, valor = :valor, data = :data WHERE id = :id");
        $stmt->execute([
            'tipo' => $tipo,
            'descricao' => $descricao,
            'valor' => $valor,
            'data' => $data,
            'id' => $id
        ]);
        $_SESSION['mensagem_sucesso'] = "Transação atualizada com sucesso!";
        header('Location: index.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM transacoes WHERE id = :id");
$stmt->execute(['id' => $id]);
$transacao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transacao) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Transação - MyPocket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">Editar Transação #<?= htmlspecialchars((string)$transacao['id']) ?></h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="receita" <?= $transacao['tipo'] === 'receita' ? 'selected' : '' ?>>Entrada (+)</option>
                                <option value="despesa" <?= $transacao['tipo'] === 'despesa' ? 'selected' : '' ?>>Saída (-)</option>
                                <option value="diario" <?= $transacao['tipo'] === 'diario' ? 'selected' : '' ?>>Gasto Diário (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($transacao['descricao']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" name="valor" class="form-control" value="<?= htmlspecialchars((string)$transacao['valor']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= htmlspecialchars($transacao['data']) ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-2">Atualizar</button>
                        <a href="index.php" class="btn btn-secondary w-100">Cancelar</a>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>