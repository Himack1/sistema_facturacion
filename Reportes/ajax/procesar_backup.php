<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../../conexion.php';
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar_config') {
    $frecuencia = $_POST['frecuencia'] ?? 'manual';
    $hora_ejecucion = $_POST['hora_ejecucion'] ?? '23:00:00';

    $sql = "UPDATE config_backups SET frecuencia = ?, hora_ejecucion = ? WHERE id = 1";
    $params = [$frecuencia, $hora_ejecucion];
    
    if (sqlsrv_query($conn, $sql, $params)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
    }
    exit();
}

if ($accion === 'ejecutar_backup') {
    // 1. Obtener la ruta
    $sql = "SELECT ruta_destino FROM config_backups WHERE id = 1";
    $stmt = sqlsrv_query($conn, $sql);
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    $ruta = isset($config['ruta_destino']) ? trim($config['ruta_destino']) : 'C:\\wamp64\\www\\Sistema de facturacion\\backups_db\\';
    if (substr($ruta, -1) !== '\\' && substr($ruta, -1) !== '/') {
        $ruta .= '\\';
    }
    
    if (!is_dir($ruta)) {
        mkdir($ruta, 0777, true);
    }
    
    // 2. Generar nombre de archivo
    $fecha = date('Y-m-d_H-i-s');
    $archivo = 'Backup_Sistema_Facturacion_' . $fecha . '.bak';
    $ruta_completa = $ruta . $archivo;
    
    // 3. Ejecutar BACKUP
    // IMPORTANTE: SQL Server envía mensajes informativos como "Processed X pages..."
    // El driver sqlsrv de PHP los trata como errores por defecto, lo que causa que
    // sqlsrv_query() retorne FALSE y el backup se aborte.
    // Solución: desactivar WarningsReturnAsErrors temporalmente.
    sqlsrv_configure('WarningsReturnAsErrors', 0);
    
    $ruta_segura = str_replace("'", "''", $ruta_completa);
    $sql_backup = "BACKUP DATABASE [Sistema_Facturacion] TO DISK = '$ruta_segura'";
    $stmt_backup = sqlsrv_query($conn, $sql_backup);
    
    if ($stmt_backup) {
        // Consumir todos los result sets para que el backup termine completamente
        while (sqlsrv_next_result($stmt_backup)) {}
        sqlsrv_free_stmt($stmt_backup);
    }
    
    // Restaurar comportamiento normal
    sqlsrv_configure('WarningsReturnAsErrors', 1);
    
    // Verificar si el archivo se creó en el disco
    clearstatcache();
    if (file_exists($ruta_completa)) {
        sqlsrv_query($conn, "UPDATE config_backups SET ultimo_backup = GETDATE() WHERE id = 1");
        
        echo json_encode([
            'success' => true,
            'archivo' => $archivo,
            'fecha' => date('Y-m-d H:i:s')
        ]);
    } else {
        $errors = sqlsrv_errors();
        $error_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error desconocido al crear el backup';
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>
