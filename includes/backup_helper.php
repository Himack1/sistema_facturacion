<?php
/**
 * Helper para Backups Automáticos
 * Ejecutado desde dashboard.php
 */

function verificar_y_ejecutar_backup($conn) {
    $sql = "SELECT * FROM config_backups WHERE id = 1";
    $stmt = sqlsrv_query($conn, $sql);
    
    if (!$stmt) return false;
    
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$config || $config['frecuencia'] === 'manual') {
        return false;
    }
    
    $frecuencia = $config['frecuencia'];
    $hora_ejecucion = is_object($config['hora_ejecucion']) ? $config['hora_ejecucion']->format('H:i:s') : $config['hora_ejecucion'];
    $ultimo_backup = $config['ultimo_backup'];
    
    $ahora = new DateTime();
    $fecha_actual_str = $ahora->format('Y-m-d');
    $hora_actual_str = $ahora->format('H:i:s');
    
    $debe_ejecutar = false;
    
    // Si la hora actual es mayor o igual a la hora configurada
    if ($hora_actual_str >= $hora_ejecucion) {
        if ($ultimo_backup == null) {
            $debe_ejecutar = true;
        } else {
            $diff = $ahora->diff($ultimo_backup);
            $dias_pasados = $diff->days;
            
            if ($frecuencia === 'diario' && $dias_pasados >= 1) {
                $debe_ejecutar = true;
            } else if ($frecuencia === 'semanal' && $dias_pasados >= 7) {
                $debe_ejecutar = true;
            } else if ($frecuencia === 'mensual' && $dias_pasados >= 30) {
                $debe_ejecutar = true;
            }
        }
    }
    
    if ($debe_ejecutar) {
        $ruta = isset($config['ruta_destino']) ? trim($config['ruta_destino']) : 'C:\\wamp64\\www\\Sistema de facturacion\\backups_db\\';
        if (substr($ruta, -1) !== '\\' && substr($ruta, -1) !== '/') {
            $ruta .= '\\';
        }
        
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0777, true);
        }
        
        $archivo = 'Backup_Auto_' . $ahora->format('Y-m-d_H-i-s') . '.bak';
        $ruta_completa = $ruta . $archivo;
        
        $ruta_segura = str_replace("'", "''", $ruta_completa);
        $sql_backup = "BACKUP DATABASE [Sistema_Facturacion] TO DISK = '$ruta_segura'";
        
        // Desactivar warnings como errores para que el BACKUP no se aborte
        sqlsrv_configure('WarningsReturnAsErrors', 0);
        $stmt_backup = sqlsrv_query($conn, $sql_backup);
        
        if ($stmt_backup) {
            while (sqlsrv_next_result($stmt_backup)) {}
            sqlsrv_free_stmt($stmt_backup);
        }
        
        sqlsrv_configure('WarningsReturnAsErrors', 1);
        
        clearstatcache();
        if (file_exists($ruta_completa)) {
            sqlsrv_query($conn, "UPDATE config_backups SET ultimo_backup = GETDATE() WHERE id = 1");
            return true;
        }
    }
    
    return false;
}
?>
