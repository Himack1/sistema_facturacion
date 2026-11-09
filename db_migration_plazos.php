<?php
require 'conexion.php';
$output = [];

// 1. Alterar tabla facturas
$sql_alter = "
IF COL_LENGTH('facturas', 'es_a_plazos') IS NULL
BEGIN
    ALTER TABLE facturas ADD 
        es_a_plazos BIT DEFAULT 0,
        frecuencia_plazos VARCHAR(20) NULL,
        cantidad_cuotas INT NULL,
        interes_porcentaje DECIMAL(5,2) NULL,
        monto_inicial DECIMAL(10,2) NULL;
END
";
$stmt = sqlsrv_query($conn, $sql_alter);
if ($stmt === false) {
    $output[] = "Error alterando facturas: " . print_r(sqlsrv_errors(), true);
} else {
    $output[] = "Tabla facturas alterada correctamente.";
}

// 2. Crear tabla facturas_cuotas
$sql_create = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='facturas_cuotas' and xtype='U')
BEGIN
    CREATE TABLE facturas_cuotas (
        id INT IDENTITY(1,1) PRIMARY KEY,
        factura_id INT NOT NULL,
        numero_cuota INT NOT NULL,
        monto_cuota DECIMAL(10,2) NOT NULL,
        fecha_vencimiento DATE NOT NULL,
        estado VARCHAR(20) DEFAULT 'pendiente',
        monto_pagado DECIMAL(10,2) DEFAULT 0.00,
        fecha_pago DATETIME NULL,
        FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE
    );
END
";
$stmt2 = sqlsrv_query($conn, $sql_create);
if ($stmt2 === false) {
    $output[] = "Error creando facturas_cuotas: " . print_r(sqlsrv_errors(), true);
} else {
    $output[] = "Tabla facturas_cuotas creada correctamente.";
}

echo implode("<br>", $output);
?>
