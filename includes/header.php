<!DOCTYPE html>
<html class="light" lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&family=Noto+Serif:wght@600;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "secondary-container": "#fed488",
            "secondary": "#775a19",
            "on-surface-variant": "#434840",
            "secondary-fixed-dim": "#e9c176",
            "outline-variant": "#c3c8bd",
            "inverse-surface": "#2f312d",
            "error-container": "#ffdad6",
            "tertiary": "#3b3c3a",
            "surface-container-lowest": "#ffffff",
            "on-primary-container": "#aed0a1",
            "surface-container": "#eeeee8",
            "on-tertiary-fixed-variant": "#464744",
            "tertiary-fixed": "#e3e2df",
            "surface": "#fafaf4",
            "inverse-on-surface": "#f1f1eb",
            "secondary-fixed": "#ffdea5",
            "on-background": "#1a1c19",
            "on-secondary-container": "#785a1a",
            "background": "#fafaf4",
            "on-primary-fixed-variant": "#314e2a",
            "surface-container-low": "#f4f4ee",
            "error": "#ba1a1a",
            "surface-tint": "#486640",
            "primary": "#264220",
            "on-primary-fixed": "#062104",
            "primary-container": "#3d5a35",
            "on-error-container": "#93000a",
            "on-secondary-fixed-variant": "#5d4201",
            "tertiary-container": "#525350",
            "tertiary-fixed-dim": "#c7c7c3",
            "on-surface": "#1a1c19",
            "on-secondary": "#ffffff",
            "surface-dim": "#dadad5",
            "on-tertiary": "#ffffff",
            "inverse-primary": "#aed0a1",
            "on-tertiary-fixed": "#1b1c1a",
            "surface-bright": "#fafaf4",
            "primary-fixed": "#caecbc",
            "surface-container-high": "#e9e8e3",
            "primary-fixed-dim": "#aed0a1",
            "surface-variant": "#e3e3dd",
            "on-primary": "#ffffff",
            "on-secondary-fixed": "#261900",
            "on-error": "#ffffff",
            "on-tertiary-container": "#c7c7c3",
            "surface-container-highest": "#e3e3dd",
            "outline": "#73796f"
          },
          borderRadius: {
            DEFAULT: "0.25rem",
            lg: "0.5rem",
            xl: "0.75rem",
            full: "9999px"
          },
          spacing: {
            unit: "8px",
            gutter: "24px",
            "container-max": "1280px",
            "margin-mobile": "16px",
            "margin-desktop": "64px"
          },
          fontFamily: {
            "headline-md": ["Noto Serif"],
            "body-md": ["Be Vietnam Pro"],
            button: ["Be Vietnam Pro"],
            "headline-lg": ["Noto Serif"],
            "label-caps": ["Be Vietnam Pro"],
            "headline-lg-mobile": ["Noto Serif"],
            "display-lg": ["Noto Serif"],
            "body-lg": ["Be Vietnam Pro"]
          },
          fontSize: {
            "headline-md": ["24px", { lineHeight: "32px", fontWeight: "600" }],
            "body-md": ["16px", { lineHeight: "24px", fontWeight: "400" }],
            button: ["14px", { lineHeight: "20px", fontWeight: "600" }],
            "headline-lg": ["32px", { lineHeight: "40px", fontWeight: "600" }],
            "label-caps": ["12px", { lineHeight: "16px", letterSpacing: "0.1em", fontWeight: "700" }],
            "headline-lg-mobile": ["28px", { lineHeight: "36px", fontWeight: "600" }],
            "display-lg": ["48px", { lineHeight: "56px", letterSpacing: "-0.02em", fontWeight: "700" }],
            "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }]
          }
        }
      }
    }
  </script>
  <?php if (!empty($pageCSS)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCSS, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <title><?= htmlspecialchars($pageTitle ?? 'Margo Project', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body class="fondo principal min-h-screen flex flex-col font-body-md">
