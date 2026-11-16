<?php

/**
 * Spanish translations - Flash messages
 */

return [
    // Success messages
    'saved' => 'Guardado correctamente.',
    'created' => 'Creado correctamente.',
    'updated' => 'Actualizado correctamente.',
    'deleted' => 'Eliminado correctamente.',
    'imported' => 'Importado correctamente.',
    'exported' => 'Exportado correctamente.',

    // Error messages
    'error_occurred' => 'Ha ocurrido un error. Por favor, inténtalo de nuevo.',
    'not_found' => 'Recurso no encontrado.',
    'unauthorized' => 'No tienes autorización para realizar esta acción.',
    'validation_error' => 'Por favor, revisa tus datos e inténtalo de nuevo.',

    // Confirmation messages
    'confirm_delete' => '¿Estás seguro de que quieres eliminar esto?',
    'confirm_action' => '¿Estás seguro de que quieres continuar?',
    'action_irreversible' => 'Esta acción no se puede deshacer.',
    'campaign' => [
        'invalid_lists' => 'Lista de destinatarios no válida.',
        'invalid_excluded_lists' => 'Lista de exclusión no válida.',
        'invalid_mailbox' => 'Buzón de envío no válido.',
        'scheduled' => 'El mensaje se ha programado.',
        'queued' => 'El mensaje se ha puesto en la cola de envío.',
        'saved_draft' => 'El mensaje se ha guardado como borrador.',
        'saved' => 'El mensaje se ha guardado.',
        'updated' => 'El mensaje se ha actualizado.',
        'resend_broadcast_only' => 'Solo los mensajes únicos (broadcast) se pueden volver a enviar.',
        'resend_no_new_recipients' => 'No hay destinatarios nuevos: todos los suscriptores ya han recibido este mensaje.',
        'resend_scheduled' => 'El mensaje se ha programado. Destinatarios nuevos: :count.',
        'deleted' => 'El mensaje se ha eliminado.',
        'queue_only_active' => 'Solo los mensajes de cola tienen estado activo.',
        'activated' => 'El mensaje se ha activado.',
        'deactivated' => 'El mensaje se ha desactivado.',
        'queue_only_feature' => 'Esta función solo está disponible para mensajes de cola.',
        'must_be_active' => 'El mensaje debe estar activo para enviarlo a los suscriptores omitidos.',
        'no_skipped' => 'No hay suscriptores omitidos a los que enviar.',
        'skipped_scheduled' => 'Se ha programado el envío a :count suscriptores omitidos.',
        'automation_description' => 'Automatización creada a partir del mensaje #:id',
    ],
];
