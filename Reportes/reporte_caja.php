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
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';

// Consulta para obtener usuarios
$sql_usuarios = "SELECT id, usuario, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre";
$stmt_usuarios = sqlsrv_query($conn, $sql_usuarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Caja - Sistema de Facturación</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .badge-abierta { background: #27ae60; color: white; }
        .badge-cerrada { background: #95a5a6; color: white; }
        .badge-revision { background: #f39c12; color: white; }

        .badge-diferencia {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-sin-diferencia { background: #27ae60; color: white; }
        .badge-sobrante { background: #3498db; color: white; }
        .badge-faltante { background: #e74c3c; color: white; }

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

        .detalle-movimientos {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .detalle-movimientos h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .movimiento-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .movimiento-item:last-child {
            border-bottom: none;
        }

        .movimiento-tipo {
            font-weight: 500;
            font-size: 13px;
        }

        .movimiento-monto {
            font-weight: 600;
        }

        .movimiento-ingreso { color: var(--success-color); }
        .movimiento-egreso { color: var(--danger-color); }

        /* Colores de iconos */
        .bg-efectivo { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; }
        .bg-transferencia { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; }
        .bg-credito { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; }
        .bg-diferencias { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; }
        .bg-cajas { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; }
        .bg-promedio { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); color: white; }

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
                    <i class="bi bi-wallet2"></i>
                    Reporte de Caja
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
                        <?php 
                        sqlsrv_fetch_array($stmt_usuarios, SQLSRV_FETCH_ASSOC); // Reset
                        $stmt_usuarios = sqlsrv_query($conn, $sql_usuarios);
                        while ($usuario = sqlsrv_fetch_array($stmt_usuarios, SQLSRV_FETCH_ASSOC)): 
                        ?>
                            <option value="<?php echo $usuario['id']; ?>" 
                                <?php echo ($usuario_filtro == $usuario['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado de Caja</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="abierta" <?php echo ($estado_filtro == 'abierta') ? 'selected' : ''; ?>>Abierta</option>
                        <option value="cerrada" <?php echo ($estado_filtro == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option>
                        <option value="revision" <?php echo ($estado_filtro == 'revision') ? 'selected' : ''; ?>>En Revisión</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-action btn-primary-custom">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="reporte_caja.php" class="btn-action btn-back">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Alertas de Diferencias -->
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
                    <h5><i class="bi bi-pie-chart"></i> Ventas por Tipo de Pago</h5>
                    <canvas id="chartTipoPago" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="chart-card">
                    <h5><i class="bi bi-bar-chart"></i> Diferencias por Caja</h5>
                    <canvas id="chartDiferencias" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de Cajas -->
        <div class="table-card">
            <h5><i class="bi bi-inbox-fill"></i> Registro de Cajas</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaCajas">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Fecha Apertura</th>
                            <th>Fecha Cierre</th>
                            <th>Monto Inicial</th>
                            <th>Total Ventas</th>
                            <th>Esperado</th>
                            <th>Real</th>
                            <th>Diferencia</th>
                            <th>Estado</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCajas">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla de Movimientos Manuales -->
        <div class="table-card">
            <h5><i class="bi bi-arrow-left-right"></i> Movimientos Manuales de Caja</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaMovimientos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Caja ID</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMovimientos">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Detalle de Caja -->
    <div class="modal fade" id="modalDetalleCaja" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle"></i> Detalle de Caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="contenidoDetalleCaja">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables de filtros
        const fechaInicio = '<?php echo $fecha_inicio; ?>';
        const fechaFin = '<?php echo $fecha_fin; ?>';
        const usuarioFiltro = '<?php echo $usuario_filtro; ?>';
        const estadoFiltro = '<?php echo $estado_filtro; ?>';

        // Cargar datos al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosReporte();
        });

        function cargarDatosReporte() {
            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                usuario: usuarioFiltro,
                estado: estadoFiltro
            });

            // Ruta simple relativa
            fetch('ajax/obtener_datos_caja.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlertas(data.alertas);
                        mostrarEstadisticas(data.estadisticas);
                        mostrarGraficos(data.graficos);
                        mostrarTablaCajas(data.cajas);
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
            
            if (alertas.diferencias_importantes > 0) {
                html += `
                    <div class="alert-card danger">
                        <h6><i class="bi bi-exclamation-triangle-fill"></i> Atención: Diferencias Importantes</h6>
                        <p>Se detectaron ${alertas.diferencias_importantes} caja(s) con diferencias significativas que requieren revisión.</p>
                    </div>
                `;
            }
            
            if (alertas.cajas_abiertas > 0) {
                html += `
                    <div class="alert-card warning">
                        <h6><i class="bi bi-info-circle-fill"></i> Cajas Abiertas</h6>
                        <p>Actualmente hay ${alertas.cajas_abiertas} caja(s) abierta(s) sin cerrar.</p>
                    </div>
                `;
            }
            
            document.getElementById('alertas-container').innerHTML = html;
        }

        function mostrarEstadisticas(stats) {
            const html = `
                <div class="stat-box">
                    <div class="icon bg-efectivo">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h6>Total Efectivo</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_efectivo)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-transferencia">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <h6>Total Transferencias</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_transferencia)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-credito">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h6>Total Crédito</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_credito)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-diferencias">
                        <i class="bi bi-exclamation-diamond"></i>
                    </div>
                    <h6>Diferencias Totales</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_diferencias)}</h3>
                    <small>${stats.diferencias_positivas} positivas / ${stats.diferencias_negativas} negativas</small>
                </div>
                <div class="stat-box">
                    <div class="icon bg-cajas">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <h6>Abonos a Crédito</h6>
                    <h3>RD$ ${formatearMoneda(stats.total_abonos_credito)}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-cajas">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <h6>Cajas Cerradas</h6>
                    <h3>${stats.cajas_cerradas}</h3>
                </div>
                <div class="stat-box">
                    <div class="icon bg-promedio">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h6>Promedio por Caja</h6>
                    <h3>RD$ ${formatearMoneda(stats.promedio_caja)}</h3>
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

            // Gráfico de Diferencias
            const ctxDiferencias = document.getElementById('chartDiferencias').getContext('2d');
            new Chart(ctxDiferencias, {
                type: 'bar',
                data: {
                    labels: graficos.diferencias.labels,
                    datasets: [{
                        label: 'Diferencia (RD$)',
                        data: graficos.diferencias.datos,
                        backgroundColor: graficos.diferencias.colores
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
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
        }

        function mostrarTablaCajas(cajas) {
            let html = '';
            cajas.forEach(caja => {
                const diferencia = parseFloat(caja.diferencia);
                let badgeDiferencia = 'sin-diferencia';
                let textoDiferencia = 'Sin diferencia';
                
                if (diferencia > 0) {
                    badgeDiferencia = 'sobrante';
                    textoDiferencia = '+' + formatearMoneda(diferencia);
                } else if (diferencia < 0) {
                    badgeDiferencia = 'faltante';
                    textoDiferencia = formatearMoneda(diferencia);
                } else {
                    textoDiferencia = '0.00';
                }
                
                html += `
                    <tr>
                        <td><strong>#${caja.id}</strong></td>
                        <td>${caja.usuario}</td>
                        <td>${caja.fecha_apertura}</td>
                        <td>${caja.fecha_cierre || '<span class="text-muted">-</span>'}</td>
                        <td>RD$ ${formatearMoneda(caja.monto_inicial)}</td>
                        <td>RD$ ${formatearMoneda(caja.total_ventas)}</td>
                        <td>RD$ ${formatearMoneda(caja.monto_final_esperado)}</td>
                        <td>RD$ ${formatearMoneda(caja.monto_final_real)}</td>
                        <td><span class="badge-diferencia badge-${badgeDiferencia}">${textoDiferencia}</span></td>
                        <td><span class="badge-estado badge-${caja.estado}">${caja.estado.toUpperCase()}</span></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-primary" onclick="verDetalleCaja(${caja.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            document.getElementById('tbodyCajas').innerHTML = html || '<tr><td colspan="11" class="text-center">No hay registros de caja en el período seleccionado</td></tr>';
        }

        function mostrarTablaMovimientos(movimientos) {
            let html = '';
            movimientos.forEach(mov => {
                const claseMonto = mov.tipo === 'ingreso' ? 'movimiento-ingreso' : 'movimiento-egreso';
                const signo = mov.tipo === 'ingreso' ? '+' : '-';
                
                html += `
                    <tr>
                        <td>${mov.fecha}</td>
                        <td>#${mov.caja_id}</td>
                        <td>${mov.usuario}</td>
                        <td><span class="badge badge-${mov.tipo === 'ingreso' ? 'success' : 'danger'}">${mov.tipo.toUpperCase()}</span></td>
                        <td>${mov.concepto}</td>
                        <td class="${claseMonto}"><strong>${signo} RD$ ${formatearMoneda(mov.monto)}</strong></td>
                    </tr>
                `;
            });
            document.getElementById('tbodyMovimientos').innerHTML = html || '<tr><td colspan="6" class="text-center">No hay movimientos manuales registrados</td></tr>';
        }

        function verDetalleCaja(cajaId) {
            fetch('ajax/obtener_detalle_caja.php?id=' + cajaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarModalDetalle(data.caja, data.facturas, data.movimientos, data.pagos_credito);
                    } else {
                        alert('Error al cargar detalle: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión');
                });
        }

        function mostrarModalDetalle(caja, facturas, movimientos, pagos_credito) {
            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Usuario:</strong> ${caja.usuario}<br>
                        <strong>Fecha Apertura:</strong> ${caja.fecha_apertura}<br>
                        <strong>Fecha Cierre:</strong> ${caja.fecha_cierre || 'Aún abierta'}
                    </div>
                    <div class="col-md-6">
                        <strong>Monto Inicial:</strong> RD$ ${formatearMoneda(caja.monto_inicial)}<br>
                        <strong>Monto Esperado:</strong> RD$ ${formatearMoneda(caja.monto_final_esperado)}<br>
                        <strong>Monto Real:</strong> RD$ ${formatearMoneda(caja.monto_final_real)}
                    </div>
                </div>
                
                <div class="detalle-movimientos">
                    <h6><i class="bi bi-receipt"></i> Resumen de Ventas</h6>
                    <div class="movimiento-item">
                        <span>Total Efectivo:</span>
                        <span class="movimiento-monto movimiento-ingreso">RD$ ${formatearMoneda(caja.total_ventas_efectivo)}</span>
                    </div>
                    <div class="movimiento-item">
                        <span>Total Transferencia:</span>
                        <span class="movimiento-monto movimiento-ingreso">RD$ ${formatearMoneda(caja.total_ventas_transferencia)}</span>
                    </div>
                    <div class="movimiento-item">
                        <span>Total Crédito:</span>
                        <span class="movimiento-monto movimiento-ingreso">RD$ ${formatearMoneda(caja.total_ventas_credito)}</span>
                    </div>
                    <div class="movimiento-item">
                        <span>Abonos a Crédito (Cobros):</span>
                        <span class="movimiento-monto movimiento-ingreso">RD$ ${formatearMoneda(caja.total_abonos_credito)}</span>
                    </div>
                </div>

                ${pagos_credito.length > 0 ? `
                <div class="detalle-movimientos mt-3">
                    <h6><i class="bi bi-piggy-bank"></i> Detalle de Cobros a Crédito</h6>
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                            <tr class="text-muted" style="font-size: 11px;">
                                <th>Cliente</th>
                                <th>Factura/Ref</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${pagos_credito.map(p => `
                                <tr style="font-size: 12px; border-bottom: 1px solid #eee;">
                                    <td>${p.cliente}</td>
                                    <td>${p.factura} ${p.es_cuota ? '(Cuota)' : ''}</td>
                                    <td class="text-end fw-bold">RD$ ${formatearMoneda(p.monto)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ` : ''}
                
                ${movimientos.length > 0 ? `
                <div class="detalle-movimientos mt-3">
                    <h6><i class="bi bi-arrow-left-right"></i> Movimientos Manuales</h6>
                    ${movimientos.map(mov => `
                        <div class="movimiento-item">
                            <span class="movimiento-tipo">${mov.concepto}</span>
                            <span class="movimiento-monto movimiento-${mov.tipo}">${mov.tipo === 'ingreso' ? '+' : '-'} RD$ ${formatearMoneda(mov.monto)}</span>
                        </div>
                    `).join('')}
                </div>
                ` : ''}
                
                <div class="table-responsive mt-3">
                    <h6><i class="bi bi-list-ul"></i> Facturas de esta Caja (${facturas.length})</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nº Factura</th>
                                <th>Cliente</th>
                                <th>Tipo Pago</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${facturas.map(f => `
                                <tr>
                                    <td>${f.numero_factura}</td>
                                    <td>${f.cliente}</td>
                                    <td>${f.tipo_pago}</td>
                                    <td>RD$ ${formatearMoneda(f.total)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('contenidoDetalleCaja').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalleCaja')).show();
        }

        function formatearMoneda(valor) {
            return parseFloat(valor).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
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