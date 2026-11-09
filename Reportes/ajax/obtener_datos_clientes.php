<?php
/**
 * Obtener datos para el reporte de clientes
 * Ubicación: Reportes/ajax/obtener_datos_clientes.php
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
    $estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
    $credito_filtro = isset($_GET['credito']) ? $_GET['credito'] : '';
    
    // Construir cláusula WHERE
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($estado_filtro)) {
        $where .= " AND c.estado = ?";
        $params[] = $estado_filtro;
    }
    
    if (!empty($credito_filtro)) {
        if ($credito_filtro === 'con_credito') {
            $where .= " AND c.limite_credito > 0";
        } elseif ($credito_filtro === 'sin_credito') {
            $where .= " AND c.limite_credito = 0";
        } elseif ($credito_filtro === 'credito_excedido') {
            $where .= " AND c.credito_consumido > c.limite_credito AND c.limite_credito > 0";
        }
    }
    
    // ===========================================
    // 1. ALERTAS
    // ===========================================
    $alertas = [
        'credito_excedido' => 0,
        'credito_alto' => 0
    ];
    
    // Clientes con crédito excedido
    $sql_excedido = "
        SELECT COUNT(*) as total
        FROM clientes
        WHERE credito_consumido > limite_credito
        AND limite_credito > 0
        AND estado = 'activo'
    ";
    $stmt_exc = sqlsrv_query($conn, $sql_excedido);
    if ($row = sqlsrv_fetch_array($stmt_exc, SQLSRV_FETCH_ASSOC)) {
        $alertas['credito_excedido'] = $row['total'];
    }
    
    // Clientes cerca del límite (>80%)
    $sql_alto = "
        SELECT COUNT(*) as total
        FROM clientes
        WHERE limite_credito > 0
        AND credito_consumido <= limite_credito
        AND (credito_consumido / limite_credito) >= 0.8
        AND estado = 'activo'
    ";
    $stmt_alto = sqlsrv_query($conn, $sql_alto);
    if ($row = sqlsrv_fetch_array($stmt_alto, SQLSRV_FETCH_ASSOC)) {
        $alertas['credito_alto'] = $row['total'];
    }
    
    // ===========================================
    // 2. ESTADÍSTICAS GENERALES
    // ===========================================
    $sql_stats = "
        SELECT 
            COUNT(*) as total_clientes,
            COUNT(CASE WHEN estado = 'activo' THEN 1 END) as clientes_activos,
            COUNT(CASE WHEN limite_credito > 0 THEN 1 END) as clientes_con_credito,
            ISNULL(SUM(credito_consumido), 0) as deuda_total,
            ISNULL(SUM(limite_credito), 0) as limite_total_credito,
            
            -- Clientes en Mora (cuotas vencidas)
            ISNULL((
                SELECT COUNT(DISTINCT f.cliente_id) 
                FROM facturas_cuotas fc
                JOIN facturas f ON fc.factura_id = f.id
                WHERE fc.estado = 'pendiente' AND fc.fecha_vencimiento < GETDATE()
            ), 0) as clientes_mora,
            
            -- Monto total en Mora
            ISNULL((
                SELECT SUM(monto_cuota) 
                FROM facturas_cuotas 
                WHERE estado = 'pendiente' AND fecha_vencimiento < GETDATE()
            ), 0) as monto_mora,

            -- Interés total proyectado en cartera
            ISNULL((
                SELECT SUM((total - monto_inicial) - ((total - monto_inicial) / (1 + (interes_porcentaje / 100.0))))
                FROM facturas 
                WHERE interes_porcentaje > 0 AND estado != 'anulada'
            ), 0) as interes_cartera
        FROM clientes c
        $where
    ";
    
    $stmt_stats = sqlsrv_query($conn, $sql_stats, $params);
    $estadisticas = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    
    // ===========================================
    // 3. GRÁFICO: TOP 10 MEJORES CLIENTES
    // ===========================================
    $sql_top = "
        SELECT TOP 10
            c.nombre,
            ISNULL(SUM(f.total), 0) as total_comprado
        FROM clientes c
        LEFT JOIN facturas f ON c.id = f.cliente_id
        WHERE f.estado = 'pagada'
        AND f.fecha BETWEEN ? AND ?
        GROUP BY c.nombre
        ORDER BY total_comprado DESC
    ";
    
    $stmt_top = sqlsrv_query($conn, $sql_top, [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    
    $top_clientes_labels = [];
    $top_clientes_datos = [];
    
    while ($row = sqlsrv_fetch_array($stmt_top, SQLSRV_FETCH_ASSOC)) {
        $top_clientes_labels[] = $row['nombre'];
        $top_clientes_datos[] = (float)$row['total_comprado'];
    }
    
    // ===========================================
    // 4. GRÁFICO: DISTRIBUCIÓN DE CRÉDITO
    // ===========================================
    $sql_dist = "
        SELECT 
            COUNT(CASE WHEN limite_credito = 0 THEN 1 END) as sin_credito,
            COUNT(CASE WHEN limite_credito > 0 AND credito_consumido < (limite_credito * 0.8) THEN 1 END) as credito_disponible,
            COUNT(CASE WHEN limite_credito > 0 AND credito_consumido >= (limite_credito * 0.8) AND credito_consumido <= limite_credito THEN 1 END) as credito_limitado,
            COUNT(CASE WHEN credito_consumido > limite_credito AND limite_credito > 0 THEN 1 END) as credito_excedido
        FROM clientes
        WHERE estado = 'activo'
    ";
    
    $stmt_dist = sqlsrv_query($conn, $sql_dist);
    $distribucion = sqlsrv_fetch_array($stmt_dist, SQLSRV_FETCH_ASSOC);
    
    $distribucion_labels = ['Disponible', 'Limitado', 'Excedido', 'Sin Crédito'];
    $distribucion_datos = [
        (int)$distribucion['credito_disponible'],
        (int)$distribucion['credito_limitado'],
        (int)$distribucion['credito_excedido'],
        (int)$distribucion['sin_credito']
    ];
    
    // ===========================================
    // 5. TABLA DE CLIENTES
    // ===========================================
    $sql_clientes = "
        SELECT 
            c.id,
            c.codigo,
            c.nombre,
            c.documento,
            c.telefono,
            c.celular,
            c.estado,
            c.limite_credito,
            c.credito_consumido,
            c.credito_disponible,
            ISNULL((
                SELECT SUM(f.total)
                FROM facturas f
                WHERE f.cliente_id = c.id
                AND f.estado = 'pagada'
                AND f.fecha BETWEEN ? AND ?
            ), 0) as total_comprado,
            (
                SELECT TOP 1 FORMAT(f.fecha, 'dd/MM/yyyy')
                FROM facturas f
                WHERE f.cliente_id = c.id
                AND f.estado = 'pagada'
                ORDER BY f.fecha DESC
            ) as ultima_compra
        FROM clientes c
        $where
        ORDER BY c.nombre
    ";
    
    $params_clientes = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];
    foreach ($params as $param) {
        $params_clientes[] = $param;
    }
    
    $stmt_clientes = sqlsrv_query($conn, $sql_clientes, $params_clientes);
    
    $clientes = [];
    while ($row = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)) {
        $clientes[] = [
            'id' => $row['id'],
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'documento' => $row['documento'],
            'telefono' => $row['telefono'],
            'celular' => $row['celular'],
            'estado' => $row['estado'],
            'limite_credito' => (float)$row['limite_credito'],
            'credito_consumido' => (float)$row['credito_consumido'],
            'credito_disponible' => (float)$row['credito_disponible'],
            'total_comprado' => (float)$row['total_comprado'],
            'ultima_compra' => $row['ultima_compra']
        ];
    }
    
    // ===========================================
    // 6. TABLA DE CLIENTES CON CRÉDITO ACTIVO
    // ===========================================
    $sql_credito = "
        SELECT 
            c.id,
            c.nombre,
            c.limite_credito,
            c.credito_consumido,
            c.credito_disponible
        FROM clientes c
        WHERE c.limite_credito > 0
        AND c.estado = 'activo'
        ORDER BY (c.credito_consumido / c.limite_credito) DESC
    ";
    
    $stmt_credito = sqlsrv_query($conn, $sql_credito);
    
    $clientes_credito = [];
    while ($row = sqlsrv_fetch_array($stmt_credito, SQLSRV_FETCH_ASSOC)) {
        $clientes_credito[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'limite_credito' => (float)$row['limite_credito'],
            'credito_consumido' => (float)$row['credito_consumido'],
            'credito_disponible' => (float)$row['credito_disponible']
        ];
    }
    
    // ===========================================
    // RESPUESTA FINAL
    // ===========================================
    $response = [
        'success' => true,
        'alertas' => $alertas,
        'estadisticas' => [
            'total_clientes' => (int)$estadisticas['total_clientes'],
            'clientes_activos' => (int)$estadisticas['clientes_activos'],
            'clientes_con_credito' => (int)$estadisticas['clientes_con_credito'],
            'deuda_total' => (float)$estadisticas['deuda_total'],
            'limite_total_credito' => (float)$estadisticas['limite_total_credito'],
            'clientes_mora' => (int)$estadisticas['clientes_mora'],
            'monto_mora' => (float)$estadisticas['monto_mora'],
            'interes_cartera' => (float)$estadisticas['interes_cartera']
        ],
        'graficos' => [
            'top_clientes' => [
                'labels' => $top_clientes_labels,
                'datos' => $top_clientes_datos
            ],
            'distribucion_credito' => [
                'labels' => $distribucion_labels,
                'datos' => $distribucion_datos
            ]
        ],
        'clientes' => $clientes,
        'clientes_credito' => $clientes_credito,
        'filtros_aplicados' => [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => $estado_filtro,
            'credito' => $credito_filtro
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