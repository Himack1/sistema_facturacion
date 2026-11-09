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
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
$credito_filtro = isset($_GET['credito']) ? $_GET['credito'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Clientes - Sistema de Facturación</title>
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
            --info-color: #16a085;
        }

        body {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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

        .stat-box small {
            color: #95a5a6;
            font-size: 11px;
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

        .badge-estado {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-activo { background: #27ae60; color: white; }
        .badge-inactivo { background: #95a5a6; color: white; }

        .badge-credito {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-disponible { background: #27ae60; color: white; }
        .badge-limitado { background: #f39c12; color: white; }
        .badge-excedido { background: #e74c3c; color: white; }
        .badge-sin-credito { background: #95a5a6; color: white; }

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

        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #ecf0f1;
        }

        .progress-bar {
            border-radius: 10px;
        }

        /* Colores de iconos */
        .bg-total-clientes { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .bg-activos { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; }
        .bg-con-credito { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; }
        .bg-deuda-total { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; }
        .bg-limite-credito { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; }
        .bg-credito-disponible { background: linear-gradient(135deg, #16a085 0%, #138871 100%); color: white; }

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
                    <i class="bi bi-person-lines-fill"></i>
                    Reporte de Clientes
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
                    <label class="form-label">Fecha Inicio (Compras)</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin (Compras)</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Cliente</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo ($estado_filtro == 'activo') ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo ($estado_filtro == 'inactivo') ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Crédito</label>
                    <select class="form-select" name="credito">
                        <option value="">Todos</option>
                        <option value="con_credito" <?php echo ($credito_filtro == 'con_credito') ? 'selected' : ''; ?>>Con Crédito</option>
                        <option value="sin_credito" <?php echo ($credito_filtro == 'sin_credito') ? 'selected' : ''; ?>>Sin Crédito</option>
                        <option value="credito_excedido" <?php echo ($credito_filtro == 'credito_excedido') ? 'selected' : ''; ?>>Crédito Excedido</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-action btn-primary-custom">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="reporte_clientes.php" class="btn-action btn-back">
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
                    <h5><i class="bi bi-bar-chart"></i> TOP 10 Mejores Clientes</h5>
                    <canvas id="chartTopClientes" height="250"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-pie-chart"></i> Distribución de Crédito</h5>
                    <canvas id="chartDistribucionCredito" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de Clientes -->
        <div class="table-card">
            <h5><i class="bi bi-people-fill"></i> Listado de Clientes</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaClientes">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Límite Crédito</th>
                            <th>Crédito Usado</th>
                            <th>Crédito Disponible</th>
                            <th>Total Comprado</th>
                            <th>Última Compra</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyClientes">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla de Clientes con Crédito Activo -->
        <div class="table-card">
            <h5><i class="bi bi-credit-card"></i> Clientes con Crédito Activo</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaCreditos">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Límite</th>
                            <th>Consumido</th>
                            <th>Disponible</th>
                            <th>% Usado</th>
                            <th>Estado Crédito</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCreditos">
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
        const estadoFiltro = '<?php echo $estado_filtro; ?>';
        const creditoFiltro = '<?php echo $credito_filtro; ?>';

        // Cargar datos al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosReporte();
        });

        function cargarDatosReporte() {
            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                estado: estadoFiltro,
                credito: creditoFiltro
            });

            fetch('ajax/obtener_datos_clientes.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlertas(data.alertas);
                        mostrarEstadisticas(data.estadisticas);
                        mostrarGraficos(data.graficos);
                        mostrarTablaClientes(data.clientes);
                        mostrarTablaCreditos(data.clientes_credito);
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
            
            if (alertas.credito_excedido > 0) {
                html += `
                    <div class="alert-card danger">
                        <h6><i class="bi bi-exclamation-triangle-fill"></i> Clientes con Crédito Excedido</h6>
                        <p>${alertas.credito_excedido} cliente(s) han excedido su límite de crédito. Requiere atención inmediata.</p>
                    </div>
                `;
            }
            
            if (alertas.credito_alto > 0) {
                html += `
                    <div class="alert-card warning">
                        <h6><i class="bi bi-exclamation-circle-fill"></i> Clientes Cerca del Límite</h6>
                        <p>${alertas.credito_alto} cliente(s) han consumido más del 80% de su crédito disponible.</p>
                    </div>
                `;
            }

            // Alerta de Mora (Calculada localmente desde stats si no viene en alertas)
            // Usamos un pequeño truco: si stats.clientes_mora > 0, mostramos alerta
            // Pero mejor esperamos a que el usuario vea los cuadros rojos.
            
            document.getElementById('alertas-container').innerHTML = html;
        }

        function mostrarEstadisticas(stats) {
            const html = `
                <div class="stat-box">
                    <div class="icon bg-total-clientes">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h6>Total Clientes</h6>
                    <h3>${stats.total_clientes}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-activos">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h6>Clientes Activos</h6>
                    <h3>${stats.clientes_activos}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-con-credito">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <h6>Con Crédito Activo</h6>
                    <h3>${stats.clientes_con_credito}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-deuda-total">
                        <i class="bi bi-exclamation-diamond"></i>
                    </div>
                    <h6>Deuda Total</h6>
                    <h3>RD$ ${formatearMoneda(stats.deuda_total)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-limite-credito">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h6>Límite Total Crédito</h6>
                    <h3>RD$ ${formatearMoneda(stats.limite_total_credito)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-deuda-total">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h6>Clientes en Mora</h6>
                    <h3>${stats.clientes_mora}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-deuda-total">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                    <h6>Monto en Mora</h6>
                    <h3>RD$ ${formatearMoneda(stats.monto_mora)}</h3>
                </div>
                <div class="stat-box" style="background: #eef2f3;">
                    <div class="icon bg-con-credito">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h6>Cartera de Intereses</h6>
                    <h3>RD$ ${formatearMoneda(stats.interes_cartera)}</h3>
                    <small>Proyección de ganancia total</small>
                </div>
            `;
            document.getElementById('estadisticas').innerHTML = html;
        }

        function mostrarGraficos(graficos) {
            // Gráfico TOP Clientes
            const ctxTop = document.getElementById('chartTopClientes').getContext('2d');
            new Chart(ctxTop, {
                type: 'bar',
                data: {
                    labels: graficos.top_clientes.labels,
                    datasets: [{
                        label: 'Total Comprado (RD$)',
                        data: graficos.top_clientes.datos,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
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
                        x: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'RD$ ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico Distribución Crédito
            const ctxDist = document.getElementById('chartDistribucionCredito').getContext('2d');
            new Chart(ctxDist, {
                type: 'doughnut',
                data: {
                    labels: graficos.distribucion_credito.labels,
                    datasets: [{
                        data: graficos.distribucion_credito.datos,
                        backgroundColor: ['#27ae60', '#f39c12', '#e74c3c', '#95a5a6']
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

        function mostrarTablaClientes(clientes) {
            let html = '';
            clientes.forEach(cliente => {
                const porcentajeUsado = cliente.limite_credito > 0 ? 
                    (cliente.credito_consumido / cliente.limite_credito) * 100 : 0;
                
                let badgeCredito = '';
                if (cliente.limite_credito === 0) {
                    badgeCredito = '<span class="badge-credito badge-sin-credito">SIN CRÉDITO</span>';
                } else if (porcentajeUsado >= 100) {
                    badgeCredito = '<span class="badge-credito badge-excedido">EXCEDIDO</span>';
                } else if (porcentajeUsado >= 80) {
                    badgeCredito = '<span class="badge-credito badge-limitado">LIMITADO</span>';
                } else {
                    badgeCredito = '<span class="badge-credito badge-disponible">DISPONIBLE</span>';
                }
                
                html += `
                    <tr>
                        <td><strong>${cliente.codigo}</strong></td>
                        <td>${cliente.nombre}</td>
                        <td>${cliente.documento || 'N/A'}</td>
                        <td>${cliente.telefono || cliente.celular || 'N/A'}</td>
                        <td><span class="badge-estado badge-${cliente.estado}">${cliente.estado.toUpperCase()}</span></td>
                        <td>RD$ ${formatearMoneda(cliente.limite_credito)}</td>
                        <td>RD$ ${formatearMoneda(cliente.credito_consumido)}</td>
                        <td>RD$ ${formatearMoneda(cliente.credito_disponible)}</td>
                        <td><strong>RD$ ${formatearMoneda(cliente.total_comprado)}</strong></td>
                        <td>${cliente.ultima_compra || 'Nunca'}</td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-primary" onclick="verDetalleCliente(${cliente.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            document.getElementById('tbodyClientes').innerHTML = html || '<tr><td colspan="11" class="text-center">No hay clientes registrados</td></tr>';
        }

        function mostrarTablaCreditos(clientes) {
            let html = '';
            clientes.forEach(cliente => {
                const porcentajeUsado = (cliente.credito_consumido / cliente.limite_credito) * 100;
                
                let badgeCredito = '';
                let colorBarra = '';
                if (porcentajeUsado >= 100) {
                    badgeCredito = 'badge-excedido';
                    colorBarra = 'bg-danger';
                } else if (porcentajeUsado >= 80) {
                    badgeCredito = 'badge-limitado';
                    colorBarra = 'bg-warning';
                } else {
                    badgeCredito = 'badge-disponible';
                    colorBarra = 'bg-success';
                }
                
                html += `
                    <tr>
                        <td><strong>${cliente.nombre}</strong></td>
                        <td>RD$ ${formatearMoneda(cliente.limite_credito)}</td>
                        <td>RD$ ${formatearMoneda(cliente.credito_consumido)}</td>
                        <td>RD$ ${formatearMoneda(cliente.credito_disponible)}</td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar ${colorBarra}" style="width: ${Math.min(porcentajeUsado, 100)}%">
                                    ${porcentajeUsado.toFixed(1)}%
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-credito ${badgeCredito}">${porcentajeUsado >= 100 ? 'EXCEDIDO' : porcentajeUsado >= 80 ? 'LIMITADO' : 'DISPONIBLE'}</span></td>
                    </tr>
                `;
            });
            document.getElementById('tbodyCreditos').innerHTML = html || '<tr><td colspan="6" class="text-center">No hay clientes con crédito activo</td></tr>';
        }

        function formatearMoneda(valor) {
            return parseFloat(valor).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function verDetalleCliente(id) {
            window.location.href = `../Gestion_Clientes/ver_cliente.php?id=${id}`;
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