<?php
session_start();
require_once '../conexion.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$usuario_rol = $_SESSION['usuario_rol'];

// Solo ADMIN puede ver reportes
if ($usuario_rol !== 'ADMIN') {
    header('Location: ../dashboard.php');
    exit();
}

// Obtener filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$categoria_filtro = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$stock_filtro = isset($_GET['stock']) ? $_GET['stock'] : '';

// Consulta para obtener categorías
$sql_categorias = "SELECT id, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre";
$stmt_categorias = sqlsrv_query($conn, $sql_categorias);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inventario - Sistema de Facturación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #16a085;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .container-fluid {
            max-width: 1600px;
        }

        .header-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header-card h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filter-card h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-box .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .stat-box h6 {
            color: #7f8c8d;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-box h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            font-size: 24px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .chart-card h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge-stock {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-normal { background: #27ae60; color: white; }
        .badge-bajo { background: #f39c12; color: white; }
        .badge-critico { background: #e74c3c; color: white; }
        .badge-na { background: #95a5a6; color: white; }

        .badge-tipo {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-producto { background: #3498db; color: white; }
        .badge-servicio { background: #9b59b6; color: white; }

        .badge-movimiento {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-entrada { background: #27ae60; color: white; }
        .badge-salida { background: #e74c3c; color: white; }
        .badge-ajuste { background: #f39c12; color: white; }

        .alert-card {
            background: white;
            border-left: 4px solid;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .alert-card.warning {
            border-color: var(--warning-color);
            background: #fff3cd;
        }

        .alert-card.danger {
            border-color: var(--danger-color);
            background: #f8d7da;
        }

        .alert-card h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
            color: var(--primary-color);
        }

        .alert-card p {
            margin: 0;
            font-size: 14px;
            color: #6c757d;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-primary-custom {
            background: var(--secondary-color);
            color: white;
        }

        .btn-primary-custom:hover {
            background: #138871;
            transform: scale(1.05);
        }

        .btn-success-custom {
            background: var(--success-color);
            color: white;
        }

        .btn-success-custom:hover {
            background: #229954;
        }

        .btn-back {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-back:hover {
            background: var(--primary-color);
            color: white;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(22, 160, 133, 0.25);
        }

        /* Colores de iconos */
        .bg-productos { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .bg-servicios { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; }
        .bg-bajo-stock { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; }
        .bg-sin-stock { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; }
        .bg-valor-inventario { background: linear-gradient(135deg, #16a085 0%, #138871 100%); color: white; }
        .bg-movimientos { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; }

        @media print {
            body { background: white; }
            .no-print { display: none; }
            .header-card, .filter-card, .table-card, .chart-card {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="bi bi-boxes"></i>
                    Reporte de Inventario
                </h1>
                <div class="no-print">
                    <button class="btn-action btn-success-custom" onclick="exportarPDF()">
                        <i class="bi bi-file-pdf"></i> Exportar PDF
                    </button>
                    <button class="btn-action btn-primary-custom" onclick="exportarExcel()">
                        <i class="bi bi-file-excel"></i> Exportar Excel
                    </button>
                    <a href="index.php" class="btn-action btn-back">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card no-print">
            <h5><i class="bi bi-funnel"></i> Filtros de Búsqueda</h5>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio (Movimientos)</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin (Movimientos)</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria">
                        <option value="">Todas</option>
                        <?php 
                        while ($categoria = sqlsrv_fetch_array($stmt_categorias, SQLSRV_FETCH_ASSOC)): 
                        ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                <?php echo ($categoria_filtro == $categoria['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <option value="">Todos</option>
                        <option value="producto" <?php echo ($tipo_filtro == 'producto') ? 'selected' : ''; ?>>Producto</option>
                        <option value="servicio" <?php echo ($tipo_filtro == 'servicio') ? 'selected' : ''; ?>>Servicio</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado Stock</label>
                    <select class="form-select" name="stock">
                        <option value="">Todos</option>
                        <option value="critico" <?php echo ($stock_filtro == 'critico') ? 'selected' : ''; ?>>Sin Stock</option>
                        <option value="bajo" <?php echo ($stock_filtro == 'bajo') ? 'selected' : ''; ?>>Bajo Stock</option>
                        <option value="normal" <?php echo ($stock_filtro == 'normal') ? 'selected' : ''; ?>>Stock Normal</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-action btn-primary-custom">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="reporte_inventario.php" class="btn-action btn-back">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Alertas -->
        <div id="alertas-container" class="no-print">
            <!-- Se cargarán dinámicamente -->
        </div>

        <!-- Estadísticas Resumidas -->
        <div class="stats-grid" id="estadisticas">
            <!-- Se cargarán dinámicamente -->
        </div>

        <!-- Gráficos -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-pie-chart"></i> TOP 10 Productos Más Vendidos</h5>
                    <canvas id="chartTopProductos" height="250"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-bar-chart"></i> Movimientos de Inventario</h5>
                    <canvas id="chartMovimientos" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <div class="table-card">
            <h5><i class="bi bi-box-seam"></i> Productos en Inventario</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaProductos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Tipo</th>
                            <th>Stock Actual</th>
                            <th>Stock Mínimo</th>
                            <th>Estado Stock</th>
                            <th>Precio Venta</th>
                            <th>Veces Vendido</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductos">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla de Movimientos Recientes -->
        <div class="table-card">
            <h5><i class="bi bi-arrow-left-right"></i> Movimientos Recientes de Inventario</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaMovimientos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMovimientos">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables de filtros
        const fechaInicio = '<?php echo $fecha_inicio; ?>';
        const fechaFin = '<?php echo $fecha_fin; ?>';
        const categoriaFiltro = '<?php echo $categoria_filtro; ?>';
        const tipoFiltro = '<?php echo $tipo_filtro; ?>';
        const stockFiltro = '<?php echo $stock_filtro; ?>';

        // Cargar datos al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosReporte();
        });

        function cargarDatosReporte() {
            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                categoria: categoriaFiltro,
                tipo: tipoFiltro,
                stock: stockFiltro
            });

            fetch('ajax/obtener_datos_inventario.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlertas(data.alertas);
                        mostrarEstadisticas(data.estadisticas);
                        mostrarGraficos(data.graficos);
                        mostrarTablaProductos(data.productos);
                        mostrarTablaMovimientos(data.movimientos);
                    } else {
                        alert('Error al cargar datos: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión');
                });
        }

        function mostrarAlertas(alertas) {
            let html = '';
            
            if (alertas.sin_stock > 0) {
                html += `
                    <div class="alert-card danger">
                        <h6><i class="bi bi-exclamation-triangle-fill"></i> Productos Sin Stock</h6>
                        <p>${alertas.sin_stock} producto(s) sin stock disponible. Requiere reabastecimiento urgente.</p>
                    </div>
                `;
            }
            
            if (alertas.bajo_stock > 0) {
                html += `
                    <div class="alert-card warning">
                        <h6><i class="bi bi-exclamation-circle-fill"></i> Productos con Stock Bajo</h6>
                        <p>${alertas.bajo_stock} producto(s) por debajo del stock mínimo. Se recomienda reabastecer pronto.</p>
                    </div>
                `;
            }
            
            document.getElementById('alertas-container').innerHTML = html;
        }

        function mostrarEstadisticas(stats) {
            const html = `
                <div class="stat-box">
                    <div class="icon bg-productos">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h6>Total Productos</h6>
                    <h3>${stats.total_productos}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-servicios">
                        <i class="bi bi-tools"></i>
                    </div>
                    <h6>Total Servicios</h6>
                    <h3>${stats.total_servicios}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-sin-stock">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h6>Sin Stock</h6>
                    <h3>${stats.productos_sin_stock}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-bajo-stock">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h6>Stock Bajo</h6>
                    <h3>${stats.productos_bajo_stock}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-valor-inventario">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <h6>Valor Inventario</h6>
                    <h3>RD$ ${formatearMoneda(stats.valor_total_inventario)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-movimientos">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <h6>Movimientos (Período)</h6>
                    <h3>${stats.total_movimientos}</h3>
                </div>
            `;
            document.getElementById('estadisticas').innerHTML = html;
        }

        function mostrarGraficos(graficos) {
            // Gráfico TOP Productos
            const ctxTop = document.getElementById('chartTopProductos').getContext('2d');
            new Chart(ctxTop, {
                type: 'bar',
                data: {
                    labels: graficos.top_productos.labels,
                    datasets: [{
                        label: 'Unidades Vendidas',
                        data: graficos.top_productos.datos,
                        backgroundColor: 'rgba(22, 160, 133, 0.7)',
                        borderColor: 'rgba(22, 160, 133, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });

            // Gráfico Movimientos
            const ctxMov = document.getElementById('chartMovimientos').getContext('2d');
            new Chart(ctxMov, {
                type: 'doughnut',
                data: {
                    labels: graficos.movimientos.labels,
                    datasets: [{
                        data: graficos.movimientos.datos,
                        backgroundColor: ['#27ae60', '#e74c3c', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function mostrarTablaProductos(productos) {
            let html = '';
            productos.forEach(prod => {
                let badgeStock = '';
                if (prod.tipo === 'servicio') {
                    badgeStock = '<span class="badge-stock badge-na">N/A</span>';
                } else if (prod.stock_actual <= 0) {
                    badgeStock = '<span class="badge-stock badge-critico">SIN STOCK</span>';
                } else if (prod.stock_actual <= prod.stock_minimo) {
                    badgeStock = '<span class="badge-stock badge-bajo">BAJO</span>';
                } else {
                    badgeStock = '<span class="badge-stock badge-normal">NORMAL</span>';
                }
                
                html += `
                    <tr>
                        <td><strong>${prod.codigo}</strong></td>
                        <td>${prod.nombre}</td>
                        <td>${prod.categoria}</td>
                        <td><span class="badge-tipo badge-${prod.tipo}">${prod.tipo.toUpperCase()}</span></td>
                        <td>${prod.tipo === 'servicio' ? 'N/A' : prod.stock_actual}</td>
                        <td>${prod.tipo === 'servicio' ? 'N/A' : prod.stock_minimo}</td>
                        <td>${badgeStock}</td>
                        <td>RD$ ${formatearMoneda(prod.precio_venta)}</td>
                        <td><strong>${prod.veces_vendido}</strong></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-info" onclick="verMovimientos('${prod.id}')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            document.getElementById('tbodyProductos').innerHTML = html || '<tr><td colspan="10" class="text-center">No hay productos registrados</td></tr>';
        }

        function mostrarTablaMovimientos(movimientos) {
            let html = '';
            movimientos.forEach(mov => {
                html += `
                    <tr>
                        <td>${mov.fecha}</td>
                        <td>${mov.producto}</td>
                        <td><span class="badge-movimiento badge-${mov.tipo_movimiento}">${mov.tipo_movimiento.toUpperCase()}</span></td>
                        <td><strong>${mov.cantidad}</strong></td>
                        <td>${mov.motivo || '-'}</td>
                        <td>${mov.usuario || 'Sistema'}</td>
                        <td>${mov.referencia || '-'}</td>
                    </tr>
                `;
            });
            document.getElementById('tbodyMovimientos').innerHTML = html || '<tr><td colspan="7" class="text-center">No hay movimientos en el período seleccionado</td></tr>';
        }

        function formatearMoneda(valor) {
            return parseFloat(valor).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function verMovimientos(id) {
            window.location.href = `../Gestion_Inventario/ver_producto.php?id=${id}`;
        }

        function exportarPDF() {
            window.print();
        }

        function exportarExcel() {
            alert('Función de exportación a Excel en desarrollo');
        }
    </script>
</body>
</html>

<?php sqlsrv_close($conn); ?>