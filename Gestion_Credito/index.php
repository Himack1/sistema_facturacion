<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

// Obtener totales para tarjetas
$sql_resumen = "
    SELECT 
        SUM(credito_consumido) as total_por_cobrar,
        COUNT(id) as clientes_con_deuda
    FROM clientes 
    WHERE credito_consumido > 0 AND estado = 'activo'
";
$stmt_resumen = sqlsrv_query($conn, $sql_resumen);
$resumen = sqlsrv_fetch_array($stmt_resumen, SQLSRV_FETCH_ASSOC);

$total_por_cobrar = $resumen['total_por_cobrar'] ?? 0;
$clientes_con_deuda = $resumen['clientes_con_deuda'] ?? 0;

// Obtener pagos del mes actual
$sql_pagos = "
    SELECT SUM(monto) as cobrado_mes 
    FROM pagos_credito 
    WHERE MONTH(fecha_pago) = MONTH(GETDATE()) AND YEAR(fecha_pago) = YEAR(GETDATE())
";
$stmt_pagos = sqlsrv_query($conn, $sql_pagos);
$pagos_mes = sqlsrv_fetch_array($stmt_pagos, SQLSRV_FETCH_ASSOC)['cobrado_mes'] ?? 0;

// Obtener lista de clientes con deuda
$sql_clientes = "
    SELECT id, nombre, documento, telefono, limite_credito, credito_consumido, credito_disponible
    FROM clientes 
    WHERE credito_consumido > 0 
    ORDER BY credito_consumido DESC
";
$stmt_clientes = sqlsrv_query($conn, $sql_clientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Créditos - Sistema de Facturación</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-volver:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        /* Cards */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .card-stat { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-left: 5px solid #4f46e5; display: flex; align-items: center; justify-content: space-between; }
        .card-stat.warning { border-left-color: #f59e0b; }
        .card-stat.success { border-left-color: #10b981; }
        .stat-info h3 { color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .stat-info p { color: #0f172a; font-size: 28px; font-weight: 700; }
        .stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .card-stat .stat-icon { background: #e0e7ff; color: #4f46e5; }
        .card-stat.warning .stat-icon { background: #fef3c7; color: #f59e0b; }
        .card-stat.success .stat-icon { background: #d1fae5; color: #10b981; }
        
        /* Table */
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { color: #0f172a; font-size: 18px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 16px 24px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        tr:hover { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }
        
        .client-name { font-weight: 600; color: #0f172a; display: block; }
        .client-doc { font-size: 12px; color: #64748b; }
        
        .amount { font-weight: 600; font-family: monospace; font-size: 15px; }
        .amount.debt { color: #ef4444; }
        
        /* Progress Bar */
        .progress-wrapper { width: 100%; }
        .progress-text { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; color: #64748b; }
        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
        
        .btn-action { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; color: #475569; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .btn-action:hover { background: #e2e8f0; color: #0f172a; }
        .btn-pay { background: #4f46e5; color: white; }
        .btn-pay:hover { background: #4338ca; color: white; }
    </style>
</head>
<body>
    <header class="header">
        <h1>💳 Gestión de Créditos y Cobros</h1>
        <a href="../dashboard.php" class="btn-volver">← Volver al Inicio</a>
    </header>

    <div class="container">
        <div class="cards-grid">
            <div class="card-stat warning">
                <div class="stat-info">
                    <h3>Total por Cobrar</h3>
                    <p>RD$ <?php echo number_format($total_por_cobrar, 2); ?></p>
                </div>
                <div class="stat-icon">📈</div>
            </div>
            
            <div class="card-stat success">
                <div class="stat-info">
                    <h3>Cobrado este Mes</h3>
                    <p>RD$ <?php echo number_format($pagos_mes, 2); ?></p>
                </div>
                <div class="stat-icon">💰</div>
            </div>
            
            <div class="card-stat">
                <div class="stat-info">
                    <h3>Clientes con Deuda</h3>
                    <p><?php echo $clientes_con_deuda; ?></p>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Cuentas por Cobrar</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Estado de Crédito</th>
                        <th>Saldo Pendiente</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if($stmt_clientes && sqlsrv_has_rows($stmt_clientes)): 
                        while($cliente = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)): 
                            $limite = floatval($cliente['limite_credito']);
                            $consumido = floatval($cliente['credito_consumido']);
                            $porcentaje = $limite > 0 ? ($consumido / $limite) * 100 : 0;
                            
                            $color_barra = '#10b981'; // Verde
                            if($porcentaje > 80) $color_barra = '#ef4444'; // Rojo
                            else if($porcentaje > 50) $color_barra = '#f59e0b'; // Naranja
                    ?>
                    <tr>
                        <td>
                            <span class="client-name"><?php echo htmlspecialchars($cliente['nombre']); ?></span>
                            <span class="client-doc">Doc: <?php echo htmlspecialchars($cliente['documento']); ?> | Tel: <?php echo htmlspecialchars($cliente['telefono']); ?></span>
                        </td>
                        <td style="width: 30%;">
                            <div class="progress-wrapper">
                                <div class="progress-text">
                                    <span>Consumido: <?php echo number_format($porcentaje, 1); ?>%</span>
                                    <span>Límite: RD$ <?php echo number_format($limite, 2); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min($porcentaje, 100); ?>%; background-color: <?php echo $color_barra; ?>"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="amount debt">RD$ <?php echo number_format($consumido, 2); ?></span>
                        </td>
                        <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                            <a href="estado_cuenta.php?cliente_id=<?php echo $cliente['id']; ?>" class="btn-action">📄 Estado de Cuenta</a>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">
                            <div style="font-size: 40px; margin-bottom: 10px;">🎉</div>
                            No hay clientes con saldos pendientes actualmente.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
