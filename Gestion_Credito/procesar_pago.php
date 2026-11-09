<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';
require_once '../includes/notificaciones.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id']);
    $input_id = !empty($_POST['factura_id']) ? $_POST['factura_id'] : null;
    $factura_id = null;
    $cuota_id = null;
    
    if ($input_id) {
        if (strpos($input_id, 'cuota_') === 0) {
            $cuota_id = intval(str_replace('cuota_', '', $input_id));
        } else {
            $factura_id = intval($input_id);
        }
    }
    $monto = floatval($_POST['monto']);
    $metodo_pago = trim($_POST['metodo_pago']);
    $referencia = trim($_POST['referencia']);
    $usuario_id = $_SESSION['usuario_id'];
    $caja_id = isset($_SESSION['caja_id']) ? $_SESSION['caja_id'] : null;

    if($monto <= 0 || $cliente_id <= 0) {
        header("Location: estado_cuenta.php?cliente_id=$cliente_id&error=monto_invalido");
        exit();
    }
    
    // Obtener deuda total real del cliente para evitar saldos negativos
    $sql_deuda = "SELECT ISNULL(SUM(total - ISNULL(monto_pagado, 0)), 0) as deuda_total FROM facturas WHERE cliente_id = ? AND tipo_pago = 'credito' AND estado != 'anulada' AND (total - ISNULL(monto_pagado, 0)) > 0";
    $stmt_deuda = sqlsrv_query($conn, $sql_deuda, array($cliente_id));
    $deuda_total = 0;
    if ($stmt_deuda && $row = sqlsrv_fetch_array($stmt_deuda, SQLSRV_FETCH_ASSOC)) {
        $deuda_total = floatval($row['deuda_total']);
    }
    
    if ($monto > ($deuda_total + 0.05)) {
        header("Location: estado_cuenta.php?cliente_id=$cliente_id&error=monto_excesivo");
        exit();
    }

    try {
        sqlsrv_begin_transaction($conn);

        // MIGRACIONES SILENCIOSAS
        $sql_check = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('pagos_credito') AND name = 'cuota_id')
                      ALTER TABLE pagos_credito ADD cuota_id INT NULL";
        sqlsrv_query($conn, $sql_check);
        
        // Columna para rastrear abonos parciales en cuotas
        $sql_check2 = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('facturas_cuotas') AND name = 'monto_abonado')
                       ALTER TABLE facturas_cuotas ADD monto_abonado DECIMAL(18,2) NOT NULL DEFAULT 0";
        sqlsrv_query($conn, $sql_check2);

        // 1. Registrar el pago en la tabla pagos_credito
        // Si pagó una cuota, registramos el id de la factura asociada, pero podemos dejar null si queremos. Mejor asociarlo a la factura de la cuota.
        $factura_registro_pago = $factura_id;
        if ($cuota_id) {
            $sql_fc = "SELECT factura_id FROM facturas_cuotas WHERE id = ?";
            $stmt_fc = sqlsrv_query($conn, $sql_fc, array($cuota_id));
            if ($stmt_fc && sqlsrv_has_rows($stmt_fc)) {
                $factura_registro_pago = sqlsrv_fetch_array($stmt_fc, SQLSRV_FETCH_ASSOC)['factura_id'];
            }
        }

        $sql_pago = "INSERT INTO pagos_credito (cliente_id, factura_id, cuota_id, usuario_id, caja_id, monto, metodo_pago, referencia) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?);
                     SELECT SCOPE_IDENTITY() AS pago_id;";
        $params_pago = array($cliente_id, $factura_registro_pago, $cuota_id, $usuario_id, $caja_id, $monto, $metodo_pago, $referencia);
        
        $stmt_insert = sqlsrv_query($conn, $sql_pago, $params_pago);
        if(!$stmt_insert) throw new Exception("Error registrando pago.");
        
        // Obtener el ID del pago insertado
        sqlsrv_next_result($stmt_insert);
        $row_id = sqlsrv_fetch_array($stmt_insert, SQLSRV_FETCH_ASSOC);
        $ultimo_pago_id = $row_id['pago_id'];

        // 2. Disminuir el crédito consumido del cliente
        $sql_cliente = "UPDATE clientes SET credito_consumido = ISNULL(credito_consumido, 0) - ? WHERE id = ?";
        if(!sqlsrv_query($conn, $sql_cliente, array($monto, $cliente_id))) throw new Exception("Error actualizando cliente.");

        // 3. Aplicar pago a la(s) factura(s)
        $monto_restante = $monto;

        // 3. APLICAR PAGO (Lógica Waterfall / Cascada Mejorada)
        $monto_restante = $monto;

        // PRIORIDAD 1: Si hay una cuota específica
        if ($cuota_id && $monto_restante > 0) {
            $sql_c = "SELECT id, monto_cuota, ISNULL(monto_abonado, 0) as monto_abonado, factura_id FROM facturas_cuotas WHERE id = ?";
            $stmt_c = sqlsrv_query($conn, $sql_c, array($cuota_id));
            if ($stmt_c && $c = sqlsrv_fetch_array($stmt_c, SQLSRV_FETCH_ASSOC)) {
                $pendiente_c = floatval($c['monto_cuota']) - floatval($c['monto_abonado']);
                if ($pendiente_c > 0) {
                    $aplicar_c = min($monto_restante, $pendiente_c);
                    if ($aplicar_c >= ($pendiente_c - 0.05)) {
                        sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'pagada', monto_abonado = monto_cuota, fecha_pago = GETDATE() WHERE id = ?", array($c['id']));
                    } else {
                        sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'parcial', monto_abonado = ISNULL(monto_abonado, 0) + ? WHERE id = ?", array($aplicar_c, $c['id']));
                    }
                    $monto_restante -= $aplicar_c;
                    
                    // Actualizar cabecera de la factura por este monto aplicado
                    sqlsrv_query($conn, "UPDATE facturas SET monto_pagado = ISNULL(monto_pagado, 0) + ?, estado = CASE WHEN (total - ISNULL(monto_pagado, 0) - ?) <= 0.05 THEN 'pagada' ELSE estado END WHERE id = ?", array($aplicar_c, $aplicar_c, $c['factura_id']));
                    
                    // Si sobra dinero, que intente pagar el resto de la factura a la que pertenece esta cuota
                    if ($monto_restante > 0) {
                        $factura_id = $c['factura_id'];
                    }
                }
            }
        }

        // PRIORIDAD 2: Si hay una factura específica (y aún queda monto)
        if ($factura_id && $monto_restante > 0) {
            $sql_f = "SELECT total, ISNULL(monto_pagado, 0) as monto_pagado FROM facturas WHERE id = ?";
            $stmt_f = sqlsrv_query($conn, $sql_f, array($factura_id));
            if ($stmt_f && $f = sqlsrv_fetch_array($stmt_f, SQLSRV_FETCH_ASSOC)) {
                $pendiente_f = floatval($f['total']) - floatval($f['monto_pagado']);
                if ($pendiente_f > 0) {
                    $aplicar_f = min($monto_restante, $pendiente_f);
                    
                    // Actualizar cabecera
                    sqlsrv_query($conn, "UPDATE facturas SET monto_pagado = ISNULL(monto_pagado, 0) + ?, estado = CASE WHEN (total - ISNULL(monto_pagado, 0) - ?) <= 0.05 THEN 'pagada' ELSE estado END WHERE id = ?", array($aplicar_f, $aplicar_f, $factura_id));
                    
                    // Actualizar cuotas (si las tiene) con el monto aplicado a la factura
                    $sql_c = "SELECT id, monto_cuota, ISNULL(monto_abonado, 0) as monto_abonado FROM facturas_cuotas WHERE factura_id = ? AND estado IN ('pendiente', 'parcial') ORDER BY numero_cuota ASC";
                    $stmt_c = sqlsrv_query($conn, $sql_c, array($factura_id));
                    
                    if ($stmt_c) {
                        $monto_restante_cuotas = $aplicar_f;
                        while ($c = sqlsrv_fetch_array($stmt_c, SQLSRV_FETCH_ASSOC)) {
                            if ($monto_restante_cuotas <= 0) break;
                            $pendiente_c = floatval($c['monto_cuota']) - floatval($c['monto_abonado']);
                            $aplicar_c = min($monto_restante_cuotas, $pendiente_c);
                            
                            if ($aplicar_c >= ($pendiente_c - 0.05)) {
                                sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'pagada', monto_abonado = monto_cuota, fecha_pago = GETDATE() WHERE id = ?", array($c['id']));
                            } else {
                                sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'parcial', monto_abonado = ISNULL(monto_abonado, 0) + ? WHERE id = ?", array($aplicar_c, $c['id']));
                            }
                            $monto_restante_cuotas -= $aplicar_c;
                        }
                    }
                    
                    $monto_restante -= $aplicar_f;
                }
            }
        }

        // PRIORIDAD 3: Waterfall global para el monto restante (Abono general o exceso)
        if ($monto_restante > 0) {
            $sql_todas = "SELECT id, total, ISNULL(monto_pagado, 0) as monto_pagado FROM facturas WHERE cliente_id = ? AND tipo_pago = 'credito' AND estado != 'anulada' AND (total - ISNULL(monto_pagado, 0)) > 0 ORDER BY fecha ASC";
            $stmt_todas = sqlsrv_query($conn, $sql_todas, array($cliente_id));
            
            if ($stmt_todas) {
                while ($f = sqlsrv_fetch_array($stmt_todas, SQLSRV_FETCH_ASSOC)) {
                    if ($monto_restante <= 0) break;
                    
                    $fid = $f['id'];
                    $pendiente_f = floatval($f['total']) - floatval($f['monto_pagado']);
                    $aplicar_f = min($monto_restante, $pendiente_f);
                    
                    sqlsrv_query($conn, "UPDATE facturas SET monto_pagado = ISNULL(monto_pagado, 0) + ?, estado = CASE WHEN (total - ISNULL(monto_pagado, 0) - ?) <= 0.05 THEN 'pagada' ELSE estado END WHERE id = ?", array($aplicar_f, $aplicar_f, $fid));
                    
                    $sql_c = "SELECT id, monto_cuota, ISNULL(monto_abonado, 0) as monto_abonado FROM facturas_cuotas WHERE factura_id = ? AND estado IN ('pendiente', 'parcial') ORDER BY numero_cuota ASC";
                    $stmt_c = sqlsrv_query($conn, $sql_c, array($fid));
                    
                    if ($stmt_c) {
                        $monto_restante_cuotas = $aplicar_f;
                        while ($c = sqlsrv_fetch_array($stmt_c, SQLSRV_FETCH_ASSOC)) {
                            if ($monto_restante_cuotas <= 0) break;
                            $pendiente_c = floatval($c['monto_cuota']) - floatval($c['monto_abonado']);
                            $aplicar_c = min($monto_restante_cuotas, $pendiente_c);
                            
                            if ($aplicar_c >= ($pendiente_c - 0.05)) {
                                sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'pagada', monto_abonado = monto_cuota, fecha_pago = GETDATE() WHERE id = ?", array($c['id']));
                            } else {
                                sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'parcial', monto_abonado = ISNULL(monto_abonado, 0) + ? WHERE id = ?", array($aplicar_c, $c['id']));
                            }
                            $monto_restante_cuotas -= $aplicar_c;
                        }
                    }
                    
                    $monto_restante -= $aplicar_f;
                }
            }
        }

        // 4. Actualizar Caja (Ahora lo hacemos dinámicamente en los reportes sumando pagos_credito)
        // No actualizamos total_ventas_efectivo aquí para evitar duplicidad, ya que ese campo 
        // representa ventas directas (facturas). Los cobros de deuda se suman desde pagos_credito.

        sqlsrv_commit($conn);

        // ============================================================
        // NOTIFICACIÓN TELEGRAM (Post-Commit)
        // ============================================================
        try {
            // Re-obtener saldo restante para la notificación
            $sql_bal = "SELECT nombre, credito_consumido FROM clientes WHERE id = ?";
            $st_bal = sqlsrv_query($conn, $sql_bal, array($cliente_id));
            $c_bal = sqlsrv_fetch_array($st_bal, SQLSRV_FETCH_ASSOC);
            
            $sql_usr = "SELECT nombre FROM usuarios WHERE id = ?";
            $st_usr = sqlsrv_query($conn, $sql_usr, array($usuario_id));
            $u_usr = sqlsrv_fetch_array($st_usr, SQLSRV_FETCH_ASSOC);

            $detalle = "Abono general a deuda";
            $es_parcial = false;
            if ($cuota_id) {
                // Obtener número de cuota y estado actual para el mensaje
                $sql_c_inf = "SELECT fc.numero_cuota, fc.monto_cuota, ISNULL(fc.monto_abonado, 0) as monto_abonado, fc.estado, f.numero_factura 
                             FROM facturas_cuotas fc 
                             JOIN facturas f ON fc.factura_id = f.id 
                             WHERE fc.id = ?";
                $st_c_inf = sqlsrv_query($conn, $sql_c_inf, array($cuota_id));
                $inf = sqlsrv_fetch_array($st_c_inf, SQLSRV_FETCH_ASSOC);
                $detalle = "Fact. #" . $inf['numero_factura'] . " - Cuota " . $inf['numero_cuota'];
                if ($inf['estado'] === 'parcial') {
                    $es_parcial = true;
                    $resta = floatval($inf['monto_cuota']) - floatval($inf['monto_abonado']);
                    $detalle .= " (Abono parcial, resta RD$ " . number_format($resta, 2) . ")";
                } else {
                    $detalle .= " (Pagada completa)";
                }
            } elseif ($factura_id) {
                $sql_f_inf = "SELECT numero_factura FROM facturas WHERE id = ?";
                $st_f_inf = sqlsrv_query($conn, $sql_f_inf, array($factura_id));
                $inf = sqlsrv_fetch_array($st_f_inf, SQLSRV_FETCH_ASSOC);
                $detalle = "A Factura #" . $inf['numero_factura'];
            }

            $pago_notif = [
                'cliente_nombre' => $c_bal['nombre'],
                'monto' => $monto,
                'metodo_pago' => $metodo_pago,
                'referencia' => $referencia,
                'detalle' => $detalle,
                'saldo_restante' => $c_bal['credito_consumido'],
                'usuario_nombre' => $u_usr['nombre'],
                'es_cuota' => ($cuota_id !== null),
                'es_factura' => ($factura_id !== null),
                'es_parcial' => $es_parcial
            ];

            notificarPagoCredito($conn, $pago_notif);
        } catch (Exception $e_notif) {
            error_log("Error en notificación de pago: " . $e_notif->getMessage());
        }

        // Redirigir al comprobante de pago
        header("Location: comprobante_pago.php?pago_id=$ultimo_pago_id");
        
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        error_log("Error en abono: " . $e->getMessage());
        header("Location: estado_cuenta.php?cliente_id=$cliente_id&error=db");
    }
} else {
    header("Location: index.php");
}
?>
