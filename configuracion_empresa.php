<?php
/**
 * CONFIGURACIÓN DE LA EMPRESA
 * Panel para personalizar datos del negocio
 */
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: dashboard.php");
    exit();
}
require_once 'conexion.php';

// Obtener config actual (Primera lectura)
$sql = "SELECT TOP 1 * FROM config_empresa WHERE id = 1";
$stmt = sqlsrv_query($conn, $sql);
$empresa = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// FORZAR SINCRONIZACIÓN MANUAL (Mover al principio para que afecte a la sesión actual)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_sincronizar'])) {
    require_once 'includes/licencia_helper.php';
    
    // Si el usuario pegó una URL nueva, guardarla de una vez para que la sincronización la use
    if (!empty($_POST['licencia_cloud_url'])) {
        $nueva_url = trim($_POST['licencia_cloud_url']);
        sqlsrv_query($conn, "UPDATE config_empresa SET licencia_cloud_url = ? WHERE id = 1", [$nueva_url]);
    }

    // Reseteamos el last_sync para que verificarLicencia lo haga de inmediato
    sqlsrv_query($conn, "UPDATE config_empresa SET licencia_last_sync = NULL WHERE id = 1");
    verificarLicencia($conn, true);
    
    // IMPORTANTE: Recargar los datos de la empresa para que la UI muestre lo nuevo que bajó de la nube
    $sql_reload = "SELECT *, ISNULL(licencia_sync_intervalo, 60) as licencia_sync_intervalo FROM config_empresa WHERE id = 1";
    $stmt_reload = sqlsrv_query($conn, $sql_reload);
    if ($stmt_reload) {
        $empresa = sqlsrv_fetch_array($stmt_reload, SQLSRV_FETCH_ASSOC);
    }
    
    $mensaje = 'sync_success';
    
    // RE-LEER los datos para que el resto del script use la clave nueva
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM config_empresa WHERE id = 1");
    $empresa = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// Logout del modo maestro
if (isset($_GET['master_logout'])) {
    unset($_SESSION['master_verified']);
    header("Location: configuracion_empresa.php");
    exit();
}

// Verificación de Contraseña Maestra (si se intenta entrar
// --- VERIFICACIÓN MAESTRA ---
$master_verificado = isset($_SESSION['master_verified']) && $_SESSION['master_verified'] === true;
$error_master = false;

    if (isset($_GET['master']) && isset($_POST['password_master'])) {
        $pass = trim($_POST['password_master']);
        $uid = $_SESSION['usuario_id'];
        
        // Obtener la clave maestra de la configuración
        $master_key_db = isset($empresa['licencia_master_key']) ? trim($empresa['licencia_master_key']) : '';
        
        if (!empty($master_key_db)) {
            // VALIDACIÓN CON CLAVE MAESTRA (Cloud)
            if ($pass === $master_key_db) {
            $_SESSION['master_verified'] = true;
            $master_verificado = true;
            
            // Sincronizar automáticamente al entrar para que los datos estén frescos
            require_once 'includes/licencia_helper.php';
            sqlsrv_query($conn, "UPDATE config_empresa SET licencia_last_sync = NULL WHERE id = 1");
            verificarLicencia($conn, true);
            
            // Recargar datos para que la UI muestre lo nuevo
            $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM config_empresa WHERE id = 1");
            $empresa = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        } else {
            $error_master = true;
        }
    } else {
        // FALLBACK: Si no hay clave maestra configurada aún, usar clave de Admin actual
        $sql_u = "SELECT password FROM usuarios WHERE id = ?";
        $stmt_u = sqlsrv_query($conn, $sql_u, [$uid]);
        $user_data = sqlsrv_fetch_array($stmt_u, SQLSRV_FETCH_ASSOC);

        if ($user_data && password_verify($pass, $user_data['password'])) {
            $_SESSION['master_verified'] = true;
            $master_verificado = true;
        } else {
            $error_master = true;
        }
    }
}


// Procesar guardado normal (SOLO si se envió el formulario de configuración y NO se pulsó sincronizar)
$mensaje = $mensaje ?: ''; // Mantener el mensaje de sync si ya existe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_empresa']) && !isset($_POST['btn_sincronizar'])) {
    $nombre = trim($_POST['nombre_empresa'] ?? '');
    $rnc = trim($_POST['rnc'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sitio_web = trim($_POST['sitio_web'] ?? '');
    $moneda = trim($_POST['moneda'] ?? 'RD$');
    $itbis = floatval($_POST['itbis_porcentaje'] ?? 18);
    $aplicar_impuestos = isset($_POST['aplicar_impuestos']) ? 1 : 0;
    $mensaje_factura = trim($_POST['mensaje_factura'] ?? '');
    $formato_documento = trim($_POST['formato_documento_cliente'] ?? 'completa');

    // Logo: archivo o URL
    $logo_url = $empresa['logo_url'] ?? '';
    if (!empty($_POST['logo_url_input'])) {
        $logo_url = trim($_POST['logo_url_input']);
    }
    // Lógica para eliminar logo
    if (isset($_POST['eliminar_logo']) && $_POST['eliminar_logo'] == '1') {
        $logo_url = '';
    }
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $upload_dir = 'uploads/logo/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $filename = 'logo_empresa.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_dir . $filename)) {
                $logo_url = $upload_dir . $filename . '?t=' . time();
            }
        }
    }

    // Licencia (solo si se envía, protegemos estos campos)
    $licencia_sql = "";
    $licencia_params = [];
    if (isset($_POST['licencia_activa_hidden'])) {
        $lic_activa = isset($_POST['licencia_activa']) ? 1 : 0;
        $lic_permanente = isset($_POST['licencia_permanente']) ? 1 : 0;
        $lic_cloud_url = trim($_POST['licencia_cloud_url'] ?? '');
        $lic_intervalo = intval($_POST['licencia_sync_intervalo'] ?? 60);
        $lic_serial = trim($_POST['licencia_serial'] ?? '');
        
        // Si el campo está desactivado en el navegador, usamos la fecha que ya tenía
        $fecha_venc = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : (!empty($empresa['fecha_vencimiento']) ? $empresa['fecha_vencimiento']->format('Y-m-d') : '2030-12-31');
        
        $licencia_sql = ", licencia_activa = ?, fecha_vencimiento = ?, licencia_permanente = ?, licencia_cloud_url = ?, licencia_sync_intervalo = ?, licencia_serial = ?";
        $licencia_params = [$lic_activa, $fecha_venc, $lic_permanente, $lic_cloud_url, $lic_intervalo, $lic_serial];
    }

    // Asegurar que exista el registro ID 1 (Para instalaciones nuevas)
    $stmt_check = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM config_empresa WHERE id = 1");
    if ($stmt_check) {
        $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($row_check['c'] == 0) {
            sqlsrv_query($conn, "SET IDENTITY_INSERT config_empresa ON; INSERT INTO config_empresa (id) VALUES (1); SET IDENTITY_INSERT config_empresa OFF;");
        }
    }

    $sql_update = "UPDATE config_empresa SET 
        nombre_empresa = ?, rnc = ?, telefono = ?, direccion = ?,
        email = ?, sitio_web = ?, logo_url = ?, moneda = ?,
        itbis_porcentaje = ?, aplicar_impuestos = ?, mensaje_factura = ?,
        formato_documento_cliente = ?
        $licencia_sql
        , ultima_modificacion = GETDATE()
        WHERE id = 1";

    $params = [$nombre, $rnc, $telefono, $direccion, $email, $sitio_web, $logo_url, $moneda, $itbis, $aplicar_impuestos, $mensaje_factura, $formato_documento];
    $params = array_merge($params, $licencia_params);

        $result = sqlsrv_query($conn, $sql_update, $params);

    if ($result) {
        $mensaje = 'success';
        // Refrescar datos
        $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM config_empresa WHERE id = 1");
        $empresa = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    } else {
        $mensaje = 'error';
    }
}

$usuario_nombre = $_SESSION['usuario_nombre'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa - Sistema de Facturación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .header h1 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-volver {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-volver:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .config-card {
            background: white;
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .config-card h2 {
            color: #1a1a2e;
            margin-bottom: 5px;
            font-size: 20px;
        }

        .config-card .subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .section-title {
            color: #667eea;
            font-size: 16px;
            font-weight: 700;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e8e8e8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 15px;
        }

        .form-grid.full {
            grid-template-columns: 1fr;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .form-group small {
            color: #999;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .logo-preview {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: white;
            font-size: 32px;
            color: #ccc;
            flex-shrink: 0;
        }

        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .logo-inputs {
            flex: 1;
        }

        .btn-guardar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            transition: opacity 0.2s;
        }

        .btn-guardar:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .preview-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 12px;
            padding: 25px;
            color: white;
            text-align: center;
        }

        .preview-card .empresa-nombre {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .preview-card .empresa-rnc {
            font-size: 13px;
            opacity: 0.7;
            margin-top: 4px;
        }

        .preview-card .empresa-info {
            font-size: 12px;
            opacity: 0.6;
            margin-top: 8px;
            line-height: 1.6;
        }

        .preview-card hr {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 15px 0;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <h1>⚙️ Configuración de la Empresa</h1>
        <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
    </header>

    <div class="container">
        <!-- PANTALLA DE VERIFICACIÓN MAESTRA -->
        <?php if (isset($_GET['master']) && !$master_verificado): ?>
            <div class="config-card" style="max-width: 450px; margin: 100px auto; text-align: center;">
                <div style="font-size: 50px; margin-bottom: 20px;">🔐</div>
                <h2>Verificación de Identidad</h2>
                <p class="subtitle">Ingrese su contraseña maestra para acceder al panel de control de licencias.</p>

                <?php if ($error_master): ?>
                    <div class="alert alert-error">❌ Contraseña incorrecta. Acceso denegado.</div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group" style="margin-bottom: 20px; position: relative;">
                        <input type="password" name="password_master" id="pass_master" placeholder="Contraseña Maestra" required
                            style="text-align: center; font-size: 18px; padding: 15px; padding-right: 50px;">
                        <button type="button" onclick="togglePass()" 
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 20px; cursor: pointer;">
                            👁️
                        </button>
                    </div>
                    <button type="submit" class="btn-guardar">Verificar y Entrar</button>
                </form>

                <script>
                    function togglePass() {
                        const input = document.getElementById('pass_master');
                        input.type = input.type === 'password' ? 'text' : 'password';
                    }
                </script>
            </div>
        <?php else: ?>

            <?php if ($mensaje === 'success'): ?>
                <div class="alert alert-success">✅ Configuración guardada exitosamente.</div>
            <?php elseif ($mensaje === 'sync_success'): ?>
                <div class="alert alert-success">🔄 Sincronización exitosa. Los datos han sido actualizados desde la nube.</div>
            <?php elseif ($mensaje === 'error'): ?>
                <div class="alert alert-error">❌ Error al procesar la solicitud.</div>
            <?php endif; ?>

            <!-- Vista previa en vivo -->
            <div class="config-card">
                <div class="preview-card" id="previewCard">
            <div id="previewLogoLive"
                style="margin-bottom: 15px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 40px;">
                        <?php if (!empty($empresa['logo_url'])): ?>
                            <img src="<?php echo htmlspecialchars($empresa['logo_url']); ?>" alt="Logo"
                                style="max-height: 100%; max-width: 150px; object-fit: contain;">
                        <?php else: ?>
                            🏪
                        <?php endif; ?>
                    </div>
                    <div class="empresa-nombre" id="prevNombre">
                        <?php echo htmlspecialchars($empresa['nombre_empresa'] ?? 'POWER IN TECH'); ?>
                    </div>
                    <div class="empresa-rnc" id="prevRnc">RNC:
                        <?php echo htmlspecialchars($empresa['rnc'] ?? '000-00000-0'); ?>
                    </div>
                    <hr>
                    <div class="empresa-info" id="prevInfo">
                        📞 <?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?><br>
                        📍 <?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="config-card">
                    <h2>Datos del Negocio</h2>
                    <p class="subtitle">Esta información aparece en las facturas, recibos y reportes del sistema.</p>

                    <div class="section-title">🏢 Identidad</div>

                    <!-- Logo -->
                    <div class="logo-area">
                        <input type="hidden" name="eliminar_logo" id="eliminar_logo" value="0">
                        <div class="logo-preview" id="previewLogoForm">
                            <?php if (!empty($empresa['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($empresa['logo_url']); ?>" alt="Logo">
                            <?php else: ?>
                                🏪
                            <?php endif; ?>
                        </div>
                        <div class="logo-inputs">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Subir Logo</label>
                                <input type="file" name="logo_file" id="logo_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                            </div>
                            <div class="form-group">
                                <label>o URL del Logo</label>
                                <input type="url" name="logo_url_input" id="logo_url_input" value=""
                                    placeholder="https://example.com/logo.png">
                            </div>
                            <?php if (!empty($empresa['logo_url'])): ?>
                                <button type="button" onclick="marcarEliminarLogo()" 
                                    style="background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; padding: 5px 10px; border-radius: 5px; font-size: 12px; cursor: pointer; margin-top: 5px;">
                                    🗑️ Eliminar Logo Actual
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre de la Empresa *</label>
                            <input type="text" name="nombre_empresa" id="inp_nombre" required
                                value="<?php echo htmlspecialchars($empresa['nombre_empresa'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>RNC / Cédula Fiscal</label>
                            <input type="text" name="rnc" id="inp_rnc"
                                value="<?php echo htmlspecialchars($empresa['rnc'] ?? ''); ?>" placeholder="000-00000-0">
                        </div>
                    </div>

                    <div class="section-title">📞 Contacto</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" id="inp_tel"
                                value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>"
                                placeholder="(809) 000-0000">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email"
                                value="<?php echo htmlspecialchars($empresa['email'] ?? ''); ?>"
                                placeholder="info@empresa.com">
                        </div>
                    </div>
                    <div class="form-grid full">
                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" name="direccion" id="inp_dir"
                                value="<?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?>"
                                placeholder="Calle, Ciudad, País">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Sitio Web</label>
                            <input type="url" name="sitio_web"
                                value="<?php echo htmlspecialchars($empresa['sitio_web'] ?? ''); ?>"
                                placeholder="https://www.miempresa.com">
                        </div>
                    </div>

                    <div class="section-title">💰 Facturación</div>
                    <div class="form-grid full">
                        <div class="form-group">
                            <label
                                style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #e8f5e9; padding: 15px; border-radius: 8px; border: 1px solid #c8e6c9;">
                                <input type="checkbox" name="aplicar_impuestos" value="1" style="width: 20px; height: 20px;"
                                    <?php echo (!empty($empresa['aplicar_impuestos'])) ? 'checked' : ''; ?>>
                                <div>
                                    <strong style="color: #2e7d32; font-size: 15px;">Aplicar Impuestos en
                                        Facturación</strong>
                                    <small style="display: block; color: #4caf50; font-weight: normal;">Si está activo, el
                                        sistema sumará automáticamente el impuesto (ej. ITBIS) al final de las
                                        facturas.</small>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Moneda</label>
                            <select name="moneda">
                                <option value="RD$" <?php echo ($empresa['moneda'] ?? '') === 'RD$' ? 'selected' : ''; ?>>RD$
                                    (Peso Dominicano)</option>
                                <option value="US$" <?php echo ($empresa['moneda'] ?? '') === 'US$' ? 'selected' : ''; ?>>US$
                                    (Dólar)</option>
                                <option value="€" <?php echo ($empresa['moneda'] ?? '') === '€' ? 'selected' : ''; ?>>€ (Euro)
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ITBIS Global (%)</label>
                            <input type="number" name="itbis_porcentaje" step="0.01" min="0" max="100"
                                value="<?php echo $empresa['itbis_porcentaje'] ?? 18; ?>">
                            <small>Impuesto por defecto si el producto no tiene uno específico</small>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Privacidad: Documento del Cliente</label>
                            <select name="formato_documento_cliente">
                                <option value="completa" <?php echo ($empresa['formato_documento_cliente'] ?? '') === 'completa' ? 'selected' : ''; ?>>Mostrar Completo</option>
                                <option value="enmascarada" <?php echo ($empresa['formato_documento_cliente'] ?? '') === 'enmascarada' ? 'selected' : ''; ?>>Enmascarar (Privacidad)</option>
                                <option value="quitar" <?php echo ($empresa['formato_documento_cliente'] ?? '') === 'quitar' ? 'selected' : ''; ?>>No mostrar en factura</option>
                            </select>
                            <small>Controla cómo se muestra la Cédula/RNC del cliente en la factura impresa.</small>
                        </div>
                    </div>

                    <div class="form-grid full">
                        <div class="form-group">
                            <label>Mensaje en Factura</label>
                            <textarea name="mensaje_factura"
                                placeholder="Ej: Gracias por su compra"><?php echo htmlspecialchars($empresa['mensaje_factura'] ?? ''); ?></textarea>
                            <small>Aparece al pie de cada factura impresa</small>
                        </div>
                    </div>

                    <button type="submit" class="btn-guardar">💾 Guardar Configuración</button>
                </div>

            <!-- SECCIÓN MAESTRA (Solo si está verificado) -->
            <?php if (isset($_GET['master']) && $master_verificado): ?>
                <div class="config-card" style="border: 2px solid #ef4444; margin-top: 50px;">
                    <h2 style="color: #ef4444;">🛡️ Control de Licencia (Modo Desarrollador)</h2>
                    <p class="subtitle">Usa esto para bloquear el sistema en caso de falta de pago.</p>

                        <!-- Campo oculto para indicar que se están enviando campos de licencia -->
                        <input type="hidden" name="licencia_activa_hidden" value="1">

                        <div class="form-grid">
                            <div class="form-group">
                                <label
                                    style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff1f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca;">
                                    <input type="checkbox" name="licencia_activa" id="lic_activa" value="1"
                                        style="width: 20px; height: 20px;" <?php echo (isset($empresa['licencia_activa']) && $empresa['licencia_activa'] == 1) ? 'checked' : ''; ?>>
                                    <div>
                                        <strong style="color: #991b1b; font-size: 15px;">Sistema Activo</strong>
                                        <small style="display: block; color: #b91c1c; font-weight: normal;">Control manual de
                                            acceso.</small>
                                    </div>
                                </label>
                            </div>
                            <div class="form-group">
                                <label
                                    style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0;">
                                    <input type="checkbox" name="licencia_permanente" id="lic_permanente" value="1"
                                        style="width: 20px; height: 20px;" <?php echo (isset($empresa['licencia_permanente']) && $empresa['licencia_permanente'] == 1) ? 'checked' : ''; ?>>
                                    <div>
                                        <strong style="color: #166534; font-size: 15px;">Licencia Permanente</strong>
                                        <small style="display: block; color: #15803d; font-weight: normal;">Sin restricciones de
                                            fecha.</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Fecha de Vencimiento (Local)</label>
                                <input type="date" name="fecha_vencimiento"
                                    value="<?php echo (!empty($empresa['fecha_vencimiento'])) ? $empresa['fecha_vencimiento']->format('Y-m-d') : ''; ?>">
                                <small>Solo se usa si no hay URL en la nube.</small>
                            </div>
                            <div class="form-group">
                                <label>Serial Único de Instalación</label>
                                <input type="text" name="licencia_serial" value="<?php echo htmlspecialchars($empresa['licencia_serial'] ?? ''); ?>" 
                                    style="background: #fff; font-family: monospace; font-weight: bold; border: 1px solid #ced4da;">
                                <small>Copia este serial en tu Google Sheet. Cámbialo para identificar cada cliente.</small>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>🌐 URL de Control en la Nube (Google Sheets CSV)</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="url" name="licencia_cloud_url" 
                                        value="<?php echo htmlspecialchars($empresa['licencia_cloud_url'] ?? ''); ?>"
                                        placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv"
                                        style="flex: 1;">
                                    <button type="submit" name="btn_sincronizar" value="1" class="btn-guardar" 
                                        style="width: auto; margin-top: 0; background: #34495e; padding: 10px 15px; font-size: 13px;">
                                        🔄 Sincronizar Ahora
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>⏱️ Intervalo de Sincronización (Minutos)</label>
                                <input type="number" name="licencia_sync_intervalo" min="1" max="1440"
                                    value="<?php echo $empresa['licencia_sync_intervalo'] ?? 60; ?>">
                            </div>
                        </div>

                        <div style="font-size: 12px; margin-bottom: 15px; text-align: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee;">
                            <strong>Sincronización Cloud:</strong> 
                            <?php if (!empty($empresa['licencia_last_sync'])): ?>
                                <span style="color: #2e7d32;">🔄 Última vez: <?php echo $empresa['licencia_last_sync']->format('d/m/Y H:i:s'); ?></span>
                            <?php else: ?>
                                <span style="color: #d32f2f;">❌ Nunca sincronizado</span>
                            <?php endif; ?>
                        </div>

                        <div style="background: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #fef3c7; margin-bottom: 20px; font-size: 12px; color: #92400e;">
                            <strong>Nota Importante:</strong> Si configuras una URL en la nube, los campos de "Sistema Activo" y "Fecha de Vencimiento" se actualizarán automáticamente con lo que diga tu hoja de Google cada vez que se sincronice.
                        </div>
                        <button type="submit" class="btn-guardar" style="background: #ef4444;">Actualizar Estado de
                            Licencia</button>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="?master_logout=1" style="color: #999; font-size: 12px; text-decoration: none;">🔒 Volver a
                            bloquear panel secreto</a>
                    </div>
                </div>
            <?php endif; ?>
            </form>

        <?php endif; // Fin del ELSE de verificación maestra ?>
    </div>

    <script>
        // Vista previa en vivo
        document.getElementById('inp_nombre').addEventListener('input', function () {
            document.getElementById('prevNombre').textContent = this.value || 'Nombre de Empresa';
        });
        document.getElementById('inp_rnc').addEventListener('input', function () {
            document.getElementById('prevRnc').textContent = 'RNC: ' + (this.value || '---');
        });
        function updateInfo() {
            const tel = document.getElementById('inp_tel').value;
            const dir = document.getElementById('inp_dir').value;
            let html = '';
            if (tel) html += '📞 ' + tel + '<br>';
            if (dir) html += '📍 ' + dir;
            document.getElementById('prevInfo').innerHTML = html || 'Sin información de contacto';
        }
        document.getElementById('inp_tel').addEventListener('input', updateInfo);
        document.getElementById('inp_dir').addEventListener('input', updateInfo);

        // Logo preview
        function updateLogoPreview(src) {
            const html = src ? '<img src="' + src + '" style="max-height: 100%; max-width: 100%; object-fit: contain;">' : '🏪';
            document.getElementById('previewLogoLive').innerHTML = html;
            document.getElementById('previewLogoForm').innerHTML = html;
            if (src) document.getElementById('eliminar_logo').value = '0';
        }

        document.getElementById('logo_file').addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    updateLogoPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('logo_url_input').addEventListener('input', function () {
            if (this.value) {
                updateLogoPreview(this.value);
            }
        });

        function marcarEliminarLogo() {
            if (confirm('¿Está seguro de que desea eliminar el logo de la empresa?')) {
                document.getElementById('eliminar_logo').value = '1';
                updateLogoPreview('');
                document.getElementById('logo_url_input').value = '';
                document.getElementById('logo_file').value = '';
            }
        }

        // Lógica de Licencia Maestra
        const licPerm = document.getElementById('lic_permanente');
        const licAct = document.getElementById('lic_activa');
        const licDate = document.querySelector('input[name="fecha_vencimiento"]');

        if (licPerm) {
            function toggleLicFields() {
                const isPerm = licPerm.checked;
                licAct.disabled = isPerm;
                licDate.disabled = isPerm;
                licAct.parentElement.parentElement.style.opacity = isPerm ? '0.5' : '1';
                licDate.parentElement.style.opacity = isPerm ? '0.5' : '1';
            }
            licPerm.addEventListener('change', toggleLicFields);
            toggleLicFields(); // Ejecutar al cargar
        }
    </script>
</body>

</html>
<?php sqlsrv_close($conn); ?>