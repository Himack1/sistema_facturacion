<?php
/**
 * Gestión de Usuarios
 * Sistema de Facturación - Solo ADMIN
 */

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar que sea ADMIN
if($_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: dashboard.php?error=no_autorizado");
    exit();
}

// Incluir conexión
require_once '../conexion.php';

// Obtener datos del usuario logueado
$admin_nombre = $_SESSION['usuario_nombre'];
$admin_usuario = $_SESSION['usuario_usuario'];

// Obtener todos los usuarios
$sql = "SELECT id, nombre, usuario, rol, estado, fecha_creacion 
        FROM usuarios 
        ORDER BY fecha_creacion DESC";

$stmt = sqlsrv_query($conn, $sql);

if($stmt === false) {
    die("Error al obtener usuarios: " . print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema de Facturación</title>
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

        /* Container */
        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Mensajes */
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
            font-size: 14px;
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
            padding: 18px 15px;
            color: #333;
            font-size: 14px;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.admin {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge.cajero {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }

        .badge.activo {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge.inactivo {
            background-color: #ffebee;
            color: #c62828;
        }

        /* Botones de acción */
        .acciones {
            display: flex;
            gap: 8px;
        }

        .btn-accion {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-editar {
            background-color: #2196f3;
            color: white;
        }

        .btn-editar:hover {
            background-color: #1976d2;
        }

        .btn-desactivar {
            background-color: #ff9800;
            color: white;
        }

        .btn-desactivar:hover {
            background-color: #f57c00;
        }

        .btn-activar {
            background-color: #4caf50;
            color: white;
        }

        .btn-activar:hover {
            background-color: #388e3c;
        }

        .btn-eliminar {
            background-color: #f44336;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #d32f2f;
        }

        /* Vacío */
        .tabla-vacia {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .tabla-vacia-icono {
            font-size: 60px;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .tabla-container {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <h1>👥 Gestión de Usuarios</h1>
        <div class="header-right">
            <span><?php echo htmlspecialchars($admin_nombre); ?> (ADMIN)</span>
            <a href="../dashboard.php" class="btn-dashboard">← Volver al Dashboard</a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Mensajes -->
        <?php if(isset($_GET['success'])): ?>
            <div class="mensaje exito">
                <span>✓</span>
                <span>
                    <?php
                    switch($_GET['success']) {
                        case 'creado':
                            echo 'Usuario creado exitosamente.';
                            break;
                        case 'editado':
                            echo 'Usuario actualizado exitosamente.';
                            break;
                        case 'activado':
                            echo 'Usuario activado exitosamente.';
                            break;
                        case 'desactivado':
                            echo 'Usuario desactivado exitosamente.';
                            break;
                        case 'eliminado':
                            echo 'Usuario eliminado exitosamente.';
                            break;
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="mensaje error">
                <span>✕</span>
                <span>
                    <?php
                    switch($_GET['error']) {
                        case 'existe':
                            echo 'El nombre de usuario ya existe.';
                            break;
                        case 'no_eliminar':
                            echo 'No puedes eliminar tu propia cuenta.';
                            break;
                        default:
                            echo 'Ha ocurrido un error. Intenta nuevamente.';
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Top Bar -->
        <div class="top-bar">
            <h2>Lista de Usuarios</h2>
            <a href="crear_usuario.php" class="btn-nuevo">
                <span>+</span>
                <span>Nuevo Usuario</span>
            </a>
        </div>

        <!-- Tabla -->
        <div class="tabla-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hay_usuarios = false;
                    while($usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                        $hay_usuarios = true;
                        $es_usuario_actual = ($usuario['id'] == $_SESSION['usuario_id']);
                        $fecha = $usuario['fecha_creacion']->format('d/m/Y');
                    ?>
                    <tr>
                        <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($usuario['nombre']); ?>
                            <?php if($es_usuario_actual): ?>
                                <span style="color: #667eea; font-size: 12px;">(Tú)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($usuario['rol']); ?>">
                                <?php echo $usuario['rol']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo strtolower($usuario['estado']); ?>">
                                <?php echo $usuario['estado']; ?>
                            </span>
                        </td>
                        <td><?php echo $fecha; ?></td>
                        <td>
                            <div class="acciones">
                                <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                   class="btn-accion btn-editar">
                                    Editar
                                </a>
                                
                                <?php if(!$es_usuario_actual): ?>
                                    <?php if($usuario['estado'] === 'activo'): ?>
                                        <a href="cambiar_estado_usuario.php?id=<?php echo $usuario['id']; ?>&estado=inactivo" 
                                           class="btn-accion btn-desactivar"
                                           onclick="return confirm('¿Desactivar este usuario?');">
                                            Desactivar
                                        </a>
                                    <?php else: ?>
                                        <a href="cambiar_estado_usuario.php?id=<?php echo $usuario['id']; ?>&estado=activo" 
                                           class="btn-accion btn-activar"
                                           onclick="return confirm('¿Activar este usuario?');">
                                            Activar
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="eliminar_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                       class="btn-accion btn-eliminar"
                                       onclick="return confirm('⚠️ ¿ELIMINAR permanentemente este usuario?\n\nEsta acción NO se puede deshacer.');">
                                        Eliminar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if(!$hay_usuarios): ?>
                    <tr>
                        <td colspan="7">
                            <div class="tabla-vacia">
                                <div class="tabla-vacia-icono">📋</div>
                                <p>No hay usuarios registrados</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-ocultar mensajes después de 5 segundos
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
// Liberar recursos
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>