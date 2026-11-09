<?php
/**
 * Obtener datos para el reporte de ventas
 * Ubicación: Reportes/ajax/obtener_datos_ventas.php
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
    // Obtener filtros
    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
    $usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';
    $tipo_pago_filtro = isset($_GET['tipo_pago']) ? $_GET['tipo_pago'] : '';
    
    // Construir cláusula WHERE
    $where = "WHERE CONVERT(DATE, f.fecha) BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($usuario_filtro)) {
        $where .= " AND f.usuario_id = ?";
        $params[] = $usuario_filtro;
    }
    
    if (!empty($tipo_pago_filtro)) {
        $where .= " AND f.tipo_pago = ?";
        $params[] = $tipo_pago_filtro;
    }
    
    // ===========================================
    // 1. ESTADÍSTICAS GENERALES
    // ===========================================
    $sql_stats = "
        SELECT 
            ISNULL(SUM(CASE WHEN estado = 'pagada' THEN total ELSE 0 END), 0) as total_vendido,
            ISNULL(SUM(CASE WHEN estado = 'pagada' THEN monto_inicial ELSE 0 END), 0) as total_iniciales,
            COUNT(CASE WHEN estado = 'pagada' THEN 1 END) as total_facturas,
            COUNT(CASE WHEN estado = 'anulada' THEN 1 END) as facturas_anuladas,
            -- Cálculo de interés acumulado
            ISNULL(SUM(CASE 
                WHEN estado = 'pagada' AND interes_porcentaje > 0 
                THEN (total - monto_inicial) - ((total - monto_inicial) / (1 + (interes_porcentaje / 100.0)))
                ELSE 0 
            END), 0) as total_interes
        FROM facturas f
        $where
    ";
    
    $stmt_stats = sqlsrv_query($conn, $sql_stats, $params);
    $estadisticas = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    
    $total_vendido = (float)$estadisticas['total_vendido'];
    $total_interes = (float)$estadisticas['total_interes'];
    $capital_puro = $total_vendido - $total_interes;

    // ===========================================
    // 2. GRÁFICO: VENTAS POR TIPO DE PAGO
    // ===========================================
    $sql_tipo_pago = "
        SELECT tipo_pago, SUM(total) as total
        FROM facturas f
        $where AND estado = 'pagada'
        GROUP BY tipo_pago ORDER BY total DESC
    ";
    $stmt_tipo_pago = sqlsrv_query($conn, $sql_tipo_pago, $params);
    $tipo_pago_labels = []; $tipo_pago_datos = [];
    while ($row = sqlsrv_fetch_array($stmt_tipo_pago, SQLSRV_FETCH_ASSOC)) {
        $tipo_pago_labels[] = ucfirst($row['tipo_pago']);
        $tipo_pago_datos[] = (float)$row['total'];
    }
    
    // ===========================================
    // 3. GRÁFICO: VENTAS DIARIAS
    // ===========================================
    $sql_ventas_diarias = "
        SELECT CONVERT(VARCHAR(10), fecha, 23) as fecha, SUM(total) as total
        FROM facturas f
        $where AND estado = 'pagada'
        GROUP BY CONVERT(VARCHAR(10), fecha, 23) ORDER BY fecha
    ";
    $stmt_ventas_diarias = sqlsrv_query($conn, $sql_ventas_diarias, $params);
    $ventas_diarias_labels = []; $ventas_diarias_datos = [];
    while ($row = sqlsrv_fetch_array($stmt_ventas_diarias, SQLSRV_FETCH_ASSOC)) {
        $ventas_diarias_labels[] = date('d/m', strtotime($row['fecha']));
        $ventas_diarias_datos[] = (float)$row['total'];
    }
    
    // ===========================================
    // 4. TABLA DE FACTURAS DETALLADAS
    // ===========================================
    $sql_facturas = "
        SELECT 
            f.id, f.numero_factura, FORMAT(f.fecha, 'dd/MM/yyyy HH:mm') as fecha,
            c.nombre as cliente, u.nombre as usuario, f.tipo_pago,
            f.total, f.estado, f.monto_inicial, f.interes_porcentaje
        FROM facturas f
        INNER JOIN clientes c ON f.cliente_id = c.id
        INNER JOIN usuarios u ON f.usuario_id = u.id
        $where ORDER BY f.fecha DESC
    ";
    $stmt_facturas = sqlsrv_query($conn, $sql_facturas, $params);
    $facturas = [];
    while ($row = sqlsrv_fetch_array($stmt_facturas, SQLSRV_FETCH_ASSOC)) {
        $interes_f = 0;
        if ($row['interes_porcentaje'] > 0) {
            $mci = (float)$row['total'] - (float)$row['monto_inicial'];
            $cap = $mci / (1 + ((float)$row['interes_porcentaje'] / 100));
            $interes_f = $mci - $cap;
        }
        $facturas[] = [
            'id' => $row['id'],
            'numero_factura' => $row['numero_factura'],
            'fecha' => $row['fecha'],
            'cliente' => $row['cliente'],
            'usuario' => $row['usuario'],
            'tipo_pago' => $row['tipo_pago'],
            'total' => (float)$row['total'],
            'interes' => $interes_f,
            'capital' => (float)$row['total'] - $interes_f,
            'estado' => $row['estado']
        ];
    }
    
    // ===========================================
    // RESPUESTA FINAL
    // ===========================================
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'total_vendido' => $total_vendido,
            'total_interes' => $total_interes,
            'capital_puro' => $capital_puro,
            'total_facturas' => (int)$estadisticas['total_facturas'],
            'promedio_factura' => $estadisticas['total_facturas'] > 0 ? $total_vendido / $estadisticas['total_facturas'] : 0,
            'facturas_anuladas' => (int)$estadisticas['facturas_anuladas']
        ],
        'graficos' => [
            'tipo_pago' => [ 'labels' => $tipo_pago_labels, 'datos' => $tipo_pago_datos ],
            'ventas_diarias' => [ 'labels' => $ventas_diarias_labels, 'datos' => $ventas_diarias_datos ]
        ],
        'facturas' => $facturas
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
sqlsrv_close($conn);
?>