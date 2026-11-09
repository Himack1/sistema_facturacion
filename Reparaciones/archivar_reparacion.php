<?php
session_start();
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

if($id > 0 && ($accion === 'archivar' || $accion === 'restaurar')) {
    $nuevo_estado = ($accion === 'archivar') ? 1 : 0;
    
    $sql = "UPDATE reparaciones SET archivado = ? WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($nuevo_estado, $id));
    
    if($stmt) {
        if($accion === 'archivar') {
            header("Location: index.php?msg=archivado");
        } else {
            header("Location: papelera.php?msg=restaurado");
        }
        exit();
    } else {
        die("Error de base de datos al cambiar el estado.");
    }
} else {
    header("Location: index.php");
    exit();
}
?>
