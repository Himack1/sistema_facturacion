<?php
/**
 * Eliminar Cliente
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
    header("Location: gestion_clientes.php?error=parametros");
    exit();
}

// Eliminar cliente
$sql = "DELETE FROM clientes WHERE id = ?";
$params = array($id);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al eliminar cliente: " . print_r(sqlsrv_errors(), true));
    header("Location: gestion_clientes.php?error=database");
    exit();
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
header("Location: gestion_clientes.php?success=eliminado");
exit();
?>