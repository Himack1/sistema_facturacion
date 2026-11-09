<?php
/**
 * Cambiar Estado de Usuario (Activar/Desactivar)
 * Sistema de Facturación - Solo ADMIN
 */

session_start();

// Verificar permisos
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

require_once '../conexion.php';

// Obtener parámetros
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Validar
if($id <= 0 || !in_array($estado, ['activo', 'inactivo'])) {
    header("Location: gestion_usuarios.php?error=parametros");
    exit();
}

// No permitir desactivar su propia cuenta
if($id == $_SESSION['usuario_id']) {
    header("Location: gestion_usuarios.php?error=no_desactivar_propio");
    exit();
}

// Actualizar estado
$sql = "UPDATE usuarios SET estado = ?, ultima_modificacion = GETDATE() WHERE id = ?";
$params = array($estado, $id);
$stmt = sqlsrv_prepare($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al cambiar estado: " . print_r(sqlsrv_errors(), true));
    header("Location: gestion_usuarios.php?error=database");
    exit();
}

if(sqlsrv_execute($stmt)) {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    
    $mensaje = ($estado === 'activo') ? 'activado' : 'desactivado';
    header("Location: gestion_usuarios.php?success=$mensaje");
    exit();
} else {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: gestion_usuarios.php?error=database");
    exit();
}
?>