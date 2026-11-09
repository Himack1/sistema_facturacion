<?php
/**
 * COMPROBANTE DE PAGO / ABONO
 * Genera un recibo imprimible cuando un cliente realiza un pago de crédito
 * Soporta formato Carta y Ticket
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';
require_once '../includes/config_empresa.php';
$config_empresa = obtenerConfigEmpresa($conn);

// Obtener ID del pago
$pago_id = isset($_GET['pago_id']) ? intval($_GET['pago_id']) : 0;
if ($pago_id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del pago
$sql_pago = "SELECT 
    p.id, p.cliente_id, p.monto, p.metodo_pago, p.referencia, p.fecha_pago, p.cuota_id, p.factura_id,
    c.nombre AS cliente_nombre, c.documento AS cliente_documento, c.tipo_documento,
    c.telefono AS cliente_telefono, c.credito_consumido AS saldo_pendiente,
    u.nombre AS cajero_nombre,
    f.numero_factura, (f.total - ISNULL(f.monto_pagado, 0)) as balance_factura
FROM pagos_credito p
INNER JOIN clientes c ON p.cliente_id = c.id
INNER JOIN usuarios u ON p.usuario_id = u.id
LEFT JOIN facturas f ON p.factura_id = f.id
WHERE p.id = ?";

$stmt_pago = sqlsrv_query($conn, $sql_pago, array($pago_id));
if (!$stmt_pago || !sqlsrv_has_rows($stmt_pago)) {
    echo "<div style='padding:40px;text-align:center;'><h2>Comprobante no encontrado</h2><a href='index.php'>Volver</a></div>";
    exit();
}
$pago = sqlsrv_fetch_array($stmt_pago, SQLSRV_FETCH_ASSOC);

// Función para enmascarar documento según configuración
function formatearDocumentoCliente($doc, $tipo, $formato) {
    if (!$doc) return '';
    if ($formato === 'quitar') return '';
    if ($formato === 'enmascarada') {
        if (strpos($doc, '-') !== false) {
            $partes = explode('-', $doc);
            if (count($partes) >= 2) {
                $inicio = $partes[0];
                $fin = end($partes);
                return $inicio . '-XXXXXXX-' . $fin;
            }
        }
        if (strlen($doc) > 6) {
            return substr($doc, 0, 3) . 'XXXX' . substr($doc, -2);
        }
    }
    return $doc;
}

// Info de cuota si aplica
$info_cuota = null;
if ($pago['cuota_id']) {
    $sql_cuota = "SELECT numero_cuota, monto_cuota, monto_abonado, estado, fecha_vencimiento FROM facturas_cuotas WHERE id = ?";
    $stmt_cuota = sqlsrv_query($conn, $sql_cuota, array($pago['cuota_id']));
    if ($stmt_cuota && sqlsrv_has_rows($stmt_cuota)) {
        $info_cuota = sqlsrv_fetch_array($stmt_cuota, SQLSRV_FETCH_ASSOC);
    }
}

$fecha_pago = $pago['fecha_pago']->format('d/m/Y H:i:s');
$moneda = $config_empresa['moneda'] ?? 'RD$';

$metodos = [
    'efectivo' => 'Efectivo',
    'transferencia' => 'Transferencia',
    'tarjeta' => 'Tarjeta',
    'cheque' => 'Cheque'
];
$metodo_texto = $metodos[$pago['metodo_pago']] ?? ucfirst($pago['metodo_pago']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Pago #<?php echo $pago['id']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }

        .acciones {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-volver { background: #e0e0e0; color: #333; }
        .btn-volver:hover { background: #d0d0d0; }
        .btn-imprimir { background: #2196f3; color: white; }
        .btn-imprimir:hover { background: #1976d2; }

        /* FORMATO CARTA */
        .comprobante-carta {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .comprobante-header {
            border-bottom: 3px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        .empresa-info h1 {
            color: #10b981;
            font-size: 26px;
            margin-bottom: 5px;
        }
        .empresa-info p {
            color: #666;
            font-size: 13px;
            margin: 3px 0;
        }
        .comprobante-titulo {
            text-align: right;
        }
        .comprobante-titulo h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 5px;
        }
        .comprobante-titulo .numero {
            font-size: 16px;
            color: #10b981;
            font-weight: 700;
        }
        .comprobante-titulo .fecha {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        .info-box {
            background: #f8fafb;
            padding: 18px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        .info-box h3 {
            color: #10b981;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #333;
            font-size: 14px;
            margin: 4px 0;
        }
        .info-box strong {
            color: #555;
        }

        .detalle-pago {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .detalle-pago-header {
            background: #f0fdf4;
            padding: 12px 20px;
            font-weight: 700;
            color: #166534;
            font-size: 14px;
            border-bottom: 2px solid #e0e0e0;
        }
        .detalle-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        .detalle-row:last-child { border-bottom: none; }
        .detalle-row .label { color: #555; }
        .detalle-row .valor { font-weight: 600; color: #333; }
        .detalle-row.total {
            background: #f0fdf4;
            font-size: 18px;
            padding: 15px 20px;
        }
        .detalle-row.total .valor {
            color: #10b981;
            font-size: 20px;
        }
        .detalle-row.saldo .valor {
            color: #ef4444;
            font-weight: 700;
        }

        .footer-comprobante {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .footer-comprobante p { margin: 3px 0; }

        .sello-pagado {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 3px solid #10b981;
            border-radius: 10px;
            background: #f0fdf4;
        }
        .sello-pagado span {
            font-size: 24px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 3px;
        }

        /* FORMATO TICKET */
        .comprobante-ticket {
            background: white;
            width: 300px;
            margin: 0 auto;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            display: none;
        }
        .comprobante-ticket.active { display: block; }
        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .ticket-header h2 { font-size: 16px; margin-bottom: 3px; }
        .ticket-header p { font-size: 10px; margin: 2px 0; }
        .ticket-titulo {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
            padding: 5px;
            border: 1px solid #333;
        }
        .ticket-linea {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 11px;
        }
        .ticket-separador {
            border-top: 1px dashed #333;
            margin: 8px 0;
        }
        .ticket-total {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }
        .ticket-footer {
            text-align: center;
            margin-top: 10px;
            border-top: 2px dashed #333;
            padding-top: 8px;
            font-size: 10px;
        }

        /* Modal */
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; padding: 30px; border-radius: 10px;
            max-width: 400px; text-align: center;
        }
        .modal-content h3 { margin-bottom: 20px; color: #333; }
        .modal-buttons { display: flex; gap: 15px; margin-top: 20px; }
        .btn-formato {
            flex: 1; padding: 15px; font-size: 16px; cursor: pointer;
            border: 2px solid #10b981; background: white; color: #10b981;
            border-radius: 5px; transition: all 0.3s;
        }
        .btn-formato:hover { background: #10b981; color: white; }

        @media print {
            body { background: white; padding: 0; }
            .acciones, .modal { display: none !important; }
            .comprobante-carta { box-shadow: none; padding: 20px; }
            .comprobante-ticket { width: 80mm; margin: 0; padding: 5mm; }
        }
    </style>
</head>
<body>
    <!-- Modal de formato -->
    <div class="modal" id="modalFormato">
        <div class="modal-content">
            <h3>Seleccionar formato de impresión</h3>
            <div class="modal-buttons">
                <button class="btn-formato" onclick="imprimirCarta()">📄<br>Carta</button>
                <button class="btn-formato" onclick="imprimirTicket()">🎫<br>Ticket</button>
            </div>
            <button class="btn" onclick="cerrarModal()" style="margin-top: 15px; background: #e0e0e0;">Cancelar</button>
        </div>
    </div>

    <div class="container">
        <div class="acciones">
            <a href="estado_cuenta.php?cliente_id=<?php echo $pago['cliente_id']; ?>" class="btn btn-volver">← Volver al Estado de Cuenta</a>
            <button onclick="document.getElementById('modalFormato').classList.add('active')" class="btn btn-imprimir">🖨️ Imprimir Comprobante</button>
        </div>

        <!-- FORMATO CARTA -->
        <div class="comprobante-carta" id="comprobanteCarta">
            <div class="comprobante-header">
                <?php if(!empty($config_empresa['logo_url'])): ?>
                    <div style="flex: 0 0 100px; margin-right: 15px;">
                        <img src="../<?php echo htmlspecialchars($config_empresa['logo_url']); ?>" alt="Logo" style="width: 100%; max-height: 80px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <div class="empresa-info" style="flex: 1;">
                    <h1><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h1>
                    <p>RNC: <?php echo htmlspecialchars($config_empresa['rnc']); ?></p>
                    <p>Tel: <?php echo htmlspecialchars($config_empresa['telefono']); ?></p>
                    <p>Dirección: <?php echo htmlspecialchars($config_empresa['direccion']); ?></p>
                    <?php if (!empty($config_empresa['email'])): ?>
                        <p>Email: <?php echo htmlspecialchars($config_empresa['email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($config_empresa['sitio_web'])): ?>
                        <p>Web: <?php echo htmlspecialchars($config_empresa['sitio_web']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="comprobante-titulo">
                    <h2>COMPROBANTE DE PAGO</h2>
                    <div class="numero">No. <?php echo str_pad($pago['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    <div class="fecha"><?php echo $fecha_pago; ?></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <h3>Cliente</h3>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pago['cliente_nombre']); ?></p>
                    <?php 
                        $doc_formateado = formatearDocumentoCliente($pago['cliente_documento'], $pago['tipo_documento'], $config_empresa['formato_documento_cliente'] ?? 'completa');
                        if (!empty($doc_formateado)): 
                    ?>
                        <p><strong><?php echo htmlspecialchars($pago['tipo_documento']); ?>:</strong> <?php echo htmlspecialchars($doc_formateado); ?></p>
                    <?php endif; ?>
                    <?php if ($pago['cliente_telefono']): ?>
                        <p><strong>Tel:</strong> <?php echo htmlspecialchars($pago['cliente_telefono']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="info-box">
                    <h3>Información del Pago</h3>
                    <p><strong>Cajero:</strong> <?php echo htmlspecialchars($pago['cajero_nombre']); ?></p>
                    <p><strong>Método:</strong> <?php echo $metodo_texto; ?></p>
                    <?php if (!empty($pago['referencia'])): ?>
                        <p><strong>Referencia:</strong> <?php echo htmlspecialchars($pago['referencia']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detalle-pago">
                <div class="detalle-pago-header">Detalle del Pago</div>
                
                <?php if ($pago['numero_factura']): ?>
                <div class="detalle-row">
                    <span class="label">Factura Asociada:</span>
                    <span class="valor"><?php echo htmlspecialchars($pago['numero_factura']); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($info_cuota): ?>
                <div class="detalle-row">
                    <span class="label">Cuota No.:</span>
                    <span class="valor"><?php echo $info_cuota['numero_cuota']; ?></span>
                </div>
                <div class="detalle-row">
                    <span class="label">Monto de la Cuota:</span>
                    <span class="valor"><?php echo $moneda; ?> <?php echo number_format($info_cuota['monto_cuota'], 2); ?></span>
                </div>
                <div class="detalle-row">
                    <span class="label">Vencimiento de la Cuota:</span>
                    <span class="valor"><?php echo $info_cuota['fecha_vencimiento']->format('d/m/Y'); ?></span>
                </div>
                <?php endif; ?>

                <?php 
                    // Calcular si es pago parcial de la cuota usando el estado real
                    $es_pago_parcial = false;
                    $restante_cuota = 0;
                    if ($info_cuota && ($info_cuota['estado'] === 'parcial' || ($info_cuota['estado'] === 'pendiente' && $pago['monto'] < $info_cuota['monto_cuota'] - 0.05))) {
                        $es_pago_parcial = true;
                        $restante_cuota = floatval($info_cuota['monto_cuota']) - floatval($info_cuota['monto_abonado']);
                    }
                ?>

                <?php if ($es_pago_parcial): ?>
                <div class="detalle-row" style="background: #fffbeb;">
                    <span class="label" style="color: #92400e;">Restante de esta Cuota:</span>
                    <span class="valor" style="color: #d97706;"><?php echo $moneda; ?> <?php echo number_format($restante_cuota, 2); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$pago['numero_factura'] && !$info_cuota): ?>
                <div class="detalle-row">
                    <span class="label">Concepto:</span>
                    <span class="valor">Abono general a deuda</span>
                </div>
                <?php endif; ?>

                <div class="detalle-row">
                    <span class="label">Método de Pago:</span>
                    <span class="valor"><?php echo $metodo_texto; ?></span>
                </div>

                <div class="detalle-row total">
                    <span class="label">MONTO PAGADO:</span>
                    <span class="valor"><?php echo $moneda; ?> <?php echo number_format($pago['monto'], 2); ?></span>
                </div>

                <?php if ($pago['factura_id']): ?>
                <div class="detalle-row saldo" style="border-top: 1px dashed #e2e8f0; margin-top: 10px; padding-top: 10px;">
                    <span class="label">Balance de esta Factura:</span>
                    <span class="valor" style="color: <?php echo $pago['balance_factura'] > 0.05 ? '#ef4444' : '#10b981'; ?>;">
                        <?php echo $moneda; ?> <?php echo number_format(max(0, $pago['balance_factura']), 2); ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="detalle-row saldo">
                    <span class="label">Saldo Total del Cliente:</span>
                    <span class="valor"><?php echo $moneda; ?> <?php echo number_format(max(0, $pago['saldo_pendiente']), 2); ?></span>
                </div>
            </div>

            <div class="sello-pagado" style="border-color: <?php echo $es_pago_parcial ? '#f59e0b' : '#10b981'; ?>; background: <?php echo $es_pago_parcial ? '#fffbeb' : '#f0fdf4'; ?>;">
                <span style="color: <?php echo $es_pago_parcial ? '#d97706' : '#10b981'; ?>;">
                    <?php echo $es_pago_parcial ? 'ABONO PARCIAL' : 'PAGADO'; ?>
                </span>
            </div>

            <div class="footer-comprobante">
                <p><?php echo nl2br(htmlspecialchars($config_empresa['mensaje_factura'] ?? '¡Gracias por su pago!')); ?></p>
                <p>Comprobante generado el <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>

        <!-- FORMATO TICKET -->
        <div class="comprobante-ticket" id="comprobanteTicket">
            <div class="ticket-header">
                <?php if(!empty($config_empresa['logo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($config_empresa['logo_url']); ?>" alt="Logo" style="width: 50mm; max-height: 25mm; object-fit: contain; margin-bottom: 5px;">
                <?php endif; ?>
                <h2><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h2>
                <p>RNC: <?php echo htmlspecialchars($config_empresa['rnc']); ?></p>
                <p>Tel: <?php echo htmlspecialchars($config_empresa['telefono']); ?></p>
                <p>Dir: <?php echo htmlspecialchars($config_empresa['direccion']); ?></p>
                <?php if (!empty($config_empresa['email'])): ?>
                    <p>Email: <?php echo htmlspecialchars($config_empresa['email']); ?></p>
                <?php endif; ?>
            </div>

            <div class="ticket-titulo">COMPROBANTE DE PAGO</div>

            <div class="ticket-linea">
                <span>No. Comprobante:</span>
                <strong><?php echo str_pad($pago['id'], 6, '0', STR_PAD_LEFT); ?></strong>
            </div>
            <div class="ticket-linea">
                <span>Fecha:</span>
                <span><?php echo $fecha_pago; ?></span>
            </div>

            <div class="ticket-separador"></div>

            <div class="ticket-linea">
                <span>Cliente:</span>
                <span><?php echo htmlspecialchars($pago['cliente_nombre']); ?></span>
            </div>
            <div class="ticket-linea">
                <span>Cajero:</span>
                <span><?php echo htmlspecialchars($pago['cajero_nombre']); ?></span>
            </div>

            <div class="ticket-separador"></div>

            <?php if ($pago['numero_factura']): ?>
            <div class="ticket-linea">
                <span>Factura:</span>
                <span><?php echo htmlspecialchars($pago['numero_factura']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($info_cuota): ?>
            <div class="ticket-linea">
                <span>Cuota No.:</span>
                <span><?php echo $info_cuota['numero_cuota']; ?></span>
            </div>
            <div class="ticket-linea">
                <span>Monto Cuota:</span>
                <span><?php echo $moneda; ?> <?php echo number_format($info_cuota['monto_cuota'], 2); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!$pago['numero_factura'] && !$info_cuota): ?>
            <div class="ticket-linea">
                <span>Concepto:</span>
                <span>Abono general</span>
            </div>
            <?php endif; ?>

            <div class="ticket-linea">
                <span>Método:</span>
                <span><?php echo $metodo_texto; ?></span>
            </div>
            <?php if (!empty($pago['referencia'])): ?>
            <div class="ticket-linea">
                <span>Ref:</span>
                <span><?php echo htmlspecialchars($pago['referencia']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($es_pago_parcial): ?>
            <div class="ticket-linea" style="color: #d97706;">
                <span>Restante Cuota:</span>
                <span><?php echo $moneda; ?> <?php echo number_format($restante_cuota, 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="ticket-total">
                <?php echo $es_pago_parcial ? 'ABONO PARCIAL' : 'PAGADO'; ?>: <?php echo $moneda; ?> <?php echo number_format($pago['monto'], 2); ?>
            </div>

            <?php if ($pago['factura_id']): ?>
            <div class="ticket-linea">
                <span>Bal. Factura:</span>
                <span><?php echo $moneda; ?> <?php echo number_format(max(0, $pago['balance_factura']), 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="ticket-linea" style="font-weight: bold; color: #333;">
                <span>Saldo Total:</span>
                <span><?php echo $moneda; ?> <?php echo number_format(max(0, $pago['saldo_pendiente']), 2); ?></span>
            </div>

            <div class="ticket-footer">
                <p><?php echo nl2br(htmlspecialchars($config_empresa['mensaje_factura'] ?? '¡Gracias por su pago!')); ?></p>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        function cerrarModal() {
            document.getElementById('modalFormato').classList.remove('active');
        }
        function imprimirCarta() {
            document.getElementById('comprobanteTicket').classList.remove('active');
            document.getElementById('comprobanteCarta').style.display = 'block';
            cerrarModal();
            setTimeout(() => { window.print(); }, 100);
        }
        function imprimirTicket() {
            document.getElementById('comprobanteCarta').style.display = 'none';
            document.getElementById('comprobanteTicket').classList.add('active');
            cerrarModal();
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.getElementById('comprobanteCarta').style.display = 'block';
                    document.getElementById('comprobanteTicket').classList.remove('active');
                }, 500);
            }, 100);
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) { sqlsrv_close($conn); }
?>
