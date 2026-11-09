<?php
/**
 * Obtener datos para el reporte de inventario
 * Ubicación: Reportes/ajax/obtener_datos_inventario.php
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
    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
    $categoria_filtro = isset($_GET['categoria']) ? $_GET['categoria'] : '';
    $tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    $stock_filtro = isset($_GET['stock']) ? $_GET['stock'] : '';
    
    // Construir cláusula WHERE para productos
    $where_productos = "WHERE p.estado = 'activo'";
    $params_productos = [];
    
    if (!empty($categoria_filtro)) {
        $where_productos .= " AND p.categoria_id = ?";
        $params_productos[] = $categoria_filtro;
    }
    
    if (!empty($tipo_filtro)) {
        $where_productos .= " AND p.tipo = ?";
        $params_productos[] = $tipo_filtro;
    }
    
    if (!empty($stock_filtro)) {
        if ($stock_filtro === 'critico') {
            $where_productos .= " AND p.aplica_inventario = 1 AND p.stock_actual <= 0";
        } elseif ($stock_filtro === 'bajo') {
            $where_productos .= " AND p.aplica_inventario = 1 AND p.stock_actual > 0 AND p.stock_actual <= p.stock_minimo";
        } elseif ($stock_filtro === 'normal') {
            $where_productos .= " AND p.aplica_inventario = 1 AND p.stock_actual > p.stock_minimo";
        }
    }
    
    // ===========================================
    // 1. ALERTAS
    // ===========================================
    $alertas = [
        'sin_stock' => 0,
        'bajo_stock' => 0
    ];
    
    // Productos sin stock
    $sql_sin_stock = "
        SELECT COUNT(*) as total
        FROM productos
        WHERE estado = 'activo'
        AND aplica_inventario = 1
        AND stock_actual <= 0
    ";
    $stmt_sin = sqlsrv_query($conn, $sql_sin_stock);
    if ($row = sqlsrv_fetch_array($stmt_sin, SQLSRV_FETCH_ASSOC)) {
        $alertas['sin_stock'] = $row['total'];
    }
    
    // Productos con stock bajo
    $sql_bajo_stock = "
        SELECT COUNT(*) as total
        FROM productos
        WHERE estado = 'activo'
        AND aplica_inventario = 1
        AND stock_actual > 0
        AND stock_actual <= stock_minimo
    ";
    $stmt_bajo = sqlsrv_query($conn, $sql_bajo_stock);
    if ($row = sqlsrv_fetch_array($stmt_bajo, SQLSRV_FETCH_ASSOC)) {
        $alertas['bajo_stock'] = $row['total'];
    }
    
    // ===========================================
    // 2. ESTADÍSTICAS GENERALES
    // ===========================================
    $sql_stats = "
        SELECT 
            COUNT(CASE WHEN tipo = 'producto' THEN 1 END) as total_productos,
            COUNT(CASE WHEN tipo = 'servicio' THEN 1 END) as total_servicios,
            COUNT(CASE WHEN aplica_inventario = 1 AND stock_actual <= 0 THEN 1 END) as productos_sin_stock,
            COUNT(CASE WHEN aplica_inventario = 1 AND stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 END) as productos_bajo_stock,
            ISNULL(SUM(CASE WHEN aplica_inventario = 1 THEN stock_actual * precio_costo ELSE 0 END), 0) as valor_total_inventario
        FROM productos p
        $where_productos
    ";
    
    $stmt_stats = sqlsrv_query($conn, $sql_stats, $params_productos);
    $estadisticas = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    
    // Contar movimientos en el período
    $sql_movimientos_count = "
        SELECT COUNT(*) as total
        FROM movimientos_inventario
        WHERE CONVERT(DATE, fecha_movimiento) BETWEEN ? AND ?
    ";
    $stmt_mov_count = sqlsrv_query($conn, $sql_movimientos_count, [$fecha_inicio, $fecha_fin]);
    $total_movimientos = 0;
    if ($row = sqlsrv_fetch_array($stmt_mov_count, SQLSRV_FETCH_ASSOC)) {
        $total_movimientos = $row['total'];
    }
    
    // ===========================================
    // 3. GRÁFICO: TOP 10 PRODUCTOS MÁS VENDIDOS
    // ===========================================
    $sql_top_productos = "
        SELECT TOP 10
            p.nombre,
            ISNULL(SUM(df.cantidad), 0) as total_vendido
        FROM productos p
        LEFT JOIN detalle_factura df ON p.id = df.producto_id
        LEFT JOIN facturas f ON df.factura_id = f.id
        WHERE f.estado = 'pagada'
        AND f.fecha BETWEEN ? AND ?
        GROUP BY p.nombre
        ORDER BY total_vendido DESC
    ";
    
    $stmt_top = sqlsrv_query($conn, $sql_top_productos, [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    
    $top_productos_labels = [];
    $top_productos_datos = [];
    
    while ($row = sqlsrv_fetch_array($stmt_top, SQLSRV_FETCH_ASSOC)) {
        $top_productos_labels[] = $row['nombre'];
        $top_productos_datos[] = (int)$row['total_vendido'];
    }
    
    // ===========================================
    // 4. GRÁFICO: MOVIMIENTOS DE INVENTARIO
    // ===========================================
    $sql_movimientos_tipo = "
        SELECT 
            tipo_movimiento,
            COUNT(*) as total
        FROM movimientos_inventario
        WHERE CONVERT(DATE, fecha_movimiento) BETWEEN ? AND ?
        GROUP BY tipo_movimiento
    ";
    
    $stmt_mov_tipo = sqlsrv_query($conn, $sql_movimientos_tipo, [$fecha_inicio, $fecha_fin]);
    
    $movimientos_labels = [];
    $movimientos_datos = [];
    
    while ($row = sqlsrv_fetch_array($stmt_mov_tipo, SQLSRV_FETCH_ASSOC)) {
        $movimientos_labels[] = ucfirst($row['tipo_movimiento']);
        $movimientos_datos[] = (int)$row['total'];
    }
    
    // ===========================================
    // 5. TABLA DE PRODUCTOS
    // ===========================================
    $sql_productos = "
        SELECT 
            p.id,
            p.codigo,
            p.nombre,
            ISNULL(c.nombre, 'Sin categoría') as categoria,
            p.tipo,
            p.stock_actual,
            p.stock_minimo,
            p.precio_venta,
            ISNULL((
                SELECT SUM(df.cantidad)
                FROM detalle_factura df
                INNER JOIN facturas f ON df.factura_id = f.id
                WHERE df.producto_id = p.id
                AND f.estado = 'pagada'
                AND f.fecha BETWEEN ? AND ?
            ), 0) as veces_vendido
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        $where_productos
        ORDER BY p.nombre
    ";
    
    $params_productos_tabla = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];
    foreach ($params_productos as $param) {
        $params_productos_tabla[] = $param;
    }
    
    $stmt_productos = sqlsrv_query($conn, $sql_productos, $params_productos_tabla);
    
    $productos = [];
    while ($row = sqlsrv_fetch_array($stmt_productos, SQLSRV_FETCH_ASSOC)) {
        $productos[] = [
            'id' => $row['id'],
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'categoria' => $row['categoria'],
            'tipo' => $row['tipo'],
            'stock_actual' => (int)$row['stock_actual'],
            'stock_minimo' => (int)$row['stock_minimo'],
            'precio_venta' => (float)$row['precio_venta'],
            'veces_vendido' => (int)$row['veces_vendido']
        ];
    }
    
    // ===========================================
    // 6. TABLA DE MOVIMIENTOS RECIENTES
    // ===========================================
    $sql_movimientos = "
        SELECT TOP 50
            FORMAT(mi.fecha_movimiento, 'dd/MM/yyyy HH:mm') as fecha,
            p.nombre as producto,
            mi.tipo_movimiento,
            mi.cantidad,
            mi.motivo,
            u.nombre as usuario,
            mi.referencia
        FROM movimientos_inventario mi
        INNER JOIN productos p ON mi.producto_id = p.id
        LEFT JOIN usuarios u ON mi.usuario_id = u.id
        WHERE CONVERT(DATE, mi.fecha_movimiento) BETWEEN ? AND ?
        ORDER BY mi.fecha_movimiento DESC
    ";
    
    $stmt_movimientos = sqlsrv_query($conn, $sql_movimientos, [$fecha_inicio, $fecha_fin]);
    
    $movimientos = [];
    while ($row = sqlsrv_fetch_array($stmt_movimientos, SQLSRV_FETCH_ASSOC)) {
        $movimientos[] = [
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'tipo_movimiento' => $row['tipo_movimiento'],
            'cantidad' => (int)$row['cantidad'],
            'motivo' => $row['motivo'],
            'usuario' => $row['usuario'],
            'referencia' => $row['referencia']
        ];
    }
    
    // ===========================================
    // RESPUESTA FINAL
    // ===========================================
    $response = [
        'success' => true,
        'alertas' => $alertas,
        'estadisticas' => [
            'total_productos' => (int)$estadisticas['total_productos'],
            'total_servicios' => (int)$estadisticas['total_servicios'],
            'productos_sin_stock' => (int)$estadisticas['productos_sin_stock'],
            'productos_bajo_stock' => (int)$estadisticas['productos_bajo_stock'],
            'valor_total_inventario' => (float)$estadisticas['valor_total_inventario'],
            'total_movimientos' => (int)$total_movimientos
        ],
        'graficos' => [
            'top_productos' => [
                'labels' => $top_productos_labels,
                'datos' => $top_productos_datos
            ],
            'movimientos' => [
                'labels' => $movimientos_labels,
                'datos' => $movimientos_datos
            ]
        ],
        'productos' => $productos,
        'movimientos' => $movimientos,
        'filtros_aplicados' => [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'categoria' => $categoria_filtro,
            'tipo' => $tipo_filtro,
            'stock' => $stock_filtro
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

sqlsrv_close($conn);
?>