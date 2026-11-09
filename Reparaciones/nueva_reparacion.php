<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$error = '';
$success = '';

// Obtener clientes para el selector
$sql_clientes = "SELECT id, nombre, documento, telefono FROM clientes ORDER BY nombre ASC";
$stmt_clientes = sqlsrv_query($conn, $sql_clientes);
$opciones_clientes = '';
while($c = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)) {
    $opciones_clientes .= "<option value='{$c['id']}'>" . htmlspecialchars($c['nombre'] . ' - ' . $c['documento']) . "</option>";
}

// Obtener inventario (Piezas / Productos)
$sql_prod = "SELECT p.id, p.nombre, p.precio_venta, p.stock_actual, c.nombre as categoria_nombre 
             FROM productos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.estado = 'activo' AND p.stock_actual > 0";
$stmt_prod = sqlsrv_query($conn, $sql_prod);
$productos = [];
if($stmt_prod !== false) {
    while($prod = sqlsrv_fetch_array($stmt_prod, SQLSRV_FETCH_ASSOC)) {
        $productos[] = $prod;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $tipo_equipo = trim($_POST['tipo_equipo']);
    $marca_modelo = trim($_POST['marca_modelo']);
    $serial_imei = trim($_POST['serial_imei']);
    $problema_reportado = trim($_POST['problema_reportado']);
    $contrasena_pin = trim($_POST['contrasena_pin']);
    $condicion_fisica = trim($_POST['condicion_fisica']);
    $costo_estimado = isset($_POST['costo_estimado']) && $_POST['costo_estimado'] !== '' ? floatval($_POST['costo_estimado']) : null;
    
    if ($cliente_id <= 0 || empty($tipo_equipo) || empty($marca_modelo) || empty($problema_reportado)) {
        $error = "Por favor, complete todos los campos obligatorios.";
    } else {
        // Generar código de ticket único (REP-YYMMDD-XXXX)
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        $codigo_ticket = 'REP-' . date('ymd') . '-' . $random;
        
        $sql_insert = "INSERT INTO reparaciones 
            (codigo_ticket, cliente_id, usuario_id, tipo_equipo, marca_modelo, serial_imei, problema_reportado, contrasena_pin, condicion_fisica, costo_estimado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
            SELECT SCOPE_IDENTITY() AS id;";
            
        $params = array(
            $codigo_ticket, $cliente_id, $usuario_id, $tipo_equipo, $marca_modelo, 
            $serial_imei, $problema_reportado, $contrasena_pin, $condicion_fisica, $costo_estimado
        );
        
        $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);
        if ($stmt_insert) {
            sqlsrv_next_result($stmt_insert);
            $row = sqlsrv_fetch_array($stmt_insert, SQLSRV_FETCH_ASSOC);
            $nuevo_id = $row['id'];
            
            // Insertar piezas opcionales cotizadas
            $productos_ids = isset($_POST['productos_ids']) ? $_POST['productos_ids'] : [];
            $cantidades = isset($_POST['cantidades']) ? $_POST['cantidades'] : [];
            $precios = isset($_POST['precios']) ? $_POST['precios'] : [];
            
            for ($i = 0; $i < count($productos_ids); $i++) {
                $pid = intval($productos_ids[$i]);
                $cant = intval($cantidades[$i]);
                $prec = floatval($precios[$i]);
                if($pid > 0 && $cant > 0) {
                    $sql_p = "INSERT INTO reparacion_piezas (reparacion_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)";
                    sqlsrv_query($conn, $sql_p, array($nuevo_id, $pid, $cant, $prec));
                }
            }
            
            // Redirigir a la constancia
            header("Location: imprimir_constancia.php?id=$nuevo_id");
            exit();
        } else {
            $error = "Error al guardar el registro en la base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibir Equipo - Servicio Técnico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-volver { background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-card h2 { color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #f59e0b; }
        
        .btn-submit { background: #10b981; color: white; padding: 14px 20px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-submit:hover { background: #059669; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-error { background: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444; }
        
        .prod-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .prod-row input { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-remove:hover { background: #dc2626; }
    </style>
</head>
<body>
    <header class="header">
        <h1>📥 Recibir Equipo Nuevo</h1>
        <a href="index.php" class="btn-volver">← Volver a la Lista</a>
    </header>

    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2>Información de Recepción</h2>
            <form method="POST" action="">
                
                <div class="form-group">
                    <label>Cliente (Dueño del Equipo) *</label>
                    <select name="cliente_id" required>
                        <option value="">-- Seleccione un cliente --</option>
                        <?php echo $opciones_clientes; ?>
                    </select>
                    <small style="color: #64748b; margin-top: 5px; display: block;"><a href="../Gestion_Clientes/crear_clientes.php" target="_blank" style="color: #3b82f6;">+ Crear cliente nuevo si no existe</a></small>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Tipo de Equipo *</label>
                        <select name="tipo_equipo" required>
                            <option value="Celular">Celular / Smartphone</option>
                            <option value="Laptop">Laptop / Computadora</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Consola">Consola de Videojuegos</option>
                            <option value="Smartwatch">Smartwatch</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marca y Modelo *</label>
                        <input type="text" name="marca_modelo" required placeholder="Ej. iPhone 13 Pro Max">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Serial / IMEI (Opcional, muy recomendado)</label>
                        <input type="text" name="serial_imei" placeholder="Número de serie o IMEI">
                    </div>
                    <div class="form-group">
                        <label>Contraseña o PIN (Para revisión)</label>
                        <input type="text" name="contrasena_pin" placeholder="Ej. 1234, o patrón">
                    </div>
                </div>

                <div class="form-group">
                    <label>Problema Reportado por el Cliente *</label>
                    <textarea name="problema_reportado" rows="3" required placeholder="Ej. El teléfono no enciende después de mojarse..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Condición Física (Golpes, rayones, accesorios incluidos)</label>
                    <textarea name="condicion_fisica" rows="2" placeholder="Ej. Pantalla estrellada esquina superior, incluye cover negro, sin cargador..."></textarea>
                </div>
                
                <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;">

                <div class="form-group">
                    <label>Cotizar Piezas (Opcional - Buscador)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="buscador_producto" placeholder="Buscar por nombre o categoría..." list="lista_productos" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); agregarPieza(); return false; }">
                        <datalist id="lista_productos">
                            <?php foreach($productos as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['nombre'] ?? ''); ?>" data-id="<?php echo $p['id']; ?>" data-precio="<?php echo $p['precio_venta']; ?>" data-stock="<?php echo $p['stock_actual']; ?>">
                                    <?php echo htmlspecialchars($p['categoria_nombre'] ?? 'Sin Categoría'); ?> - Disp: <?php echo $p['stock_actual']; ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                        <button type="button" onclick="agregarPieza()" style="background: #3b82f6; color: white; border: none; padding: 0 15px; border-radius: 8px; cursor: pointer; font-weight: bold;">Agregar</button>
                    </div>
                </div>

                <div id="lista_piezas">
                    <!-- Aquí se insertan las piezas dinámicamente -->
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>Costo Mano de Obra (RD$) (Opcional)</label>
                    <input type="number" id="costo_mano_obra" step="0.01" min="0" placeholder="0.00" oninput="calcularTotal()">
                </div>

                <div class="form-group" style="background: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #fde68a;">
                    <label style="color: #b45309; font-size: 16px;">Gran Total / Costo Estimado Final (RD$)</label>
                    <input type="number" name="costo_estimado" id="costo_estimado" step="0.01" min="0" placeholder="0.00">
                    <small style="color: #92400e;">Puedes escribir el total manualmente, o se calculará automáticamente si agregas piezas/mano de obra.</small>
                </div>

                <div id="hidden_inputs"></div>
                <button type="button" onclick="enviarFormulario()" class="btn-submit">💾 Guardar y Generar Constancia</button>
            </form>
        </div>
    </div>

    <script>
        let piezas = [];

        function agregarPieza() {
            const input = document.getElementById('buscador_producto');
            const datalist = document.getElementById('lista_productos');
            const val = input.value.trim();
            if(val === '') return false;
            
            let selectedOption = null;
            for (let i = 0; i < datalist.options.length; i++) {
                if (datalist.options[i].value === val) {
                    selectedOption = datalist.options[i];
                    break;
                }
            }

            if(selectedOption) {
                const id = selectedOption.getAttribute('data-id');
                const precio = parseFloat(selectedOption.getAttribute('data-precio'));
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                
                let existe = piezas.find(p => p.id === id);
                if(existe) {
                    if(existe.cantidad < stock) existe.cantidad++;
                    else {
                        alert("No hay más stock disponible de esta pieza.");
                        return false;
                    }
                } else {
                    piezas.push({ id: id, nombre: val, precio: precio, cantidad: 1, max_stock: stock });
                }
                
                input.value = '';
                renderPiezas();
                return true;
            } else {
                alert("Seleccione un producto válido de la lista.");
                return false;
            }
        }

        function removerPieza(id) {
            piezas = piezas.filter(p => p.id !== id);
            renderPiezas();
        }

        function actualizarCantidad(id, val) {
            let p = piezas.find(p => p.id === id);
            if(p) {
                let cant = parseInt(val);
                if(cant > p.max_stock) {
                    alert("Stock insuficiente. Máximo: " + p.max_stock);
                    cant = p.max_stock;
                }
                if(cant < 1) cant = 1;
                p.cantidad = cant;
                renderPiezas();
            }
        }

        function renderPiezas() {
            const container = document.getElementById('lista_piezas');
            container.innerHTML = '';
            
            piezas.forEach(p => {
                let subt = (p.precio * p.cantidad).toFixed(2);
                container.innerHTML += `
                    <div class="prod-row">
                        <div style="flex-grow: 1; font-weight: bold; font-size: 14px;">${p.nombre}</div>
                        <div>
                            <span style="font-size: 12px; color: #64748b;">Cant:</span>
                            <input type="number" value="${p.cantidad}" min="1" max="${p.max_stock}" style="width: 60px;" onchange="actualizarCantidad('${p.id}', this.value)">
                        </div>
                        <div style="width: 100px; text-align: right; font-weight: bold; color: #0f172a;">RD$ ${subt}</div>
                        <button type="button" class="btn-remove" onclick="removerPieza('${p.id}')">X</button>
                    </div>
                `;
            });
            calcularTotal();
        }

        function calcularTotal() {
            let costoManoObra = parseFloat(document.getElementById('costo_mano_obra').value) || 0;
            let totalPiezas = piezas.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
            
            let granTotal = costoManoObra + totalPiezas;
            
            // Si hay mano de obra o piezas, auto-calcula el costo estimado
            if (costoManoObra > 0 || totalPiezas > 0) {
                document.getElementById('costo_estimado').value = granTotal.toFixed(2);
            }
        }

        function enviarFormulario() {
            const input = document.getElementById('buscador_producto');
            if (input.value.trim() !== '') {
                if (agregarPieza() === false) return;
            }

            const hiddenDiv = document.getElementById('hidden_inputs');
            hiddenDiv.innerHTML = '';
            
            piezas.forEach(p => {
                hiddenDiv.innerHTML += `
                    <input type="hidden" name="productos_ids[]" value="${p.id}">
                    <input type="hidden" name="cantidades[]" value="${p.cantidad}">
                    <input type="hidden" name="precios[]" value="${p.precio}">
                `;
            });
            
            document.forms[0].submit();
        }
    </script>
</body>
</html>
