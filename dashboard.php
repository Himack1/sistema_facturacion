<?php
/**
 * Dashboard Principal
 * Sistema de Facturación
 */

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Conexión y verificación de licencia
require_once 'conexion.php';
require_once 'includes/backup_helper.php';

// Intentar ejecutar backup automático (silencioso)
try {
    verificar_y_ejecutar_backup($conn);
} catch (Exception $e) {
    // Ignorar errores para no afectar el inicio del dashboard
}

// Obtener datos del usuario de la sesión
$usuario_nombre = $_SESSION['usuario_nombre'];
$usuario_usuario = $_SESSION['usuario_usuario'];
$usuario_rol = $_SESSION['usuario_rol'];

// Verificar si es un registro nuevo
$es_nuevo_registro = isset($_GET['registro']) && $_GET['registro'] == 'nuevo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Facturación</title>
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

        /* Header */
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-details {
            text-align: right;
        }

        .user-details .name {
            font-weight: 600;
            font-size: 16px;
        }

        .user-details .role {
            font-size: 13px;
            opacity: 0.9;
        }

        .role-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-logout {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .btn-config {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-config:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-message {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .welcome-message h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .welcome-message p {
            color: #666;
            line-height: 1.6;
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }

        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .card p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .card-link {
            display: inline-block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .card-link:hover {
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 14px;
            margin-top: 50px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .user-info {
                flex-direction: column;
            }

            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <h1>🧾 Sistema de Facturación</h1>
        <div class="user-info">
            <div class="user-details">
                <div class="name"><?php echo htmlspecialchars($usuario_nombre); ?></div>
                <div class="role">
                    <span class="role-badge"><?php echo htmlspecialchars($usuario_rol); ?></span>
                </div>
            </div>
            <?php if($usuario_rol === 'ADMIN'): ?>
            <a href="configuracion_empresa.php?master=1" class="btn-config" title="Configuración de la Empresa">
                ⚙️ Empresa
            </a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout" onclick="return confirm('¿Estás seguro que deseas cerrar sesión?');">
                Cerrar Sesión
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <?php if($es_nuevo_registro): ?>
        <div class="success-message">
            <strong>¡Bienvenido!</strong> Tu cuenta ha sido creada exitosamente. Ya puedes usar el sistema.
        </div>
        <?php endif; ?>

        <div class="welcome-message">
            <h2>¡Bienvenido, <?php echo htmlspecialchars($usuario_nombre); ?>! 👋</h2>
            <p>Has iniciado sesión correctamente en el Sistema de Facturación. Desde aquí puedes acceder a todas las funcionalidades del sistema según tu rol de <strong><?php echo htmlspecialchars($usuario_rol); ?></strong>.</p>
        </div>

        <?php
        // NOTIFICACIONES DE COBRO
        require_once 'includes/notificaciones_helper.php';
        $resumen = obtenerResumenCobros($conn);
        $total_alertas = count($resumen['vencidas']) + count($resumen['hoy']) + count($resumen['manana']);
        ?>

        <?php if ($total_alertas > 0): ?>
        <div style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">🔔 Alertas de Cobro Pendientes</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                
                <!-- ALERTA: VENCIDAS (ROJO) -->
                <?php if (!empty($resumen['vencidas'])): ?>
                <div style="background: #fff5f5; border-left: 5px solid #f56565; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <div style="color: #c53030; font-weight: bold; margin-bottom: 5px;">⚠️ CUOTAS VENCIDAS (<?php echo count($resumen['vencidas']); ?>)</div>
                    <ul style="font-size: 13px; color: #742a2a; list-style: none; padding-left: 5px;">
                        <?php foreach (array_slice($resumen['vencidas'], 0, 3) as $c): ?>
                        <li>• <?php echo $c['cliente_nombre']; ?> (Fact: <?php echo $c['numero_factura']; ?>)</li>
                        <?php endforeach; ?>
                        <?php if (count($resumen['vencidas']) > 3): ?>
                        <li style="font-style: italic;">... y <?php echo count($resumen['vencidas']) - 3; ?> más</li>
                        <?php endif; ?>
                    </ul>
                    <a href="Gestion_Credito/index.php" style="font-size: 12px; color: #f56565; font-weight: bold; text-decoration: none; display: block; margin-top: 10px;">Ver todos los morosos →</a>
                </div>
                <?php endif; ?>

                <!-- ALERTA: HOY (AMARILLO) -->
                <?php if (!empty($resumen['hoy'])): ?>
                <div style="background: #fffbeb; border-left: 5px solid #d97706; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <div style="color: #92400e; font-weight: bold; margin-bottom: 5px;">📍 COBROS PARA HOY (<?php echo count($resumen['hoy']); ?>)</div>
                    <ul style="font-size: 13px; color: #78350f; list-style: none; padding-left: 5px;">
                        <?php foreach (array_slice($resumen['hoy'], 0, 3) as $c): ?>
                        <li>• <?php echo $c['cliente_nombre']; ?> - RD$ <?php echo number_format($c['monto_cuota'], 2); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($resumen['hoy']) > 3): ?>
                        <li style="font-style: italic;">... y <?php echo count($resumen['hoy']) - 3; ?> más</li>
                        <?php endif; ?>
                    </ul>
                    <a href="Gestion_Credito/index.php" style="font-size: 12px; color: #d97706; font-weight: bold; text-decoration: none; display: block; margin-top: 10px;">Gestionar cobros de hoy →</a>
                </div>
                <?php endif; ?>

                <!-- ALERTA: MAÑANA (AZUL) -->
                <?php if (!empty($resumen['manana'])): ?>
                <div style="background: #ebf8ff; border-left: 5px solid #3182ce; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <div style="color: #2b6cb0; font-weight: bold; margin-bottom: 5px;">⏳ VENCEN MAÑANA (<?php echo count($resumen['manana']); ?>)</div>
                    <ul style="font-size: 13px; color: #2c5282; list-style: none; padding-left: 5px;">
                        <?php foreach (array_slice($resumen['manana'], 0, 3) as $c): ?>
                        <li>• <?php echo $c['cliente_nombre']; ?> (Mañana)</li>
                        <?php endforeach; ?>
                        <?php if (count($resumen['manana']) > 3): ?>
                        <li style="font-style: italic;">... y <?php echo count($resumen['manana']) - 3; ?> más</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

        <div class="cards-grid">
            <!-- Card 1: Nueva Factura -->
            <div class="card">
                <div class="card-icon">📄</div>
                <h3>Nueva Factura</h3>
                <p>Crea y genera facturas para tus clientes de forma rápida y sencilla.</p>
                <a href="Facturacion/nueva_factura.php" class="card-link">Crear factura →</a>
            </div>

            <!-- Card 2: Consultar Facturas -->
            <div class="card">
                <div class="card-icon">🔍</div>
                <h3>Consultar Facturas</h3>
                <p>Busca y visualiza el historial completo de facturas emitidas.</p>
                <a href="Ver_Facturas/consultar_facturas.php" class="card-link">Ver facturas →</a>
            </div>

            <!-- Card 3: Clientes -->
            <div class="card">
                <div class="card-icon">👥</div>
                <h3>Gestión de Clientes</h3>
                <p>Administra la información de tus clientes y sus datos de contacto.</p>
                <a href="Gestion_Clientes/gestion_clientes.php" class="card-link">Ver clientes →</a>
            </div>

            <!-- Card: Gestión de Créditos -->
            <div class="card" style="border: 2px solid #e2e8f0; border-top: 4px solid #4f46e5;">
                <div class="card-icon">💳</div>
                <h3 style="color: #4f46e5;">Gestión de Créditos</h3>
                <p>Administra cuentas por cobrar, facturas a crédito y abonos de clientes.</p>
                <a href="Gestion_Credito/index.php" class="card-link" style="color: #4f46e5;">Ver créditos →</a>
            </div>

            <!-- Card 4: Productos -->
            <div class="card">
                <div class="card-icon">📦</div>
                <h3>Inventario</h3>
                <p>Controla tu inventario de productos y servicios disponibles.</p>
                <a href="Gestion_Inventario/gestion_inventario.php" class="card-link">Ver inventario →</a>
            </div>

            <!-- Card: Servicio Técnico -->
            <div class="card" style="border: 2px solid #e2e8f0; border-top: 4px solid #f59e0b;">
                <div class="card-icon">🔧</div>
                <h3 style="color: #d97706;">Servicio Técnico</h3>
                <p>Registra y administra equipos dejados para reparación en la tienda.</p>
                <a href="Reparaciones/index.php" class="card-link" style="color: #d97706;">Ver reparaciones →</a>
            </div>

            <!-- Card 5: Reportes (solo ADMIN) -->
            <?php if($usuario_rol === 'ADMIN'): ?>
            <div class="card">
                <div class="card-icon">📊</div>
                <h3>Reportes</h3>
                <p>Genera reportes y estadísticas de ventas y facturación.</p>
                <a href="Reportes/index.php" class="card-link">Ver reportes →</a>
            </div>

            <!-- Card 6: Usuarios (solo ADMIN) -->
            <div class="card">
                <div class="card-icon">⚙️</div>
                <h3>Gestión de Usuarios</h3>
                <p>Administra los usuarios del sistema y sus permisos.</p>
                <a href="administracion_usuarios/gestion_usuarios.php" class="card-link">Administrar →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        Sistema de Facturación &copy; <?php echo date('Y'); ?> - Todos los derechos reservados
    </footer>

    <script>
        // Mostrar tiempo de sesión (opcional)
        console.log('Sesión iniciada correctamente');
        console.log('Usuario: <?php echo $usuario_usuario; ?>');
        console.log('Rol: <?php echo $usuario_rol; ?>');

        // Ocultar mensaje de éxito después de 5 segundos
        setTimeout(function() {
            const successMsg = document.querySelector('.success-message');
            if(successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(function() {
                    successMsg.remove();
                }, 500);
            }
        }, 5000);
    </script>
</body>
</html>