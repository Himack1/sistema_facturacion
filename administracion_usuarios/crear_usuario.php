<?php
/**
 * Crear Nuevo Usuario
 * Sistema de Facturación - Solo ADMIN
 */

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado y es ADMIN
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

$admin_nombre = $_SESSION['usuario_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Sistema de Facturación</title>
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
            transition: background-color 0.3s;
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

        .password-strength {
            margin-top: 8px;
            height: 5px;
            border-radius: 3px;
            background-color: #e0e0e0;
            transition: all 0.3s;
        }

        .password-strength.weak {
            width: 33%;
            background-color: #f44336;
        }

        .password-strength.medium {
            width: 66%;
            background-color: #ff9800;
        }

        .password-strength.strong {
            width: 100%;
            background-color: #4caf50;
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
        <h1>➕ Crear Nuevo Usuario</h1>
        <a href="gestion_usuarios.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2>Nuevo Usuario</h2>
                <p>Completa los datos para crear una nueva cuenta de usuario</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="mensaje-error">
                    <?php
                    switch($_GET['error']) {
                        case 'empty':
                            echo 'Por favor, complete todos los campos obligatorios.';
                            break;
                        case 'exists':
                            echo 'El nombre de usuario ya está en uso. Elige otro.';
                            break;
                        case 'short':
                            echo 'La contraseña debe tener al menos 6 caracteres.';
                            break;
                        default:
                            echo 'Ha ocurrido un error. Intenta nuevamente.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="procesar_crear_usuario.php" method="POST" id="formCrear">
                <div class="form-group">
                    <label for="nombre">
                        Nombre Completo <span class="required">*</span>
                    </label>
                    <input type="text" id="nombre" name="nombre" required autofocus>
                    <small>Nombre y apellido del usuario</small>
                </div>

                <div class="form-group">
                    <label for="usuario">
                        Usuario <span class="required">*</span>
                    </label>
                    <input type="text" id="usuario" name="usuario" required>
                    <small>Sin espacios, mínimo 4 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="password">
                        Contraseña <span class="required">*</span>
                    </label>
                    <input type="password" id="password" name="password" required>
                    <div id="passwordStrength" class="password-strength"></div>
                    <small>Mínimo 6 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="rol">
                        Rol <span class="required">*</span>
                    </label>
                    <select id="rol" name="rol" required>
                        <option value="">-- Selecciona un rol --</option>
                        <option value="CAJERO" selected>CAJERO</option>
                        <option value="ADMIN">ADMIN</option>
                    </select>
                    <small>Define los permisos del usuario en el sistema</small>
                </div>

                <div class="form-group">
                    <label for="estado">
                        Estado <span class="required">*</span>
                    </label>
                    <select id="estado" name="estado" required>
                        <option value="activo" selected>Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                    <small>Los usuarios inactivos no pueden iniciar sesión</small>
                </div>

                <div class="form-actions">
                    <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.getElementById('formCrear').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const usuario = document.getElementById('usuario').value.trim();
            const password = document.getElementById('password').value.trim();
            const rol = document.getElementById('rol').value;

            if(nombre === '' || usuario === '' || password === '' || rol === '') {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
                return;
            }

            if(usuario.length < 4) {
                e.preventDefault();
                alert('El usuario debe tener al menos 4 caracteres.');
                return;
            }

            if(usuario.includes(' ')) {
                e.preventDefault();
                alert('El usuario no puede contener espacios.');
                return;
            }

            if(password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres.');
                return;
            }
        });

        // Medidor de fortaleza de contraseña
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            strengthBar.className = 'password-strength';
            
            if(password.length === 0) {
                strengthBar.classList.remove('weak', 'medium', 'strong');
            } else if(password.length < 6) {
                strengthBar.classList.add('weak');
            } else if(password.length < 10) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });

        // Eliminar espacios del usuario mientras escribe
        document.getElementById('usuario').addEventListener('input', function() {
            this.value = this.value.replace(/\s/g, '');
        });
    </script>
</body>
</html>