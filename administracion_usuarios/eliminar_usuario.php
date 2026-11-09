<?php
/**
 * Eliminar Usuario
 * Sistema de Facturación - Solo ADMIN
 */

session_start();

// Verificar permisos
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

require_once '../conexion.php';

// Obtener ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validar
if($id <= 0) {
    header("Location: gestion_usuarios.php?error=parametros");
    exit();
}

// No permitir eliminar su propia cuenta
if($id == $_SESSION['usuario_id']) {
    header("Location: gestion_usuarios.php?error=no_eliminar");
    exit();
}

// Eliminar usuario
$sql = "DELETE FROM usuarios WHERE id = ?";
$params = array($id);
$stmt = sqlsrv_prepare($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al eliminar usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: gestion_usuarios.php?error=database");
    exit();
}

if(sqlsrv_execute($stmt)) {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: gestion_usuarios.php?success=eliminado");
    exit();
} else {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: gestion_usuarios.php?error=database");
    exit();
}
?>