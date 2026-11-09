# 💼 Sistema de Facturación, Inventario y Punto de Venta (POS)

Sistema web integral de facturación comercial, control de inventario y punto de venta (POS) desarrollado en **PHP** y **Microsoft SQL Server**, diseñado para la gestión operativa y financiera de pequeñas y medianas empresas (PYMES).

---

## 🚀 Módulos y Características Principales

### 🛒 1. Facturación y Punto de Venta (POS)
- Emisión ágil de facturas y tickets de venta en tiempo real.
- Soporte para múltiples métodos de pago: Efectivo, Tarjeta, Transferencia y Crédito.
- Cálculo automático de impuestos (ITBIS), descuentos por ítem o generales.
- Generación de comprobantes para impresoras térmicas (formato POS) y estándar.

### 📦 2. Control de Inventario y Stock
- Catálogo de productos y servicios con categorías, código de barra y marcas.
- Control de existencias en tiempo real con alertas visuales de stock mínimo/agotado.
- Precios de compra, costo promedio y márgenes de ganancia configurables.

### 👥 3. Gestión de Clientes y Cuentas por Cobrar (Crédito)
- Directorio de clientes con registro de RNC / Cédula y datos de contacto.
- Sistema de ventas a crédito con límites asignados.
- Control de abonos, seguimiento de cuotas vencidas y estados de cuenta por cliente.

### 🔧 4. Módulo de Servicios y Reparaciones Técnicas
- Recepción de equipos con número de orden de servicio.
- Seguimiento por estados: *Recibido*, *En Diagnóstico*, *En Reparación*, *Listo* y *Entregado*.
- Vinculación de repuestos y mano de obra a la factura final del cliente.

### 📊 5. Reportes Financieros y Dashboard
- Panel de control interactivo con métricas de ventas del día, semana y mes.
- Gráficos estadísticos de rendimiento de ventas y flujo de caja.
- Historial detallado de facturas emitidas, anulaciones y cierres de caja.

### ⚙️ 6. Administración y Seguridad
- Control de acceso por roles de usuario (Administrador, Cajero, Soporte Técnico).
- Configuración institucional: logotipo, datos fiscales de la empresa y moneda.
- Sistema de copias de seguridad de la base de datos SQL Server (`.bak`).

---

## 🛠️ Tecnologías Utilizadas

- **Lenguaje Backend:** PHP 8.x
- **Base de Datos:** Microsoft SQL Server (Driver `sqlsrv` / `pdo_sqlsrv`)
- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5, FontAwesome
- **Servidor Web:** Apache 2.4 (WampServer)

---

## 💻 Requisitos de Instalación

1. **Servidor Web local:** WampServer, XAMPP o Apache con **PHP 8.0+**.
2. **Motor de Base de Datos:** Microsoft SQL Server (versión 2017, 2019 o superior).
3. **Controladores PHP:** Extensiones `php_sqlsrv` y `php_pdo_sqlsrv` habilitadas en `php.ini`.

---

## ⚙️ Configuración

1. Clonar el repositorio en la carpeta pública del servidor web:
   ```bash
   cd C:\wamp64\www\
   git clone https://github.com/Himack1/sistema_facturacion.git
   ```
2. Configurar la conexión a la base de datos en `conexion.php`:
   ```php
   $serverName = "localhost,1433";  
   $connectionOptions = array(
       "Database" => "sistema_facturacion",    
       "UID" => "sa",                
       "PWD" => "tu_contraseña",      
       "CharacterSet" => "UTF-8"       
   );
   ```
3. Iniciar el servidor web y acceder desde el navegador:
   ```text
   http://localhost/sistema_facturacion/login.php
   ```

---

## 👨‍💻 Autor

Desarrollado por **Himack1**  
GitHub: [@Himack1](https://github.com/Himack1)
