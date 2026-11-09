<?php
/**
 * CIERRE DE CAJA
 * Registra el cierre de turno con conteo de efectivo
 * Calcula diferencias y actualiza estado de caja
 */

session_start();

// Validar sesión y caja abierta
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['caja_id'])) {
    header("Location: verificar_caja.php");
    exit();
}

require_once '../conexion.php';

// ==================================================
// OBTENER INFORMACIÓN DE LA CAJA
// ==================================================
$sql = "SELECT 
            c.id,
            c.usuario_id,
            c.fecha_apertura,
            c.monto_inicial,
            c.total_ventas_efectivo,
            c.total_ventas_transferencia,
            c.total_ventas_credito,
            c.estado,
            c.observaciones_apertura,
            u.nombre as usuario_nombre
        FROM cajas c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'abierta'";

$params = array($_SESSION['caja_id'], $_SESSION['usuario_id']);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !sqlsrv_has_rows($stmt)) {
    $_SESSION['mensaje_error'] = "No se encontró caja abierta";
    header("Location: verificar_caja.php");
    exit();
}

$caja = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Verificar que la caja pertenece al usuario actual (seguridad adicional)
if ($caja['usuario_id'] != $_SESSION['usuario_id']) {
    $_SESSION['mensaje_error'] = "Esta caja no pertenece a tu usuario";
    header("Location: verificar_caja.php");
    exit();
}

// ==================================================
// OBTENER ABONOS A CRÉDITO REALIZADOS EN ESTE TURNO
// ==================================================
$sql_abonos = "SELECT 
                ISNULL(SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END), 0) as abonos_efectivo,
                ISNULL(SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END), 0) as abonos_transferencia
               FROM pagos_credito 
               WHERE caja_id = ?";
$stmt_abonos = sqlsrv_query($conn, $sql_abonos, array($_SESSION['caja_id']));
$abonos = sqlsrv_fetch_array($stmt_abonos, SQLSRV_FETCH_ASSOC);

$abonos_efectivo = (float)$abonos['abonos_efectivo'];
$abonos_transferencia = (float)$abonos['abonos_transferencia'];

// ==================================================
// CALCULAR EFECTIVO ESPERADO
// Solo el efectivo debe coincidir: monto inicial + ventas en efectivo + abonos en efectivo
// ==================================================
$monto_esperado = (float)$caja['monto_inicial'] + (float)$caja['total_ventas_efectivo'] + $abonos_efectivo;

// ==================================================
// PROCESAR CIERRE
// ==================================================
$error = null;
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtener datos del formulario
    $monto_real = isset($_POST['monto_real']) ? floatval($_POST['monto_real']) : 0;
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    
    // Validaciones
    if ($monto_real < 0) {
        $error = "El monto real no puede ser negativo";
    } else {
        
        try {
            // Calcular diferencia
            $diferencia = $monto_real - $monto_esperado;
            
            // Iniciar transacción
            sqlsrv_begin_transaction($conn);
            
            // Actualizar registro de caja
            $sql_cerrar = "UPDATE cajas SET 
                           fecha_cierre = GETDATE(),
                           monto_final_esperado = ?,
                           monto_final_real = ?,
                           diferencia = ?,
                           estado = 'cerrada',
                           observaciones_cierre = ?
                           WHERE id = ? AND usuario_id = ? AND estado = 'abierta'";
            
            $params_cerrar = array(
                $monto_esperado,
                $monto_real,
                $diferencia,
                $observaciones,
                $_SESSION['caja_id'],
                $_SESSION['usuario_id']
            );
            
            $stmt_cerrar = sqlsrv_query($conn, $sql_cerrar, $params_cerrar);
            
            if ($stmt_cerrar === false) {
                throw new Exception("Error al cerrar caja");
            }
            
            // Verificar que se actualizó el registro
            $rows_affected = sqlsrv_rows_affected($stmt_cerrar);
            if ($rows_affected === 0) {
                throw new Exception("No se pudo actualizar el estado de la caja");
            }
            
            // Confirmar transacción
            sqlsrv_commit($conn);
            
            // ====== NOTIFICAR Y EXPORTAR ======
            try {
                // Obtener conteo de facturas anuladas en este turno
                $sql_anuladas = "SELECT COUNT(*) as cant FROM facturas WHERE caja_id = ? AND estado = 'anulada'";
                $stmt_anuladas = sqlsrv_query($conn, $sql_anuladas, array($_SESSION['caja_id']));
                $cant_anuladas = 0;
                if ($stmt_anuladas && $row_anul = sqlsrv_fetch_array($stmt_anuladas, SQLSRV_FETCH_ASSOC)) {
                    $cant_anuladas = $row_anul['cant'];
                }

                require_once '../includes/notificaciones.php';
                $caja_notif = [
                    'caja_id'                    => $_SESSION['caja_id'],
                    'usuario_nombre'             => $caja['usuario_nombre'],
                    'monto_inicial'              => $caja['monto_inicial'],
                    'total_ventas_efectivo'       => $caja['total_ventas_efectivo'],
                    'total_ventas_transferencia'  => $caja['total_ventas_transferencia'],
                    'total_ventas_credito'        => $caja['total_ventas_credito'],
                    'monto_esperado'             => $monto_esperado,
                    'monto_real'                 => $monto_real,
                    'diferencia'                 => $diferencia,
                    'observaciones'              => $observaciones,
                    'facturas_anuladas'          => $cant_anuladas
                ];
                notificarCierreCaja($conn, $caja_notif);
                exportarCierreDrive($conn, $caja_notif);
                notificarStockBajo($conn);
            } catch (Exception $ne) {
                error_log("Notificación de cierre falló: " . $ne->getMessage());
            }
            
            // Limpiar variables de sesión
            unset($_SESSION['caja_id']);
            unset($_SESSION['caja_abierta']);
            unset($_SESSION['caja_fecha_apertura']);
            unset($_SESSION['caja_monto_inicial']);
            
            // Redirigir al dashboard con mensaje
            $_SESSION['mensaje_exito'] = "Caja cerrada correctamente. Diferencia: RD$ " . number_format($diferencia, 2);
            header("Location: ../dashboard.php?caja_cerrada=si");
            exit();
            
        } catch (Exception $e) {
            // Revertir transacción
            sqlsrv_rollback($conn);
            
            // Log del error
            error_log("Error al cerrar caja: " . $e->getMessage());
            $error = "Error al cerrar la caja. " . $e->getMessage();
        }
    }
}

// ==================================================
// CALCULAR DATOS PARA MOSTRAR
// ==================================================
$fecha_apertura = $caja['fecha_apertura']->format('d/m/Y H:i');
$timestamp_apertura = $caja['fecha_apertura']->getTimestamp();
$timestamp_actual = time();
$segundos_transcurridos = $timestamp_actual - $timestamp_apertura;
$horas = round($segundos_transcurridos / 3600, 1);

// Total general de ventas (todos los métodos de pago)
$total_ventas_general = $caja['total_ventas_efectivo'] + 
                        $caja['total_ventas_transferencia'] + 
                        $caja['total_ventas_credito'];

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrar Caja - Sistema de Facturación</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%); 
            min-height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 20px; 
        }
        .cierre-card { 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            max-width: 600px; 
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .cierre-icon { 
            text-align: center; 
            font-size: 80px; 
            margin-bottom: 20px; 
        }
        .cierre-header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .cierre-header h1 { 
            color: #333; 
            font-size: 32px; 
            margin-bottom: 10px; 
        }
        .info-box { 
            background: #f8f9ff; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 10px 0; 
            border-bottom: 1px solid #e0e0e0; 
        }
        .info-row:last-child { 
            border-bottom: none; 
        }
        .info-label { 
            color: #666; 
            font-size: 14px; 
        }
        .info-value { 
            font-weight: 600; 
            color: #333; 
            font-size: 16px; 
        }
        .total-esperado { 
            background: #e8f5e9; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            text-align: center; 
        }
        .total-esperado-label { 
            color: #2e7d32; 
            font-size: 14px; 
            margin-bottom: 5px; 
        }
        .total-esperado-valor { 
            font-size: 48px; 
            font-weight: 700; 
            color: #4caf50; 
        }
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-weight: 600; 
        }
        .form-group input, 
        .form-group textarea { 
            width: 100%; 
            padding: 15px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 16px; 
            font-family: inherit; 
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f44336;
        }
        .form-group textarea { 
            resize: vertical; 
            min-height: 80px; 
        }
        .diferencia { 
            text-align: center; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 18px; 
            font-weight: 600; 
            display: none; 
        }
        .diferencia.positivo { 
            background: #e8f5e9; 
            color: #2e7d32; 
        }
        .diferencia.negativo { 
            background: #ffebee; 
            color: #c62828; 
        }
        .diferencia.ok { 
            background: #e3f2fd; 
            color: #1976d2; 
        }
        .btn-cerrar { 
            width: 100%; 
            padding: 18px; 
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: 600; 
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-cerrar:hover {
            transform: translateY(-2px);
        }
        .btn-imprimir {
            width: 100%;
            padding: 15px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s;
        }
        .btn-imprimir:hover {
            transform: translateY(-2px);
            background: #1976d2;
        }
        .btn-cancelar { 
            width: 100%; 
            padding: 12px; 
            background: #e0e0e0; 
            color: #333; 
            border: none; 
            border-radius: 8px; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-top: 15px; 
            text-decoration: none; 
            display: block; 
            text-align: center; 
        }
        .mensaje-error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 12px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        .nota-importante {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="cierre-card">
        <div class="cierre-icon">🔒</div>
        <div class="cierre-header">
            <h1>Cierre de Caja</h1>
            <p style="color: #666;">Cuenta el efectivo y registra el monto</p>
        </div>

        <?php if ($error): ?>
            <div class="mensaje-error">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="nota-importante">
            ℹ️ <strong>Importante:</strong> Solo cuenta el efectivo físico. Transferencias y créditos no afectan el efectivo de caja.
        </div>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Usuario:</span>
                <span class="info-value"><?php echo htmlspecialchars($caja['usuario_nombre']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Apertura:</span>
                <span class="info-value"><?php echo $fecha_apertura; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tiempo abierta:</span>
                <span class="info-value"><?php echo $horas; ?> horas</span>
            </div>
            <div class="info-row">
                <span class="info-label">Monto Inicial:</span>
                <span class="info-value">RD$ <?php echo number_format($caja['monto_inicial'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Ventas Efectivo:</span>
                <span class="info-value" style="color: #4caf50;">RD$ <?php echo number_format($caja['total_ventas_efectivo'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Abonos Efectivo:</span>
                <span class="info-value" style="color: #27ae60;">RD$ <?php echo number_format($abonos_efectivo, 2); ?></span>
            </div>
            <div class="info-row" style="border-top: 2px solid #e0e0e0; margin-top: 10px; padding-top: 15px;">
                <span class="info-label">Ventas Transferencia:</span>
                <span class="info-value" style="color: #2196f3;">RD$ <?php echo number_format($caja['total_ventas_transferencia'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Abonos Transferencia:</span>
                <span class="info-value" style="color: #3498db;">RD$ <?php echo number_format($abonos_transferencia, 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Ventas Crédito:</span>
                <span class="info-value" style="color: #ff9800;">RD$ <?php echo number_format($caja['total_ventas_credito'], 2); ?></span>
            </div>
            <div class="info-row" style="border-top: 2px solid #667eea; margin-top: 10px; padding-top: 15px;">
                <span class="info-label" style="font-weight: 600;">Recaudación Total (Ventas + Abonos):</span>
                <span class="info-value" style="color: #667eea; font-size: 18px;">RD$ <?php echo number_format($total_ventas_general + $abonos_efectivo + $abonos_transferencia, 2); ?></span>
            </div>
        </div>

        <div class="total-esperado">
            <div class="total-esperado-label">💵 Efectivo Esperado en Caja:</div>
            <div class="total-esperado-valor">RD$ <?php echo number_format($monto_esperado, 2); ?></div>
            <div style="font-size: 12px; color: #2e7d32; margin-top: 5px;">
                (Monto inicial + Ventas + Abonos en Efectivo)
            </div>
        </div>

        <form method="POST" id="formCierre">
            <div class="form-group">
                <label>💰 Efectivo Real Contado (RD$) *</label>
                <input 
                    type="number" 
                    name="monto_real" 
                    id="monto_real" 
                    step="0.01" 
                    min="0" 
                    required 
                    autofocus
                    placeholder="0.00">
            </div>

            <div class="diferencia" id="diferencia"></div>

            <div class="form-group">
                <label>📝 Observaciones del Cierre</label>
                <textarea 
                    name="observaciones" 
                    placeholder="Notas sobre el cierre (opcional)..."
                    maxlength="500"></textarea>
            </div>

            <button type="submit" class="btn-cerrar" id="btnCerrar">🔒 Cerrar Caja</button>
            <button type="button" class="btn-imprimir" onclick="imprimirCierre()">🖨️ Imprimir Resumen de Ventas</button>
            <a href="nueva_factura.php" class="btn-cancelar">Cancelar</a>
        </form>
    </div>

    <script>
        const montoEsperado = <?php echo $monto_esperado; ?>;
        
        // Función para imprimir cierre
        function imprimirCierre() {
            const url = 'imprimir_cierre.php';
            const ventana = window.open(url, 'Cierre de Caja', 'width=400,height=800,menubar=no,toolbar=no,location=no');
            if (ventana) {
                ventana.focus();
            } else {
                alert('Por favor, permite las ventanas emergentes para imprimir el cierre.');
            }
        }
        
        // Calcular diferencia en tiempo real
        document.getElementById('monto_real').addEventListener('input', function() {
            const montoReal = parseFloat(this.value) || 0;
            const diferencia = montoReal - montoEsperado;
            const divDif = document.getElementById('diferencia');
            
            divDif.style.display = 'block';
            divDif.className = 'diferencia';
            
            if (diferencia === 0) {
                divDif.classList.add('ok');
                divDif.innerHTML = '✓ Perfecto • Sin diferencia';
            } else if (diferencia > 0) {
                divDif.classList.add('positivo');
                divDif.innerHTML = '+ RD$ ' + Math.abs(diferencia).toFixed(2) + ' de más (sobrante)';
            } else {
                divDif.classList.add('negativo');
                divDif.innerHTML = '- RD$ ' + Math.abs(diferencia).toFixed(2) + ' faltante';
            }
        });

        // Confirmación al cerrar
        document.getElementById('formCierre').addEventListener('submit', function(e) {
            const montoReal = parseFloat(document.getElementById('monto_real').value) || 0;
            const diferencia = montoReal - montoEsperado;
            
            let mensaje = '¿Confirmar cierre de caja?\n\n';
            mensaje += 'Efectivo esperado: RD$ ' + montoEsperado.toFixed(2) + '\n';
            mensaje += 'Efectivo contado: RD$ ' + montoReal.toFixed(2) + '\n';
            
            if (diferencia === 0) {
                mensaje += '\n✓ Sin diferencia';
            } else if (diferencia > 0) {
                mensaje += '\n+ RD$ ' + Math.abs(diferencia).toFixed(2) + ' de más';
            } else {
                mensaje += '\n- RD$ ' + Math.abs(diferencia).toFixed(2) + ' faltante';
            }
            
            if (!confirm(mensaje)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>