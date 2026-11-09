<?php
//conexion.php

use Dom\CharacterData;

require_once 'config.php';

$serverName = "localhost,1433";  
$connectionOptions = array(
    "Database" => "sistema_facturacion",    
    "UID" => "sa",                
    "PWD" => "123456",      
    "CharacterSet" => "UTF-8"       
);

//establecer conexi0n
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));  
}

if($conn) {
    //echo "Conexión establecida.";
} else {
    echo "Conexión no se pudo establecer.";
}

// Función para obtener fecha/hora actual en formato dominicano
function fecha_hora_actual($formato = 'd/m/Y H:i:s') {
    return date($formato);
}

// Bloqueo de seguridad por licencia
require_once __DIR__ . '/includes/licencia_helper.php';
verificarLicencia($conn);
?>



