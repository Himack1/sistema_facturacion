<?php
/**
 * HELPER DE LICENCIA CLOUD (Google Sheets)
 * Verifica si el sistema está activo consultando una Hoja de Cálculo en la nube
 */

function verificarLicencia($conn, $force = false) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $is_master = isset($_GET['master']);
    
    // 1. EXCEPCIONES ABSOLUTAS: Si vamos al Login o a Configuración, NO bloqueamos nunca
    if (!$force) {
        if ($current_page === 'login.php' || $current_page === 'configuracion_empresa.php') {
            return;
        }
    }

    // 2. Autodestruir sesión maestra si salimos del panel de configuración
    if ($current_page !== 'configuracion_empresa.php' && isset($_SESSION['master_verified'])) {
        unset($_SESSION['master_verified']);
    }

    // 1. Obtener configuración actual
    $sql = "SELECT licencia_activa, fecha_vencimiento, licencia_permanente, 
                   licencia_cloud_url, licencia_serial, licencia_last_sync,
                   ISNULL(licencia_sync_intervalo, 60) as licencia_sync_intervalo 
            FROM config_empresa WHERE id = 1";
    $stmt = sqlsrv_query($conn, $sql);
    $lic = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$lic) return;

    // Si la licencia es permanente, ignoramos TODA la lógica de bloqueo y sincronización
    if (isset($lic['licencia_permanente']) && $lic['licencia_permanente'] == 1) {
        // Si estamos en la página de bloqueo, sacamos al usuario de ahí
        if ($current_page === 'bloqueo.php') {
            header("Location: dashboard.php");
            exit();
        }
        return;
    }

    $hoy = new DateTime();
    $serial = $lic['licencia_serial'];
    $cloud_url = $lic['licencia_cloud_url'];
    $last_sync = $lic['licencia_last_sync'];
    $intervalo_minutos = (int)$lic['licencia_sync_intervalo'];

    // ==================================================
    // 2. SINCRONIZACIÓN CON LA NUBE (Google Sheets)
    // ==================================================
    $debe_sincronizar = false;
    if (!empty($cloud_url)) {
        // SI estamos en el Dashboard, forzamos sincronización para que el bloqueo sea casi instantáneo
        if ($current_page === 'dashboard.php' || $current_page === 'index.php') {
            $debe_sincronizar = true;
        } else if (!$last_sync) {
            $debe_sincronizar = true;
        } else {
            $segundos_transcurridos = $hoy->getTimestamp() - $last_sync->getTimestamp();
            if ($segundos_transcurridos >= ($intervalo_minutos * 60)) { 
                $debe_sincronizar = true;
            }
        }
    }

    if ($debe_sincronizar) {
        $context = stream_context_create([
            "http" => ["timeout" => 8, "ignore_errors" => true]
        ]);
        
        $contenido = @file_get_contents($cloud_url, false, $context);
        
        if ($contenido !== false) {
            file_put_contents('licencia_debug.txt', "--- NUEVA SINCRO ---\n" . $contenido . "\n", FILE_APPEND);
            // Eliminar BOM de UTF-8 y otros caracteres raros
            $contenido = preg_replace('/^[\x00-\x1F\x80-\xFF\xEF\xBB\xBF]+/', '', $contenido);
            
            $lineas = explode("\n", $contenido);
            $encontrado = false;
            $serial_local = strtolower(trim($serial));
            
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if (empty($linea)) continue;
                
                // Detectar delimitador (Coma o Punto y Coma)
                $delimitador = (strpos($linea, ';') !== false) ? ';' : ',';
                $datos = str_getcsv($linea, $delimitador);
                // Estructura: Serial, Estado, Vencimiento, Clave
                $serial_nube = isset($datos[0]) ? strtolower(trim($datos[0])) : '';
                
                file_put_contents('licencia_debug.txt', "Probando: [$serial_nube] vs [$serial_local]\n", FILE_APPEND);
                
                if (count($datos) >= 2 && $serial_nube === $serial_local) {
                    $estado_txt = strtolower(trim($datos[1]));
                    file_put_contents('licencia_debug.txt', "MATCH! Estado detectado: [$estado_txt]\n", FILE_APPEND);
                    $nuevo_estado = ($estado_txt === 'activo' || $estado_txt === '1') ? 1 : 0;
                    $nueva_fecha = (isset($datos[2]) && !empty(trim($datos[2]))) ? trim($datos[2]) : null;
                    $nueva_clave = (isset($datos[3]) && !empty(trim($datos[3]))) ? trim($datos[3]) : null;
                    
                    // Actualizar base de datos local
                    $sql_upd = "UPDATE config_empresa SET 
                                licencia_activa = ?, 
                                fecha_vencimiento = ?, 
                                licencia_master_key = ?,
                                licencia_last_sync = GETDATE() 
                                WHERE id = 1";
                    sqlsrv_query($conn, $sql_upd, [$nuevo_estado, $nueva_fecha, $nueva_clave]);
                    
                    // Actualizar variables para la verificación inmediata
                    $lic['licencia_activa'] = $nuevo_estado;
                    if ($nueva_fecha) $lic['fecha_vencimiento'] = new DateTime($nueva_fecha);
                    $encontrado = true;
                    break;
                }
            }
            
            if (!$encontrado) {
                sqlsrv_query($conn, "UPDATE config_empresa SET licencia_last_sync = GETDATE() WHERE id = 1");
            }
        }
    }

    // ==================================================
    // 3. VERIFICACIÓN DE BLOQUEO
    // ==================================================
    
    // ==================================================
    // 3. VERIFICACIÓN DE REGLAS
    // ==================================================
    $vencimiento = $lic['fecha_vencimiento'] ? new DateTime($lic['fecha_vencimiento']->format('Y-m-d')) : null;
    $bloqueado = false;
    $motivo_bloqueo = "";

    // A. Manipulación de Reloj (Anti-Rollback)
    if (!empty($lic['licencia_last_sync'])) {
        $last_sync_obj = ($lic['licencia_last_sync'] instanceof DateTime) ? $lic['licencia_last_sync'] : new DateTime($lic['licencia_last_sync']->format('Y-m-d H:i:s'));
        $ahora = new DateTime();
        $last_sync_mod = clone $last_sync_obj;
        $last_sync_mod->modify('-5 minutes');
        
        if ($ahora < $last_sync_mod) {
            $bloqueado = true;
            $motivo_bloqueo = "Manipulación de reloj detectada.";
        }
    }

    // B. Estado de la Licencia
    if (!$bloqueado && $lic['licencia_activa'] == 0) {
        $bloqueado = true;
        $motivo_bloqueo = "Licencia desactivada.";
    }

    // C. Vencimiento
    if (!$bloqueado && $vencimiento && $hoy > $vencimiento) {
        $bloqueado = true;
        $motivo_bloqueo = "Licencia vencida.";
    }

    // ==================================================
    // 4. ACCIÓN DE BLOQUEO / DESBLOQUEO
    // ==================================================
    if ($bloqueado) {
        // Manejo para peticiones AJAX
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $motivo_bloqueo]);
            exit();
        }
        
        // Redirigir a pantalla de bloqueo (solo si NO estamos ya ahí)
        if ($current_page !== 'bloqueo.php') {
            $path = file_exists('bloqueo.php') ? 'bloqueo.php' : (file_exists('../bloqueo.php') ? '../bloqueo.php' : '../../bloqueo.php');
            header("Location: $path");
            exit();
        }
    } else {
        // AUTO-DESBLOQUEO: Si estamos en bloqueo.php pero ya todo está bien, volver al dashboard
        if ($current_page === 'bloqueo.php') {
            header("Location: dashboard.php");
            exit();
        }
    }
}
?>
