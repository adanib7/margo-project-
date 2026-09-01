<?php
/**
 * Plantilla de configuración de correo (Brevo).
 *
 * COPIÁ este archivo como  includes/mail_config.php  y completá tus datos.
 * mail_config.php está en .gitignore: la API key NO se sube al repo.
 *
 * Pasos en Brevo (https://www.brevo.com, plan gratuito):
 *   1. Crear cuenta.
 *   2. Settings -> Senders, Domains & Dedicated IPs -> "Senders":
 *      agregar y VERIFICAR el email remitente (te llega un mail de confirmación).
 *      Con un Gmail alcanza (ej. reservas.corralin@gmail.com).
 *   3. Settings -> "SMTP & API" -> pestaña "API Keys" -> generar una v3 key.
 */

return [
    // La API key v3 de Brevo (empieza con "xkeysib-").
    'brevo_api_key' => '',

    // Remitente YA verificado en Brevo.
    'from_email' => 'reservas.corralin@gmail.com',
    'from_name'  => 'El Corralín de Campanal',

    // Copia oculta al restaurante en cada correo (opcional, dejar '' para nada).
    'bcc' => '',

    // Interruptor general. Con false no se manda ningún correo (útil en local).
    'enabled' => false,
];
