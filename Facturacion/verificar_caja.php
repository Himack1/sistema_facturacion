<?php
/**
 * VERIFICAR ESTADO DE CAJA
 * Punto de entrada al módulo de facturación
 * Verifica si el usuario tiene caja abierta y redirige según corresponda
 */

session_start();



// Validar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

// PREVENIR BUCLE: Si ya estamos verificando, salir
if (isset($_SESSION['verificando_caja']) && $_SESSION['verificando_caja'] === true) {
    unset($_SESSION['verificando_caja']);
    header("Location: ../dashboard.php");
    exit();
}

$_SESSION['verificando_caja'] = true;

require_once '../conexion.php';

try {
    // Verificar si existe caja abierta para este usuario
    $sql = "SELECT id, fecha_apertura, monto_inicial, 
                   total_ventas_efectivo, total_ventas_transferencia, total_ventas_credito
            FROM cajas 
            WHERE usuario_id = ? AND estado = 'abierta'";
    
    $params = array($_SESSION['usuario_id']);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Error al consultar estado de caja");
    }
    
    // Si tiene caja abierta
    if (sqlsrv_has_rows($stmt)) {
        $caja = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Actualizar variables de sesión
        $_SESSION['caja_id'] = $caja['id'];
        $_SESSION['caja_abierta'] = true;
        $_SESSION['caja_fecha_apertura'] = $caja['fecha_apertura']->format('Y-m-d H:i:s');
        $_SESSION['caja_monto_inicial'] = $caja['monto_inicial'];
        
        // Limpiar flag de verificación
        unset($_SESSION['verificando_caja']);
        
        // Redirigir a facturación
        header("Location: nueva_factura.php");
        exit();
    } else {
        // No tiene caja abierta
        $_SESSION['caja_abierta'] = false;
        unset($_SESSION['caja_id']);
        
        // Limpiar flag de verificación
        unset($_SESSION['verificando_caja']);
        
        // Redirigir a abrir caja
        header("Location: abrir_caja.php");
        exit();
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en verificar_caja.php: " . $e->getMessage());
    
    // Limpiar flag de verificación
    unset($_SESSION['verificando_caja']);
    
    // Redirigir al dashboard con mensaje de error
    $_SESSION['mensaje_error'] = "Error al verificar estado de caja. Intente nuevamente.";
    header("Location: ../dashboard.php");
    exit();
    
} finally {
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
}
?>