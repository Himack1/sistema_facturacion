<?php
/**
 * Ayudante de Notificaciones de Cobro
 * Gestiona alertas web y notificaciones push vía Telegram
 */

function obtenerResumenCobros($conn) {
    $hoy = date('Y-m-d');
    $manana = date('Y-m-d', strtotime('+1 day'));
    
    $resumen = [
        'vencidas' => [],
        'hoy' => [],
        'manana' => []
    ];
    
    // 1. Cuotas VENCIDAS (fecha menor a hoy y no pagadas completamente)
    $sql_vencidas = "SELECT fc.id, fc.numero_cuota, fc.monto_cuota, fc.monto_pagado, fc.fecha_vencimiento, 
                     c.nombre as cliente_nombre, f.numero_factura 
                     FROM facturas_cuotas fc
                     JOIN facturas f ON fc.factura_id = f.id
                     JOIN clientes c ON f.cliente_id = c.id
                     WHERE fc.fecha_vencimiento < ?
                     AND fc.estado = 'Pendiente'
                     ORDER BY fc.fecha_vencimiento ASC";
    $stmt = sqlsrv_query($conn, $sql_vencidas, [$hoy]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resumen['vencidas'][] = $row;
        }
    }
    
    // 2. Cuotas que vencen HOY
    $sql_hoy = "SELECT fc.id, fc.numero_cuota, fc.monto_cuota, fc.monto_pagado, fc.fecha_vencimiento,
                c.nombre as cliente_nombre, f.numero_factura 
                FROM facturas_cuotas fc
                JOIN facturas f ON fc.factura_id = f.id
                JOIN clientes c ON f.cliente_id = c.id
                WHERE fc.fecha_vencimiento = ?
                AND fc.estado = 'Pendiente'";
    $stmt = sqlsrv_query($conn, $sql_hoy, [$hoy]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resumen['hoy'][] = $row;
        }
    }
    
    // 3. Cuotas que vencen MAÑANA
    $sql_manana = "SELECT fc.id, fc.numero_cuota, fc.monto_cuota, fc.monto_pagado, fc.fecha_vencimiento,
                   c.nombre as cliente_nombre, f.numero_factura 
                   FROM facturas_cuotas fc
                   JOIN facturas f ON fc.factura_id = f.id
                   JOIN clientes c ON f.cliente_id = c.id
                   WHERE fc.fecha_vencimiento = ?
                   AND fc.estado = 'Pendiente'";
    $stmt = sqlsrv_query($conn, $sql_manana, [$manana]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resumen['manana'][] = $row;
        }
    }
    
    return $resumen;
}

/**
 * Envía el resumen de cobros a Telegram
 * Requiere que el bot esté configurado en config_empresa
 */
function enviarAlertasCobroTelegram($conn) {
    // Obtener token y chat_id de la configuración de reportes
    $sql = "SELECT telegram_bot_token, telegram_chat_id FROM config_reportes";
    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) return false;
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (empty($config['telegram_bot_token']) || empty($config['telegram_chat_id'])) return false;
    
    $resumen = obtenerResumenCobros($conn);
    
    if (empty($resumen['vencidas']) && empty($resumen['hoy']) && empty($resumen['manana'])) return "Sin pendientes";
    
    $mensaje = "🔔 *RESUMEN DIARIO DE COBROS*\n\n";
    
    if (!empty($resumen['hoy'])) {
        $mensaje .= "📍 *VENCEN HOY:*\n";
        foreach ($resumen['hoy'] as $c) {
            $mensaje .= "• " . $c['cliente_nombre'] . " (RD$ " . number_format($c['monto_cuota'] - $c['monto_pagado'], 2) . ")\n";
        }
        $mensaje .= "\n";
    }
    
    if (!empty($resumen['manana'])) {
        $mensaje .= "⏳ *VENCEN MAÑANA:*\n";
        foreach ($resumen['manana'] as $c) {
            $mensaje .= "• " . $c['cliente_nombre'] . " (RD$ " . number_format($c['monto_cuota'] - $c['monto_pagado'], 2) . ")\n";
        }
        $mensaje .= "\n";
    }
    
    if (!empty($resumen['vencidas'])) {
        $mensaje .= "⚠️ *MOROSOS (Vencidos):*\n";
        foreach ($resumen['vencidas'] as $c) {
            $fecha = $c['fecha_vencimiento'];
            $fecha_str = ($fecha instanceof DateTime) ? $fecha->format('d/m/Y') : $fecha;
            $mensaje .= "• " . $c['cliente_nombre'] . " - Venció: " . $fecha_str . "\n";
        }
    }
    
    $url = "https://api.telegram.org/bot" . $config['telegram_bot_token'] . "/sendMessage";
    $data = [
        'chat_id' => $config['telegram_chat_id'],
        'text' => $mensaje,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
