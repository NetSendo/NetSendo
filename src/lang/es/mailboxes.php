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
];
