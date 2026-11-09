<?php
/**
 * Guardar configuración de reportes
 * Ubicación: Reportes/ajax/guardar_configuracion.php
 */

session_start();
require_once '../../conexion.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

try {
    // Obtener datos JSON del body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Datos inválidos');
    }
    
    // Validar datos recibidos
    $auto_cierre_caja = isset($data['auto_cierre_caja']) ? (int)$data['auto_cierre_caja'] : 0;
    $auto_diario = isset($data['auto_diario']) ? (int)$data['auto_diario'] : 0;
    $auto_semanal = isset($data['auto_semanal']) ? (int)$data['auto_semanal'] : 0;
    $auto_drive = isset($data['auto_drive']) ? (int)$data['auto_drive'] : 0;
    $alerta_diferencias = isset($data['alerta_diferencias']) ? (int)$data['alerta_diferencias'] : 0;
    $alerta_credito = isset($data['alerta_credito']) ? (int)$data['alerta_credito'] : 0;
    $alerta_stock = isset($data['alerta_stock']) ? (int)$data['alerta_stock'] : 0;
    $telegram_bot_token = isset($data['telegram_bot_token']) ? trim($data['telegram_bot_token']) : '';
    $telegram_chat_id = isset($data['telegram_chat_id']) ? trim($data['telegram_chat_id']) : '';
    $drive_folder_path = isset($data['drive_folder_path']) ? trim($data['drive_folder_path']) : '';
    
    // Verificar si existe configuración
    $sql_check = "SELECT COUNT(*) as total FROM config_reportes WHERE id = 1";
    $stmt_check = sqlsrv_query($conn, $sql_check);
    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
    
    if ($row_check['total'] > 0) {
        // Actualizar configuración existente
        $sql = "UPDATE config_reportes
                SET auto_cierre_caja = ?,
                    auto_diario = ?,
                    auto_semanal = ?,
                    auto_drive = ?,
                    alerta_diferencias = ?,
                    alerta_credito = ?,
                    alerta_stock = ?,
                    telegram_bot_token = ?,
                    telegram_chat_id = ?,
                    drive_folder_path = ?,
                    ultima_modificacion = GETDATE()
                WHERE id = 1";
        
        $params = [
            $auto_cierre_caja,
            $auto_diario,
            $auto_semanal,
            $auto_drive,
            $alerta_diferencias,
            $alerta_credito,
            $alerta_stock,
            $telegram_bot_token,
            $telegram_chat_id,
            $drive_folder_path
        ];
        
    } else {
        // Insertar nueva configuración
        $sql = "INSERT INTO config_reportes (
                    auto_cierre_caja, auto_diario, auto_semanal, auto_drive,
                    alerta_diferencias, alerta_credito, alerta_stock,
                    telegram_bot_token, telegram_chat_id, drive_folder_path,
                    hora_reporte_diario, dia_reporte_semanal
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '18:00:00', 1)";
        
        $params = [
            $auto_cierre_caja,
            $auto_diario,
            $auto_semanal,
            $auto_drive,
            $alerta_diferencias,
            $alerta_credito,
            $alerta_stock,
            $telegram_bot_token,
            $telegram_chat_id,
            $drive_folder_path
        ];
    }

    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception('Error al guardar configuración: ' . print_r($errors, true));
    }
    
    // Registrar en log (opcional)
    $sql_log = "INSERT INTO movimientos_caja (caja_id, tipo, concepto, monto, usuario_id, fecha_movimiento)
                SELECT TOP 1 id, 'ingreso', 'Configuración de reportes actualizada', 0, ?, GETDATE()
                FROM cajas 
                WHERE estado = 'abierta' 
                ORDER BY fecha_apertura DESC";
    
    // Este log es opcional y puede fallar si no hay caja abierta
    @sqlsrv_query($conn, $sql_log, [$_SESSION['usuario_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada exitosamente',
        'config' => [
            'auto_cierre_caja' => $auto_cierre_caja,
            'auto_diario' => $auto_diario,
            'auto_semanal' => $auto_semanal,
            'auto_drive' => $auto_drive,
            'alerta_diferencias' => $alerta_diferencias,
            'alerta_credito' => $alerta_credito,
            'alerta_stock' => $alerta_stock
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

sqlsrv_close($conn);
?>