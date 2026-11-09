<?php
/**
 * Procesar Creación de Usuario
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
    header("Location: crear_usuario.php");
    exit();
}

require_once '../conexion.php';

// Obtener datos del formulario
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'activo';

// Validaciones
if(empty($nombre) || empty($usuario) || empty($password) || empty($rol)) {
    header("Location: crear_usuario.php?error=empty");
    exit();
}

if(strlen($password) < 6) {
    header("Location: crear_usuario.php?error=short");
    exit();
}

if(!in_array($rol, ['ADMIN', 'CAJERO'])) {
    $rol = 'CAJERO';
}

if(!in_array($estado, ['activo', 'inactivo'])) {
    $estado = 'activo';
}

// Verificar si el usuario ya existe
$sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ?";
$stmt_check = sqlsrv_prepare($conn, $sql_check, array(&$usuario));

if($stmt_check === false) {
    error_log("Error al verificar usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: crear_usuario.php?error=database");
    exit();
}

sqlsrv_execute($stmt_check);
$result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

if($result['total'] > 0) {
    sqlsrv_free_stmt($stmt_check);
    header("Location: crear_usuario.php?error=exists");
    exit();
}

sqlsrv_free_stmt($stmt_check);

// Hashear contraseña
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

// Insertar nuevo usuario
$sql = "INSERT INTO usuarios (nombre, usuario, password, rol, estado) 
        VALUES (?, ?, ?, ?, ?)";

$params = array($nombre, $usuario, $password_hash, $rol, $estado);
$stmt = sqlsrv_prepare($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al crear usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: crear_usuario.php?error=database");
    exit();
}

if(sqlsrv_execute($stmt)) {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: gestion_usuarios.php?success=creado");
    exit();
} else {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: crear_usuario.php?error=database");
    exit();
}
?>