<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Consulta base
$sql = "SELECT r.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono, u.nombre as receptor_nombre 
        FROM reparaciones r 
        JOIN clientes c ON r.cliente_id = c.id 
        JOIN usuarios u ON r.usuario_id = u.id 
        WHERE r.archivado = 1";
$params = array();

if ($filtro_estado !== '') {
    $sql .= " AND r.estado = ?";
    $params[] = $filtro_estado;
}
if ($filtro_busqueda !== '') {
    $sql .= " AND (r.codigo_ticket LIKE ? OR r.serial_imei LIKE ? OR c.nombre LIKE ?)";
    $busqueda_param = '%' . $filtro_busqueda . '%';
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql .= " ORDER BY r.fecha_recepcion DESC";
$stmt = sqlsrv_query($conn, $sql, $params);

$estados_badge = [
    'Recibido' => '#6b7280', // Gray
    'En Revisión' => '#f59e0b', // Yellow
    'Esperando Pieza' => '#ef4444', // Red
    'Reparado' => '#10b981', // Green
    'No Reparable' => '#1f2937', // Dark Gray
    'Entregado' => '#3b82f6' // Blue
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicio Técnico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .actions-bar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; }
        .btn-nuevo { background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; border: none; cursor: pointer; }
        .btn-nuevo:hover { background: #059669; }
        
        .filters { display: flex; gap: 10px; }
        .filters select, .filters input, .filters button { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; }
        .filters select:focus, .filters input:focus { border-color: #f59e0b; }
        .btn-search { background: #3b82f6; color: white; cursor: pointer; border: none; }
        .btn-search:hover { background: #2563eb; }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 15px; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; display: inline-block; }
        
        .btn-view { color: #f59e0b; text-decoration: none; font-weight: 600; font-size: 14px; margin-right: 10px; }
        .btn-view:hover { text-decoration: underline; }
        
        .btn-archive { color: #ef4444; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-archive:hover { text-decoration: underline; }
        
        .btn-papelera { background: white; color: #ef4444; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; border: 1px solid #ef4444; margin-left: 10px; }
        .btn-papelera:hover { background: #fee2e2; }
    </style>
</head>
<body>
    <header class="header" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
        <h1>🗑️ Papelera de Reparaciones (Archivadas)</h1>
        <a href="index.php" class="btn-volver">← Volver a Activas</a>
    </header>

    <div class="container">
        <div class="actions-bar">
            <div>
                <a href="index.php" class="btn-papelera" style="color: #64748b; border-color: #cbd5e1;">📋 Ver Lista Activa</a>
            </div>
            
            <form class="filters" method="GET" action="">
                <select name="estado">
                    <option value="">Todos los Estados</option>
                    <option value="Recibido" <?php echo $filtro_estado == 'Recibido' ? 'selected' : ''; ?>>Recibido</option>
                    <option value="En Revisión" <?php echo $filtro_estado == 'En Revisión' ? 'selected' : ''; ?>>En Revisión</option>
                    <option value="Esperando Pieza" <?php echo $filtro_estado == 'Esperando Pieza' ? 'selected' : ''; ?>>Esperando Pieza</option>
                    <option value="Reparado" <?php echo $filtro_estado == 'Reparado' ? 'selected' : ''; ?>>Reparado</option>
                    <option value="No Reparable" <?php echo $filtro_estado == 'No Reparable' ? 'selected' : ''; ?>>No Reparable</option>
                    <option value="Entregado" <?php echo $filtro_estado == 'Entregado' ? 'selected' : ''; ?>>Entregado</option>
                </select>
                <input type="text" name="busqueda" placeholder="Buscar ticket, IMEI o cliente..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                <button type="submit" class="btn-search">🔍 Buscar</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Fecha Entrada</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt === false): ?>
                        <tr><td colspan="6" style="text-align: center; color: red;">Error en la base de datos.</td></tr>
                    <?php else: ?>
                        <?php $hay_registros = false; while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): $hay_registros = true; ?>
                        <tr>
                            <td style="font-weight: bold;"><?php echo htmlspecialchars($r['codigo_ticket'] ?? ''); ?></td>
                            <td><?php echo date_format($r['fecha_recepcion'], 'd/m/Y h:i A'); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($r['cliente_nombre'] ?? ''); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($r['cliente_telefono'] ?? ''); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars(($r['tipo_equipo'] ?? '') . ' - ' . ($r['marca_modelo'] ?? '')); ?></div>
                                <div style="font-size: 12px; color: #64748b;">IMEI: <?php echo htmlspecialchars($r['serial_imei'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $estados_badge[$r['estado']] ?? '#000'; ?>">
                                    <?php echo htmlspecialchars($r['estado'] ?? ''); ?>
                                </span>
                            </td>
                            <td>
                                <a href="ver_reparacion.php?id=<?php echo $r['id']; ?>" class="btn-view">Ver Detalles →</a>
                                <a href="archivar_reparacion.php?id=<?php echo $r['id']; ?>&accion=restaurar" class="btn-nuevo" style="background: #3b82f6; padding: 6px 12px; margin-left: 5px;" onclick="return confirm('¿Restaurar este ticket a la lista activa?');">♻️ Restaurar</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(!$hay_registros): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">No se encontraron reparaciones.</td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
