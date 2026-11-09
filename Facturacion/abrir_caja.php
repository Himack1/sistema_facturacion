<?php
/**
 * APERTURA DE CAJA
 * Registra el inicio de turno del cajero con monto inicial
 * Solo permite abrir si NO tiene caja activa
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

// ==================================================
// VERIFICAR QUE NO TENGA CAJA ABIERTA
// ==================================================
$sql_check = "SELECT id FROM cajas WHERE usuario_id = ? AND estado = 'abierta'";
$stmt_check = sqlsrv_query($conn, $sql_check, array($_SESSION['usuario_id']));

if ($stmt_check && sqlsrv_has_rows($stmt_check)) {
    // Ya tiene caja abierta, redirigir a facturación
    $row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
    $_SESSION['caja_id'] = $row['id'];
    $_SESSION['caja_abierta'] = true;
    header("Location: nueva_factura.php");
    exit();
}

// ==================================================
// PROCESAR APERTURA DE CAJA
// ==================================================
$error = null;
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar y limpiar datos
    $monto_inicial = isset($_POST['monto_inicial']) ? floatval($_POST['monto_inicial']) : 0;
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    
    // Validaciones de negocio
    if ($monto_inicial < 0) {
        $error = "El monto inicial no puede ser negativo";
    } else {
        
        try {
            // Iniciar transacción
            sqlsrv_begin_transaction($conn);
            
            // Insertar nueva caja
            $sql = "INSERT INTO cajas (
                        usuario_id, 
                        fecha_apertura,
                        monto_inicial, 
                        observaciones_apertura, 
                        estado,
                        total_ventas_efectivo,
                        total_ventas_transferencia,
                        total_ventas_credito
                    ) VALUES (?, GETDATE(), ?, ?, 'abierta', 0.00, 0.00, 0.00)";
            
            $params = array(
                $_SESSION['usuario_id'],
                $monto_inicial,
                $observaciones
            );
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Error al insertar caja");
            }
            
            // Obtener ID de la caja recién creada
            $sql_id = "SELECT @@IDENTITY as id";
            $stmt_id = sqlsrv_query($conn, $sql_id);
            $row = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
            $caja_id = $row['id'];
            
            // Confirmar transacción
            sqlsrv_commit($conn);
            
            // Actualizar variables de sesión
            $_SESSION['caja_id'] = $caja_id;
            $_SESSION['caja_abierta'] = true;
            $_SESSION['caja_monto_inicial'] = $monto_inicial;
            
            // Redirigir a facturación
            header("Location: nueva_factura.php?caja_abierta=si");
            exit();
            
        } catch (Exception $e) {
            // Revertir transacción
            sqlsrv_rollback($conn);
            
            // Log del error
            error_log("Error al abrir caja: " . $e->getMessage());
            $error = "Error al abrir la caja. Intente nuevamente.";
        }
    }
}

// ==================================================
// OBTENER DATOS DEL USUARIO
// ==================================================
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$fecha_actual = date('d/m/Y H:i');

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir Caja - Sistema de Facturación</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center;
            padding: 20px;
        }
        .caja-card { 
            background: white; 
            padding: 50px; 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
            max-width: 500px; 
            width: 100%; 
        }
        .caja-icon { 
            text-align: center; 
            font-size: 80px; 
            margin-bottom: 20px; 
        }
        .caja-header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .caja-header h1 { 
            color: #333; 
            font-size: 32px; 
            margin-bottom: 10px; 
        }
        .caja-header p { 
            color: #666; 
            font-size: 16px; 
        }
        .info-usuario { 
            background: #f8f9ff; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
            text-align: center; 
        }
        .info-usuario strong { 
            color: #667eea; 
            font-size: 18px; 
        }
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-weight: 600; 
            font-size: 14px; 
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
            border-color: #667eea; 
        }
        .form-group textarea { 
            resize: vertical; 
            min-height: 80px; 
        }
        .form-group small { 
            display: block; 
            margin-top: 6px; 
            color: #888; 
            font-size: 13px; 
        }
        .monto-display { 
            text-align: center; 
            font-size: 48px; 
            font-weight: 700; 
            color: #4caf50; 
            margin: 20px 0; 
        }
        .btn-abrir { 
            width: 100%; 
            padding: 18px; 
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: transform 0.2s; 
        }
        .btn-abrir:hover { 
            transform: translateY(-2px); 
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
        .mensaje-exito { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 12px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="caja-card">
        <div class="caja-icon">💰</div>
        <div class="caja-header">
            <h1>Apertura de Caja</h1>
            <p>Registra el monto inicial para comenzar tu turno</p>
        </div>

        <div class="info-usuario">
            <div style="color: #888; font-size: 12px; margin-bottom: 5px;">Usuario</div>
            <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong>
            <div style="color: #888; font-size: 12px; margin-top: 5px;">
                <?php echo $fecha_actual; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mensaje-error">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje): ?>
            <div class="mensaje-exito">
                ✓ <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formApertura">
            <div class="form-group">
                <label>Monto Inicial (RD$) *</label>
                <input 
                    type="number" 
                    name="monto_inicial" 
                    id="monto_inicial" 
                    step="0.01" 
                    min="0" 
                    value="0.00" 
                    required 
                    autofocus>
                <small>Efectivo con el que inicias el turno</small>
            </div>

            <div class="monto-display" id="montoDisplay">RD$ 0.00</div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea 
                    name="observaciones" 
                    placeholder="Notas adicionales sobre la apertura (opcional)..."
                    maxlength="500"></textarea>
            </div>

            <button type="submit" class="btn-abrir">🔓 Abrir Caja</button>
            <a href="../dashboard.php" class="btn-cancelar">Cancelar</a>
        </form>
    </div>

    <script>
        // Actualizar visualización del monto en tiempo real
        document.getElementById('monto_inicial').addEventListener('input', function() {
            const monto = parseFloat(this.value) || 0;
            document.getElementById('montoDisplay').textContent = 
                'RD$ ' + monto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        });

        // Validación adicional al enviar
        document.getElementById('formApertura').addEventListener('submit', function(e) {
            const monto = parseFloat(document.getElementById('monto_inicial').value);
            
            if (monto < 0) {
                e.preventDefault();
                alert('⚠️ El monto inicial no puede ser negativo');
                return false;
            }
            
            // Confirmación si el monto es 0
            if (monto === 0) {
                if (!confirm('¿Deseas abrir la caja sin monto inicial (RD$ 0.00)?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>