<?php
/**
 * Script para ejecución automática (CRON)
 * Envía el resumen de cobros a Telegram
 */

// Cambiar al directorio del proyecto para que los includes funcionen
chdir(__DIR__ . '/..');

require_once 'conexion.php';
require_once 'includes/notificaciones_helper.php';

echo "--- Iniciando envío de notificaciones Telegram ---\n";
$resultado = enviarAlertasCobroTelegram($conn);

if ($resultado === "Sin pendientes") {
    echo "No hay cobros pendientes para notificar hoy.\n";
} elseif ($resultado) {
    echo "Resumen enviado correctamente a Telegram.\n";
} else {
    echo "Error: El bot de Telegram no está configurado correctamente en el sistema.\n";
}
echo "--- Proceso finalizado ---\n";
?>
