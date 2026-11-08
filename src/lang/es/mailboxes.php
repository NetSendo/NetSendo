<?php

return [
    'title' => 'Buzones de correo',
    'created' => 'El buzón ha sido creado.',
    'updated' => 'El buzón ha sido actualizado.',
    'deleted' => 'El buzón ha sido eliminado.',
    'set_default' => 'Buzón configurado como predeterminado.',

    'types' => [
        'broadcast' => 'Única vez (Broadcast)',
        'autoresponder' => 'Autorespondedor (Cola)',
        'system' => 'Sistema',
    ],

    'bounce' => [
        'section_title' => 'Monitoreo de buzón de rebotes',
        'section_desc' => 'Monitorea un buzón IMAP dedicado para correos de rebote y marca automáticamente a los suscriptores como rebotados.',
        'enabled' => 'Activar monitoreo de rebotes',
        'imap_host' => 'Host IMAP',
        'imap_port' => 'Puerto IMAP',
        'imap_encryption' => 'Cifrado',
        'imap_username' => 'Usuario IMAP',
        'imap_password' => 'Contraseña IMAP',
        'imap_folder' => 'Carpeta IMAP',
        'test_connection' => 'Probar conexión IMAP',
        'test_success' => 'Conexión exitosa al buzón de rebotes',
        'test_failed' => 'Error al conectar con el buzón de rebotes',
        'last_scanned' => 'Último escaneo',
        'last_scan_count' => 'Rebotes encontrados en el último escaneo',
        'never_scanned' => 'Nunca escaneado',
        'processed' => ':count rebote(s) procesado(s)',
    ],

    'bounce_webhooks' => [
        'token_generated' => 'Se ha generado el token del webhook. Pégalo en tu proveedor: cualquier token anterior ya no funciona.',
        'invalid_sendgrid_key' => 'Esta no es una clave de verificación de SendGrid válida. Copia la clave pública que SendGrid muestra al activar Signed Event Webhook.',
    ],

    'replies' => [
        'test_success' => 'Conexión exitosa al buzón de respuestas',
        'test_failed' => 'Error al conectar con el buzón de respuestas',
        'not_configured' => 'La detección de respuestas no está configurada. Guarda la configuración del buzón e inténtalo de nuevo.',
    ],

    'custom_headers' => [
        'section_title' => 'Encabezados SMTP personalizados',
        'section_desc' => 'Agrega encabezados personalizados a todos los correos enviados desde este servidor (ej. Feedback-ID para Google Postmaster Tools).',
        'key' => 'Nombre del encabezado',
        'value' => 'Valor del encabezado',
        'add' => 'Agregar encabezado',
        'remove' => 'Eliminar',
        'key_placeholder' => 'ej. Feedback-ID',
        'value_placeholder' => 'ej. campaign1:user123:netsendo',
    ],

    // NetSendo MTA (NMI) mailboxes
    'nmi' => [
        'disabled' => 'El servidor de envío está desactivado. Un administrador lo activa en Infraestructura de correo (la guía paso a paso).',
        'new_mailboxes_disabled' => 'Las nuevas cuentas de correo NetSendo MTA están desactivadas en esta instalación (NMI_ALLOW_NEW en .env).',
        'domain_required' => 'Elija uno de sus dominios para NetSendo MTA.',
        'ip_required' => 'Elija una de sus IP dedicadas para NetSendo MTA.',
        'domain_not_verified' => 'El dominio :domain aún no está verificado. Añada primero su registro CNAME en Deliverability Shield.',
        'domain_not_enabled' => 'NetSendo MTA no está activado para :domain. Actívelo en la página del dominio en Deliverability Shield.',
        'ip_not_assigned' => 'La IP :ip no está asignada a :domain. Asígnela en la página de la IP en Infraestructura de Correo.',
        'ip_no_dkim' => 'La IP :ip aún no tiene claves DKIM. Genérelas en la página de la IP en Infraestructura de Correo.',
        'ip_inactive' => 'La IP :ip está desactivada.',
        'from_email_domain' => 'La dirección del remitente debe pertenecer a :domain o a uno de sus subdominios, para que coincida con la firma DKIM.',
        'no_domain' => 'La cuenta de correo ":mailbox" no tiene dominio de NetSendo MTA. Edítela y elija un dominio y una IP.',
        'test' => [
            'not_verified' => 'El dominio :domain no está verificado. Añada primero su registro CNAME en Deliverability Shield.',
            'not_enabled' => 'NetSendo MTA está desactivado para :domain.',
            'no_credentials' => 'El dominio :domain no tiene usuario de NetSendo MTA. Desactive y vuelva a activar NetSendo MTA en la página del dominio.',
            'warming_limit' => 'Se alcanzó el límite diario de calentamiento de esta IP. Inténtelo de nuevo mañana.',
            'success' => 'La conexión funciona: se envió un correo de prueba a través de NetSendo MTA.',
            'failed' => 'La conexión falló: :error',
        ],
    ],
];
