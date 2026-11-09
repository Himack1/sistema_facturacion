<?php
/**
 * Obtener datos para el reporte de caja
 * Ubicación: Reportes/ajax/obtener_datos_caja.php
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
    $estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
    
    // Construir cláusula WHERE base para cajas
    $where_cajas = "WHERE CONVERT(DATE, c.fecha_apertura) BETWEEN ? AND ?";
    $params_cajas = [$fecha_inicio, $fecha_fin];
    
    if (!empty($usuario_filtro)) {
        $where_cajas .= " AND c.usuario_id = ?";
        $params_cajas[] = $usuario_filtro;
    }
    
    if (!empty($estado_filtro)) {
        $where_cajas .= " AND c.estado = ?";
        $params_cajas[] = $estado_filtro;
    }
    
    // ===========================================
    // 1. ALERTAS
    // ===========================================
    $alertas = [
        'diferencias_importantes' => 0,
        'cajas_abiertas' => 0
    ];
    
    // Contar diferencias importantes (más de RD$500)
    $sql_diferencias = "
        SELECT COUNT(*) as total
        FROM cajas c
        $where_cajas
        AND ABS(ISNULL(c.monto_final_real, 0) - ISNULL(c.monto_final_esperado, 0)) > 500
        AND c.estado = 'cerrada'
    ";
    
    $stmt_dif = sqlsrv_query($conn, $sql_diferencias, $params_cajas);
    if ($stmt_dif === false) {
        throw new Exception('Error en consulta de diferencias: ' . print_r(sqlsrv_errors(), true));
    }
    
    if ($row = sqlsrv_fetch_array($stmt_dif, SQLSRV_FETCH_ASSOC)) {
        $alertas['diferencias_importantes'] = $row['total'];
    }
    
    // Contar cajas abiertas
    $sql_abiertas = "SELECT COUNT(*) as total FROM cajas WHERE estado = 'abierta'";
    $stmt_ab = sqlsrv_query($conn, $sql_abiertas);
    if ($stmt_ab === false) {
        throw new Exception('Error en consulta de cajas abiertas: ' . print_r(sqlsrv_errors(), true));
    }
    
    if ($row = sqlsrv_fetch_array($stmt_ab, SQLSRV_FETCH_ASSOC)) {
        $alertas['cajas_abiertas'] = $row['total'];
    }
    
    // ===========================================
    // 2. ESTADÍSTICAS GENERALES
    // ===========================================
    $sql_stats = "
        SELECT 
            -- Ventas directas
            ISNULL((SELECT SUM(f.total) FROM facturas f WHERE f.caja_id = c.id AND f.estado = 'pagada' AND f.tipo_pago = 'efectivo'), 0) as v_efectivo,
            ISNULL((SELECT SUM(f.total) FROM facturas f WHERE f.caja_id = c.id AND f.estado = 'pagada' AND f.tipo_pago = 'transferencia'), 0) as v_transferencia,
            ISNULL((SELECT SUM(f.total) FROM facturas f WHERE f.caja_id = c.id AND f.estado = 'pagada' AND f.tipo_pago = 'credito'), 0) as v_credito,
            
            -- Abonos a créditos realizados en esta caja
            ISNULL((SELECT SUM(p.monto) FROM pagos_credito p WHERE p.caja_id = c.id AND p.metodo_pago = 'efectivo'), 0) as a_efectivo,
            ISNULL((SELECT SUM(p.monto) FROM pagos_credito p WHERE p.caja_id = c.id AND p.metodo_pago = 'transferencia'), 0) as a_transferencia,
            
            -- Diferencia calculada
            ISNULL(c.monto_final_real - c.monto_final_esperado, 0) as diferencia,
            CASE WHEN c.estado = 'cerrada' THEN 1 ELSE 0 END as caja_cerrada,
            CASE WHEN (c.monto_final_real - c.monto_final_esperado) > 0 THEN 1 ELSE 0 END as diferencia_positiva,
            CASE WHEN (c.monto_final_real - c.monto_final_esperado) < 0 THEN 1 ELSE 0 END as diferencia_negativa,
            
            -- Total volumen de ventas
            ISNULL((SELECT SUM(f.total) FROM facturas f WHERE f.caja_id = c.id AND f.estado = 'pagada'), 0) as total_ventas_caja
        FROM cajas c
        $where_cajas
    ";
    
    $stmt_stats = sqlsrv_query($conn, $sql_stats, $params_cajas);
    if ($stmt_stats === false) throw new Exception('Error en stats caja');
    
    $estadisticas_acumuladas = [
        'total_efectivo' => 0,
        'total_transferencia' => 0,
        'total_credito' => 0,
        'total_abonos_credito' => 0,
        'total_diferencias' => 0,
        'cajas_cerradas' => 0,
        'diferencias_positivas' => 0,
        'diferencias_negativas' => 0,
        'total_ventas_cerradas' => 0,
        'promedio_caja' => 0
    ];
    
    while ($row = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC)) {
        $efectivo_total = (float)$row['v_efectivo'] + (float)$row['a_efectivo'];
        $transf_total = (float)$row['v_transferencia'] + (float)$row['a_transferencia'];
        $abonos_total = (float)$row['a_efectivo'] + (float)$row['a_transferencia'];

        $estadisticas_acumuladas['total_efectivo'] += $efectivo_total;
        $estadisticas_acumuladas['total_transferencia'] += $transf_total;
        $estadisticas_acumuladas['total_credito'] += (float)$row['v_credito'];
        $estadisticas_acumuladas['total_abonos_credito'] += $abonos_total;
        $estadisticas_acumuladas['total_diferencias'] += (float)$row['diferencia'];
        $estadisticas_acumuladas['cajas_cerradas'] += (int)$row['caja_cerrada'];
        $estadisticas_acumuladas['diferencias_positivas'] += (int)$row['diferencia_positiva'];
        $estadisticas_acumuladas['diferencias_negativas'] += (int)$row['diferencia_negativa'];
        $estadisticas_acumuladas['total_ventas_cerradas'] += (float)$row['total_ventas_caja'];
    }
    
    // Calcular promedio por caja cerrada
    if ($estadisticas_acumuladas['cajas_cerradas'] > 0) {
        $estadisticas_acumuladas['promedio_caja'] = 
            $estadisticas_acumuladas['total_ventas_cerradas'] / $estadisticas_acumuladas['cajas_cerradas'];
    }
    
    // ===========================================
    // 3. GRÁFICO: VENTAS POR TIPO DE PAGO - VERSIÓN SIMPLIFICADA
    // ===========================================
    // Primero obtenemos los IDs de cajas que cumplen los filtros
    $sql_ids_cajas = "SELECT id FROM cajas c $where_cajas";
    $stmt_ids = sqlsrv_query($conn, $sql_ids_cajas, $params_cajas);
    
    $tipo_pago_labels = ['Efectivo', 'Transferencia', 'Crédito'];
    $tipo_pago_datos = [0, 0, 0];
    
    if ($stmt_ids !== false) {
        $ids_cajas = [];
        while ($row = sqlsrv_fetch_array($stmt_ids, SQLSRV_FETCH_ASSOC)) {
            $ids_cajas[] = $row['id'];
        }
        
        if (!empty($ids_cajas)) {
            // Crear placeholders para la consulta IN
            $placeholders = str_repeat('?,', count($ids_cajas) - 1) . '?';
            
            // Consulta para ventas por tipo de pago
            $sql_tipo_pago = "
                SELECT 
                    f.tipo_pago,
                    ISNULL(SUM(f.total), 0) as total
                FROM facturas f
                WHERE f.caja_id IN ($placeholders)
                AND f.estado = 'pagada'
                GROUP BY f.tipo_pago
            ";
            
            $stmt_tipo_pago = sqlsrv_query($conn, $sql_tipo_pago, $ids_cajas);
            
            if ($stmt_tipo_pago !== false) {
                // Resetear datos
                $tipo_pago_datos = [0, 0, 0];
                
                while ($row = sqlsrv_fetch_array($stmt_tipo_pago, SQLSRV_FETCH_ASSOC)) {
                    $tipo = strtolower($row['tipo_pago']);
                    $total = (float)$row['total'];
                    
                    if ($tipo === 'efectivo') {
                        $tipo_pago_datos[0] = $total;
                    } elseif ($tipo === 'transferencia') {
                        $tipo_pago_datos[1] = $total;
                    } elseif ($tipo === 'credito') {
                        $tipo_pago_datos[2] = $total;
                    }
                }
            }
        }
    }
    
    // ===========================================
    // 4. GRÁFICO: DIFERENCIAS POR CAJA
    // ===========================================
    $sql_diferencias_graf = "
        SELECT TOP 10
            CONCAT('Caja #', c.id) as caja,
            (c.monto_final_real - c.monto_final_esperado) as diferencia
        FROM cajas c
        $where_cajas
        AND c.estado = 'cerrada'
        AND c.monto_final_real IS NOT NULL
        AND c.monto_final_esperado IS NOT NULL
        ORDER BY ABS(c.monto_final_real - c.monto_final_esperado) DESC
    ";
    
    $stmt_dif_graf = sqlsrv_query($conn, $sql_diferencias_graf, $params_cajas);
    
    $diferencias_labels = [];
    $diferencias_datos = [];
    $diferencias_colores = [];
    
    if ($stmt_dif_graf !== false) {
        while ($row = sqlsrv_fetch_array($stmt_dif_graf, SQLSRV_FETCH_ASSOC)) {
            $diferencias_labels[] = $row['caja'];
            $diferencia_valor = (float)$row['diferencia'];
            $diferencias_datos[] = $diferencia_valor;
            
            // Color según diferencia
            if ($diferencia_valor > 0) {
                $diferencias_colores[] = 'rgba(52, 152, 219, 0.7)'; // Azul (sobrante)
            } elseif ($diferencia_valor < 0) {
                $diferencias_colores[] = 'rgba(231, 76, 60, 0.7)'; // Rojo (faltante)
            } else {
                $diferencias_colores[] = 'rgba(39, 174, 96, 0.7)'; // Verde (correcto)
            }
        }
    }
    
    // ===========================================
    // 5. TABLA DE CAJAS
    // ===========================================
    $sql_cajas = "
        SELECT 
            c.id,
            u.nombre as usuario,
            FORMAT(c.fecha_apertura, 'dd/MM/yyyy HH:mm') as fecha_apertura,
            FORMAT(c.fecha_cierre, 'dd/MM/yyyy HH:mm') as fecha_cierre,
            c.monto_inicial,
            ISNULL((
                SELECT SUM(f.total) 
                FROM facturas f 
                WHERE f.caja_id = c.id 
                AND f.estado = 'pagada'
            ), 0) as total_ventas,
            c.monto_final_esperado,
            c.monto_final_real,
            ISNULL(c.monto_final_real - c.monto_final_esperado, 0) as diferencia,
            c.estado
        FROM cajas c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        $where_cajas
        ORDER BY c.fecha_apertura DESC
    ";
    
    $stmt_cajas = sqlsrv_query($conn, $sql_cajas, $params_cajas);
    if ($stmt_cajas === false) {
        throw new Exception('Error en consulta cajas: ' . print_r(sqlsrv_errors(), true));
    }
    
    $cajas = [];
    while ($row = sqlsrv_fetch_array($stmt_cajas, SQLSRV_FETCH_ASSOC)) {
        $cajas[] = [
            'id' => $row['id'],
            'usuario' => $row['usuario'],
            'fecha_apertura' => $row['fecha_apertura'],
            'fecha_cierre' => $row['fecha_cierre'],
            'monto_inicial' => (float)$row['monto_inicial'],
            'total_ventas' => (float)$row['total_ventas'],
            'monto_final_esperado' => (float)$row['monto_final_esperado'],
            'monto_final_real' => (float)$row['monto_final_real'],
            'diferencia' => (float)$row['diferencia'],
            'estado' => $row['estado']
        ];
    }
    
    // ===========================================
    // 6. TABLA DE MOVIMIENTOS MANUALES
    // ===========================================
    $movimientos = [];
    
    // Reutilizar los IDs de cajas que ya obtuvimos
    if (!empty($ids_cajas)) {
        $placeholders = str_repeat('?,', count($ids_cajas) - 1) . '?';
        $sql_movimientos = "
            SELECT 
                FORMAT(mc.fecha_movimiento, 'dd/MM/yyyy HH:mm') as fecha,
                mc.caja_id,
                u.nombre as usuario,
                mc.tipo,
                mc.concepto,
                mc.monto
            FROM movimientos_caja mc
            INNER JOIN usuarios u ON mc.usuario_id = u.id
            WHERE mc.caja_id IN ($placeholders)
            ORDER BY mc.fecha_movimiento DESC
        ";
        
        $stmt_movimientos = sqlsrv_query($conn, $sql_movimientos, $ids_cajas);
        
        if ($stmt_movimientos !== false) {
            while ($row = sqlsrv_fetch_array($stmt_movimientos, SQLSRV_FETCH_ASSOC)) {
                $movimientos[] = [
                    'fecha' => $row['fecha'],
                    'caja_id' => $row['caja_id'],
                    'usuario' => $row['usuario'],
                    'tipo' => $row['tipo'],
                    'concepto' => $row['concepto'],
                    'monto' => (float)$row['monto']
                ];
            }
        }
    }
    
    // ===========================================
    // RESPUESTA FINAL
    // ===========================================
    $response = [
        'success' => true,
        'alertas' => $alertas,
        'estadisticas' => [
            'total_efectivo' => (float)$estadisticas_acumuladas['total_efectivo'],
            'total_transferencia' => (float)$estadisticas_acumuladas['total_transferencia'],
            'total_credito' => (float)$estadisticas_acumuladas['total_credito'],
            'total_abonos_credito' => (float)$estadisticas_acumuladas['total_abonos_credito'],
            'total_diferencias' => (float)$estadisticas_acumuladas['total_diferencias'],
            'cajas_cerradas' => (int)$estadisticas_acumuladas['cajas_cerradas'],
            'diferencias_positivas' => (int)$estadisticas_acumuladas['diferencias_positivas'],
            'diferencias_negativas' => (int)$estadisticas_acumuladas['diferencias_negativas'],
            'promedio_caja' => round((float)$estadisticas_acumuladas['promedio_caja'], 2)
        ],
        'graficos' => [
            'tipo_pago' => [
                'labels' => $tipo_pago_labels,
                'datos' => $tipo_pago_datos
            ],
            'diferencias' => [
                'labels' => $diferencias_labels,
                'datos' => $diferencias_datos,
                'colores' => $diferencias_colores
            ]
        ],
        'cajas' => $cajas,
        'movimientos' => $movimientos,
        'filtros_aplicados' => [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'usuario' => $usuario_filtro,
            'estado' => $estado_filtro
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