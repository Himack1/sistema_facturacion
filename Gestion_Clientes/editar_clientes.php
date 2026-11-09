<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit(); }
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) { header("Location: gestion_clientes.php"); exit(); }

$sql = "SELECT * FROM clientes WHERE id = ?";
$params = array($id);
$stmt = sqlsrv_query($conn, $sql, $params);
if($stmt === false) { header("Location: gestion_clientes.php"); exit(); }
$cliente = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if(!$cliente) { header("Location: gestion_clientes.php"); exit(); }
sqlsrv_free_stmt($stmt);

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $tipo_documento = empty($_POST['tipo_documento']) ? null : trim($_POST['tipo_documento']);
    $documento = empty($_POST['documento']) ? null : trim($_POST['documento']);
    $telefono = empty($_POST['telefono']) ? null : trim($_POST['telefono']);
    $celular = empty($_POST['celular']) ? null : trim($_POST['celular']);
    $email = empty($_POST['email']) ? null : trim($_POST['email']);
    $direccion = empty($_POST['direccion']) ? null : trim($_POST['direccion']);
    $ciudad = empty($_POST['ciudad']) ? null : trim($_POST['ciudad']);
    $provincia = empty($_POST['provincia']) ? null : trim($_POST['provincia']);
    $limite_credito = floatval($_POST['limite_credito']);
    $estado = trim($_POST['estado']);
    $notas = empty($_POST['notas']) ? null : trim($_POST['notas']);

    if(empty($nombre)) {
        $error = "empty";
    } else {
        // Verificar documento único SOLO si se proporcionó
        if($documento !== null) {
            $sql_check = "SELECT COUNT(*) as total FROM clientes WHERE documento = ? AND id != ?";
            $params_check = array($documento, $id);
            $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
            if($stmt_check) {
                $result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
                
                if($result['total'] > 0) {
                    $error = "existe_documento";
                }
            }
        }
        
        if(!isset($error)) {
            $sql_update = "UPDATE clientes SET nombre = ?, tipo_documento = ?, documento = ?, 
                          telefono = ?, celular = ?, email = ?, direccion = ?, ciudad = ?, provincia = ?, 
                          limite_credito = ?, estado = ?, notas = ?, ultima_modificacion = GETDATE() 
                          WHERE id = ?";
            $params = array($nombre, $tipo_documento, $documento, $telefono, $celular, $email, 
                          $direccion, $ciudad, $provincia, $limite_credito, $estado, $notas, $id);
            $stmt_update = sqlsrv_query($conn, $sql_update, $params);
            
            if($stmt_update) {
                header("Location: gestion_clientes.php?success=editado");
                exit();
            } else {
                $error = "database";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .form-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 35px; }
        .form-section-title { color: #667eea; font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .required { color: #f44336; }
        .mensaje-error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #f44336; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 30px; border-top: 2px solid #e0e0e0; }
        .btn { flex: 1; padding: 14px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .full-width { grid-column: 1 / -1; }
    </style>
</head>
<body>
    <header class="header">
        <h1>✏️ Editar Cliente</h1>
        <a href="gestion_clientes.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="form-card">
            <?php if(isset($error)): ?>
                <div class="mensaje-error">
                    <?php 
                    echo $error === 'empty' ? 'Complete todos los campos obligatorios.' : 
                         ($error === 'existe_documento' ? 'El documento ya está registrado.' : 'Error al actualizar.');
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <div class="form-section-title">📋 Información Básica</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Código</label>
                            <input type="text" value="<?php echo htmlspecialchars($cliente['codigo']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Nombre <span class="required">*</span></label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Documento</label>
                            <select name="tipo_documento">
                                <option value="">-- Sin documento --</option>
                                <option value="Cédula" <?php echo $cliente['tipo_documento'] === 'Cédula' ? 'selected' : ''; ?>>Cédula</option>
                                <option value="RNC" <?php echo $cliente['tipo_documento'] === 'RNC' ? 'selected' : ''; ?>>RNC</option>
                                <option value="Pasaporte" <?php echo $cliente['tipo_documento'] === 'Pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
                                <option value="N/A" <?php echo $cliente['tipo_documento'] === 'N/A' ? 'selected' : ''; ?>>N/A (Sin documento)</option>
                                <option value="Otro" <?php echo $cliente['tipo_documento'] === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de Documento</label>
                            <input type="text" name="documento" value="<?php echo isset($cliente['documento']) ? htmlspecialchars($cliente['documento']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">📞 Contacto</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo isset($cliente['telefono']) ? htmlspecialchars($cliente['telefono']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Celular</label>
                            <input type="text" name="celular" value="<?php echo isset($cliente['celular']) ? htmlspecialchars($cliente['celular']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo isset($cliente['email']) ? htmlspecialchars($cliente['email']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">📍 Ubicación</div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Dirección</label>
                            <input type="text" name="direccion" value="<?php echo isset($cliente['direccion']) ? htmlspecialchars($cliente['direccion']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ciudad</label>
                            <input type="text" name="ciudad" value="<?php echo isset($cliente['ciudad']) ? htmlspecialchars($cliente['ciudad']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Provincia</label>
                            <input type="text" name="provincia" value="<?php echo isset($cliente['provincia']) ? htmlspecialchars($cliente['provincia']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">💼 Adicional</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Límite de Crédito</label>
                            <input type="number" name="limite_credito" value="<?php echo $cliente['limite_credito']; ?>" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Estado <span class="required">*</span></label>
                            <select name="estado" required>
                                <option value="activo" <?php echo $cliente['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $cliente['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Notas</label>
                            <textarea name="notas"><?php echo isset($cliente['notas']) ? htmlspecialchars($cliente['notas']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="gestion_clientes.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php sqlsrv_close($conn); ?>