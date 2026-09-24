<?php
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Conexión a la base de datos
require_once 'conexion.php';

$sesionActiva  = isset($_SESSION['id_usuario']);
$idRol         = (int)($_SESSION['id_rol'] ?? 1);
$nombreRol     = strtoupper(trim($_SESSION['nombre_rol'] ?? ''));
$esAdmin       = ($idRol === 2 || strpos($nombreRol, 'ADMIN') !== false);
$nombreCliente = htmlspecialchars($_SESSION['nombre'] ?? '');

// Consulta de servicios activos en la base de datos
try {
    $stmt = $pdo->prepare("SELECT id_servicio, nombre, descripcion, categoria, tipo_servicio, precio_actual, duracion_minutos 
                           FROM servicio 
                           WHERE activo = 1 
                           ORDER BY id_servicio ASC");
    $stmt->execute();
    $servicios = $stmt->fetchAll();
} catch (PDOException $e) {
    $servicios = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>STAY_beauty — Salón de estética</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Parisienne&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css?v=11">
</head>
<body>

<header class="nav">
  <div class="container nav__inner">
    <a href="index.php" class="nav__brand">
      <span class="nav__brand-mark">SB</span>
      <span class="nav__brand-text">STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></span>
    </a>
    
    <nav class="nav__links" id="nav-links" aria-label="Navegación principal">
      <a href="#catalogo">Servicios</a>
      <a href="#nosotros">Nosotros</a>
      <?php if ($sesionActiva): ?>
          <a href="<?php echo $esAdmin ? 'dashboardAdmin.php' : 'miPanel.php'; ?>" class="btn btn-sm">
          <?php echo $esAdmin ? 'Panel Admin' : 'Panel de ' . $nombreCliente; ?>
        </a>
      <?php endif; ?>
    </nav>

    <?php if ($sesionActiva): ?>
      <a href="login.php?logout=1" class="btn btn-sm nav__cta">Cerrar sesión</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-sm nav__cta">Iniciar sesión</a>
    <?php endif; ?>

    <button class="nav__toggle" id="nav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- ===== hero ===== -->
<section class="hero">
  <div class="container hero__inner">
    <div class="hero__text">
      <p class="eyebrow">Salón de estética</p>
      <h1>STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></h1>
      <p class="hero__lead">Tratamientos faciales y corporales pensados para tu rutina. Reserva tu cita en 3 pasos.</p>
      <div class="hero__cta">
        <a href="reserva.php" class="btn">Reservar cita</a>
        <a href="#catalogo" class="btn btn-outline">Ver servicios</a>
      </div>
    </div>
    <div class="hero__media" id="hero-carousel">
      <div class="hero__slide is-active">
        <img src="assets/images/hero-1.jpg" alt="Tratamiento facial" onerror="this.onerror=null; this.src='https://picsum.photos/seed/hero1/600/600';">
        <span class="hero__slide-caption">Tratamientos Faciales</span>
      </div>
      <div class="hero__slide">
        <img src="assets/images/hero-2.jpg" alt="Masaje corporal" onerror="this.onerror=null; this.src='https://picsum.photos/seed/hero2/600/600';">
        <span class="hero__slide-caption">Bienestar Corporal</span>
      </div>
      <div class="hero__slide">
        <img src="assets/images/hero-3.jpg" alt="Cuidado capilar" onerror="this.onerror=null; this.src='https://picsum.photos/seed/hero3/600/600';">
        <span class="hero__slide-caption">Cuidado Capilar</span>
      </div>
      <div class="hero__dots">
        <button type="button" class="hero__dot is-active" aria-label="Ver diapositiva 1"></button>
        <button type="button" class="hero__dot" aria-label="Ver diapositiva 2"></button>
        <button type="button" class="hero__dot" aria-label="Ver diapositiva 3"></button>
      </div>
    </div>
  </div>
</section>

<!-- ===== catálogo de servicios ===== -->
<section class="section container" id="catalogo">
  <div class="section__header">
    <p class="eyebrow">Nuestros tratamientos</p>
    <h2>Servicios diseñados para tu cuidado</h2>
  </div>

  <div class="catalog-filters">
    <div class="tabs">
      <button type="button" class="tabs__btn is-active" data-filter="todos">Todos</button>
      <button type="button" class="tabs__btn" data-filter="facial">Facial</button>
      <button type="button" class="tabs__btn" data-filter="corporal">Corporal</button>
      <button type="button" class="tabs__btn" data-filter="capilar">Capilar</button>
    </div>
  </div>

  <div class="services-grid" id="catalogo-grid">
    <?php if (!empty($servicios)): ?>
      <?php foreach ($servicios as $srv): ?>
        <?php 
          $precioFormateado = '$' . number_format($srv['precio_actual'], 0, ',', '.');
          $imagen = "assets/images/servicio-{$srv['id_servicio']}.jpg";
        ?>
        <article class="service-card" data-categoria="<?php echo htmlspecialchars(strtolower($srv['categoria'])); ?>">
          <div class="service-card__media">
            <img src="<?php echo $imagen; ?>" 
                 onerror="this.onerror=null; this.src='https://picsum.photos/seed/<?php echo $srv['id_servicio']; ?>/480/360';" 
                 alt="<?php echo htmlspecialchars($srv['nombre']); ?>">
            <span class="service-card__categoria">
              <?php echo htmlspecialchars(ucfirst(strtolower($srv['categoria']))); ?>
            </span>
          </div>
          <div class="service-card__body">
            <h3><?php echo htmlspecialchars($srv['nombre']); ?></h3>
            <p class="service-card__desc"><?php echo htmlspecialchars($srv['descripcion']); ?></p>
            <p class="service-card__meta"><?php echo (int)$srv['duracion_minutos']; ?> min &middot; <?php echo htmlspecialchars($srv['tipo_servicio']); ?></p>
            <div class="service-card__footer">
              <span class="service-card__price"><?php echo $precioFormateado; ?></span>
              <a href="reserva.php?servicio=<?php echo $srv['id_servicio']; ?>" class="btn btn-outline btn-sm">Reservar</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-state">No hay servicios disponibles en este momento.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ===== nosotros ===== -->
<section id="nosotros" class="section about">
  <div class="container about__inner">
    <div>
      <p class="eyebrow">Sobre nosotros</p>
      <h2>Experiencia y tranquilidad en cada sesión</h2>
      <p>Nos enfocamos en brindar atención personalizada con productos de alta calidad en un ambiente diseñado para tu descanso.</p>
    </div>
    <ul class="about__list">
      <li><span class="eyebrow">Facial</span>Limpieza facial profunda, peeling químico y paquete VIP de renovación.</li>
      <li><span class="eyebrow">Corporal</span>Masaje reductivo localizado y masaje relajante con piedras volcánicas.</li>
      <li><span class="eyebrow">Capilar</span>Cortes, tinte, balayage y peinados para toda ocasión.</li>
      <li><span class="eyebrow">Reserva</span>Agenda en 3 pasos, confirmación inmediata por correo.</li>
    </ul>
  </div>
</section>

<footer class="footer">
  <div class="container footer__inner">
    <div class="footer__brand">
      <p class="footer__brand-text">STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></p>
      <p class="footer__tag">Servicios faciales y corporales, con reserva en 3 pasos.</p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Contacto</p>
      <p><a href="tel:+571234567890">+57 123 456 7890</a></p>
      <p><a href="mailto:hola@staybeauty.com">hola@staybeauty.com</a></p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Horario</p>
      <p>Lunes a sábado</p>
      <p>9:00 a.m. - 7:00 p.m.</p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Acceso</p>
      <p>
        <?php if ($sesionActiva): ?>
          <a href="login.php?logout=1" class="link-btn" style="color:inherit;">Cerrar sesión</a>
        <?php else: ?>
          <a href="login.php" class="link-btn" style="color:inherit;">Iniciar sesión</a>
        <?php endif; ?>
      </p>
    </div>
  </div>
  <p class="footer__legal">&copy; 2026 STAY_BEAUTY &middot; Plataforma de gestión de citas.</p>
</footer>

<div class="modal-overlay" id="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modal__head">
      <h3 id="modal-titulo">Atención</h3>
      <button type="button" class="modal__close" aria-label="Cerrar modal">&times;</button>
    </div>
    <div id="modal-contenido"></div>
  </div>
</div>

<script src="script.js?v=11"></script>
</body>
</html>