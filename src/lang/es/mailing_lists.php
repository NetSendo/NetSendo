<?php

/**
 * Mailing list flash and error messages (panel)
 */

return [
    'flash' => [
        'created' => 'La lista se ha creado.',
        'updated' => 'La lista se ha actualizado.',
        'deleted' => 'La lista se ha eliminado.',
        'no_edit_permission' => 'No tiene permiso para editar esta lista.',
        'owner_only_delete' => 'Solo el propietario de la lista puede eliminarla.',
        'transfer_same_list' => 'No se pueden mover los suscriptores a la misma lista.',
        'has_subscribers' => 'Esta lista tiene suscriptores. Elija si desea moverlos o eliminarlos.',
        'api_key_generated' => 'Se ha generado una nueva clave API.',
        'webhook_test_sent' => 'Se ha enviado el webhook de prueba.',
        'webhook_test_failed' => 'No se pudo enviar el webhook de prueba. Compruebe la URL y los registros.',
        'no_copy_permission' => 'No tiene permiso para copiar esta lista.',
        'copy_success' => '¡Lista copiada con éxito!',
    ],
    'validation' => [
        'broadcast_mailbox_type' => 'El buzón ":mailbox" (:email) no permite mensajes Única vez (Broadcast) (los buzones de Gmail nunca lo permiten), así que no puede enviar las campañas de la lista. Elija un buzón que pueda enviarlas, normalmente un buzón SMTP de su dominio.',
    ],
];
