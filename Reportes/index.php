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

// Solo ADMIN puede ver reportes completos
if ($rol_usuario !== 'ADMIN') {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Facturación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header-section h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-section h1 i {
            color: var(--secondary-color);
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stats-card .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stats-card h6 {
            color: #7f8c8d;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stats-card h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .report-card .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .report-card .report-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .report-card .report-title i {
            font-size: 32px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .report-card h4 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .report-card p {
            color: #7f8c8d;
            margin: 0;
            font-size: 14px;
        }

        .btn-action {
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: var(--secondary-color);
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
            transform: scale(1.05);
        }

        .btn-download {
            background: var(--success-color);
            color: white;
            margin-left: 10px;
        }

        .btn-download:hover {
            background: #229954;
        }

        .config-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .config-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .config-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .config-option label {
            margin: 0;
            font-weight: 500;
            color: var(--primary-color);
        }

        .form-switch .form-check-input {
            width: 50px;
            height: 25px;
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .back-btn {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-5px);
        }

        /* Colores para iconos */
        .bg-ventas { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .bg-caja { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .bg-clientes { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .bg-inventario { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .bg-config { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="bi bi-bar-chart-line-fill"></i>
                    Reportes y Análisis
                </h1>
                <a href="../dashboard.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>

        <!-- Tarjetas de resumen rápido -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="icon-box bg-ventas">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h6>Ventas Hoy</h6>
                    <h3>RD$ <span id="ventas-hoy">0.00</span></h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="icon-box bg-caja">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <h6>Cajas Abiertas</h6>
                    <h3><span id="cajas-abiertas">0</span></h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="icon-box bg-clientes">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h6>Clientes Activos</h6>
                    <h3><span id="clientes-activos">0</span></h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="icon-box bg-inventario">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h6>Productos Bajo Stock</h6>
                    <h3><span id="productos-bajo-stock">0</span></h3>
                </div>
            </div>
        </div>

        <!-- Reportes Disponibles -->
        <div class="row">
            <!-- Columna Izquierda: Reportes -->
            <div class="col-lg-8">
                <h3 class="text-white mb-3"><i class="bi bi-file-earmark-bar-graph"></i> Reportes Disponibles</h3>

                <!-- Reporte de Ventas -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-title">
                            <i class="bi bi-graph-up-arrow bg-ventas" style="border-radius: 10px; padding: 10px;"></i>
                            <div>
                                <h4>Reporte de Ventas</h4>
                                <p>Análisis detallado de ventas por fecha, cajero y tipo de pago</p>
                            </div>
                        </div>
                        <div>
                            <button class="btn-action btn-view" onclick="abrirReporte('ventas')">
                                <i class="bi bi-eye"></i> Ver Reporte
                            </button>
                            <button class="btn-action btn-download">
                                <i class="bi bi-download"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reporte de Caja -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-title">
                            <i class="bi bi-wallet2 bg-caja" style="border-radius: 10px; padding: 10px;"></i>
                            <div>
                                <h4>Reporte de Caja</h4>
                                <p>Control de aperturas, cierres y diferencias de caja</p>
                            </div>
                        </div>
                        <div>
                            <button class="btn-action btn-view" onclick="abrirReporte('caja')">
                                <i class="bi bi-eye"></i> Ver Reporte
                            </button>
                            <button class="btn-action btn-download">
                                <i class="bi bi-download"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reporte de Clientes -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-title">
                            <i class="bi bi-person-lines-fill bg-clientes" style="border-radius: 10px; padding: 10px;"></i>
                            <div>
                                <h4>Reporte de Clientes</h4>
                                <p>Análisis de créditos, deudas y top clientes</p>
                            </div>
                        </div>
                        <div>
                            <button class="btn-action btn-view" onclick="abrirReporte('clientes')">
                                <i class="bi bi-eye"></i> Ver Reporte
                            </button>
                            <button class="btn-action btn-download">
                                <i class="bi bi-download"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reporte de Inventario -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-title">
                            <i class="bi bi-boxes bg-inventario" style="border-radius: 10px; padding: 10px;"></i>
                            <div>
                                <h4>Reporte de Inventario</h4>
                                <p>Productos más vendidos, stock y movimientos</p>
                            </div>
                        </div>
                        <div>
                            <button class="btn-action btn-view" onclick="abrirReporte('inventario')">
                                <i class="bi bi-eye"></i> Ver Reporte
                            </button>
                            <button class="btn-action btn-download">
                                <i class="bi bi-download"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Configuración -->
            <div class="col-lg-4">
                <h3 class="text-white mb-3"><i class="bi bi-gear-fill"></i> Automatización</h3>
                
                <div class="config-section">
                    <!-- BACKUPS -->
                    <div style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #e2e8f0;">
                        <h4><i class="bi bi-database-check" style="color: #10b981;"></i> Backups de Base de Datos</h4>
                        <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 15px;">
                            Configura copias de seguridad automáticas o realizalas manualmente.
                        </p>
                        <a href="config_backups.php" class="btn w-100" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-size: 13px; font-weight: 600;">
                            <i class="bi bi-gear"></i> Configurar Backups
                        </a>
                    </div>

                    <!-- TELEGRAM -->
                    <h4><i class="bi bi-telegram" style="color: #0088cc;"></i> Notificaciones Telegram</h4>
                    <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 15px;">
                        Recibe alertas en tu celular al instante. 
                        <a href="https://t.me/BotFather" target="_blank" style="color: #0088cc;">Crear bot aquí</a>
                    </p>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Bot Token</label>
                        <input type="text" class="form-control form-control-sm" id="telegram-token" 
                               placeholder="123456:ABC-DEF1234ghIkl..." style="font-size: 13px;">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Chat ID</label>
                        <input type="text" class="form-control form-control-sm" id="telegram-chatid" 
                               placeholder="Ej: 123456789" style="font-size: 13px;">
                    </div>
                    <button class="btn btn-sm w-100 mb-3" id="btnTestTelegram" onclick="testTelegram()" 
                            style="background: #0088cc; color: white; font-weight: 600;">
                        <i class="bi bi-send"></i> Probar Conexión
                    </button>
                    <div id="telegramTestResult" style="display: none; font-size: 13px; padding: 8px; border-radius: 6px; margin-bottom: 15px;"></div>

                    <hr>

                    <!-- EVENTOS A NOTIFICAR -->
                    <h4 class="mt-3"><i class="bi bi-bell"></i> Eventos a Notificar</h4>
                    
                    <div class="config-option">
                        <label>🧾 Notificar cada venta</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-cierre-caja">
                        </div>
                    </div>

                    <div class="config-option">
                        <label>🔒 Resumen al cerrar caja</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-diario">
                        </div>
                    </div>

                    <div class="config-option">
                        <label>📦 Alerta de stock bajo</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="alerta-stock">
                        </div>
                    </div>

                    <div class="config-option">
                        <label>⚠️ Diferencias de caja</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="alerta-diferencias">
                        </div>
                    </div>

                    <div class="config-option">
                        <label>📋 Créditos vencidos</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="alerta-credito">
                        </div>
                    </div>

                    <hr>

                    <!-- GOOGLE DRIVE -->
                    <h4 class="mt-3"><i class="bi bi-cloud-upload" style="color: #4285f4;"></i> Respaldo Google Drive</h4>
                    <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 15px;">
                        Los CSV de cierre de caja se exportan a esta carpeta. Si usas Google Drive para Escritorio, pon la ruta de la carpeta sincronizada.
                    </p>
                    
                    <div class="config-option">
                        <label>Activar respaldo automático</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-drive">
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Carpeta de Respaldo</label>
                        <input type="text" class="form-control form-control-sm" id="drive-folder" 
                               placeholder="Ej: G:\Mi unidad\Backups Facturacion" style="font-size: 13px;">
                        <small style="color: #999;">Dejar vacío usa la carpeta por defecto del sistema.</small>
                    </div>

                    <div class="config-option" style="display: none;">
                        <label>Reporte semanal</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-semanal">
                        </div>
                    </div>

                    <hr>

                    <button class="btn btn-primary w-100 mt-3" onclick="guardarConfiguracion()" style="font-weight: 600;">
                        <i class="bi bi-save"></i> Guardar Configuración
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar estadísticas al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarEstadisticas();
            cargarConfiguracion();
        });

        // Función para cargar estadísticas rápidas
        function cargarEstadisticas() {
            fetch('ajax/obtener_estadisticas.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('ventas-hoy').textContent = formatearMoneda(data.ventas_hoy);
                        document.getElementById('cajas-abiertas').textContent = data.cajas_abiertas;
                        document.getElementById('clientes-activos').textContent = data.clientes_activos;
                        document.getElementById('productos-bajo-stock').textContent = data.productos_bajo_stock;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Función para cargar configuración guardada
        function cargarConfiguracion() {
            fetch('ajax/obtener_configuracion.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('auto-cierre-caja').checked = data.config.auto_cierre_caja == 1;
                        document.getElementById('auto-diario').checked = data.config.auto_diario == 1;
                        document.getElementById('auto-semanal').checked = data.config.auto_semanal == 1;
                        document.getElementById('auto-drive').checked = data.config.auto_drive == 1;
                        document.getElementById('alerta-diferencias').checked = data.config.alerta_diferencias == 1;
                        document.getElementById('alerta-credito').checked = data.config.alerta_credito == 1;
                        document.getElementById('alerta-stock').checked = data.config.alerta_stock == 1;
                        // Nuevos campos
                        document.getElementById('telegram-token').value = data.config.telegram_bot_token || '';
                        document.getElementById('telegram-chatid').value = data.config.telegram_chat_id || '';
                        document.getElementById('drive-folder').value = data.config.drive_folder_path || '';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Función para guardar configuración
        function guardarConfiguracion() {
            const config = {
                auto_cierre_caja: document.getElementById('auto-cierre-caja').checked ? 1 : 0,
                auto_diario: document.getElementById('auto-diario').checked ? 1 : 0,
                auto_semanal: document.getElementById('auto-semanal').checked ? 1 : 0,
                auto_drive: document.getElementById('auto-drive').checked ? 1 : 0,
                alerta_diferencias: document.getElementById('alerta-diferencias').checked ? 1 : 0,
                alerta_credito: document.getElementById('alerta-credito').checked ? 1 : 0,
                alerta_stock: document.getElementById('alerta-stock').checked ? 1 : 0,
                telegram_bot_token: document.getElementById('telegram-token').value.trim(),
                telegram_chat_id: document.getElementById('telegram-chatid').value.trim(),
                drive_folder_path: document.getElementById('drive-folder').value.trim()
            };

            fetch('ajax/guardar_configuracion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(config)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Configuración guardada exitosamente');
                } else {
                    alert('❌ Error al guardar: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error de conexión');
            });
        }

        // Probar conexión con Telegram
        function testTelegram() {
            const token = document.getElementById('telegram-token').value.trim();
            const chatId = document.getElementById('telegram-chatid').value.trim();
            const resultDiv = document.getElementById('telegramTestResult');
            const btn = document.getElementById('btnTestTelegram');

            if (!token || !chatId) {
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#fff3cd';
                resultDiv.style.color = '#856404';
                resultDiv.textContent = '⚠️ Ingresa el Bot Token y el Chat ID primero.';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

            fetch('ajax/test_telegram.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token, chat_id: chatId })
            })
            .then(r => r.json())
            .then(data => {
                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.style.background = '#d1e7dd';
                    resultDiv.style.color = '#0f5132';
                } else {
                    resultDiv.style.background = '#f8d7da';
                    resultDiv.style.color = '#842029';
                }
                resultDiv.textContent = data.message;
            })
            .catch(() => {
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#842029';
                resultDiv.textContent = '❌ Error de conexión al servidor.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send"></i> Probar Conexión';
            });
        }

        // Función para abrir reportes
        function abrirReporte(tipo) {
            window.location.href = `reporte_${tipo}.php`;
        }

        // Función para formatear moneda
        function formatearMoneda(valor) {
            return parseFloat(valor).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    </script>
</body>
</html>