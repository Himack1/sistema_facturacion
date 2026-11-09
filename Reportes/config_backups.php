<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header("Location: ../index.php");
    exit();
}

require_once '../conexion.php';

// Obtener configuración actual
$sql = "SELECT * FROM config_backups WHERE id = 1";
$stmt = sqlsrv_query($conn, $sql);
$config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$config) {
    // Valores por defecto
    $config = [
        'frecuencia' => 'manual',
        'hora_ejecucion' => '23:00:00',
        'ultimo_backup' => null,
        'ruta_destino' => 'C:\\wamp64\\www\\Sistema de facturacion\\backups_db\\'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Backups BD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .config-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; margin-top: 30px; }
        .btn-backup { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; }
        .btn-backup:hover { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="config-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0"><i class="bi bi-database-check text-primary"></i> Backups de Base de Datos</h3>
                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Último Backup:</strong> 
                        <span id="txt-ultimo-backup">
                            <?php echo $config['ultimo_backup'] ? $config['ultimo_backup']->format('Y-m-d H:i:s') : 'Nunca'; ?>
                        </span>
                    </div>

                    <div class="card bg-light mb-4 border-0">
                        <div class="card-body text-center">
                            <h5 class="card-title">Backup Manual</h5>
                            <p class="text-muted small">Crea una copia de seguridad en este instante.</p>
                            <button class="btn btn-backup" id="btn-manual" onclick="realizarBackupManual()">
                                <i class="bi bi-cloud-arrow-down-fill"></i> Realizar Backup Ahora
                            </button>
                            <div id="manual-msg" class="mt-2 fw-bold"></div>
                        </div>
                    </div>

                    <h5 class="mb-3">Automatización (Cron Simulado)</h5>
                    <form id="form-config">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Frecuencia de Copia Automática</label>
                            <select class="form-select" name="frecuencia" id="frecuencia">
                                <option value="manual" <?php echo $config['frecuencia'] == 'manual' ? 'selected' : ''; ?>>Solo Manual (Desactivado)</option>
                                <option value="diario" <?php echo $config['frecuencia'] == 'diario' ? 'selected' : ''; ?>>Diario</option>
                                <option value="semanal" <?php echo $config['frecuencia'] == 'semanal' ? 'selected' : ''; ?>>Semanal (Lunes)</option>
                                <option value="mensual" <?php echo $config['frecuencia'] == 'mensual' ? 'selected' : ''; ?>>Mensual (Día 1)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="div-hora" style="<?php echo $config['frecuencia'] == 'manual' ? 'display: none;' : ''; ?>">
                            <label class="form-label fw-bold">Hora de Ejecución (Formato 24h)</label>
                            <input type="time" class="form-control" name="hora_ejecucion" value="<?php echo is_object($config['hora_ejecucion']) ? $config['hora_ejecucion']->format('H:i') : substr($config['hora_ejecucion'], 0, 5); ?>">
                            <div class="form-text">El sistema hará la copia la primera vez que alguien inicie sesión o cargue la página después de esta hora.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Carpeta de Destino (En el Servidor)</label>
                            <input type="text" class="form-control bg-light" name="ruta_destino" value="<?php echo htmlspecialchars($config['ruta_destino']); ?>" readonly>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 p-2 fw-bold" id="btn-guardar">
                            <i class="bi bi-save"></i> Guardar Configuración
                        </button>
                        <div id="save-msg" class="mt-2 text-center fw-bold"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const selectFrecuencia = document.getElementById('frecuencia');
        const divHora = document.getElementById('div-hora');

        selectFrecuencia.addEventListener('change', function() {
            if (this.value === 'manual') {
                divHora.style.display = 'none';
            } else {
                divHora.style.display = 'block';
            }
        });

        document.getElementById('form-config').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-guardar');
            const msg = document.getElementById('save-msg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
            
            const formData = new FormData(this);
            formData.append('accion', 'guardar_config');

            fetch('ajax/procesar_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.style.color = 'green';
                    msg.innerText = '¡Configuración guardada exitosamente!';
                } else {
                    msg.style.color = 'red';
                    msg.innerText = 'Error: ' + data.message;
                }
            })
            .catch(e => {
                msg.style.color = 'red';
                msg.innerText = 'Error de conexión.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save"></i> Guardar Configuración';
                setTimeout(() => msg.innerText = '', 4000);
            });
        });

        function realizarBackupManual() {
            if (!confirm('¿Deseas generar una copia de seguridad en este momento? Esto tomará unos segundos.')) return;
            
            const btn = document.getElementById('btn-manual');
            const msg = document.getElementById('manual-msg');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Realizando backup...';
            msg.innerText = '';
            msg.style.color = 'black';

            const formData = new FormData();
            formData.append('accion', 'ejecutar_backup');

            fetch('ajax/procesar_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.style.color = 'green';
                    msg.innerText = '¡Backup creado con éxito! Archivo: ' + data.archivo;
                    document.getElementById('txt-ultimo-backup').innerText = data.fecha;
                } else {
                    msg.style.color = 'red';
                    msg.innerText = 'Error: ' + data.message;
                }
            })
            .catch(e => {
                msg.style.color = 'red';
                msg.innerText = 'Error de conexión con el servidor.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill"></i> Realizar Backup Ahora';
            });
        }
    </script>
</body>
</html>
