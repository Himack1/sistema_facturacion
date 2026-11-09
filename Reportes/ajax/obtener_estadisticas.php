<?php
/**
 * Obtener estadísticas rápidas para el dashboard de reportes
 * Ubicación: Reportes/ajax/obtener_estadisticas.php
 */

session_start();
require_once '../../conexion.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $response = [
        'success' => true,
        'ventas_hoy' => 0,
        'cajas_abiertas' => 0,
        'clientes_activos' => 0,
        'productos_bajo_stock' => 0
    ];
    
    // 1. Ventas del día actual
    $sql_ventas = "SELECT ISNULL(SUM(total), 0) as total_ventas
                   FROM facturas
                   WHERE CONVERT(DATE, fecha) = CONVERT(DATE, GETDATE())
                   AND estado = 'pagada'";
    
    $stmt_ventas = sqlsrv_query($conn, $sql_ventas);
    if ($stmt_ventas && $row = sqlsrv_fetch_array($stmt_ventas, SQLSRV_FETCH_ASSOC)) {
        $response['ventas_hoy'] = $row['total_ventas'];
    }
    
    // 2. Cajas abiertas actualmente
    $sql_cajas = "SELECT COUNT(*) as total_cajas
                  FROM cajas
                  WHERE estado = 'abierta'";
    
    $stmt_cajas = sqlsrv_query($conn, $sql_cajas);
    if ($stmt_cajas && $row = sqlsrv_fetch_array($stmt_cajas, SQLSRV_FETCH_ASSOC)) {
        $response['cajas_abiertas'] = $row['total_cajas'];
    }
    
    // 3. Clientes activos
    $sql_clientes = "SELECT COUNT(*) as total_clientes
                     FROM clientes
                     WHERE estado = 'activo'";
    
    $stmt_clientes = sqlsrv_query($conn, $sql_clientes);
    if ($stmt_clientes && $row = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)) {
        $response['clientes_activos'] = $row['total_clientes'];
    }
    
    // 4. Productos con stock bajo o sin stock
    $sql_productos = "SELECT COUNT(*) as total_productos
                      FROM productos
                      WHERE aplica_inventario = 1
                      AND stock_actual <= stock_minimo
                      AND estado = 'activo'";
    
    $stmt_productos = sqlsrv_query($conn, $sql_productos);
    if ($stmt_productos && $row = sqlsrv_fetch_array($stmt_productos, SQLSRV_FETCH_ASSOC)) {
        $response['productos_bajo_stock'] = $row['total_productos'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}

sqlsrv_close($conn);
?>