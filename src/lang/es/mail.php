<?php

return [
    'new_subscriber_notification_subject' => 'Nuevo suscriptor en la lista :list',
    'errors' => [
        'no_mailbox_for_type' => 'Ningún buzón activo puede enviar mensajes de tipo ":type". Permita este tipo en la configuración del buzón (Configuración > Buzones de envío > Tipos permitidos).',
        'no_mailbox' => 'No hay ningún buzón configurado. Configure uno en los ajustes antes de enviar mensajes.',
        'campaign_sender_type' => 'El buzón ":mailbox" (:email) de la campaña no permite mensajes Única vez (Broadcast) (los buzones de Gmail nunca lo permiten) y ninguna lista de la campaña tiene un buzón de envío masivo, así que la campaña no pudo salir. No se ha enviado desde otro buzón. Configure un buzón de envío masivo en los ajustes de la lista (Envío): las campañas de la lista salen desde él. O elija un buzón que pueda enviar campañas.',
        'individual_sender' => [
            'inactive' => 'Este correo individual debía salir del buzón ":mailbox" (:email), que ahora está desactivado o eliminado. No se ha enviado desde otro buzón. Active el buzón y vuelva a enviar el correo.',
            'type' => 'El buzón ":mailbox" (:email) no permite Única vez (Broadcast) ni Autorespondedor, así que no puede enviar este correo individual. No se ha enviado desde otro buzón. Permita uno de estos tipos en la configuración del buzón (Configuración > Buzones de envío > Tipos permitidos).',
            'limit' => 'El buzón ":mailbox" (:email) ha agotado su límite diario de envío. Este correo individual no se ha enviado desde otro buzón. Vuelva a enviarlo cuando se renueve el límite o aumente el límite en la configuración del buzón.',
        ],
    ],
];
