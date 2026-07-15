<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>El Corralín del Campanal · Restaurante y llagar en Nava, Asturias</title>
<link rel="stylesheet" href="assets/css/colors_and_type.css">
<script src="assets/js/lucide.min.js" defer></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{background:var(--crema-50);color:var(--fg-default);font-family:var(--font-body);overflow-x:hidden}
a{color:var(--verde-700);text-decoration:none;transition:color var(--dur-fast) var(--ease-out)}
a:hover{color:var(--dorado-600)}
img{display:block;max-width:100%}
section{scroll-margin-top:96px}
.wrap{width:min(1200px,100% - 64px);margin-inline:auto}
.eyebrow{display:inline-block}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:var(--font-display);font-weight:500;font-size:var(--fs-button);letter-spacing:var(--ls-button);text-transform:uppercase;border-radius:var(--radius-md);padding:0 24px;min-height:52px;cursor:pointer;border:1px solid transparent;transition:all var(--dur-base) var(--ease-out);white-space:nowrap}
.btn:active{transform:scale(.97)}
.btn i{width:18px;height:18px}
.btn-primary{background:var(--verde-700);color:var(--fg-on-brand)}
.btn-primary:hover{background:var(--verde-800);color:#fff}
.btn-accent{background:var(--dorado-500);color:var(--verde-900)}
.btn-accent:hover{background:var(--dorado-600);color:var(--verde-900)}
.btn-secondary{background:transparent;color:var(--verde-700);border-color:var(--verde-700)}
.btn-secondary:hover{background:var(--verde-50)}
.btn-ghost-light{background:transparent;color:#fff;border-color:rgba(245,239,224,.5)}
.btn-ghost-light:hover{background:rgba(245,239,224,.12);color:#fff}
.btn-lg{min-height:56px;padding:0 32px;font-size:14px}

/* ---- placeholder (foto pendiente) ---- */
.ph{position:relative;background:repeating-linear-gradient(135deg,#ECE3CB,#ECE3CB 14px,#F5EFE0 14px,#F5EFE0 28px);border-radius:var(--radius-lg);overflow:hidden;display:flex;align-items:center;justify-content:center}
.ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.ph:has(img)::after{display:none}
.ph::after{content:attr(data-note);position:absolute;font-family:var(--font-mono);font-size:11px;letter-spacing:.04em;color:var(--verde-700);background:rgba(251,247,236,.85);padding:6px 12px;border-radius:var(--radius-pill);border:1px solid var(--verde-100);max-width:80%;text-align:center;line-height:1.4}

/* ---- header ---- */
header{position:fixed;top:0;left:0;right:0;height:76px;z-index:100;display:flex;align-items:center;transition:background var(--dur-base) var(--ease-out),box-shadow var(--dur-base) var(--ease-out),border-color var(--dur-base) var(--ease-out);border-bottom:1px solid transparent}
header .wrap{display:flex;align-items:center;gap:24px}
header .logo-link{flex-shrink:0;display:block}
header .logo-img{height:72px;width:auto;display:block;filter:brightness(0) invert(1);transition:filter var(--dur-base) var(--ease-out)}
nav.main{display:flex;gap:28px;margin-left:auto}
nav.main a{font-family:var(--font-display);font-weight:500;font-size:14px;color:#fff;letter-spacing:.01em}
nav.main a:hover{color:var(--dorado-300)}
.header-actions{display:flex;align-items:center;gap:16px;margin-left:auto}
/* scrolled state */
header.solid{background:var(--crema-50);border-bottom-color:var(--gris-200);box-shadow:var(--shadow-sm)}
header.solid nav.main a{color:var(--gris-800)}
header.solid nav.main a:hover{color:var(--dorado-600)}
header.solid .logo-img{filter:none}
header.solid .lang-btn{color:var(--gris-700);border-color:var(--gris-300)}
header.solid .lang-btn.on{background:var(--verde-700);color:#fff;border-color:var(--verde-700)}

/* ---- language pills ---- */
.lang{display:flex;gap:4px;background:rgba(27,59,39,.28);border-radius:var(--radius-pill);padding:4px}
.lang-btn{font-family:var(--font-display);font-weight:600;font-size:11px;letter-spacing:.06em;color:#fff;background:transparent;border:1px solid transparent;border-radius:var(--radius-pill);height:32px;min-width:38px;padding:0 4px;cursor:pointer;transition:all var(--dur-fast) var(--ease-out)}
.lang-btn:hover{background:rgba(255,255,255,.14)}
.lang-btn.on{background:var(--dorado-500);color:var(--verde-900);border-color:var(--dorado-500)}
header.solid .lang{background:var(--crema-200)}

/* ---- hamburger toggle ---- */
.menu-toggle{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;flex-shrink:0;background:transparent;border:none;cursor:pointer;margin-left:auto}
.menu-toggle span{display:block;width:24px;height:2px;border-radius:2px;background:#fff;transition:transform var(--dur-base) var(--ease-out),opacity var(--dur-fast) var(--ease-out),background var(--dur-base) var(--ease-out)}
header.solid .menu-toggle span{background:var(--gris-800)}
.menu-toggle.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.menu-toggle.open span:nth-child(2){opacity:0}
.menu-toggle.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

/* ---- mobile menu ---- */
.mobile-backdrop{position:fixed;inset:0;background:rgba(20,28,20,.45);opacity:0;pointer-events:none;transition:opacity var(--dur-base) var(--ease-out);z-index:98}
.mobile-backdrop.open{opacity:1;pointer-events:auto}
.mobile-menu{position:fixed;top:76px;left:0;right:0;background:var(--crema-50);border-bottom:1px solid var(--gris-200);box-shadow:var(--shadow-lg);z-index:99;max-height:calc(100vh - 76px);overflow-y:auto;opacity:0;transform:translateY(-14px) scale(.98);transform-origin:top center;pointer-events:none;transition:opacity var(--dur-base) var(--ease-out),transform var(--dur-base) var(--ease-out)}
.mobile-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
.mobile-nav{display:flex;flex-direction:column;padding:8px 0}
.mobile-nav a{font-family:var(--font-display);font-weight:600;font-size:17px;color:var(--gris-800);padding:15px 4px;border-bottom:1px solid var(--gris-100);opacity:0;transform:translateX(-14px);transition:opacity var(--dur-base) var(--ease-out),transform var(--dur-base) var(--ease-out),color var(--dur-fast) var(--ease-out)}
.mobile-menu.open .mobile-nav a{opacity:1;transform:translateX(0)}
.mobile-menu.open .mobile-nav a:nth-child(1){transition-delay:.04s}
.mobile-menu.open .mobile-nav a:nth-child(2){transition-delay:.08s}
.mobile-menu.open .mobile-nav a:nth-child(3){transition-delay:.12s}
.mobile-menu.open .mobile-nav a:nth-child(4){transition-delay:.16s}
.mlang-block{border-bottom:1px solid var(--gris-100);opacity:0;transform:translateX(-14px);transition:opacity var(--dur-base) var(--ease-out),transform var(--dur-base) var(--ease-out)}
.mobile-menu.open .mlang-block{opacity:1;transform:translateX(0);transition-delay:.2s}
.mlang-toggle{display:flex;align-items:center;gap:10px;width:100%;background:transparent;border:none;padding:15px 4px;font-family:var(--font-display);font-weight:600;font-size:15px;color:var(--gris-800);cursor:pointer}
.mlang-toggle i[data-lucide="globe"]{width:18px;height:18px;color:var(--verde-700)}
.mlang-current{margin-left:auto;font-size:12px;letter-spacing:.06em;color:var(--fg-muted)}
.mlang-chevron{width:16px;height:16px;color:var(--fg-muted);transition:transform var(--dur-base) var(--ease-out)}
.mlang-block.open .mlang-chevron{transform:rotate(90deg)}
.mlang-options{display:grid;grid-template-columns:0fr;transition:grid-template-columns var(--dur-base) var(--ease-out)}
.mlang-block.open .mlang-options{grid-template-columns:1fr}
.mlang-options-inner{overflow:hidden;display:flex;gap:8px;min-width:0;padding-bottom:14px}
.mlang-options .lang-btn{color:var(--gris-700);background:var(--crema-100);border:1px solid var(--gris-300);flex-shrink:0}
.mlang-options .lang-btn:hover{background:var(--crema-200)}
.mlang-options .lang-btn.on{background:var(--verde-700);color:#fff;border-color:var(--verde-700)}
.mobile-reservar{margin-top:20px;opacity:0;transform:translateX(-14px);transition:opacity var(--dur-base) var(--ease-out),transform var(--dur-base) var(--ease-out);transition-delay:.24s}
.mobile-menu.open .mobile-reservar{opacity:1;transform:translateX(0)}

/* ---- hero ---- */
.hero{position:relative;min-height:100vh;display:flex;align-items:center;padding-top:76px}
.hero .bg{position:absolute;inset:0;background:#234C32}
.hero .bg img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.hero .overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(27,59,39,.55) 0%,rgba(27,59,39,.66) 55%,rgba(27,59,39,.82) 100%)}
.hero .wrap{position:relative;z-index:2;padding-block:64px}
.hero-eyebrow{color:var(--dorado-300);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;font-family:var(--font-display);font-weight:500;font-size:13px}
.hero h1{color:#fff;font-family:var(--font-display);font-weight:700;font-size:clamp(40px,6vw,68px);line-height:1.04;letter-spacing:-.02em;margin:20px 0;max-width:16ch}
.hero .lede{color:var(--crema-100);font-size:19px;line-height:1.6;max-width:52ch;margin-bottom:36px}
.hero-cta{display:flex;gap:16px;flex-wrap:wrap}
.hero-meta{display:flex;gap:40px;flex-wrap:wrap;margin-top:56px;padding-top:32px;border-top:1px solid rgba(245,239,224,.2)}
.hero-meta .item{display:flex;align-items:center;gap:12px;color:var(--crema-100)}
.hero-meta i{width:22px;height:22px;color:var(--dorado-300)}
.hero-meta .k{font-family:var(--font-display);font-weight:600;font-size:15px;color:#fff}
.hero-meta .s{font-size:13px;color:rgba(245,239,224,.75)}

/* ---- section shell ---- */
.sec{padding-block:96px}
.sec-head{max-width:640px;margin-bottom:56px}
.sec-head.center{margin-inline:auto;text-align:center}
.sec-head h2{font-family:var(--font-display);font-weight:700;font-size:clamp(30px,3.6vw,44px);line-height:1.1;letter-spacing:-.015em;color:var(--verde-800);margin:14px 0}
.sec-head p{font-size:17px;line-height:1.6;color:var(--fg-muted)}

/* ---- cocina cards ---- */
.dishes{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.dish{background:var(--blanco);border:1px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm);transition:transform var(--dur-base) var(--ease-out),box-shadow var(--dur-base) var(--ease-out)}
.dish:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.dish .ph{height:220px;border-radius:0}
.dish .body{padding:24px}
.dish h3{font-family:var(--font-display);font-weight:600;font-size:21px;color:var(--verde-800);margin-bottom:8px}
.dish p{font-size:15px;line-height:1.6;color:var(--fg-muted)}
.dish .tag{font-family:var(--font-display);font-weight:600;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--dorado-600);display:block;margin-bottom:6px}

/* ---- galeria ---- */
.gallery{display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:200px;gap:16px}
.gallery .ph{border-radius:var(--radius-md)}
.gallery .g1{grid-column:span 2;grid-row:span 2}
.gallery .g4{grid-column:span 2}

/* ---- historia ---- */
.split{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.split .ph{height:480px}
.split .txt .eyebrow{margin-bottom:14px}
.split .txt h2{font-family:var(--font-display);font-weight:700;font-size:clamp(28px,3.4vw,40px);line-height:1.12;letter-spacing:-.015em;color:var(--verde-800);margin-bottom:20px}
.split .txt p{font-size:16px;line-height:1.7;color:var(--fg-default);margin-bottom:16px}
.split .txt p.muted{color:var(--fg-muted)}
.stats{display:flex;gap:40px;margin-top:32px}
.stats .n{font-family:var(--font-display);font-weight:700;font-size:34px;color:var(--dorado-600);line-height:1}
.stats .l{font-size:13px;color:var(--fg-muted);margin-top:6px;max-width:14ch}


/* ---- ubicacion ---- */
.info-grid{display:grid;grid-template-columns:1fr 1.15fr;gap:56px;align-items:stretch}
.hours-card{background:var(--blanco);border:1px solid var(--gris-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:36px}
.hours-card h3{font-family:var(--font-display);font-weight:600;font-size:22px;color:var(--verde-800);margin-bottom:24px}
.hrow{display:flex;justify-content:space-between;align-items:baseline;padding:13px 0;border-bottom:1px solid var(--gris-100)}
.hrow:last-child{border-bottom:0}
.hrow .day{font-family:var(--font-display);font-weight:500;font-size:15px;color:var(--gris-800)}
.hrow .time{font-size:14px;color:var(--fg-muted)}
.hrow.closed .time{color:var(--burdeos-500)}
.hrow.today{background:var(--verde-50);margin-inline:-16px;padding-inline:16px;border-radius:var(--radius-sm);border-bottom-color:transparent}
.hrow.today .day{color:var(--verde-800)}
.addr{margin-top:28px;display:grid;gap:16px}
.addr .line{display:flex;gap:12px;align-items:flex-start}
.addr i{width:20px;height:20px;color:var(--dorado-600);flex-shrink:0;margin-top:2px}
.addr .t{font-size:15px;line-height:1.5;color:var(--gris-800)}
.map .ph{height:100%;min-height:460px}

/* ---- contacto / footer ---- */
.contact{background:var(--crema-100)}
.contact .split{gap:56px}
.form-card{background:var(--blanco);border:1px solid var(--gris-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);padding:40px}
.contact .txt h2{font-family:var(--font-display);font-weight:700;font-size:clamp(28px,3.4vw,40px);color:var(--verde-800);line-height:1.1;letter-spacing:-.015em;margin-bottom:20px}
.contact .txt p{font-size:16px;line-height:1.7;color:var(--fg-muted);margin-bottom:28px}
.contact-lines{display:grid;gap:20px}
.contact-lines .cl{display:flex;gap:14px;align-items:center}
.contact-lines .ci{width:48px;height:48px;border-radius:var(--radius-md);background:var(--verde-50);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.contact-lines .ci i{width:22px;height:22px;color:var(--verde-700)}
.contact-lines .ck{font-family:var(--font-display);font-weight:600;font-size:15px;color:var(--gris-900)}
.contact-lines .cv{font-size:14px;color:var(--fg-muted);margin-top:2px}

footer{background:var(--verde-900);color:var(--crema-100);padding:64px 0 32px}
footer .cols{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:48px;padding-bottom:48px;border-bottom:1px solid rgba(245,239,224,.15);min-width:0}
footer .cols>div{min-width:0}
footer .logo-img{height:48px;width:auto;filter:brightness(0) invert(1);margin-bottom:12px}
footer .about{font-size:14px;line-height:1.7;color:rgba(245,239,224,.7);margin-top:20px;max-width:34ch}
footer h4{font-family:var(--font-display);font-weight:600;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--dorado-300);margin-bottom:18px}
footer ul{list-style:none;display:grid;gap:12px}
footer ul a{color:rgba(245,239,224,.8);font-size:14px}
footer ul a:hover{color:#fff}
footer .bottom{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding-top:28px;font-size:12px;color:rgba(245,239,224,.55)}
footer .bottom a{color:rgba(245,239,224,.7)}

/* ---- responsive ---- */
@media(max-width:960px){
  nav.main{display:none}
  .header-actions .lang{display:none}
  .header-actions .btn-reservar-top{display:none}
  .menu-toggle{display:flex}
  .dishes{grid-template-columns:1fr;max-width:440px;margin-inline:auto}
  .gallery{grid-template-columns:repeat(2,1fr)}
  .split,.menu-grid,.info-grid,.contact .split{grid-template-columns:1fr;gap:40px}
  .split .ph{height:340px;order:-1}
  .map .ph{min-height:320px}
  .sec{padding-block:72px}
  footer .cols{grid-template-columns:1fr;gap:36px}
}
@media(max-width:560px){
  .wrap{width:calc(100% - 40px)}
  .hero-meta{gap:24px}
  .stats{flex-wrap:wrap;gap:28px}
  .gallery{grid-template-columns:1fr 1fr;grid-auto-rows:150px}
}
</style>
</head>
<body>

<header id="site-header">
  <div class="wrap">
    <a href="#top" class="logo-link">
      <img src="assets/img/logo-horizontal-verde.png" alt="El Corralín de Campanal" class="logo-img">
    </a>
    <nav class="main">
      <a href="#cocina" data-i18n="nav_cocina">La cocina</a>
      <a href="#galeria" data-i18n="nav_galeria">Galería</a>
      <a href="#historia" data-i18n="nav_historia">Historia</a>
      <a href="#contacto" data-i18n="nav_contacto">Contacto</a>
    </nav>
    <div class="header-actions">
      <div class="lang" id="lang">
        <button class="lang-btn on" data-lang="es">ES</button>
        <button class="lang-btn" data-lang="en">EN</button>
        <button class="lang-btn" data-lang="fr">FR</button>
        <button class="lang-btn" data-lang="pt">PT</button>
      </div>
      <a href="public/login.php" class="btn btn-accent btn-reservar-top" data-i18n="cta_reservar">Reservar</a>
      <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="mobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<div class="mobile-backdrop" id="mobileBackdrop"></div>
<div class="mobile-menu" id="mobileMenu">
  <div class="wrap">
    <nav class="mobile-nav">
      <a href="#cocina" data-i18n="nav_cocina">La cocina</a>
      <a href="#galeria" data-i18n="nav_galeria">Galería</a>
      <a href="#historia" data-i18n="nav_historia">Historia</a>
      <a href="#contacto" data-i18n="nav_contacto">Contacto</a>
    </nav>
    <div class="mlang-block" id="mlangBlock">
      <button class="mlang-toggle" id="mlangToggle" type="button" aria-expanded="false" aria-controls="mlangOptions">
        <i data-lucide="globe"></i>
        <span>Idioma</span>
        <span class="mlang-current" id="mlangCurrent">ES</span>
        <i data-lucide="chevron-right" class="mlang-chevron"></i>
      </button>
      <div class="mlang-options" id="mlangOptions">
        <div class="mlang-options-inner">
          <button class="lang-btn on" data-lang="es">ES</button>
          <button class="lang-btn" data-lang="en">EN</button>
          <button class="lang-btn" data-lang="fr">FR</button>
          <button class="lang-btn" data-lang="pt">PT</button>
        </div>
      </div>
    </div>
    <a href="public/login.php" class="btn btn-accent btn-lg mobile-reservar" style="width:100%" data-i18n="cta_reservar">Reservar</a>
  </div>
</div>

<main id="top">

<!-- HERO -->
<section class="hero">
  <div class="bg" data-note="foto: comedor / llagar en hora dorada"><img src="assets/img/hero.jpg" alt="" width="1798" height="1354" fetchpriority="high"></div>
  <div class="overlay"></div>
  <div class="wrap">
    <span class="hero-eyebrow" data-i18n="hero_eyebrow">El Corralín de Campanal · Nava, Asturias</span>
    <h1 data-i18n="hero_title">Conoce nuestro restaurante</h1>
    <p class="lede" data-i18n="hero_lede">Cocina asturiana de siempre en la villa de Nava. Elige día y hora — te esperamos.</p>
    <div class="hero-cta">
      <a href="login.php" class="btn btn-accent btn-lg"><i data-lucide="calendar-check"></i><span data-i18n="cta_reservar_mesa">Reservar mesa</span></a>
    </div>
    <div class="hero-meta">
      <div class="item"><i data-lucide="map-pin"></i><div><div class="k">Nava, Asturias</div><div class="s" data-i18n="meta_dir">Plaza Manuel Uría, 4</div></div></div>
      <div class="item"><i data-lucide="clock"></i><div><div class="k" data-i18n="meta_horario_k">Cocina 13:00–16:00</div><div class="s" data-i18n="meta_horario_s">y 20:30–23:30</div></div></div>
      <div class="item"><i data-lucide="phone"></i><div><div class="k">985 71 60 42</div><div class="s" data-i18n="meta_tel_s">Reservar por teléfono</div></div></div>
    </div>
  </div>
</section>

<!-- COCINA -->
<section class="sec" id="cocina">
  <div class="wrap">
    <div class="sec-head center">
      <span class="eyebrow" data-i18n="cocina_eyebrow">La cocina</span>
      <h2 data-i18n="cocina_title">Producto de la tierra, recetas de casa</h2>
      <p data-i18n="cocina_sub">Tres cosas hacemos como en ningún sitio. Lo demás, también rico.</p>
    </div>
    <div class="dishes">
      <article class="dish">
        <div class="ph" data-note="foto: escanciado de sidra"><img src="https://picsum.photos/seed/corralin-sidra/700/500" alt=""></div>
        <div class="body"><span class="tag" data-i18n="dish1_tag">Del llagar</span><h3 data-i18n="dish1_title">Sidra natural</h3><p data-i18n="dish1_desc">Escanciada al culín, de llagar de la comarca. La bebida de Nava, capital de la manzana.</p></div>
      </article>
      <article class="dish">
        <div class="ph" data-note="foto: cachopo al corte"><img src="https://picsum.photos/seed/corralin-cachopo/700/500" alt=""></div>
        <div class="body"><span class="tag" data-i18n="dish2_tag">La estrella</span><h3 data-i18n="dish2_title">Cachopo al corte</h3><p data-i18n="dish2_desc">Ternera asturiana rellena de jamón y queso Afuega'l Pitu. Para compartir, o casi.</p></div>
      </article>
      <article class="dish">
        <div class="ph" data-note="foto: fabada en pote"><img src="https://picsum.photos/seed/corralin-fabada/700/500" alt=""></div>
        <div class="body"><span class="tag" data-i18n="dish3_tag">De cuchara</span><h3 data-i18n="dish3_title">Fabada de la güela</h3><p data-i18n="dish3_desc">Faba de la Granja con su compango: morcilla, chorizo y lacón. A fuego lento, como toca.</p></div>
      </article>
    </div>
  </div>
</section>

<!-- GALERIA -->
<section class="sec" id="galeria" style="background:var(--crema-100)">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow" data-i18n="galeria_eyebrow">El local</span>
      <h2 data-i18n="galeria_title">Un corralín con solera</h2>
      <p data-i18n="galeria_sub">Piedra, madera y el aroma de la manzana. Así se come en El Corralín.</p>
    </div>
    <div class="gallery">
      <div class="ph g1" data-note="foto: comedor principal"><img src="https://picsum.photos/seed/corralin-g1/900/900" alt=""></div>
      <div class="ph g2" data-note="foto: barra de sidra"><img src="https://picsum.photos/seed/corralin-g2/500/500" alt=""></div>
      <div class="ph g3" data-note="foto: detalle mesa"><img src="https://picsum.photos/seed/corralin-g3/500/500" alt=""></div>
      <div class="ph g4" data-note="foto: terraza / patio"><img src="https://picsum.photos/seed/corralin-g4/900/500" alt=""></div>
    </div>
  </div>
</section>

<!-- HISTORIA -->
<section class="sec" id="historia">
  <div class="wrap">
    <div class="split">
      <div class="txt">
        <span class="eyebrow" data-i18n="historia_eyebrow">Sobre el Corralín</span>
        <h2 data-i18n="historia_title">Desde el Sueve hasta tu mesa</h2>
        <p data-i18n="historia_p1">El Corralín del Campanal abrió en el casco de Nava con una idea sencilla: cocina asturiana honrada, sin florituras, y buena sidra al lado.</p>
        <p class="muted" data-i18n="historia_p2">Trabajamos con productores de la comarca —faba, ternera, quesos de la sierra del Sueve— y escanciamos sidra de llagares que conocemos por el nombre. Lo tradicional, sin caricatura.</p>
        <div class="stats">
          <div><div class="n">1987</div><div class="l" data-i18n="stat1">Desde este año en Nava</div></div>
          <div><div class="n">12</div><div class="l" data-i18n="stat2">Llagares de la comarca</div></div>
          <div><div class="n">100%</div><div class="l" data-i18n="stat3">Producto asturiano</div></div>
        </div>
      </div>
      <div class="ph" data-note="foto: fachada / equipo del restaurante"><img src="https://picsum.photos/seed/corralin-fachada/800/900" alt=""></div>
    </div>
  </div>
</section>



<!-- UBICACION / HORARIO -->
<section class="sec" id="ubicacion">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow" data-i18n="ubi_eyebrow">Horario y ubicación</span>
      <h2 data-i18n="ubi_title">En el corazón de Nava</h2>
      <p data-i18n="ubi_sub">A un paso del Museo de la Sidra. Aparcamiento en la plaza.</p>
    </div>
    <div class="info-grid">
      <div class="hours-card">
        <h3 data-i18n="ubi_hours_title">Horario</h3>
        <div class="hrow closed"><span class="day" data-i18n="d_lun">Lunes</span><span class="time" data-i18n="cerrado">Cerrado</span></div>
        <div class="hrow"><span class="day" data-i18n="d_mar">Martes</span><span class="time">13:00–16:00 · 20:30–23:30</span></div>
        <div class="hrow"><span class="day" data-i18n="d_mie">Miércoles</span><span class="time">13:00–16:00 · 20:30–23:30</span></div>
        <div class="hrow"><span class="day" data-i18n="d_jue">Jueves</span><span class="time">13:00–16:00 · 20:30–23:30</span></div>
        <div class="hrow"><span class="day" data-i18n="d_vie">Viernes</span><span class="time">13:00–16:00 · 20:30–00:00</span></div>
        <div class="hrow"><span class="day" data-i18n="d_sab">Sábado</span><span class="time">13:00–16:30 · 20:30–00:00</span></div>
        <div class="hrow"><span class="day" data-i18n="d_dom">Domingo</span><span class="time">13:00–16:30</span></div>
        <div class="addr">
          <div class="line"><i data-lucide="map-pin"></i><span class="t">Plaza Manuel Uría, 4<br>33520 Nava, Asturias</span></div>
          <div class="line"><i data-lucide="phone"></i><span class="t">985 71 60 42</span></div>
        </div>
      </div>
      <div class="map"><div class="ph" data-note="mapa: ubicación en Nava (embed)"><img src="https://picsum.photos/seed/corralin-map/800/600" alt=""></div></div>
    </div>
  </div>
</section>

<!-- CONTACTO -->
<section class="sec contact" id="contacto">
  <div class="wrap">
    <div class="split">
      <div class="txt">
        <span class="eyebrow" data-i18n="cont_eyebrow">Reservas y contacto</span>
        <h2 data-i18n="cont_title">Reserva tu mesa</h2>
        <p data-i18n="cont_sub">Elige mesa, día y hora desde tu cuenta. Para grupos de más de 8 personas, llámanos y lo organizamos.</p>
        <div class="contact-lines">
          <div class="cl"><div class="ci"><i data-lucide="phone"></i></div><div><div class="ck">985 71 60 42</div><div class="cv" data-i18n="cont_tel">Martes a domingo, en horario de cocina</div></div></div>
          <div class="cl"><div class="ci"><i data-lucide="mail"></i></div><div><div class="ck"><a href="mailto:reservas@elcorralindelcampanal.com">reservas@elcorralindelcampanal.com</a></div><div class="cv" data-i18n="cont_mail">Te respondemos en el día</div></div></div>
          <div class="cl"><div class="ci"><i data-lucide="map-pin"></i></div><div><div class="ck">Plaza Manuel Uría, 4</div><div class="cv">33520 Nava, Asturias</div></div></div>
        </div>
      </div>
      <div class="form-card" style="display:flex;flex-direction:column;align-items:center;text-align:center;justify-content:center;gap:16px">
        <i data-lucide="calendar-check" style="width:40px;height:40px;color:var(--verde-700)"></i>
        <h3 style="font-family:var(--font-display);font-weight:600;font-size:22px;color:var(--verde-800)" data-i18n="cont_card_title">Elige tu mesa</h3>
        <p style="font-size:15px;line-height:1.6;color:var(--fg-muted)" data-i18n="cont_card_sub">Inicia sesión o crea tu cuenta gratis para reservar mesa, día y hora en el plano del salón.</p>
        <a href="public/login.php" class="btn btn-primary btn-lg" style="width:100%"><i data-lucide="calendar-check"></i><span data-i18n="f_enviar">Reservar mesa</span></a>
      </div>
    </div>
  </div>
</section>

</main>

<footer>
  <div class="wrap">
    <div class="cols">
      <div>
        <img src="assets/img/logo-horizontal-verde.png" alt="El Corralín de Campanal" class="logo-img">
        <p class="about" data-i18n="foot_about">Cocina asturiana tradicional y sidra de llagar en la villa de Nava, Principado de Asturias.</p>
      </div>
      <div>
        <h4 data-i18n="foot_visita">Visítanos</h4>
        <ul>
          <li><span style="color:rgba(245,239,224,.8)">Plaza Manuel Uría, 4</span></li>
          <li><span style="color:rgba(245,239,224,.8)">33520 Nava, Asturias</span></li>
          <li><a href="tel:+34985716042">985 71 60 42</a></li>
          <li><a href="mailto:reservas@elcorralindelcampanal.com">reservas@elcorralindelcampanal.com</a></li>
        </ul>
      </div>
      <div>
        <h4 data-i18n="foot_explora">Explora</h4>
        <ul>
          <li><a href="#cocina" data-i18n="nav_cocina">La cocina</a></li>
          <li><a href="#historia" data-i18n="nav_historia">Historia</a></li>
          <li><a href="public/login.php" data-i18n="cta_reservar">Reservar</a></li>
        </ul>
      </div>
    </div>
    <div class="bottom">
      <span>© 2026 El Corralín del Campanal · Nava, Asturias</span>
      <span><a href="#top" data-i18n="foot_top">Volver arriba</a></span>
    </div>
  </div>
</footer>

<script>
const I18N={
  en:{nav_cocina:"The kitchen",nav_galeria:"Gallery",nav_historia:"Our story",nav_contacto:"Contact",cta_reservar:"Book",cta_reservar_mesa:"Book a table",
    hero_eyebrow:"El Corralín del Campanal · Nava, Asturias",hero_title:"Book your table at El Corralín",hero_lede:"Time-honoured Asturian cooking in the village of Nava: cider from the press, sliced cachopo and grandma's fabada. Pick a table, day and time — we'll be waiting.",
    meta_dir:"Plaza Manuel Uría, 4",meta_horario_k:"Kitchen 1–4pm",meta_horario_s:"and 8:30–11:30pm",meta_tel_s:"Reservations by phone",
    cocina_eyebrow:"The kitchen",cocina_title:"Local produce, home recipes",cocina_sub:"Three things we do like nowhere else. The rest, also delicious.",
    dish1_tag:"From the press",dish1_title:"Natural cider",dish1_desc:"Poured 'al culín', from presses in the region. The drink of Nava, capital of the apple.",
    dish2_tag:"The star",dish2_title:"Sliced cachopo",dish2_desc:"Asturian beef stuffed with ham and Afuega'l Pitu cheese. To share — almost.",
    dish3_tag:"With a spoon",dish3_title:"Grandma's fabada",dish3_desc:"Granja beans with their 'compango': blood sausage, chorizo and pork. Slow-cooked, as it should be.",
    galeria_eyebrow:"The place",galeria_title:"A courtyard with character",galeria_sub:"Stone, wood and the scent of apple. That's how you eat at El Corralín.",
    historia_eyebrow:"About El Corralín",historia_title:"From the Sueve to your table",historia_p1:"El Corralín del Campanal opened in old Nava with a simple idea: honest Asturian cooking, no frills, and good cider alongside.",historia_p2:"We work with local producers — beans, beef, mountain cheeses from the Sueve — and pour cider from presses we know by name. Traditional, without the caricature.",
    stat1:"In Nava since this year",stat2:"Presses in the region",stat3:"Asturian produce",
    menu_dia_k:"Set lunch · Tuesday to Friday",menu_dia_v:"Starter, main, homemade dessert and cider or a drink",
    menu_entrantes:"To start",menu_principales:"Mains",
    mi1:"Asturian cheese board",mi1d:"Cabrales, Afuega'l Pitu and Gamonéu with spelt bread",mi2:"Compango croquettes",mi2d:"Half a dozen, creamy inside",mi3:"Chorizo in cider",mi3d:"Stewed just right",mi4:"Battered monkfish",mi4d:"Market monkfish, lightly fried",
    mi5:"Sliced cachopo",mi5d:"Beef, ham and cheese · for two",mi6:"Asturian fabada",mi6d:"With the full compango",mi7:"Hake in cider",mi7d:"With clams and potato",mi8:"Burnt rice pudding",mi8d:"House dessert",
    ubi_eyebrow:"Hours & location",ubi_title:"In the heart of Nava",ubi_sub:"A step from the Cider Museum. Parking in the square.",ubi_hours_title:"Opening hours",
    d_lun:"Monday",d_mar:"Tuesday",d_mie:"Wednesday",d_jue:"Thursday",d_vie:"Friday",d_sab:"Saturday",d_dom:"Sunday",cerrado:"Closed",
    cont_eyebrow:"Reservations & contact",cont_title:"Book your table",cont_sub:"Pick a table, day and time from your account. For groups of more than 8, call us and we'll arrange it.",cont_tel:"Tuesday to Sunday, during kitchen hours",cont_mail:"We reply the same day",
    cont_card_title:"Choose your table",cont_card_sub:"Log in or create a free account to book a table, day and time on the dining room floor plan.",f_enviar:"Book a table",
    foot_about:"Traditional Asturian cooking and press cider in the village of Nava, Principality of Asturias.",foot_visita:"Visit us",foot_explora:"Explore",foot_top:"Back to top"},
  fr:{nav_cocina:"La cuisine",nav_galeria:"Galerie",nav_historia:"Histoire",nav_contacto:"Contact",cta_reservar:"Réserver",cta_reservar_mesa:"Réserver une table",
    hero_eyebrow:"El Corralín del Campanal · Nava, Asturies",hero_title:"Réservez votre table à El Corralín",hero_lede:"Cuisine asturienne de toujours au village de Nava : cidre du pressoir, cachopo tranché et fabada de la grand-mère. Choisissez une table, un jour et une heure — on vous attend.",
    meta_dir:"Plaza Manuel Uría, 4",meta_horario_k:"Cuisine 13h–16h",meta_horario_s:"et 20h30–23h30",meta_tel_s:"Réservations par téléphone",
    cocina_eyebrow:"La cuisine",cocina_title:"Produits du terroir, recettes de maison",cocina_sub:"Trois choses qu'on fait comme nulle part ailleurs. Le reste, délicieux aussi.",
    dish1_tag:"Du pressoir",dish1_title:"Cidre naturel",dish1_desc:"Versé « al culín », des pressoirs de la région. La boisson de Nava, capitale de la pomme.",
    dish2_tag:"La vedette",dish2_title:"Cachopo tranché",dish2_desc:"Bœuf asturien farci de jambon et de fromage Afuega'l Pitu. À partager — ou presque.",
    dish3_tag:"À la cuillère",dish3_title:"Fabada de la grand-mère",dish3_desc:"Haricots de la Granja avec leur « compango » : boudin, chorizo et jambonneau. Mijotée, comme il se doit.",
    galeria_eyebrow:"Le lieu",galeria_title:"Une cour de caractère",galeria_sub:"Pierre, bois et parfum de pomme. C'est ainsi qu'on mange à El Corralín.",
    historia_eyebrow:"À propos d'El Corralín",historia_title:"Du Sueve à votre table",historia_p1:"El Corralín del Campanal a ouvert dans le vieux Nava avec une idée simple : une cuisine asturienne honnête, sans chichis, et du bon cidre à côté.",historia_p2:"Nous travaillons avec des producteurs de la région — haricots, bœuf, fromages de la sierra du Sueve — et servons du cidre de pressoirs que nous connaissons par leur nom. Traditionnel, sans caricature.",
    stat1:"À Nava depuis cette année",stat2:"Pressoirs de la région",stat3:"Produits asturiens",
    menu_dia_k:"Menu du jour · du mardi au vendredi",menu_dia_v:"Entrée, plat, dessert maison et cidre ou boisson",
    menu_entrantes:"Pour commencer",menu_principales:"Plats",
    mi1:"Plateau de fromages asturiens",mi1d:"Cabrales, Afuega'l Pitu et Gamonéu avec pain d'épeautre",mi2:"Croquettes de compango",mi2d:"Une demi-douzaine, crémeuses",mi3:"Chorizo au cidre",mi3d:"Mijoté à point",mi4:"Lotte panée",mi4d:"Lotte du marché, frite légère",
    mi5:"Cachopo tranché",mi5d:"Bœuf, jambon et fromage · pour deux",mi6:"Fabada asturienne",mi6d:"Avec tout son compango",mi7:"Merlu au cidre",mi7d:"Avec palourdes et pomme de terre",mi8:"Riz au lait caramélisé",mi8d:"Dessert maison",
    ubi_eyebrow:"Horaires & adresse",ubi_title:"Au cœur de Nava",ubi_sub:"À deux pas du Musée du Cidre. Parking sur la place.",ubi_hours_title:"Horaires",
    d_lun:"Lundi",d_mar:"Mardi",d_mie:"Mercredi",d_jue:"Jeudi",d_vie:"Vendredi",d_sab:"Samedi",d_dom:"Dimanche",cerrado:"Fermé",
    cont_eyebrow:"Réservations & contact",cont_title:"Réservez votre table",cont_sub:"Choisissez une table, un jour et une heure depuis votre compte. Pour les groupes de plus de 8, appelez-nous.",cont_tel:"Du mardi au dimanche, aux heures de cuisine",cont_mail:"Nous répondons le jour même",
    cont_card_title:"Choisissez votre table",cont_card_sub:"Connectez-vous ou créez un compte gratuit pour réserver une table, un jour et une heure sur le plan de la salle.",f_enviar:"Réserver une table",
    foot_about:"Cuisine asturienne traditionnelle et cidre de pressoir au village de Nava, Principauté des Asturies.",foot_visita:"Nous trouver",foot_explora:"Explorer",foot_top:"Retour en haut"},
  pt:{nav_cocina:"A cozinha",nav_galeria:"Galeria",nav_historia:"História",nav_contacto:"Contacto",cta_reservar:"Reservar",cta_reservar_mesa:"Reservar mesa",
    hero_eyebrow:"El Corralín del Campanal · Nava, Astúrias",hero_title:"Reserva a tua mesa no Corralín",hero_lede:"Cozinha asturiana de sempre na vila de Nava: sidra do lagar, cachopo às fatias e fabada da avó. Escolhe mesa, dia e hora — estamos à tua espera.",
    meta_dir:"Plaza Manuel Uría, 4",meta_horario_k:"Cozinha 13h–16h",meta_horario_s:"e 20h30–23h30",meta_tel_s:"Reservas por telefone",
    cocina_eyebrow:"A cozinha",cocina_title:"Produto da terra, receitas de casa",cocina_sub:"Três coisas fazemos como em nenhum outro sítio. O resto, também é bom.",
    dish1_tag:"Do lagar",dish1_title:"Sidra natural",dish1_desc:"Servida «al culín», de lagares da região. A bebida de Nava, capital da maçã.",
    dish2_tag:"A estrela",dish2_title:"Cachopo às fatias",dish2_desc:"Vitela asturiana recheada com fiambre e queijo Afuega'l Pitu. Para partilhar — quase.",
    dish3_tag:"De colher",dish3_title:"Fabada da avó",dish3_desc:"Feijão da Granja com o seu «compango»: morcela, chouriço e lacão. Cozinhada em lume brando, como deve ser.",
    galeria_eyebrow:"O espaço",galeria_title:"Um pátio com carácter",galeria_sub:"Pedra, madeira e o aroma da maçã. Assim se come no Corralín.",
    historia_eyebrow:"Sobre o Corralín",historia_title:"Do Sueve até à tua mesa",historia_p1:"O Corralín del Campanal abriu no centro histórico de Nava com uma ideia simples: cozinha asturiana honesta, sem floreados, e boa sidra ao lado.",historia_p2:"Trabalhamos com produtores da região — feijão, vitela, queijos da serra do Sueve — e servimos sidra de lagares que conhecemos pelo nome. Tradicional, sem caricatura.",
    stat1:"Em Nava desde este ano",stat2:"Lagares da região",stat3:"Produto asturiano",
    menu_dia_k:"Menu do dia · de terça a sexta",menu_dia_v:"Entrada, prato principal, sobremesa caseira e sidra ou bebida",
    menu_entrantes:"Para começar",menu_principales:"Pratos principais",
    mi1:"Tábua de queijos asturianos",mi1d:"Cabrales, Afuega'l Pitu e Gamonéu com pão de espelta",mi2:"Croquetes de compango",mi2d:"Meia dúzia, cremosos por dentro",mi3:"Chouriço à sidra",mi3d:"Guisado no ponto",mi4:"Tamboril panado",mi4d:"Tamboril da lota, frito leve",
    mi5:"Cachopo às fatias",mi5d:"Vitela, fiambre e queijo · para dois",mi6:"Fabada asturiana",mi6d:"Com o seu compango completo",mi7:"Pescada à sidra",mi7d:"Com amêijoas e batata",mi8:"Arroz-doce queimado",mi8d:"Sobremesa da casa",
    ubi_eyebrow:"Horário e localização",ubi_title:"No coração de Nava",ubi_sub:"A dois passos do Museu da Sidra. Estacionamento na praça.",ubi_hours_title:"Horário",
    d_lun:"Segunda",d_mar:"Terça",d_mie:"Quarta",d_jue:"Quinta",d_vie:"Sexta",d_sab:"Sábado",d_dom:"Domingo",cerrado:"Encerrado",
    cont_eyebrow:"Reservas e contacto",cont_title:"Reserva a tua mesa",cont_sub:"Escolhe mesa, dia e hora a partir da tua conta. Para grupos de mais de 8 pessoas, liga-nos e tratamos disso.",cont_tel:"De terça a domingo, no horário de cozinha",cont_mail:"Respondemos no próprio dia",
    cont_card_title:"Escolhe a tua mesa",cont_card_sub:"Inicia sessão ou cria a tua conta grátis para reservar mesa, dia e hora na planta da sala.",f_enviar:"Reservar mesa",
    foot_about:"Cozinha asturiana tradicional e sidra de lagar na vila de Nava, Principado das Astúrias.",foot_visita:"Visita-nos",foot_explora:"Explora",foot_top:"Voltar ao topo"}
};
document.querySelectorAll('[data-i18n]').forEach(el=>{el._orig=el.textContent;});
function setLang(lang){
  const dict=I18N[lang];
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const k=el.getAttribute('data-i18n');
    if(!dict){el.textContent=el._orig;}
    else if(dict[k]!=null){el.textContent=dict[k];}
  });
  document.documentElement.lang=lang;
  document.querySelectorAll('.lang-btn').forEach(b=>b.classList.toggle('on',b.dataset.lang===lang));
  const mc=document.getElementById('mlangCurrent');
  if(mc)mc.textContent=lang.toUpperCase();
}
document.querySelectorAll('.lang-btn').forEach(b=>b.addEventListener('click',()=>{
  setLang(b.dataset.lang);
  if(mlangBlock)mlangBlock.classList.remove('open');
}));

const header=document.getElementById('site-header');
function onScroll(){header.classList.toggle('solid',window.scrollY>60);}
onScroll();window.addEventListener('scroll',onScroll,{passive:true});

/* ---- mobile menu ---- */
const menuToggle=document.getElementById('menuToggle');
const mobileMenu=document.getElementById('mobileMenu');
const mobileBackdrop=document.getElementById('mobileBackdrop');
const mlangBlock=document.getElementById('mlangBlock');
const mlangToggle=document.getElementById('mlangToggle');

function closeMenu(){
  menuToggle.classList.remove('open');
  menuToggle.setAttribute('aria-expanded','false');
  mobileMenu.classList.remove('open');
  mobileBackdrop.classList.remove('open');
  mlangBlock.classList.remove('open');
  mlangToggle.setAttribute('aria-expanded','false');
  document.body.style.overflow='';
}
function openMenu(){
  menuToggle.classList.add('open');
  menuToggle.setAttribute('aria-expanded','true');
  mobileMenu.classList.add('open');
  mobileBackdrop.classList.add('open');
  document.body.style.overflow='hidden';
}
menuToggle.addEventListener('click',()=>{
  menuToggle.classList.contains('open')?closeMenu():openMenu();
});
mobileBackdrop.addEventListener('click',closeMenu);
mobileMenu.querySelectorAll('.mobile-nav a, .mobile-reservar').forEach(el=>el.addEventListener('click',closeMenu));
mlangToggle.addEventListener('click',()=>{
  const isOpen=mlangBlock.classList.toggle('open');
  mlangToggle.setAttribute('aria-expanded',String(isOpen));
});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
window.addEventListener('resize',()=>{if(window.innerWidth>960)closeMenu();});

document.addEventListener('DOMContentLoaded',()=>{if(window.lucide)lucide.createIcons();});
</script>
</body>
</html>
