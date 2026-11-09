<?php
/**
 * Procesar Creación de Cliente
 * Sistema de Facturación
 */

// Asegurar codificación UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: crear_clientes.php");
    exit();
}

require_once '../conexion.php';

// Obtener datos del formulario
$codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$tipo_documento = isset($_POST['tipo_documento']) ? trim($_POST['tipo_documento']) : null;
$documento = isset($_POST['documento']) ? trim($_POST['documento']) : null;

// DEBUG TEMPORAL: Verificar qué está recibiendo
// error_log("Tipo documento recibido: [" . $tipo_documento . "] Encoding: " . mb_detect_encoding($tipo_documento));

$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : null;
$celular = isset($_POST['celular']) ? trim($_POST['celular']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;
$ciudad = isset($_POST['ciudad']) ? trim($_POST['ciudad']) : null;
$provincia = isset($_POST['provincia']) ? trim($_POST['provincia']) : null;
$limite_credito = isset($_POST['limite_credito']) ? floatval($_POST['limite_credito']) : 0.00;
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'activo';
$notas = isset($_POST['notas']) ? trim($_POST['notas']) : null;

// Convertir strings vacíos a NULL
$tipo_documento = empty($tipo_documento) ? null : $tipo_documento;
$documento = empty($documento) ? null : $documento;
$telefono = empty($telefono) ? null : $telefono;
$celular = empty($celular) ? null : $celular;
$email = empty($email) ? null : $email;
$direccion = empty($direccion) ? null : $direccion;
$ciudad = empty($ciudad) ? null : $ciudad;
$provincia = empty($provincia) ? null : $provincia;
$notas = empty($notas) ? null : $notas;

// Validaciones básicas - Solo nombre y código son obligatorios
if(empty($codigo) || empty($nombre)) {
    header("Location: crear_clientes.php?error=empty");
    exit();
}

// Validar tipo de documento si se proporcionó
if($tipo_documento !== null && !in_array($tipo_documento, ['Cédula', 'RNC', 'Pasaporte', 'Otro', 'N/A'], true)) {
    header("Location: crear_clientes.php?error=tipo_documento");
    exit();
}

if(!in_array($estado, ['activo', 'inactivo'])) {
    $estado = 'activo';
}

// Verificar si el código ya existe
$sql_check_codigo = "SELECT COUNT(*) as total FROM clientes WHERE codigo = ?";
$params_check_codigo = array($codigo);
$stmt_check_codigo = sqlsrv_query($conn, $sql_check_codigo, $params_check_codigo);

if($stmt_check_codigo === false) {
    error_log("Error al verificar código: " . print_r(sqlsrv_errors(), true));
    header("Location: crear_clientes.php?error=database");
    exit();
}

$result_codigo = sqlsrv_fetch_array($stmt_check_codigo, SQLSRV_FETCH_ASSOC);

if($result_codigo['total'] > 0) {
    sqlsrv_free_stmt($stmt_check_codigo);
    header("Location: crear_clientes.php?error=existe_codigo");
    exit();
}

sqlsrv_free_stmt($stmt_check_codigo);

// Verificar si el documento ya existe SOLO si se proporcionó un documento
if($documento !== null) {
    $sql_check_doc = "SELECT COUNT(*) as total FROM clientes WHERE documento = ?";
    $params_check_doc = array($documento);
    $stmt_check_doc = sqlsrv_query($conn, $sql_check_doc, $params_check_doc);

    if($stmt_check_doc === false) {
        error_log("Error al verificar documento: " . print_r(sqlsrv_errors(), true));
        header("Location: crear_clientes.php?error=database");
        exit();
    }

    $result_doc = sqlsrv_fetch_array($stmt_check_doc, SQLSRV_FETCH_ASSOC);

    if($result_doc['total'] > 0) {
        sqlsrv_free_stmt($stmt_check_doc);
        header("Location: crear_clientes.php?error=existe_documento");
        exit();
    }

    sqlsrv_free_stmt($stmt_check_doc);
}

// Insertar cliente
$sql = "INSERT INTO clientes 
        (codigo, nombre, tipo_documento, documento, telefono, celular, email, 
         direccion, ciudad, provincia, limite_credito, estado, notas, usuario_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$usuario_id = $_SESSION['usuario_id'];
$params = array(
    $codigo, 
    $nombre, 
    $tipo_documento, 
    $documento, 
    $telefono, 
    $celular, 
    $email, 
    $direccion, 
    $ciudad, 
    $provincia, 
    $limite_credito, 
    $estado, 
    $notas, 
    $usuario_id
);

$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false) {
    error_log("Error al crear cliente: " . print_r(sqlsrv_errors(), true));
    header("Location: crear_clientes.php?error=database");
    exit();
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
header("Location: gestion_clientes.php?success=creado");
exit();
?>