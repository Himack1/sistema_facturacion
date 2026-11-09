<?php
/**
 * Página de Registro
 * Sistema de Facturación
 */

// Iniciar sesión
session_start();

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Facturación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .register-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
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
            margin-top: 5px;
            color: #888;
            font-size: 12px;
        }

        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 14px;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 5px;
            height: 5px;
            border-radius: 3px;
            background-color: #e0e0e0;
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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📝 Crear Cuenta</h1>
            <p>Completa el formulario para registrarte</p>
        </div>

        <?php
        // Mostrar mensaje de error si existe
        if(isset($_GET['error'])) {
            echo '<div class="error-message">';
            switch($_GET['error']) {
                case 'empty':
                    echo 'Por favor, complete todos los campos.';
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
            echo '</div>';
        }
        ?>

        <form action="procesar_register.php" method="POST" id="registerForm">
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" id="nombre" name="nombre" required autofocus>
                <small>Ejemplo: Juan Pérez</small>
            </div>

            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required>
                <small>Sin espacios, mínimo 4 caracteres</small>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
                <div id="passwordStrength" class="password-strength"></div>
                <small>Mínimo 6 caracteres</small>
            </div>

            <!-- El rol se asigna automáticamente como CAJERO por seguridad -->
            <input type="hidden" name="rol" value="CAJERO">

            <button type="submit" class="btn-register">Registrarse</button>
        </form>

        <div class="login-link">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
        </div>
    </div>

    <script>
        // Validación en el cliente
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const usuario = document.getElementById('usuario').value.trim();
            const password = document.getElementById('password').value.trim();

            // Validar campos vacíos
            if(nombre === '' || usuario === '' || password === '') {
                e.preventDefault();
                alert('Por favor, complete todos los campos.');
                return;
            }

            // Validar longitud del usuario
            if(usuario.length < 4) {
                e.preventDefault();
                alert('El usuario debe tener al menos 4 caracteres.');
                return;
            }

            // Validar que el usuario no tenga espacios
            if(usuario.includes(' ')) {
                e.preventDefault();
                alert('El usuario no puede contener espacios.');
                return;
            }

            // Validar longitud de la contraseña
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
    </script>
</body>
</html>