<?php
/**
 * Gestión de Inventario
 * Sistema de Facturación - Tienda de Teléfonos
 */

session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

$usuario_nombre = $_SESSION['usuario_nombre'];
$usuario_rol = $_SESSION['usuario_rol'];

// Obtener filtros
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtro_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtro_stock = isset($_GET['stock']) ? trim($_GET['stock']) : '';

// Construir consulta
$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE 1=1";

$params = array();

if(!empty($buscar)) {
    $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $buscar_param = '%' . $buscar . '%';
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
}

if($filtro_categoria > 0) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $filtro_categoria;
}

if(!empty($filtro_tipo)) {
    $sql .= " AND p.tipo = ?";
    $params[] = $filtro_tipo;
}

if(!empty($filtro_estado)) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if($filtro_stock === 'bajo') {
    $sql .= " AND p.aplica_inventario = 1 AND p.stock_actual <= p.stock_minimo";
}

$sql .= " ORDER BY p.fecha_creacion DESC";

$stmt = !empty($params) ? sqlsrv_query($conn, $sql, $params) : sqlsrv_query($conn, $sql);

// Obtener categorías para filtros
$sql_cat = "SELECT id, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre";
$stmt_cat = sqlsrv_query($conn, $sql_cat);

// Obtener estadísticas
$sql_stats = "SELECT 
    COUNT(*) as total_productos,
    SUM(CASE WHEN tipo = 'producto' THEN 1 ELSE 0 END) as total_productos_fisicos,
    SUM(CASE WHEN tipo = 'servicio' THEN 1 ELSE 0 END) as total_servicios,
    SUM(CASE WHEN aplica_inventario = 1 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) as productos_bajo_stock
FROM productos WHERE estado = 'activo'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - Sistema de Facturación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-dashboard {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-dashboard:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1600px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            font-size: 40px;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        .stat-icon.blue { background: #e3f2fd; }
        .stat-icon.green { background: #e8f5e9; }
        .stat-icon.purple { background: #f3e5f5; }
        .stat-icon.orange { background: #fff3e0; }

        .stat-info h3 {
            font-size: 32px;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .top-bar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .top-bar h2 {
            color: #333;
            font-size: 28px;
        }

        .btn-actions {
            display: flex;
            gap: 10px;
        }

        .btn-nuevo, .btn-categoria {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-categoria {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .filtros {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto auto;
            gap: 15px;
            align-items: end;
        }

        .filtro-grupo label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 13px;
            font-weight: 600;
        }

        .filtro-grupo input, .filtro-grupo select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .filtro-grupo input:focus, .filtro-grupo select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-filtrar, .btn-limpiar {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-filtrar {
            background-color: #667eea;
            color: white;
        }

        .btn-limpiar {
            background-color: #e0e0e0;
            color: #333;
            text-decoration: none;
            display: inline-block;
        }

        .mensaje {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mensaje.exito {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .mensaje.error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        /* Tabla */
        .tabla-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        thead th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background-color: #f8f9ff;
        }

        tbody td {
            padding: 16px 15px;
            color: #333;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.producto { background-color: #e3f2fd; color: #1976d2; }
        .badge.servicio { background-color: #f3e5f5; color: #7b1fa2; }
        .badge.activo { background-color: #e8f5e9; color: #2e7d32; }
        .badge.inactivo { background-color: #ffebee; color: #c62828; }
        .badge.bajo { background-color: #fff3e0; color: #e65100; }
        .badge.normal { background-color: #e8f5e9; color: #2e7d32; }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .acciones {
            display: flex;
            gap: 8px;
        }

        .btn-accion {
            padding: 7px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-ver { background-color: #2196f3; color: white; }
        .btn-editar { background-color: #ff9800; color: white; }
        .btn-stock { background-color: #4caf50; color: white; }
        .btn-eliminar { background-color: #f44336; color: white; }

        @media (max-width: 1400px) {
            .filtros {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>📦 Gestión de Inventario</h1>
        <div class="header-right">
            <span><?php echo htmlspecialchars($usuario_nombre); ?> (<?php echo $usuario_rol; ?>)</span>
            <a href="../dashboard.php" class="btn-dashboard">← Dashboard</a>
        </div>
    </header>

    <div class="container">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📦</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_productos']; ?></h3>
                    <p>Total Productos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">📱</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_productos_fisicos']; ?></h3>
                    <p>Productos Físicos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">🛠️</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_servicios']; ?></h3>
                    <p>Servicios</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">⚠️</div>
                <div class="stat-info">
                    <h3><?php echo $stats['productos_bajo_stock']; ?></h3>
                    <p>Stock Bajo</p>
                </div>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mensaje exito">
                <span>✓</span>
                <span>
                    <?php
                    switch($_GET['success']) {
                        case 'creado': echo 'Producto creado exitosamente.'; break;
                        case 'editado': echo 'Producto actualizado exitosamente.'; break;
                        case 'eliminado': echo 'Producto eliminado exitosamente.'; break;
                        case 'movimiento': echo 'Movimiento registrado exitosamente.'; break;
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="top-bar">
            <div class="top-bar-header">
                <h2>Inventario de Productos y Servicios</h2>
                <div class="btn-actions">
                    <a href="crear_producto.php" class="btn-nuevo">
                        <span>+</span>
                        <span>Nuevo Producto</span>
                    </a>
                    <a href="gestion_categorias.php" class="btn-categoria">
                        <span>📂</span>
                        <span>Categorías</span>
                    </a>
                </div>
            </div>

            <form method="GET" action="" class="filtros">
                <div class="filtro-grupo">
                    <label>Buscar</label>
                    <input type="text" name="buscar" placeholder="Código, nombre..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>

                <div class="filtro-grupo">
                    <label>Categoría</label>
                    <select name="categoria">
                        <option value="">Todas</option>
                        <?php while($cat = sqlsrv_fetch_array($stmt_cat, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="producto" <?php echo $filtro_tipo === 'producto' ? 'selected' : ''; ?>>Producto</option>
                        <option value="servicio" <?php echo $filtro_tipo === 'servicio' ? 'selected' : ''; ?>>Servicio</option>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <label>Estado</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <label>Stock</label>
                    <select name="stock">
                        <option value="">Todos</option>
                        <option value="bajo" <?php echo $filtro_stock === 'bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                    </select>
                </div>

                <button type="submit" class="btn-filtrar">Buscar</button>
                <a href="gestion_inventario.php" class="btn-limpiar">Limpiar</a>
            </form>
        </div>

        <div class="tabla-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hay_productos = false;
                    while($prod = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                        $hay_productos = true;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($prod['codigo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                        <td>
                            <span class="badge <?php echo $prod['tipo']; ?>">
                                <?php echo $prod['tipo'] === 'producto' ? '📦 Producto' : '🛠️ Servicio'; ?>
                            </span>
                        </td>
                        <td>RD$ <?php echo number_format($prod['precio_venta'], 2); ?></td>
                        <td>
                            <?php if($prod['aplica_inventario']): ?>
                                <div class="stock-info">
                                    <span><strong><?php echo $prod['stock_actual']; ?></strong></span>
                                    <?php if($prod['stock_actual'] <= $prod['stock_minimo']): ?>
                                        <span class="badge bajo">⚠️ Bajo</span>
                                    <?php else: ?>
                                        <span class="badge normal">✓</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $prod['estado']; ?>">
                                <?php echo $prod['estado']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="acciones">
                                <a href="ver_producto.php?id=<?php echo $prod['id']; ?>" 
                                   class="btn-accion btn-ver" title="Ver">👁️</a>
                                <a href="editar_producto.php?id=<?php echo $prod['id']; ?>" 
                                   class="btn-accion btn-editar" title="Editar">✏️</a>
                                <?php if($prod['aplica_inventario']): ?>
                                <a href="ajustar_stock.php?id=<?php echo $prod['id']; ?>" 
                                   class="btn-accion btn-stock" title="Ajustar Stock">📊</a>
                                <?php endif; ?>
                                <a href="eliminar_producto.php?id=<?php echo $prod['id']; ?>" 
                                   class="btn-accion btn-eliminar"
                                   onclick="return confirm('⚠️ ¿ELIMINAR este producto?');"
                                   title="Eliminar">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if(!$hay_productos): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 60px 20px; color: #999;">
                            <div style="font-size: 60px; margin-bottom: 15px;">📦</div>
                            <p><?php echo !empty($buscar) ? 'No se encontraron productos' : 'No hay productos registrados'; ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if(mensaje) {
                mensaje.style.transition = 'opacity 0.5s';
                mensaje.style.opacity = '0';
                setTimeout(() => mensaje.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>
<?php
sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($stmt_cat);
sqlsrv_free_stmt($stmt_stats);
sqlsrv_close($conn);
?>