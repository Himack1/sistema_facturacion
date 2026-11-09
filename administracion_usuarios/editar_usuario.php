<?php
/**
 * Editar Usuario
 * Sistema de Facturación - Solo ADMIN
 */

session_start();

// Verificar permisos
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

require_once '../conexion.php';

// Obtener ID del usuario a editar
$id_editar = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id_editar <= 0) {
    header("Location: gestion_usuarios.php");
    exit();
}

// Obtener datos del usuario
$sql = "SELECT id, nombre, usuario, rol, estado FROM usuarios WHERE id = ?";
$stmt = sqlsrv_prepare($conn, $sql, array(&$id_editar));

if($stmt === false || !sqlsrv_execute($stmt)) {
    header("Location: gestion_usuarios.php?error=no_encontrado");
    exit();
}

$usuario_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$usuario_data) {
    header("Location: gestion_usuarios.php?error=no_encontrado");
    exit();
}

sqlsrv_free_stmt($stmt);

$es_usuario_actual = ($usuario_data['id'] == $_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Sistema de Facturación</title>
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

        .btn-volver {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-volver:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: #333;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 14px;
        }

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            margin-bottom: 25px;
            border-radius: 5px;
            font-size: 14px;
            color: #1565c0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #888;
            font-size: 13px;
        }

        .required {
            color: #f44336;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .mensaje-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
            font-size: 14px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>✏️ Editar Usuario</h1>
        <a href="gestion_usuarios.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2>Editar Usuario #<?php echo $usuario_data['id']; ?></h2>
                <p>Modifica los datos del usuario</p>
            </div>

            <?php if($es_usuario_actual): ?>
                <div class="info-box">
                    ℹ️ Estás editando tu propia cuenta. Ten cuidado al cambiar tu rol o estado.
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="mensaje-error">
                    <?php
                    switch($_GET['error']) {
                        case 'empty':
                            echo 'Por favor, complete todos los campos obligatorios.';
                            break;
                        case 'exists':
                            echo 'El nombre de usuario ya está en uso por otro usuario.';
                            break;
                        default:
                            echo 'Ha ocurrido un error. Intenta nuevamente.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="procesar_editar_usuario.php" method="POST" id="formEditar">
                <input type="hidden" name="id" value="<?php echo $usuario_data['id']; ?>">

                <div class="form-group">
                    <label for="nombre">
                        Nombre Completo <span class="required">*</span>
                    </label>
                    <input type="text" id="nombre" name="nombre" 
                           value="<?php echo htmlspecialchars($usuario_data['nombre']); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="usuario">
                        Usuario <span class="required">*</span>
                    </label>
                    <input type="text" id="usuario" name="usuario" 
                           value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" required>
                    <small>Este campo puede causar conflictos si ya existe</small>
                </div>

                <div class="form-group">
                    <label for="rol">
                        Rol <span class="required">*</span>
                    </label>
                    <select id="rol" name="rol" required>
                        <option value="CAJERO" <?php echo $usuario_data['rol'] === 'CAJERO' ? 'selected' : ''; ?>>CAJERO</option>
                        <option value="ADMIN" <?php echo $usuario_data['rol'] === 'ADMIN' ? 'selected' : ''; ?>>ADMIN</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="estado">
                        Estado <span class="required">*</span>
                    </label>
                    <select id="estado" name="estado" required>
                        <option value="activo" <?php echo $usuario_data['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $usuario_data['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Cambiar Contraseña</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="cambiar_password" name="cambiar_password" value="1">
                        <label for="cambiar_password">Quiero cambiar la contraseña</label>
                    </div>
                </div>

                <div class="form-group" id="password_group" style="display: none;">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" name="password">
                    <small>Mínimo 6 caracteres. Deja vacío si no quieres cambiarla</small>
                </div>

                <div class="form-actions">
                    <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mostrar/ocultar campo de contraseña
        document.getElementById('cambiar_password').addEventListener('change', function() {
            const passwordGroup = document.getElementById('password_group');
            const passwordInput = document.getElementById('password');
            
            if(this.checked) {
                passwordGroup.style.display = 'block';
                passwordInput.required = true;
            } else {
                passwordGroup.style.display = 'none';
                passwordInput.required = false;
                passwordInput.value = '';
            }
        });

        // Validación del formulario
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const usuario = document.getElementById('usuario').value.trim();
            const cambiarPassword = document.getElementById('cambiar_password').checked;
            const password = document.getElementById('password').value.trim();

            if(nombre === '' || usuario === '') {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
                return;
            }

            if(cambiarPassword && password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres.');
                return;
            }
        });

        // Eliminar espacios del usuario
        document.getElementById('usuario').addEventListener('input', function() {
            this.value = this.value.replace(/\s/g, '');
        });
    </script>
</body>
</html>
<?php
sqlsrv_close($conn);
?>