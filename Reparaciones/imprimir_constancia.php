<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) {
    die("ID de reparación inválido.");
}

// Obtener datos
$sql = "SELECT r.*, c.nombre as cliente_nombre, c.documento as cliente_doc, c.telefono as cliente_telefono, c.direccion as cliente_direccion,
        u.nombre as usuario_nombre
        FROM reparaciones r
        JOIN clientes c ON r.cliente_id = c.id
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$r) {
    die("Reparación no encontrada.");
}

// Obtener piezas cotizadas
$sql_piezas = "SELECT rp.cantidad, rp.precio, p.nombre 
               FROM reparacion_piezas rp 
               JOIN productos p ON rp.producto_id = p.id 
               WHERE rp.reparacion_id = ?";
$stmt_piezas = sqlsrv_query($conn, $sql_piezas, array($id));
$piezas_cotizadas = [];
$total_piezas = 0;
if($stmt_piezas) {
    while($p = sqlsrv_fetch_array($stmt_piezas, SQLSRV_FETCH_ASSOC)) {
        $piezas_cotizadas[] = $p;
        $total_piezas += ($p['precio'] * $p['cantidad']);
    }
}
$mano_obra = max(0, floatval($r['costo_estimado']) - $total_piezas);

// Obtener datos de la empresa (si existe configuración)
$empresa_nombre = "Sistema de Facturación";
$empresa_rnc = "";
$empresa_tel = "";
$empresa_dir = "";

$sql_empresa = "SELECT * FROM config_empresa WHERE id = 1";
$stmt_empresa = sqlsrv_query($conn, $sql_empresa);
if($stmt_empresa && $empresa = sqlsrv_fetch_array($stmt_empresa, SQLSRV_FETCH_ASSOC)) {
    $empresa_nombre = $empresa['nombre_empresa'] ?: $empresa_nombre;
    $empresa_rnc = $empresa['rnc'];
    $empresa_tel = $empresa['telefono'];
    $empresa_dir = $empresa['direccion'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Recepción - <?php echo htmlspecialchars($r['codigo_ticket'] . ' - ' . ($r['cliente_nombre'] ?? '')); ?></title>
    <!-- JsBarcode CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #e2e8f0; margin: 0; padding: 20px; color: #000; }
        .ticket { background: white; max-width: 400px; margin: 0 auto; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 15px; }
        .header h1 { font-size: 20px; margin: 0 0 5px 0; font-family: Arial, sans-serif; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 13px; }
        
        .title { text-align: center; font-size: 16px; font-weight: bold; margin: 15px 0; font-family: Arial, sans-serif; text-transform: uppercase; }
        
        .section { margin-bottom: 15px; font-size: 13px; line-height: 1.4; }
        .section strong { display: inline-block; width: 100px; }
        
        .divider { border-bottom: 1px dashed #000; margin: 15px 0; }
        
        .barcode-container { text-align: center; margin: 20px 0; overflow: hidden; }
        .barcode-container svg { max-width: 100%; height: auto; }
        
        .terms { font-size: 10px; text-align: justify; margin-top: 20px; line-height: 1.3; }
        .terms ul { padding-left: 15px; margin: 5px 0; }
        
        .signatures { margin-top: 40px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 45%; }
        .sig-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; font-size: 12px; }
        
        .print-btn { display: block; width: 100%; padding: 15px; background: #4f46e5; color: white; border: none; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; font-family: Arial, sans-serif; text-decoration: none; margin-bottom: 20px; border-radius: 8px; }
        .print-btn:hover { background: #4338ca; }
        .back-btn { display: block; text-align: center; margin-top: 10px; font-family: Arial, sans-serif; color: #4f46e5; text-decoration: none; }
        
        @media print {
            body { background: white; padding: 0; }
            .ticket { box-shadow: none; max-width: 100%; padding: 0; }
            .print-btn, .back-btn { display: none; }
        }
    </style>
</head>
<body>
    <div style="max-width: 400px; margin: 0 auto;">
        <button onclick="window.print()" class="print-btn">🖨️ Imprimir Ticket</button>
        <div class="ticket">
            <div class="header">
                <h1><?php echo htmlspecialchars($empresa_nombre); ?></h1>
                <?php if($empresa_rnc): ?><p>RNC: <?php echo htmlspecialchars($empresa_rnc ?? ''); ?></p><?php endif; ?>
                <?php if($empresa_tel): ?><p>Tel: <?php echo htmlspecialchars($empresa_tel ?? ''); ?></p><?php endif; ?>
                <?php if($empresa_dir): ?><p><?php echo htmlspecialchars($empresa_dir ?? ''); ?></p><?php endif; ?>
            </div>
            
            <div class="title">Constancia de Recepción</div>
            
            <div class="barcode-container">
                <svg id="barcode"></svg>
            </div>
            
            <div class="section">
                <div><strong>Ticket:</strong> <?php echo htmlspecialchars($r['codigo_ticket'] ?? ''); ?></div>
                <div><strong>Fecha:</strong> <?php echo date_format($r['fecha_recepcion'], 'd/m/Y h:i A'); ?></div>
                <div><strong>Atendido por:</strong> <?php echo htmlspecialchars($r['usuario_nombre'] ?? ''); ?></div>
            </div>
            
            <div class="divider"></div>
            
            <div class="section">
                <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">--- DATOS DEL CLIENTE ---</div>
                <div><strong>Nombre:</strong> <?php echo htmlspecialchars($r['cliente_nombre'] ?? ''); ?></div>
                <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($r['cliente_telefono'] ?? ''); ?></div>
                <div><strong>Doc:</strong> <?php echo htmlspecialchars($r['cliente_doc'] ?? ''); ?></div>
            </div>
            
            <div class="divider"></div>
            
            <div class="section">
                <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">--- DATOS DEL EQUIPO ---</div>
                <div><strong>Equipo:</strong> <?php echo htmlspecialchars($r['tipo_equipo'] ?? ''); ?></div>
                <div><strong>Marca/Mod:</strong> <?php echo htmlspecialchars($r['marca_modelo'] ?? ''); ?></div>
                <div><strong>IMEI/Serial:</strong> <?php echo htmlspecialchars($r['serial_imei'] ?? 'NO ESPECIFICADO'); ?></div>
                <div style="margin-top: 8px;"><strong>Problema Reportado:</strong><br><?php echo nl2br(htmlspecialchars($r['problema_reportado'] ?? '')); ?></div>
                <?php if($r['condicion_fisica']): ?>
                <div style="margin-top: 8px;"><strong>Condición Física:</strong><br><?php echo nl2br(htmlspecialchars($r['condicion_fisica'] ?? '')); ?></div>
                <?php endif; ?>
                
                <?php if(count($piezas_cotizadas) > 0 || floatval($r['costo_estimado']) > 0): ?>
                <div style="margin-top: 15px;">
                    <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">--- COTIZACIÓN ESTIMADA ---</div>
                    <table style="width: 100%; font-size: 11px; margin-bottom: 5px; border-collapse: collapse;">
                        <tr style="border-bottom: 1px dashed #000;">
                            <th style="text-align: left; padding-bottom: 3px;">Cant. Descripción</th>
                            <th style="text-align: right; padding-bottom: 3px;">Total</th>
                        </tr>
                        <?php if($mano_obra > 0): ?>
                        <tr>
                            <td style="padding-top: 3px;">1x Mano de Obra / Servicio</td>
                            <td style="text-align: right; padding-top: 3px;">RD$ <?php echo number_format($mano_obra, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($piezas_cotizadas as $pz): ?>
                        <tr>
                            <td><?php echo $pz['cantidad']; ?>x <?php echo htmlspecialchars($pz['nombre']); ?></td>
                            <td style="text-align: right;">RD$ <?php echo number_format($pz['precio'] * $pz['cantidad'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <div style="text-align: right; font-weight: bold; font-size: 13px; margin-top: 5px; border-top: 1px dashed #000; padding-top: 5px;">
                        GRAN TOTAL: RD$ <?php echo number_format($r['costo_estimado'], 2); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="divider"></div>
            
            <div class="terms">
                <strong>Términos y Condiciones:</strong>
                <ul>
                    <li>No nos hacemos responsables por equipos abandonados después de 30 días de la notificación de reparación. Serán considerados para reciclaje de piezas.</li>
                    <li>No somos responsables por la pérdida de datos, contactos o archivos. Por favor, asegúrese de tener un respaldo.</li>
                    <li>Toda revisión de equipo conlleva un cargo mínimo por diagnóstico en caso de no aceptar la reparación.</li>
                    <li>Debe presentar este ticket para retirar su equipo.</li>
                </ul>
            </div>
            
            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-line">Firma Tienda</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line">Firma Cliente (Acuerdo)</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; font-size: 10px; font-weight: bold;">
                *** COPIA DEL ESTABLECIMIENTO ***
            </div>
            <div style="text-align: center; font-size: 10px;">
                (Imprima dos copias, una para el cliente y otra para anexar al equipo)
            </div>
        </div>
        <a href="index.php" class="back-btn">← Volver al módulo</a>
    </div>

    <script>
        // Generar Código de Barras
        JsBarcode("#barcode", "<?php echo htmlspecialchars($r['codigo_ticket'] ?? ''); ?>", {
            format: "CODE128",
            lineColor: "#000",
            width: 1.5,
            height: 50,
            displayValue: true,
            fontSize: 14,
            margin: 0
        });
    </script>
</body>
</html>
