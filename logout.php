<?php
/**
 * Cerrar Sesión (Logout)
 * Sistema de Facturación
 */

// Iniciar sesión
session_start();

// Guardar el nombre de usuario antes de destruir la sesión (opcional)
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : '';

// Eliminar todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, también se debe borrar la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al login con mensaje de despedida (opcional)
header("Location: login.php?logout=success");
exit();

/**
 * Proceso de logout seguro:
 * 1. Se eliminan todas las variables de sesión
 * 2. Se borra la cookie de sesión del navegador
 * 3. Se destruye la sesión en el servidor
 * 4. Se redirige al login
 * 
 * Esto garantiza que la sesión se cierre completamente
 * y el usuario no pueda acceder sin volver a autenticarse.
 */
?>