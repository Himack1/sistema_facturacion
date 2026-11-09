<?php
/**
 * Procesamiento del Registro
 * Sistema de Facturación
 */

// Iniciar sesión
session_start();

// Incluir archivo de conexión
require_once 'conexion.php';

// Verificar que sea una petición POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

// Obtener y limpiar datos del formulario
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// El rol siempre será CAJERO para registros públicos
// Solo los administradores pueden crear otros ADMIN desde el panel de administración
$rol = 'CAJERO';

// Validar que los campos no estén vacíos
if(empty($nombre) || empty($usuario) || empty($password)) {
    header("Location: register.php?error=empty");
    exit();
}

// Validar longitud de la contraseña
if(strlen($password) < 6) {
    header("Location: register.php?error=short");
    exit();
}

// Validar que el usuario no tenga espacios
if(strpos($usuario, ' ') !== false) {
    header("Location: register.php?error=spaces");
    exit();
}

// Verificar si el usuario ya existe
$sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ?";
$stmt_check = sqlsrv_prepare($conn, $sql_check, array(&$usuario));

if($stmt_check === false) {
    error_log("Error al verificar usuario: " . print_r(sqlsrv_errors(), true));
    header("Location: register.php?error=database");
    exit();
}

sqlsrv_execute($stmt_check);
$result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

if($result['total'] > 0) {
    // El usuario ya existe
    sqlsrv_free_stmt($stmt_check);
    header("Location: register.php?error=exists");
    exit();
}

sqlsrv_free_stmt($stmt_check);

// Hashear la contraseña usando password_hash con BCRYPT
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

// Preparar la consulta SQL para insertar el nuevo usuario
$sql = "INSERT INTO usuarios (nombre, usuario, password, rol, estado) 
        VALUES (?, ?, ?, ?, 'activo')";

$params = array($nombre, $usuario, $password_hash, $rol);
$stmt = sqlsrv_prepare($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al preparar inserción: " . print_r(sqlsrv_errors(), true));
    header("Location: register.php?error=database");
    exit();
}

// Ejecutar la inserción
if(sqlsrv_execute($stmt)) {
    // Registro exitoso
    
    // Obtener el ID del usuario recién creado
    $sql_last_id = "SELECT @@IDENTITY as id";
    $stmt_id = sqlsrv_query($conn, $sql_last_id);
    $row_id = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
    $usuario_id = $row_id['id'];
    
    // Liberar recursos
    sqlsrv_free_stmt($stmt);
    sqlsrv_free_stmt($stmt_id);
    
    // Login automático después del registro
    // Regenerar el ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Guardar datos en la sesión
    $_SESSION['usuario_id'] = $usuario_id;
    $_SESSION['usuario_nombre'] = $nombre;
    $_SESSION['usuario_usuario'] = $usuario;
    $_SESSION['usuario_rol'] = $rol;
    $_SESSION['login_time'] = time();
    
    // Cerrar conexión
    sqlsrv_close($conn);
    
    // Redirigir directamente al dashboard
    header("Location: dashboard.php?registro=nuevo");
    exit();
    
} else {
    // Error al insertar
    $errors = sqlsrv_errors();
    error_log("Error en registro: " . print_r($errors, true));
    
    sqlsrv_free_stmt($stmt);
    
    // Verificar si es error de usuario duplicado
    if(isset($errors[0]['code']) && $errors[0]['code'] == 2627) {
        header("Location: register.php?error=exists");
    } else {
        header("Location: register.php?error=database");
    }
    exit();
}

// Cerrar conexión
sqlsrv_close($conn);

/**
 * Notas importantes:
 * 1. Se usa password_hash() con BCRYPT para mayor seguridad
 * 2. Se verifica que el usuario no exista antes de insertar
 * 3. Después del registro exitoso, se hace login automático
 * 4. El rol por defecto es CAJERO si no se especifica otro
 * 5. Todas las contraseñas se hashean antes de guardarlas
 * 6. Se usan consultas preparadas para prevenir SQL Injection
 * 
 * Seguridad:
 * - Las contraseñas NUNCA se almacenan en texto plano
 * - Se validan los datos antes de procesarlos
 * - Los errores de BD no se muestran al usuario
 */
?>