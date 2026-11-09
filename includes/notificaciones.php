<?php
/**
 * MOTOR DE NOTIFICACIONES Y RESPALDO
 * Envía alertas por Telegram y exporta respaldos a carpeta de Google Drive
 * 
 * Todas las funciones fallan silenciosamente para no interrumpir
 * el flujo principal del sistema.
 */

// ============================================================
// CONFIGURACIÓN: Obtener tokens y config desde la BD
// ============================================================
function obtenerConfigNotificaciones($conn) {
    $sql = "SELECT TOP 1 * FROM config_reportes WHERE id = 1";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return null;
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $config ?: null;
}

// ============================================================
// TELEGRAM: Enviar mensaje al dueño
// ============================================================
function enviarTelegram($token, $chat_id, $mensaje) {
    if (empty($token) || empty($chat_id)) return false;
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $mensaje,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Telegram error: " . $error);
        return false;
    }
    
    $result = json_decode($response, true);
    return isset($result['ok']) && $result['ok'] === true;
}

// ============================================================
// NOTIFICACIÓN: Nueva venta realizada
// ============================================================
function notificarVenta($conn, $numero_factura, $cliente_nombre, $total, $tipo_pago, $usuario_nombre, $plazos = null) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['auto_cierre_caja']) return; // Usa la misma config de cierre para ventas
    
    $token = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';
    if (empty($token) || empty($chat_id)) return;
    
    $emoji_pago = '💵';
    if ($tipo_pago === 'transferencia') $emoji_pago = '🏦';
    if ($tipo_pago === 'credito') $emoji_pago = '📋';
    
    $mensaje = "🧾 <b>NUEVA VENTA</b>\n\n";
    if ($plazos !== null) {
        $mensaje = "📝 <b>NUEVA VENTA A PLAZOS</b>\n\n";
    }

    $mensaje .= "📌 Factura: <b>{$numero_factura}</b>\n";
    $mensaje .= "👤 Cliente: {$cliente_nombre}\n";
    $mensaje .= "{$emoji_pago} Pago: " . ucfirst($tipo_pago) . "\n";
    $mensaje .= "💰 Total: <b>RD$ " . number_format($total, 2) . "</b>\n";

    if ($plazos !== null) {
        $mensaje .= "\n📊 <b>Detalles del Financiamiento:</b>\n";
        $mensaje .= "💵 Inicial: RD$ " . number_format($plazos['inicial'], 2) . "\n";
        
        $monto_con_interes = $total - $plazos['inicial'];
        $mensaje .= "💳 Total financiado: <b>RD$ " . number_format($monto_con_interes, 2) . "</b>\n";
        
        if ($plazos['interes'] > 0) {
            $balance_financiado = $monto_con_interes / (1 + ($plazos['interes'] / 100));
            $interes_generado = $monto_con_interes - $balance_financiado;
            $mensaje .= "📈 Interés generado (" . $plazos['interes'] . "%): RD$ " . number_format($interes_generado, 2) . "\n";
        }
        
        $cuota_monto = $monto_con_interes / $plazos['cuotas'];
        $mensaje .= "📆 Plan: {$plazos['cuotas']} cuotas " . ucfirst($plazos['frecuencia']) . "s de RD$ " . number_format($cuota_monto, 2) . "\n\n";
    } else {
        $mensaje .= "\n";
    }

    $mensaje .= "🕐 " . date('d/m/Y H:i') . "\n";
    $mensaje .= "👨‍💼 Cajero: {$usuario_nombre}";
    
    enviarTelegram($token, $chat_id, $mensaje);
}

// ============================================================
// NOTIFICACIÓN: Pago de crédito (Cuotas o Abonos)
// ============================================================
function notificarPagoCredito($conn, $pago_data) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['auto_cierre_caja']) return;
    
    $token = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';
    if (empty($token) || empty($chat_id)) return;
    
    $emoji_metodo = $pago_data['metodo_pago'] === 'transferencia' ? '🏦' : '💵';
    if ($pago_data['es_cuota'] && !empty($pago_data['es_parcial'])) {
        $titulo = "⚡ <b>ABONO PARCIAL DE CUOTA</b>";
    } elseif ($pago_data['es_cuota']) {
        $titulo = "💳 <b>PAGO DE CUOTA</b>";
    } elseif ($pago_data['es_factura']) {
        $titulo = "🧾 <b>PAGO DE FACTURA</b>";
    } else {
        $titulo = "💰 <b>ABONO A DEUDA</b>";
    }
    
    $mensaje = "{$titulo}\n\n";
    $mensaje .= "👤 Cliente: <b>{$pago_data['cliente_nombre']}</b>\n";
    $mensaje .= "{$emoji_metodo} Monto: <b>RD$ " . number_format($pago_data['monto'], 2) . "</b>\n";
    $mensaje .= "📌 Detalle: {$pago_data['detalle']}\n";
    $mensaje .= "💳 Método: " . ucfirst($pago_data['metodo_pago']) . "\n";
    
    if (!empty($pago_data['referencia'])) {
        $mensaje .= "🔗 Ref: {$pago_data['referencia']}\n";
    }
    
    $mensaje .= "\n📊 <b>Estado de Cuenta:</b>\n";
    $mensaje .= "📉 Pendiente actual: <b>RD$ " . number_format($pago_data['saldo_restante'], 2) . "</b>\n";
    
    $mensaje .= "\n🕐 " . date('d/m/Y H:i') . "\n";
    $mensaje .= "👨‍💼 Recibido por: {$pago_data['usuario_nombre']}";
    
    enviarTelegram($token, $chat_id, $mensaje);
}

// ============================================================
// NOTIFICACIÓN: Factura anulada
// ============================================================
function notificarAnulacionFactura($conn, $numero_factura, $cliente_nombre, $total, $usuario_nombre, $motivo) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['auto_cierre_caja']) return; // Usa la misma config de cierre
    
    $token = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';
    if (empty($token) || empty($chat_id)) return;
    
    $mensaje = "⚠️ <b>FACTURA ANULADA</b>\n\n";
    $mensaje .= "📌 Factura: <b>{$numero_factura}</b>\n";
    $mensaje .= "👤 Cliente: {$cliente_nombre}\n";
    $mensaje .= "💰 Total Revertido: <b>RD$ " . number_format($total, 2) . "</b>\n";
    $mensaje .= "👨‍💼 Anulada por: {$usuario_nombre}\n";
    $mensaje .= "🕐 " . date('d/m/Y H:i') . "\n\n";
    $mensaje .= "📝 <b>Motivo:</b>\n<i>{$motivo}</i>";
    
    enviarTelegram($token, $chat_id, $mensaje);
}

// ============================================================
// NOTIFICACIÓN: Cierre de caja
// ============================================================
function notificarCierreCaja($conn, $caja_data) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['auto_cierre_caja']) return;
    
    $token = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';
    if (empty($token) || empty($chat_id)) return;
    
    $total_general = $caja_data['total_ventas_efectivo'] + 
                     $caja_data['total_ventas_transferencia'] + 
                     $caja_data['total_ventas_credito'];
    
    $diferencia = $caja_data['diferencia'] ?? 0;
    $emoji_dif = $diferencia == 0 ? '✅' : ($diferencia > 0 ? '📈' : '⚠️');
    
    $mensaje = "🔒 <b>CIERRE DE CAJA</b>\n\n";
    $mensaje .= "👨‍💼 Cajero: {$caja_data['usuario_nombre']}\n";
    $mensaje .= "🕐 Cerrada: " . date('d/m/Y H:i') . "\n\n";
    $mensaje .= "━━━ <b>RESUMEN</b> ━━━\n";
    $mensaje .= "💵 Efectivo: RD$ " . number_format($caja_data['total_ventas_efectivo'], 2) . "\n";
    $mensaje .= "🏦 Transferencia: RD$ " . number_format($caja_data['total_ventas_transferencia'], 2) . "\n";
    $mensaje .= "📋 Crédito: RD$ " . number_format($caja_data['total_ventas_credito'], 2) . "\n";
    $mensaje .= "━━━━━━━━━━━━\n";
    $mensaje .= "💰 <b>Total: RD$ " . number_format($total_general, 2) . "</b>\n\n";
    $mensaje .= "{$emoji_dif} Diferencia: RD$ " . number_format($diferencia, 2);
    
    // Agregar conteo de anulaciones si está en $caja_data
    if (isset($caja_data['facturas_anuladas'])) {
        $emoji_anul = $caja_data['facturas_anuladas'] > 0 ? '🚫' : '✅';
        $mensaje .= "\n{$emoji_anul} Facturas Anuladas: " . $caja_data['facturas_anuladas'];
    }
    
    if (!empty($caja_data['observaciones'])) {
        $mensaje .= "\n\n📝 Nota: " . $caja_data['observaciones'];
    }
    
    enviarTelegram($token, $chat_id, $mensaje);
}

// ============================================================
// NOTIFICACIÓN: Alerta de stock bajo
// ============================================================
function notificarStockBajo($conn) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['alerta_stock']) return;
    
    $token = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';
    if (empty($token) || empty($chat_id)) return;
    
    $sql = "SELECT nombre, codigo, stock_actual, stock_minimo 
            FROM productos 
            WHERE aplica_inventario = 1 
              AND stock_actual <= stock_minimo 
              AND estado = 'activo'
            ORDER BY stock_actual ASC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return;
    
    $productos = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $productos[] = $row;
    }
    
    if (empty($productos)) return;
    
    $mensaje = "📦 <b>ALERTA DE STOCK BAJO</b>\n\n";
    $mensaje .= count($productos) . " producto(s) requieren reabastecimiento:\n\n";
    
    foreach (array_slice($productos, 0, 10) as $p) {
        $emoji = $p['stock_actual'] <= 0 ? '🔴' : '🟡';
        $mensaje .= "{$emoji} <b>{$p['nombre']}</b> ({$p['codigo']})\n";
        $mensaje .= "    Stock: {$p['stock_actual']} / Mín: {$p['stock_minimo']}\n";
    }
    
    if (count($productos) > 10) {
        $mensaje .= "\n...y " . (count($productos) - 10) . " más.";
    }
    
    enviarTelegram($token, $chat_id, $mensaje);
}

// ============================================================
// GOOGLE DRIVE: Exportar cierre de caja a CSV
// ============================================================
function exportarCierreDrive($conn, $caja_data) {
    $config = obtenerConfigNotificaciones($conn);
    if (!$config || !$config['auto_drive']) return;
    
    $drive_path = $config['drive_folder_path'] ?? '';
    if (empty($drive_path)) {
        // Usar carpeta por defecto dentro del sistema
        $drive_path = dirname(__DIR__) . '/backups_cloud';
    }
    
    // Crear la carpeta si no existe
    if (!is_dir($drive_path)) {
        @mkdir($drive_path, 0777, true);
    }
    
    $fecha = date('Y-m-d');
    $hora = date('H-i-s');
    $filename = "cierre_caja_{$fecha}_{$hora}.csv";
    $filepath = rtrim($drive_path, '/\\') . '/' . $filename;
    
    $total_general = $caja_data['total_ventas_efectivo'] + 
                     $caja_data['total_ventas_transferencia'] + 
                     $caja_data['total_ventas_credito'];
    
    // Cabecera y datos del CSV
    $csv_data = [
        ['CIERRE DE CAJA - ' . date('d/m/Y H:i')],
        [''],
        ['Campo', 'Valor'],
        ['Cajero', $caja_data['usuario_nombre']],
        ['Fecha Cierre', date('d/m/Y H:i:s')],
        ['Monto Inicial', 'RD$ ' . number_format($caja_data['monto_inicial'], 2)],
        ['Ventas Efectivo', 'RD$ ' . number_format($caja_data['total_ventas_efectivo'], 2)],
        ['Ventas Transferencia', 'RD$ ' . number_format($caja_data['total_ventas_transferencia'], 2)],
        ['Ventas Crédito', 'RD$ ' . number_format($caja_data['total_ventas_credito'], 2)],
        ['Total Ventas', 'RD$ ' . number_format($total_general, 2)],
        ['Efectivo Esperado', 'RD$ ' . number_format($caja_data['monto_esperado'] ?? 0, 2)],
        ['Efectivo Real', 'RD$ ' . number_format($caja_data['monto_real'] ?? 0, 2)],
        ['Diferencia', 'RD$ ' . number_format($caja_data['diferencia'] ?? 0, 2)],
        [''],
        ['DETALLE DE VENTAS'],
        ['Factura', 'Cliente', 'Tipo Pago', 'Total']
    ];
    
    // Obtener facturas de esta caja
    $sql_facturas = "SELECT f.numero_factura, c.nombre as cliente_nombre, f.tipo_pago, f.total
                     FROM facturas f
                     INNER JOIN clientes c ON f.cliente_id = c.id
                     WHERE f.caja_id = ?
                     ORDER BY f.fecha_factura ASC";
    $stmt_f = sqlsrv_query($conn, $sql_facturas, [$caja_data['caja_id']]);
    
    if ($stmt_f) {
        while ($f = sqlsrv_fetch_array($stmt_f, SQLSRV_FETCH_ASSOC)) {
            $csv_data[] = [
                $f['numero_factura'],
                $f['cliente_nombre'],
                ucfirst($f['tipo_pago']),
                'RD$ ' . number_format($f['total'], 2)
            ];
        }
    }
    
    // Escribir el CSV
    $fp = @fopen($filepath, 'w');
    if ($fp) {
        // BOM para que Excel lea bien los acentos
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        error_log("Backup exportado: " . $filepath);
    } else {
        error_log("Error creando backup: " . $filepath);
    }
}

// ============================================================
// TEST: Probar la conexión con Telegram
// ============================================================
function testTelegram($token, $chat_id) {
    $mensaje = "✅ <b>Conexión Exitosa</b>\n\n";
    $mensaje .= "El sistema de facturación está conectado correctamente.\n";
    $mensaje .= "Recibirás notificaciones de:\n";
    $mensaje .= "• 🧾 Nuevas ventas\n";
    $mensaje .= "• 🔒 Cierres de caja\n";
    $mensaje .= "• 📦 Alertas de stock bajo\n\n";
    $mensaje .= "🕐 " . date('d/m/Y H:i:s');
    
    return enviarTelegram($token, $chat_id, $mensaje);
}
?>
