<?php

// Messages of NetSendo Mail Infrastructure (NMI): IP pools, dedicated IPs and NMI on domains
return [
    'pool_created' => '¡Pool de IP creado exitosamente!',
    'pool_deleted' => 'Pool de IP eliminado.',
    'pool_not_empty' => 'No se puede eliminar un pool que contiene direcciones IP.',
    'pool_max_ips_reached' => 'Este pool ha alcanzado el número máximo de direcciones IP.',
    'warming_started' => '¡Calentamiento de IP iniciado!',
    'dkim_generated' => '¡Claves DKIM generadas exitosamente!',
    'dkim_verified' => '¡DKIM verificado exitosamente!',
    'dkim_not_verified' => 'Registro DKIM no encontrado. Verifique su configuración DNS.',
    'ip_added' => '¡La dirección IP se ha añadido correctamente!',
    'ip_deleted' => 'La dirección IP ha sido eliminada.',
    'ip_has_mailboxes' => 'No se puede eliminar una dirección IP con buzones asignados.',
    'invalid_ip_address' => 'Formato de dirección IP no válido.',
    'provider_not_configured' => 'Este proveedor no está configurado. Añada una clave API en la configuración de proveedores.',
    'provider_saved' => '¡Configuración del proveedor guardada correctamente!',
    'ip_provisioned' => '¡Dirección IP aprovisionada correctamente!',
    'blacklist_checked' => 'Listas negras comprobadas.',
    'ip_assigned' => 'La dirección IP envía ahora para :domain.',
    'ip_unassigned' => 'La dirección IP ya no está asignada a ningún dominio.',
    'ip_in_use' => 'Hay cuentas de correo que envían a través de esta dirección IP (:count). Cámbielas primero a otra IP u otro proveedor.',
    'domain_not_found' => 'Elija uno de sus dominios de Deliverability Shield.',
    'domain_enabled' => 'NetSendo MTA está activado para :domain.',
    'domain_disabled' => 'NetSendo MTA está desactivado para :domain.',
    'domain_not_verified' => 'El dominio :domain aún no está verificado. Añada primero su registro CNAME.',
    'domain_in_use' => 'Hay cuentas de correo que envían a través de NetSendo MTA de este dominio (:count). Cámbielas primero a otro proveedor.',
];
