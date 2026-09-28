<?php
session_start();
require_once 'conexao.php';

$erro = $_SESSION['mensagem_erro'] ?? null;
$sucesso = $_SESSION['mensagem_sucesso'] ?? null;
unset($_SESSION['mensagem_erro'], $_SESSION['mensagem_sucesso']);

// Define Mês e Ano selecionados (padrão: mês/ano atual)
$mesSelecionado = (int)($_GET['mes'] ?? date('m'));
$anoSelecionado = (int)($_GET['ano'] ?? date('Y'));

// Nomes dos meses em português
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// 1. Buscar Saldo Anterior (Soma de tudo antes do mês selecionado)
$dataInicioMes = sprintf('%04d-%02d-01', $anoSelecionado, $mesSelecionado);
$stmtAnterior = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) -
        SUM(CASE WHEN tipo IN ('despesa', 'diario') THEN valor ELSE 0 END) as saldo_anterior
    FROM transacoes 
    WHERE data < :data_inicio
");
$stmtAnterior->execute(['data_inicio' => $dataInicioMes]);
$saldoAcumulado = (float)($stmtAnterior->fetch(PDO::FETCH_ASSOC)['saldo_anterior'] ?? 0);

// 2. Buscar todas as transações do mês selecionado
$stmtMes = $pdo->prepare("
    SELECT * FROM transacoes 
    WHERE MONTH(data) = :mes AND YEAR(data) = :ano 
    ORDER BY data ASC, id ASC
");
$stmtMes->execute(['mes' => $mesSelecionado, 'ano' => $anoSelecionado]);
$transacoesMes = $stmtMes->fetchAll(PDO::FETCH_ASSOC);

// Agrupar lançamentos por dia
$diasDoMes = date('t', strtotime($dataInicioMes));
$dadosPorDia = [];
for ($d = 1; $d <= $diasDoMes; $d++) {
    $dadosPorDia[$d] = ['entrada' => 0, 'saida' => 0, 'diario' => 0];
}

$totaisMes = ['entrada' => 0, 'saida' => 0, 'diario' => 0];

foreach ($transacoesMes as $t) {
    $dia = (int)date('d', strtotime($t['data']));
    if ($t['tipo'] === 'receita') {
        $dadosPorDia[$dia]['entrada'] += $t['valor'];
        $totaisMes['entrada'] += $t['valor'];
    } elseif ($t['tipo'] === 'despesa') {
        $dadosPorDia[$dia]['saida'] += $t['valor'];
        $totaisMes['saida'] += $t['valor'];
    } elseif ($t['tipo'] === 'diario') {
        $dadosPorDia[$dia]['diario'] += $t['valor'];
        $totaisMes['diario'] += $t['valor'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyPocket - Controle Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid py-4 px-4">
    <h1 class="text-center mb-4">💳 MyPocket - Controle Financeiro</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($erro) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Seletor de Mês e Ano -->
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="fw-bold">Mês:</label>
                <select name="mes" class="form-select w-auto" onchange="this.form.submit()">
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $num === $mesSelecionado ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="fw-bold ms-2">Ano:</label>
                <input type="number" name="ano" value="<?= $anoSelecionado ?>" class="form-control w-auto" onchange="this.form.submit()">
            </form>
            <div>
                <span class="text-muted me-2">Saldo Inicial do Mês: <strong>R$ <?= number_format($saldoAcumulado, 2, ',', '.') ?></strong></span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Coluna Formulário -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">Novo Lançamento</h5>
                    <form action="processa.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="receita">Entrada (+)</option>
                                <option value="despesa">Saída (-)</option>
                                <option value="diario">Gasto Diário (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Salário, Aluguel, Refeição" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" name="valor" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Registrar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna Tabela no formato da Planilha (Diário 1 a 31) -->
        <div class="col-lg-9">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h5 class="mb-0">Planilha do Mês: <?= $meses[$mesSelecionado] ?> / <?= $anoSelecionado ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-striped table-hover table-sm text-center mb-0 align-middle">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Dia</th>
                                    <th>Entrada</th>
                                    <th>Saída</th>
                                    <th>Diário</th>
                                    <th>Saldo Acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $saldoDia = $saldoAcumulado;
                                for ($d = 1; $d <= $diasDoMes; $d++): 
                                    $e = $dadosPorDia[$d]['entrada'];
                                    $s = $dadosPorDia[$d]['saida'];
                                    $di = $dadosPorDia[$d]['diario'];
                                    $saldoDia += ($e - $s - $di);
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= $d ?></td>
                                        <td class="<?= $e > 0 ? 'text-success fw-bold' : 'text-muted' ?>"><?= $e > 0 ? 'R$ ' . number_format($e, 2, ',', '.') : '-' ?></td>
                                        <td class="<?= $s > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= $s > 0 ? 'R$ ' . number_format($s, 2, ',', '.') : '-' ?></td>
                                        <td class="<?= $di > 0 ? 'text-warning fw-bold text-dark' : 'text-muted' ?>"><?= $di > 0 ? 'R$ ' . number_format($di, 2, ',', '.') : '-' ?></td>
                                        <td class="fw-bold <?= $saldoDia >= 0 ? 'text-primary' : 'text-danger' ?>">
                                            R$ <?= number_format($saldoDia, 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Lista de Lançamentos com Opção de Editar / Excluir -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">Lançamentos Detalhados do Mês</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transacoesMes)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">Nenhum lançamento neste mês.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($transacoesMes as $t): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                                            <td><?= htmlspecialchars($t['descricao']) ?></td>
                                            <td>
                                                <?php if ($t['tipo'] === 'receita'): ?>
                                                    <span class="badge bg-success">Entrada</span>
                                                <?php elseif ($t['tipo'] === 'despesa'): ?>
                                                    <span class="badge bg-danger">Saída</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Diário</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold <?= $t['tipo'] === 'receita' ? 'text-success' : 'text-danger' ?>">
                                                R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <a href="editar.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                <a href="deletar.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja excluir?')">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>