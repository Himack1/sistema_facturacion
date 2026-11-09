<?php
/**
 * Eliminar Producto
 * Sistema de Facturación
 */

session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id <= 0) {
    header("Location: gestion_inventario.php?error=parametros");
    exit();
}

// En un sistema de facturación, no se eliminan productos con historial (facturas, movimientos)
// para no romper la integridad de los datos. Lo marcamos como 'inactivo'.

// Cambiar estado a inactivo
$sql = "UPDATE productos SET estado = 'inactivo' WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if($stmt === false) {
    error_log("Error al inactivar producto: " . print_r(sqlsrv_errors(), true));
    header("Location: gestion_inventario.php?error=database");
    exit();
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
header("Location: gestion_inventario.php?success=eliminado");
exit();
?>