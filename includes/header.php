<!DOCTYPE html>
<html class="light" lang="es">
<head>
  <link rel="icon" href="<?= buildUrl('/image.ico') ?>" type="image/x-icon">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&family=Noto+Serif:wght@600;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script id="tailwind-config">
    // Las pantallas usan CSS propio; solo se personaliza esta utilidad de Tailwind.
    tailwind.config = {
      theme: { extend: { fontFamily: { 'body-md': ['Be Vietnam Pro'] } } }
    };
  </script>
  <script src="<?= jsUrl('comun.js') ?>"></script>
  <?php if (sesionAutenticada()): ?>
    <script src="<?= jsUrl('sesion.js') ?>" defer
            data-api="<?= buildUrl('/api/sesion.php') ?>"
            data-login="<?= buildUrl('/public/login.php?sesion=expirada') ?>"></script>
  <?php endif; ?>
  <?php if (!empty($pageCSS)): ?>
    <?php
      // Cache-buster: la versión cambia con la fecha de modificación del archivo,
      // para que el navegador (y el caché estático de InfinityFree) siempre sirvan
      // el CSS actualizado después de un deploy en vez de una copia vieja.
      $cssPath = '/' . preg_replace('~^(\.\./)+~', '', $pageCSS);
      $cssAbsPath = __DIR__ . '/..' . $cssPath;
      $cssVersion = is_file($cssAbsPath) ? filemtime($cssAbsPath) : time();
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(buildUrl($cssPath), ENT_QUOTES, 'UTF-8') ?>?v=<?= $cssVersion ?>" />
  <?php endif; ?>
  <title><?= htmlspecialchars($pageTitle ?? 'Margo Project', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body class="fondo principal min-h-screen flex flex-col font-body-md">
