<?php
/**
 * Página de Login
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
    <title>Login - Sistema de Facturación</title>
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
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
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

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
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

        .btn-login:hover {
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

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🧾 Sistema de Facturación</h1>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <?php
        // Mostrar mensaje de error si existe
        if(isset($_GET['error'])) {
            echo '<div class="error-message">';
            switch($_GET['error']) {
                case 'empty':
                    echo 'Por favor, complete todos los campos.';
                    break;
                case 'invalid':
                    echo 'Usuario o contraseña incorrectos.';
                    break;
                case 'inactive':
                    echo 'Tu cuenta está inactiva. Contacta al administrador.';
                    break;
                default:
                    echo 'Ha ocurrido un error. Intenta nuevamente.';
            }
            echo '</div>';
        }

        // Mostrar mensaje de éxito si viene del registro
        if(isset($_GET['registro']) && $_GET['registro'] == 'exitoso') {
            echo '<div style="background-color: #efe; color: #2a2; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2a2;">';
            echo 'Registro exitoso. Ahora puedes iniciar sesión.';
            echo '</div>';
        }
        ?>

        <form action="procesar_login.php" method="POST" id="loginForm">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>

        <div class="register-link">
            ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
        </div>
    </div>

    <script>
        // Validación básica en el cliente
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario').value.trim();
            const password = document.getElementById('password').value.trim();

            if(usuario === '' || password === '') {
                e.preventDefault();
                alert('Por favor, complete todos los campos.');
            }
        });
    </script>
</body>
</html>