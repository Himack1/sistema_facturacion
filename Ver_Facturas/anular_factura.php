<?php
/**
 * ANULAR FACTURA
 * Procesa la anulación de facturas
 * Solo para usuarios ADMIN
 * Revierte stock y marca factura como anulada
 */

session_start();

// ==================================================
// VALIDACIONES DE SEGURIDAD
// ==================================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

// Solo admins pueden anular facturas
if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    $_SESSION['mensaje_error'] = "No tienes permisos para anular facturas";
    header("Location: consultar_facturas.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: consultar_facturas.php");
    exit();
}

require_once '../conexion.php';

// ==================================================
// RECIBIR DATOS
// ==================================================
$factura_id = isset($_POST['factura_id']) ? intval($_POST['factura_id']) : 0;
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';

// Validaciones
if ($factura_id <= 0) {
    $_SESSION['mensaje_error'] = "ID de factura inválido";
    header("Location: consultar_facturas.php");
    exit();
}

if (empty($motivo)) {
    $_SESSION['mensaje_error'] = "Debe especificar un motivo de anulación";
    header("Location: ver_factura_consulta.php?id=$factura_id");
    exit();
}

try {
    // ==================================================
    // VERIFICAR QUE LA FACTURA EXISTE Y ESTÁ PAGADA
    // ==================================================
    $sql_verificar = "SELECT 
                        f.id, 
                        f.numero_factura, 
                        f.estado, 
                        f.total, 
                        f.tipo_pago, 
                        f.caja_id,
                        f.cliente_id,
                        c.nombre as cliente_nombre
                      FROM facturas f
                      LEFT JOIN clientes c ON f.cliente_id = c.id
                      WHERE f.id = ?";
    $stmt_verificar = sqlsrv_query($conn, $sql_verificar, array($factura_id));
    
    if ($stmt_verificar === false || !sqlsrv_has_rows($stmt_verificar)) {
        throw new Exception("Factura no encontrada");
    }
    
    $factura = sqlsrv_fetch_array($stmt_verificar, SQLSRV_FETCH_ASSOC);
    
    // Verificar que esté en un estado anulable (no anulada)
    if ($factura['estado'] === 'anulada') {
        throw new Exception("Esta factura ya está anulada");
    }
    
    // ==================================================
    // INICIAR TRANSACCIÓN
    // ==================================================
    if (!sqlsrv_begin_transaction($conn)) {
        throw new Exception("No se pudo iniciar la transacción");
    }
    
    // ==================================================
    // 1. MARCAR FACTURA COMO ANULADA
    // ==================================================
    $sql_anular_factura = "UPDATE facturas 
                          SET estado = 'anulada',
                              fecha_anulacion = GETDATE(),
                              motivo_anulacion = ?,
                              usuario_anulacion_id = ?
                          WHERE id = ?";
    
    $params_anular = array($motivo, $_SESSION['usuario_id'], $factura_id);
    $stmt_anular_factura = sqlsrv_query($conn, $sql_anular_factura, $params_anular);
    
    if ($stmt_anular_factura === false) {
        throw new Exception("Error al marcar factura como anulada: " . print_r(sqlsrv_errors(), true));
    }
    
    // ==================================================
    // 2. REVERTIR INVENTARIO - DEVOLVER STOCK
    // ==================================================
    $sql_detalle = "SELECT d.producto_id, d.cantidad, p.aplica_inventario, p.nombre
                   FROM detalle_factura d
                   INNER JOIN productos p ON d.producto_id = p.id
                   WHERE d.factura_id = ? AND d.tipo_item = 'producto'";
    
    $stmt_detalle = sqlsrv_query($conn, $sql_detalle, array($factura_id));
    
    if ($stmt_detalle === false) {
        throw new Exception("Error al obtener detalle de productos: " . print_r(sqlsrv_errors(), true));
    }
    
    // Procesar cada producto
    while ($detalle = sqlsrv_fetch_array($stmt_detalle, SQLSRV_FETCH_ASSOC)) {
        // Solo revertir si aplica inventario
        if ($detalle['aplica_inventario']) {
            $producto_id = $detalle['producto_id'];
            $cantidad = $detalle['cantidad'];
            
            // Devolver stock
            $sql_devolver_stock = "UPDATE productos 
                                  SET stock_actual = stock_actual + ?,
                                      ultima_modificacion = GETDATE()
                                  WHERE id = ?";
            
            $stmt_devolver = sqlsrv_query($conn, $sql_devolver_stock, array($cantidad, $producto_id));
            
            if ($stmt_devolver === false) {
                throw new Exception("Error al devolver stock del producto: " . $detalle['nombre'] . " - " . print_r(sqlsrv_errors(), true));
            }
            
            // Registrar movimiento de inventario (devolución) - CORREGIDO
            $sql_mov_inv = "INSERT INTO movimientos_inventario 
                           (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, referencia)
                           VALUES (?, 'entrada', ?, ?, ?, ?)";
            
            $motivo_mov = "Anulación de factura " . $factura['numero_factura'] . " - " . $motivo;
            $params_mov = array($producto_id, $cantidad, $motivo_mov, $_SESSION['usuario_id'], $factura['numero_factura']);
            
            $stmt_mov = sqlsrv_query($conn, $sql_mov_inv, $params_mov);
            
            if ($stmt_mov === false) {
                throw new Exception("Error al registrar movimiento de inventario: " . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    // ==================================================
    // 3. AJUSTAR TOTALES DE CAJA (si existe caja asociada)
    // ==================================================
    if ($factura['caja_id'] && $factura['tipo_pago'] !== 'credito') {
        $campo_caja = '';
        switch ($factura['tipo_pago']) {
            case 'efectivo':
                $campo_caja = 'total_ventas_efectivo';
                break;
            case 'transferencia':
                $campo_caja = 'total_ventas_transferencia';
                break;
            case 'mixto':
                // Para tipo mixto, necesitaríamos saber la distribución
                break;
        }
        
        if ($campo_caja) {
            $sql_ajustar_caja = "UPDATE cajas 
                                 SET $campo_caja = $campo_caja - ? 
                                 WHERE id = ?";
            $params_caja = array($factura['total'], $factura['caja_id']);
            $stmt_caja = sqlsrv_query($conn, $sql_ajustar_caja, $params_caja);
            
            if ($stmt_caja === false) {
                throw new Exception("Error al ajustar totales de caja: " . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    // ==================================================
    // 4. DEVOLVER CRÉDITO AL CLIENTE (si aplica)
    // ==================================================
    if ($factura['tipo_pago'] === 'credito' && $factura['cliente_id']) {
        $sql_devolver_credito = "UPDATE clientes 
                                SET credito_consumido = ISNULL(credito_consumido, 0) - ?
                                WHERE id = ?";
        $stmt_devolver = sqlsrv_query($conn, $sql_devolver_credito, array($factura['total'], $factura['cliente_id']));
        
        if ($stmt_devolver === false) {
            throw new Exception("Error al devolver crédito al cliente: " . print_r(sqlsrv_errors(), true));
        }

        // ==================================================
        // 5. ELIMINAR O ANULAR CUOTAS DE FINANCIAMIENTO
        // ==================================================
        // Primero verificamos si tiene cuotas pagadas
        $sql_check_pagos = "SELECT COUNT(*) as pagadas FROM facturas_cuotas WHERE factura_id = ? AND estado = 'pagada'";
        $stmt_check_pagos = sqlsrv_query($conn, $sql_check_pagos, array($factura_id));
        $has_paid_cuotas = false;
        if ($stmt_check_pagos && $row_p = sqlsrv_fetch_array($stmt_check_pagos, SQLSRV_FETCH_ASSOC)) {
            if ($row_p['pagadas'] > 0) $has_paid_cuotas = true;
        }

        // Si tiene pagos, tal vez deberíamos advertir, pero por ahora procederemos a eliminarlas 
        // para limpiar la cuenta por cobrar del cliente.
        $sql_del_cuotas = "DELETE FROM facturas_cuotas WHERE factura_id = ?";
        if (!sqlsrv_query($conn, $sql_del_cuotas, array($factura_id))) {
            throw new Exception("Error al eliminar cuotas de financiamiento.");
        }
    }
    
    // ==================================================
    // CONFIRMAR TRANSACCIÓN
    // ==================================================
    if (!sqlsrv_commit($conn)) {
        throw new Exception("Error al confirmar transacción: " . print_r(sqlsrv_errors(), true));
    }
    
    // ==================================================
    // NOTIFICAR AL BOT
    // ==================================================
    try {
        require_once '../includes/notificaciones.php';
        $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
        notificarAnulacionFactura($conn, $factura['numero_factura'], $factura['cliente_nombre'], $factura['total'], $usuario_nombre, $motivo);
    } catch (Exception $ne) {
        error_log("No se pudo notificar anulación: " . $ne->getMessage());
    }
    
    // ==================================================
    // ÉXITO
    // ==================================================
    $_SESSION['mensaje_exito'] = "Factura {$factura['numero_factura']} anulada correctamente";
    header("Location: ver_factura_consulta.php?id=$factura_id");
    exit();
    
} catch (Exception $e) {
    // ==================================================
    // ERROR: REVERTIR TRANSACCIÓN
    // ==================================================
    if (isset($conn)) {
        // Verificar si hay transacción activa
        $sql_check_trans = "SELECT @@TRANCOUNT AS trans_count";
        $stmt_check = sqlsrv_query($conn, $sql_check_trans);
        if ($stmt_check) {
            $trans_row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
            if ($trans_row && $trans_row['trans_count'] > 0) {
                sqlsrv_rollback($conn);
            }
        }
    }
    
    // Log del error
    error_log("=== ERROR AL ANULAR FACTURA ===");
    error_log("Factura ID: " . $factura_id);
    error_log("Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A'));
    error_log("Mensaje: " . $e->getMessage());
    error_log("================================");
    
    // Mensaje para el usuario
    $_SESSION['mensaje_error'] = "Error al anular factura: " . $e->getMessage();
    header("Location: ver_factura_consulta.php?id=$factura_id");
    exit();
    
} finally {
    // ==================================================
    // CERRAR CONEXIÓN
    // ==================================================
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
}
?>