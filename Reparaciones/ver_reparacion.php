<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $estado = trim($_POST['estado']);
    $diagnostico_tecnico = trim($_POST['diagnostico_tecnico']);
    $costo_estimado = isset($_POST['costo_estimado']) && $_POST['costo_estimado'] !== '' ? floatval($_POST['costo_estimado']) : null;
    
    $sql_upd = "UPDATE reparaciones SET estado = ?, diagnostico_tecnico = ?, costo_estimado = ? WHERE id = ?";
    if(sqlsrv_query($conn, $sql_upd, array($estado, $diagnostico_tecnico, $costo_estimado, $id))) {
        $success = "Reparación actualizada exitosamente.";
    } else {
        $error = "Error al actualizar la reparación.";
    }
}

// Obtener datos
$sql = "SELECT r.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono, u.nombre as receptor_nombre 
        FROM reparaciones r 
        JOIN clientes c ON r.cliente_id = c.id 
        JOIN usuarios u ON r.usuario_id = u.id 
        WHERE r.id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$r) {
    header("Location: index.php");
    exit();
}

$estados_badge = [
    'Recibido' => '#6b7280',
    'En Revisión' => '#f59e0b',
    'Esperando Pieza' => '#ef4444',
    'Reparado' => '#10b981',
    'No Reparable' => '#1f2937',
    'Entregado' => '#3b82f6'
];

// Preparar URL de WhatsApp
$tel_wa = preg_replace('/[^0-9]/', '', $r['cliente_telefono'] ?? '');
if(strlen($tel_wa) == 10) { $tel_wa = '1' . $tel_wa; } // Asumiendo Rep. Dom.
$msg_wa = urlencode("Hola " . trim($r['cliente_nombre'] ?? '') . ", te contactamos de soporte técnico. El estado de tu equipo (" . trim($r['tipo_equipo'] ?? '') . " " . trim($r['marca_modelo'] ?? '') . ") con ticket " . $r['codigo_ticket'] . " es: *" . $r['estado'] . "*.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles Reparación - <?php echo htmlspecialchars($r['codigo_ticket']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card h2 { color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; font-size: 18px; }
        
        .info-row { margin-bottom: 15px; }
        .info-label { color: #64748b; font-size: 13px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .info-value { color: #0f172a; font-size: 15px; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #f59e0b; }
        
        .btn-submit { background: #3b82f6; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #2563eb; }
        
        .btn-print { background: #64748b; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 13px; display: inline-block; margin-bottom: 20px; text-align: center; }
        .btn-print:hover { background: #475569; }
        
        .btn-wa { background: #25D366; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 13px; display: inline-block; margin-bottom: 20px; text-align: center; }
        .btn-wa:hover { background: #128C7E; }
        .btn-wa.disabled { background: #cbd5e1; cursor: not-allowed; color: #64748b; }

        .btn-bill { background: #10b981; color: white; padding: 15px 20px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; width: 100%; display: block; text-align: center; text-decoration: none; margin-top: 20px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
        .btn-bill:hover { background: #059669; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; grid-column: 1 / -1; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; display: inline-block; background-color: <?php echo $estados_badge[$r['estado']] ?? '#000'; ?>; }
    </style>
</head>
<body>
    <header class="header">
        <h1>🔧 Gestión de Ticket: <?php echo htmlspecialchars($r['codigo_ticket']); ?></h1>
        <a href="index.php" class="btn-volver">← Volver a la Lista</a>
    </header>

    <div class="container">
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success ?? ''); ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error ?? ''); ?></div>
        <?php endif; ?>

        <!-- Panel Izquierdo: Info -->
        <div class="card">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="imprimir_constancia.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-print">🖨️ Re-imprimir Ticket</a>
                <?php if($tel_wa): ?>
                    <a href="https://wa.me/<?php echo $tel_wa; ?>?text=<?php echo $msg_wa; ?>" target="_blank" class="btn-wa">📱 WhatsApp Cliente</a>
                <?php else: ?>
                    <span class="btn-wa disabled" title="El cliente no tiene teléfono registrado">📱 Sin número registrado</span>
                <?php endif; ?>
            </div>
            
            <h2>Información General</h2>
            
            <div class="info-row">
                <span class="info-label">Estado Actual</span>
                <div class="badge"><?php echo htmlspecialchars($r['estado'] ?? ''); ?></div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Cliente</span>
                <div class="info-value">
                    <strong><?php echo htmlspecialchars($r['cliente_nombre'] ?? ''); ?></strong><br>
                    📞 <?php echo htmlspecialchars($r['cliente_telefono'] ?? ''); ?>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Equipo</span>
                <div class="info-value">
                    <?php echo htmlspecialchars(($r['tipo_equipo'] ?? '') . ' - ' . ($r['marca_modelo'] ?? '')); ?><br>
                    <small>IMEI/Serial: <?php echo htmlspecialchars($r['serial_imei'] ?? 'N/A'); ?></small>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Problema Reportado</span>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($r['problema_reportado'] ?? '')); ?></div>
            </div>
            
            <?php if($r['condicion_fisica']): ?>
            <div class="info-row">
                <span class="info-label">Condición Física</span>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($r['condicion_fisica'] ?? '')); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if($r['contrasena_pin']): ?>
            <div class="info-row">
                <span class="info-label">Contraseña / PIN</span>
                <div class="info-value" style="font-family: monospace; letter-spacing: 2px;"><?php echo htmlspecialchars($r['contrasena_pin'] ?? ''); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Panel Derecho: Acciones y Actualización -->
        <div class="card">
            <h2>Actualización Técnica</h2>
            
            <?php if($r['estado'] === 'Entregado'): ?>
                <div class="alert alert-success" style="grid-column: unset;">✅ Este equipo ya fue entregado al cliente y facturado. El ticket está cerrado.
                    <h3>Opciones Extras</h3>
                    <a href="../Facturacion/ver_factura.php?id=<?php echo $r['factura_id']; ?>" target="_blank" class="btn-print" style="width: 100%; text-align: center;">📄 Ver Factura Generada</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="actualizar">
                    
                    <div class="form-group">
                        <label>Estado del Equipo</label>
                        <select name="estado" required>
                            <option value="Recibido" <?php echo $r['estado'] == 'Recibido' ? 'selected' : ''; ?>>Recibido (Aún no revisado)</option>
                            <option value="En Revisión" <?php echo $r['estado'] == 'En Revisión' ? 'selected' : ''; ?>>En Revisión (Diagnosticando)</option>
                            <option value="Esperando Pieza" <?php echo $r['estado'] == 'Esperando Pieza' ? 'selected' : ''; ?>>Esperando Pieza</option>
                            <option value="Reparado" <?php echo $r['estado'] == 'Reparado' ? 'selected' : ''; ?>>Reparado (Listo para entregar)</option>
                            <option value="No Reparable" <?php echo $r['estado'] == 'No Reparable' ? 'selected' : ''; ?>>No Reparable / Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnóstico Técnico / Notas Internas</label>
                        <textarea name="diagnostico_tecnico" rows="4" placeholder="Ej. Se encontró humedad en la placa. Se procedió a limpiar."><?php echo htmlspecialchars($r['diagnostico_tecnico'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Costo Estimado / Final (RD$)</label>
                        <input type="number" name="costo_estimado" step="0.01" min="0" value="<?php echo $r['costo_estimado'] !== null ? $r['costo_estimado'] : ''; ?>">
                    </div>

                    <button type="submit" class="btn-submit">💾 Guardar Cambios</button>
                </form>

                <?php if($r['estado'] === 'Reparado' || $r['estado'] === 'No Reparable'): ?>
                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;">
                    <a href="procesar_entrega.php?id=<?php echo $r['id']; ?>" class="btn-bill">📦 Entregar al Cliente y Facturar</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
