<?php

/**
 * Spanish translations - Automations Module
 */

return [
    'title' => 'Automatizaciones',
    'create' => 'Nueva automatización',
    'edit' => 'Editar automatización',
    'delete' => 'Eliminar automatización',
    'logs' => 'Registros de ejecución',
    
    'no_rules' => 'No hay automatizaciones',
    'no_rules_hint' => 'Cree su primera automatización para responder automáticamente a los eventos.',
    'create_first' => 'Crear primera automatización',
    
    'basic_info' => 'Información básica',
    'name_placeholder' => 'ej. Bienvenida a nuevos suscriptores',
    'description_placeholder' => 'Descripción (opcional)',
    
    'when' => 'CUANDO',
    'if' => 'SI',
    'then' => 'ENTONCES',
    
    'trigger' => 'Disparador',
    'trigger_event' => 'Evento disparador',
    'actions_count' => 'Acciones',
    'executions' => 'Ejecuciones',
    
    'filter_by_list' => 'Filtrar por lista',
    'filter_by_message' => 'Filtrar por mensaje',
    'filter_by_form' => 'Filtrar por formulario',
    'filter_by_tag' => 'Filtrar por etiqueta',
    
    'add_condition' => 'Añadir condición',
    'no_conditions_hint' => 'Sin condiciones - la automatización se activará para cada ocurrencia del evento.',
    'all_conditions' => 'Se deben cumplir todas las condiciones',
    'any_condition' => 'Se debe cumplir cualquier condición',
    'value' => 'Valor',
    
    'add_action' => 'Añadir acción',
    'no_actions_hint' => 'Añada al menos una acción para ejecutar.',
    'select_tag' => 'Seleccionar etiqueta',
    'select_list' => 'Seleccionar lista',
    'select_message' => 'Seleccionar mensaje',
    'select_funnel' => 'Seleccionar embudo',
    'select_field' => 'Seleccionar campo',
    'webhook_url' => 'URL del webhook',
    'admin_email' => 'Email del administrador',
    'email_subject' => 'Asunto del email',
    'notification_message' => 'Mensaje de notificación',
    'new_value' => 'Nuevo valor',
    
    'rate_limiting' => 'Limitación de frecuencia',
    'limit_per_subscriber' => 'Limitar ejecuciones por suscriptor',
    'max' => 'Máximo',
    'times' => 'veces',
    'per_hour' => 'por hora',
    'per_day' => 'por día',
    'per_week' => 'por semana',
    'per_month' => 'por mes',
    'ever' => 'en total',
    
    'activate_immediately' => 'Activar inmediatamente',
    
    'confirm_duplicate' => '¿Desea duplicar esta automatización?',
    'confirm_delete' => '¿Está seguro de que desea eliminar esta automatización? Esta acción no se puede deshacer.',
    
    'triggers' => [
        'subscriber_signup' => 'Suscripción de suscriptor',
        'subscriber_activated' => 'Suscriptor activado',
        'email_opened' => 'Email abierto',
        'email_clicked' => 'Enlace clicado',
        'subscriber_unsubscribed' => 'Suscriptor dado de baja',
        'email_bounced' => 'Email rebotado',
        'form_submitted' => 'Formulario enviado',
        'tag_added' => 'Etiqueta añadida',
        'tag_removed' => 'Etiqueta eliminada',
        'field_updated' => 'Campo actualizado',
    ],
    
    'actions' => [
        'send_email' => 'Enviar email',
        'add_tag' => 'Añadir etiqueta',
        'remove_tag' => 'Eliminar etiqueta',
        'move_to_list' => 'Mover a la lista',
        'copy_to_list' => 'Copiar a la lista',
        'unsubscribe' => 'Dar de baja',
        'call_webhook' => 'Llamar webhook',
        'start_funnel' => 'Iniciar embudo',
        'update_field' => 'Actualizar campo',
        'notify_admin' => 'Notificar al admin',
    ],
    
    'log_status' => [
        'success' => 'Éxito',
        'partial' => 'Éxito parcial',
        'failed' => 'Error',
        'skipped' => 'Omitido',
    ],
];
