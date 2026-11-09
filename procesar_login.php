<?php
/**
 * Procesamiento del Login
 * Sistema de Facturación
 */

// Iniciar sesión
session_start();

// Incluir archivo de conexión
require_once 'conexion.php';

// Verificar que sea una petición POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Obtener y limpiar datos del formulario
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validar que los campos no estén vacíos
if(empty($usuario) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

// Preparar la consulta SQL para buscar el usuario
$sql = "SELECT id, nombre, usuario, password, rol, estado 
        FROM usuarios 
        WHERE usuario = ?";

// Preparar y ejecutar la consulta
$stmt = sqlsrv_prepare($conn, $sql, array(&$usuario));

if($stmt === false) {
    error_log("Error al preparar consulta: " . print_r(sqlsrv_errors(), true));
    header("Location: login.php?error=database");
    exit();
}

// Ejecutar la consulta
if(sqlsrv_execute($stmt)) {
    
    // Verificar si se encontró el usuario
    if(sqlsrv_has_rows($stmt)) {
        // Obtener los datos del usuario
        $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Verificar que la cuenta esté activa
        if($user['estado'] !== 'activo') {
            sqlsrv_free_stmt($stmt);
            header("Location: login.php?error=inactive");
            exit();
        }
        
        // Verificar la contraseña usando password_verify
        if(password_verify($password, $user['password'])) {
            // Contraseña correcta - Crear sesión
            
            // Regenerar el ID de sesión por seguridad
            session_regenerate_id(true);
            
            // Guardar datos en la sesión
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['usuario_usuario'] = $user['usuario'];
            $_SESSION['usuario_rol'] = $user['rol'];
            $_SESSION['login_time'] = time();
            
            // Opcional: Registrar el último acceso en la base de datos
            $sql_update = "UPDATE usuarios 
                          SET ultima_modificacion = GETDATE() 
                          WHERE id = ?";
            $params_update = array($user['id']);
            $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);
            
            // Liberar recursos
            sqlsrv_free_stmt($stmt);
            if($stmt_update) sqlsrv_free_stmt($stmt_update);
            
            // Redirigir al dashboard
            header("Location: dashboard.php");
            exit();
            
        } else {
            // Contraseña incorrecta
            sqlsrv_free_stmt($stmt);
            header("Location: login.php?error=invalid");
            exit();
        }
        
    } else {
        // Usuario no existe
        sqlsrv_free_stmt($stmt);
        header("Location: login.php?error=invalid");
        exit();
    }
    
} else {
    // Error al ejecutar la consulta
    error_log("Error en login: " . print_r(sqlsrv_errors(), true));
    sqlsrv_free_stmt($stmt);
    header("Location: login.php?error=database");
    exit();
}

// Cerrar conexión
sqlsrv_close($conn);
 
/**
 * Notas de seguridad:
 * 1. Se usa password_verify() para comparar contraseñas hasheadas
 * 2. Se regenera el ID de sesión después del login exitoso
 * 3. No se especifica si el error es por usuario o contraseña (seguridad)
 * 4. Se verifica que la cuenta esté activa antes de permitir el acceso
 * 5. Los errores de BD se registran en el log, no se muestran al usuario
 * 6. Se usan consultas preparadas para prevenir SQL Injection
 */
?>