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

    // NetSendo MTA (NMI) mailboxes: a verified domain and, optionally, a dedicated IP
    'nmi' => [
        'errors' => [
            'domain_required' => 'Elige uno de tus dominios verificados para el buzón de NetSendo MTA.',
            'domain_not_verified' => 'El dominio :domain aún no está verificado. Verifícalo primero en Entregabilidad.',
            'domain_missing' => 'Este buzón de NetSendo MTA ya no tiene dominio de envío (el dominio fue eliminado). Edita el buzón y elige un dominio verificado.',
            'from_domain_mismatch' => 'La dirección del remitente debe estar en el dominio elegido (@:domain).',
            'ip_invalid' => 'Elige una IP dedicada activa de uno de tus grupos de IP, o ninguna.',
            'ip_other_domain' => 'La dirección IP :ip ya está asignada a otro dominio.',
            'mta_login_file' => 'NetSendo no pudo escribir el archivo de accesos del MTA (:file). Comprueba que el directorio existe y se puede escribir, y guarda de nuevo.',
            'mta_login_refused' => 'El MTA no conoce el acceso de este dominio: debe leer el archivo de accesos que escribe NetSendo (volumen nmi-auth en docker-compose.yml).',
        ],
        // Added to the result of "Test connection" of an NMI mailbox
        'test' => [
            'ip_not_on_mta' => 'Nota: el MTA no tiene la dirección :ip, así que este mensaje salió desde la dirección propia del MTA.',
            'unsigned' => 'Nota: el mensaje no se firmó con DKIM. Publica el registro DKIM del dominio y verifícalo en el formulario del buzón.',
        ],
    ],

    // Field names in validation messages of the mailbox form
    'attributes' => [
        'name' => 'nombre del buzón',
        'provider' => 'proveedor',
        'from_email' => 'dirección del remitente',
        'from_name' => 'nombre del remitente',
        'reply_to' => 'dirección Reply-To',
        'allowed_types' => 'tipos de mensaje permitidos',
        'credentials' => 'datos de acceso al servidor',
        'daily_limit' => 'límite diario de envíos',
        'time_restriction' => 'intervalo mínimo',
        'custom_headers.*.key' => 'nombre del encabezado',
        'custom_headers.*.value' => 'valor del encabezado',
        'domain_configuration_id' => 'dominio de envío',
        'dedicated_ip_id' => 'IP dedicada',
        'reply_imap_host' => 'host IMAP de respuestas',
    ],
];
