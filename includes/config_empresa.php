<?php
/**
 * HELPER: Obtener configuración de la empresa
 * Incluir este archivo en cualquier página que necesite datos de la empresa.
 * 
 * Uso: require_once 'includes/config_empresa.php';
 *      echo $config_empresa['nombre_empresa'];
 */

function obtenerConfigEmpresa($conn) {
    $sql = "SELECT TOP 1 * FROM config_empresa WHERE id = 1";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return [
        'nombre_empresa' => 'Bradel',
        'rnc' => '000-00000-0',
        'telefono' => '829-367-0411',
        'direccion' => '',
        'email' => '',
        'sitio_web' => '',
        'logo_url' => '',
        'moneda' => 'RD$',
        'itbis_porcentaje' => 18.00,
        'aplicar_impuestos' => 0,
        'mensaje_factura' => 'Gracias por su compra'
    ];
    $config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $config ?: [];
}

// Verificar licencia automáticamente al incluir este archivo
require_once __DIR__ . '/licencia_helper.php';
if (isset($conn)) {
    verificarLicencia($conn);
}
?>
