<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit(); }
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) { header("Location: gestion_inventario.php"); exit(); }

// Obtener producto
$sql = "SELECT * FROM productos WHERE id = ? AND aplica_inventario = 1";
$stmt = sqlsrv_query($conn, $sql, array($id));
$prod = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$prod) { 
    header("Location: gestion_inventario.php?error=no_inventario"); 
    exit(); 
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_movimiento = trim($_POST['tipo_movimiento']);
    $cantidad = intval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    
    if($cantidad <= 0) {
        $error = "cantidad_invalida";
    } elseif($tipo_movimiento === 'salida' && $cantidad > $prod['stock_actual']) {
        $error = "sin_stock";
    } else {
        // Registrar movimiento usando el procedimiento almacenado
        $sql_mov = "EXEC sp_RegistrarMovimiento @producto_id = ?, @tipo_movimiento = ?, @cantidad = ?, @motivo = ?, @usuario_id = ?";
        $params = array($id, $tipo_movimiento, $cantidad, $motivo, $_SESSION['usuario_id']);
        
        if(sqlsrv_query($conn, $sql_mov, $params)) {
            header("Location: gestion_inventario.php?success=movimiento");
            exit();
        } else {
            $error = "database";
        }
    }
}

// Obtener últimos movimientos
$sql_mov = "SELECT TOP 5 m.*, u.usuario as usuario_nombre
            FROM movimientos_inventario m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.producto_id = ?
            ORDER BY m.fecha_movimiento DESC";
$stmt_mov = sqlsrv_query($conn, $sql_mov, array($id));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ajustar Stock</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .producto-info { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .producto-nombre h2 { color: #333; font-size: 24px; margin-bottom: 5px; }
        .producto-nombre p { color: #666; font-size: 14px; }
        .stock-actual { text-align: center; padding: 20px; background: #f8f9ff; border-radius: 8px; }
        .stock-actual-label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: 600; }
        .stock-actual-valor { font-size: 48px; font-weight: 700; color: #4caf50; }
        .form-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-section-title { color: #4caf50; font-size: 20px; font-weight: 600; margin-bottom: 25px; }
        .tipo-movimiento { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .tipo-option { position: relative; }
        .tipo-option input[type="radio"] { position: absolute; opacity: 0; }
        .tipo-option label { display: block; padding: 20px; border: 3px solid #e0e0e0; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s; font-weight: 600; }
        .tipo-option input[type="radio"]:checked + label { border-color: #4caf50; background: #e8f5e9; color: #2e7d32; }
        .tipo-option label:hover { border-color: #c0c0c0; }
        .tipo-icono { font-size: 32px; display: block; margin-bottom: 10px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #4caf50; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .mensaje-error { background: #ffebee; color: #c62828; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #f44336; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn { flex: 1; padding: 14px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); color: white; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .historial { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .historial h3 { margin-bottom: 20px; color: #333; }
        .movimiento-item { padding: 15px; border-left: 4px solid #e0e0e0; margin-bottom: 15px; background: #f8f9ff; border-radius: 5px; }
        .movimiento-item.entrada { border-left-color: #4caf50; }
        .movimiento-item.salida { border-left-color: #f44336; }
        .movimiento-item.ajuste { border-left-color: #ff9800; }
        .movimiento-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .movimiento-tipo { font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .movimiento-cantidad { font-size: 18px; font-weight: 700; }
        .movimiento-info { font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📊 Ajustar Stock</h1>
        <a href="gestion_inventario.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="producto-info">
            <div class="producto-nombre">
                <h2><?php echo htmlspecialchars($prod['nombre']); ?></h2>
                <p>Código: <?php echo htmlspecialchars($prod['codigo']); ?></p>
            </div>
            <div class="stock-actual">
                <div class="stock-actual-label">Stock Actual</div>
                <div class="stock-actual-valor"><?php echo $prod['stock_actual']; ?></div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-section-title">Registrar Movimiento de Inventario</div>

            <?php if(isset($error)): ?>
                <div class="mensaje-error">
                    <?php 
                    switch($error) {
                        case 'cantidad_invalida': echo 'La cantidad debe ser mayor a 0.'; break;
                        case 'sin_stock': echo 'No hay suficiente stock para realizar esta salida.'; break;
                        default: echo 'Error al registrar el movimiento.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="tipo-movimiento">
                    <div class="tipo-option">
                        <input type="radio" name="tipo_movimiento" id="entrada" value="entrada" required>
                        <label for="entrada">
                            <span class="tipo-icono">📥</span>
                            Entrada
                        </label>
                    </div>
                    <div class="tipo-option">
                        <input type="radio" name="tipo_movimiento" id="salida" value="salida" required>
                        <label for="salida">
                            <span class="tipo-icono">📤</span>
                            Salida
                        </label>
                    </div>
                    <div class="tipo-option">
                        <input type="radio" name="tipo_movimiento" id="ajuste" value="ajuste" required>
                        <label for="ajuste">
                            <span class="tipo-icono">⚙️</span>
                            Ajuste
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cantidad">Cantidad *</label>
                    <input type="number" name="cantidad" id="cantidad" min="1" required autofocus>
                </div>

                <div class="form-group">
                    <label for="motivo">Motivo *</label>
                    <textarea name="motivo" id="motivo" placeholder="Describe el motivo del movimiento..." required></textarea>
                </div>

                <div class="form-actions">
                    <a href="gestion_inventario.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
                </div>
            </form>
        </div>

        <?php if($stmt_mov && sqlsrv_has_rows($stmt_mov)): ?>
        <div class="historial">
            <h3>Últimos Movimientos</h3>
            <?php while($mov = sqlsrv_fetch_array($stmt_mov, SQLSRV_FETCH_ASSOC)): 
                $fecha = $mov['fecha_movimiento']->format('d/m/Y H:i');
            ?>
            <div class="movimiento-item <?php echo $mov['tipo_movimiento']; ?>">
                <div class="movimiento-header">
                    <span class="movimiento-tipo"><?php echo $mov['tipo_movimiento']; ?></span>
                    <span class="movimiento-cantidad">
                        <?php echo $mov['tipo_movimiento'] === 'salida' ? '-' : '+'; ?><?php echo $mov['cantidad']; ?>
                    </span>
                </div>
                <div class="movimiento-info">
                    <strong><?php echo htmlspecialchars($mov['motivo']); ?></strong><br>
                    <?php echo $fecha; ?> • <?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema'); ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php sqlsrv_close($conn); ?>