<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit(); }
require_once '../conexion.php';

// Obtener categorías
$sql_cat = "SELECT id, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre";
$stmt_cat = sqlsrv_query($conn, $sql_cat);

// Generar código sugerido
$sql_codigo = "SELECT dbo.fn_GenerarCodigoProducto(1) AS codigo";
$stmt_codigo = sqlsrv_query($conn, $sql_codigo);
$row_codigo = sqlsrv_fetch_array($stmt_codigo, SQLSRV_FETCH_ASSOC);
$codigo_sugerido = $row_codigo['codigo'];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $descripcion = empty($_POST['descripcion']) ? null : trim($_POST['descripcion']);
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $tipo = trim($_POST['tipo']);
    $precio_venta = floatval($_POST['precio_venta']);
    $precio_costo = empty($_POST['precio_costo']) ? null : floatval($_POST['precio_costo']);
    $impuesto = ($_POST['impuesto'] === '') ? null : floatval($_POST['impuesto']);
    $aplica_inventario = ($tipo === 'producto') ? 1 : 0;
    $stock_actual = ($tipo === 'producto') ? intval($_POST['stock_actual']) : 0;
    $stock_minimo = ($tipo === 'producto') ? intval($_POST['stock_minimo']) : 0;
    $estado = trim($_POST['estado']);
    
    if(empty($codigo) || empty($nombre) || $precio_venta <= 0) {
        $error = "empty";
    } else {
        // Verificar código único
        $sql_check = "SELECT COUNT(*) as total FROM productos WHERE codigo = ?";
        $stmt_check = sqlsrv_query($conn, $sql_check, array($codigo));
        $result = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        
        if($result['total'] > 0) {
            $error = "existe_codigo";
        } else {
            // Manejo de la imagen
            $imagen = null;
            if(!empty($_POST['imagen_url'])) {
                $imagen = trim($_POST['imagen_url']);
            }
            if(isset($_FILES['imagen_file']) && $_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['imagen_file']['tmp_name'];
                $file_name = $_FILES['imagen_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                if(in_array($file_ext, $allowed_ext)) {
                    $new_name = uniqid('prod_') . '.' . $file_ext;
                    $dest_path = '../uploads/productos/' . $new_name;
                    if(move_uploaded_file($file_tmp, $dest_path)) {
                        $imagen = 'uploads/productos/' . $new_name;
                    }
                }
            }

            $sql = "INSERT INTO productos (codigo, nombre, descripcion, categoria_id, tipo, precio_venta, precio_costo, impuesto, stock_actual, stock_minimo, aplica_inventario, estado, usuario_registro, imagen) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array($codigo, $nombre, $descripcion, $categoria_id, $tipo, $precio_venta, $precio_costo, $impuesto, $stock_actual, $stock_minimo, $aplica_inventario, $estado, $_SESSION['usuario_id'], $imagen);
            
            if(sqlsrv_query($conn, $sql, $params)) {
                header("Location: gestion_inventario.php?success=creado");
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
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Nuevo Producto - Sistema de Facturación</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .form-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 35px; }
        .form-section-title { color: #667eea; font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group small { display: block; margin-top: 6px; color: #888; font-size: 13px; }
        .required { color: #f44336; }
        .mensaje-error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #f44336; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 30px; border-top: 2px solid #e0e0e0; }
        .btn { flex: 1; padding: 14px; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .full-width { grid-column: 1 / -1; }
        .hidden { display: none; }
        .img-preview { max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #888; overflow: hidden; margin-top: 10px; }
        .img-preview img { width: 100%; height: 100%; object-fit: cover; }
        /* Estilos de código y barras */
        .codigo-group { display: flex; gap: 8px; align-items: flex-end; }
        .codigo-group input { flex: 1; }
        .btn-generar-codigo { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 12px 16px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600; white-space: nowrap; transition: opacity 0.2s; }
        .btn-generar-codigo:hover { opacity: 0.85; }
        .barcode-preview { margin-top: 20px; padding: 20px; border: 2px dashed #e0e0e0; border-radius: 8px; text-align: center; display: none; }
        .barcode-preview.visible { display: block; }
        .barcode-preview p { color: #666; font-size: 13px; margin-top: 8px; }
        .btn-print-barcode { margin-top: 10px; background: #4caf50; color: white; border: none; padding: 8px 18px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <header class="header">
        <h1>➕ Nuevo Producto/Servicio</h1>
        <a href="gestion_inventario.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="form-card">
            <h2 style="color: #333; margin-bottom: 10px;">Registrar Producto o Servicio</h2>
            <p style="color: #666; margin-bottom: 30px;">Completa la información del producto o servicio</p>

            <?php if(isset($error)): ?>
                <div class="mensaje-error">
                    <?php 
                    echo $error === 'empty' ? 'Complete los campos obligatorios.' : 
                         ($error === 'existe_codigo' ? 'El código ya existe.' : 'Error al guardar.');
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formProducto" enctype="multipart/form-data">
                <div class="form-section">
                    <div class="form-section-title">📋 Información Básica</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Código <span class="required">*</span></label>
                            <div class="codigo-group">
                                <input type="text" name="codigo" id="codigo" value="<?php echo $codigo_sugerido; ?>" required placeholder="Ej: PRD-001">
                                <button type="button" class="btn-generar-codigo" onclick="generarCodigo()">⚡ Generar</button>
                            </div>
                            <small>Código único y escaneable. Se genera automáticamente.</small>
                        </div>

                        <div class="form-group">
                            <label>Tipo <span class="required">*</span></label>
                            <select name="tipo" id="tipo" required>
                                <option value="producto" selected>📦 Producto (maneja stock)</option>
                                <option value="servicio">🛠️ Servicio (sin stock)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Categoría</label>
                            <select name="categoria_id" id="categoria_id" onchange="generarCodigo()">
                                <option value="">Sin categoría</option>
                                <?php while($cat = sqlsrv_fetch_array($stmt_cat, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Nombre <span class="required">*</span></label>
                            <input type="text" name="nombre" required autofocus>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <textarea name="descripcion"></textarea>
                        </div>
                    </div>

                    <!-- Vista previa código de barras -->
                    <div class="barcode-preview" id="barcodePreview">
                        <svg id="barcodeCanvas"></svg>
                        <p id="barcodeLabel"></p>
                        <button type="button" class="btn-print-barcode" onclick="imprimirCodigoBarras()">🖨️ Imprimir Código de Barras</button>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">🖼️ Imagen del Producto</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subir Imagen (PC)</label>
                            <input type="file" name="imagen_file" id="imagen_file" accept="image/jpeg, image/png, image/webp, image/gif">
                            <small>Formatos: JPG, PNG, WEBP. Prioridad sobre la URL.</small>
                        </div>
                        <div class="form-group">
                            <label>O URL de Internet</label>
                            <input type="url" name="imagen_url" id="imagen_url" placeholder="https://ejemplo.com/imagen.jpg">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Vista Previa</label>
                            <div class="img-preview" id="img_preview">
                                <span>Sin imagen</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">💰 Precios</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio de Venta (RD$) <span class="required">*</span></label>
                            <input type="number" name="precio_venta" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Precio de Costo (RD$)</label>
                            <input type="number" name="precio_costo" step="0.01" min="0">
                            <small>Opcional</small>
                        </div>

                        <div class="form-group">
                            <label>Impuesto (%)</label>
                            <input type="number" name="impuesto" step="0.01" min="0" value="" placeholder="Ejem: 18">
                            <small>Vacio = Usa ITBIS Global de la empresa</small>
                        </div>
                    </div>
                </div>

                <div class="form-section" id="seccion-inventario">
                    <div class="form-section-title">📦 Control de Inventario</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock Inicial</label>
                            <input type="number" name="stock_actual" id="stock_actual" min="0" value="0">
                        </div>

                        <div class="form-group">
                            <label>Stock Mínimo</label>
                            <input type="number" name="stock_minimo" id="stock_minimo" min="0" value="5">
                            <small>Alerta de reabastecimiento</small>
                        </div>

                        <div class="form-group">
                            <label>Estado <span class="required">*</span></label>
                            <select name="estado" required>
                                <option value="activo" selected>Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="gestion_inventario.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('tipo').addEventListener('change', function() {
            const seccionInventario = document.getElementById('seccion-inventario');
            const stockActual = document.getElementById('stock_actual');
            const stockMinimo = document.getElementById('stock_minimo');
            
            if(this.value === 'servicio') {
                seccionInventario.classList.add('hidden');
                stockActual.value = 0;
                stockMinimo.value = 0;
            } else {
                seccionInventario.classList.remove('hidden');
            }
        });

        // Lógica de vista previa de imagen
        const inputFile = document.getElementById('imagen_file');
        const inputUrl = document.getElementById('imagen_url');
        const imgPreview = document.getElementById('img_preview');

        function setPreview(url) {
            imgPreview.innerHTML = `<img src="${url}" alt="Vista previa" onerror="this.onerror=null; this.parentElement.innerHTML='<span>Error al cargar imagen</span>';">`;
            imgPreview.style.border = "none";
        }

        inputFile.addEventListener('change', function() {
            const file = this.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    setPreview(e.target.result);
                }
                reader.readAsDataURL(file);
            } else if(inputUrl.value) {
                setPreview(inputUrl.value);
            } else {
                imgPreview.innerHTML = '<span>Sin imagen</span>';
                imgPreview.style.border = "2px dashed #ccc";
            }
        });

        inputUrl.addEventListener('input', function() {
            if(!inputFile.files[0] && this.value) {
                setPreview(this.value);
            } else if(!inputFile.files[0] && !this.value) {
                imgPreview.innerHTML = '<span>Sin imagen</span>';
                imgPreview.style.border = "2px dashed #ccc";
            }
        });

        // ===== CÓDIGO DE BARRAS: Generar y previsualizar =====
        const codigoInput = document.getElementById('codigo');

        function renderBarcode(code) {
            if(!code || code.length < 3) return;
            try {
                JsBarcode('#barcodeCanvas', code, {
                    format: 'CODE128',
                    lineColor: '#1a1a2e',
                    width: 2,
                    height: 60,
                    displayValue: true,
                    fontSize: 14,
                    margin: 10
                });
                document.getElementById('barcodeLabel').textContent = 'Código: ' + code;
                document.getElementById('barcodePreview').classList.add('visible');
            } catch(e) { /* código no válido aún */ }
        }

        // Renderizar al cargar si hay código sugerido
        window.addEventListener('load', function() {
            const code = codigoInput.value.trim();
            if(code) renderBarcode(code);
        });

        // Actualizar barcode en tiempo real mientras se escribe
        codigoInput.addEventListener('input', function() {
            renderBarcode(this.value.trim());
        });

        // Generar código vía AJAX según categoría
        function generarCodigo() {
            const catId = document.getElementById('categoria_id').value || 1;
            fetch('generar_codigo.php?categoria_id=' + catId)
                .then(r => r.json())
                .then(data => {
                    if(data.codigo) {
                        codigoInput.value = data.codigo;
                        renderBarcode(data.codigo);
                    }
                })
                .catch(() => alert('Error generando código'));
        }

        // Imprimir código de barras
        function imprimirCodigoBarras() {
            const nombre = document.querySelector('input[name="nombre"]').value || 'Producto';
            const codigo = codigoInput.value;
            const svgContent = document.getElementById('barcodeCanvas').outerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html><html><head>
                <title>Código de Barras - ${nombre}</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }
                    h2 { font-size: 18px; margin-bottom: 5px; }
                    p { color: #666; font-size: 14px; }
                    svg { margin: 20px auto; display: block; }
                </style></head><body onload="window.print()">
                <h2>${nombre}</h2>
                <p>${codigo}</p>
                ${svgContent}
                </body></html>
            `);
            printWindow.document.close();
        }
    </script>
</body>
</html>
<?php sqlsrv_close($conn); ?>