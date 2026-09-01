<?php
/**
 * Envío de correos transaccionales por la API HTTP de Brevo.
 *
 * Se usa API HTTP (puerto 443) y no SMTP porque InfinityFree bloquea los
 * puertos de correo. Requiere includes/mail_config.php (ver .example).
 */

/**
 * Carga la config de correo una sola vez. Si el archivo no existe, el correo
 * queda desactivado (nunca rompe la app).
 */
function mailConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/mail_config.php';
        $cfg  = is_file($file) ? (array) (require $file) : [];
        $cfg += [
            'brevo_api_key' => '',
            'from_email'    => '',
            'from_name'     => 'El Corralín de Campanal',
            'bcc'           => '',
            'enabled'       => false,
            'sitio_url'     => 'https://corralin.kesug.com',
        ];
    }
    return $cfg;
}

function mailHabilitado(): bool
{
    $c = mailConfig();
    return !empty($c['enabled']) && $c['brevo_api_key'] !== '' && $c['from_email'] !== '';
}

/**
 * Manda un correo. Devuelve [ok(bool), detalle(string)]. Nunca lanza excepción.
 */
function enviarCorreoBrevo(string $paraEmail, string $paraNombre, string $asunto, string $html): array
{
    $c = mailConfig();

    if (!mailHabilitado()) {
        return [false, 'correo deshabilitado o sin configurar'];
    }
    if (!filter_var($paraEmail, FILTER_VALIDATE_EMAIL)) {
        return [false, 'email de destino inválido'];
    }

    $payload = [
        'sender'      => ['name' => $c['from_name'], 'email' => $c['from_email']],
        'to'          => [['email' => $paraEmail, 'name' => $paraNombre !== '' ? $paraNombre : $paraEmail]],
        'subject'     => $asunto,
        'htmlContent' => $html,
    ];
    if (!empty($c['bcc']) && filter_var($c['bcc'], FILTER_VALIDATE_EMAIL)) {
        $payload['bcc'] = [['email' => $c['bcc']]];
    }

    $json     = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $endpoint = 'https://api.brevo.com/v3/smtp/email';
    $headers  = [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $c['brevo_api_key'],
    ];

    // 1) cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return [false, 'cURL: ' . $err];
        }
        return [$code >= 200 && $code < 300, 'HTTP ' . $code . ' ' . substr((string) $resp, 0, 300)];
    }

    // 2) Fallback con stream
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => 12,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($endpoint, false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if ($resp === false) {
        return [false, 'sin respuesta de la API de Brevo'];
    }
    return [$code >= 200 && $code < 300, 'HTTP ' . $code];
}

/* ══════════════════════ Plantillas ══════════════════════ */

function e_($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Cáscara HTML común (membrete verde + pie). $cuerpo ya viene escapado.
 */
function correoLayout(string $preheader, string $cuerpo): string
{
    $sitio = rtrim(mailConfig()['sitio_url'], '/');
    $logo  = $sitio . '/assets/img/logo-horizontal-blanco.png';

    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f1f1ee;font-family:Georgia,\'Times New Roman\',serif;color:#2a2a25;">'
        . '<span style="display:none;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">' . e_($preheader) . '</span>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f1ee;padding:24px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:92%;background:#ffffff;border:1px solid #e4e1d9;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#2d5f3f;padding:22px 28px;">'
        . '<img src="' . e_($logo) . '" alt="El Corralín de Campanal" height="34" style="height:34px;display:block;">'
        . '</td></tr>'
        . '<tr><td style="padding:28px;">' . $cuerpo . '</td></tr>'
        . '<tr><td style="background:#f5efe0;padding:16px 28px;font-size:12px;color:#6b6558;line-height:1.6;">'
        . 'El Corralín de Campanal &middot; Plaza Manuel Uría, 4 &middot; 33520 Nava (Asturias)<br>'
        . 'Tel. 985 71 60 42 &middot; <a href="' . e_($sitio) . '" style="color:#2d5f3f;">corralin.kesug.com</a>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Fila etiqueta / valor para el bloque de datos.
 */
function correoFila(string $etiqueta, string $valor): string
{
    return '<tr>'
        . '<td style="padding:7px 0;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b6558;width:130px;vertical-align:top;">' . e_($etiqueta) . '</td>'
        . '<td style="padding:7px 0;font-size:15px;color:#2a2a25;">' . e_($valor) . '</td>'
        . '</tr>';
}

/**
 * Construye el correo de "reserva confirmada".
 *
 * @param array $r  fecha (Y-m-d), hora (H:i o H:i:s), personas, codigo, nombre,
 *                  mesa_numero (int|null), comentario
 * @return array [asunto, html]
 */
function correoConfirmacionReserva(array $r): array
{
    $DIAS = ['Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves',
             'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'];
    $MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
             7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    $f          = new DateTime($r['fecha']);
    $fechaLarga = $DIAS[$f->format('l')] . ', ' . (int) $f->format('j') . ' de ' . $MESES[(int) $f->format('n')] . ' de ' . $f->format('Y');
    $hora       = substr((string) $r['hora'], 0, 5);
    $personas   = (int) $r['personas'];
    $mesa       = !empty($r['mesa_numero']) ? 'Mesa ' . (int) $r['mesa_numero'] : 'Se asignará a la llegada';
    $nombre     = trim((string) ($r['nombre'] ?? ''));

    $filas  = correoFila('Código', $r['codigo']);
    $filas .= correoFila('Fecha', $fechaLarga);
    $filas .= correoFila('Hora', $hora . ' h');
    $filas .= correoFila('Mesa', $mesa);
    $filas .= correoFila('Comensales', $personas === 1 ? '1 persona' : $personas . ' personas');
    if (trim((string) ($r['comentario'] ?? '')) !== '') {
        $filas .= correoFila('Nota', $r['comentario']);
    }

    $cuerpo = '<p style="margin:0 0 14px;font-size:16px;">' . e_($nombre !== '' ? "Hola, $nombre:" : 'Hola:') . '</p>'
        . '<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#4a4a42;">'
        . 'Tu reserva en El Corralín de Campanal quedó <strong style="color:#2d5f3f;">confirmada</strong>. '
        . 'Guardá este correo; el día de tu visita te va a servir el código.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
        . 'style="border-top:2px solid #c9962e;border-bottom:1px solid #e4e1d9;margin:6px 0 20px;">' . $filas . '</table>'
        . '<p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#4a4a42;">'
        . 'La mesa se mantiene durante 15 minutos a partir de la hora reservada. '
        . 'Para cualquier cambio o anulación, respondé a este correo o llamanos al 985 71 60 42.</p>'
        . '<p style="margin:18px 0 0;font-size:15px;color:#2d5f3f;">Te esperamos.</p>';

    $asunto = 'Reserva confirmada · ' . $r['codigo'] . ' · ' . $fechaLarga;

    return [$asunto, correoLayout('Reserva confirmada para el ' . $fechaLarga . ' a las ' . $hora . ' h', $cuerpo)];
}
