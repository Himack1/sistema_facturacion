<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}
require_once '../conexion.php';

$categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 1;

// Generar código base según categoría
$sql = "SELECT dbo.fn_GenerarCodigoProducto(?) AS codigo";
$stmt = sqlsrv_query($conn, $sql, array($categoria_id));
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$codigo_base = $row['codigo'] ?? 'PRD-001';

// Asegurar unicidad real en la tabla (por si hay colisiones)
$suffix = 0;
$codigo_final = $codigo_base;

do {
    $sql_check = "SELECT COUNT(*) as total FROM productos WHERE codigo = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($codigo_final));
    $result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
    
    if($result['total'] > 0) {
        $suffix++;
        // Extraer prefijo y generar nuevo sufijo
        $parts = explode('-', $codigo_base);
        $prefijo = $parts[0];
        $num = isset($parts[1]) ? intval($parts[1]) + $suffix : $suffix;
        $codigo_final = $prefijo . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    } else {
        break;
    }
} while($suffix < 100);

header('Content-Type: application/json');
echo json_encode(['codigo' => $codigo_final]);
sqlsrv_close($conn);
?>
