<?php

return [
    // Quick Win recommendations
    'missing_preheader' => [
        'title' => 'Añade preheaders a tus emails',
        'description' => 'Los emails sin preheader pierden espacio valioso de vista previa en la bandeja de entrada. Añadir preheaders atractivos puede aumentar la tasa de apertura un 5-10%.',
        'action_steps' => [
            'Abre el editor de email para cada borrador/email programado',
            'Añade un preheader que complemente tu línea de asunto',
            'Mantenlo por debajo de 100 caracteres para mejor visualización',
            'Usa tokens de personalización para mayor engagement',
        ],
    ],
    'long_subject' => [
        'title' => 'Acorta tus líneas de asunto',
        'description' => 'Las líneas de asunto largas se recortan en dispositivos móviles. Mantenerlas por debajo de 50 caracteres asegura visibilidad completa.',
        'action_steps' => [
            'Revisa las líneas de asunto con más de 50 caracteres',
            'Enfócate en la parte más convincente de tu mensaje',
            'Usa palabras poderosas que despierten emociones',
            'Prueba con emoji (moderadamente) para atractivo visual',
        ],
    ],
    'no_personalization' => [
        'title' => 'Personaliza el contenido de tus emails',
        'description' => 'Los emails personalizados logran un 26% más de tasa de apertura. Usar nombres de suscriptores crea conexiones más fuertes.',
        'action_steps' => [
            'Añade [[first_name]] a líneas de asunto y saludos',
            'Usa [[company]] o [[city]] para comunicación B2B',
            'Crea bloques de contenido dinámico basados en etiquetas de suscriptores',
            'Configura valores de respaldo para datos faltantes',
        ],
    ],
    'spam_content' => [
        'title' => 'Reduce las palabras que activan spam',
        'description' => 'Tu contenido contiene palabras que pueden activar filtros de spam. Limpiar el lenguaje mejora la entregabilidad.',
        'action_steps' => [
            'Evita MAYÚSCULAS y signos de exclamación excesivos',
            'Reemplaza palabras como "GRATIS", "URGENTE", "ACTÚA AHORA" con alternativas más suaves',
            'Equilibra contenido promocional y de valor',
            'Usa verificadores de HTML de email antes de enviar',
        ],
    ],
    'stale_list' => [
        'title' => 'Limpia tus listas de suscriptores',
        'description' => 'Las listas con suscriptores inactivos perjudican la entregabilidad. La limpieza regular mejora las tasas de apertura y la reputación del remitente.',
        'action_steps' => [
            'Identifica suscriptores sin aperturas en 90 días',
            'Ejecuta una campaña de reactivación antes de eliminar',
            'Elimina rebotes duros inmediatamente',
            'Considera una política de caducidad para usuarios inactivos a largo plazo',
        ],
    ],
    'poor_timing' => [
        'title' => 'Optimiza tus horarios de envío',
        'description' => 'Enviar en horarios óptimos impacta significativamente en las tasas de apertura. Tu mejor ventana es típicamente 9-11 AM o 2-4 PM hora local.',
        'action_steps' => [
            'Programa emails entre 9-11 AM para audiencias de negocios',
            'Prueba 2-4 PM para audiencias de consumidores',
            'Martes a jueves típicamente funcionan mejor',
            'Evita fines de semana a menos que tus datos indiquen lo contrario',
        ],
    ],
    'over_mailing' => [
        'title' => 'Reduce la frecuencia de envío',
        'description' => 'Estás enviando con demasiada frecuencia a algunas listas. Esto aumenta las bajas y las quejas de spam.',
        'action_steps' => [
            'Limita a 2-3 emails por semana por lista',
            'Crea un centro de preferencias para opciones de frecuencia',
            'Segmenta usuarios de alto engagement para más contenido',
            'Usa automatizaciones en lugar de broadcasts manuales donde sea posible',
        ],
    ],
    'no_automation' => [
        'title' => 'Configura automatizaciones de bienvenida',
        'description' => 'Los emails automatizados generan 320% más ingresos que los no automatizados. Comienza con una secuencia de bienvenida.',
        'action_steps' => [
            'Crea una secuencia de bienvenida de 3-5 emails',
            'Configura automatización activada por nuevo suscriptor',
            'Incluye contenido de valor antes de ofertas promocionales',
            'Rastrea engagement para identificar leads calientes',
        ],
    ],
    'sms_missing' => [
        'title' => 'Lanza campañas de SMS',
        'description' => 'Tienes números de teléfono pero no usas SMS. Las campañas multicanal mejoran la conversión un 12-15%.',
        'action_steps' => [
            'Crea un SMS de seguimiento para campañas de email clave',
            'Usa SMS para ofertas sensibles al tiempo',
            'Mantén los mensajes por debajo de 160 caracteres',
            'Incluye un llamado a la acción claro con enlace',
        ],
    ],

    // Strategic recommendations
    'declining_open_rate' => [
        'title' => 'Revierte las tasas de apertura en declive',
        'description' => 'Tus tasas de apertura han caído un :change% en los últimos 30 días. Enfócate en optimización de líneas de asunto e higiene de listas.',
        'action_steps' => [
            'Pruebas A/B de líneas de asunto en tus próximas 5 campañas',
            'Elimina suscriptores inactivos por más de 90 días',
            'Verifica tu reputación de remitente en mail-tester.com',
            'Verifica los registros SPF/DKIM/DMARC',
        ],
    ],
    'low_click_rate' => [
        'title' => 'Mejora las tasas de clics de email',
        'description' => 'Tu tasa de clics está por debajo del 2%, lo cual está bajo el promedio de la industria. Mejores CTAs y estructura de contenido pueden ayudar.',
        'action_steps' => [
            'Usa CTAs con estilo de botón en lugar de enlaces de texto',
            'Coloca tu CTA principal visible sin scroll',
            'Usa lenguaje orientado a la acción ("Comenzar" vs "Haz clic aquí")',
            'Limita a 1-2 CTAs principales por email',
        ],
    ],
    'low_segmentation' => [
        'title' => 'Implementa segmentación de suscriptores',
        'description' => 'Solo el :percent% de tus suscriptores tienen etiquetas. Mejor segmentación lleva a un 14% más de tasa de clics.',
        'action_steps' => [
            'Crea etiquetas basadas en intereses del comportamiento de clics',
            'Configura automatizaciones de etiquetas para acciones clave',
            'Segmenta por nivel de engagement (activo/pasivo/frío)',
            'Usa bloques de contenido dinámico para diferentes segmentos',
        ],
    ],
];
