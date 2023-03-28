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
    - Forma de pago 
    - Número de registros (Número de registros a facturar cada que se ejecuta el cron).
    - Fecha para generar facturas para público en general
    - Hora de inicio para la Facturación de público en general
4. Agregar permisos en modulo ISS
    - View iss Block
    - View the ISS form.

#### Lista de pagos

La lista de pagos muestra los pagos recurrentes recibidos de las suscripciones contratadas.
Para poder visualizar la lista de pagos:
1. Ir a /admin/config/services/iss/list (de forma predetermindada muestra los pagos recibidos del mes actual).
2. Seleccionar la fecha de inicio y fin para la consulta.
3. Da clic en el botón "Filter" para realizar la búsqueda entre las fechas seleccionadas.

La tabla muestra los siguientes datos:
- Folio: Folio del pago
- Subscription ID: ID de la suscripción de paypal
- Plan: Nombre del plan contratado
- Total price: Precio del plan
- Payment type: Tipo de pago
- Date: Fecha del pago
- Email: Email del usuario para enviar la factura generada
- Invoice: Estado de la factura En espera/Facturado
- Type: Tipo de factura generada RFC/Público en general
- Event ID: Id del evento del Webhook paypal