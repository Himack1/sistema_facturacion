<?php
/**
 * NUEVA FACTURA
 * Interface para crear facturas de venta
 * Permite vender productos y servicios
 * Requiere caja abierta
 */

session_start();

// Validar sesión y caja abierta
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['caja_abierta']) || !$_SESSION['caja_abierta']) {
    header("Location: verificar_caja.php");
    exit();
}

require_once '../conexion.php';
require_once '../includes/config_empresa.php';
$config_empresa = obtenerConfigEmpresa($conn);

// ==================================================
// OBTENER CLIENTES ACTIVOS
// ==================================================
$sql_cli = "SELECT id, codigo, nombre, documento, limite_credito, 
            ISNULL(credito_consumido, 0) as credito_consumido,
            (limite_credito - ISNULL(credito_consumido, 0)) as credito_disponible
            FROM clientes 
            WHERE estado = 'activo' 
            ORDER BY nombre";
$stmt_cli = sqlsrv_query($conn, $sql_cli);

if ($stmt_cli === false) {
    die("Error al cargar clientes");
}

// Almacenar clientes en array para usar en JavaScript
$clientes = [];
while($cli = sqlsrv_fetch_array($stmt_cli, SQLSRV_FETCH_ASSOC)) {
    $clientes[] = $cli;
}

// ==================================================
// OBTENER PRODUCTOS Y SERVICIOS ACTIVOS
// ==================================================
$sql_prod = "SELECT 
                p.id, 
                p.codigo, 
                p.nombre, 
                p.tipo, 
                p.precio_venta, 
                p.impuesto,
                p.stock_actual, 
                p.aplica_inventario, 
                p.imagen,
                c.nombre as categoria
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             WHERE p.estado = 'activo'
             ORDER BY p.tipo DESC, p.nombre";
$stmt_prod = sqlsrv_query($conn, $sql_prod);

if ($stmt_prod === false) {
    die("Error al cargar productos");
}

// ==================================================
// OBTENER DATOS DE LA CAJA ACTUAL
// ==================================================
$sql_caja = "SELECT 
                fecha_apertura, 
                monto_inicial,
                total_ventas_efectivo,
                total_ventas_transferencia,
                total_ventas_credito
             FROM cajas 
             WHERE id = ? AND estado = 'abierta'";
$stmt_caja = sqlsrv_query($conn, $sql_caja, array($_SESSION['caja_id']));
$caja_info = sqlsrv_fetch_array($stmt_caja, SQLSRV_FETCH_ASSOC);

$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Factura - Sistema de Facturación</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f5f5; 
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { 
            font-size: 22px; 
        }
        .info-caja { 
            background: rgba(255,255,255,0.2); 
            padding: 8px 15px; 
            border-radius: 5px; 
            font-size: 13px; 
        }
        .btn-cerrar-caja { 
            background: #f44336; 
            color: white; 
            padding: 8px 15px; 
            border-radius: 5px; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-cerrar-caja:hover {
            background: #d32f2f;
        }
        .btn-dashboard {
            background: #4caf50;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-dashboard:hover {
            background: #45a049;
        }
        .alerta-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alerta-exito {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .container { 
            max-width: 1600px; 
            margin: 20px auto; 
            padding: 0 20px; 
        }
        .factura-grid { 
            display: grid; 
            grid-template-columns: 1fr 400px; 
            gap: 20px; 
        }
        .panel { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .panel-title { 
            font-size: 18px; 
            font-weight: 600; 
            color: #333; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid #e0e0e0; 
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 6px; 
            color: #666; 
            font-size: 13px; 
            font-weight: 600; 
        }
        .form-group select, 
        .form-group input { 
            width: 100%; 
            padding: 10px; 
            border: 2px solid #e0e0e0; 
            border-radius: 5px; 
            font-size: 14px; 
        }
        .form-group select:focus, 
        .form-group input:focus { 
            outline: none; 
            border-color: #667eea; 
        }
        .buscar-producto { 
            position: relative; 
            margin-bottom: 20px;
        }
        .buscar-producto input { 
            padding-right: 40px; 
        }
        .buscar-icon { 
            position: absolute; 
            right: 12px; 
            top: 35px; 
            color: #999; 
        }
        /* Scanner de códigos de barras */
        .scanner-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 14px;
            color: #a0aec0;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(26,26,46,0.3);
        }
        .scanner-box input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: white;
            padding: 8px 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .scanner-box input::placeholder { color: rgba(255,255,255,0.4); }
        .scanner-box input:focus { border-color: #667eea; background: rgba(255,255,255,0.15); }
        .scanner-box.scanner-hit { animation: scannerPulse 0.4s ease; }
        @keyframes scannerPulse {
            0% { background: linear-gradient(135deg, #1a1a2e, #16213e); }
            50% { background: linear-gradient(135deg, #10b981, #059669); }
            100% { background: linear-gradient(135deg, #1a1a2e, #16213e); }
        }
        .scanner-icon { font-size: 22px; }

        .productos-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 10px; 
            max-height: 400px; 
            overflow-y: auto;
            padding-right: 5px;
        }
        .productos-grid::-webkit-scrollbar {
            width: 8px;
        }
        .productos-grid::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        .producto-card { 
            padding: 15px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .producto-card:hover { 
            border-color: #667eea; 
            background: #f8f9ff; 
            transform: translateY(-2px);
        }
        .producto-card.sin-stock { 
            opacity: 0.5; 
            cursor: not-allowed; 
        }
        .producto-card.sin-stock:hover {
            transform: none;
        }
        .producto-card-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
            background: #f0f0f0;
        }
        .producto-nombre { 
            font-weight: 600; 
            font-size: 14px; 
            color: #333; 
            margin-bottom: 5px; 
        }
        .producto-precio { 
            color: #4caf50; 
            font-weight: 600; 
            font-size: 16px; 
        }
        .producto-stock { 
            font-size: 12px; 
            color: #666; 
            margin-top: 5px; 
        }
        .producto-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }
        .tipo-producto {
            background: #e3f2fd;
            color: #1976d2;
        }
        .tipo-servicio {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .carrito-item { 
            padding: 18px; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px;
            background: #ffffff;
            display: flex; 
            flex-direction: column;
            gap: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .carrito-item:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .item-info { 
            flex: 1;
            min-width: 0;
        }
        .item-nombre { 
            font-weight: 700; 
            color: #1e293b; 
            font-size: 15px;
            white-space: normal;
            line-height: 1.3;
        }
        .item-precio-unitario {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
        }
        .item-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
        }
        .item-cantidad { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1px dashed #e2e8f0;
            padding-top: 10px;
        }
        .item-precio { 
            color: #666; 
            font-size: 13px; 
        }
        .item-cantidad { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .btn-cant { 
            width: 30px; 
            height: 30px; 
            border: none; 
            background: #667eea; 
            color: white; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn-cant:hover {
            background: #5568d3;
        }
        .cant-valor { 
            width: 50px; 
            text-align: center; 
            font-weight: 600; 
}
        .item-total { 
            font-weight: 600; 
            color: #667eea; 
            font-size: 16px;
            min-width: 100px;
            text-align: right;
        }
        .btn-eliminar { 
            background: #f44336; 
            color: white; 
            border: none; 
            padding: 6px 10px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px;
            transition: background 0.3s;
        }
        .btn-eliminar:hover {
            background: #d32f2f;
        }
        .carrito-vacio { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999; 
        }
        .totales { 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 2px solid #e0e0e0; 
        }
        .total-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            font-size: 14px; 
        }
        .total-row.final { 
            font-size: 24px; 
            font-weight: 700; 
            color: #667eea; 
            padding-top: 15px; 
            border-top: 2px solid #e0e0e0; 
            margin-top: 10px; 
        }
        .pago-efectivo-section {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 2px solid #667eea;
            display: none;
        }
        .pago-efectivo-section.visible {
            display: block;
        }
        .cambio-display {
            background: #4caf50;
            color: white;
            padding: 12px;
            border-radius: 5px;
            margin-top: 10px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }
        .cambio-display.insuficiente {
            background: #f44336;
        }
        .btn-facturar { 
            width: 100%; 
            padding: 18px; 
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn-facturar:hover {
            transform: translateY(-2px);
        }
        .btn-facturar:disabled { 
            background: #ccc; 
            cursor: not-allowed;
            transform: none;
        }
        .btn-limpiar { 
            width: 100%; 
            padding: 12px; 
            background: #e0e0e0; 
            color: #333; 
            border: none; 
            border-radius: 5px; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-top: 10px;
            transition: background 0.3s;
        }
        .btn-limpiar:hover {
            background: #d0d0d0;
        }
        .alerta-credito {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }
        
        .toggle-descuento-linea {
            background: #f8f9ff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 2px solid #667eea;
        }
        
        .toggle-descuento-linea label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
        }
        
        .toggle-descuento-linea input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .descuento-linea-input {
            display: none;
            margin-top: 5px;
        }
        
        .descuento-linea-input.visible {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .descuento-linea-input input {
            width: 80px;
            padding: 5px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .descuento-linea-input select {
            padding: 5px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 11px;
        }
        
        @media (max-width: 1200px) { 
            .factura-grid { 
                grid-template-columns: 1fr; 
            }
        }

        .descuento-linea-input {
            display: flex;
            gap: 5px;
            margin-top: 5px;
            align-items: center;
        }
        
        .descuento-linea-input input {
            width: 85px;
            padding: 6px 10px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .descuento-linea-input select {
            padding: 6px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: #f1f5f9;
        }
        
        .descuento-linea-badge {
            display: inline-block;
            background: #ff9800;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-top: 3px;
        }

        .item-impuesto-tag {
            font-size: 10px;
            color: #764ba2;
            background: #f3e5f5;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-top: 3px;
        }
        
        /* Estilos para la búsqueda de clientes */
        .buscar-cliente-container {
            position: relative;
        }
        
        .sugerencias-clientes {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .sugerencia-cliente {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .sugerencia-cliente:hover {
            background: #f8f9ff;
        }
        
        .sugerencia-cliente:last-child {
            border-bottom: none;
        }
        
        .cliente-nombre {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        
        .cliente-documento {
            font-size: 12px;
            color: #666;
        }
        
        .cliente-codigo {
            font-size: 11px;
            color: #999;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .selected-cliente {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
            border-left: 4px solid #4caf50;
        }
        
        .selected-cliente h4 {
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .selected-cliente p {
            font-size: 12px;
            color: #666;
            margin: 3px 0;
        }
        
        .btn-clear-cliente {
            background: #f44336;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            cursor: pointer;
            margin-top: 5px;
        }
        
        .btn-clear-cliente:hover {
            background: #d32f2f;
        }
        
        .info-cliente-credito {
            background: #e3f2fd;
            padding: 8px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 12px;
            border-left: 3px solid #1976d2;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>🧾 Nueva Factura</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <div class="info-caja">
                💰 Caja Abierta • <?php echo htmlspecialchars($usuario_nombre); ?>
            </div>
            <a href="../dashboard.php" class="btn-dashboard">🏠 Dashboard</a>
            <a href="cerrar_caja.php" class="btn-cerrar-caja">Cerrar Caja</a>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alerta-error">
                ⚠️ <strong>Error:</strong> <?php echo htmlspecialchars($_SESSION['mensaje_error']); ?>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="alerta-exito">
                ✓ <?php echo htmlspecialchars($_SESSION['mensaje_exito']); ?>
            </div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>

        <div class="factura-grid">
            <!-- Panel Izquierdo: Productos y Servicios -->
            <div class="panel">
                <div class="panel-title">📦 Productos y Servicios</div>
                
                <!-- Scanner de Código de Barras -->
                <div class="scanner-box" id="scannerBox">
                    <div class="scanner-icon">📅</div>
                    <div class="scanner-text">
                        <div style="font-weight:700; color:#1a1a2e; margin-bottom:4px;">📷 Escanear Código de Barras</div>
                        <div>Pasa el scanner por el código del producto</div>
                    </div>
                    <input type="text" id="scannerInput" autocomplete="off"
                           placeholder="Escanea aquí..." style="flex:1; min-width:120px;">
                </div>

                <div class="buscar-producto">
                    <label>Buscar por nombre</label>
                    <input 
                        type="text" 
                        id="buscarProducto" 
                        placeholder="Buscar por nombre o código...">
                    <span class="buscar-icon">🔍</span>
                </div>

                <div class="productos-grid" id="productosGrid">
                    <?php while($prod = sqlsrv_fetch_array($stmt_prod, SQLSRV_FETCH_ASSOC)): 
                        $sin_stock = $prod['aplica_inventario'] && $prod['stock_actual'] <= 0;
                        $es_servicio = $prod['tipo'] === 'servicio';
                    ?>
                    <div class="producto-card <?php echo $sin_stock ? 'sin-stock' : ''; ?>" 
                         data-id="<?php echo $prod['id']; ?>"
                         data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                         data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                         data-precio="<?php echo $prod['precio_venta']; ?>"
                         data-tipo="<?php echo $prod['tipo']; ?>"
                         data-impuesto="<?php echo $prod['impuesto'] ?? ''; ?>"
                         data-stock="<?php echo $prod['stock_actual']; ?>"
                         data-aplica="<?php echo $prod['aplica_inventario']; ?>"
                         <?php if (!$sin_stock): ?>
                         onclick="agregarAlCarrito(this)"
                         <?php endif; ?>>
                        
                        <?php if(!empty($prod['imagen'])): ?>
                            <img src="<?php echo strpos($prod['imagen'], 'http') === 0 ? htmlspecialchars($prod['imagen']) : '../' . htmlspecialchars($prod['imagen']); ?>" class="producto-card-img" alt="Imagen">
                        <?php endif; ?>

                        <div class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                        <div class="producto-precio">RD$ <?php echo number_format($prod['precio_venta'], 2); ?></div>
                        
                        <?php if ($es_servicio): ?>
                            <div class="producto-tipo tipo-servicio">🛠️ Servicio</div>
                        <?php else: ?>
                            <div class="producto-tipo tipo-producto">📦 Producto</div>
                            <div class="producto-stock">
                                Stock: <?php echo $prod['stock_actual']; ?>
                                <?php echo $sin_stock ? ' ⚠️ Agotado' : ''; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Panel Derecho: Carrito y Totales -->
            <div class="panel">
                <div class="panel-title">🛒 Carrito de Venta</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cliente *</label>
                        <div class="buscar-cliente-container">
                            <input 
                                type="text" 
                                id="buscarCliente" 
                                placeholder="Escribe nombre, código o documento del cliente..."
                                autocomplete="off">
                            <div class="sugerencias-clientes" id="sugerenciasClientes"></div>
                        </div>
                        <input type="hidden" id="cliente_id" name="cliente_id">
                        <div id="selectedClienteContainer" style="display: none;">
                            <div class="selected-cliente">
                                <h4>👤 Cliente seleccionado</h4>
                                <p><strong>Nombre:</strong> <span id="clienteNombre"></span></p>
                                <p><strong>Documento:</strong> <span id="clienteDocumento"></span></p>
                                <p><strong>Código:</strong> <span id="clienteCodigo"></span></p>
                                <div id="clienteCreditoInfo" class="info-cliente-credito" style="display: none;">
                                    <strong>📊 Información de crédito:</strong>
                                    <p>Límite: RD$ <span id="clienteLimiteCredito"></span></p>
                                    <p>Disponible: RD$ <span id="clienteCreditoDisponible"></span></p>
                                </div>
                                <button type="button" class="btn-clear-cliente" onclick="limpiarCliente()">Cambiar cliente</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Pago *</label>
                        <select id="tipo_pago" required>
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="transferencia">🏦 Transferencia</option>
                            <option value="credito">📝 Crédito</option>
                        </select>
                    </div>
                </div>

                <!-- Sección de Pago en Efectivo -->
                <div class="pago-efectivo-section" id="pagoEfectivoSection">
                    <div class="form-group">
                        <label>💵 Efectivo Recibido</label>
                        <input 
                            type="number" 
                            id="efectivo_recibido" 
                            placeholder="0.00" 
                            step="0.01"
                            min="0">
                    </div>
                    <div class="cambio-display" id="cambioDisplay">
                        Cambio: RD$ 0.00
                    </div>
                </div>

                <!-- Sección de Venta a Plazos -->
                <div class="pago-plazos-section" id="pagoPlazosSection" style="display: none; background: #fff3e0; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #ff9800;">
                    <div style="font-weight: bold; color: #e65100; margin-bottom: 10px;">📊 Opciones de Financiamiento</div>
                    
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: bold;">
                            <input type="checkbox" id="es_a_plazos" style="width: 18px; height: 18px; cursor: pointer;"> 
                            Aplicar Venta a Plazos
                        </label>
                    </div>

                    <div id="opcionesPlazos" style="display: none;">
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group">
                                <label>Frecuencia</label>
                                <select id="frecuencia_plazos" style="width: 100%; padding: 8px; border: 2px solid #cbd5e1; border-radius: 6px;">
                                    <option value="semanal">Semanal</option>
                                    <option value="quincenal">Quincenal</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cant. Cuotas</label>
                                <input type="number" id="cantidad_cuotas" min="1" placeholder="Ej. 6" style="width: 100%; padding: 8px; border: 2px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>Inicial (RD$)</label>
                                <input type="number" id="monto_inicial" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px; border: 2px solid #cbd5e1; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label>Interés (%)</label>
                                <input type="number" id="interes_porcentaje" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px; border: 2px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>
                        <div id="simuladorPlazos" style="margin-top: 15px; font-size: 13px; color: #333; background: #fff; padding: 12px; border-radius: 6px; border: 1px dashed #ccc; text-align: center;">
                            <em>Llena los datos arriba para simular las cuotas.</em>
                        </div>
                    </div>
                </div>

                <div class="alerta-credito" id="alertaCredito"></div>

                <!-- Toggle para activar descuentos por línea -->
                <div class="toggle-descuento-linea">
                    <label>
                        <input type="checkbox" id="toggleDescuentoLinea">
                        💰 Aplicar descuento por producto
                    </label>
                </div>

                <div id="carritoItems" style="max-height: 350px; overflow-y: auto;">
                    <div class="carrito-vacio" id="carritoVacio">
                        <div style="font-size: 60px; margin-bottom: 15px;">🛒</div>
                        <p>Selecciona productos o servicios para agregar</p>
                    </div>
                </div>

                <div class="totales" id="totalesSection" style="display: none;">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">RD$ 0.00</span>
                    </div>
                    
                    <!-- Descuentos por línea -->
                    <div class="total-row" id="descuentoLineaAplicado" style="display: none; color: #ff9800;">
                        <span>Descuentos por línea:</span>
                        <span id="descuentoLineaValor">- RD$ 0.00</span>
                    </div>
                    
                    <!-- Campo de Descuento Global -->
                    <div class="total-row" style="align-items: center; gap: 10px;">
                        <span>Descuento Global:</span>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input 
                                type="number" 
                                id="descuento" 
                                placeholder="0.00" 
                                step="0.01"
                                min="0"
                                style="width: 100px; padding: 5px; border: 2px solid #e0e0e0; border-radius: 4px; font-size: 14px;">
                            <select id="tipo_descuento" style="padding: 5px; border: 2px solid #e0e0e0; border-radius: 4px; font-size: 12px;">
                                <option value="monto">RD$</option>
                                <option value="porcentaje">%</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="total-row" id="descuentoAplicado" style="display: none; color: #f44336;">
                        <span>Descuento Global Aplicado:</span>
                        <span id="descuentoValor">- RD$ 0.00</span>
                    </div>
                    
                    <?php if (!empty($config_empresa['aplicar_impuestos'])): ?>
                    <div class="total-row" id="impuestoAplicado" style="color: #1976d2;">
                        <span>ITBIS:</span>
                        <span id="impuestoValor">RD$ 0.00</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row final">
                        <span>TOTAL:</span>
                        <span id="total">RD$ 0.00</span>
                    </div>
                </div>

                <!-- Sección de Notas -->
                <div class="form-group" style="margin-top: 20px;">
                    <label>📝 Notas / Observaciones</label>
                    <textarea 
                        id="notas" 
                        placeholder="Escribe alguna nota o comentario para esta factura..." 
                        rows="3"
                        style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 13px; resize: vertical;"></textarea>
                </div>


                <button class="btn-facturar" id="btnFacturar" onclick="procesarVenta()" disabled>
                    💳 Procesar Venta
                </button>
                <button class="btn-limpiar" onclick="limpiarCarrito()">🗑️ Limpiar Todo</button>
            </div>
        </div>
    </div>

    <script>
        // Array de clientes desde PHP
        const clientes = <?php echo json_encode($clientes); ?>;
        const config_empresa = <?php echo json_encode($config_empresa); ?>;
        const aplicarImpuestos = config_empresa.aplicar_impuestos == 1;
        const itbisPorcentajeGlobal = parseFloat(config_empresa.itbis_porcentaje) || 18;
        
        let carrito = [];
        let totalVenta = 0;
        let subtotalVenta = 0;
        let totalImpuestoVenta = 0;
        let descuentoAplicado = 0;
        let descuentoPorLineaActivo = false;
        let clienteSeleccionado = null;
        let timeoutBusqueda = null;

        // ===================================
        // BÚSQUEDA DE CLIENTES
        // ===================================
        document.getElementById('buscarCliente').addEventListener('input', function() {
            const termino = this.value.trim().toLowerCase();
            const sugerencias = document.getElementById('sugerenciasClientes');
            
            if (timeoutBusqueda) {
                clearTimeout(timeoutBusqueda);
            }
            
            // Debounce para evitar búsquedas excesivas
            timeoutBusqueda = setTimeout(() => {
                if (termino.length < 2) {
                    sugerencias.style.display = 'none';
                    return;
                }
                
                const resultados = clientes.filter(cliente => 
                    cliente.nombre.toLowerCase().includes(termino) ||
                    (cliente.documento && cliente.documento.toLowerCase().includes(termino)) ||
                    (cliente.codigo && cliente.codigo.toLowerCase().includes(termino))
                );
                
                if (resultados.length === 0) {
                    sugerencias.innerHTML = '<div class="sugerencia-cliente">No se encontraron clientes</div>';
                    sugerencias.style.display = 'block';
                    return;
                }
                
                let html = '';
                resultados.forEach(cliente => {
                    html += `
                        <div class="sugerencia-cliente" onclick="seleccionarCliente(${JSON.stringify(cliente).replace(/"/g, '&quot;')})">
                            <div>
                                <div class="cliente-nombre">${cliente.nombre}</div>
                                <div class="cliente-documento">${cliente.documento || 'Sin documento'}</div>
                            </div>
                            <div class="cliente-codigo">${cliente.codigo}</div>
                        </div>
                    `;
                });
                
                sugerencias.innerHTML = html;
                sugerencias.style.display = 'block';
            }, 300);
        });

        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.buscar-cliente-container')) {
                document.getElementById('sugerenciasClientes').style.display = 'none';
            }
        });

        // ===================================
        // SELECCIONAR CLIENTE
        // ===================================
        function seleccionarCliente(cliente) {
            clienteSeleccionado = cliente;
            
            document.getElementById('cliente_id').value = cliente.id;
            document.getElementById('clienteNombre').textContent = cliente.nombre;
            document.getElementById('clienteDocumento').textContent = cliente.documento || 'N/A';
            document.getElementById('clienteCodigo').textContent = cliente.codigo;
            
            // Mostrar información de crédito
            const creditoInfo = document.getElementById('clienteCreditoInfo');
            if (cliente.limite_credito > 0) {
                document.getElementById('clienteLimiteCredito').textContent = parseFloat(cliente.limite_credito).toFixed(2);
                document.getElementById('clienteCreditoDisponible').textContent = parseFloat(cliente.credito_disponible).toFixed(2);
                creditoInfo.style.display = 'block';
            } else {
                creditoInfo.style.display = 'none';
            }
            
            // Ocultar campo de búsqueda y mostrar cliente seleccionado
            document.getElementById('buscarCliente').style.display = 'none';
            document.getElementById('selectedClienteContainer').style.display = 'block';
            document.getElementById('sugerenciasClientes').style.display = 'none';
            
            // Validar límite de crédito si es pago a crédito
            validarLimiteCredito();
            
            // Habilitar botón de facturar si hay productos
            if (carrito.length > 0) {
                document.getElementById('btnFacturar').disabled = false;
            }
        }

        // ===================================
        // LIMPIAR CLIENTE SELECCIONADO
        // ===================================
        function limpiarCliente() {
            clienteSeleccionado = null;
            document.getElementById('cliente_id').value = '';
            document.getElementById('buscarCliente').value = '';
            document.getElementById('buscarCliente').style.display = 'block';
            document.getElementById('selectedClienteContainer').style.display = 'none';
            document.getElementById('alertaCredito').style.display = 'none';
            document.getElementById('btnFacturar').disabled = true;
        }

        // ===================================
        // AGREGAR PRODUCTO/SERVICIO AL CARRITO
        // ===================================
        function agregarAlCarrito(elemento) {
            const id = elemento.dataset.id;
            const existe = carrito.find(item => item.id === id);
            
            if (existe) {
                const stockDisponible = parseInt(elemento.dataset.stock);
                const aplicaInventario = elemento.dataset.aplica === '1';
                
                if (aplicaInventario && existe.cantidad >= stockDisponible) {
                    alert('⚠️ Stock insuficiente para agregar más unidades');
                    return;
                }
                existe.cantidad++;
            } else {
                carrito.push({
                    id: id,
                    nombre: elemento.dataset.nombre,
                    codigo: elemento.dataset.codigo,
                    precio: parseFloat(elemento.dataset.precio),
                    tipo: elemento.dataset.tipo,
                    cantidad: 1,
                    stock: parseInt(elemento.dataset.stock),
                    aplica: elemento.dataset.aplica === '1',
                    descuento_linea: 0.00,
                    descuento_linea_valor_usuario: 0,
                    tipo_descuento_linea: 'monto',
                    impuesto_porcentaje: (elemento.dataset.impuesto !== '') ? parseFloat(elemento.dataset.impuesto) : itbisPorcentajeGlobal
                });
            }
            
            actualizarCarrito();
        }

        // ===================================
        // CAMBIAR CANTIDAD
        // ===================================
        function cambiarCantidad(id, cambio) {
            const item = carrito.find(i => i.id === id);
            if (!item) return;
            
            const nuevaCantidad = item.cantidad + cambio;
            
            if (nuevaCantidad <= 0) {
                eliminarItem(id);
                return;
            }
            
            if (item.aplica && nuevaCantidad > item.stock) {
                alert('⚠️ Stock insuficiente');
                return;
            }
            
            item.cantidad = nuevaCantidad;
            
            // Recalcular descuento por línea si es tipo porcentaje
            if (item.tipo_descuento_linea === 'porcentaje') {
                const subtotalLinea = item.precio * item.cantidad;
                item.descuento_linea = (subtotalLinea * item.descuento_linea_valor_usuario) / 100;
            }
            
            actualizarCarrito();
        }

        // ===================================
        // APLICAR DESCUENTO POR LÍNEA
        // ===================================
        function aplicarDescuentoLinea(id, valor, tipo) {
            const item = carrito.find(i => i.id === id);
            if (!item) return;
            
            const subtotalLinea = item.precio * item.cantidad;
            let descuento = 0;
            
            if (tipo === 'porcentaje') {
                if (valor > 100) {
                    alert('⚠️ El descuento no puede ser mayor al 100%');
                    return;
                }
                descuento = (subtotalLinea * valor) / 100;
                item.descuento_linea_valor_usuario = valor;
                item.tipo_descuento_linea = 'porcentaje';
            } else {
                if (valor > subtotalLinea) {
                    alert('⚠️ El descuento no puede ser mayor al subtotal de la línea');
                    return;
                }
                descuento = valor;
                item.descuento_linea_valor_usuario = valor;
                item.tipo_descuento_linea = 'monto';
            }
            
            if (descuento < 0) {
                alert('⚠️ El descuento no puede ser negativo');
                return;
            }
            
            item.descuento_linea = descuento;
            actualizarCarrito();
        }

        // ===================================
        // ELIMINAR ITEM
        // ===================================
        function eliminarItem(id) {
            carrito = carrito.filter(item => item.id !== id);
            actualizarCarrito();
        }

        // ===================================
        // CALCULAR DESCUENTO GLOBAL
        // ===================================
        function calcularDescuento() {
            const descuentoInput = parseFloat(document.getElementById('descuento').value) || 0;
            const tipoDescuento = document.getElementById('tipo_descuento').value;
            
            if (descuentoInput <= 0) {
                descuentoAplicado = 0;
                return 0;
            }
            
            const subtotalConDescuentosLinea = carrito.reduce((sum, item) => {
                const subtotalLinea = item.precio * item.cantidad;
                const descLinea = item.descuento_linea || 0;
                return sum + (subtotalLinea - descLinea);
            }, 0);
            
            if (tipoDescuento === 'porcentaje') {
                if (descuentoInput > 100) {
                    alert('⚠️ El descuento no puede ser mayor al 100%');
                    document.getElementById('descuento').value = '100';
                    descuentoAplicado = subtotalConDescuentosLinea;
                    return subtotalConDescuentosLinea;
                }
                descuentoAplicado = (subtotalConDescuentosLinea * descuentoInput) / 100;
            } else {
                if (descuentoInput > subtotalConDescuentosLinea) {
                    alert('⚠️ El descuento no puede ser mayor al subtotal');
                    document.getElementById('descuento').value = subtotalConDescuentosLinea.toFixed(2);
                    descuentoAplicado = subtotalConDescuentosLinea;
                    return subtotalConDescuentosLinea;
                }
                descuentoAplicado = descuentoInput;
            }
            
            return descuentoAplicado;
        }

        // ===================================
        // ACTUALIZAR VISUALIZACIÓN DEL CARRITO
        // ===================================
        function actualizarCarrito() {
            const container = document.getElementById('carritoItems');
            const totalesSection = document.getElementById('totalesSection');
            const btnFacturar = document.getElementById('btnFacturar');
            
            // Guardar el ID del elemento que tiene el foco actualmente
            const activeElementId = document.activeElement ? document.activeElement.id : null;
            
            if (carrito.length === 0) {
                container.innerHTML = `
                    <div class="carrito-vacio">
                        <div style="font-size: 60px; margin-bottom: 15px;">🛒</div>
                        <p>Selecciona productos o servicios para agregar</p>
                    </div>
                `;
                totalesSection.style.display = 'none';
                btnFacturar.disabled = true;
                subtotalVenta = 0;
                totalVenta = 0;
                descuentoAplicado = 0;
                calcularCambio();
                return;
            }
            
            totalesSection.style.display = 'block';
            
            // Solo habilitar botón si hay cliente seleccionado
            btnFacturar.disabled = !clienteSeleccionado;
            
            let html = '';
            let subtotal = 0;
            let totalDescuentosLinea = 0;
            totalImpuestoVenta = 0;
            
            carrito.forEach(item => {
                const subtotalLinea = item.precio * item.cantidad;
                const descuentoLinea = item.descuento_linea || 0;
                const totalLinea = subtotalLinea - descuentoLinea;
                
                subtotal += subtotalLinea;
                totalDescuentosLinea += descuentoLinea;
                
                // Impuesto por línea (se calcula sobre subtotalLinea - descuentoLinea)
                if (aplicarImpuestos && item.impuesto_porcentaje > 0) {
                    const baseImponible = totalLinea;
                    item.impuesto_linea = (baseImponible * item.impuesto_porcentaje) / 100;
                    totalImpuestoVenta += item.impuesto_linea;
                } else {
                    item.impuesto_linea = 0;
                }
                
                html += `
                    <div class="carrito-item">
                        <!-- Cabecera: Nombre y Eliminar -->
                        <div class="item-header">
                            <div class="item-info">
                                <div class="item-nombre">${item.nombre}</div>
                                <div class="item-precio-unitario">RD$ ${item.precio.toFixed(2)} c/u</div>
                                ${aplicarImpuestos ? `<div class="item-impuesto-tag">ITBIS: ${item.impuesto_porcentaje}%</div>` : ''}
                            </div>
                            <button class="btn-eliminar" onclick="eliminarItem('${item.id}')" title="Eliminar">✕</button>
                        </div>

                        <!-- Cuerpo: Controles de Cantidad -->
                        <div class="item-body">
                            <div class="item-cantidad">
                                <button class="btn-cant" onclick="cambiarCantidad('${item.id}', -1)">-</button>
                                <span class="cant-valor">${item.cantidad}</span>
                                <button class="btn-cant" onclick="cambiarCantidad('${item.id}', 1)">+</button>
                            </div>
                            <div class="item-total" style="color: #1e293b; font-size: 15px;">
                                <span style="font-size: 11px; color: #64748b; display: block; text-align: right;">Subtotal</span>
                                RD$ ${subtotalLinea.toFixed(2)}
                            </div>
                        </div>

                        <!-- Footer: Descuento y Total de Línea -->
                        ${descuentoPorLineaActivo || descuentoLinea > 0 ? `
                        <div class="item-footer">
                            <div style="flex: 1;">
                                ${descuentoPorLineaActivo ? `
                                    <div class="descuento-linea-input">
                                        <input 
                                            type="number" 
                                            id="desc-input-${item.id}"
                                            placeholder="Desc." 
                                            step="0.01"
                                            min="0"
                                            value="${item.descuento_linea_valor_usuario > 0 ? item.descuento_linea_valor_usuario : ''}"
                                            oninput="aplicarDescuentoLinea('${item.id}', parseFloat(this.value) || 0, this.nextElementSibling.value)">
                                        <select id="desc-select-${item.id}" onchange="aplicarDescuentoLinea('${item.id}', parseFloat(this.previousElementSibling.value) || 0, this.value)">
                                            <option value="monto" ${item.tipo_descuento_linea === 'monto' ? 'selected' : ''}>RD$</option>
                                            <option value="porcentaje" ${item.tipo_descuento_linea === 'porcentaje' ? 'selected' : ''}>%</option>
                                        </select>
                                    </div>
                                ` : ''}
                                ${descuentoLinea > 0 ? `<div class="descuento-linea-badge" style="margin-top: 5px;">Ahorro: -RD$ ${descuentoLinea.toFixed(2)}</div>` : ''}
                            </div>
                            <div class="item-total" style="color: #4f46e5; font-size: 18px; font-weight: 800;">
                                <span style="font-size: 11px; color: #64748b; display: block; text-align: right;">Total Item</span>
                                RD$ ${totalLinea.toFixed(2)}
                            </div>
                        </div>
                        ` : `
                        <div class="item-footer" style="justify-content: flex-end;">
                            <div class="item-total" style="color: #4f46e5; font-size: 18px; font-weight: 800;">
                                RD$ ${totalLinea.toFixed(2)}
                            </div>
                        </div>
                        `}
                    </div>
                `;
            });
            
            container.innerHTML = html;
            subtotalVenta = subtotal;
            
            // Calcular descuento global
            const descuentoGlobal = calcularDescuento();
            
            // Calcular subtotal después de descuentos por línea
            const subtotalConDescuentosLinea = subtotal - totalDescuentosLinea;
            
            // Mostrar/ocultar fila de descuentos por línea
            const descuentoLineaRow = document.getElementById('descuentoLineaAplicado');
            if (totalDescuentosLinea > 0) {
                if (descuentoLineaRow) {
                    descuentoLineaRow.style.display = 'flex';
                    document.getElementById('descuentoLineaValor').textContent = '- RD$ ' + totalDescuentosLinea.toFixed(2);
                }
            } else {
                if (descuentoLineaRow) descuentoLineaRow.style.display = 'none';
            }
            
            // Mostrar/ocultar fila de descuento global
            const descuentoGlobalRow = document.getElementById('descuentoAplicado');
            if (descuentoGlobal > 0) {
                descuentoGlobalRow.style.display = 'flex';
                document.getElementById('descuentoValor').textContent = '- RD$ ' + descuentoGlobal.toFixed(2);
            } else {
                descuentoGlobalRow.style.display = 'none';
            }
            
            // Ajustar el impuesto total si hay un descuento global (proporcionalmente)
            if (descuentoGlobal > 0 && subtotalConDescuentosLinea > 0) {
                const proporcionDescuento = descuentoGlobal / subtotalConDescuentosLinea;
                totalImpuestoVenta = totalImpuestoVenta * (1 - proporcionDescuento);
            }
            
            // Mostrar/ocultar fila de impuesto
            if (aplicarImpuestos) {
                const impuestoRow = document.getElementById('impuestoAplicado');
                if (impuestoRow) {
                    impuestoRow.style.display = 'flex';
                    document.getElementById('impuestoValor').textContent = 'RD$ ' + totalImpuestoVenta.toFixed(2);
                }
            }
            
            // Calcular total final
            totalVenta = subtotalConDescuentosLinea - descuentoGlobal + totalImpuestoVenta;
            
            // Validar que el total no sea negativo
            if (totalVenta < 0) {
                totalVenta = 0;
                alert('⚠️ El total de la factura no puede ser negativo');
            }
            
            document.getElementById('subtotal').textContent = 'RD$ ' + subtotal.toFixed(2);
            document.getElementById('total').textContent = 'RD$ ' + totalVenta.toFixed(2);
            
            validarLimiteCredito();
            calcularCambio();
            
            // Actualizar simulador si está abierto
            if (document.getElementById('es_a_plazos') && document.getElementById('es_a_plazos').checked) {
                simularPlazos();
            }

            // Restaurar el foco si existía un elemento activo (para permitir escritura fluida)
            if (activeElementId) {
                const el = document.getElementById(activeElementId);
                if (el) {
                    el.focus();
                    // Forzar el cursor al final del texto
                    if (el.tagName === 'INPUT') {
                        const val = el.value;
                        el.value = '';
                        el.value = val;
                    }
                }
            }
        }

        // ===================================
        // LIMPIAR CARRITO
        // ===================================
        function limpiarCarrito() {
            if (carrito.length > 0 && !confirm('¿Deseas limpiar todo el carrito?')) {
                return;
            }
            carrito = [];
            document.getElementById('efectivo_recibido').value = '';
            document.getElementById('descuento').value = '';
            document.getElementById('toggleDescuentoLinea').checked = false;
            descuentoPorLineaActivo = false;
            document.getElementById('notas').value = '';
            actualizarCarrito();
        }

        // ===================================
        // VALIDAR LÍMITE DE CRÉDITO
        // ===================================
        function validarLimiteCredito() {
            const tipoPagoSelect = document.getElementById('tipo_pago');
            const tipo_pago = tipoPagoSelect.value;
            const alertaCredito = document.getElementById('alertaCredito');
            
            if (tipo_pago !== 'credito' || !clienteSeleccionado) {
                alertaCredito.style.display = 'none';
                return true;
            }
            
            const limiteTotal = parseFloat(clienteSeleccionado.limite_credito);
            const creditoDisponible = parseFloat(clienteSeleccionado.credito_disponible);
            const creditoConsumido = parseFloat(clienteSeleccionado.credito_consumido);
            
            if (totalVenta > creditoDisponible) {
                alertaCredito.innerHTML = `
                    ⚠️ <strong>Crédito insuficiente</strong><br>
                    Límite total: RD$ ${limiteTotal.toFixed(2)}<br>
                    Ya consumido: RD$ ${creditoConsumido.toFixed(2)}<br>
                    <strong>Disponible: RD$ ${creditoDisponible.toFixed(2)}</strong><br>
                    Requerido: RD$ ${totalVenta.toFixed(2)}
                `;
                alertaCredito.style.display = 'block';
                return false;
            } else {
                if (creditoConsumido > 0) {
                    alertaCredito.innerHTML = `
                        ℹ️ <strong>Crédito disponible:</strong> RD$ ${creditoDisponible.toFixed(2)} de RD$ ${limiteTotal.toFixed(2)}
                    `;
                    alertaCredito.style.display = 'block';
                    alertaCredito.style.background = '#e3f2fd';
                    alertaCredito.style.color = '#1976d2';
                } else {
                    alertaCredito.style.display = 'none';
                }
                return true;
            }
        }

        // ===================================
        // CALCULAR CAMBIO (EFECTIVO)
        // ===================================
        function calcularCambio() {
            const tipoPagoSelect = document.getElementById('tipo_pago');
            const tipo_pago = tipoPagoSelect.value;
            const pagoSection = document.getElementById('pagoEfectivoSection');
            const cambioDisplay = document.getElementById('cambioDisplay');
            const efectivoRecibido = parseFloat(document.getElementById('efectivo_recibido').value) || 0;
            
            if (tipo_pago === 'efectivo') {
                pagoSection.classList.add('visible');
                
                if (efectivoRecibido > 0) {
                    const cambio = efectivoRecibido - totalVenta;
                    
                    if (cambio >= 0) {
                        cambioDisplay.textContent = `Cambio: RD$ ${cambio.toFixed(2)}`;
                        cambioDisplay.className = 'cambio-display';
                    } else {
                        cambioDisplay.textContent = `⚠️ Falta: RD$ ${Math.abs(cambio).toFixed(2)}`;
                        cambioDisplay.className = 'cambio-display insuficiente';
                    }
                } else {
                    cambioDisplay.textContent = 'Cambio: RD$ 0.00';
                    cambioDisplay.className = 'cambio-display';
                }
            } else {
                pagoSection.classList.remove('visible');
            }
        }

        // ===================================
        // PROCESAR VENTA
        // ===================================
        function procesarVenta() {
            // Obtener tipo de pago actual
            const tipoPagoSelect = document.getElementById('tipo_pago');
            const tipo_pago = tipoPagoSelect.value;
            
            if (!clienteSeleccionado) {
                alert('⚠️ Debes seleccionar un cliente');
                return;
            }
            
            if (carrito.length === 0) {
                alert('⚠️ Debes agregar al menos un producto o servicio');
                return;
            }
            
            // Validar límite de crédito
            if (tipo_pago === 'credito' && !validarLimiteCredito()) {
                alert('⚠️ El total excede el límite de crédito del cliente');
                return;
            }
            
            // Validar efectivo recibido si es pago en efectivo
            let efectivo_recibido = 0;
            if (tipo_pago === 'efectivo') {
                const efectivoInput = document.getElementById('efectivo_recibido');
                efectivo_recibido = parseFloat(efectivoInput.value) || 0;
                
                if (efectivo_recibido <= 0) {
                    alert('⚠️ Debes ingresar el efectivo recibido');
                    efectivoInput.focus();
                    return;
                }
                
                if (efectivo_recibido < totalVenta) {
                    alert('⚠️ El efectivo recibido es insuficiente');
                    efectivoInput.focus();
                    return;
                }
            }
            
            // Validar que el total final sea positivo
            if (totalVenta <= 0) {
                alert('⚠️ El total de la venta debe ser mayor a cero');
                return;
            }
            
            // Preparar variables para plazos
            let es_a_plazos = 0;
            let frecuencia_plazos = '';
            let cantidad_cuotas = 0;
            let monto_inicial = 0;
            let interes_porcentaje = 0;

            if (tipo_pago === 'credito' && document.getElementById('es_a_plazos').checked) {
                es_a_plazos = 1;
                frecuencia_plazos = document.getElementById('frecuencia_plazos').value;
                cantidad_cuotas = parseInt(document.getElementById('cantidad_cuotas').value) || 0;
                monto_inicial = parseFloat(document.getElementById('monto_inicial').value) || 0;
                interes_porcentaje = parseFloat(document.getElementById('interes_porcentaje').value) || 0;

                if (cantidad_cuotas <= 0) {
                    alert('⚠️ Debes ingresar una cantidad válida de cuotas.');
                    return;
                }
                if (monto_inicial >= totalVenta) {
                    alert('⚠️ El inicial no puede ser mayor o igual al total de la factura.');
                    return;
                }
            }

            // Confirmar venta
            if (!confirm(`¿Confirmar venta por RD$ ${totalVenta.toFixed(2)}?\nCliente: ${clienteSeleccionado.nombre}\nTipo de pago: ${tipo_pago.toUpperCase()}`)) {
                return;
            }
            
            // Preparar carrito con descuentos por línea e impuestos
            const carritoProcesado = carrito.map(item => ({
                ...item,
                descuento_linea: item.descuento_linea || 0,
                impuesto_linea: item.impuesto_linea || 0,
                impuesto_porcentaje: item.impuesto_porcentaje || 0
            }));
            
            // Enviar formulario
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'procesar_factura.php';
            
            form.innerHTML = `
                <input type="hidden" name="cliente_id" value="${clienteSeleccionado.id}">
                <input type="hidden" name="tipo_pago" value="${tipo_pago}">
                <input type="hidden" name="efectivo_recibido" value="${efectivo_recibido}">
                <input type="hidden" name="descuento_global" value="${descuentoAplicado}">
                <input type="hidden" name="total_impuesto" value="${totalImpuestoVenta}">
                <input type="hidden" name="notas" value="${document.getElementById('notas').value}">
                
                <!-- Datos de Plazos -->
                <input type="hidden" name="es_a_plazos" value="${es_a_plazos}">
                <input type="hidden" name="frecuencia_plazos" value="${frecuencia_plazos}">
                <input type="hidden" name="cantidad_cuotas" value="${cantidad_cuotas}">
                <input type="hidden" name="monto_inicial" value="${monto_inicial}">
                <input type="hidden" name="interes_porcentaje" value="${interes_porcentaje}">

                <input type="hidden" name="carrito" value='${JSON.stringify(carritoProcesado)}'>
            `;
            
            document.body.appendChild(form);
            form.submit();
        }

        // ===================================
        // BÚSQUEDA DE PRODUCTOS/SERVICIOS
        // ===================================
        document.getElementById('buscarProducto').addEventListener('input', function() {
            const buscar = this.value.toLowerCase();
            document.querySelectorAll('.producto-card').forEach(card => {
                const nombre = card.dataset.nombre.toLowerCase();
                const codigo = card.dataset.codigo.toLowerCase();
                const visible = nombre.includes(buscar) || codigo.includes(buscar);
                card.style.display = visible ? 'block' : 'none';
            });
        });

        // ===================================
        // EVENT LISTENERS
        // ===================================
        document.getElementById('tipo_pago').addEventListener('change', function() {
            validarLimiteCredito();
            calcularCambio();
            
            const pagoPlazosSection = document.getElementById('pagoPlazosSection');
            
            // Mostrar información de crédito si cambia a crédito
            if (clienteSeleccionado) {
                const creditoInfo = document.getElementById('clienteCreditoInfo');
                if (this.value === 'credito' && clienteSeleccionado.limite_credito > 0) {
                    creditoInfo.style.display = 'block';
                } else {
                    creditoInfo.style.display = 'none';
                }
            }

            // Mostrar sección de plazos solo si es a crédito
            if (this.value === 'credito') {
                pagoPlazosSection.style.display = 'block';
            } else {
                pagoPlazosSection.style.display = 'none';
                document.getElementById('es_a_plazos').checked = false;
                document.getElementById('opcionesPlazos').style.display = 'none';
            }
        });

        // Toggle para Venta a Plazos
        document.getElementById('es_a_plazos').addEventListener('change', function() {
            document.getElementById('opcionesPlazos').style.display = this.checked ? 'block' : 'none';
            simularPlazos();
        });

        // Eventos para recalcular el simulador de plazos
        ['frecuencia_plazos', 'cantidad_cuotas', 'monto_inicial', 'interes_porcentaje'].forEach(id => {
            document.getElementById(id).addEventListener('input', simularPlazos);
            document.getElementById(id).addEventListener('change', simularPlazos);
        });

        function simularPlazos() {
            const simulador = document.getElementById('simuladorPlazos');
            if (!document.getElementById('es_a_plazos').checked || carrito.length === 0) {
                simulador.innerHTML = '<em>Llena los datos arriba para simular las cuotas.</em>';
                return;
            }

            const cantidad = parseInt(document.getElementById('cantidad_cuotas').value) || 0;
            const inicial = parseFloat(document.getElementById('monto_inicial').value) || 0;
            const interes = parseFloat(document.getElementById('interes_porcentaje').value) || 0;

            if (cantidad <= 0) {
                simulador.innerHTML = '<em>Ingresa una cantidad de cuotas válida.</em>';
                return;
            }

            if (inicial >= totalVenta) {
                simulador.innerHTML = '<em style="color:red;">El inicial no puede ser mayor o igual al total.</em>';
                return;
            }

            const deudaBase = totalVenta - inicial;
            const montoInteres = deudaBase * (interes / 100);
            const deudaTotal = deudaBase + montoInteres;
            const cuotaMonto = deudaTotal / cantidad;

            simulador.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; text-align: left; gap: 5px;">
                    <div><strong>Total Factura:</strong></div> <div style="text-align:right;">RD$ ${totalVenta.toFixed(2)}</div>
                    <div><strong>Inicial:</strong></div> <div style="text-align:right;">- RD$ ${inicial.toFixed(2)}</div>
                    <div><strong>Interés (${interes}%):</strong></div> <div style="text-align:right;">+ RD$ ${montoInteres.toFixed(2)}</div>
                    <div style="grid-column: span 2; border-bottom: 1px solid #ccc; margin: 4px 0;"></div>
                    <div><strong>A Financiar:</strong></div> <div style="text-align:right;">RD$ ${deudaTotal.toFixed(2)}</div>
                    <div style="grid-column: span 2; margin-top: 8px; font-size: 15px; text-align: center; color: #2e7d32; font-weight: bold;">
                        ${cantidad} pagos de RD$ ${cuotaMonto.toFixed(2)}
                    </div>
                </div>
            `;
        }
        
        document.getElementById('efectivo_recibido').addEventListener('input', calcularCambio);
        
        // Recalcular cuando cambie el descuento global
        document.getElementById('descuento').addEventListener('input', actualizarCarrito);
        document.getElementById('tipo_descuento').addEventListener('change', actualizarCarrito);
        
        // Toggle de descuento por línea
        document.getElementById('toggleDescuentoLinea').addEventListener('change', function() {
            descuentoPorLineaActivo = this.checked;
            actualizarCarrito();
        });
        
        // Prevenir envío de formulario con Enter
        document.getElementById('buscarCliente').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.value.trim() !== '' && clientes.length > 0) {
                    const primerCliente = clientes.find(cliente => 
                        cliente.nombre.toLowerCase().includes(this.value.toLowerCase()) ||
                        (cliente.documento && cliente.documento.toLowerCase().includes(this.value.toLowerCase()))
                    );
                    if (primerCliente) {
                        seleccionarCliente(primerCliente);
                    }
                }
            }
        });

        // ============================================
        // SCANNER DE CÓDIGOS DE BARRAS
        // ============================================
        // Los scanners físicos escriben cada carácter muy rápido (<50ms entre teclas)
        // y terminan con un Enter. Este código detecta eso automáticamente.
        
        const scannerInput = document.getElementById('scannerInput');
        let scannerBuffer = '';
        let scannerLastTime = 0;

        // Cargar todos los productos en un mapa para búsqueda rápida por código
        const productosMap = {};
        document.querySelectorAll('.producto-card').forEach(card => {
            const codigo = card.dataset.codigo;
            if(codigo) productosMap[codigo.toUpperCase()] = card;
        });

        function procesarCodigoScanner(codigo) {
            if(!codigo) return;
            const card = productosMap[codigo.toUpperCase()];
            const scannerBox = document.getElementById('scannerBox');

            if(card) {
                if(!card.classList.contains('sin-stock')) {
                    agregarAlCarrito(card);
                    // Animación de éxito
                    scannerBox.classList.add('scanner-hit');
                    setTimeout(() => scannerBox.classList.remove('scanner-hit'), 500);
                    // Flash verde en la tarjeta
                    card.style.transition = 'background 0.3s';
                    card.style.background = '#d1fae5';
                    setTimeout(() => card.style.background = '', 800);
                } else {
                    // Sin stock
                    scannerInput.value = '⚠️ Sin stock: ' + codigo;
                    setTimeout(() => scannerInput.value = '', 2000);
                }
            } else {
                scannerInput.value = '❌ Código no encontrado: ' + codigo;
                setTimeout(() => scannerInput.value = '', 2000);
            }
        }

        // Listener para el input del scanner
        scannerInput.addEventListener('keydown', function(e) {
            const now = Date.now();
            if(e.key === 'Enter') {
                const codigo = scannerBuffer.trim();
                scannerBuffer = '';
                if(codigo.length > 0) {
                    procesarCodigoScanner(codigo);
                    this.value = '';
                }
                e.preventDefault();
                return;
            }
            // Si el tiempo entre teclas es muy corto, es el scanner
            if(now - scannerLastTime < 80 || scannerBuffer === '') {
                scannerBuffer += e.key;
            } else {
                scannerBuffer = e.key;
            }
            scannerLastTime = now;
        });

        // También permitir buscar manualmente en el campo del scanner
        scannerInput.addEventListener('blur', function() {
            scannerBuffer = '';
        });

    </script>
</body>
</html>
<?php sqlsrv_close($conn); ?>