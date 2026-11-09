<?php
/**
 * Herramienta de Gestión de Contraseñas (Hash & SQL)
 * Diseñado para recuperar el acceso al sistema de forma segura.
 */

$password_to_hash = isset($_POST['password_to_hash']) ? $_POST['password_to_hash'] : 'admin123';
$generated_hash = password_hash($password_to_hash, PASSWORD_BCRYPT, ['cost' => 10]);

// Lógica de Verificación
$verify_password = isset($_POST['verify_password']) ? $_POST['verify_password'] : '';
$verify_hash = isset($_POST['verify_hash']) ? $_POST['verify_hash'] : '';
$verification_result = null;

if (isset($_POST['action']) && $_POST['action'] === 'verify' && !empty($verify_password) && !empty($verify_hash)) {
    if (password_verify($verify_password, $verify_hash)) {
        $verification_result = true;
    } else {
        $verification_result = false;
    }
}

// Datos para el INSERT sugerido
$new_user = isset($_POST['new_user']) ? $_POST['new_user'] : 'admin_recuperacion';
$new_name = isset($_POST['new_name']) ? $_POST['new_name'] : 'Administrador de Emergencia';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toolbox de Seguridad | Sistema de Facturación</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --success: #10b981;
            --error: #ef4444;
            --info: #3b82f6;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 40px;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #4f46e5, #9333ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .card-full {
            grid-column: 1 / -1;
        }

        h2 {
            font-size: 1.25rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
            text-align: center;
        }

        .btn:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: #64748b;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            margin-top: 15px;
            overflow-x: auto;
            position: relative;
        }

        .code-block code {
            display: block;
            white-space: pre-wrap;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border-left: 4px solid transparent;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-color: #f59e0b;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-color: #3b82f6;
        }

        .footer {
            text-align: center;
            margin-top: 60px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        hr {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔐 Herramientas de Acceso</h1>
            <p>Genera nuevos hashes o verifica los existentes para recuperar tu cuenta.</p>
        </header>

        <div class="alert alert-warning">
            <strong>⚠ Nota Importante:</strong> Las contraseñas en este sistema están protegidas con el algoritmo <code>BCRYPT</code>. 
            Este proceso es de <strong>una sola vía</strong>, lo que significa que no se puede "des-hashear" una contraseña para verla. 
            Si olvidaste la clave, debes generar una nueva y actualizar la base de datos.
        </div>

        <div class="grid">
            <!-- SECCION 1: GENERADOR -->
            <section class="card">
                <h2>🛠 Generador de Hash</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="form-group">
                        <label>Contraseña a usar:</label>
                        <input type="text" name="password_to_hash" value="<?php echo htmlspecialchars($password_to_hash); ?>" required>
                    </div>
                    <button type="submit" class="btn">Generar Nuevo Hash</button>
                </form>

                <div class="code-block">
                    <div style="margin-bottom: 5px;"><span class="badge badge-info">Hash Resultante</span></div>
                    <code><?php echo $generated_hash; ?></code>
                </div>
            </section>

            <!-- SECCION 2: VERIFICADOR -->
            <section class="card">
                <h2>🔍 Verificador</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="verify">
                    <div class="form-group">
                        <label>Contraseña plana:</label>
                        <input type="text" name="verify_password" placeholder="Ej: admin123" value="<?php echo htmlspecialchars($verify_password); ?>">
                    </div>
                    <div class="form-group">
                        <label>Hash de la base de datos:</label>
                        <textarea name="verify_hash" rows="2" placeholder="$2y$10$..."><?php echo htmlspecialchars($verify_hash); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">Verificar Coincidencia</button>
                </form>

                <?php if ($verification_result === true): ?>
                    <div class="alert alert-info" style="margin-top: 15px; border-color: var(--success); background: #ecfdf5; color: #065f46;">
                        ✅ <strong>¡Éxito!</strong> La contraseña coincide con el hash.
                    </div>
                <?php elseif ($verification_result === false): ?>
                    <div class="alert alert-warning" style="margin-top: 15px; border-color: var(--error); background: #fef2f2; color: #991b1b;">
                        ❌ <strong>Error:</strong> La contraseña NO coincide con el hash.
                    </div>
                <?php endif; ?>
            </section>

            <!-- SECCION 3: SQL RECOVERY -->
            <section class="card card-full">
                <h2>🚀 SQL para Recuperar Acceso</h2>
                <p style="margin-bottom: 15px; color: var(--text-muted);">Usa estos comandos en tu gestor de base de datos para recuperar el acceso de inmediato.</p>
                
                <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 20px; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Nombre Completo:</label>
                        <input type="text" name="new_name" value="<?php echo htmlspecialchars($new_name); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Nombre de Usuario:</label>
                        <input type="text" name="new_user" value="<?php echo htmlspecialchars($new_user); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Contraseña deseada:</label>
                        <input type="text" name="password_to_hash" value="<?php echo htmlspecialchars($password_to_hash); ?>">
                    </div>
                    <button type="submit" class="btn" style="width: auto; padding: 12px 20px;">Actualizar SQL</button>
                </form>

                <div class="grid">
                    <div>
                        <h3 style="font-size: 0.9rem; margin-bottom: 10px;">Opción A: Crear Nuevo Usuario Admin</h3>
                        <div class="code-block" style="background: #0f172a;">
<code>INSERT INTO usuarios (nombre, usuario, password, rol, estado)
VALUES ('<?php echo htmlspecialchars($new_name); ?>', '<?php echo htmlspecialchars($new_user); ?>', '<?php echo $generated_hash; ?>', 'ADMIN', 'activo');</code>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 0.9rem; margin-bottom: 10px;">Opción B: Cambiar Clave a Usuario ID 2 (admin)</h3>
                        <div class="code-block" style="background: #0f172a;">
<code>UPDATE usuarios 
SET password = '<?php echo $generated_hash; ?>' 
WHERE id = 2;</code>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Toolbox de Seguridad - Sistema de Facturación</p>
            <p style="margin-top: 10px;"><a href="login.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">← Volver al Login</a></p>
        </div>
    </div>
</body>
</html>