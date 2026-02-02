<?php

return [
    // Page titles
    'title' => 'Deliverability Shield',
    'subtitle' => 'Asegure que sus correos lleguen a la bandeja de entrada, no al spam',

    // Navigation
    'add_domain' => 'Añadir dominio',
    'verified' => 'Verificado',
    'pending_verification' => 'Verificación pendiente',
    'never_checked' => 'Nunca comprobado',
    'last_check' => 'Última comprobación',
    'refresh' => 'Actualizar',

    // Stats
    'stats' => [
        'domains' => 'Dominios',
        'verified' => 'Verificados',
        'critical' => 'Problemas críticos',
        'avg_score' => 'Puntuación media',
    ],

    // Domains
    'domains' => [
        'title' => 'Sus dominios',
        'empty' => [
            'title' => 'Aún no hay dominios añadidos',
            'description' => 'Añada su primer dominio para comenzar a monitorear la entregabilidad',
        ],
    ],

    // DMARC Wiz
    'dmarc_wiz' => [
        'title' => 'DMARC Wiz',
        'subtitle' => 'Añada su dominio en un solo paso',
        'step_domain' => 'Dominio',
        'step_verify' => 'Verificar',
        'enter_domain_title' => 'Introduzca su dominio',
        'enter_domain_description' => 'Este es el dominio desde el que envía correos electrónicos',
        'add_record_title' => 'Añadir registro DNS',
        'add_record_description' => 'Añada este registro CNAME a la configuración DNS de su dominio',
        'dns_propagation_info' => 'Los cambios en el DNS pueden tardar hasta 48 horas en propagarse. Puede verificar en cualquier momento.',
        'add_and_verify' => 'Añadir y comprobar verificación',
    ],

    // Domain fields
    'domain_name' => 'Nombre de dominio',
    'record_type' => 'Tipo de registro',
    'host' => 'Host',
    'target' => 'Valor objetivo',
    'type' => 'Tipo',

    // Status
    'status_overview' => 'Resumen de estado',
    'verification_required' => 'Verificación requerida',
    'verification_description' => 'Añada el siguiente registro CNAME a su configuración DNS para verificar la propiedad del dominio.',
    'add_this_record' => 'Añadir este registro DNS',
    'verify_now' => 'Verificar ahora',

    // Alerts
    'alerts' => [
        'title' => 'Alertas por correo electrónico',
        'description' => 'Reciba notificaciones cuando haya problemas con la entregabilidad de su dominio',
    ],

    // Test email
    'test_email' => 'Pruebe su correo electrónico',
    'test_email_description' => 'Ejecute una simulación para comprobar la entregabilidad antes de enviar',

    // Simulations
    'simulations' => [
        'recent' => 'Simulaciones recientes',
        'empty' => 'No hay simulaciones todavía. Ejecute su primera prueba de InboxPassport AI.',
        'history' => 'Historial de simulaciones',
        'no_history' => 'Sin historial de simulaciones',
        'no_history_desc' => 'Ejecute su primera simulación de InboxPassport AI para ver los resultados aquí.',
    ],

    // InboxPassport
    'inbox_passport' => [
        'title' => 'InboxPassport AI',
        'subtitle' => 'Prediga dónde aterrizará su correo electrónico antes de enviarlo',
        'how_it_works' => 'Cómo funciona',
        'step1_title' => 'Analizar dominio',
        'step1_desc' => 'Comprobamos su configuración SPF, DKIM y DMARC',
        'step2_title' => 'Escanear contenido',
        'step2_desc' => 'La IA detecta desencadenantes de spam, enlaces sospechosos y problemas de formato',
        'step3_title' => 'Predecir entrega',
        'step3_desc' => 'Obtenga una predicción de ubicación en la bandeja de entrada para Gmail, Outlook y Yahoo',
        'what_we_check' => 'Lo que analizamos',
    ],

    // Simulation form
    'select_domain' => 'Seleccionar dominio',
    'no_verified_domains' => 'No hay dominios verificados. Añada un dominio primero.',
    'email_subject' => 'Asunto del correo',
    'subject_placeholder' => 'Introduzca el asunto de su correo...',
    'email_content' => 'Contenido del correo (HTML)',
    'content_placeholder' => 'Pegue aquí el contenido HTML de su correo...',
    'analyzing' => 'Analizando...',
    'run_simulation' => 'Ejecutar InboxPassport AI',

    // Analysis elements
    'spam_words' => 'Palabras spam',
    'subject_analysis' => 'Análisis del asunto',
    'link_check' => 'Verificación de enlaces',
    'html_structure' => 'Estructura HTML',
    'formatting' => 'Formato',

    // Results
    'simulation_result' => 'Resultado de la simulación',
    'predicted_folder' => 'Carpeta predicha',
    'provider_predictions' => 'Predicciones del proveedor',
    'confidence' => 'confianza',
    'issues_found' => 'Problemas encontrados',
    'recommendations' => 'Recomendaciones',
    'run_new_simulation' => 'Ejecutar nueva simulación',
    'view_history' => 'Ver historial',
    'new_simulation' => 'Nueva simulación',

    // Scores
    'score' => [
        'excellent' => 'Excelente',
        'good' => 'Bueno',
        'fair' => 'Regular',
        'poor' => 'Pobre',
    ],

    // Folders
    'folder' => [
        'inbox' => 'Bandeja de entrada principal',
        'promotions' => 'Promociones',
        'spam' => 'Spam',
    ],

    // Table headers
    'subject' => 'Asunto',
    'domain' => 'Dominio',
    'score' => 'Puntuación',
    'folder' => 'Carpeta',

    // Actions
    'confirm_delete' => '¿Está seguro de que desea eliminar este dominio?',

    // Messages
    'messages' => [
        'domain_added' => 'Dominio añadido exitosamente. Añada el registro CNAME para verificar.',
        'cname_verified' => '¡Dominio verificado exitosamente!',
        'cname_not_found' => 'Registro CNAME no encontrado. Por favor, compruebe su configuración DNS.',
        'status_refreshed' => 'Estado actualizado exitosamente.',
        'domain_removed' => 'Dominio eliminado exitosamente.',
        'alerts_updated' => 'Configuración de alertas actualizada.',
        'simulation_complete' => '¡Simulación completada!',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Por favor, introduzca un nombre de dominio válido',
        'domain_exists' => 'Este dominio ya ha sido añadido',
    ],

    // Upsell for non-GOLD users
    'upsell' => [
        'title' => 'Desbloquear Deliverability Shield',
        'description' => 'Maximice su entregabilidad de correo electrónico con herramientas avanzadas. Asegure que cada correo llegue a la bandeja de entrada, no al spam.',
        'feature1' => 'DMARC Wiz - Configuración fácil de dominio',
        'feature2' => 'InboxPassport AI - Pruebas previas al envío',
        'feature3' => 'Monitoreo DNS 24/7',
        'feature4' => 'Alertas y recomendaciones automatizadas',
        'cta' => 'Actualizar a GOLD',
    ],
];
