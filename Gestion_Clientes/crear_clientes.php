<?php
/**
 * Crear Nuevo Cliente
 * Sistema de Facturación
 */

session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../conexion.php';

// Generar código automático
$sql_codigo = "SELECT dbo.fn_GenerarCodigoCliente() AS codigo";
$stmt_codigo = sqlsrv_query($conn, $sql_codigo);
$row_codigo = sqlsrv_fetch_array($stmt_codigo, SQLSRV_FETCH_ASSOC);
$codigo_sugerido = $row_codigo['codigo'];
sqlsrv_free_stmt($stmt_codigo);

$usuario_nombre = $_SESSION['usuario_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - Sistema de Facturación</title>
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
        }

        .btn-volver:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 900px;
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

        .form-section {
            margin-bottom: 35px;
        }

        .form-section-title {
            color: #667eea;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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

        .mensaje-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>➕ Nuevo Cliente</h1>
        <a href="gestion_clientes.php" class="btn-volver">← Volver</a>
    </header>

    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2>Registrar Nuevo Cliente</h2>
                <p>Completa los datos del cliente</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="mensaje-error">
                    <?php
                    switch($_GET['error']) {
                        case 'empty':
                            echo 'Por favor, complete todos los campos obligatorios.';
                            break;
                        case 'existe_codigo':
                            echo 'El código ya está en uso.';
                            break;
                        case 'existe_documento':
                            echo 'El documento ya está registrado.';
                            break;
                        default:
                            echo 'Ha ocurrido un error. Intenta nuevamente.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="procesar_crear_clientes.php" method="POST" id="formCliente">
                <!-- Información Básica -->
                <div class="form-section">
                    <div class="form-section-title">📋 Información Básica</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código <span class="required">*</span></label>
                            <input type="text" id="codigo" name="codigo" value="<?php echo $codigo_sugerido; ?>" required readonly>
                            <small>Generado automáticamente</small>
                        </div>

                        <div class="form-group">
                            <label for="nombre">Nombre / Razón Social <span class="required">*</span></label>
                            <input type="text" id="nombre" name="nombre" required autofocus>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_documento">Tipo de Documento</label>
                            <select id="tipo_documento" name="tipo_documento">
                                <option value="">-- Seleccionar --</option>
                                <option value="Cédula">Cédula</option>
                                <option value="RNC">RNC</option>
                                <option value="Pasaporte">Pasaporte</option>
                                <option value="N/A" selected>N/A (Sin documento)</option>
                                <option value="Otro">Otro</option>
                            </select>
                            <small>Opcional - Usar N/A para niños o ventas al contado</small>
                        </div>

                        <div class="form-group">
                            <label for="documento">Número de Documento</label>
                            <input type="text" id="documento" name="documento">
                            <small>Opcional - Dejar vacío si no aplica</small>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="form-section">
                    <div class="form-section-title">📞 Información de Contacto</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" placeholder="809-555-0000">
                        </div>

                        <div class="form-group">
                            <label for="celular">Celular</label>
                            <input type="text" id="celular" name="celular" placeholder="829-555-0000">
                        </div>

                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" id="email" name="email" placeholder="cliente@ejemplo.com">
                        </div>
                    </div>
                </div>

                <!-- Información de Ubicación -->
                <div class="form-section">
                    <div class="form-section-title">📍 Información de Ubicación</div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="direccion">Dirección</label>
                            <input type="text" id="direccion" name="direccion" placeholder="Calle, número, sector">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ciudad">Ciudad</label>
                            <input type="text" id="ciudad" name="ciudad" placeholder="Santiago">
                        </div>

                        <div class="form-group">
                            <label for="provincia">Provincia</label>
                            <input type="text" id="provincia" name="provincia" placeholder="Santiago">
                        </div>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="form-section">
                    <div class="form-section-title">💼 Información Adicional</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="limite_credito">Límite de Crédito (RD$)</label>
                            <input type="number" id="limite_credito" name="limite_credito" value="0.00" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="estado">Estado <span class="required">*</span></label>
                            <select id="estado" name="estado" required>
                                <option value="activo" selected>Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="notas">Notas</label>
                            <textarea id="notas" name="notas" placeholder="Información adicional del cliente..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="gestion_clientes.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('formCliente').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();

            if(nombre === '') {
                e.preventDefault();
                alert('Por favor, ingrese el nombre del cliente.');
                return;
            }
        });

        // Si cambia a N/A, limpiar el campo de documento
        document.getElementById('tipo_documento').addEventListener('change', function() {
            if(this.value === 'N/A') {
                document.getElementById('documento').value = '';
                document.getElementById('documento').placeholder = 'No aplica';
            } else {
                document.getElementById('documento').placeholder = '';
            }
        });
    </script>
</body>
</html>
<?php
sqlsrv_close($conn);
?>