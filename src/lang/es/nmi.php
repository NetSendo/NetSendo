<?php

return [
    'dkim_generated' => '¡Claves DKIM generadas exitosamente!',
    'dkim_not_verified' => 'Registro DKIM no encontrado. Verifique su configuración DNS.',
    'dkim_verified' => '¡DKIM verificado exitosamente!',
    'invalid_ip_address' => 'Formato de dirección IP no válido.',
    'ip_added' => '¡La dirección IP se ha añadido correctamente!',
    'ip_deleted' => 'La dirección IP ha sido eliminada.',
    'ip_has_mailboxes' => 'No se puede eliminar una dirección IP con buzones asignados.',
    'ip_provisioned' => '¡Dirección IP aprovisionada correctamente!',
    'pool_created' => '¡Pool de IP creado exitosamente!',
    'pool_deleted' => 'Pool de IP eliminado.',
    'pool_max_ips_reached' => 'Este pool ha alcanzado el número máximo de direcciones IP.',
    'pool_not_empty' => 'No se puede eliminar un pool que contiene direcciones IP.',
    'provider_not_configured' => 'Este proveedor no está configurado. Añada una clave API en la configuración de proveedores.',
    'provider_saved' => '¡Configuración del proveedor guardada correctamente!',
    'warming_started' => '¡Calentamiento de IP iniciado!',
    'blacklist_checked' => 'Comprobación de listas negras completada.',

    // IP providers (addresses ordered through a hosting company's API) and reverse DNS
    'ip_release_failed' => 'El proveedor no ha recuperado la dirección, así que se seguiría cobrando: :reason',
    'providers' => [
        'errors' => [
            'unreachable' => 'No se pudo conectar con :provider. Inténtalo de nuevo en un momento.',
            'unauthorized' => ':provider no aceptó la clave API: :reason',
            'refused' => ':provider rechazó la solicitud: :reason',
            'server_not_found' => 'Este servidor ya no está en tu cuenta de :provider.',
            'digitalocean_unusable' => 'DigitalOcean no puede ofrecer IP dedicadas para correo: bloquea SMTP en las Reserved IP y no les da DNS inverso.',
        ],
    ],
    'ptr' => [
        'verified' => 'El DNS inverso es correcto: la dirección nombra su host y el host apunta de vuelta.',
        'not_verified' => 'El DNS inverso aún no es correcto.',
        'set' => 'El DNS inverso se ha configurado en el proveedor.',
        'not_supported' => 'El DNS inverso de esta dirección no se puede configurar desde NetSendo. Configúralo en el panel de la empresa de la que procede la dirección.',
        'no_hostname' => 'La dirección no tiene un nombre de host al que apuntar.',
    ],
    'provision' => [
        'hostname' => 'nombre de host',
        'server' => 'servidor',
    ],
];
