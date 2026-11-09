<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
if($cliente_id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del cliente
$sql_cliente = "SELECT * FROM clientes WHERE id = ?";
$stmt_cliente = sqlsrv_query($conn, $sql_cliente, array($cliente_id));
$cliente = sqlsrv_fetch_array($stmt_cliente, SQLSRV_FETCH_ASSOC);

if(!$cliente) {
    header("Location: index.php");
    exit();
}

// Obtener facturas a crédito de este cliente (que no estén anuladas)
$sql_facturas = "
    SELECT id, numero_factura, fecha, total, monto_pagado, (total - ISNULL(monto_pagado, 0)) as pendiente 
    FROM facturas 
    WHERE cliente_id = ? AND tipo_pago = 'credito' AND estado != 'anulada' AND (total - ISNULL(monto_pagado, 0)) > 0
    ORDER BY fecha ASC
";
$stmt_facturas = sqlsrv_query($conn, $sql_facturas, array($cliente_id));

// Obtener historial de pagos
$sql_pagos = "
    SELECT p.*, f.numero_factura, u.nombre as usuario_nombre, fc.numero_cuota
    FROM pagos_credito p
    LEFT JOIN facturas f ON p.factura_id = f.id
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN facturas_cuotas fc ON p.cuota_id = fc.id
    WHERE p.cliente_id = ?
    ORDER BY p.fecha_pago DESC
";
$stmt_pagos = sqlsrv_query($conn, $sql_pagos, array($cliente_id));

// Obtener cuotas pendientes (Plazos) - Ordenadas por fecha de factura y número de cuota
$sql_cuotas = "
    SELECT c.*, f.numero_factura, f.fecha as fecha_factura
    FROM facturas_cuotas c
    JOIN facturas f ON c.factura_id = f.id
    WHERE f.cliente_id = ? AND c.estado IN ('pendiente', 'parcial')
    ORDER BY f.fecha ASC, c.numero_cuota ASC
";
$stmt_cuotas = sqlsrv_query($conn, $sql_cuotas, array($cliente_id));

// AUTO-FIX: Si una factura ya está pagada, marcar sus cuotas como pagadas automáticamente
sqlsrv_query($conn, "UPDATE facturas_cuotas SET estado = 'pagada', fecha_pago = GETDATE() WHERE factura_id IN (SELECT id FROM facturas WHERE cliente_id = ? AND estado = 'pagada') AND estado = 'pendiente'", array($cliente_id));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuenta - <?php echo htmlspecialchars($cliente['nombre']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        
        /* Panel Izquierdo: Perfil */
        .profile-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); align-self: start; }
        .profile-header { text-align: center; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #e2e8f0; }
        .avatar { width: 80px; height: 80px; background: #e0e7ff; color: #4f46e5; font-size: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-weight: bold; }
        .client-name { font-size: 20px; color: #0f172a; margin-bottom: 5px; }
        .client-doc { color: #64748b; font-size: 14px; }
        
        .finance-summary { margin-bottom: 25px; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .summary-label { color: #64748b; font-size: 14px; }
        .summary-value { font-weight: 700; color: #0f172a; }
        .summary-value.debt { color: #ef4444; font-size: 24px; }
        
        .btn-pay-large { display: block; width: 100%; text-align: center; background: #10b981; color: white; padding: 14px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn-pay-large:hover { background: #059669; }
        
        /* Panel Derecho: Tabs e Historial */
        .content-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .tabs { display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .tab { padding: 20px 30px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; }
        .tab.active { color: #4f46e5; border-bottom-color: #4f46e5; background: white; }
        
        .tab-content { display: none; padding: 30px; }
        .tab-content.active { display: block; }
        
        table { width: 100%; border-collapse: collapse; }
        th { color: #64748b; font-weight: 600; text-align: left; padding: 12px 15px; border-bottom: 2px solid #e2e8f0; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-success { background: #d1fae5; color: #047857; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        
        /* Search Bar */
        .search-container { padding: 20px 30px 10px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; transition: border 0.3s; }
        .search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .invoice-group-header { background: #f8fafc; font-weight: bold; color: #475569; }
        
        /* Modal Abono */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; width: 100%; max-width: 500px; border-radius: 12px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal h2 { margin-bottom: 20px; color: #0f172a; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #4f46e5; }
        .modal-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn { flex: 1; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px; }
        .btn-cancel { background: #f1f5f9; color: #475569; }
        .btn-confirm { background: #4f46e5; color: white; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📊 Estado de Cuenta</h1>
        <a href="index.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        
        <?php if(isset($_GET['exito']) && $_GET['exito'] == 1): ?>
        <div style="grid-column: 1 / -1; background: #10b981; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            ✅ Pago registrado exitosamente.
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
        <div style="grid-column: 1 / -1; background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php 
                if($_GET['error'] == 'monto_invalido') echo '❌ Error: El monto ingresado es inválido.';
                elseif($_GET['error'] == 'monto_excesivo') echo '❌ Error: No puedes abonar un monto mayor a la deuda total del cliente.';
                else echo '❌ Ocurrió un error al procesar el pago.';
            ?>
        </div>
        <?php endif; ?>

        <!-- Perfil Izquierdo -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
                <h2 class="client-name"><?php echo htmlspecialchars($cliente['nombre']); ?></h2>
                <div class="client-doc"><?php echo htmlspecialchars($cliente['tipo_documento'] . ': ' . $cliente['documento']); ?></div>
            </div>
            
            <div class="finance-summary">
                <div class="summary-item" style="flex-direction: column; align-items: center; margin-bottom: 25px;">
                    <span class="summary-label">Saldo Pendiente (Deuda)</span>
                    <span class="summary-value debt">RD$ <?php echo number_format($cliente['credito_consumido'], 2); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Límite de Crédito</span>
                    <span class="summary-value">RD$ <?php echo number_format($cliente['limite_credito'], 2); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Crédito Disponible</span>
                    <span class="summary-value" style="color: #10b981;">RD$ <?php echo number_format($cliente['credito_disponible'], 2); ?></span>
                </div>
            </div>
            
            <?php if($cliente['credito_consumido'] > 0): ?>
            <button class="btn-pay-large" onclick="openModal()">💰 Registrar Abono</button>
            <?php endif; ?>
        </div>

        <!-- Contenido Derecho -->
        <div class="content-card">
            <div class="search-container">
                <span style="color: #64748b; font-size: 18px;">🔍</span>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar factura, cuota, fecha o monto..." onkeyup="filterData()">
            </div>

            <div class="tabs">
                <div class="tab active" onclick="switchTab('facturas', this)">Facturas Pendientes</div>
                <div class="tab" onclick="switchTab('cuotas', this)">Cuotas de Financiamiento</div>
                <div class="tab" onclick="switchTab('historial', this)">Historial de Pagos</div>
            </div>
            
            <!-- Tab: Facturas Pendientes -->
            <div id="facturas" class="tab-content active">
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Deuda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $opciones_facturas = "";
                        if($stmt_facturas && sqlsrv_has_rows($stmt_facturas)): 
                            while($factura = sqlsrv_fetch_array($stmt_facturas, SQLSRV_FETCH_ASSOC)): 
                                $opciones_facturas .= "<option value='{$factura['id']}'>Factura #{$factura['numero_factura']} - Pendiente: RD$ " . number_format($factura['pendiente'], 2) . "</option>";
                        ?>
                        <tr class="filterable-row">
                            <td><strong>#<?php echo $factura['numero_factura']; ?></strong></td>
                            <td><?php echo $factura['fecha']->format('d/m/Y'); ?></td>
                            <td>RD$ <?php echo number_format($factura['total'], 2); ?></td>
                            <td>RD$ <?php echo number_format($factura['monto_pagado'], 2); ?></td>
                            <td><span class="badge badge-pending">RD$ <?php echo number_format($factura['pendiente'], 2); ?></span></td>
                        </tr>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b;">No hay facturas pendientes.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Cuotas de Financiamiento -->
            <div id="cuotas" class="tab-content">
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>No. Cuota</th>
                            <th>Vencimiento</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $opciones_cuotas = "";
                        $last_invoice = null;
                        if($stmt_cuotas && sqlsrv_has_rows($stmt_cuotas)): 
                            while($cuota = sqlsrv_fetch_array($stmt_cuotas, SQLSRV_FETCH_ASSOC)): 
                                $fecha_venc_str = $cuota['fecha_vencimiento']->format('d/m/Y');
                                $abonado_c = floatval($cuota['monto_abonado'] ?? 0);
                                $restante_c = floatval($cuota['monto_cuota']) - $abonado_c;
                                $label_extra = $abonado_c > 0 ? " (Resta: RD$ " . number_format($restante_c, 2) . ")" : "";
                                $opciones_cuotas .= "<option value='cuota_{$cuota['id']}'>Fact. #{$cuota['numero_factura']} - Cuota #{$cuota['numero_cuota']} (RD$ " . number_format($restante_c, 2) . "){$label_extra}</option>";
                                
                                if ($last_invoice !== $cuota['numero_factura']) {
                                    echo "<tr class='invoice-group-header'><td colspan='5'>Factura #{$cuota['numero_factura']} (Fecha: " . $cuota['fecha_factura']->format('d/m/Y') . ")</td></tr>";
                                    $last_invoice = $cuota['numero_factura'];
                                }
                        ?>
                        <tr class="filterable-row">
                            <td style="padding-left: 25px;"><strong>#<?php echo htmlspecialchars($cuota['numero_factura']); ?></strong></td>
                            <td>Cuota <?php echo $cuota['numero_cuota']; ?></td>
                            <td><?php echo $fecha_venc_str; ?></td>
                            <td>
                                <?php 
                                    $abonado = floatval($cuota['monto_abonado'] ?? 0);
                                    $pendiente_c = floatval($cuota['monto_cuota']) - $abonado;
                                    if ($abonado > 0): 
                                ?>
                                    <span class="badge badge-pending">RD$ <?php echo number_format($cuota['monto_cuota'], 2); ?></span>
                                    <br><small style="color: #10b981;">Abonado: RD$ <?php echo number_format($abonado, 2); ?></small>
                                    <br><small style="color: #ef4444; font-weight: 600;">Resta: RD$ <?php echo number_format($pendiente_c, 2); ?></small>
                                <?php else: ?>
                                    <span class="badge badge-pending">RD$ <?php echo number_format($cuota['monto_cuota'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cuota['estado'] === 'parcial'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #d97706;">Parcial</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b;">No hay cuotas pendientes.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            

            <!-- Tab: Historial de Pagos -->
            <div id="historial" class="tab-content">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Recibo #</th>
                            <th>Referencia</th>
                            <th>Método</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($stmt_pagos && sqlsrv_has_rows($stmt_pagos)): 
                            while($pago = sqlsrv_fetch_array($stmt_pagos, SQLSRV_FETCH_ASSOC)): 
                        ?>
                        <tr class="filterable-row">
                            <td><?php echo $pago['fecha_pago']->format('d/m/Y H:i'); ?></td>
                            <td>
                                <?php 
                                if ($pago['factura_id']) {
                                    echo "A Factura #" . htmlspecialchars($pago['numero_factura']);
                                    if ($pago['numero_cuota']) {
                                        echo " <b>Cuota " . htmlspecialchars($pago['numero_cuota']) . "</b>";
                                    }
                                } else {
                                    echo "Abono General";
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($pago['referencia']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($pago['metodo_pago'])); ?></td>
                            <td><span class="badge badge-success">+ RD$ <?php echo number_format($pago['monto'], 2); ?></span></td>
                        </tr>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b;">No hay registros de pagos.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Registro de Pago -->
    <div class="modal-overlay" id="pagoModal">
        <div class="modal">
            <h2>Registrar Abono</h2>
            <form action="procesar_pago.php" method="POST">
                <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
                
                <div class="form-group">
                    <label>Aplicar a:</label>
                    <select name="factura_id" id="factura_id" onchange="actualizarMontoSugerido()">
                        <option value="">Abono al saldo general (Más antiguo primero)</option>
                        <optgroup label="Facturas a Crédito">
                            <?php echo $opciones_facturas; ?>
                        </optgroup>
                        <?php if(!empty($opciones_cuotas)): ?>
                        <optgroup label="Cuotas de Financiamiento">
                            <?php echo $opciones_cuotas; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Monto a Abonar (RD$) *</label>
                    <input type="number" name="monto" id="monto" step="0.01" min="0.01" max="<?php echo $cliente['credito_consumido']; ?>" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Método de Pago *</label>
                    <select name="metodo_pago" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Referencia (Opcional)</label>
                    <input type="text" name="referencia" placeholder="Nro. de transferencia, cheque, etc.">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-confirm">Procesar Abono</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabId, element) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            element.classList.add('active');
        }
        
        function openModal() {
            document.getElementById('pagoModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('pagoModal').classList.remove('active');
        }

        function actualizarMontoSugerido() {
            const select = document.getElementById('factura_id');
            const montoInput = document.getElementById('monto');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value === "") {
                montoInput.value = "";
                return;
            }
            
            // Extraer monto del texto de la opción (ej: RD$ 1,234.56)
            const text = selectedOption.text;
            const match = text.match(/RD\$ ([\d,.]+)/);
            if (match) {
                const monto = match[1].replace(/,/g, '');
                montoInput.value = monto;
                
                // Enfocar el input de monto por si quieren editarlo
                montoInput.focus();
                montoInput.select();
            }
        }

        function filterData() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const activeTab = document.querySelector('.tab-content.active');
            const rows = activeTab.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                // No filtrar los encabezados de grupo de factura
                if (rows[i].classList.contains('invoice-group-header')) {
                    // Podríamos mejorar esto para ocultar el header si todas sus filas están ocultas, 
                    // pero por simplicidad lo dejamos visible.
                    continue; 
                }
                
                let text = rows[i].textContent || rows[i].innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>
