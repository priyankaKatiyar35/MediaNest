<?php

// Real counts from the database
$__conn = @mysqli_connect('localhost', 'root', '', 's&p');
function _stat_count($conn, $sql) {
    if (!$conn) return 0;
    $r = @mysqli_query($conn, $sql);
    return $r ? (int)(mysqli_fetch_row($r)[0] ?? 0) : 0;
}

// Files managed = total videos + total photos + total documents
$n_videos = _stat_count($__conn, "SELECT COUNT(*) FROM video");
$n_photos = _stat_count($__conn, "SELECT COUNT(*) FROM tbl_gallery WHERE status='process'");
$n_files  = _stat_count($__conn, "SELECT COUNT(*) FROM files");
$total_managed = $n_videos + $n_photos + $n_files;

// Active users = signed in within the last 30 days; falls back to total users if last_login is null
$n_active = _stat_count($__conn,
    "SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL AND last_login > (NOW() - INTERVAL 30 DAY)");
if ($n_active === 0) $n_active = _stat_count($__conn, "SELECT COUNT(*) FROM users");

// Sections (videos + photos + documents + admin) → label "sections served"
$n_sections = 4;

if ($__conn) mysqli_close($__conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MediaNest — Your Media Hub</title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

  <!-- Lottie -->
  <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

  <!-- GSAP + ScrollTrigger -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

  <link rel="stylesheet" href="style/style.css" />
</head>

<body>

  <!-- Scroll progress -->
  <div id="scroll-progress"></div>
 
  <!-- Nav -->
  <nav class="mn-nav">
    <div class="mn-nav-inner">
      <a href="#" class="mn-logo">
        <span class="mn-logo-mark"><i class="fas fa-cube"></i></span>
        <span class="mn-logo-text">Media<span>Nest</span></span>
      </a>

      <div class="mn-nav-actions">
      <?php include __DIR__ . '../auth/notif_bell.php'; ?>  
      <button id="search-toggle" class="mn-icon-btn" title="Search (Ctrl + K)">
          <i class="fas fa-search"></i>
        </button>
        <button id="theme-toggle" class="mn-icon-btn" title="Toggle theme">
          <i class="fas fa-moon"></i>
        </button>
<a href="admin/login.php" id="admin-btn" class="mn-btn-primary">
  <i class="fas fa-user-shield"></i>
  <span>Admin</span>
</a>

      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero">
    <div class="hero-bg">
      <div class="hero-orb hero-orb-1"></div>
      <div class="hero-orb hero-orb-2"></div>
      <div class="hero-grid"></div>
    </div>

    <div class="hero-inner">
      <div class="hero-badge animate__animated animate__fadeInDown">
        <span class="pulse-dot"></span>
        AI-powered media management
      </div>

      <div class="video-thumbnail">
        <lottie-player
          src="https://assets2.lottiefiles.com/packages/lf20_1pxqjqps.json"
          background="transparent" speed="1" loop autoplay
          style="height: 180px; width: 180px; margin: 0 auto;">
        </lottie-player>
      </div>

      <h1 class="hero-title">
        Welcome to <span class="gradient-text">MediaNest</span>
      </h1>
      <p class="hero-sub">
        Store, organize, and enhance your media with AI-powered features built for teams that ship.
      </p>

      <div class="hero-cta">
        <a href="#features" class="btn-cta ripple">
          <i class="fas fa-rocket"></i> Get Started
        </a>
        <a href="#stats" class="btn-ghost">
          See it in action <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
    
  </section>

  <!-- Stats — single horizontal row -->
  <section id="stats" class="stats-section">
    <div class="stats-row">
      <div class="stat" data-target="<?php echo (int)$total_managed; ?>" data-suffix="">
        <div class="stat-num">0</div>
        <div class="stat-label">Files managed</div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat" data-target="<?php echo (int)$n_videos; ?>" data-suffix="">
        <div class="stat-num">0</div>
        <div class="stat-label">Videos</div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat" data-target="<?php echo (int)$n_active; ?>" data-suffix="">
        <div class="stat-num">0</div>
        <div class="stat-label">Active users</div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat" data-target="<?php echo (int)$n_sections; ?>" data-suffix="">
        <div class="stat-num">0</div>
        <div class="stat-label">Sections</div>
      </div>
    </div>
  </section>

  <!-- Features — single horizontal row -->
  <section id="features" class="features-wrap">
    <div class="section-head">
      <h2>Everything you need, <span class="gradient-text">in one nest</span></h2>
      <p>Three powerful modules. One unified workspace.</p>
    </div>

    <div class="feature-row">

      <div class="feature-card" data-color="cyan">
        <div class="card-glow"></div>
        <div class="card-tag">01</div>
        <div class="thumb video-thumb">
          <i class="fas fa-play-circle"></i>
        </div>
        <h3>Video Gallery</h3>
        <p>Manage your videos with easy upload and smart categorization.</p>
        <a href="Videos/index.php" class="btn ripple">
          Explore <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="feature-card" data-color="pink">
        <div class="card-glow"></div>
        <div class="card-tag">02</div>
        <div class="thumb photo-thumb">
          <i class="fas fa-camera"></i>
        </div>
        <h3>Photo Gallery</h3>
        <p>Store photos with AI-powered tagging and easy search.</p>
        <a href="Photo/index.php" class="btn ripple">
          Explore <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="feature-card" data-color="emerald">
        <div class="card-glow"></div>
        <div class="card-tag">03</div>
        <div class="thumb doc-thumb">
          <i class="fas fa-folder-open"></i>
        </div>
        <h3>Document Management</h3>
        <p>Effortlessly manage documents with intelligent search.</p>
        <a href="Documents/index.php" class="btn ripple">
          Explore <i class="fas fa-arrow-right"></i>
        </a>
      </div>

    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="footer-inner">
      <div class="footer-brand">
        <span class="mn-logo-mark"><i class="fas fa-cube"></i></span>
        <span>MediaNest</span>
      </div>
      <!-- <div class="footer-links">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">Contact</a>
      </div> -->
      <p>&copy; 2025 MediaNest. All Rights Reserved.</p>
    </div>
  </footer>

  <!-- Search palette -->
  <div id="search-overlay" class="overlay" hidden>
    <div class="search-panel" role="dialog" aria-modal="true">
      <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input id="search-input" type="text" placeholder="Search modules…" autocomplete="off"/>
        <span class="kbd">ESC</span>
      </div>
      <ul id="search-results" class="search-results"></ul>
    </div>
  </div>

  <!-- Toast -->
  <div id="toast" class="toast" hidden></div>

  <script src="js/script.js" defer></script>
</body>
</html>