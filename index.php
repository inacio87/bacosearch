<?php
/**
 * /index.php - Homepage Simplificada - BacoSearch Brasil
 * Foco: Acompanhantes no Brasil
 * Versão: Pivot Brasil 1.0
 * Data: 06/11/2025
 */

$page_name = 'home';

// INICIALIZAÇÃO
require_once __DIR__ . '/core/bootstrap.php';

// Headers de cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// DADOS DA PÁGINA
$language_code = $_SESSION['language'] ?? 'pt-br';
$city = $_SESSION['city'] ?? 'Salvador';

// Traduções mínimas
$translations = [
    'page_title' => 'Acompanhantes em Todo o Brasil | BacoSearch',
    'meta_description' => 'Encontre acompanhantes verificadas em sua cidade. O maior site de acompanhantes do Brasil.',
    'search_placeholder' => 'Digite sua cidade...',
    'search_button' => 'Buscar',
    'featured_title' => 'Em Destaque',
    'new_profiles' => 'Perfis Recentes',
    'verified_badge' => 'Verificado',
    'online_now' => 'Online Agora',
    'logo_alt' => 'BacoSearch - Acompanhantes Brasil'
];

// Buscar perfis em destaque (últimos 12 ativos)
$featured_ads = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.age,
            c.name AS city_name,
            s.name AS state_name,
            ph.image_path AS photo,
            p.phone,
            p.whatsapp,
            p.is_verified,
            p.is_online
        FROM profiles p
        LEFT JOIN cities c ON p.city_id = c.id
        LEFT JOIN states s ON c.state_id = s.id
        LEFT JOIN (
            SELECT profile_id, MIN(image_path) AS image_path
            FROM photos
            GROUP BY profile_id
        ) ph ON p.id = ph.profile_id
        WHERE p.is_active = 1
          AND p.status = 'approved'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $stmt->execute();
    $featured_ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar perfis: " . $e->getMessage());
}

$page_title = $translations['page_title'];
$meta_description = $translations['meta_description'];

// Head e Header
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>

<main class="home-container">
    <!-- HERO SECTION - Busca Principal -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Encontre Acompanhantes no Brasil</h1>
            <p class="subtitle">Milhares de anúncios verificados em todo o país</p>
            
            <form action="<?= SITE_URL; ?>/buscar.php" method="GET" class="search-form">
                <div class="search-box">
                    <i class="fas fa-map-marker-alt search-icon"></i>
                    <input 
                        type="text" 
                        name="cidade" 
                        id="city-search"
                        placeholder="<?= htmlspecialchars($translations['search_placeholder']); ?>" 
                        value="<?= htmlspecialchars($city); ?>"
                        autocomplete="off"
                        required
                    >
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <?= htmlspecialchars($translations['search_button']); ?>
                    </button>
                </div>
            </form>

            <!-- Sugestões Rápidas -->
            <div class="quick-links">
                <a href="<?= SITE_URL; ?>/buscar.php?cidade=São Paulo">São Paulo</a>
                <a href="<?= SITE_URL; ?>/buscar.php?cidade=Rio de Janeiro">Rio de Janeiro</a>
                <a href="<?= SITE_URL; ?>/buscar.php?cidade=Belo Horizonte">Belo Horizonte</a>
                <a href="<?= SITE_URL; ?>/buscar.php?cidade=Salvador">Salvador</a>
                <a href="<?= SITE_URL; ?>/buscar.php?cidade=Brasília">Brasília</a>
            </div>
        </div>
    </section>

    <!-- PERFIS EM DESTAQUE -->
    <section class="featured-section">
        <div class="section-header">
            <h2><i class="fas fa-star"></i> <?= htmlspecialchars($translations['featured_title']); ?></h2>
        </div>

        <div class="profiles-grid">
            <?php foreach ($featured_ads as $ad): ?>
                <a href="<?= SITE_URL; ?>/perfil.php?id=<?= $ad['id']; ?>" class="profile-card">
                    <div class="profile-image" style="background-image: url('<?= SITE_URL; ?>/<?= htmlspecialchars($ad['photo'] ?? 'assets/images/no-photo.jpg'); ?>');">
                        <?php if ($ad['is_verified']): ?>
                            <span class="badge badge-verified">
                                <i class="fas fa-check-circle"></i> Verificado
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($ad['is_online']): ?>
                            <span class="badge badge-online">
                                <i class="fas fa-circle"></i> Online
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-info">
                        <h3><?= htmlspecialchars($ad['name']); ?></h3>
                        <p class="age-location">
                            <?= htmlspecialchars($ad['age']); ?> anos • <?= htmlspecialchars($ad['city_name']); ?>, <?= htmlspecialchars($ad['state_name']); ?>
                        </p>
                        
                        <div class="profile-actions">
                            <?php if ($ad['whatsapp']): ?>
                                <button class="btn-whatsapp" onclick="event.preventDefault(); window.open('https://wa.me/55<?= preg_replace('/\D/', '', $ad['whatsapp']); ?>', '_blank');">
                                    <i class="fab fa-whatsapp"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($ad['phone']): ?>
                                <button class="btn-phone" onclick="event.preventDefault(); window.location.href='tel:<?= htmlspecialchars($ad['phone']); ?>';">
                                    <i class="fas fa-phone"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($featured_ads)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Nenhum perfil encontrado no momento.</p>
                <a href="<?= SITE_URL; ?>/register.php" class="btn-primary">Seja o primeiro a anunciar!</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<style>
/* ESTILOS SIMPLIFICADOS DA HOMEPAGE */
.home-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* HERO SECTION */
.hero-section {
    background: linear-gradient(135deg, #8e2157 0%, #c42a72 100%);
    color: white;
    padding: 80px 40px;
    border-radius: 16px;
    margin-bottom: 40px;
    text-align: center;
}

.hero-content h1 {
    font-size: 3rem;
    margin-bottom: 16px;
    font-weight: 700;
}

.hero-content .subtitle {
    font-size: 1.25rem;
    margin-bottom: 40px;
    opacity: 0.95;
}

/* BUSCA */
.search-form {
    max-width: 700px;
    margin: 0 auto 30px;
}

.search-box {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 50px;
    padding: 8px 8px 8px 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.search-icon {
    color: #8e2157;
    font-size: 1.25rem;
    margin-right: 12px;
}

.search-box input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1.125rem;
    padding: 12px;
    color: #333;
}

.search-btn {
    background: #8e2157;
    color: white;
    border: none;
    padding: 14px 32px;
    border-radius: 50px;
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    background: #6d1942;
    transform: scale(1.05);
}

/* QUICK LINKS */
.quick-links {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
}

.quick-links a {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.quick-links a:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

/* SECTION HEADER */
.section-header {
    margin-bottom: 30px;
}

.section-header h2 {
    font-size: 2rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-header h2 i {
    color: #f7b731;
}

/* GRID DE PERFIS */
.profiles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

.profile-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
}

.profile-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.profile-image {
    height: 350px;
    background-size: cover;
    background-position: center;
    position: relative;
}

.badge {
    position: absolute;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.badge-verified {
    top: 12px;
    right: 12px;
    background: #27ae60;
    color: white;
}

.badge-online {
    top: 50px;
    right: 12px;
    background: #3498db;
    color: white;
}

.badge-online i {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.profile-info {
    padding: 16px;
}

.profile-info h3 {
    font-size: 1.25rem;
    margin-bottom: 8px;
    color: #333;
}

.age-location {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 12px;
}

.profile-actions {
    display: flex;
    gap: 8px;
}

.btn-whatsapp,
.btn-phone {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.125rem;
    transition: all 0.3s;
}

.btn-whatsapp {
    background: #25d366;
    color: white;
}

.btn-whatsapp:hover {
    background: #1fb855;
}

.btn-phone {
    background: #3498db;
    color: white;
}

.btn-phone:hover {
    background: #2980b9;
}

/* NO RESULTS */
.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.no-results i {
    font-size: 4rem;
    margin-bottom: 20px;
}

.no-results p {
    font-size: 1.25rem;
    margin-bottom: 24px;
}

.btn-primary {
    background: #8e2157;
    color: white;
    padding: 14px 32px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #6d1942;
    transform: scale(1.05);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2rem;
    }
    
    .hero-content .subtitle {
        font-size: 1rem;
    }
    
    .search-box {
        flex-direction: column;
        padding: 16px;
        border-radius: 12px;
    }
    
    .search-box input {
        width: 100%;
        margin-bottom: 12px;
    }
    
    .search-btn {
        width: 100%;
        justify-content: center;
        border-radius: 8px;
    }
    
    .profiles-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .profile-image {
        height: 250px;
    }
}

@media (max-width: 480px) {
    .profiles-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once TEMPLATE_PATH . 'footer.php'; ?>
