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
        'subtitle' => 'Añada su dominio en segundos',
        'step_domain' => 'Dominio',
        'step_verify' => 'Verificar',
        'enter_domain_title' => 'Introduzca su dominio',
        'enter_domain_description' => 'Este es el dominio desde el que envía correos electrónicos',
        'add_record_title' => 'Añadir registro DNS',
        'add_record_description' => 'Añada este registro CNAME a la configuración DNS de su dominio',
        'dns_propagation_info' => 'Los cambios en el DNS pueden tardar hasta 48 horas en propagarse. Puede verificar en cualquier momento.',
        'add_and_verify' => 'Añadir y comprobar verificación',
        'add_domain_btn' => 'Añadir dominio',
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

    // Domain issues
    'domain' => [
        'spf_warning' => 'El registro SPF tiene estado de advertencia - puede afectar la entregabilidad',
        'dmarc_policy_none' => 'La política DMARC está configurada como "none" - los correos pueden llegar al spam',
    ],

    // DNS Issues (detailed)
    'issues' => [
        'spf_missing' => 'No se encontró registro SPF para este dominio',
        'spf_no_include' => 'El registro SPF no contiene el include requerido',
        'spf_no_provider_include' => 'El registro SPF no contiene include de :provider (:required)',
        'spf_permissive' => 'El registro SPF es demasiado permisivo (+all o ?all)',
        'dkim_missing' => 'No se encontró registro DKIM (selectores comprobados: :selectors_checked)',
        'dkim_invalid' => 'El registro DKIM es inválido (falta la clave pública)',
        'dmarc_missing' => 'No se encontró registro DMARC para este dominio',
        'dmarc_none' => 'La política DMARC está configurada como "none"',
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
        'gmail_managed_dns' => 'Gmail gestiona automáticamente SPF/DKIM para su cuenta. No se requiere configuración DNS adicional.',
        'domain_not_configured' => 'El dominio :domain no está configurado en DMARC Wiz. Añádalo para un análisis completo de entregabilidad.',
        'no_domain_warning' => 'Ningún dominio configurado. Análisis basado solo en el contenido del mensaje.',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Por favor, introduzca un nombre de dominio válido',
        'domain_exists' => 'Este dominio ya ha sido añadido',
    ],

    // Localhost/Development Environment Warning
    'localhost_warning' => [
        'title' => 'Entorno de desarrollo detectado',
        'description' => 'Está ejecutando NetSendo en localhost. La verificación DNS requiere un dominio público. Los registros CNAME que apuntan a localhost no pueden ser verificados.',
    ],

    // HTML Analysis Issues
    'html' => [
        'ratio_low' => 'Baja proporción de texto a HTML - su correo contiene demasiado código HTML',
        'hidden_text' => 'Texto oculto detectado (display:none) - esto es un indicador de spam',
        'tiny_font' => 'Tamaño de fuente muy pequeño detectado - esto es un indicador de spam',
        'image_heavy' => 'Correo con muchas imágenes y poco texto - añada más contenido de texto',
    ],

    // Subject Analysis Issues
    'subject' => [
        'too_long' => 'El asunto es demasiado largo (más de 60 caracteres)',
        'too_short' => 'El asunto es demasiado corto (menos de 5 caracteres)',
        'all_caps' => 'El asunto contiene demasiadas mayúsculas',
        'exclamations' => 'El asunto contiene demasiados signos de exclamación',
        'questions' => 'El asunto contiene demasiados signos de interrogación',
        'fake_reply' => 'El asunto comienza con RE: o FW: lo que parece una respuesta falsa',
    ],

    // Link Issues
    'links' => [
        'shortener' => 'Acortador de URL detectado - use URLs completas',
        'suspicious_tld' => 'Extensión de dominio sospechosa detectada',
        'ip_address' => 'Dirección IP en URL detectada - use nombres de dominio apropiados',
        'too_many' => 'Demasiados enlaces en el correo (más de 20)',
    ],

    // Formatting Issues
    'formatting' => [
        'caps' => 'El contenido contiene demasiadas mayúsculas',
        'symbols' => 'El contenido contiene demasiados símbolos especiales',
    ],

    // Content Issues
    'content' => [
        'spam_word' => 'Palabra desencadenante de spam detectada: ":word"',
    ],

    // Spam Words
    'spam' => [
        'word_detected' => 'Palabra desencadenante de spam detectada',
    ],

    // Recommendations
    'recommendations' => [
        'fix_domain' => 'Corrija los problemas de configuración DNS de su dominio',
        'upgrade_dmarc' => 'Actualice su política DMARC de "none" a "quarantine" o "reject"',
        'remove_spam_words' => 'Elimine o reemplace las palabras desencadenantes de spam en su contenido',
        'improve_subject' => 'Mejore su línea de asunto - evite mayúsculas y puntuación excesiva',
        'fix_html' => 'Corrija los problemas de estructura HTML - mejore la proporción texto/HTML',
        'fix_links' => 'Corrija los problemas de enlaces - evite acortadores de URL y dominios sospechosos',
        'looks_good' => '¡Su correo se ve bien! No se detectaron problemas importantes',
        'add_domain' => 'Añada y verifique un dominio en DMARC Wiz para un análisis completo de entregabilidad',
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

    // DMARC Generator (One-Click Fix)
    'dmarc_generator' => [
        'title' => 'Generador DMARC',
        'subtitle' => 'Genere el registro DMARC óptimo con un clic',
        'initial_explanation' => 'Comience con la política "quarantine" para monitorear sin bloquear. Este es un inicio seguro.',
        'recommended_explanation' => 'Protección completa con política "reject". Use después de 7-14 días de monitoreo sin problemas.',
        'minimal_explanation' => 'Configuración DMARC mínima con política "quarantine" y reportes básicos.',
        'upgrade_notice' => 'Después de 7-14 días sin problemas, puede actualizar de forma segura a la política "reject" para máxima protección.',
        'copy_record' => 'Copiar registro',
        'current_policy' => 'Política actual',
        'recommended_policy' => 'Política recomendada',
        'report_email' => 'Correo de reportes',
        'report_email_hint' => 'Recibirá reportes DMARC en esta dirección',
    ],

    // SPF Generator (One-Click Fix)
    'spf_generator' => [
        'title' => 'Generador SPF',
        'subtitle' => 'Genere un registro SPF optimizado',
        'optimal_explanation' => 'Registro SPF simplificado con fallo duro (-all). Incluye solo los includes necesarios para su proveedor.',
        'softfail_explanation' => 'Registro SPF con fallo suave (~all). Menos restrictivo pero puede afectar la entregabilidad.',
        'lookup_warning' => 'Su registro SPF actual supera o se acerca al límite de 10 consultas DNS. Recomendamos simplificarlo.',
        'lookup_count' => 'Consultas DNS',
        'max_lookups' => 'Límite máximo',
        'copy_record' => 'Copiar registro',
        'current_record' => 'Registro actual',
        'optimal_record' => 'Registro optimizado',
        'provider_detected' => 'Proveedor detectado',
    ],

    // DNS Generator Common
    'dns_generator' => [
        'instructions_title' => 'Cómo agregar un registro DNS',
        'step1' => '1. Acceda al panel DNS de su dominio (ej. GoDaddy, Cloudflare, Arsys)',
        'step2' => '2. Agregue un nuevo registro TXT con los datos anteriores',
        'step3' => '3. Espere la propagación DNS (hasta 48h) y haga clic en "Verificar"',
        'copy_success' => '¡Copiado al portapapeles!',
        'copy_failed' => 'Error al copiar. Por favor, copie manualmente.',
        'show_generator' => 'Mostrar generador',
        'hide_generator' => 'Ocultar generador',
        'one_click_fix' => 'Reparación con un clic',
    ],
];

