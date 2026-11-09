<?php
/**
 * Obtener detalle completo de una caja específica
 * Ubicación: Reportes/ajax/obtener_detalle_caja.php
 */

session_start();
require_once '../../conexion.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

try {
    $caja_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($caja_id <= 0) {
        throw new Exception('ID de caja inválido');
    }
    
    // ===========================================
    // 1. INFORMACIÓN DE LA CAJA
    // ===========================================
    $sql_caja = "
        SELECT 
            c.id,
            u.nombre as usuario,
            FORMAT(c.fecha_apertura, 'dd/MM/yyyy HH:mm') as fecha_apertura,
            FORMAT(c.fecha_cierre, 'dd/MM/yyyy HH:mm') as fecha_cierre,
            c.monto_inicial,
            c.total_ventas_efectivo,
            c.total_ventas_transferencia,
            c.total_ventas_credito,
            c.monto_final_esperado,
            c.monto_final_real,
            ISNULL(c.diferencia, 0) as diferencia,
            c.estado,
            c.observaciones_apertura,
            c.observaciones_cierre
        FROM cajas c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.id = ?
    ";
    
    $stmt_caja = sqlsrv_query($conn, $sql_caja, [$caja_id]);
    
    if ($stmt_caja === false) {
        throw new Exception('Error al consultar caja');
    }
    
    $caja = sqlsrv_fetch_array($stmt_caja, SQLSRV_FETCH_ASSOC);
    
    if (!$caja) {
        throw new Exception('Caja no encontrada');
    }
    
    // ===========================================
    // 2. FACTURAS DE ESTA CAJA
    // ===========================================
    $sql_facturas = "
        SELECT 
            f.numero_factura,
            c.nombre as cliente,
            f.tipo_pago,
            f.total
        FROM facturas f
        INNER JOIN clientes c ON f.cliente_id = c.id
        WHERE f.caja_id = ?
        AND f.estado = 'pagada'
        ORDER BY f.fecha
    ";
    
    $stmt_facturas = sqlsrv_query($conn, $sql_facturas, [$caja_id]);
    
    $facturas = [];
    while ($row = sqlsrv_fetch_array($stmt_facturas, SQLSRV_FETCH_ASSOC)) {
        $facturas[] = [
            'numero_factura' => $row['numero_factura'],
            'cliente' => $row['cliente'],
            'tipo_pago' => $row['tipo_pago'],
            'total' => (float)$row['total']
        ];
    }
    
    // ===========================================
    // 3. ABONOS A CRÉDITO DE ESTA CAJA
    // ===========================================
    $sql_pagos = "
        SELECT 
            p.monto, p.metodo_pago, p.referencia, p.cuota_id,
            c.nombre as cliente,
            f.numero_factura
        FROM pagos_credito p
        INNER JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN facturas f ON p.factura_id = f.id
        WHERE p.caja_id = ?
        ORDER BY p.id
    ";
    $stmt_pagos = sqlsrv_query($conn, $sql_pagos, [$caja_id]);
    $pagos_credito = [];
    $total_abonos = 0;
    while ($row = sqlsrv_fetch_array($stmt_pagos, SQLSRV_FETCH_ASSOC)) {
        $pagos_credito[] = [
            'monto' => (float)$row['monto'],
            'metodo' => $row['metodo_pago'],
            'cliente' => $row['cliente'],
            'factura' => $row['numero_factura'] ?? 'Abono General',
            'es_cuota' => ($row['cuota_id'] !== null)
        ];
        $total_abonos += (float)$row['monto'];
    }

    // ===========================================
    // 4. MOVIMIENTOS MANUALES DE ESTA CAJA
    // ===========================================
    $sql_movimientos = "
        SELECT 
            mc.tipo,
            mc.concepto,
            mc.monto,
            FORMAT(mc.fecha_movimiento, 'dd/MM/yyyy HH:mm') as fecha
        FROM movimientos_caja mc
        WHERE mc.caja_id = ?
        ORDER BY mc.fecha_movimiento
    ";
    
    $stmt_movimientos = sqlsrv_query($conn, $sql_movimientos, [$caja_id]);
    
    $movimientos = [];
    while ($row = sqlsrv_fetch_array($stmt_movimientos, SQLSRV_FETCH_ASSOC)) {
        $movimientos[] = [
            'tipo' => $row['tipo'],
            'concepto' => $row['concepto'],
            'monto' => (float)$row['monto'],
            'fecha' => $row['fecha']
        ];
    }
    
    // ===========================================
    // RESPUESTA FINAL
    // ===========================================
    $response = [
        'success' => true,
        'caja' => [
            'id' => $caja['id'],
            'usuario' => $caja['usuario'],
            'fecha_apertura' => $caja['fecha_apertura'],
            'fecha_cierre' => $caja['fecha_cierre'],
            'monto_inicial' => (float)$caja['monto_inicial'],
            'total_ventas_efectivo' => (float)$caja['total_ventas_efectivo'],
            'total_ventas_transferencia' => (float)$caja['total_ventas_transferencia'],
            'total_ventas_credito' => (float)$caja['total_ventas_credito'],
            'total_abonos_credito' => $total_abonos,
            'monto_final_esperado' => (float)$caja['monto_final_esperado'],
            'monto_final_real' => (float)$caja['monto_final_real'],
            'diferencia' => (float)$caja['diferencia'],
            'estado' => $caja['estado'],
            'observaciones_apertura' => $caja['observaciones_apertura'],
            'observaciones_cierre' => $caja['observaciones_cierre']
        ],
        'facturas' => $facturas,
        'pagos_credito' => $pagos_credito,
        'movimientos' => $movimientos
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

sqlsrv_close($conn);
?>