<?php
/**
 * Procesar Edición de Usuario
 * Sistema de Facturación - Solo ADMIN
 */

session_start();

// Verificar permisos
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

// Verificar método POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gestion_usuarios.php");
    exit();
}

require_once '../conexion.php';

// Obtener datos del formulario
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
$cambiar_password = isset($_POST['cambiar_password']) && $_POST['cambiar_password'] == '1';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validaciones
if($id <= 0 || empty($nombre) || empty($usuario) || empty($rol) || empty($estado)) {
    header("Location: editar_usuario.php?id=$id&error=empty");
    exit();
}

if(!in_array($rol, ['ADMIN', 'CAJERO'])) {
    $rol = 'CAJERO';
}

if(!in_array($estado, ['activo', 'inactivo'])) {
    $estado = 'activo';
}

// Si quiere cambiar la contraseña, validarla
if($cambiar_password) {
    if(strlen($password) < 6) {
        header("Location: editar_usuario.php?id=$id&error=short");
        exit();
    }
}

// Verificar si el usuario ya existe (excepto el actual)
$sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ? AND id != ?";
$params_check = array($usuario, $id);
$stmt_check = sqlsrv_prepare($conn, $sql_check, $params_check);

if($stmt_check === false) {
    error_log("Error al verificar usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: editar_usuario.php?id=$id&error=database");
    exit();
}

sqlsrv_execute($stmt_check);
$result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

if($result['total'] > 0) {
    sqlsrv_free_stmt($stmt_check);
    header("Location: editar_usuario.php?id=$id&error=exists");
    exit();
}

sqlsrv_free_stmt($stmt_check);

// Preparar la actualización
if($cambiar_password) {
    // Actualizar con nueva contraseña
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    
    $sql = "UPDATE usuarios 
            SET nombre = ?, usuario = ?, password = ?, rol = ?, estado = ?, ultima_modificacion = GETDATE() 
            WHERE id = ?";
    
    $params = array($nombre, $usuario, $password_hash, $rol, $estado, $id);
} else {
    // Actualizar sin cambiar contraseña
    $sql = "UPDATE usuarios 
            SET nombre = ?, usuario = ?, rol = ?, estado = ?, ultima_modificacion = GETDATE() 
            WHERE id = ?";
    
    $params = array($nombre, $usuario, $rol, $estado, $id);
}

$stmt = sqlsrv_prepare($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al actualizar usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: editar_usuario.php?id=$id&error=database");
    exit();
}

if(sqlsrv_execute($stmt)) {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    
    // Si el usuario editado es el mismo que está logueado, actualizar la sesión
    if($id == $_SESSION['usuario_id']) {
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_usuario'] = $usuario;
        $_SESSION['usuario_rol'] = $rol;
    }
    
    header("Location: gestion_usuarios.php?success=editado");
    exit();
} else {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: editar_usuario.php?id=$id&error=database");
    exit();
}
?>