<?php
session_start();
require_once '../conexion.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

$nombre_usuario = $_SESSION['usuario_nombre'];
$rol_usuario = $_SESSION['usuario_rol'];

// Solo ADMIN puede ver reportes
if ($rol_usuario !== 'ADMIN') {
    header('Location: ../dashboard.php');
    exit();
}

// Obtener filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$tipo_pago_filtro = isset($_GET['tipo_pago']) ? $_GET['tipo_pago'] : '';

// Consulta para obtener usuarios
$sql_usuarios = "SELECT id, usuario, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre";
$stmt_usuarios = sqlsrv_query($conn, $sql_usuarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - Sistema de Facturación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .badge-tipo-pago {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-efectivo { background: #27ae60; color: white; }
        .badge-transferencia { background: #3498db; color: white; }
        .badge-credito { background: #e74c3c; color: white; }
        .badge-mixto { background: #f39c12; color: white; }

        .badge-estado {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-pagada { background: #27ae60; color: white; }
        .badge-anulada { background: #e74c3c; color: white; }
        .badge-pendiente { background: #f39c12; color: white; }

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
            background: #2980b9;
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
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        /* Colores de iconos */
        .bg-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .bg-facturas { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .bg-promedio { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .bg-anuladas { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }

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
                    <i class="bi bi-graph-up-arrow"></i>
                    Reporte de Ventas
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
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Usuario/Cajero</label>
                    <select class="form-select" name="usuario">
                        <option value="">Todos los usuarios</option>
                        <?php while ($usuario = sqlsrv_fetch_array($stmt_usuarios, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $usuario['id']; ?>" 
                                <?php echo ($usuario_filtro == $usuario['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Pago</label>
                    <select class="form-select" name="tipo_pago">
                        <option value="">Todos</option>
                        <option value="efectivo" <?php echo ($tipo_pago_filtro == 'efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                        <option value="transferencia" <?php echo ($tipo_pago_filtro == 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                        <option value="credito" <?php echo ($tipo_pago_filtro == 'credito') ? 'selected' : ''; ?>>Crédito</option>
                        <option value="mixto" <?php echo ($tipo_pago_filtro == 'mixto') ? 'selected' : ''; ?>>Mixto</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-action btn-primary-custom">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="reporte_ventas.php" class="btn-action btn-back">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Estadísticas Resumidas -->
        <div class="stats-grid" id="estadisticas">
            <!-- Se cargarán dinámicamente -->
        </div>

        <!-- Gráficos -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-pie-chart"></i> Ventas por Tipo de Pago</h5>
                    <canvas id="chartTipoPago" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-bar-chart"></i> Ventas por Día</h5>
                    <canvas id="chartVentasDiarias" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de Facturas -->
        <div class="table-card">
            <h5><i class="bi bi-table"></i> Detalle de Facturas</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaFacturas">
                    <thead>
                        <tr>
                            <th>Nº Factura</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Usuario</th>
                            <th>Tipo Pago</th>
                            <th>Capital</th>
                            <th>Interés</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFacturas">
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
        const usuarioFiltro = '<?php echo $usuario_filtro; ?>';
        const tipoPagoFiltro = '<?php echo $tipo_pago_filtro; ?>';

        // Cargar datos al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosReporte();
        });

        function cargarDatosReporte() {
            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                usuario: usuarioFiltro,
                tipo_pago: tipoPagoFiltro
            });

            fetch(`ajax/obtener_datos_ventas.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarEstadisticas(data.estadisticas);
                        mostrarGraficos(data.graficos);
                        mostrarTablaFacturas(data.facturas);
                    } else {
                        alert('Error al cargar datos: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión');
                });
        }

        function mostrarEstadisticas(stats) {
            const html = `
                <div class="stat-box">
                    <div class="icon bg-total">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h6>Total Vendido (Bruto)</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_vendido)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-promedio">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <h6>Capital Puro (Ventas)</h6>
                    <h3>RD$ ${formatearMoneda(stats.capital_puro)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-anuladas" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h6>Ganancia Intereses</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_interes)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-facturas">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <h6>Facturas</h6>
                    <h3>${stats.total_facturas}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-promedio">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h6>Promedio por Factura</h6>
                    <h3>RD$ ${formatearMoneda(stats.promedio_factura)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-anuladas">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h6>Facturas Anuladas</h6>
                    <h3>${stats.facturas_anuladas}</h3>
                </div>
            `;
            document.getElementById('estadisticas').innerHTML = html;
        }

        function mostrarGraficos(graficos) {
            // Gráfico de Tipo de Pago
            const ctxTipoPago = document.getElementById('chartTipoPago').getContext('2d');
            new Chart(ctxTipoPago, {
                type: 'doughnut',
                data: {
                    labels: graficos.tipo_pago.labels,
                    datasets: [{
                        data: graficos.tipo_pago.datos,
                        backgroundColor: ['#27ae60', '#3498db', '#e74c3c', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Gráfico de Ventas Diarias
            const ctxVentasDiarias = document.getElementById('chartVentasDiarias').getContext('2d');
            new Chart(ctxVentasDiarias, {
                type: 'bar',
                data: {
                    labels: graficos.ventas_diarias.labels,
                    datasets: [{
                        label: 'Ventas (RD$)',
                        data: graficos.ventas_diarias.datos,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function mostrarTablaFacturas(facturas) {
            let html = '';
            facturas.forEach(factura => {
                html += `
                    <tr>
                        <td><strong>${factura.numero_factura}</strong></td>
                        <td>${factura.fecha}</td>
                        <td>${factura.cliente}</td>
                        <td>${factura.usuario}</td>
                        <td><span class="badge-tipo-pago badge-${factura.tipo_pago}">${factura.tipo_pago.toUpperCase()}</span></td>
                        <td>RD$ ${formatearMoneda(factura.capital)}</td>
                        <td>RD$ ${formatearMoneda(factura.interes)}</td>
                        <td><strong>RD$ ${formatearMoneda(factura.total)}</strong></td>
                        <td><span class="badge-estado badge-${factura.estado}">${factura.estado.toUpperCase()}</span></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-primary" onclick="verDetalle(${factura.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            document.getElementById('tbodyFacturas').innerHTML = html || '<tr><td colspan="11" class="text-center">No hay facturas en el período seleccionado</td></tr>';
        }

        function formatearMoneda(valor) {
            return parseFloat(valor).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function verDetalle(facturaId) {
            window.open(`../Ver_Facturas/ver_factura_consulta.php?id=${facturaId}`, '_blank');
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