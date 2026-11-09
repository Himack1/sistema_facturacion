<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) {
    header("Location: index.php");
    exit();
}

$error = '';

// Obtener datos de reparación y crédito del cliente
$sql = "SELECT r.*, c.nombre as cliente_nombre, 
        c.limite_credito, ISNULL(c.credito_consumido, 0) as credito_consumido,
        (c.limite_credito - ISNULL(c.credito_consumido, 0)) as credito_disponible 
        FROM reparaciones r JOIN clientes c ON r.cliente_id = c.id WHERE r.id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$r || $r['estado'] === 'Entregado') {
    header("Location: index.php");
    exit();
}

// Obtener inventario (Piezas / Productos)
$sql_prod = "SELECT p.id, p.nombre, p.precio_venta, p.stock_actual, c.nombre as categoria_nombre 
             FROM productos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.estado = 'activo' AND p.stock_actual > 0";
$stmt_prod = sqlsrv_query($conn, $sql_prod);
$productos = [];
if($stmt_prod !== false) {
    while($prod = sqlsrv_fetch_array($stmt_prod, SQLSRV_FETCH_ASSOC)) {
        $productos[] = $prod;
    }
}

// Obtener piezas precargadas/cotizadas
$sql_piezas_cot = "SELECT rp.producto_id, rp.cantidad, rp.precio, p.nombre, p.stock_actual 
                   FROM reparacion_piezas rp 
                   JOIN productos p ON rp.producto_id = p.id 
                   WHERE rp.reparacion_id = ?";
$stmt_piezas_cot = sqlsrv_query($conn, $sql_piezas_cot, array($id));
$piezas_precargadas = [];
$total_piezas_precargadas = 0;
if($stmt_piezas_cot !== false) {
    while($row = sqlsrv_fetch_array($stmt_piezas_cot, SQLSRV_FETCH_ASSOC)) {
        $piezas_precargadas[] = array(
            'id' => (string)$row['producto_id'],
            'nombre' => $row['nombre'],
            'precio' => floatval($row['precio']),
            'cantidad' => intval($row['cantidad']),
            'max_stock' => intval($row['stock_actual'])
        );
        $total_piezas_precargadas += ($row['precio'] * $row['cantidad']);
    }
}
// Ajustar el costo de servicio (mano de obra) si hay piezas precargadas
$costo_estimado_db = floatval($r['costo_estimado'] ?: 0);
$mano_obra_inicial = max(0, $costo_estimado_db - $total_piezas_precargadas);

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo_pago = trim($_POST['metodo_pago']);
    $tipo_pago = trim($_POST['tipo_pago']);
    
    // Variables de plazos (crédito)
    $es_a_plazos = isset($_POST['es_a_plazos']) ? intval($_POST['es_a_plazos']) : 0;
    $monto_inicial_credito = isset($_POST['monto_inicial']) ? floatval($_POST['monto_inicial']) : 0;
    $cantidad_cuotas = isset($_POST['cantidad_cuotas']) ? intval($_POST['cantidad_cuotas']) : 0;
    $frecuencia_plazos = isset($_POST['frecuencia_plazos']) ? trim($_POST['frecuencia_plazos']) : '';
    $interes_porcentaje = isset($_POST['interes_porcentaje']) ? floatval($_POST['interes_porcentaje']) : 0;

    // Si es crédito, el tipo de pago forzosamente es crédito para cumplir con la BD
    if ($metodo_pago === 'credito') {
        $tipo_pago = 'credito';
    }
    $notas_factura = trim($_POST['notas_factura']);
    
    // Costo del servicio técnico (Mano de obra)
    $costo_servicio = isset($_POST['costo_servicio']) ? floatval($_POST['costo_servicio']) : 0;
    
    // Arrays de piezas seleccionadas
    $productos_ids = isset($_POST['productos_ids']) ? $_POST['productos_ids'] : [];
    $cantidades = isset($_POST['cantidades']) ? $_POST['cantidades'] : [];
    $precios = isset($_POST['precios']) ? $_POST['precios'] : [];
    
    // Calcular total
    $costo_total = $costo_servicio;
    for ($i = 0; $i < count($productos_ids); $i++) {
        $costo_total += floatval($precios[$i]) * intval($cantidades[$i]);
    }

    try {
        // ==================================================
        // Ajustes si es crédito a plazos y Validación
        // ==================================================
        if ($metodo_pago === 'credito') {
            if ($es_a_plazos === 1) {
                if ($monto_inicial_credito >= $costo_total) {
                    throw new Exception("El inicial no puede ser mayor o igual al total de la reparación.");
                }
                $deuda_base = $costo_total - $monto_inicial_credito;
                $monto_interes = $deuda_base * ($interes_porcentaje / 100);
                $costo_total += $monto_interes; // Aumentar total de factura por intereses
                $monto_pagado_inicial = $monto_inicial_credito;
            } else {
                $monto_pagado_inicial = 0; // Crédito a 1 solo pago
            }
            
            // Validar límite de crédito disponible
            if ($costo_total > $r['credito_disponible']) {
                throw new Exception(sprintf("Crédito insuficiente. Límite Disponible: RD$ %.2f | Requerido: RD$ %.2f", $r['credito_disponible'], $costo_total));
            }
        } else {
            $monto_pagado_inicial = $costo_total; // Al contado paga todo
        }

        sqlsrv_begin_transaction($conn);
        
        $estado_factura = ($metodo_pago === 'credito') ? 'pendiente' : 'pagada';
        
        // Buscar o crear servicio de reparación genérico
        $sql_srv_rep = "SELECT id FROM productos WHERE codigo = 'SERV-REP'";
        $stmt_srv_rep = sqlsrv_query($conn, $sql_srv_rep);
        $srv_rep_id = 0;
        if ($stmt_srv_rep && sqlsrv_has_rows($stmt_srv_rep)) {
            $row_srv = sqlsrv_fetch_array($stmt_srv_rep, SQLSRV_FETCH_ASSOC);
            $srv_rep_id = $row_srv['id'];
        } else {
            $sql_ins_srv = "INSERT INTO productos (codigo, nombre, tipo, precio_venta, estado, aplica_inventario) VALUES ('SERV-REP', 'Mano de Obra (Reparaciones)', 'servicio', 0, 'activo', 0);
                            SELECT SCOPE_IDENTITY() AS id;";
            $stmt_ins = sqlsrv_query($conn, $sql_ins_srv);
            if($stmt_ins) {
                sqlsrv_next_result($stmt_ins);
                $row_ins = sqlsrv_fetch_array($stmt_ins, SQLSRV_FETCH_ASSOC);
                $srv_rep_id = $row_ins['id'];
            }
        }
        if(!$srv_rep_id) throw new Exception("Error al generar producto de servicio.");

        // Obtener secuencia con función de DB
        $sql_numero = "SELECT dbo.fn_GenerarNumeroFactura() AS numero";
        $stmt_numero = sqlsrv_query($conn, $sql_numero);
        
        if ($stmt_numero === false || !sqlsrv_has_rows($stmt_numero)) {
            throw new Exception("Error al generar número de factura");
        }
        
        $row_numero = sqlsrv_fetch_array($stmt_numero, SQLSRV_FETCH_ASSOC);
        $numero_factura = $row_numero['numero'];
        
        // 1. Crear Factura
        $caja_id = isset($_SESSION['caja_id']) ? $_SESSION['caja_id'] : null;
        $sql_factura = "INSERT INTO facturas (numero_factura, cliente_id, usuario_id, caja_id, tipo_pago, subtotal, impuesto, total, estado, observaciones, monto_pagado, es_a_plazos, fecha) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE());
                        SELECT SCOPE_IDENTITY() AS id;";
        $params_factura = array(
            $numero_factura, $r['cliente_id'], $_SESSION['usuario_id'], $caja_id, $tipo_pago, 
            $costo_total, 0, $costo_total, $estado_factura, $notas_factura, $monto_pagado_inicial, $es_a_plazos
        );
        $stmt_fac = sqlsrv_query($conn, $sql_factura, $params_factura);
        
        if(!$stmt_fac) {
            $errs = sqlsrv_errors();
            throw new Exception("Error al crear factura: " . print_r($errs, true));
        }
        sqlsrv_next_result($stmt_fac);
        $row_fac = sqlsrv_fetch_array($stmt_fac, SQLSRV_FETCH_ASSOC);
        $factura_id = $row_fac['id'];
        
        // 2. Insertar Detalle - Servicio Técnico (Mano de obra)
        if($costo_servicio > 0) {
            $sql_det_serv = "INSERT INTO detalle_factura (factura_id, producto_id, descripcion, tipo_item, cantidad, precio_unitario, descuento_linea, impuesto_linea, total_linea, impuesto_porcentaje) VALUES (?, ?, ?, 'servicio', 1, ?, 0, 0, ?, 0)";
            $desc_serv = "Servicio Técnico - Mano de obra ({$r['tipo_equipo']} {$r['marca_modelo']})";
            if(!sqlsrv_query($conn, $sql_det_serv, array($factura_id, $srv_rep_id, $desc_serv, $costo_servicio, $costo_servicio))) {
                throw new Exception("Error al insertar el servicio en la factura: " . print_r(sqlsrv_errors(), true));
            }
        }

        // 3. Insertar Detalles - Piezas / Repuestos
        for ($i = 0; $i < count($productos_ids); $i++) {
            $pid = intval($productos_ids[$i]);
            $cant = intval($cantidades[$i]);
            $prec = floatval($precios[$i]);
            $subt = $cant * $prec;

            // Obtener el nombre real del producto para la descripción
            $sql_nom = "SELECT nombre FROM productos WHERE id = ?";
            $stmt_nom = sqlsrv_query($conn, $sql_nom, array($pid));
            $row_nom = sqlsrv_fetch_array($stmt_nom, SQLSRV_FETCH_ASSOC);
            $nombre_pieza = $row_nom ? $row_nom['nombre'] : 'Pieza/Repuesto';

            $sql_det_prod = "INSERT INTO detalle_factura (factura_id, producto_id, descripcion, tipo_item, cantidad, precio_unitario, descuento_linea, impuesto_linea, total_linea, impuesto_porcentaje) VALUES (?, ?, ?, 'producto', ?, ?, 0, 0, ?, 0)";
            if(!sqlsrv_query($conn, $sql_det_prod, array($factura_id, $pid, $nombre_pieza, $cant, $prec, $subt))) {
                throw new Exception("Error al insertar pieza en la factura: " . print_r(sqlsrv_errors(), true));
            }

            // Descontar inventario
            $sql_stock = "UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?";
            if(!sqlsrv_query($conn, $sql_stock, array($cant, $pid))) {
                throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
            }
        }
        
        
        // 4. Crédito de cliente y Cuotas
        if($metodo_pago === 'credito' && $costo_total > 0) {
            $saldo_a_deber = $costo_total - $monto_inicial_credito;
            sqlsrv_query($conn, "UPDATE clientes SET credito_consumido = ISNULL(credito_consumido,0) + ? WHERE id = ?", array($saldo_a_deber, $r['cliente_id']));
            
            // Si dio un inicial en efectivo, sumarlo a caja (asumiremos efectivo)
            if ($monto_inicial_credito > 0 && $caja_id) {
                sqlsrv_query($conn, "UPDATE cajas SET total_ventas_efectivo = total_ventas_efectivo + ? WHERE id = ?", array($monto_inicial_credito, $caja_id));
            }
            
            // Insertar cuotas si es a plazos
            if ($es_a_plazos === 1 && $cantidad_cuotas > 0) {
                $deuda_total_a_financiar = $costo_total - $monto_inicial_credito;
                $monto_cuota_estandar = round($deuda_total_a_financiar / $cantidad_cuotas, 2);
                
                $acumulado_cuotas = 0;
                $fecha_base = new DateTime();
                
                for ($i = 1; $i <= $cantidad_cuotas; $i++) {
                    $monto_final_cuota = $monto_cuota_estandar;
                    if ($i === (int)$cantidad_cuotas) {
                        $monto_final_cuota = $deuda_total_a_financiar - $acumulado_cuotas;
                    }
                    $acumulado_cuotas += $monto_final_cuota;

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
                    sqlsrv_query($conn, $sql_cuota, array($factura_id, $i, $monto_final_cuota, $fecha_venc_str));
                }
            }
        }
        
        // 5. Actualizar Reparación
        $sql_upd = "UPDATE reparaciones SET estado = 'Entregado', fecha_entrega = GETDATE(), factura_id = ? WHERE id = ?";
        if(!sqlsrv_query($conn, $sql_upd, array($factura_id, $id))) {
            throw new Exception("Error al cerrar ticket de reparación.");
        }
        
        sqlsrv_commit($conn);
        
        // Redireccionar a la impresión de factura
        header("Location: ../Facturacion/ver_factura.php?id=$factura_id");
        exit();
        
    } catch(Exception $e) {
        sqlsrv_rollback($conn);
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar Equipo - <?php echo htmlspecialchars($r['codigo_ticket'] ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card h2 { color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; font-size: 18px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #10b981; }
        
        .btn-submit { background: #10b981; color: white; padding: 15px 20px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-submit:hover { background: #059669; }
        
        .alert-error { background: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; grid-column: 1 / -1; }
        
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-top: 20px; text-align: center; }
        .total-amount { font-size: 32px; font-weight: bold; color: #0f172a; margin-top: 5px; }

        .prod-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .prod-row input { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-remove:hover { background: #dc2626; }
        
        .panel-credito { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none; }
        .panel-credito h3 { font-size: 14px; color: #334155; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        .grid-cuotas { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📦 Entregar Equipo (Ticket: <?php echo htmlspecialchars($r['codigo_ticket'] ?? ''); ?>)</h1>
        <a href="ver_reparacion.php?id=<?php echo $id; ?>" class="btn-volver">← Volver al Ticket</a>
    </header>

    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error ?? ''); ?></div>
        <?php endif; ?>

        <!-- Columna Izquierda: Agregar Piezas y Servicio -->
        <div class="card">
            <h2>Detalle a Facturar</h2>
            
            <div class="form-group">
                <label>Costo Mano de Obra / Servicio (RD$)</label>
                <input type="number" id="costo_servicio" value="<?php echo $mano_obra_inicial; ?>" step="0.01" min="0" oninput="calcularTotal()">
            </div>

            <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;">

            <div class="form-group">
                <label>Agregar Pieza de Repuesto (Buscador)</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="buscador_producto" placeholder="Buscar por nombre o categoría..." list="lista_productos" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); agregarPieza(); return false; }">
                    <datalist id="lista_productos">
                        <?php foreach($productos as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['nombre'] ?? ''); ?>" data-id="<?php echo $p['id']; ?>" data-precio="<?php echo $p['precio_venta']; ?>" data-stock="<?php echo $p['stock_actual']; ?>">
                                <?php echo htmlspecialchars($p['categoria_nombre'] ?? 'Sin Categoría'); ?> - Disp: <?php echo $p['stock_actual']; ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <button type="button" onclick="agregarPieza()" style="background: #3b82f6; color: white; border: none; padding: 0 15px; border-radius: 8px; cursor: pointer; font-weight: bold;">Agregar</button>
                </div>
            </div>

            <div id="lista_piezas">
                <!-- Aquí se insertan las piezas dinámicamente -->
            </div>
        </div>

        <!-- Columna Derecha: Confirmar Pago -->
        <div class="card">
            <h2>Confirmar Pago</h2>
            
            <div class="summary-box">
                <div style="color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 13px;">Gran Total a Cobrar</div>
                <div class="total-amount" id="display_total">RD$ 0.00</div>
                <div style="color: #475569; margin-top: 10px; font-size: 14px;">Cliente: <?php echo htmlspecialchars($r['cliente_nombre'] ?? ''); ?></div>
            </div>

            <form method="POST" action="" id="form_entrega">
                <!-- Inputs ocultos para enviar a PHP -->
                <input type="hidden" name="costo_servicio" id="hidden_costo_servicio" value="0">
                <div id="hidden_inputs"></div>

                <div class="form-group">
                    <label>Condición de Pago *</label>
                    <select name="metodo_pago" id="metodo_pago" required onchange="toggleFormaPago()">
                        <option value="contado">Al Contado</option>
                        <option value="credito">A Crédito (Se agregará a su deuda)</option>
                    </select>
                </div>
                
                <div class="form-group" id="grupo_tipo_pago">
                    <label>Forma de Pago *</label>
                    <select name="tipo_pago" id="tipo_pago">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia Bancaria / Tarjeta</option>
                        <option value="mixto">Mixto</option>
                    </select>
                </div>
                
                <div class="panel-credito" id="panel_credito">
                    <div style="margin-bottom: 15px; font-size: 14px;">
                        <strong>Límite Disponible:</strong> 
                        <span style="color: <?php echo $r['credito_disponible'] > 0 ? '#10b981' : '#ef4444'; ?>;">
                            RD$ <?php echo number_format($r['credito_disponible'] ?? 0, 2); ?>
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Crédito</label>
                        <select name="es_a_plazos" id="es_a_plazos" onchange="toggleCuotas()">
                            <option value="0">1 Solo Pago (Monto Total)</option>
                            <option value="1">A Plazos (Cuotas)</option>
                        </select>
                    </div>
                    
                    <div id="opciones_cuotas" style="display: none;">
                        <div class="grid-cuotas">
                            <div class="form-group">
                                <label>Inicial (Opcional)</label>
                                <input type="number" name="monto_inicial" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Cant. de Cuotas</label>
                                <input type="number" name="cantidad_cuotas" min="1" placeholder="Ej: 4">
                            </div>
                            <div class="form-group">
                                <label>Frecuencia</label>
                                <select name="frecuencia_plazos">
                                    <option value="semanal">Semanal</option>
                                    <option value="quincenal">Quincenal</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Interés (%)</label>
                                <input type="number" name="interes_porcentaje" step="0.01" min="0" placeholder="Ej: 5">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notas para la Factura (Opcional)</label>
                    <textarea name="notas_factura" rows="3"><?php echo htmlspecialchars("Factura por reparación ticket: " . ($r['codigo_ticket'] ?? '')); ?></textarea>
                </div>

                <button type="button" onclick="enviarFormulario()" class="btn-submit">✅ Confirmar Entrega y Facturar</button>
            </form>
        </div>
    </div>

    <script>
        let piezas = <?php echo json_encode($piezas_precargadas); ?>;

        function toggleFormaPago() {
            const metodo = document.getElementById('metodo_pago').value;
            const grupoTipo = document.getElementById('grupo_tipo_pago');
            const panelCredito = document.getElementById('panel_credito');
            if (metodo === 'credito') {
                grupoTipo.style.display = 'none';
                panelCredito.style.display = 'block';
            } else {
                grupoTipo.style.display = 'block';
                panelCredito.style.display = 'none';
            }
        }

        function toggleCuotas() {
            const es_a_plazos = document.getElementById('es_a_plazos').value;
            const opcionesCuotas = document.getElementById('opciones_cuotas');
            if (es_a_plazos === '1') {
                opcionesCuotas.style.display = 'block';
            } else {
                opcionesCuotas.style.display = 'none';
            }
        }
        
        // Inicializar vistas correctas
        document.addEventListener('DOMContentLoaded', function() {
            toggleFormaPago();
            toggleCuotas();
        });

        function agregarPieza() {
            const input = document.getElementById('buscador_producto');
            const datalist = document.getElementById('lista_productos');
            const val = input.value.trim();
            if(val === '') return false;
            
            // Buscar la opción seleccionada
            let selectedOption = null;
            for (let i = 0; i < datalist.options.length; i++) {
                if (datalist.options[i].value === val) {
                    selectedOption = datalist.options[i];
                    break;
                }
            }

            if(selectedOption) {
                const id = selectedOption.getAttribute('data-id');
                const precio = parseFloat(selectedOption.getAttribute('data-precio'));
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                
                // Evitar duplicados (simplemente incrementar cantidad)
                let existe = piezas.find(p => p.id === id);
                if(existe) {
                    if(existe.cantidad < stock) existe.cantidad++;
                    else {
                        alert("No hay más stock disponible de esta pieza.");
                        return false;
                    }
                } else {
                    piezas.push({ id: id, nombre: val, precio: precio, cantidad: 1, max_stock: stock });
                }
                
                input.value = '';
                renderPiezas();
                return true;
            } else {
                alert("Seleccione un producto válido de la lista.");
                return false;
            }
        }

        function removerPieza(id) {
            piezas = piezas.filter(p => p.id !== id);
            renderPiezas();
        }

        function actualizarCantidad(id, val) {
            let p = piezas.find(p => p.id === id);
            if(p) {
                let cant = parseInt(val);
                if(cant > p.max_stock) {
                    alert("Stock insuficiente. Máximo: " + p.max_stock);
                    cant = p.max_stock;
                }
                if(cant < 1) cant = 1;
                p.cantidad = cant;
                renderPiezas();
            }
        }

        function renderPiezas() {
            const container = document.getElementById('lista_piezas');
            container.innerHTML = '';
            
            piezas.forEach(p => {
                let subt = (p.precio * p.cantidad).toFixed(2);
                container.innerHTML += `
                    <div class="prod-row">
                        <div style="flex-grow: 1; font-weight: bold; font-size: 14px;">${p.nombre}</div>
                        <div>
                            <span style="font-size: 12px; color: #64748b;">Cant:</span>
                            <input type="number" value="${p.cantidad}" min="1" max="${p.max_stock}" style="width: 60px;" onchange="actualizarCantidad('${p.id}', this.value)">
                        </div>
                        <div style="width: 100px; text-align: right; font-weight: bold; color: #0f172a;">RD$ ${subt}</div>
                        <button type="button" class="btn-remove" onclick="removerPieza('${p.id}')">X</button>
                    </div>
                `;
            });
            calcularTotal();
        }

        function calcularTotal() {
            let costoServicio = parseFloat(document.getElementById('costo_servicio').value) || 0;
            let totalPiezas = piezas.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
            
            let granTotal = costoServicio + totalPiezas;
            document.getElementById('display_total').innerText = 'RD$ ' + granTotal.toFixed(2);
        }

        function enviarFormulario() {
            const input = document.getElementById('buscador_producto');
            if (input.value.trim() !== '') {
                // Intentar agregar la pieza que se quedó escrita
                if (agregarPieza() === false) {
                    return; // Detener envío si hubo un error (ej. nombre inválido)
                }
            }

            // Actualizar inputs ocultos antes de enviar
            document.getElementById('hidden_costo_servicio').value = parseFloat(document.getElementById('costo_servicio').value) || 0;
            
            const hiddenDiv = document.getElementById('hidden_inputs');
            hiddenDiv.innerHTML = '';
            
            piezas.forEach(p => {
                hiddenDiv.innerHTML += `
                    <input type="hidden" name="productos_ids[]" value="${p.id}">
                    <input type="hidden" name="cantidades[]" value="${p.cantidad}">
                    <input type="hidden" name="precios[]" value="${p.precio}">
                `;
            });
            
            document.getElementById('form_entrega').submit();
        }
        
        // Init total y render de piezas precargadas
        renderPiezas();
    </script>
</body>
</html>
