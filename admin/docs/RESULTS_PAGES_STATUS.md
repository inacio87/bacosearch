# Páginas de Resultados - Padrão BacoSearch

## Status das Páginas de Listagem

### ✅ Funcionais (padrão providers.js)
- `results_providers.php` - **PADRÃO DE REFERÊNCIA**
  - CSS: `search-providers.css`
  - JS: `providers.js`  
  - API: `/api/providers.php`
  - Navegação hierárquica (Planet → Country → Region → City)
  - Filtros dinâmicos
  - Paginação
  - Breadcrumb interativo

### 🔄 Aguardando Adaptação (usar padrão providers)

#### `results_clubs.php`
- API Backend: `/api/clubs.php`
- Frontend JS: `/assets/js/clubs.js` (a criar, baseado em providers.js)
- Filtros: category (liberal, sensual_bar, striptease, etc.), price_max, distance
- Data source: tabela `clubs` (após migração)

#### `results_business.php` 
- API Backend: `/api/businesses.php`
- Frontend JS: `/assets/js/businesses.js` (a criar, baseado em providers.js)
- Filtros: category, price_max, distance
- Data source: tabela `companies`

#### `results_services.php`
- API Backend: `/api/services.php`
- Frontend JS: `/assets/js/services.js` (a criar, baseado em providers.js)
- Filtros: category, price_min, price_max, distance
- Data source: tabela `services_listings`

#### `results_streets.php`
- API Backend: `/api/streets.php`
- Frontend JS: `/assets/js/streets.js` (a criar, baseado em providers.js)
- Filtros: place_type (street/bar), city, tags
- Data source: tabela `street_posts`
- Estilo: Forum/feed de contribuições

## Padrão de Implementação

### 1. PHP (Backend Page)
```php
$page_specific_styles = [SITE_URL . '/assets/css/search-providers.css'];
$page_specific_scripts = [['src' => SITE_URL . '/assets/js/{vertical}.js', 'attrs' => ['defer' => true]]];

$language_code = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$initial_location = [/* planet, country_code, country_name, region, city */];
$initial_filters = [/* category, price_max, distance, etc. */];
$initial_data = [ '{vertical}' => [], 'level' => 'global' ];
$adData = [ 'global' => [] ];

window.appConfig = {
    site_url, translations, locationData, adData,
    initial{Vertical}Data, initialFilters
};
```

### 2. API Endpoint (`/api/{vertical}.php`)
Retorna JSON:
```json
{
  "success": true,
  "data": {
    "{vertical}": [ /* array de registros */ ],
    "level": "city|region|country|global",
    "total": 42
  }
}
```

Filtros aplicados:
- Geolocalização (city → region → country → global fallback)
- Status: `status='active' AND is_active=1`
- Filtros específicos da categoria

### 3. Frontend JS (`/assets/js/{vertical}.js`)
Baseado em `providers.js`:
- Fetch de `/api/{vertical}.php`
- Renderização de cards
- Navegação hierárquica (breadcrumb)
- Filtros dinâmicos
- Paginação
- Modais (planeta, localização, filtros avançados)

## Ações Necessárias

1. **Executar migração**: `/admin/run_migration_add_verticals.php`
2. **Criar JS files**: Copiar `providers.js` e adaptar endpoint/data keys
3. **Testar APIs**: Verificar `/api/{vertical}.php` retorna dados corretos
4. **Popular traduções**: Audit + seed missing i18n keys
5. **Validar fluxo**: Registro → Aprovação Admin → Listagem Pública

## Notas
- Todas as páginas results_* removem referência a `additional_functions.php` (não existe)
- CSS reutiliza `search-providers.css` (layout grid responsivo já pronto)
- Navegação planetária e breadcrumb hierárquico são padrão
- Apenas registros `status='active' AND is_active=1` aparecem
- Admin approval é o único ponto de ativação
