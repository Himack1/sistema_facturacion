<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit(); }
require_once '../conexion.php';

// Procesar acciones
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['accion'])) {
        if($_POST['accion'] === 'crear') {
            $nombre = trim($_POST['nombre']);
            $descripcion = empty($_POST['descripcion']) ? null : trim($_POST['descripcion']);
            $estado = trim($_POST['estado']);
            
            if(!empty($nombre)) {
                $sql = "INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, ?)";
                sqlsrv_query($conn, $sql, array($nombre, $descripcion, $estado));
                header("Location: gestion_categorias.php?success=creada");
                exit();
            }
        } elseif($_POST['accion'] === 'editar') {
            $id = intval($_POST['id']);
            $nombre = trim($_POST['nombre']);
            $descripcion = empty($_POST['descripcion']) ? null : trim($_POST['descripcion']);
            $estado = trim($_POST['estado']);
            
            $sql = "UPDATE categorias SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?";
            sqlsrv_query($conn, $sql, array($nombre, $descripcion, $estado, $id));
            header("Location: gestion_categorias.php?success=editada");
            exit();
        } elseif($_POST['accion'] === 'eliminar') {
            $id = intval($_POST['id']);
            // Verificar si tiene productos
            $sql_check = "SELECT COUNT(*) as total FROM productos WHERE categoria_id = ?";
            $stmt_check = sqlsrv_query($conn, $sql_check, array($id));
            $result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
            
            if($result['total'] == 0) {
                $sql = "DELETE FROM categorias WHERE id = ?";
                sqlsrv_query($conn, $sql, array($id));
                header("Location: gestion_categorias.php?success=eliminada");
            } else {
                header("Location: gestion_categorias.php?error=tiene_productos");
            }
            exit();
        }
    }
}

// Obtener categorías con conteo de productos
$sql = "SELECT c.*, COUNT(p.id) as total_productos 
        FROM categorias c 
        LEFT JOIN productos p ON c.id = p.categoria_id 
        GROUP BY c.id, c.nombre, c.descripcion, c.estado, c.fecha_creacion 
        ORDER BY c.nombre";
$stmt = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Gestión de Categorías</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .top-bar { background: white; padding: 25px 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar h2 { color: #333; font-size: 28px; }
        .btn-nueva { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 12px 25px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .grid-categorias { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .categoria-card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .categoria-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .categoria-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .categoria-nombre { font-size: 20px; font-weight: 600; color: #333; }
        .categoria-descripcion { color: #666; font-size: 14px; margin-bottom: 15px; line-height: 1.5; }
        .categoria-stats { display: flex; gap: 15px; margin-bottom: 15px; }
        .stat-item { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #666; }
        .categoria-acciones { display: flex; gap: 8px; padding-top: 15px; border-top: 2px solid #f0f0f0; }
        .btn-accion { flex: 1; padding: 8px; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-align: center; }
        .btn-editar { background: #ff9800; color: white; }
        .btn-eliminar { background: #f44336; color: white; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge.activo { background: #e8f5e9; color: #2e7d32; }
        .badge.inactivo { background: #ffebee; color: #c62828; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 40px; border-radius: 10px; max-width: 500px; width: 90%; }
        .modal-header { margin-bottom: 25px; }
        .modal-header h3 { font-size: 24px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .modal-actions { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 12px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .mensaje { padding: 15px 20px; border-radius: 5px; margin-bottom: 20px; }
        .mensaje.exito { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .mensaje.error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📂 Gestión de Categorías</h1>
        <a href="gestion_inventario.php" class="btn-volver">← Volver al Inventario</a>
    </header>

    <div class="container">
        <?php if(isset($_GET['success'])): ?>
            <div class="mensaje exito">
                ✓ Categoría <?php echo htmlspecialchars($_GET['success']); ?> exitosamente.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error']) && $_GET['error'] === 'tiene_productos'): ?>
            <div class="mensaje error">
                ✕ No se puede eliminar. La categoría tiene productos asignados.
            </div>
        <?php endif; ?>

        <div class="top-bar">
            <h2>Categorías del Inventario</h2>
            <button class="btn-nueva" onclick="abrirModal('nueva')">+ Nueva Categoría</button>
        </div>

        <div class="grid-categorias">
            <?php while($cat = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
            <div class="categoria-card">
                <div class="categoria-header">
                    <div class="categoria-nombre"><?php echo htmlspecialchars($cat['nombre']); ?></div>
                    <span class="badge <?php echo $cat['estado']; ?>"><?php echo $cat['estado']; ?></span>
                </div>
                <div class="categoria-descripcion">
                    <?php echo $cat['descripcion'] ? htmlspecialchars($cat['descripcion']) : '<em style="color: #999;">Sin descripción</em>'; ?>
                </div>
                <div class="categoria-stats">
                    <div class="stat-item">
                        <span>📦</span>
                        <span><strong><?php echo $cat['total_productos']; ?></strong> productos</span>
                    </div>
                </div>
                <div class="categoria-acciones">
                    <button class="btn-accion btn-editar" onclick='editarCategoria(<?php echo json_encode($cat); ?>)'>✏️ Editar</button>
                    <?php if($cat['total_productos'] == 0): ?>
                        <button class="btn-accion btn-eliminar" onclick="eliminarCategoria(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['nombre']); ?>')">🗑️ Eliminar</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Nueva/Editar -->
    <div class="modal" id="modalCategoria">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">Nueva Categoría</h3>
            </div>
            <form method="POST" id="formCategoria">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="id">
                
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="descripcion"></textarea>
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="estado">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form para eliminar -->
    <form method="POST" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" id="eliminar_id">
    </form>

    <script>
        function abrirModal(tipo) {
            document.getElementById('modalTitulo').textContent = 'Nueva Categoría';
            document.getElementById('accion').value = 'crear';
            document.getElementById('formCategoria').reset();
            document.getElementById('modalCategoria').classList.add('active');
        }

        function editarCategoria(cat) {
            document.getElementById('modalTitulo').textContent = 'Editar Categoría';
            document.getElementById('accion').value = 'editar';
            document.getElementById('id').value = cat.id;
            document.getElementById('nombre').value = cat.nombre;
            document.getElementById('descripcion').value = cat.descripcion || '';
            document.getElementById('estado').value = cat.estado;
            document.getElementById('modalCategoria').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalCategoria').classList.remove('active');
        }

        function eliminarCategoria(id, nombre) {
            if(confirm('⚠️ ¿Eliminar la categoría "' + nombre + '"?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('eliminar_id').value = id;
                document.getElementById('formEliminar').submit();
            }
        }

        document.getElementById('modalCategoria').addEventListener('click', function(e) {
            if(e.target === this) cerrarModal();
        });

        setTimeout(() => {
            const msg = document.querySelector('.mensaje');
            if(msg) {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>
<?php sqlsrv_close($conn); ?>