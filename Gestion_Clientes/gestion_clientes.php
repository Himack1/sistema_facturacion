<?php
/**
 * Gestión de Clientes
 * Sistema de Facturación
 */

session_start();

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

$usuario_nombre = $_SESSION['usuario_nombre'];
$usuario_rol = $_SESSION['usuario_rol'];

// Obtener parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Construir consulta con filtros
$sql = "SELECT c.*, u.usuario as usuario_registro_nombre
        FROM clientes c
        LEFT JOIN usuarios u ON c.usuario_registro = u.id
        WHERE 1=1";

$params = array();

if(!empty($buscar)) {
    $sql .= " AND (c.codigo LIKE ? OR c.nombre LIKE ? OR c.documento LIKE ? OR c.telefono LIKE ? OR c.email LIKE ?)";
    $buscar_param = '%' . $buscar . '%';
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
}

if(!empty($filtro_estado)) {
    $sql .= " AND c.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY c.fecha_registro DESC";

if(!empty($params)) {
    $stmt = sqlsrv_query($conn, $sql, $params);
} else {
    $stmt = sqlsrv_query($conn, $sql);
}

if($stmt === false) {
    die("Error al obtener clientes: " . print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Sistema de Facturación</title>
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
            transition: background-color 0.3s;
        }

        .btn-dashboard:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

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

        .btn-nuevo {
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
            transition: transform 0.2s;
        }

        .btn-nuevo:hover {
            transform: translateY(-2px);
        }

        .filtros {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filtro-grupo {
            flex: 1;
            min-width: 250px;
        }

        .filtro-grupo label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 13px;
            font-weight: 600;
        }

        .filtro-grupo input,
        .filtro-grupo select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .filtro-grupo input:focus,
        .filtro-grupo select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-filtrar {
            background-color: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            align-self: flex-end;
        }

        .btn-filtrar:hover {
            background-color: #5568d3;
        }

        .btn-limpiar {
            background-color: #e0e0e0;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            align-self: flex-end;
            text-decoration: none;
            display: inline-block;
        }

        .btn-limpiar:hover {
            background-color: #d0d0d0;
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
            transition: background-color 0.2s;
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

        .badge.activo {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge.inactivo {
            background-color: #ffebee;
            color: #c62828;
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
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-ver {
            background-color: #2196f3;
            color: white;
        }

        .btn-ver:hover {
            background-color: #1976d2;
        }

        .btn-editar {
            background-color: #ff9800;
            color: white;
        }

        .btn-editar:hover {
            background-color: #f57c00;
        }

        .btn-eliminar {
            background-color: #f44336;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #d32f2f;
        }

        .tabla-vacia {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .tabla-vacia-icono {
            font-size: 60px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .top-bar-header {
                flex-direction: column;
                gap: 15px;
            }

            .tabla-container {
                overflow-x: auto;
            }

            table {
                min-width: 1000px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }

            .filtros {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>👥 Gestión de Clientes</h1>
        <div class="header-right">
            <span><?php echo htmlspecialchars($usuario_nombre); ?> (<?php echo $usuario_rol; ?>)</span>
            <a href="../dashboard.php" class="btn-dashboard">← Dashboard</a>
        </div>
    </header>

    <div class="container">
        <?php if(isset($_GET['success'])): ?>
            <div class="mensaje exito">
                <span>✓</span>
                <span>
                    <?php
                    switch($_GET['success']) {
                        case 'creado':
                            echo 'Cliente registrado exitosamente.';
                            break;
                        case 'editado':
                            echo 'Cliente actualizado exitosamente.';
                            break;
                        case 'eliminado':
                            echo 'Cliente eliminado exitosamente.';
                            break;
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="mensaje error">
                <span>✕</span>
                <span>Ha ocurrido un error. Intenta nuevamente.</span>
            </div>
        <?php endif; ?>

        <div class="top-bar">
            <div class="top-bar-header">
                <h2>Lista de Clientes</h2>
                <a href="crear_clientes.php" class="btn-nuevo">
                    <span>+</span>
                    <span>Nuevo Cliente</span>
                </a>
            </div>

            <form method="GET" action="" class="filtros">
                <div class="filtro-grupo">
                    <label>Buscar</label>
                    <input type="text" name="buscar" placeholder="Código, nombre, documento, teléfono..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>

                <div class="filtro-grupo" style="max-width: 200px;">
                    <label>Estado</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <button type="submit" class="btn-filtrar">Buscar</button>
                <a href="gestion_clientes.php" class="btn-limpiar">Limpiar</a>
            </form>
        </div>

        <div class="tabla-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hay_clientes = false;
                    
                    while($cliente = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                        $hay_clientes = true;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($cliente['codigo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($cliente['tipo_documento'] ?? 'N/A'); ?>: 
                            <?php echo htmlspecialchars($cliente['documento'] ?? 'N/A'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($cliente['telefono'] ?: $cliente['celular'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cliente['ciudad'] ?? '-'); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($cliente['estado']); ?>">
                                <?php echo $cliente['estado']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="acciones">
                                <a href="ver_cliente.php?id=<?php echo $cliente['id']; ?>" 
                                   class="btn-accion btn-ver" title="Ver detalles">
                                    👁️
                                </a>
                                <a href="editar_clientes.php?id=<?php echo $cliente['id']; ?>" 
                                   class="btn-accion btn-editar" title="Editar">
                                    ✏️
                                </a>
                                <a href="eliminar_clientes.php?id=<?php echo $cliente['id']; ?>" 
                                   class="btn-accion btn-eliminar"
                                   onclick="return confirm('⚠️ ¿ELIMINAR este cliente?\n\nEsta acción NO se puede deshacer.');"
                                   title="Eliminar">
                                    🗑️
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if(!$hay_clientes): ?>
                    <tr>
                        <td colspan="8">
                            <div class="tabla-vacia">
                                <div class="tabla-vacia-icono">📋</div>
                                <p><?php echo !empty($buscar) ? 'No se encontraron clientes con esos criterios' : 'No hay clientes registrados'; ?></p>
                            </div>
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
sqlsrv_close($conn);
?>