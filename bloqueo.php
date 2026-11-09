<?php
/**
 * PÁGINA DE BLOQUEO POR LICENCIA
 * Ubicación: bloqueo.php
 */
session_start();
require_once 'conexion.php';

// Tus datos de contacto como desarrollador
$dev_nombre = "Bradel";
$dev_telefono = "829-367-0411";

// Si por alguna razón la licencia vuelve a estar activa, dejarlo salir
$check = "SELECT licencia_activa, fecha_vencimiento, licencia_permanente FROM config_empresa WHERE id = 1";
$res = sqlsrv_query($conn, $check);
$lic = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

$hoy = new DateTime();
$vencimiento = $lic['fecha_vencimiento'] ? new DateTime($lic['fecha_vencimiento']->format('Y-m-d')) : null;

if ($lic['licencia_activa'] == 1 && (!$vencimiento || $hoy <= $vencimiento)) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - Sistema de Facturación</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f8fafc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .block-card {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 450px;
            width: 90%;
            border-top: 8px solid #ef4444;
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        h1 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 24px;
        }
        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .contact-box {
            background: #fef2f2;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #fee2e2;
        }
        .contact-title {
            color: #b91c1c;
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }
        .phone {
            font-size: 20px;
            color: #1e293b;
            font-weight: 800;
            text-decoration: none;
        }
        .footer-note {
            margin-top: 30px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="block-card">
        <div class="icon">🔒</div>
        <h1>Acceso Restringido</h1>
        <p>Tu licencia de uso ha expirado o el acceso ha sido suspendido temporalmente. Por favor, regulariza tu situación para continuar utilizando el sistema.</p>
        
        <div class="contact-box">
            <span class="contact-title">PARA REACTIVAR CONTACTE A:</span>
            <div style="font-weight: 600; margin-bottom: 5px;"><?php echo htmlspecialchars($dev_nombre); ?></div>
            <a href="tel:<?php echo $dev_telefono; ?>" class="phone"><?php echo htmlspecialchars($dev_telefono); ?></a>
        </div>

        <div class="footer-note">
            Referencia: Error 402 - Payment Required
        </div>
    </div>
</body>
</html>
