<?php

/**
 * CONSULTAR FACTURAS
 * Módulo para ver historial de facturas con filtros avanzados
 * Administradores ven todas, cajeros solo sus facturas
 */

session_start();

// ==================================================
// VALIDACIÓN DE SESIÓN Y PERMISOS
// ==================================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

$es_admin = ($_SESSION['usuario_rol'] === 'ADMIN');
$usuario_id = $_SESSION['usuario_id'];

require_once '../conexion.php';

// ==================================================
// PROCESAR FILTROS
// ==================================================
$filtros = [];
$params = [];
$param_types = [];

// Filtro por usuario (cajeros solo ven sus facturas)
if (!$es_admin) {
    $filtros[] = "f.usuario_id = ?";
    $params[] = $usuario_id;
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por rango de fechas
if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
    $fecha_desde = date('Y-m-d 00:00:00', strtotime($_GET['fecha_desde']));
    $filtros[] = "f.fecha >= ?";
    $params[] = $fecha_desde;
    $param_types[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
    $fecha_hasta = date('Y-m-d 23:59:59', strtotime($_GET['fecha_hasta']));
    $filtros[] = "f.fecha <= ?";
    $params[] = $fecha_hasta;
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por número de factura
if (isset($_GET['numero_factura']) && !empty($_GET['numero_factura'])) {
    $filtros[] = "f.numero_factura LIKE ?";
    $params[] = '%' . $_GET['numero_factura'] . '%';
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por cliente
if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id'])) {
    $filtros[] = "f.cliente_id = ?";
    $params[] = intval($_GET['cliente_id']);
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por tipo de pago
if (isset($_GET['tipo_pago']) && !empty($_GET['tipo_pago'])) {
    $filtros[] = "f.tipo_pago = ?";
    $params[] = $_GET['tipo_pago'];
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por estado
if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $filtros[] = "f.estado = ?";
    $params[] = $_GET['estado'];
    $param_types[] = SQLSRV_PARAM_IN;
}

// Filtro por usuario (solo admin puede filtrar por otros usuarios)
if ($es_admin && isset($_GET['usuario_id']) && !empty($_GET['usuario_id'])) {
    $filtros[] = "f.usuario_id = ?";
    $params[] = intval($_GET['usuario_id']);
    $param_types[] = SQLSRV_PARAM_IN;
}

// ==================================================
// CONSTRUIR CONSULTA PRINCIPAL
// ==================================================
$sql = "SELECT 
            f.id,
            f.numero_factura,
            f.fecha,
            c.nombre AS cliente_nombre,
            c.documento AS cliente_documento,
            u.nombre AS usuario_nombre,
            f.tipo_pago,
            f.total,
            f.estado,
            f.monto_inicial,
            f.es_a_plazos,
            COUNT(d.id) AS cantidad_items
        FROM facturas f
        INNER JOIN clientes c ON f.cliente_id = c.id
        INNER JOIN usuarios u ON f.usuario_id = u.id
        LEFT JOIN detalle_factura d ON f.id = d.factura_id";

// Aplicar filtros si existen
if (!empty($filtros)) {
    $sql .= " WHERE " . implode(" AND ", $filtros);
}

// Agrupar y ordenar
$sql .= " GROUP BY 
            f.id, f.numero_factura, f.fecha, c.nombre, c.documento,
            u.nombre, f.tipo_pago, f.total, f.estado, f.monto_inicial, f.es_a_plazos
          ORDER BY f.fecha DESC, f.id DESC";

// ==================================================
// EJECUTAR CONSULTA
// ==================================================
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Error al consultar facturas: " . print_r(sqlsrv_errors(), true));
}

// ==================================================
// OBTENER DATOS PARA FILTROS (CLIENTES, USUARIOS)
// ==================================================
// Obtener clientes activos para el filtro
$sql_clientes = "SELECT id, nombre FROM clientes WHERE estado = 'activo' ORDER BY nombre";
$stmt_clientes = sqlsrv_query($conn, $sql_clientes);

if ($stmt_clientes === false) {
    die("Error al cargar clientes: " . print_r(sqlsrv_errors(), true));
}

// Obtener usuarios para filtro (solo si es admin)
$sql_usuarios = "SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre";
$stmt_usuarios = sqlsrv_query($conn, $sql_usuarios);

if ($stmt_usuarios === false) {
    die("Error al cargar usuarios: " . print_r(sqlsrv_errors(), true));
}

// ==================================================
// OBTENER TOTALES PARA ESTADÍSTICAS
// ==================================================
// Construir WHERE para totales
$where_totales = [];
$params_totales = [];
$param_types_totales = [];

// Filtro por usuario (cajeros solo ven sus facturas)
if (!$es_admin) {
    $where_totales[] = "f.usuario_id = ?";
    $params_totales[] = $usuario_id;
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

// Replicar los mismos filtros de la consulta principal
if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
    $fecha_desde = date('Y-m-d 00:00:00', strtotime($_GET['fecha_desde']));
    $where_totales[] = "f.fecha >= ?";
    $params_totales[] = $fecha_desde;
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
    $fecha_hasta = date('Y-m-d 23:59:59', strtotime($_GET['fecha_hasta']));
    $where_totales[] = "f.fecha <= ?";
    $params_totales[] = $fecha_hasta;
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['numero_factura']) && !empty($_GET['numero_factura'])) {
    $where_totales[] = "f.numero_factura LIKE ?";
    $params_totales[] = '%' . $_GET['numero_factura'] . '%';
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id'])) {
    $where_totales[] = "f.cliente_id = ?";
    $params_totales[] = intval($_GET['cliente_id']);
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['tipo_pago']) && !empty($_GET['tipo_pago'])) {
    $where_totales[] = "f.tipo_pago = ?";
    $params_totales[] = $_GET['tipo_pago'];
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $where_totales[] = "f.estado = ?";
    $params_totales[] = $_GET['estado'];
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

if ($es_admin && isset($_GET['usuario_id']) && !empty($_GET['usuario_id'])) {
    $where_totales[] = "f.usuario_id = ?";
    $params_totales[] = intval($_GET['usuario_id']);
    $param_types_totales[] = SQLSRV_PARAM_IN;
}

$sql_totales = "SELECT 
                    COUNT(*) as total_facturas,
                    SUM(CASE WHEN f.estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                    SUM(CASE WHEN f.estado = 'anulada' THEN 1 ELSE 0 END) as anuladas,
                    SUM(CASE WHEN f.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(f.total) as monto_total
                FROM facturas f
                INNER JOIN clientes c ON f.cliente_id = c.id
                INNER JOIN usuarios u ON f.usuario_id = u.id";

if (!empty($where_totales)) {
    $sql_totales .= " WHERE " . implode(" AND ", $where_totales);
}

$stmt_totales = sqlsrv_query($conn, $sql_totales, $params_totales);
if ($stmt_totales === false) {
    die("Error al consultar totales: " . print_r(sqlsrv_errors(), true));
}

$totales = sqlsrv_fetch_array($stmt_totales, SQLSRV_FETCH_ASSOC);
if (!$totales) {
    $totales = [
        'total_facturas' => 0,
        'pagadas' => 0,
        'anuladas' => 0,
        'pendientes' => 0,
        'monto_total' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Facturas - Sistema de Facturación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 22px;
        }

        .btn-volver {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-volver:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .estadistica-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .estadistica-valor {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .estadistica-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estadistica-total {
            color: #667eea;
        }

        .estadistica-pagada {
            color: #4caf50;
        }

        .estadistica-anulada {
            color: #f44336;
        }

        .estadistica-pendiente {
            color: #ff9800;
        }

        .filtros-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .filtros-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filtros-header h2 {
            font-size: 18px;
            color: #333;
        }

        .btn-limpiar {
            background: #e0e0e0;
            color: #333;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            margin-bottom: 5px;
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

        .form-group input[type="date"] {
            padding: 9px;
        }

        .btn-buscar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            align-self: flex-end;
        }

        .btn-buscar:hover {
            transform: translateY(-2px);
        }

        .tabla-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .factura-table {
            width: 100%;
            border-collapse: collapse;
        }

        .factura-table thead {
            background: #f8f9ff;
        }

        .factura-table th {
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }

        .factura-table td {
            padding: 15px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }

        .factura-table tbody tr:hover {
            background: #f8f9ff;
        }

        .estado-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estado-pagada {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .estado-anulada {
            background: #ffebee;
            color: #c62828;
        }

        .estado-pendiente {
            background: #fff3e0;
            color: #ef6c00;
        }

        .badge-pago {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .pago-efectivo {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .pago-transferencia {
            background: #e3f2fd;
            color: #1976d2;
        }

        .pago-credito {
            background: #fff3e0;
            color: #ef6c00;
        }

        .btn-ver {
            background: #2196f3;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-ver:hover {
            background: #1976d2;
        }

        .sin-resultados {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .paginacion {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-pagina {
            padding: 8px 15px;
            background: #e0e0e0;
            color: #333;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-pagina.activa {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .estadisticas {
                grid-template-columns: repeat(2, 1fr);
            }

            .filtros-grid {
                grid-template-columns: 1fr;
            }

            .factura-table {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <h1>📊 Consultar Facturas</h1>
        <div>
            <a href="../dashboard.php" class="btn-volver">🏠 Dashboard</a>
        </div>
    </header>

    <div class="container">
        <!-- Estadísticas -->
        <div class="estadisticas">
            <div class="estadistica-card">
                <div class="estadistica-valor estadistica-total"><?php echo $totales['total_facturas'] ?? 0; ?></div>
                <div class="estadistica-label">Total Facturas</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor estadistica-pagada"><?php echo $totales['pagadas'] ?? 0; ?></div>
                <div class="estadistica-label">Pagadas</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor estadistica-anulada"><?php echo $totales['anuladas'] ?? 0; ?></div>
                <div class="estadistica-label">Anuladas</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor estadistica-pendiente"><?php echo $totales['pendientes'] ?? 0; ?></div>
                <div class="estadistica-label">Pendientes</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor estadistica-total">RD$ <?php echo number_format($totales['monto_total'] ?? 0, 2); ?></div>
                <div class="estadistica-label">Monto Total</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-card">
            <div class="filtros-header">
                <h2>🔍 Filtros de Búsqueda</h2>
                <a href="consultar_facturas.php" class="btn-limpiar">🔄 Limpiar Filtros</a>
            </div>

            <form method="GET" action="">
                <div class="filtros-grid">
                    <!-- Rango de Fechas -->
                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde"
                            value="<?php echo $_GET['fecha_desde'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta"
                            value="<?php echo $_GET['fecha_hasta'] ?? ''; ?>">
                    </div>

                    <!-- Número de Factura -->
                    <div class="form-group">
                        <label>Número de Factura</label>
                        <input type="text" name="numero_factura"
                            placeholder="FACT-YYYYMMDD-####"
                            value="<?php echo $_GET['numero_factura'] ?? ''; ?>">
                    </div>

                    <!-- Cliente -->
                    <div class="form-group">
                        <label>Cliente</label>
                        <select name="cliente_id">
                            <option value="">Todos los clientes</option>
                            <?php while ($cliente = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $cliente['id']; ?>"
                                    <?php echo (isset($_GET['cliente_id']) && $_GET['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Usuario (solo para admin) -->
                    <?php if ($es_admin): ?>
                        <div class="form-group">
                            <label>Cajero</label>
                            <select name="usuario_id">
                                <option value="">Todos los cajeros</option>
                                <?php
                                // Reiniciar puntero
                                sqlsrv_fetch($stmt_usuarios, SQLSRV_SCROLL_ABSOLUTE, 0);
                                while ($usuario = sqlsrv_fetch_array($stmt_usuarios, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?php echo $usuario['id']; ?>"
                                        <?php echo (isset($_GET['usuario_id']) && $_GET['usuario_id'] == $usuario['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Tipo de Pago -->
                    <div class="form-group">
                        <label>Tipo de Pago</label>
                        <select name="tipo_pago">
                            <option value="">Todos los tipos</option>
                            <option value="efectivo" <?php echo (isset($_GET['tipo_pago']) && $_GET['tipo_pago'] == 'efectivo') ? 'selected' : ''; ?>>💵 Efectivo</option>
                            <option value="transferencia" <?php echo (isset($_GET['tipo_pago']) && $_GET['tipo_pago'] == 'transferencia') ? 'selected' : ''; ?>>🏦 Transferencia</option>
                            <option value="credito" <?php echo (isset($_GET['tipo_pago']) && $_GET['tipo_pago'] == 'credito') ? 'selected' : ''; ?>>📝 Crédito</option>
                        </select>
                    </div>

                    <!-- Estado -->
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos los estados</option>
                            <option value="pagada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'pagada') ? 'selected' : ''; ?>>Pagada</option>
                            <option value="anulada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'anulada') ? 'selected' : ''; ?>>Anulada</option>
                            <option value="pendiente" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                        </select>
                    </div>

                    <!-- Botón Buscar -->
                    <div class="form-group">
                        <button type="submit" class="btn-buscar">🔍 Buscar Facturas</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de Resultados -->
        <div class="tabla-container">
            <?php if (sqlsrv_has_rows($stmt)): ?>
                <table class="factura-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <?php if ($es_admin): ?>
                                <th>Cajero</th>
                            <?php endif; ?>
                            <th>Tipo Pago</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($factura = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                            $fecha = $factura['fecha']->format('d/m/Y H:i');
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($factura['numero_factura']); ?></strong>
                                </td>
                                <td><?php echo $fecha; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($factura['cliente_nombre']); ?></div>
                                    <small style="color: #666;">
                                        <?= htmlspecialchars($factura['cliente_documento'] ?? '-') ?>
                                    </small>

                                </td>
                                <?php if ($es_admin): ?>
                                    <td><?php echo htmlspecialchars($factura['usuario_nombre']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge-pago pago-<?php echo $factura['tipo_pago']; ?>">
                                        <?php
                                        $iconos_pago = [
                                            'efectivo' => '💵',
                                            'transferencia' => '🏦',
                                            'credito' => '📝'
                                        ];
                                        echo ($iconos_pago[$factura['tipo_pago']] ?? '') . ' ' . ucfirst($factura['tipo_pago']);
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $factura['cantidad_items']; ?></td>
                                <td style="font-weight: 600; color: #667eea;">
                                    RD$ <?php echo number_format($factura['total'], 2); ?>
                                    <?php if ($factura['es_a_plazos']): ?>
                                        <div style="font-size: 10px; color: #666; font-weight: normal; margin-top: 3px;">
                                            Inicial: RD$ <?php echo number_format($factura['monto_inicial'], 2); ?><br>
                                            Total financiado: RD$ <?php echo number_format($factura['total'] - $factura['monto_inicial'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?php echo $factura['estado']; ?>">
                                        <?php echo ucfirst($factura['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ver_factura_consulta.php?id=<?= $factura['id'] ?>"
                                        class="btn-ver"
                                        target="_blank">
                                        👁️ Ver
                                    </a>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sin-resultados">
                    <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                    <p>No se encontraron facturas con los filtros seleccionados</p>
                    <p style="font-size: 14px; color: #888; margin-top: 10px;">
                        Intenta cambiar los criterios de búsqueda
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-seleccionar fechas comunes
        document.addEventListener('DOMContentLoaded', function() {
            // Si no hay filtros, establecer fecha de hoy por defecto en "fecha_hasta"
            const fechaHasta = document.querySelector('input[name="fecha_hasta"]');
            const fechaDesde = document.querySelector('input[name="fecha_desde"]');

            if (fechaHasta && !fechaHasta.value) {
                const hoy = new Date().toISOString().split('T')[0];
                fechaHasta.value = hoy;
            }

            // Opción para seleccionar rangos comunes
            const rangoBtns = document.createElement('div');
            rangoBtns.innerHTML = `
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" class="btn-rango" data-dias="7">Últimos 7 días</button>
                    <button type="button" class="btn-rango" data-dias="30">Últimos 30 días</button>
                    <button type="button" class="btn-rango" data-mes="true">Este mes</button>
                </div>
            `;

            fechaDesde.parentNode.appendChild(rangoBtns);

            document.querySelectorAll('.btn-rango').forEach(btn => {
                btn.style.cssText = `
                    padding: 5px 10px;
                    background: #f0f0f0;
                    border: none;
                    border-radius: 3px;
                    font-size: 12px;
                    cursor: pointer;
                    color: #666;
                `;

                btn.addEventListener('click', function() {
                    const hoy = new Date();

                    if (this.dataset.dias) {
                        const dias = parseInt(this.dataset.dias);
                        const fecha = new Date(hoy);
                        fecha.setDate(fecha.getDate() - dias);

                        fechaDesde.value = fecha.toISOString().split('T')[0];
                        fechaHasta.value = hoy.toISOString().split('T')[0];
                    } else if (this.dataset.mes) {
                        const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                        const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);

                        fechaDesde.value = primerDiaMes.toISOString().split('T')[0];
                        fechaHasta.value = ultimoDiaMes.toISOString().split('T')[0];
                    }

                    // Enviar formulario automáticamente
                    this.closest('form').submit();
                });
            });

            // Exportar a Excel
            const btnExportar = document.createElement('button');
            btnExportar.textContent = '📊 Exportar a Excel';
            btnExportar.style.cssText = `
                background: #4caf50;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 5px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                margin-top: 20px;
                float: right;
            `;

            document.querySelector('.filtros-header').appendChild(btnExportar);

            btnExportar.addEventListener('click', function() {
                // Recopilar filtros actuales
                const formData = new FormData(document.querySelector('form'));
                const params = new URLSearchParams();

                for (const [key, value] of formData.entries()) {
                    if (value) params.append(key, value);
                }

                window.open(`exportar_facturas.php?${params.toString()}`, '_blank');
            });
        });
    </script>
</body>

</html>
<?php
// Cerrar conexión
sqlsrv_close($conn);
?>