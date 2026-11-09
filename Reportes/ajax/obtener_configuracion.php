<?php
/**
 * Obtener configuración actual de reportes
 * Ubicación: Reportes/ajax/obtener_configuracion.php
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
    $sql = "SELECT * FROM vw_config_reportes";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        throw new Exception('Error al consultar configuración');
    }
    
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($config) {
        // Convertir valores BIT a enteros para JavaScript
        $response = [
            'success' => true,
            'config' => [
                'auto_cierre_caja' => (int)$config['auto_cierre_caja'],
                'auto_diario' => (int)$config['auto_diario'],
                'auto_semanal' => (int)$config['auto_semanal'],
                'auto_drive' => (int)$config['auto_drive'],
                'alerta_diferencias' => (int)$config['alerta_diferencias'],
                'alerta_credito' => (int)$config['alerta_credito'],
                'alerta_stock' => (int)$config['alerta_stock'],
                'whatsapp_destino' => $config['whatsapp_destino'],
                'email_destino' => $config['email_destino'],
                'hora_reporte_diario' => $config['hora_reporte_diario'] ? 
                    $config['hora_reporte_diario']->format('H:i:s') : null,
                'dia_reporte_semanal' => $config['dia_reporte_semanal']
            ]
        ];
    } else {
        // Si no existe configuración, crear una por defecto
        $sql_insert = "INSERT INTO config_reportes (
            auto_cierre_caja, auto_diario, auto_semanal, auto_drive,
            alerta_diferencias, alerta_credito, alerta_stock,
            hora_reporte_diario, dia_reporte_semanal
        ) VALUES (0, 0, 0, 0, 0, 0, 0, '18:00:00', 1)";
        
        sqlsrv_query($conn, $sql_insert);
        
        $response = [
            'success' => true,
            'config' => [
                'auto_cierre_caja' => 0,
                'auto_diario' => 0,
                'auto_semanal' => 0,
                'auto_drive' => 0,
                'alerta_diferencias' => 0,
                'alerta_credito' => 0,
                'alerta_stock' => 0,
                'whatsapp_destino' => null,
                'email_destino' => null,
                'hora_reporte_diario' => '18:00:00',
                'dia_reporte_semanal' => 1
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

sqlsrv_close($conn);
?>