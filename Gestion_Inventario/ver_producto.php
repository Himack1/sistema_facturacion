<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit(); }
require_once '../conexion.php';
require_once '../includes/config_empresa.php';
$config_empresa = obtenerConfigEmpresa($conn);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) { header("Location: gestion_inventario.php"); exit(); }

// Obtener producto
$sql = "SELECT p.*, c.nombre as categoria_nombre, u.usuario as usuario_registro_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN usuarios u ON p.usuario_registro = u.id
        WHERE p.id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$prod = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$prod) { header("Location: gestion_inventario.php"); exit(); }

// Obtener últimos movimientos
$sql_mov = "SELECT m.*, u.usuario as usuario_nombre
            FROM movimientos_inventario m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.producto_id = ?
            ORDER BY m.fecha_movimiento DESC";
$stmt_mov = sqlsrv_query($conn, $sql_mov, array($id));

$fecha_creacion = $prod['fecha_creacion']->format('d/m/Y H:i');
$fecha_modificacion = $prod['ultima_modificacion']->format('d/m/Y H:i');
$utilidad = $prod['precio_costo'] ? ($prod['precio_venta'] - $prod['precio_costo']) : 0;
$margen = $prod['precio_costo'] && $prod['precio_costo'] > 0 ? (($utilidad / $prod['precio_costo']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Producto - <?php echo htmlspecialchars($prod['nombre']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .producto-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .producto-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; display: flex; justify-content: space-between; align-items: center; }
        .producto-header h2 { font-size: 28px; margin-bottom: 5px; }
        .producto-header p { opacity: 0.9; font-size: 14px; }
        .producto-header-content { display: flex; align-items: center; gap: 20px; }
        .producto-img-circle { width: 80px; height: 80px; border-radius: 50%; background: white; object-fit: cover; border: 3px solid rgba(255,255,255,0.3); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .producto-img-placeholder { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 30px; border: 3px solid rgba(255,255,255,0.3); }
        .badge { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .badge.producto { background: rgba(255,255,255,0.3); }
        .badge.servicio { background: rgba(255,255,255,0.3); }
        .producto-body { padding: 40px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-section { margin-bottom: 35px; }
        .section-title { color: #667eea; font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; display: flex; align-items: center; gap: 10px; }
        .info-item { padding: 15px; background: #f8f9ff; border-radius: 8px; border-left: 4px solid #667eea; }
        .info-label { font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
        .info-value { font-size: 16px; color: #333; font-weight: 500; }
        .info-value.empty { color: #999; font-style: italic; }
        .info-value.positivo { color: #2e7d32; font-weight: 600; }
        .info-value.negativo { color: #c62828; font-weight: 600; }
        .stock-grande { font-size: 48px; font-weight: 700; color: #667eea; text-align: center; }
        .stock-estado { text-align: center; margin-top: 10px; }
        .acciones-footer { background: #f8f9ff; padding: 25px 40px; display: flex; gap: 15px; justify-content: center; }
        .btn-accion { padding: 12px 25px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-editar { background: #ff9800; color: white; }
        .btn-stock { background: #4caf50; color: white; }
        .btn-eliminar { background: #f44336; color: white; }
        .tabla-movimientos { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-movimientos th { background: #f8f9ff; padding: 12px; text-align: left; font-weight: 600; color: #333; font-size: 13px; }
        .tabla-movimientos td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .badge-mov { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-mov.entrada { background: #e8f5e9; color: #2e7d32; }
        .badge-mov.salida { background: #ffebee; color: #c62828; }
        .badge-mov.ajuste { background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
    <header class="header">
        <h1>👁️ Detalles del Producto</h1>
        <a href="gestion_inventario.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="producto-card">
            <div class="producto-header">
                <div class="producto-header-content">
                    <?php if(!empty($prod['imagen'])): ?>
                        <img src="<?php echo strpos($prod['imagen'], 'http') === 0 ? htmlspecialchars($prod['imagen']) : '../' . htmlspecialchars($prod['imagen']); ?>" class="producto-img-circle" alt="Imagen producto">
                    <?php else: ?>
                        <div class="producto-img-placeholder">
                            <?php echo $prod['tipo'] === 'producto' ? '📦' : '🛠️'; ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h2><?php echo htmlspecialchars($prod['nombre']); ?></h2>
                        <p>Código: <?php echo htmlspecialchars($prod['codigo']); ?></p>
                    </div>
                </div>
                <span class="badge <?php echo $prod['tipo']; ?>">
                    <?php echo $prod['tipo'] === 'producto' ? '📦 PRODUCTO' : '🛠️ SERVICIO'; ?>
                </span>
            </div>

            <div class="producto-body">
                <div class="info-section">
                    <div class="section-title">📋 Información General</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Categoría</div>
                            <div class="info-value"><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Estado</div>
                            <div class="info-value"><?php echo ucfirst($prod['estado']); ?></div>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="info-label">Descripción</div>
                            <div class="info-value <?php echo empty($prod['descripcion']) ? 'empty' : ''; ?>">
                                <?php echo $prod['descripcion'] ? htmlspecialchars($prod['descripcion']) : 'Sin descripción'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <div class="section-title">💰 Información de Precios</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Precio de Venta</div>
                            <div class="info-value">RD$ <?php echo number_format($prod['precio_venta'], 2); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Precio de Costo</div>
                            <div class="info-value">
                                <?php echo $prod['precio_costo'] ? 'RD$ ' . number_format($prod['precio_costo'], 2) : 'No registrado'; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Impuesto</div>
                            <div class="info-value">
                                <?php 
                                    if ($prod['impuesto'] === null) {
                                        echo '<span style="color: #666; font-style: italic;">Global (' . number_format($config_empresa['itbis_porcentaje'], 2) . '%)</span>';
                                    } else {
                                        echo number_format($prod['impuesto'], 2) . '%';
                                    }
                                ?>
                            </div>
                        </div>
                        <?php if($prod['precio_costo']): ?>
                        <div class="info-item">
                            <div class="info-label">Utilidad por Unidad</div>
                            <div class="info-value positivo">RD$ <?php echo number_format($utilidad, 2); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Margen de Ganancia</div>
                            <div class="info-value positivo"><?php echo number_format($margen, 2); ?>%</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($prod['aplica_inventario']): ?>
                <div class="info-section">
                    <div class="section-title">📦 Control de Inventario</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Stock Actual</div>
                            <div class="stock-grande"><?php echo $prod['stock_actual']; ?></div>
                            <div class="stock-estado">
                                <?php if($prod['stock_actual'] <= $prod['stock_minimo']): ?>
                                    <span class="badge-mov salida">⚠️ Stock Bajo</span>
                                <?php else: ?>
                                    <span class="badge-mov entrada">✓ Stock Normal</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Stock Mínimo</div>
                            <div class="info-value"><?php echo $prod['stock_minimo']; ?> unidades</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Valor del Inventario</div>
                            <div class="info-value positivo">
                                RD$ <?php echo number_format($prod['stock_actual'] * $prod['precio_venta'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <?php if($stmt_mov && sqlsrv_has_rows($stmt_mov)): ?>
                    <div style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: #333;">Últimos Movimientos</h4>
                        <table class="tabla-movimientos">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Motivo</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 0;
                                while($mov = sqlsrv_fetch_array($stmt_mov, SQLSRV_FETCH_ASSOC) and $count < 10): 
                                    $count++;
                                    $fecha_mov = $mov['fecha_movimiento']->format('d/m/Y H:i');
                                ?>
                                <tr>
                                    <td><?php echo $fecha_mov; ?></td>
                                    <td>
                                        <span class="badge-mov <?php echo $mov['tipo_movimiento']; ?>">
                                            <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $mov['cantidad']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($mov['motivo'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="info-section">
                    <div class="section-title">ℹ️ Información del Sistema</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Registrado Por</div>
                            <div class="info-value"><?php echo htmlspecialchars($prod['usuario_registro_nombre'] ?? 'Sistema'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha de Registro</div>
                            <div class="info-value"><?php echo $fecha_creacion; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Última Modificación</div>
                            <div class="info-value"><?php echo $fecha_modificacion; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="acciones-footer">
                <a href="gestion_inventario.php" class="btn-accion" style="background: #e0e0e0; color: #333;">
                    ← Volver a la Lista
                </a>
                <a href="editar_producto.php?id=<?php echo $prod['id']; ?>" class="btn-accion btn-editar">
                    ✏️ Editar Producto
                </a>
                <?php if($prod['aplica_inventario']): ?>
                <a href="ajustar_stock.php?id=<?php echo $prod['id']; ?>" class="btn-accion btn-stock">
                    📊 Ajustar Stock
                </a>
                <?php endif; ?>
                <a href="eliminar_producto.php?id=<?php echo $prod['id']; ?>" class="btn-accion btn-eliminar"
                   onclick="return confirm('⚠️ ¿ELIMINAR este producto?');">
                    🗑️ Eliminar
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php sqlsrv_close($conn); ?>