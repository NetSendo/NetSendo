<?php

return [
    'reminders' => [
        'email_subject' => 'Recordatorio: :title',
        'title' => '⏰ Recordatorio de tarea',
        'subtitle' => 'Tiene una tarea programada para completar',
        'type' => 'Tipo',
        'due_date' => 'Fecha de vencimiento',
        'priority' => 'Prioridad',
        'contact' => 'Contacto',
        'view_in_crm' => 'Ver en CRM',
        'footer_auto' => 'Este mensaje fue enviado automáticamente por :appName.',
        'footer_settings' => 'Puede cambiar la configuración de notificaciones en su perfil.',
        'deal' => 'Trato: :name',
        'types' => [
            'call' => '📞 Llamada',
            'email' => '✉️ Email',
            'meeting' => '📅 Reunión',
            'follow_up' => '🔄 Seguimiento',
            'other' => '📋 Otro',
        ],
        'priorities' => [
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
        ]
    ],
    'calendar' => [
        'sync_label' => 'Sincronizar con Google Calendar',
        'select_calendar' => 'Seleccionar calendario',
        'google_calendar' => 'Google Calendar',
        'sync_all' => 'Sincronizar todas las tareas',
        'syncing' => 'Sincronizando...',
        'bulk_sync_description' => 'Sincronizar todas las tareas pendientes de CRM con Google Calendar con un clic.',
        'sync_success' => 'Sincronizadas :count tareas',
        'synced' => 'Sincronizado',
        'sync_error' => 'Ocurrió un error durante la sincronización.',
    ],
    'recurrence' => [
        'is_recurring' => 'Tarea recurrente',
        'helper_text' => 'Se repite automáticamente',
        'frequency' => [
            'label' => 'Repetir cada',
            'daily' => '{1} día|{n} días',
            'weekly' => '{1} semana|{n} semanas',
            'monthly' => '{1} mes|{n} meses',
            'yearly' => '{1} año|{n} años',
        ],
        'days_of_week' => 'Días de la semana',
        'days' => [
            'mon' => 'Lun',
            'tue' => 'Mar',
            'wed' => 'Mié',
            'thu' => 'Jue',
            'fri' => 'Vie',
            'sat' => 'Sáb',
            'sun' => 'Dom',
        ],
        'end_condition' => [
            'label' => 'Termina',
            'never' => 'Nunca',
            'date' => 'En fecha:',
            'count' => 'Después de',
            'occurrences' => '{1} ocurrencia|{n} ocurrencias',
        ],
    ],
    'conflicts' => [
        'title' => 'Conflicto de sincronización detectado',
        'description' => 'Esta tarea ha sido modificada tanto localmente como en Google Calendar',
        'detected_at' => 'Conflicto detectado: :date',
        'local_version' => 'Versión local (NetSendo)',
        'remote_version' => 'Versión remota (Google Calendar)',
        'use_local' => 'Usar versión local',
        'use_remote' => 'Usar versión remota',
        'cancel' => 'Cancelar',
        'no_conflict' => 'La tarea no tiene conflicto para resolver.',
        'resolved_local' => 'Conflicto resuelto - se usó la versión local.',
        'resolved_remote' => 'Conflicto resuelto - se usó la versión remota.',
    ],
    'task' => [
        'title' => [
            'new' => 'Nueva tarea',
            'edit' => 'Editar tarea',
        ],
        'fields' => [
            'title' => 'Título de la tarea *',
            'title_placeholder' => 'ej. Llamar al cliente sobre la oferta',
            'type' => 'Tipo de tarea',
            'priority' => 'Prioridad',
            'due_date' => 'Fecha de vencimiento',
            'contact' => 'Asignar a contacto',
            'contact_placeholder' => '-- Sin contacto --',
            'owner' => 'Asignar a propietario',
            'owner_auto' => '-- Automático (yo) --',
            'description' => 'Descripción (opcional)',
            'description_placeholder' => 'Detalles adicionales sobre la tarea...',
        ],
        'calendar' => [
            'sync' => 'Sincronizar con Google Calendar',
            'select_calendar' => 'Seleccionar calendario',
            'default' => 'Predeterminado',
            'primary' => 'Principal',
            'synced' => 'Sincronizado con calendario',
        ],
        'meet' => [
            'include_meet' => 'Añadir enlace de Google Meet',
            'include_meet_desc' => 'Crear automáticamente enlace de videoconferencia',
            'attendees' => 'Participantes',
            'add_email' => 'Añadir email y presionar Enter',
            'attendees_hint' => 'Presione Enter para añadir. El email del contacto se añade automáticamente.',
            'meeting_ready' => 'Enlace de reunión listo',
            'join' => 'Unirse a la reunión',
            'status' => [
                'accepted' => 'Aceptado',
                'declined' => 'Rechazado',
                'tentative' => 'Quizás',
                'needsAction' => 'Esperando respuesta',
            ],
            'add_guest' => 'Añadir',
            'not_connected' => 'Conecta tu cuenta de Google en la configuración para activar',
        ],
        'zoom' => [
            'include_zoom' => 'Añadir enlace de Zoom Meeting',
            'include_zoom_desc' => 'Crear automáticamente videoconferencia Zoom',
            'meeting_ready' => 'Zoom Meeting listo',
            'join' => 'Unirse a Zoom',
            'attendees' => 'Invitados',
            'add_email' => 'Ingresar email del invitado...',
            'add_guest' => 'Añadir',
            'attendees_hint' => 'Los invitados recibirán una invitación con el enlace',
            'not_connected' => 'Conecta tu cuenta de Zoom en la configuración para activar',
        ],
        'actions' => [
            'cancel' => 'Cancelar',
            'save' => 'Guardar cambios',
            'create' => 'Crear tarea',
        ],
        'empty' => [
            'title' => 'Sin tareas',
            'description' => 'Aún no tiene tareas en esta categoría.',
            'button' => 'Añadir primera tarea',
        ],
    ],
    'contacts' => [
        'search_or_email' => 'Buscar o introducir email',
        'search_placeholder' => 'Escriba nombre o email...',
        'search_hint' => 'Escriba al menos 2 caracteres para buscar un suscriptor existente o introduzca un nuevo email',
        'existing_subscriber' => 'Suscriptor existente',
        'found_subscribers' => 'Suscriptores encontrados',
        'title' => [
            'edit' => 'Editar contacto',
        ],
        'edit_success' => 'Contacto actualizado',
        'actions' => [
            'edit' => 'Editar',
        ],
    ],

    // Default lead scoring rules (LeadScoringRule::getDefaultRules())
    'scoring' => [
        'defaults' => [
            'email_opened' => [
                'name' => 'Correo abierto',
                'description' => 'Puntos por abrir un correo electrónico',
            ],
            'email_clicked' => [
                'name' => 'Enlace clicado',
                'description' => 'Puntos por hacer clic en un enlace de un correo',
            ],
            'email_replied' => [
                'name' => 'Correo respondido',
                'description' => 'Bono por responder a un mensaje',
            ],
            'contact_created' => [
                'name' => 'Contacto creado',
                'description' => 'Puntos iniciales al crear un contacto CRM',
            ],
            'form_submitted' => [
                'name' => 'Formulario enviado',
                'description' => 'Puntos por enviar cualquier formulario',
            ],
            'page_visited' => [
                'name' => 'Página visitada',
                'description' => 'Puntos por visitar una página',
            ],
            'page_visited_pricing' => [
                'name' => 'Visita a precios',
                'description' => 'Bono por visitar la página de precios',
            ],
            'product_viewed' => [
                'name' => 'Producto visto',
                'description' => 'Puntos por ver una página de producto',
            ],
            'add_to_cart' => [
                'name' => 'Añadido al carrito',
                'description' => 'Puntos por añadir un producto al carrito',
            ],
            'checkout_started' => [
                'name' => 'Pago iniciado',
                'description' => 'Bono por iniciar el proceso de pago',
            ],
            'tag_added_hot' => [
                'name' => 'Etiqueta "hot"',
                'description' => 'Bono por recibir la etiqueta \'hot\'',
            ],
            'tag_added_vip' => [
                'name' => 'Etiqueta "vip"',
                'description' => 'Bono por recibir la etiqueta \'vip\'',
            ],
            'decay_7_days' => [
                'name' => 'Inactividad 7 días',
                'description' => 'Decaimiento automático de puntos tras 7 días de inactividad',
            ],
            'decay_30_days' => [
                'name' => 'Inactividad 30 días',
                'description' => 'Gran decaimiento de puntos tras 30 días de inactividad',
            ],
        ],
    ],

    'cardintel' => [
        'settings' => [
            'saved' => 'Guardado',
        ],
    ],
    'companies' => [
        'lookup' => [
            'invalid_nip' => 'Formato de NIP inválido',
            'invalid_regon' => 'Formato de REGON inválido',
            'missing_identifier' => 'Introduzca NIP o REGON',
            'not_found' => 'Empresa con este NIP/REGON no encontrada',
        ],
    ],
    'activities' => [
        'log' => [
            'email_sent' => 'Correo enviado: :subject',
            'task_completed' => 'Tarea completada: :title',
        ],
    ],
    'notifications' => [
        'contact_replied' => [
            'subject' => 'Nueva respuesta de :name',
            'greeting' => '¡Hola!',
            'intro' => ':name ha respondido a tu mensaje (canal: :channel).',
            'action' => 'Ver contacto',
            'outro' => 'Responde mientras la conversación sigue reciente.',
        ],
        'deal_won' => [
            'subject' => 'Trato ganado: :name',
            'intro' => '¡Enhorabuena! Se ha ganado el trato «:name» por valor de :value.',
        ],
        'deal_lost' => [
            'subject' => 'Trato perdido: :name',
            'intro' => 'El trato «:name» se ha marcado como perdido.',
        ],
        'deal_stage_changed' => [
            'subject' => 'Nueva etapa del trato: :name',
            'intro' => 'El trato «:name» ha pasado a otra etapa.',
            'greeting' => '¡Hola!',
            'action' => 'Ver trato',
        ],
        'task_overdue' => [
            'subject' => 'Tarea vencida: :title',
            'greeting' => '¡Hola!',
            'intro' => 'La tarea «:title» ha superado su fecha límite.',
            'due_date' => 'Fecha límite: :date',
            'action' => 'Ver tarea',
            'outro' => 'Complétala o establece una nueva fecha límite.',
        ],
    ],
];
