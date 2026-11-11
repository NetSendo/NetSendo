<?php

// Páginas de una instancia de NetSendo Cloud en hibernación (App\Http\Middleware\DormantMode)
return [
    'asleep' => [
        'title' => 'Esta instancia de NetSendo está en hibernación',
        'body' => 'La suscripción de esta instancia ha terminado. Sus datos se conservan de forma segura, pero el acceso y los envíos están en pausa.',
        'owner' => '¿Eres el propietario? Reanúdala con el enlace del correo de NetSendo y volverá en unos minutos, en la misma dirección y con todos sus datos. También puedes escribir a contact@netsendo.com.',
        'unsubscribe' => '¿No quieres recibir más mensajes de este remitente? El enlace para darte de baja de su correo sigue funcionando.',
    ],
    'unsubscribe' => [
        'title' => 'Darse de baja',
        'body' => 'Confirma que ya no quieres recibir mensajes de esta lista.',
        'body_all' => 'Confirma que ya no quieres recibir ningún mensaje de este remitente. Te daremos de baja de todas sus listas.',
        'button' => 'Darme de baja',
        'invalid_title' => 'Este enlace no es válido',
        'invalid' => 'Usa el enlace para darte de baja del mensaje tal como lo recibiste.',
    ],
];
