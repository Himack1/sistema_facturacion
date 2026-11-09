<?php
/**
 * Ver Detalles del Cliente
 * Sistema de Facturación
 */

session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id <= 0) {
    header("Location: gestion_clientes.php");
    exit();
}

// Obtener datos del cliente
$sql = "SELECT c.*, u.usuario as usuario_registro_nombre
        FROM clientes c
        LEFT JOIN usuarios u ON c.usuario_registro = u.id
        WHERE c.id = ?";

$params = array($id);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false) {
    header("Location: gestion_clientes.php?error=no_encontrado");
    exit();
}

$cliente = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$cliente) {
    header("Location: gestion_clientes.php?error=no_encontrado");
    exit();
}

sqlsrv_free_stmt($stmt);

$fecha_registro = $cliente['fecha_registro']->format('d/m/Y H:i');
$ultima_modificacion = $cliente['ultima_modificacion']->format('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Cliente - <?php echo htmlspecialchars($cliente['nombre']); ?></title>
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

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn-header {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-header:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .cliente-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .cliente-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cliente-header-info h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .cliente-header-info p {
            opacity: 0.9;
            font-size: 14px;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.activo {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .badge.inactivo {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .cliente-body {
            padding: 40px;
        }

        .info-section {
            margin-bottom: 35px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: #667eea;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background-color: #f8f9ff;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 12px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }

        .info-value.empty {
            color: #999;
            font-style: italic;
        }

        .acciones-footer {
            background-color: #f8f9ff;
            padding: 25px 40px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-accion {
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
            transition: all 0.2s;
        }

        .btn-editar {
            background-color: #ff9800;
            color: white;
        }

        .btn-editar:hover {
            background-color: #f57c00;
            transform: translateY(-2px);
        }

        .btn-eliminar {
            background-color: #f44336;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-volver {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-volver:hover {
            background-color: #d0d0d0;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .cliente-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .acciones-footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>👤 Detalles del Cliente</h1>
        <div class="header-actions">
            <a href="gestion_clientes.php" class="btn-header">← Volver</a>
        </div>
    </header>

    <div class="container">
        <div class="cliente-card">
            <div class="cliente-header">
                <div class="cliente-header-info">
                    <h2><?php echo htmlspecialchars($cliente['nombre']); ?></h2>
                    <p><?php echo htmlspecialchars($cliente['codigo']); ?></p>
                </div>
                <div class="badge <?php echo $cliente['estado']; ?>">
                    <?php echo strtoupper($cliente['estado']); ?>
                </div>
            </div>

            <div class="cliente-body">
                <!-- Información Básica -->
                <div class="info-section">
                    <div class="section-title">📋 Información Básica</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Tipo de Documento</div>
                            <div class="info-value"><?php echo htmlspecialchars($cliente['tipo_documento'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Número de Documento</div>
                            <div class="info-value"><?php echo htmlspecialchars($cliente['documento'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="info-section">
                    <div class="section-title">📞 Información de Contacto</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Teléfono</div>
                            <div class="info-value <?php echo empty($cliente['telefono']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['telefono'] ? htmlspecialchars($cliente['telefono']) : 'No registrado'; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Celular</div>
                            <div class="info-value <?php echo empty($cliente['celular']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['celular'] ? htmlspecialchars($cliente['celular']) : 'No registrado'; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Correo Electrónico</div>
                            <div class="info-value <?php echo empty($cliente['email']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['email'] ? htmlspecialchars($cliente['email']) : 'No registrado'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Ubicación -->
                <div class="info-section">
                    <div class="section-title">📍 Información de Ubicación</div>
                    <div class="info-grid">
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="info-label">Dirección</div>
                            <div class="info-value <?php echo empty($cliente['direccion']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['direccion'] ? htmlspecialchars($cliente['direccion']) : 'No registrada'; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ciudad</div>
                            <div class="info-value <?php echo empty($cliente['ciudad']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['ciudad'] ? htmlspecialchars($cliente['ciudad']) : 'No registrada'; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Provincia</div>
                            <div class="info-value <?php echo empty($cliente['provincia']) ? 'empty' : ''; ?>">
                                <?php echo $cliente['provincia'] ? htmlspecialchars($cliente['provincia']) : 'No registrada'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Financiera -->
                <div class="info-section">
                    <div class="section-title">💼 Información Financiera</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Límite de Crédito</div>
                            <div class="info-value">RD$ <?php echo number_format($cliente['limite_credito'], 2); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Crédito Disponible</div>
                            <div class="info-value">RD$ <?php echo number_format($cliente['credito_disponible'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <?php if(!empty($cliente['notas'])): ?>
                <div class="info-section">
                    <div class="section-title">📝 Notas</div>
                    <div class="info-item">
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($cliente['notas'])); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Información del Sistema -->
                <div class="info-section">
                    <div class="section-title">ℹ️ Información del Sistema</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Registrado Por</div>
                            <div class="info-value"><?php echo htmlspecialchars($cliente['usuario_registro_nombre'] ?? 'Sistema'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha de Registro</div>
                            <div class="info-value"><?php echo $fecha_registro; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Última Modificación</div>
                            <div class="info-value"><?php echo $ultima_modificacion; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="acciones-footer">
                <a href="gestion_clientes.php" class="btn-accion btn-volver">
                    ← Volver a la Lista
                </a>
                <a href="editar_clientes.php?id=<?php echo $cliente['id']; ?>" class="btn-accion btn-editar">
                    ✏️ Editar Cliente
                </a>
                <a href="eliminar_clientes.php?id=<?php echo $cliente['id']; ?>" 
                   class="btn-accion btn-eliminar"
                   onclick="return confirm('⚠️ ¿ELIMINAR este cliente?\n\nEsta acción NO se puede deshacer.');">
                    🗑️ Eliminar Cliente
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
sqlsrv_close($conn);
?>