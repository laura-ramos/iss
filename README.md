# ISS
### Invoicing Simple Service

El módulo se conecta a la api de Factura Digital México para automatizar la facturación electrónica del sitio.
#### Instrucciones de configuración

1. Instalar y Activar modulo ISS
2. Agregar el bloques ISS Invoicing Block
3. Configurar el módulo
    Ir a /admin/config/services/iss/settings
    Agregar los datos de configuración
    - Endpoint
        Producción: https://app.facturadigital.com.mx 
        Sandbox: https://sandbox-app.facturadigital.com.mx 
    - Api key
        Para obtener la api key se debe de ingresar a https://app.facturadigital.com.mx
        Da clic en la opción Configuración-> Mis datos fiscales
        Copiar la API Key y pegalo en la configuración del sitio
    - Régimen Fiscal
    - Lugar de expedición
    - Serie 
    - Folio
    - Número de registros (Número de registros a facturar cada que se ejecuta el cron).
    - Fecha para generar facturas para publico en general
4. Agregar permisos en modulo ISS
    - View iss Block
    - View the ISS form.