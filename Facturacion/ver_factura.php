<?php
/**
 * VER FACTURA
 * Muestra el detalle completo de una factura
 * Permite imprimir en formato carta o ticket
 * INCLUYE: Descuentos globales y descuentos por línea (producto)
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';
require_once '../includes/config_empresa.php';

// Evitar advertencias de variable no definida en el editor
/** @var resource $conn */

if (!isset($conn) || $conn === false) {
    die("<div style='background: #ffebee; padding: 20px; margin: 20px; border: 2px solid #f44336; border-radius: 5px;'>
        <h3 style='color: #c62828;'>Error de Conexión</h3>
        <p>No se pudo conectar a la base de datos. Verifica la configuración en 'conexion.php'.</p>
        <p><a href='../dashboard.php'>Volver al Dashboard</a></p>
    </div>");
}

$config_empresa = obtenerConfigEmpresa($conn);

// ==================================================
// OBTENER ID DE FACTURA
// ==================================================
$factura_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($factura_id <= 0) {
    $_SESSION['mensaje_error'] = "Factura no válida";
    header("Location: ../dashboard.php");
    exit();
}

// ==================================================
// OBTENER DATOS DE LA FACTURA (ENCABEZADO)
// ==================================================
$sql_factura = "SELECT 
                    f.id,
                    f.numero_factura,
                    f.fecha,
                    f.tipo_pago,
                    f.efectivo_recibido,
                    f.subtotal,
                    f.descuento,
                    f.impuesto,
                    f.total,
                    f.estado,
                    f.observaciones,
                    f.fecha_anulacion,
                    f.motivo_anulacion,
                    c.nombre AS cliente_nombre,
                    c.documento AS cliente_documento,
                    c.tipo_documento,
                    c.telefono AS cliente_telefono,
                    c.direccion AS cliente_direccion,
                    f.es_a_plazos,
                    f.frecuencia_plazos,
                    f.cantidad_cuotas,
                    f.interes_porcentaje,
                    f.monto_inicial,
                    u.nombre AS usuario_nombre,
                    cj.fecha_apertura AS caja_fecha
                FROM facturas f
                INNER JOIN clientes c ON f.cliente_id = c.id
                INNER JOIN usuarios u ON f.usuario_id = u.id
                LEFT JOIN cajas cj ON f.caja_id = cj.id
                WHERE f.id = ?";

$stmt_factura = sqlsrv_query($conn, $sql_factura, array($factura_id));

if ($stmt_factura === false) {
    $errors = sqlsrv_errors();
    echo "<div style='background: #ffebee; padding: 20px; margin: 20px; border: 2px solid #f44336; border-radius: 5px;'>
        <h3 style='color: #c62828;'>Error al cargar factura:</h3>
        <pre>";
    print_r($errors);
    echo "</pre>
        <p><a href='../dashboard.php'>Volver al Dashboard</a></p>
    </div>";
    sqlsrv_close($conn);
    exit();
}

if (!sqlsrv_has_rows($stmt_factura)) {
    $_SESSION['mensaje_error'] = "Factura no encontrada";
    header("Location: ../dashboard.php");
    sqlsrv_close($conn);
    exit();
}

$factura = sqlsrv_fetch_array($stmt_factura, SQLSRV_FETCH_ASSOC);

// ==================================================
// OBTENER DETALLE DE LA FACTURA (LÍNEAS)
// ==================================================
$sql_detalle = "SELECT 
                    d.id,
                    d.descripcion,
                    d.tipo_item,
                    d.cantidad,
                    d.precio_unitario,
                    d.descuento_linea,
                    d.impuesto_linea,
                    d.total_linea,
                    d.impuesto_porcentaje,
                    p.codigo AS producto_codigo
                FROM detalle_factura d
                LEFT JOIN productos p ON d.producto_id = p.id
                WHERE d.factura_id = ?
                ORDER BY d.id";

$stmt_detalle = sqlsrv_query($conn, $sql_detalle, array($factura_id));

if ($stmt_detalle === false) {
    $errors = sqlsrv_errors();
    echo "<div style='background: #ffebee; padding: 20px; margin: 20px; border: 2px solid #f44336; border-radius: 5px;'>
        <h3 style='color: #c62828;'>Error al cargar detalle de factura:</h3>
        <pre>";
    print_r($errors);
    echo "</pre>
        <p><strong>Consulta SQL:</strong> " . htmlspecialchars($sql_detalle) . "</p>
        <p><strong>Factura ID:</strong> " . $factura_id . "</p>
        <p><a href='../dashboard.php'>Volver al Dashboard</a></p>
    </div>";
    sqlsrv_close($conn);
    exit();
}

// Almacenar detalles en un array para usarlos en ambos formatos
$detalles = array();
$total_descuentos_linea = 0; // Sumar descuentos por línea
$lineas_con_descuento = 0; // Contar líneas con descuento

while ($det = sqlsrv_fetch_array($stmt_detalle, SQLSRV_FETCH_ASSOC)) {
    $detalles[] = $det;
    
    // Sumar descuentos por línea
    if ($det['descuento_linea'] > 0) {
        $total_descuentos_linea += $det['descuento_linea'];
        $lineas_con_descuento++;
    }
}

// ==================================================
// OBTENER CUOTAS SI ES A PLAZOS
// ==================================================
$cuotas = array();
if (isset($factura['es_a_plazos']) && $factura['es_a_plazos'] == 1) {
    $sql_cuotas = "SELECT * FROM facturas_cuotas WHERE factura_id = ? ORDER BY numero_cuota ASC";
    $stmt_cuotas = sqlsrv_query($conn, $sql_cuotas, array($factura_id));
    if ($stmt_cuotas !== false) {
        while ($row_c = sqlsrv_fetch_array($stmt_cuotas, SQLSRV_FETCH_ASSOC)) {
            $cuotas[] = $row_c;
        }
    }
}

// ==================================================
// CALCULAR TOTALES Y PORCENTAJES
// ==================================================
$fecha_factura = $factura['fecha']->format('d/m/Y H:i:s');
$es_anulada = ($factura['estado'] === 'anulada');
$puede_anular = ($factura['estado'] !== 'anulada' && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'ADMIN');

// Calcular cambio si es efectivo
$cambio = 0;
if ($factura['tipo_pago'] === 'efectivo' && $factura['efectivo_recibido']) {
    $cambio = $factura['efectivo_recibido'] - $factura['total'];
}

// Calcular base de la factura e interés (para separar visualmente)
$total_factura_base = $factura['total'];
$interes_generado = 0;
$balance_financiado = 0;
$pendiente_actual = 0;

if (isset($factura['es_a_plazos']) && $factura['es_a_plazos'] == 1) {
    // El total base es lo que valdría la factura sin el financiamiento
    $total_factura_base = ($factura['subtotal'] - $total_descuentos_linea - $factura['descuento'] + $factura['impuesto']);
    $interes_generado = $factura['total'] - $total_factura_base;
    $balance_financiado = $total_factura_base - $factura['monto_inicial'];
    
    // Calcular pendiente actual (suma de lo que falta por pagar en cada cuota)
    foreach ($cuotas as $cuota) {
        if ($cuota['estado'] !== 'pagada') {
            $pendiente_actual += (floatval($cuota['monto_cuota']) - floatval($cuota['monto_abonado'] ?? 0));
        }
    }
}

// Calcular porcentajes de descuento

// Calcular subtotal después de descuentos por línea (para mostrar en ticket)
$subtotal_sin_descuentos_linea = $factura['subtotal']; // Este ya es el subtotal sin descuentos por línea
// Función para enmascarar documento según configuración
function formatearDocumentoCliente($doc, $tipo, $formato) {
    if (!$doc) return '';
    if ($formato === 'quitar') return '';
    if ($formato === 'enmascarada') {
        // Si tiene guiones, enmascaramos el centro
        if (strpos($doc, '-') !== false) {
            $partes = explode('-', $doc);
            if (count($partes) >= 2) {
                $inicio = $partes[0];
                $fin = end($partes);
                return $inicio . '-XXXXXXX-' . $fin;
            }
        }
        // Si no tiene guiones, mostramos solo inicio y fin
        if (strlen($doc) > 6) {
            return substr($doc, 0, 3) . 'XXXX' . substr($doc, -2);
        }
    }
    return $doc;
}

$subtotal_con_descuentos_linea = $subtotal_sin_descuentos_linea - $total_descuentos_linea;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo htmlspecialchars($factura['numero_factura'] . ' - ' . $factura['cliente_nombre']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
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
        .btn-volver {
            background: #e0e0e0;
            color: #333;
        }
        .btn-volver:hover {
            background: #d0d0d0;
        }
        .btn-imprimir {
            background: #2196f3;
            color: white;
        }
        .btn-imprimir:hover {
            background: #1976d2;
        }
        .btn-anular {
            background: #f44336;
            color: white;
        }
        .btn-anular:hover {
            background: #d32f2f;
        }
        
        /* FORMATO CARTA */
        .factura-carta {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .factura-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        .empresa-info h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .empresa-info p {
            color: #666;
            font-size: 14px;
            margin: 3px 0;
        }
        .factura-info {
            text-align: right;
        }
        .numero-factura {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .estado-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        .estado-pagada {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .estado-anulada {
            background: #ffebee;
            color: #c62828;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-section p {
            color: #333;
            font-size: 14px;
            margin: 5px 0;
        }
        .info-section strong {
            color: #666;
            font-weight: 600;
        }
        .detalle-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .detalle-table thead {
            background: #f8f9ff;
        }
        .detalle-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        .detalle-table td {
            padding: 12px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }
        .detalle-table tbody tr:hover {
            background: #f8f9ff;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .tipo-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .tipo-producto {
            background: #e3f2fd;
            color: #1976d2;
        }
        .tipo-servicio {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .descuento-linea-info {
            font-size: 11px;
            color: #f44336;
            margin-top: 3px;
        }
        .totales {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .totales-box {
            width: 350px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 15px;
        }
        .total-row.final {
            border-top: 2px solid #667eea;
            padding-top: 15px;
            margin-top: 10px;
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .alerta-anulada {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alerta-anulada h4 {
            color: #c62828;
            margin-bottom: 5px;
        }
        .alerta-anulada p {
            color: #666;
            font-size: 14px;
        }
        .descuentos-resumen {
            background: #fff3cd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        .descuentos-resumen h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        .descuentos-resumen p {
            color: #856404;
            font-size: 14px;
            margin: 3px 0;
        }
        
        /* FORMATO TICKET */
        .factura-ticket {
            background: white;
            width: 300px;
            margin: 0 auto;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            display: none;
        }
        .factura-ticket.active {
            display: block;
        }
        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .ticket-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .ticket-header p {
            font-size: 11px;
            margin: 2px 0;
        }
        .ticket-section {
            margin: 15px 0;
            font-size: 12px;
        }
        .ticket-section strong {
            display: block;
            margin-bottom: 5px;
        }
        .ticket-linea {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 11px;
        }
        .ticket-detalle {
            border-top: 1px dashed #333;
            border-bottom: 1px dashed #333;
            padding: 10px 0;
            margin: 15px 0;
        }
        .ticket-item {
            margin: 8px 0;
        }
        .ticket-item-desc {
            font-size: 11px;
            margin-bottom: 2px;
        }
        .ticket-item-calc {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }
        .ticket-item-descuento {
            font-size: 9px;
            color: #f44336;
            text-align: right;
            margin-top: 1px;
        }
        .ticket-totales {
            border-top: 2px dashed #333;
            padding-top: 10px;
            margin-top: 15px;
        }
        .ticket-total-final {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid #333;
        }
        .ticket-footer {
            text-align: center;
            margin-top: 15px;
            border-top: 2px dashed #333;
            padding-top: 10px;
            font-size: 10px;
        }
        .ticket-descuento-global {
            color: #f44336;
        }
        .ticket-descuento-linea {
            color: #ff9800;
        }
        
        /* Modal para seleccionar formato */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            text-align: center;
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn-formato {
            flex: 1;
            padding: 15px;
            font-size: 16px;
            cursor: pointer;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-formato:hover {
            background: #667eea;
            color: white;
        }
        
        /* Estilos para impresión */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .acciones, .modal {
                display: none !important;
            }
            .factura-carta {
                box-shadow: none;
                padding: 20px;
            }
            .factura-ticket {
                width: 80mm;
                margin: 0;
                padding: 5mm;
            }
        }
    </style>
</head>
<body>
    <!-- Modal de selección de formato -->
    <div class="modal" id="modalFormato">
        <div class="modal-content">
            <h3>Seleccionar formato de impresión</h3>
            <p style="color: #666; margin-bottom: 20px;">Elige el formato que desees imprimir</p>
            <div class="modal-buttons">
                <button class="btn-formato" onclick="imprimirCarta()">
                    📄<br>Formato Carta
                </button>
                <button class="btn-formato" onclick="imprimirTicket()">
                    🎫<br>Formato Ticket
                </button>
            </div>
            <button class="btn" onclick="cerrarModal()" style="margin-top: 15px; background: #e0e0e0;">Cancelar</button>
        </div>
    </div>

    <div class="container">
        <!-- Botones de acción -->
        <div class="acciones">
            <a href="nueva_factura.php" class="btn btn-volver">← Volver</a>
            <div>
                <button onclick="mostrarModalFormato()" class="btn btn-imprimir">🖨️ Imprimir</button>
                <?php if ($puede_anular): ?>
                    <button onclick="anularFactura()" class="btn btn-anular">❌ Anular Factura</button>
                <?php endif; ?>
            </div>
        </div>

        

        <!-- FORMATO CARTA (Default) -->
        <div class="factura-carta" id="facturaCarta">
            
            <?php if ($es_anulada): ?>
                <div class="alerta-anulada">
                    <h4>⚠️ FACTURA ANULADA</h4>
                    <p><strong>Fecha:</strong> <?php echo $factura['fecha_anulacion']->format('d/m/Y H:i:s'); ?></p>
                    <p><strong>Motivo:</strong> <?php echo htmlspecialchars($factura['motivo_anulacion']); ?></p>
                </div>
            <?php endif; ?>

            <div class="factura-header" style="display: flex; align-items: center; gap: 20px;">
                <?php if(!empty($config_empresa['logo_url'])): ?>
                    <div class="logo-container" style="flex: 0 0 120px;">
                        <img src="../<?php echo htmlspecialchars($config_empresa['logo_url']); ?>" alt="Logo" style="width: 100%; max-height: 100px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <div class="empresa-info" style="flex: 1;">
                    <h1 style="margin: 0; font-size: 28px;"><?php echo htmlspecialchars($config_empresa['nombre_empresa'] ?? ''); ?></h1>
                    <p style="margin: 5px 0 0 0;">RNC: <?php echo htmlspecialchars($config_empresa['rnc'] ?? ''); ?></p>
                    <p style="margin: 2px 0 0 0;">Tel: <?php echo htmlspecialchars($config_empresa['telefono'] ?? ''); ?></p>
                    <p style="margin: 2px 0 0 0;">Dirección: <?php echo htmlspecialchars($config_empresa['direccion'] ?? ''); ?></p>
                    <?php if (!empty($config_empresa['email'])): ?>
                        <p style="margin: 2px 0 0 0;">Email: <?php echo htmlspecialchars($config_empresa['email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($config_empresa['sitio_web'])): ?>
                        <p style="margin: 2px 0 0 0;">Web: <?php echo htmlspecialchars($config_empresa['sitio_web']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="factura-info">
                    <div class="numero-factura"><?php echo htmlspecialchars($factura['numero_factura']); ?></div>
                    <p style="color: #666; font-size: 14px;"><?php echo $fecha_factura; ?></p>
                    <span class="estado-badge estado-<?php echo $factura['estado']; ?>">
                        <?php echo strtoupper($factura['estado']); ?>
                    </span>
                </div>
            </div>

            <!-- Información del cliente y venta -->
            <div class="info-grid">
                <div class="info-section">
                    <h3>Cliente</h3>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($factura['cliente_nombre']); ?></p>
                    <?php 
                        $doc_formateado = formatearDocumentoCliente($factura['cliente_documento'], $factura['tipo_documento'], $config_empresa['formato_documento_cliente'] ?? 'completa');
                        if (!empty($doc_formateado)): 
                    ?>
                        <p><strong><?php echo htmlspecialchars($factura['tipo_documento']); ?>:</strong> <?php echo htmlspecialchars($doc_formateado); ?></p>
                    <?php endif; ?>
                    <?php if ($factura['cliente_telefono']): ?>
                        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($factura['cliente_telefono']); ?></p>
                    <?php endif; ?>
                    <?php if ($factura['cliente_direccion']): ?>
                        <p><strong>Dirección:</strong> <?php echo htmlspecialchars($factura['cliente_direccion']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="info-section">
                    <h3>Información de Venta</h3>
                    <p><strong>Cajero:</strong> <?php echo htmlspecialchars($factura['usuario_nombre']); ?></p>
                    <p><strong>Tipo de Pago:</strong> 
                        <?php 
                        $tipos_pago = [
                            'efectivo' => '💵 Efectivo',
                            'transferencia' => '🏦 Transferencia',
                            'credito' => '📝 Crédito'
                        ];
                        echo $tipos_pago[$factura['tipo_pago']] ?? $factura['tipo_pago'];
                        ?>
                    </p>
                    <?php if ($factura['tipo_pago'] === 'efectivo' && $factura['efectivo_recibido']): ?>
                        <p><strong>Efectivo Recibido:</strong> RD$ <?php echo number_format($factura['efectivo_recibido'], 2); ?></p>
                        <p><strong>Cambio:</strong> RD$ <?php echo number_format($cambio, 2); ?></p>
                    <?php endif; ?>
                    <?php if ($factura['observaciones']): ?>
                        <p><strong>Observaciones:</strong> <?php echo htmlspecialchars($factura['observaciones']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detalle de productos/servicios CON DESCUENTOS POR LÍNEA -->
            <table class="detalle-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Desc.</th>
                        <th class="text-right">Impuesto</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detalles)): ?>
                        <?php foreach($detalles as $detalle): 
                            $subtotal_linea = $detalle['precio_unitario'] * $detalle['cantidad'];
                            $descuento_linea = $detalle['descuento_linea'] ?? 0;
                            $total_linea = $detalle['total_linea'];
                            $tiene_descuento_linea = ($descuento_linea > 0);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detalle['producto_codigo'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($detalle['descripcion']); ?>
                                <?php if ($tiene_descuento_linea): 
                                    $base_calc = $detalle['precio_unitario'] * $detalle['cantidad'];
                                    $desc_pct = $base_calc > 0 ? ($descuento_linea / $base_calc) * 100 : 0;
                                    $desc_pct_str = abs(round($desc_pct) - $desc_pct) < 0.01 ? round($desc_pct) : number_format($desc_pct, 2);
                                ?>
                                    <div class="descuento-linea-info">
                                        💰 Descuento aplicado (<?php echo $desc_pct_str; ?>%): RD$ <?php echo number_format($descuento_linea, 2); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="tipo-badge tipo-<?php echo $detalle['tipo_item']; ?>">
                                    <?php echo $detalle['tipo_item'] === 'producto' ? '📦' : '🛠️'; ?>
                                    <?php echo ucfirst($detalle['tipo_item']); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                            <td class="text-right">RD$ <?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                            <td class="text-right">RD$ <?php echo number_format($subtotal_linea, 2); ?></td>
                            <td class="text-right" style="<?php echo $tiene_descuento_linea ? 'color: #f44336;' : ''; ?>">
                                <?php echo $tiene_descuento_linea ? '-RD$ ' . number_format($descuento_linea, 2) : '-'; ?>
                            </td>
                            <td class="text-right" style="color: #764ba2;">
                                <?php 
                                    if ($detalle['impuesto_linea'] > 0) {
                                        echo 'RD$ ' . number_format($detalle['impuesto_linea'], 2);
                                        if ($detalle['impuesto_porcentaje']) {
                                            echo '<br><small style="font-size: 10px; color: #999;">(' . number_format($detalle['impuesto_porcentaje'], 0) . '%)</small>';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td class="text-right"><strong>RD$ <?php echo number_format($total_linea, 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center" style="color: #999; padding: 20px;">
                                No se encontraron detalles para esta factura
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Totales COMPLETOS con desglose de descuentos -->
            <div class="totales">
                <div class="totales-box">
                    <div class="total-row">
                        <span>Subtotal Bruto:</span>
                        <span>RD$ <?php echo number_format($subtotal_sin_descuentos_linea, 2); ?></span>
                    </div>
                    
                    <?php if ($total_descuentos_linea > 0): ?>
                        <div class="total-row" style="color: #ff9800;">
                            <span>- Descuentos por Producto:</span>
                            <span>- RD$ <?php echo number_format($total_descuentos_linea, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($total_descuentos_linea > 0): ?>
                        <div class="total-row">
                            <span>Subtotal con desc. línea:</span>
                            <span>RD$ <?php echo number_format($subtotal_con_descuentos_linea, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                        if ($factura['descuento'] > 0): 
                            $desc_global_pct_str = "";
                            if ($subtotal_con_descuentos_linea > 0) {
                                $pct = ($factura['descuento'] / $subtotal_con_descuentos_linea) * 100;
                                if (abs(round($pct) - $pct) < 0.01) {
                                    $desc_global_pct_str = " (" . round($pct) . "%)";
                                } else {
                                    $desc_global_pct_str = " (" . number_format($pct, 2) . "%)";
                                }
                            }
                    ?>
                        <div class="total-row" style="color: #f44336;">
                            <span>- Descuento Global<?php echo $desc_global_pct_str; ?>:</span>
                            <span>- RD$ <?php echo number_format($factura['descuento'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($factura['impuesto'] > 0): ?>
                        <div class="total-row">
                            <span>ITBIS:</span>
                            <span>RD$ <?php echo number_format($factura['impuesto'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row final">
                        <span>TOTAL FACTURA:</span>
                        <span>RD$ <?php echo number_format($total_factura_base, 2); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($cuotas)): ?>
            <!-- SECCIÓN DE FINANCIAMIENTO (CARTA) -->
            <div style="margin-top: 30px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; page-break-inside: avoid;">
                <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e0e0e0;">
                    <h3 style="margin: 0; font-size: 16px; color: #333;">📊 Detalles de Financiamiento</h3>
                </div>
                <div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; background: white;">
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Monto Inicial</span>
                        <strong style="font-size: 16px; color: #10b981;">RD$ <?php echo number_format($factura['monto_inicial'], 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Balance financiado</span>
                        <strong style="font-size: 16px; color: #64748b;">RD$ <?php echo number_format($balance_financiado, 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Interés generado (<?php echo number_format($factura['interes_porcentaje'], 2); ?>%)</span>
                        <strong style="font-size: 16px; color: #ef4444;">RD$ <?php echo number_format($interes_generado, 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Total financiado</span>
                        <strong style="font-size: 16px; color: #f59e0b;">RD$ <?php echo number_format($factura['total'] - $factura['monto_inicial'], 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Pendiente actual</span>
                        <strong style="font-size: 16px; color: #dc2626;">RD$ <?php echo number_format($pendiente_actual, 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Monto total a pagar</span>
                        <strong style="font-size: 16px; color: #1e293b;">RD$ <?php echo number_format($factura['total'], 2); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Plan de Pago</span>
                        <strong style="font-size: 16px; color: #334155;">
                            <?php 
                                echo $factura['cantidad_cuotas'] . " pagos ";
                                $frec = $factura['frecuencia_plazos'];
                                if ($frec === 'semanal') echo "Semanales";
                                elseif ($frec === 'quincenal') echo "Quincenales";
                                elseif ($frec === 'mensual') echo "Mensuales";
                                else echo ucfirst($frec);
                            ?>
                        </strong>
                    </div>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; background: white; font-size: 13px;">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="padding: 10px 20px; text-align: left; color: #475569; border-bottom: 1px solid #e2e8f0;">No.</th>
                            <th style="padding: 10px 20px; text-align: left; color: #475569; border-bottom: 1px solid #e2e8f0;">Vencimiento</th>
                            <th style="padding: 10px 20px; text-align: right; color: #475569; border-bottom: 1px solid #e2e8f0;">Monto Cuota</th>
                            <th style="padding: 10px 20px; text-align: center; color: #475569; border-bottom: 1px solid #e2e8f0;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cuotas as $cuota): ?>
                        <tr>
                            <td style="padding: 8px 20px; border-bottom: 1px solid #f1f5f9;"><?php echo $cuota['numero_cuota']; ?></td>
                            <td style="padding: 8px 20px; border-bottom: 1px solid #f1f5f9;"><?php echo $cuota['fecha_vencimiento']->format('d/m/Y'); ?></td>
                            <td style="padding: 8px 20px; border-bottom: 1px solid #f1f5f9; text-align: right;">RD$ <?php echo number_format($cuota['monto_cuota'], 2); ?></td>
                            <td style="padding: 8px 20px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                <?php 
                                    $est = $cuota['estado'];
                                    $bg = '#fef3c7'; $cl = '#92400e';
                                    if ($est === 'pagada') { $bg = '#d1fae5'; $cl = '#065f46'; }
                                    elseif ($est === 'parcial') { $bg = '#e0f2fe'; $cl = '#0369a1'; }
                                ?>
                                <span style="padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; background: <?php echo $bg; ?>; color: <?php echo $cl; ?>;">
                                    <?php echo strtoupper($est); ?>
                                </span>
                                <?php if ($est === 'parcial'): ?>
                                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                                        Resta: RD$ <?php echo number_format($cuota['monto_cuota'] - $cuota['monto_abonado'], 2); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer">
                <p><?php echo nl2br(htmlspecialchars($config_empresa['mensaje_factura'] ?? 'Gracias por su compra')); ?></p>
                <p>• Generado el <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>

        <!-- FORMATO TICKET (Oculto por defecto) CON DESCUENTOS POR LÍNEA -->
        <div class="factura-ticket" id="facturaTicket">
            <div class="ticket-header">
                <?php if(!empty($config_empresa['logo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($config_empresa['logo_url']); ?>" alt="Logo" style="width: 50mm; max-height: 25mm; object-fit: contain; margin-bottom: 5px;">
                <?php endif; ?>
                <h2 style="margin: 0; font-size: 16px;"><?php echo htmlspecialchars($config_empresa['nombre_empresa'] ?? ''); ?></h2>
                <p>RNC: <?php echo htmlspecialchars($config_empresa['rnc'] ?? ''); ?></p>
                <p>Tel: <?php echo htmlspecialchars($config_empresa['telefono'] ?? ''); ?></p>
                <p>Dir: <?php echo htmlspecialchars($config_empresa['direccion'] ?? ''); ?></p>
                <?php if (!empty($config_empresa['email'])): ?>
                    <p>Email: <?php echo htmlspecialchars($config_empresa['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($config_empresa['sitio_web'])): ?>
                    <p>Web: <?php echo htmlspecialchars($config_empresa['sitio_web']); ?></p>
                <?php endif; ?>
            </div>

            <div class="ticket-section">
                <div class="ticket-linea">
                    <span>Factura:</span>
                    <strong><?php echo htmlspecialchars($factura['numero_factura']); ?></strong>
                </div>
                <div class="ticket-linea">
                    <span>Fecha:</span>
                    <span><?php echo $fecha_factura; ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Cajero:</span>
                    <span><?php echo htmlspecialchars($factura['usuario_nombre']); ?></span>
                </div>
            </div>

            <div class="ticket-section">
                <strong>Cliente:</strong>
                <p style="font-size: 11px;"><?php echo htmlspecialchars($factura['cliente_nombre']); ?></p>
                <?php 
                    $doc_ticket = formatearDocumentoCliente($factura['cliente_documento'], $factura['tipo_documento'], $config_empresa['formato_documento_cliente'] ?? 'completa');
                    if (!empty($doc_ticket)): 
                ?>
                <p style="font-size: 10px;"><?php echo htmlspecialchars($factura['tipo_documento']); ?>: <?php echo htmlspecialchars($doc_ticket); ?></p>
                <?php endif; ?>
            </div>

            <div class="ticket-detalle">
                <?php foreach($detalles as $detalle): 
                    $subtotal_linea = $detalle['precio_unitario'] * $detalle['cantidad'];
                    $descuento_linea = $detalle['descuento_linea'] ?? 0;
                    $tiene_descuento_linea = ($descuento_linea > 0);
                ?>
                <div class="ticket-item">
                    <div class="ticket-item-desc">
                        <?php echo htmlspecialchars($detalle['descripcion']); ?>
                    </div>
                    <div class="ticket-item-calc">
                        <span><?php echo $detalle['cantidad']; ?> x RD$ <?php echo number_format($detalle['precio_unitario'], 2); ?></span>
                        <span>RD$ <?php echo number_format($subtotal_linea, 2); ?></span>
                    </div>
                    <?php if ($tiene_descuento_linea): 
                        $base_calc = $detalle['precio_unitario'] * $detalle['cantidad'];
                        $desc_pct = $base_calc > 0 ? ($descuento_linea / $base_calc) * 100 : 0;
                        $desc_pct_str = abs(round($desc_pct) - $desc_pct) < 0.01 ? round($desc_pct) : number_format($desc_pct, 2);
                    ?>
                    <div class="ticket-item-descuento">
                        Desc. línea (<?php echo $desc_pct_str; ?>%): -RD$ <?php echo number_format($descuento_linea, 2); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="ticket-totales">
                <div class="ticket-linea">
                    <span>Subtotal Bruto:</span>
                    <span>RD$ <?php echo number_format($subtotal_sin_descuentos_linea, 2); ?></span>
                </div>
                
                <?php if ($total_descuentos_linea > 0): ?>
                <div class="ticket-linea ticket-descuento-linea">
                    <span>- Desc. por Producto:</span>
                    <span>- RD$ <?php echo number_format($total_descuentos_linea, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_descuentos_linea > 0): ?>
                <div class="ticket-linea">
                    <span>Subtotal con desc. línea:</span>
                    <span>RD$ <?php echo number_format($subtotal_con_descuentos_linea, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php 
                    if ($factura['descuento'] > 0): 
                        $desc_global_pct_str = "";
                        if ($subtotal_con_descuentos_linea > 0) {
                            $pct = ($factura['descuento'] / $subtotal_con_descuentos_linea) * 100;
                            if (abs(round($pct) - $pct) < 0.01) {
                                $desc_global_pct_str = " (" . round($pct) . "%)";
                            } else {
                                $desc_global_pct_str = " (" . number_format($pct, 2) . "%)";
                            }
                        }
                ?>
                <div class="ticket-linea ticket-descuento-global">
                    <span>- Descuento Global<?php echo $desc_global_pct_str; ?>:</span>
                    <span>- RD$ <?php echo number_format($factura['descuento'], 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($factura['impuesto'] > 0): ?>
                <div class="ticket-linea">
                    <span>Impuesto:</span>
                    <span>RD$ <?php echo number_format($factura['impuesto'], 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="ticket-linea ticket-total-final">
                    <span>TOTAL FACTURA:</span>
                    <strong>RD$ <?php echo number_format($total_factura_base, 2); ?></strong>
                </div>
            </div>

            <?php if ($factura['tipo_pago'] === 'efectivo' && $factura['efectivo_recibido']): ?>
            <div class="ticket-section" style="border-top: 1px dashed #333; padding-top: 10px;">
                <div class="ticket-linea">
                    <span>Efectivo:</span>
                    <span>RD$ <?php echo number_format($factura['efectivo_recibido'], 2); ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Cambio:</span>
                    <strong>RD$ <?php echo number_format($cambio, 2); ?></strong>
                </div>
            </div>
            <?php endif; ?>

            <div class="ticket-section" style="text-align: center; font-size: 10px;">
                <p>Tipo de Pago: <?php echo strtoupper($factura['tipo_pago']); ?></p>
            </div>

            <?php if (!empty($cuotas)): ?>
            <!-- SECCIÓN DE FINANCIAMIENTO (TICKET) -->
            <div class="ticket-section" style="border-top: 1px dashed #333; padding-top: 10px; font-size: 11px;">
                <p style="text-align: center; font-weight: bold; margin-bottom: 5px;">PLAN DE PAGOS</p>
                <div class="ticket-linea">
                    <span>Monto Inicial:</span>
                    <span>RD$ <?php echo number_format($factura['monto_inicial'], 2); ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Balance financiado:</span>
                    <span>RD$ <?php echo number_format($balance_financiado, 2); ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Interés generado (<?php echo number_format($factura['interes_porcentaje'], 2); ?>%):</span>
                    <span>RD$ <?php echo number_format($interes_generado, 2); ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Total financiado:</span>
                    <span>RD$ <?php echo number_format($factura['total'] - $factura['monto_inicial'], 2); ?></span>
                </div>
                <div class="ticket-linea" style="font-weight: bold; border-top: 1px solid #333; margin-top: 5px; padding-top: 5px;">
                    <span>Monto total a pagar:</span>
                    <span>RD$ <?php echo number_format($factura['total'], 2); ?></span>
                </div>
                <div class="ticket-linea" style="color: #d32f2f; font-weight: bold;">
                    <span>PENDIENTE ACTUAL:</span>
                    <span>RD$ <?php echo number_format($pendiente_actual, 2); ?></span>
                </div>
                <div class="ticket-linea">
                    <span>Cuotas:</span>
                    <span>
                        <?php 
                            echo $factura['cantidad_cuotas'] . " ";
                            $frec = $factura['frecuencia_plazos'];
                            if ($frec === 'semanal') echo "Semanales";
                            elseif ($frec === 'quincenal') echo "Quincenales";
                            elseif ($frec === 'mensual') echo "Mensuales";
                            else echo ucfirst($frec);
                        ?>
                    </span>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #333;">
                            <th style="text-align: left;">No.</th>
                            <th style="text-align: left;">Fecha</th>
                            <th style="text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cuotas as $cuota): ?>
                        <tr>
                            <td><?php echo $cuota['numero_cuota']; ?></td>
                            <td><?php echo $cuota['fecha_vencimiento']->format('d/m/Y'); ?></td>
                            <td style="text-align: right;">
                                RD$ <?php echo number_format($cuota['monto_cuota'], 2); ?>
                                <?php if ($cuota['estado'] === 'parcial'): ?>
                                    <br><small>Resta: <?php echo number_format($cuota['monto_cuota'] - $cuota['monto_abonado'], 2); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="ticket-footer">
                <p><?php echo nl2br(htmlspecialchars($config_empresa['mensaje_factura'] ?? '¡Gracias por su compra!')); ?></p>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        function mostrarModalFormato() {
            document.getElementById('modalFormato').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalFormato').classList.remove('active');
        }

        function imprimirCarta() {
            // Ocultar ticket y mostrar carta
            document.getElementById('facturaTicket').classList.remove('active');
            document.getElementById('facturaCarta').style.display = 'block';
            cerrarModal();
            
            // Esperar un momento para que se apliquen los estilos
            setTimeout(() => {
                window.print();
            }, 100);
        }

        function imprimirTicket() {
            // Ocultar carta y mostrar ticket
            document.getElementById('facturaCarta').style.display = 'none';
            document.getElementById('facturaTicket').classList.add('active');
            cerrarModal();
            
            // Esperar un momento para que se apliquen los estilos
            setTimeout(() => {
                window.print();
                
                // Después de imprimir, restaurar vista normal
                setTimeout(() => {
                    document.getElementById('facturaCarta').style.display = 'block';
                    document.getElementById('facturaTicket').classList.remove('active');
                }, 500);
            }, 100);
        }

        function anularFactura() {
            const motivo = prompt('Ingrese el motivo de anulación:');
            
            if (!motivo || motivo.trim() === '') {
                alert('Debe especificar un motivo para anular la factura');
                return;
            }
            
            if (!confirm('¿Está seguro de anular esta factura? Esta acción NO se puede revertir.')) {
                return;
            }
            
            // Enviar solicitud de anulación
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../Ver_Facturas/anular_factura.php';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'factura_id';
            inputId.value = '<?php echo $factura_id; ?>';
            
            const inputMotivo = document.createElement('input');
            inputMotivo.type = 'hidden';
            inputMotivo.name = 'motivo';
            inputMotivo.value = motivo;
            
            form.appendChild(inputId);
            form.appendChild(inputMotivo);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
<?php 
// Cerrar conexión al final
if (isset($conn)) {
    sqlsrv_close($conn);
}
?>