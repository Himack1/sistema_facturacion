<?php
/**
 * IMPRIMIR CIERRE DE CAJA
 * Genera un ticket térmico con el resumen del turno
 * Estilo factura de supermercado
 * Incluye información de descuentos GLOBALES y POR LÍNEA
 */

session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['caja_id'])) {
    header("Location: verificar_caja.php");
    exit();
}

require_once '../conexion.php';
require_once '../includes/config_empresa.php';
$config_empresa = obtenerConfigEmpresa($conn);

// ==================================================
// OBTENER DATOS DE LA CAJA
// ==================================================
$sql_caja = "SELECT 
                c.id,
                c.fecha_apertura,
                c.monto_inicial,
                c.total_ventas_efectivo,
                c.total_ventas_transferencia,
                c.total_ventas_credito,
                c.observaciones_apertura,
                u.nombre as usuario_nombre
            FROM cajas c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.id = ? AND c.usuario_id = ?";

$stmt_caja = sqlsrv_query($conn, $sql_caja, array($_SESSION['caja_id'], $_SESSION['usuario_id']));
$caja = sqlsrv_fetch_array($stmt_caja, SQLSRV_FETCH_ASSOC);

// ==================================================
// OBTENER FACTURAS DEL TURNO CON DESCUENTOS (GLOBALES Y POR LÍNEA)
// ==================================================
$sql_facturas = "SELECT 
                    f.id,
                    f.numero_factura,
                    f.fecha,
                    f.tipo_pago,
                    f.subtotal,
                    f.descuento as descuento_global,
                    f.total,
                    f.estado,
                    c.nombre as cliente_nombre
                FROM facturas f
                INNER JOIN clientes c ON f.cliente_id = c.id
                WHERE f.caja_id = ? 
                ORDER BY f.fecha";

$stmt_facturas = sqlsrv_query($conn, $sql_facturas, array($_SESSION['caja_id']));

// Almacenar facturas y calcular totales
$facturas = array();
$total_descuentos_global = 0;
$total_descuentos_linea = 0;
$total_descuentos = 0;
$facturas_con_descuento_global = 0;
$facturas_con_descuento_linea = 0;

if ($stmt_facturas) {
    while ($row = sqlsrv_fetch_array($stmt_facturas, SQLSRV_FETCH_ASSOC)) {
        // Obtener descuentos por línea de ESTA factura
        $sql_descuentos_linea = "SELECT 
                                    SUM(descuento_linea) as total_descuento_linea
                                 FROM detalle_factura 
                                 WHERE factura_id = ?";
        $stmt_descuentos = sqlsrv_query($conn, $sql_descuentos_linea, array($row['id']));
        $descuentos = sqlsrv_fetch_array($stmt_descuentos, SQLSRV_FETCH_ASSOC);
        $descuento_linea_factura = $descuentos['total_descuento_linea'] ?? 0;
        
        // Agregar descuentos por línea al objeto factura
        $row['descuento_linea'] = $descuento_linea_factura;
        $row['descuento_total'] = $row['descuento_global'] + $descuento_linea_factura;
        
        $facturas[] = $row;
        
        // Solo contar descuentos de facturas pagadas
        if ($row['estado'] === 'pagada') {
            // Descuento global
            if ($row['descuento_global'] > 0) {
                $total_descuentos_global += $row['descuento_global'];
                $facturas_con_descuento_global++;
            }
            
            // Descuento por línea
            if ($descuento_linea_factura > 0) {
                $total_descuentos_linea += $descuento_linea_factura;
                $facturas_con_descuento_linea++;
            }
        }
    }
}

// Total de todos los descuentos
$total_descuentos = $total_descuentos_global + $total_descuentos_linea;

// ==================================================
// OBTENER ESTADÍSTICAS DETALLADAS DE DESCUENTOS POR LÍNEA
// ==================================================
$sql_estadisticas_linea = "SELECT 
                                COUNT(*) as lineas_con_descuento,
                                AVG(descuento_linea) as promedio_descuento_linea,
                                MAX(descuento_linea) as maximo_descuento_linea,
                                SUM(descuento_linea) as total_descuento_linea_turno
                           FROM detalle_factura df
                           INNER JOIN facturas f ON df.factura_id = f.id
                           WHERE f.caja_id = ? 
                             AND f.estado = 'pagada'
                             AND df.descuento_linea > 0";

$stmt_estadisticas = sqlsrv_query($conn, $sql_estadisticas_linea, array($_SESSION['caja_id']));
$estadisticas_linea = sqlsrv_fetch_array($stmt_estadisticas, SQLSRV_FETCH_ASSOC);

// ==================================================
// OBTENER ABONOS A CRÉDITO REALIZADOS EN ESTE TURNO
// ==================================================
$sql_abonos = "SELECT 
                ISNULL(SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END), 0) as abonos_efectivo,
                ISNULL(SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END), 0) as abonos_transferencia
               FROM pagos_credito 
               WHERE caja_id = ?";
$stmt_abonos = sqlsrv_query($conn, $sql_abonos, array($_SESSION['caja_id']));
$abonos_data = sqlsrv_fetch_array($stmt_abonos, SQLSRV_FETCH_ASSOC);

$abonos_efectivo = (float)$abonos_data['abonos_efectivo'];
$abonos_transferencia = (float)$abonos_data['abonos_transferencia'];

// Obtener detalle de abonos para el ticket
$sql_detalle_abonos = "SELECT p.monto, p.metodo_pago, c.nombre as cliente
                       FROM pagos_credito p
                       INNER JOIN clientes c ON p.cliente_id = c.id
                       WHERE p.caja_id = ?";
$stmt_det_abonos = sqlsrv_query($conn, $sql_detalle_abonos, array($_SESSION['caja_id']));
$lista_abonos = [];
if ($stmt_det_abonos) {
    while ($row_ab = sqlsrv_fetch_array($stmt_det_abonos, SQLSRV_FETCH_ASSOC)) {
        $lista_abonos[] = $row_ab;
    }
}

// ==================================================
// CALCULAR TOTALES
// ==================================================
$total_general = (float)$caja['total_ventas_efectivo'] + 
                 (float)$caja['total_ventas_transferencia'] + 
                 (float)$caja['total_ventas_credito'] +
                 $abonos_efectivo + $abonos_transferencia;

$monto_esperado = (float)$caja['monto_inicial'] + (float)$caja['total_ventas_efectivo'] + $abonos_efectivo;

$fecha_apertura = $caja['fecha_apertura']->format('d/m/Y H:i');
$hora_actual = date('d/m/Y H:i');

// Contar facturas
$sql_count = "SELECT 
                COUNT(*) as total_facturas,
                SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                SUM(CASE WHEN estado = 'anulada' THEN 1 ELSE 0 END) as anuladas,
                SUM(CASE WHEN estado = 'pagada' THEN subtotal ELSE 0 END) as total_subtotal
              FROM facturas 
              WHERE caja_id = ?";
$stmt_count = sqlsrv_query($conn, $sql_count, array($_SESSION['caja_id']));
$conteo = sqlsrv_fetch_array($stmt_count, SQLSRV_FETCH_ASSOC);

// Cerrar conexión DESPUÉS de obtener todos los datos
sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - <?php echo $caja['usuario_nombre']; ?></title>
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f5f5f5;
            padding: 20px;
        }

        .ticket {
            width: 80mm;
            background: white;
            margin: 0 auto;
            padding: 5mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            margin: 2px 0;
        }

        .section {
            margin: 10px 0;
            padding: 5px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            text-align: center;
        }

        .row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin: 3px 0;
        }

        .row.total {
            font-weight: bold;
            font-size: 13px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            margin-top: 10px;
        }

        .row.destacado {
            background: #f0f0f0;
            padding: 3px;
            font-weight: bold;
        }

        .facturas-lista {
            font-size: 10px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin: 10px 0;
        }

        .factura-item {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            padding: 2px 0;
        }

        .factura-item.anulada {
            text-decoration: line-through;
            color: #999;
        }

        .factura-detalle {
            font-size: 9px;
            margin-left: 15px;
            color: #666;
        }

        .factura-descuento {
            font-size: 9px;
            margin-left: 15px;
            color: #666;
        }

        .descuento-global {
            color: #f44336;
        }

        .descuento-linea {
            color: #ff9800;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            border-top: 2px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
        }

        .btn-container {
            text-align: center;
            margin: 20px 0;
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .btn-print {
            background: #2196f3;
            color: white;
        }

        .btn-close {
            background: #e0e0e0;
            color: #333;
        }

        .destacado {
            background: #f0f0f0;
            padding: 5px;
            margin: 5px 0;
            text-align: center;
            font-weight: bold;
        }

        .info-descuentos {
            background: #fff3cd;
            padding: 5px;
            margin: 5px 0;
            border-left: 3px solid #ffc107;
        }

        .info-descuentos-detalle {
            background: #e8f5e9;
            padding: 5px;
            margin: 5px 0;
            border-left: 3px solid #4caf50;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <button onclick="window.print()" class="btn btn-print">🖨️ Imprimir</button>
        <button onclick="window.close()" class="btn btn-close">✕ Cerrar</button>
    </div>

    <div class="ticket">
        <!-- HEADER -->
        <div class="header">
            <h1><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h1>
            <p>RNC: <?php echo htmlspecialchars($config_empresa['rnc']); ?></p>
            <p><?php echo htmlspecialchars($config_empresa['direccion']); ?></p>
            <p>Tel: <?php echo htmlspecialchars($config_empresa['telefono']); ?></p>
        </div>

        <!-- TÍTULO -->
        <div class="destacado">
            CIERRE DE CAJA
        </div>

        <!-- INFORMACIÓN DEL TURNO -->
        <div class="section">
            <div class="row">
                <span>Cajero:</span>
                <span><?php echo htmlspecialchars($caja['usuario_nombre']); ?></span>
            </div>
            <div class="row">
                <span>Apertura:</span>
                <span><?php echo $fecha_apertura; ?></span>
            </div>
            <div class="row">
                <span>Cierre:</span>
                <span><?php echo $hora_actual; ?></span>
            </div>
        </div>

        <!-- RESUMEN DE VENTAS -->
        <div class="section">
            <div class="section-title">RESUMEN DE VENTAS</div>
            <div class="row">
                <span>Total Facturas:</span>
                <span><?php echo $conteo['total_facturas']; ?></span>
            </div>
            <div class="row">
                <span>Facturas Pagadas:</span>
                <span><?php echo $conteo['pagadas']; ?></span>
            </div>
            <div class="row">
                <span>Facturas Anuladas:</span>
                <span><?php echo $conteo['anuladas']; ?></span>
            </div>
        </div>

        <!-- INFORMACIÓN DETALLADA DE DESCUENTOS -->
        <?php if ($total_descuentos > 0): ?>
        <div class="section">
            <div class="section-title">DESCUENTOS APLICADOS</div>
            
            <div class="info-descuentos">
                <div class="row" style="font-weight: bold;">
                    <span>💰 TOTAL DESCUENTOS:</span>
                    <span>RD$ <?php echo number_format($total_descuentos, 2); ?></span>
                </div>
                
                <div class="info-descuentos-detalle">
                    <div class="row">
                        <span>🎯 Descuentos Globales:</span>
                        <span>RD$ <?php echo number_format($total_descuentos_global, 2); ?></span>
                    </div>
                    <div class="row">
                        <span style="font-size: 9px;">Facturas con desc. global:</span>
                        <span style="font-size: 9px;"><?php echo $facturas_con_descuento_global; ?></span>
                    </div>
                    
                    <div class="row" style="margin-top: 5px;">
                        <span>📦 Descuentos por Producto:</span>
                        <span>RD$ <?php echo number_format($total_descuentos_linea, 2); ?></span>
                    </div>
                    <div class="row">
                        <span style="font-size: 9px;">Facturas con desc. por línea:</span>
                        <span style="font-size: 9px;"><?php echo $facturas_con_descuento_linea; ?></span>
                    </div>
                    <?php if ($estadisticas_linea['lineas_con_descuento'] > 0): ?>
                    <div class="row">
                        <span style="font-size: 9px;">Líneas con descuento:</span>
                        <span style="font-size: 9px;"><?php echo $estadisticas_linea['lineas_con_descuento']; ?></span>
                    </div>
                    <div class="row">
                        <span style="font-size: 9px;">Promedio por línea:</span>
                        <span style="font-size: 9px;">RD$ <?php echo number_format($estadisticas_linea['promedio_descuento_linea'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DETALLE POR MÉTODO DE PAGO -->
        <div class="section">
            <div class="section-title">POR METODO DE PAGO</div>
            <?php if ($conteo['total_subtotal'] > 0): ?>
                <div class="row destacado">
                    <span>Subtotal Ventas:</span>
                    <span>RD$ <?php echo number_format($conteo['total_subtotal'], 2); ?></span>
                </div>
                <?php if ($total_descuentos > 0): ?>
                <div class="row" style="color: #f44336;">
                    <span>- Descuentos Totales:</span>
                    <span>RD$ <?php echo number_format($total_descuentos, 2); ?></span>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="row">
                <span>💵 Efectivo (Ventas):</span>
                <span>RD$ <?php echo number_format($caja['total_ventas_efectivo'], 2); ?></span>
            </div>
            <div class="row">
                <span>💰 Abonos Efectivo:</span>
                <span>RD$ <?php echo number_format($abonos_efectivo, 2); ?></span>
            </div>
            <div class="row">
                <span>🏦 Transferencia (Ventas):</span>
                <span>RD$ <?php echo number_format($caja['total_ventas_transferencia'], 2); ?></span>
            </div>
            <div class="row">
                <span>💳 Abonos Transf.:</span>
                <span>RD$ <?php echo number_format($abonos_transferencia, 2); ?></span>
            </div>
            <div class="row">
                <span>📝 Crédito (Ventas):</span>
                <span>RD$ <?php echo number_format($caja['total_ventas_credito'], 2); ?></span>
            </div>
            <div class="row total">
                <span>TOTAL VENTAS:</span>
                <span>RD$ <?php echo number_format($total_general, 2); ?></span>
            </div>
        </div>

        <!-- CONTROL DE EFECTIVO -->
        <div class="section">
            <div class="section-title">CONTROL DE EFECTIVO</div>
            <div class="row">
                <span>Monto Inicial:</span>
                <span>RD$ <?php echo number_format($caja['monto_inicial'], 2); ?></span>
            </div>
            <div class="row">
                <span>+ Ventas Efectivo:</span>
                <span>RD$ <?php echo number_format($caja['total_ventas_efectivo'], 2); ?></span>
            </div>
            <div class="row">
                <span>+ Abonos Efectivo:</span>
                <span>RD$ <?php echo number_format($abonos_efectivo, 2); ?></span>
            </div>
            <div class="row total">
                <span>= ESPERADO:</span>
                <span>RD$ <?php echo number_format($monto_esperado, 2); ?></span>
            </div>

            <!-- DETALLE DE ABONOS -->
            <?php if (!empty($lista_abonos)): ?>
            <div class="section">
                <div class="section-title">DETALLE DE ABONOS</div>
                <div class="facturas-lista">
                    <?php foreach ($lista_abonos as $ab): ?>
                        <div class="factura-item">
                            <span><?php echo substr($ab['cliente'], 0, 20); ?> (<?php echo strtoupper($ab['metodo_pago'][0]); ?>)</span>
                            <span>RD$ <?php echo number_format($ab['monto'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- LISTA DE FACTURAS -->
        <div class="section">
            <div class="section-title">DETALLE DE FACTURAS</div>
            <div class="facturas-lista">
                <?php 
                if (empty($facturas)) {
                    echo '<div style="text-align: center; padding: 10px;">No hay facturas en este turno</div>';
                } else {
                    $num = 1;
                    foreach ($facturas as $fact): 
                        $hora = $fact['fecha']->format('H:i');
                        $es_anulada = ($fact['estado'] === 'anulada');
                        $tiene_descuento_global = ($fact['descuento_global'] > 0);
                        $tiene_descuento_linea = ($fact['descuento_linea'] > 0);
                        $tiene_descuento = $tiene_descuento_global || $tiene_descuento_linea;
                ?>
                    <div class="factura-item <?php echo $es_anulada ? 'anulada' : ''; ?>">
                        <span><?php echo $num; ?>. <?php echo $hora; ?> - <?php echo $fact['numero_factura']; ?></span>
                        <span>RD$ <?php echo number_format($fact['total'], 2); ?></span>
                    </div>
                    <div class="factura-detalle">
                        <?php echo substr($fact['cliente_nombre'], 0, 25); ?> - <?php echo strtoupper($fact['tipo_pago']); ?>
                        <?php if ($es_anulada) echo ' [ANULADA]'; ?>
                    </div>
                    <?php if ($tiene_descuento && !$es_anulada): ?>
                    <div class="factura-descuento">
                        Subtotal: RD$ <?php echo number_format($fact['subtotal'], 2); ?>
                        
                        <?php if ($tiene_descuento_global): ?>
                        <span class="descuento-global">
                            | Desc. Global: -RD$ <?php echo number_format($fact['descuento_global'], 2); ?>
                        </span>
                        <?php endif; ?>
                        
                        <?php if ($tiene_descuento_linea): ?>
                        <span class="descuento-linea">
                            | Desc. Línea: -RD$ <?php echo number_format($fact['descuento_linea'], 2); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php 
                    $num++;
                    endforeach;
                }
                ?>
            </div>
        </div>

        <!-- RESUMEN FINANCIERO DETALLADO -->
        <?php if ($total_descuentos > 0): ?>
        <div class="section">
            <div class="section-title">RESUMEN FINANCIERO</div>
            <div class="row">
                <span>Ventas Brutas:</span>
                <span>RD$ <?php echo number_format($conteo['total_subtotal'], 2); ?></span>
            </div>
            
            <!-- Desglose de descuentos -->
            <?php if ($total_descuentos_global > 0): ?>
            <div class="row" style="color: #f44336; font-size: 10px;">
                <span>  - Descuentos Globales:</span>
                <span>RD$ <?php echo number_format($total_descuentos_global, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($total_descuentos_linea > 0): ?>
            <div class="row" style="color: #ff9800; font-size: 10px;">
                <span>  - Descuentos por Producto:</span>
                <span>RD$ <?php echo number_format($total_descuentos_linea, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="row total">
                <span>= Ventas Netas:</span>
                <span>RD$ <?php echo number_format($total_general, 2); ?></span>
            </div>
            
            <!-- Estadísticas adicionales -->
            <div class="row" style="font-size: 9px; margin-top: 5px;">
                <span>% Descuento/Ventas:</span>
                <span><?php echo number_format(($total_descuentos / $conteo['total_subtotal']) * 100, 2); ?>%</span>
            </div>
            
            <?php if ($facturas_con_descuento_global > 0): ?>
            <div class="row" style="font-size: 9px;">
                <span>Prom. desc. global/factura:</span>
                <span>RD$ <?php echo number_format($facturas_con_descuento_global > 0 ? $total_descuentos_global / $facturas_con_descuento_global : 0, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($estadisticas_linea['lineas_con_descuento'] > 0): ?>
            <div class="row" style="font-size: 9px;">
                <span>Prom. desc. línea/unidad:</span>
                <span>RD$ <?php echo number_format($estadisticas_linea['promedio_descuento_linea'], 2); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- FOOTER -->
        <div class="footer">
            <p>================================</p>
            <p>DOCUMENTO NO FISCAL</p>
            <p>Solo para control interno</p>
            <p>================================</p>
            <p style="margin-top: 10px;">Sistema de Facturación v1.0</p>
            <p><?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar (opcional)
        window.onload = function() {
         window.print();
        }
    </script>
</body>
</html>