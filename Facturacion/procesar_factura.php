<?php
/**
 * PROCESAR FACTURA
 * Backend para procesar ventas de forma transaccional
 * Maneja productos, servicios, stock, inventario y actualización de caja
 */

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
session_start();

// ==================================================
// VALIDACIONES DE SESIÓN Y MÉTODO
// ==================================================
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['caja_id'])) {
    header("Location: verificar_caja.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: nueva_factura.php");
    exit();
}

require_once '../conexion.php';
require_once '../includes/config_empresa.php';
$config_empresa = obtenerConfigEmpresa($conn);

// ==================================================
// RECIBIR Y VALIDAR DATOS
// ==================================================
$cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
$tipo_pago = isset($_POST['tipo_pago']) ? trim($_POST['tipo_pago']) : '';
$efectivo_recibido = isset($_POST['efectivo_recibido']) ? floatval($_POST['efectivo_recibido']) : 0;
$descuento_global = isset($_POST['descuento_global']) ? floatval($_POST['descuento_global']) : 0.00;
$total_impuesto_recibido = isset($_POST['total_impuesto']) ? floatval($_POST['total_impuesto']) : 0.00;
$notas = isset($_POST['notas']) ? trim($_POST['notas']) : '';
$carrito_json = isset($_POST['carrito']) ? $_POST['carrito'] : '';

// Variables de plazos
$es_a_plazos = isset($_POST['es_a_plazos']) ? intval($_POST['es_a_plazos']) : 0;
$frecuencia_plazos = isset($_POST['frecuencia_plazos']) ? trim($_POST['frecuencia_plazos']) : '';
$cantidad_cuotas = isset($_POST['cantidad_cuotas']) ? intval($_POST['cantidad_cuotas']) : 0;
$monto_inicial = isset($_POST['monto_inicial']) ? floatval($_POST['monto_inicial']) : 0;
$interes_porcentaje = isset($_POST['interes_porcentaje']) ? floatval($_POST['interes_porcentaje']) : 0;

// Validar que existan datos
if (empty($carrito_json) || $cliente_id <= 0) {
    $_SESSION['mensaje_error'] = "Datos de factura inválidos";
    header("Location: nueva_factura.php?error=datos_invalidos");
    exit();
}

// Decodificar carrito
$carrito = json_decode($carrito_json, true);
if (!is_array($carrito) || empty($carrito)) {
    $_SESSION['mensaje_error'] = "Carrito vacío o inválido";
    header("Location: nueva_factura.php?error=carrito_invalido");
    exit();
}

// Validar tipo de pago
$tipos_pago_validos = array('efectivo', 'transferencia', 'credito');
if (!in_array($tipo_pago, $tipos_pago_validos)) {
    $_SESSION['mensaje_error'] = "Tipo de pago inválido";
    header("Location: nueva_factura.php?error=tipo_pago");
    exit();
}

try {
    // ==================================================
    // CALCULAR TOTALES
    // ==================================================
    $subtotal = 0;
    $total_descuentos_linea = 0;
    
    foreach ($carrito as $item) {
        if (!isset($item['precio']) || !isset($item['cantidad'])) {
            throw new Exception("Datos de item inválidos en carrito");
        }
        
        $precio_unitario = floatval($item['precio']);
        $cantidad = intval($item['cantidad']);
        $subtotal_linea = $precio_unitario * $cantidad;
        
        // Descuento por línea (si existe)
        $descuento_linea = isset($item['descuento_linea']) ? floatval($item['descuento_linea']) : 0.00;
        
        // Validar descuento por línea
        if ($descuento_linea < 0) {
            throw new Exception("El descuento por línea no puede ser negativo");
        }
        
        if ($descuento_linea > $subtotal_linea) {
            throw new Exception("El descuento por línea no puede ser mayor al subtotal de la línea para: " . $item['nombre']);
        }
        
        $subtotal += $subtotal_linea;
        $total_descuentos_linea += $descuento_linea;
    }
    
    // Configuración de impuestos
    $aplicar_impuestos = isset($config_empresa['aplicar_impuestos']) && $config_empresa['aplicar_impuestos'] == 1;
    $itbis_global = floatval($config_empresa['itbis_porcentaje'] ?? 18);
    $impuesto_acumulado = 0.00;
    
    // Subtotal después de descuentos por línea
    $subtotal_con_descuentos_linea = $subtotal - $total_descuentos_linea;
    
    // Validar que el descuento global no sea mayor al subtotal descontado
    if ($descuento_global > $subtotal_con_descuentos_linea) {
        throw new Exception("El descuento global no puede ser mayor al subtotal");
    }
    
    // Asegurar que el descuento global no sea negativo
    if ($descuento_global < 0) {
        $descuento_global = 0.00;
    }
    
    // Recalcular impuestos por línea si aplica
    if ($aplicar_impuestos) {
        foreach ($carrito as $item) {
            $precio_unitario = floatval($item['precio']);
            $cantidad = intval($item['cantidad']);
            $desc_linea = isset($item['descuento_linea']) ? floatval($item['descuento_linea']) : 0.00;
            $base_imponible = ($precio_unitario * $cantidad) - $desc_linea;
            
            // Usar impuesto del item o global
            $porcentaje_imp = isset($item['impuesto_porcentaje']) ? floatval($item['impuesto_porcentaje']) : $itbis_global;
            $impuesto_linea = ($base_imponible * $porcentaje_imp) / 100;
            $impuesto_acumulado += $impuesto_linea;
        }
        
        // Ajustar proporcionalmente por descuento global
        if ($descuento_global > 0 && $subtotal_con_descuentos_linea > 0) {
            $proporcion_desc = $descuento_global / $subtotal_con_descuentos_linea;
            $impuesto_acumulado = $impuesto_acumulado * (1 - $proporcion_desc);
        }
    }
    
    $impuesto = round($impuesto_acumulado, 2);
    
    // Total final: Subtotal - Descuentos por línea - Descuento global + Impuesto
    $total = round(($subtotal - $total_descuentos_linea - $descuento_global + $impuesto), 2);
    
    if ($total <= 0) {
        throw new Exception("Total de venta inválido. El total debe ser mayor a cero.");
    }

    $monto_interes = 0;
    // Si es a plazos, sumar interés al total a deber (después del inicial)
    if ($tipo_pago === 'credito' && $es_a_plazos === 1) {
        if ($monto_inicial >= $total) {
            throw new Exception("El inicial no puede ser mayor o igual al total de la factura.");
        }
        $deuda_base = $total - $monto_inicial;
        $monto_interes = $deuda_base * ($interes_porcentaje / 100);
        $total += $monto_interes; // Aumentar el total de la factura por el interés
    }
    
    // Validar efectivo recibido si el pago es en efectivo
    if ($tipo_pago === 'efectivo' && $efectivo_recibido < $total) {
        throw new Exception("Efectivo recibido insuficiente");
    }
    
    // ==================================================
    // VALIDAR CLIENTE Y LÍMITE DE CRÉDITO
    // ==================================================
    $sql_cliente = "SELECT 
                        id, 
                        nombre, 
                        limite_credito,
                        ISNULL(credito_consumido, 0) as credito_consumido,
                        (limite_credito - ISNULL(credito_consumido, 0)) as credito_disponible
                    FROM clientes 
                    WHERE id = ? AND estado = 'activo'";
    $stmt_cliente = sqlsrv_query($conn, $sql_cliente, array($cliente_id));
    
    if ($stmt_cliente === false || !sqlsrv_has_rows($stmt_cliente)) {
        throw new Exception("Cliente no encontrado o inactivo");
    }
    
    $cliente = sqlsrv_fetch_array($stmt_cliente, SQLSRV_FETCH_ASSOC);
    
    // Validar límite de crédito si el pago es a crédito
    if ($tipo_pago === 'credito') {
        $credito_disponible = $cliente['credito_disponible'];
        
        if ($total > $credito_disponible) {
            $_SESSION['mensaje_error'] = sprintf(
                "Crédito insuficiente. Disponible: RD$ %.2f | Requerido: RD$ %.2f",
                $credito_disponible,
                $total
            );
            header("Location: nueva_factura.php?error=limite_credito");
            exit();
        }
    }
    
    // ==================================================
    // VALIDAR STOCK DE PRODUCTOS
    // ==================================================
    foreach ($carrito as $item) {
        $producto_id = intval($item['id']);
        $cantidad_solicitada = intval($item['cantidad']);
        
        if (isset($item['aplica']) && $item['aplica'] === true) {
            $sql_stock = "SELECT stock_actual, nombre FROM productos WHERE id = ? AND estado = 'activo'";
            $stmt_stock = sqlsrv_query($conn, $sql_stock, array($producto_id));
            
            if ($stmt_stock === false || !sqlsrv_has_rows($stmt_stock)) {
                throw new Exception("Producto no encontrado: " . $item['nombre']);
            }
            
            $prod = sqlsrv_fetch_array($stmt_stock, SQLSRV_FETCH_ASSOC);
            
            if ($prod['stock_actual'] < $cantidad_solicitada) {
                throw new Exception("Stock insuficiente para: " . $prod['nombre']);
            }
        }
    }
    
    // ==================================================
    // GENERAR NÚMERO DE FACTURA
    // ==================================================
    $sql_numero = "SELECT dbo.fn_GenerarNumeroFactura() AS numero";
    $stmt_numero = sqlsrv_query($conn, $sql_numero);
    
    if ($stmt_numero === false || !sqlsrv_has_rows($stmt_numero)) {
        throw new Exception("Error al generar número de factura");
    }
    
    $row_numero = sqlsrv_fetch_array($stmt_numero, SQLSRV_FETCH_ASSOC);
    $numero_factura = $row_numero['numero'];
    
    // ==================================================
    // INICIAR TRANSACCIÓN
    // ==================================================
    if (!sqlsrv_begin_transaction($conn)) {
        throw new Exception("No se pudo iniciar la transacción");
    }
    
    // ==================================================
    // 1. INSERTAR ENCABEZADO DE FACTURA
    // ==================================================
    $sql_factura = "INSERT INTO facturas (
                        numero_factura, 
                        fecha,
                        cliente_id, 
                        usuario_id, 
                        caja_id, 
                        tipo_pago, 
                        efectivo_recibido,
                        subtotal, 
                        descuento,
                        impuesto,
                        total, 
                        estado,
                        observaciones,
                        es_a_plazos,
                        frecuencia_plazos,
                        cantidad_cuotas,
                        interes_porcentaje,
                        monto_inicial,
                        monto_pagado
                    ) VALUES (?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $estado_factura = ($tipo_pago === 'credito') ? 'pendiente' : 'pagada';

    $params_factura = array(
        $numero_factura,
        $cliente_id,
        $_SESSION['usuario_id'],
        $_SESSION['caja_id'],
        $tipo_pago,
        $tipo_pago === 'efectivo' ? $efectivo_recibido : null,
        $subtotal,
        $descuento_global,  // Solo el descuento global en el encabezado
        $impuesto,
        $total,
        $estado_factura,
        $notas,
        $es_a_plazos,
        $frecuencia_plazos,
        $cantidad_cuotas,
        $interes_porcentaje,
        $monto_inicial,
        $monto_inicial // El monto pagado inicial
    );
    
    $stmt_factura = sqlsrv_query($conn, $sql_factura, $params_factura);
    
    if ($stmt_factura === false) {
        throw new Exception("Error al insertar factura");
    }
    
    // ==================================================
    // 2. OBTENER ID DE FACTURA GENERADA
    // ==================================================
    $sql_id = "SELECT @@IDENTITY as id";
    $stmt_id = sqlsrv_query($conn, $sql_id);
    
    if ($stmt_id === false || !sqlsrv_has_rows($stmt_id)) {
        throw new Exception("Error al obtener ID de factura");
    }
    
    $row_id = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
    $factura_id = intval($row_id['id']);

    // ==================================================
    // 2.5 INSERTAR CUOTAS SI ES A PLAZOS
    // ==================================================
    if ($tipo_pago === 'credito' && $es_a_plazos === 1 && $cantidad_cuotas > 0) {
        $deuda_total_a_financiar = $total - $monto_inicial;
        $monto_cuota_estandar = round($deuda_total_a_financiar / $cantidad_cuotas, 2);
        
        $acumulado_cuotas = 0;
        $fecha_base = new DateTime();
        
        for ($i = 1; $i <= $cantidad_cuotas; $i++) {
            // Ajustar la última cuota para cuadrar el centavo
            $monto_final_cuota = $monto_cuota_estandar;
            if ($i === (int)$cantidad_cuotas) {
                $monto_final_cuota = $deuda_total_a_financiar - $acumulado_cuotas;
            }
            $acumulado_cuotas += $monto_final_cuota;

            // Calcular fecha vencimiento
            $fecha_venc = clone $fecha_base;
            if ($frecuencia_plazos === 'semanal') {
                $fecha_venc->modify("+" . ($i * 7) . " days");
            } elseif ($frecuencia_plazos === 'quincenal') {
                $fecha_venc->modify("+" . ($i * 15) . " days");
            } elseif ($frecuencia_plazos === 'mensual') {
                $fecha_venc->modify("+" . $i . " months");
            }
            
            $fecha_venc_str = $fecha_venc->format('Y-m-d');
            
            $sql_cuota = "INSERT INTO facturas_cuotas (factura_id, numero_cuota, monto_cuota, fecha_vencimiento, estado) VALUES (?, ?, ?, ?, 'pendiente')";
            $params_cuota = array($factura_id, $i, $monto_final_cuota, $fecha_venc_str);
            $stmt_cuota = sqlsrv_query($conn, $sql_cuota, $params_cuota);
            
            if ($stmt_cuota === false) {
                throw new Exception("Error al insertar cuota #$i");
            }
        }
    }
    
    // ==================================================
    // 3. INSERTAR DETALLE DE FACTURA
    // ==================================================
    foreach ($carrito as $item) {
        $producto_id = intval($item['id']);
        $descripcion = trim($item['nombre']);
        $tipo_item = trim($item['tipo']);
        $cantidad = intval($item['cantidad']);
        $precio_unitario = floatval($item['precio']);
        
        // Descuento por línea
        $descuento_linea = isset($item['descuento_linea']) ? floatval($item['descuento_linea']) : 0.00;
        
        // Validar nuevamente el descuento por línea
        if ($descuento_linea < 0) {
            throw new Exception("Descuento por línea inválido para: " . $descripcion);
        }
        
        $subtotal_linea = $precio_unitario * $cantidad;
        
        if ($descuento_linea > $subtotal_linea) {
            throw new Exception("Descuento por línea excede el subtotal para: " . $descripcion);
        }
        
        $impuesto_linea = 0.00;
        $porcentaje_imp = 0.00;
        if ($aplicar_impuestos) {
            $porcentaje_imp = isset($item['impuesto_porcentaje']) ? floatval($item['impuesto_porcentaje']) : $itbis_global;
            $base_linea = $subtotal_linea - $descuento_linea;
            $impuesto_linea = ($base_linea * $porcentaje_imp) / 100;
            
            // Ajuste proporcional por descuento global en cada línea para coherencia
            if ($descuento_global > 0 && $subtotal_con_descuentos_linea > 0) {
                $proporcion_desc = $descuento_global / $subtotal_con_descuentos_linea;
                $impuesto_linea = $impuesto_linea * (1 - $proporcion_desc);
            }
        }
        
        $impuesto_linea = round($impuesto_linea, 2);
        
        // Total de línea = Subtotal - Descuento + Impuesto
        $total_linea = round(($subtotal_linea - $descuento_linea + $impuesto_linea), 2);
        
        $sql_detalle = "INSERT INTO detalle_factura (
                            factura_id, 
                            producto_id, 
                            descripcion, 
                            tipo_item, 
                            cantidad, 
                            precio_unitario, 
                            descuento_linea,
                            impuesto_linea,
                            total_linea,
                            impuesto_porcentaje
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params_detalle = array(
            $factura_id,
            $producto_id,
            $descripcion,
            $tipo_item,
            $cantidad,
            $precio_unitario,
            $descuento_linea,
            $impuesto_linea,
            $total_linea,
            $porcentaje_imp
        );
        
        $stmt_detalle = sqlsrv_query($conn, $sql_detalle, $params_detalle);
        
        if ($stmt_detalle === false) {
            throw new Exception("Error al insertar detalle de factura");
        }
    }
    
    // ==================================================
    // 4. PROCESAR VENTA (Stock e Inventario)
    // ==================================================
    $sql_procesar = "EXEC sp_ProcesarVenta @factura_id = ?";
    $stmt_procesar = sqlsrv_query($conn, $sql_procesar, array($factura_id));
    
    if ($stmt_procesar === false) {
        throw new Exception("Error al procesar venta (stock/inventario)");
    }
    
    // ==================================================
    // 5. ACTUALIZAR TOTALES DE CAJA Y CRÉDITO DEL CLIENTE
    // ==================================================
    $campo_caja = '';
    switch ($tipo_pago) {
        case 'efectivo':
            $campo_caja = 'total_ventas_efectivo';
            break;
        case 'transferencia':
            $campo_caja = 'total_ventas_transferencia';
            break;
        case 'credito':
            $campo_caja = 'total_ventas_credito';
            
            $saldo_a_deber = $total - $monto_inicial;
            $sql_credito = "UPDATE clientes 
                           SET credito_consumido = ISNULL(credito_consumido, 0) + ?
                           WHERE id = ?";
            // NOTA: El crédito consumido ahora solo suma lo que realmente quedó debiendo
            $stmt_credito = sqlsrv_query($conn, $sql_credito, array($saldo_a_deber, $cliente_id));
            
            if ($stmt_credito === false) {
                throw new Exception("Error al actualizar crédito del cliente");
            }

            // Si dio un inicial en efectivo, sumarlo a caja
            if ($es_a_plazos === 1 && $monto_inicial > 0) {
                $sql_caja_inicial = "UPDATE cajas SET total_ventas_efectivo = total_ventas_efectivo + ? WHERE id = ? AND estado = 'abierta'";
                $stmt_caja_inicial = sqlsrv_query($conn, $sql_caja_inicial, array($monto_inicial, $_SESSION['caja_id']));
                if ($stmt_caja_inicial === false) {
                    throw new Exception("Error al registrar el inicial en la caja");
                }
            }
            break;
        default:
            throw new Exception("Tipo de pago no reconocido");
    }
    
    $sql_caja = "UPDATE cajas 
                 SET $campo_caja = $campo_caja + ? 
                 WHERE id = ? AND estado = 'abierta'";
    
    // Si es crédito, a la caja de crédito solo va el balance pendiente
    $monto_a_caja = ($tipo_pago === 'credito') ? ($total - $monto_inicial) : $total;
    $stmt_caja = sqlsrv_query($conn, $sql_caja, array($monto_a_caja, $_SESSION['caja_id']));
    
    if ($stmt_caja === false) {
        throw new Exception("Error al actualizar totales de caja");
    }
    
    // ==================================================
    // CONFIRMAR TRANSACCIÓN
    // ==================================================
    if (!sqlsrv_commit($conn)) {
        throw new Exception("Error al confirmar transacción");
    }
    
    // ==================================================
    // ÉXITO: NOTIFICAR Y REDIRIGIR
    // ==================================================
    
    // Enviar notificación de venta al dueño (si está configurado)
    try {
        require_once '../includes/notificaciones.php';
        // Obtener nombre del cliente
        $sql_cli_nombre = "SELECT nombre FROM clientes WHERE id = ?";
        $stmt_cli_n = sqlsrv_query($conn, $sql_cli_nombre, [$cliente_id]);
        $cli_row = sqlsrv_fetch_array($stmt_cli_n, SQLSRV_FETCH_ASSOC);
        $nombre_cliente = $cli_row['nombre'] ?? 'N/A';
        
        $plazos_data = null;
        if ($tipo_pago === 'credito' && $es_a_plazos === 1) {
            $plazos_data = [
                'frecuencia' => $frecuencia_plazos,
                'cuotas' => $cantidad_cuotas,
                'interes' => $interes_porcentaje,
                'inicial' => $monto_inicial
            ];
        }
        
        notificarVenta($conn, $numero_factura, $nombre_cliente, $total, $tipo_pago, $_SESSION['usuario_nombre'] ?? 'Cajero', $plazos_data);
    } catch (Exception $notif_error) {
        error_log("Notificación de venta falló: " . $notif_error->getMessage());
    }
    
    $_SESSION['mensaje_exito'] = "Factura $numero_factura generada correctamente";
    header("Location: ver_factura.php?id=$factura_id");
    exit();
    
} catch (Exception $e) {
    // ==================================================
    // ERROR: REVERTIR TRANSACCIÓN
    // ==================================================
    // Verificar si hay una transacción activa
    $sql_check_trans = "SELECT @@TRANCOUNT AS trans_count";
    $stmt_check = sqlsrv_query($conn, $sql_check_trans);
    if ($stmt_check) {
        $trans_row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($trans_row && $trans_row['trans_count'] > 0) {
            sqlsrv_rollback($conn);
        }
    }
    
    // Obtener errores detallados de SQL Server
    $errors = sqlsrv_errors();
    $error_detail = "";
    if ($errors != null) {
        foreach ($errors as $error) {
            $error_detail .= "SQLSTATE: " . $error['SQLSTATE'] . " | ";
            $error_detail .= "Código: " . $error['code'] . " | ";
            $error_detail .= "Mensaje: " . $error['message'] . "\n";
        }
    }
    
    // Log completo del error
    error_log("=== ERROR AL PROCESAR FACTURA ===");
    error_log("Mensaje Exception: " . $e->getMessage());
    error_log("Errores SQL Server: " . $error_detail);
    error_log("Cliente ID: $cliente_id");
    error_log("Tipo Pago: $tipo_pago");
    error_log("Total: $total");
    error_log("Caja ID: " . $_SESSION['caja_id']);
    error_log("================================");
    
    // Mensaje detallado para el usuario
    $_SESSION['mensaje_error'] = $e->getMessage();
    if (!empty($error_detail)) {
        $_SESSION['mensaje_error'] .= " | Detalle técnico: " . substr($error_detail, 0, 200);
    }
    
    header("Location: nueva_factura.php?error=procesamiento");
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