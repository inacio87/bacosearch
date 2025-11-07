# Relatório de Auditoria de Traduções - BacoSearch
**Data**: 2025-01-XX  
**Objetivo**: Identificar chaves de tradução faltantes ou com contexto incorreto

---

## 1. PROBLEMA PRINCIPAL: `header_favorites`

### Localização
- **Arquivo**: `templates/header.php` linha 55
- **Código**: `getTranslation('header_favorites', $languageCode, 'header') ?? 'Favoritos'`
- **Contexto esperado**: `header`
- **Status**: ⚠️ **FALTANDO NO BANCO DE DADOS**

### Sintoma
A interface exibe "header_favorites" como texto bruto em vez da tradução correta.

### Causa Raiz
A chave `header_favorites` está sendo buscada do banco de dados com contexto `header`, mas:
1. Não existe registro na tabela `translations` para essa chave OU
2. O registro existe mas com contexto diferente

### Solução
Adicionar registro no banco:
```sql
INSERT INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)
VALUES 
  ('pt-br', 'header', 'header_favorites', 'Favoritos', NOW(), NOW()),
  ('en-us', 'header', 'header_favorites', 'Favorites', NOW(), NOW()),
  ('es', 'header', 'header_favorites', 'Favoritos', NOW(), NOW());
```

---

## 2. CHAVES DO HEADER USADAS

### Chaves carregadas via `loadHeaderTranslations()` (context='header')

| Chave | Arquivo | Linha | Fallback | Status |
|-------|---------|-------|----------|--------|
| `header_favorites` | `templates/header.php` | 55 | 'Favoritos' | ⚠️ VERIFICAR |
| `header_dashboard` | `templates/header.php` | 25, 64 | 'header_dashboard' | ⚠️ VERIFICAR |
| `header_menu` | `templates/header.php` | 26, 128, 129, 133 | 'header_menu' | ⚠️ VERIFICAR |
| `header_login` | `templates/header.php` | 27, 91 | 'header_login' | ⚠️ VERIFICAR |
| `header_logout` | `templates/header.php` | 28, 87 | 'header_logout' | ⚠️ VERIFICAR |
| `header_ads` | `templates/header.php` | 29, 69, 70 | 'header_ads' | ⚠️ VERIFICAR |
| `logo_alt` | `templates/header.php` | 30, 79 | 'logo_alt' | ⚠️ VERIFICAR |
| `about_us` | `templates/header.php` | 139 | 'about_us' | ⚠️ VERIFICAR |
| `terms_of_service` | `templates/header.php` | 142 | 'terms_of_service' | ⚠️ VERIFICAR |
| `privacy_policy` | `templates/header.php` | 145 | 'privacy_policy' | ⚠️ VERIFICAR |
| `cookie_policy` | `templates/header.php` | 148 | 'cookie_policy' | ⚠️ VERIFICAR |
| `header_licenses` | `templates/header.php` | 151 | 'header_licenses' | ⚠️ VERIFICAR |
| `contact_us` | `templates/header.php` | 154 | 'contact_us' | ⚠️ VERIFICAR |

---

## 3. CHAVES DE UI_MESSAGES

| Chave | Arquivo | Linha | Contexto | Fallback | Status |
|-------|---------|-------|----------|----------|--------|
| `detecting_location` | `templates/header.php` | 39 | `ui_messages` | 'detecting_location' | ⚠️ VERIFICAR |
| `use_precise_location` | `templates/header.php` | 48 | `ui_messages` | 'use_precise_location' | ⚠️ VERIFICAR |
| `unknown_city_text` | `templates/head.php` | 162 | `ui_messages` | - | ⚠️ VERIFICAR |

---

## 4. CHAVES DAS PÁGINAS DE RESULTADOS

### Contexto: `results_clubs`, `results_business`, `results_services`, `results_streets`

| Chave | Contexto | Arquivos | Status |
|-------|----------|----------|--------|
| `results_clubs_title` | `results_clubs` | `pages/results_clubs.php:54` | ⚠️ VERIFICAR |
| `results_clubs_meta_description` | `results_clubs` | `pages/results_clubs.php:55` | ⚠️ VERIFICAR |
| `results_business_title` | `results_business` | `pages/results_business.php:54` | ⚠️ VERIFICAR |
| `results_business_meta_description` | `results_business` | `pages/results_business.php:55` | ⚠️ VERIFICAR |
| `results_services_title` | `results_services` | `pages/results_services.php:54` | ⚠️ VERIFICAR |
| `results_services_meta_description` | `results_services` | `pages/results_services.php:55` | ⚠️ VERIFICAR |
| `results_streets_title` | `results_streets` | `pages/results_streets.php:54` | ⚠️ VERIFICAR |
| `results_streets_meta_description` | `results_streets` | `pages/results_streets.php:55` | ⚠️ VERIFICAR |
| `no_profiles_found` | `results_*` | Todas páginas results | ⚠️ VERIFICAR |
| `ad_level_city` | `results_*` | Todas páginas results:109 | ⚠️ VERIFICAR |
| `ad_level_region` | `results_*` | Todas páginas results:112 | ⚠️ VERIFICAR |
| `ad_level_country` | `results_*` | Todas páginas results:115 | ⚠️ VERIFICAR |
| `ad_level_global` | `results_*` | Todas páginas results:118 | ⚠️ VERIFICAR |
| `filter_category_liberal` | `results_*` | Todas páginas results:125 | ⚠️ VERIFICAR |
| `filter_category_sensual_bar` | `results_*` | Todas páginas results:126 | ⚠️ VERIFICAR |
| `filter_category_striptease` | `results_*` | Todas páginas results:127 | ⚠️ VERIFICAR |
| `filter_category_erotic_discoteca` | `results_*` | Todas páginas results:128 | ⚠️ VERIFICAR |
| `filter_category_sensual_spa` | `results_*` | Todas páginas results:129 | ⚠️ VERIFICAR |
| `filter_category_events` | `results_*` | Todas páginas results:130 | ⚠️ VERIFICAR |
| `filter_price` | `results_*` | Todas páginas results:134, 194 | ⚠️ VERIFICAR |
| `filter_advanced` | `results_*` | Todas páginas results:139 | ⚠️ VERIFICAR |
| `filter_distance` | `results_*` | Todas páginas results:142, 198 | ⚠️ VERIFICAR |
| `modal_select_planet` | `results_*` | Todas páginas results:157 | ⚠️ VERIFICAR |
| `planet_earth` | `results_*` | Todas páginas results:164 | ⚠️ VERIFICAR |
| `planet_earth_desc` | `results_*` | Todas páginas results:165 | ⚠️ VERIFICAR |
| `planet_mars` | `results_*` | Todas páginas results:169 | ⚠️ VERIFICAR |
| `planet_mars_desc` | `results_*` | Todas páginas results:170 | ⚠️ VERIFICAR |
| `filter_advanced_title` | `results_*` | Todas páginas results:188 | ⚠️ VERIFICAR |
| `filter_apply` | `results_*` | Todas páginas results:204 | ⚠️ VERIFICAR |

---

## 5. CHAVES ESPECÍFICAS DE `results_providers.php`

| Chave | Contexto | Linha | Status |
|-------|----------|-------|--------|
| `results_providers_title` | `results_providers` | 92 | ⚠️ VERIFICAR |
| `results_providers_meta_description` | `results_providers` | 94 | ⚠️ VERIFICAR |
| `filter_services_title` | `results_providers` | 198 | ⚠️ VERIFICAR |

---

## 6. CHAVES DE BREADCRUMB

| Chave | Contexto | Arquivo | Linha | Status |
|-------|----------|---------|-------|--------|
| `breadcrumb.earth` | `breadcrumb` | `pages/results_providers.php` | 168, 224 | ⚠️ VERIFICAR |
| `breadcrumb.more` | `breadcrumb` | `pages/results_providers.php` | 225 | ⚠️ VERIFICAR |

---

## 7. CHAVES DO FOOTER

### Contexto: `footer`

| Chave | Status |
|-------|--------|
| `footer_providers` | ⚠️ VERIFICAR |
| `footer_companies` | ⚠️ VERIFICAR |
| `footer_services` | ⚠️ VERIFICAR |
| `footer_clubs` | ⚠️ VERIFICAR |
| `footer_streets` | ⚠️ VERIFICAR |

---

## 8. CHAVES DE AGE GATE

### Contexto: `age_gate`

| Chave | Arquivo | Status |
|-------|---------|--------|
| `age_gate_title` | `templates/age_gate_modal.php:39` | ⚠️ VERIFICAR |
| `age_gate_p1` | `templates/age_gate_modal.php:40` | ⚠️ VERIFICAR |
| `age_gate_p2` | `templates/age_gate_modal.php:41` | ⚠️ VERIFICAR |
| `age_gate_enter_button` | `templates/age_gate_modal.php:42` | ⚠️ VERIFICAR |
| `enable_js_message` | `templates/age_gate_modal.php:82` | ⚠️ VERIFICAR |

---

## 9. ADMIN SIDEBAR

### Contexto: `admin_sidebar`

| Chave | Linha | Status |
|-------|-------|--------|
| `admin_title` | 19 | ⚠️ VERIFICAR |
| `welcome_message` | 20 | ⚠️ VERIFICAR |
| `sidebar_stats` | 48 | ⚠️ VERIFICAR |
| `sidebar_top_lists` | 54 | ⚠️ VERIFICAR |
| `sidebar_users` | 60 | ⚠️ VERIFICAR |
| `sidebar_providers` | 66 | ⚠️ VERIFICAR |
| `sidebar_ads` | 84 | ⚠️ VERIFICAR |
| `sidebar_translations` | 102 | ⚠️ VERIFICAR |
| `sidebar_logs` | 108 | ⚠️ VERIFICAR |
| `sidebar_dev_tools` | 112 | ⚠️ VERIFICAR |
| `sidebar_dev_orphans` | 116 | ⚠️ VERIFICAR |
| `sidebar_dev_no_ext` | 121 | ⚠️ VERIFICAR |
| `sidebar_dev_geo_test` | 126 | ⚠️ VERIFICAR |
| `sidebar_dev_structure` | 131 | ⚠️ VERIFICAR |

---

## 10. SEARCH RESULTS TEMPLATES

### Contexto: `search_results`

| Chave | Arquivo | Status |
|-------|---------|--------|
| `search_placeholder` | `templates/search-results.php:26-27` | ⚠️ VERIFICAR |
| `results_for` | `templates/search-results.php:28-29` | ⚠️ VERIFICAR |
| `profiles_found` | `templates/search-results.php:30` | ⚠️ VERIFICAR |
| `no_results_found` | `templates/search-results.php:32` | ⚠️ VERIFICAR |
| `refine_search_suggestion` | `templates/search-results.php:34` | ⚠️ VERIFICAR |

### Contexto: `units`

| Chave | Arquivo | Status |
|-------|---------|--------|
| `unit_km` | `templates/search-results.php:35` | ⚠️ VERIFICAR |

---

## 11. RECOMENDAÇÕES DE AÇÃO

### Imediato (Crítico)
1. ✅ **Adicionar `header_favorites` ao banco** com contexto `header` para todos os idiomas
2. ✅ **Verificar todas as chaves `header_*`** no banco com contexto correto
3. ✅ **Verificar chaves `footer_*`** no banco com contexto correto

### Curto Prazo (Alta Prioridade)
4. ✅ Verificar todas as chaves `ad_level_*` nos contextos `results_clubs`, `results_business`, etc.
5. ✅ Verificar todas as chaves `filter_*` nos respectivos contextos
6. ✅ Verificar chaves `planet_*` e `modal_*`

### Médio Prazo
7. ✅ Criar script SQL para popular TODAS as chaves faltantes
8. ✅ Implementar validação automática que compare chaves usadas no código vs banco
9. ✅ Adicionar logging quando tradução retorna a própria chave (indicador de falta)

---

## 12. SCRIPT SQL PARA POPULAR CHAVES CRÍTICAS

```sql
-- HEADER TRANSLATIONS (pt-br)
INSERT IGNORE INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)
VALUES
  -- Critical missing key
  ('pt-br', 'header', 'header_favorites', 'Favoritos', NOW(), NOW()),
  
  -- Verify these exist
  ('pt-br', 'header', 'header_dashboard', 'Painel', NOW(), NOW()),
  ('pt-br', 'header', 'header_menu', 'Menu', NOW(), NOW()),
  ('pt-br', 'header', 'header_login', 'Entrar', NOW(), NOW()),
  ('pt-br', 'header', 'header_logout', 'Sair', NOW(), NOW()),
  ('pt-br', 'header', 'header_ads', 'Anunciar', NOW(), NOW()),
  ('pt-br', 'header', 'logo_alt', 'Logotipo BacoSearch', NOW(), NOW()),
  ('pt-br', 'header', 'about_us', 'Sobre Nós', NOW(), NOW()),
  ('pt-br', 'header', 'terms_of_service', 'Termos de Serviço', NOW(), NOW()),
  ('pt-br', 'header', 'privacy_policy', 'Política de Privacidade', NOW(), NOW()),
  ('pt-br', 'header', 'cookie_policy', 'Política de Cookies', NOW(), NOW()),
  ('pt-br', 'header', 'header_licenses', 'Licenças', NOW(), NOW()),
  ('pt-br', 'header', 'contact_us', 'Contato', NOW(), NOW());

-- ENGLISH VERSIONS
INSERT IGNORE INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)
VALUES
  ('en-us', 'header', 'header_favorites', 'Favorites', NOW(), NOW()),
  ('en-us', 'header', 'header_dashboard', 'Dashboard', NOW(), NOW()),
  ('en-us', 'header', 'header_menu', 'Menu', NOW(), NOW()),
  ('en-us', 'header', 'header_login', 'Login', NOW(), NOW()),
  ('en-us', 'header', 'header_logout', 'Logout', NOW(), NOW()),
  ('en-us', 'header', 'header_ads', 'Advertise', NOW(), NOW()),
  ('en-us', 'header', 'logo_alt', 'BacoSearch Logo', NOW(), NOW()),
  ('en-us', 'header', 'about_us', 'About Us', NOW(), NOW()),
  ('en-us', 'header', 'terms_of_service', 'Terms of Service', NOW(), NOW()),
  ('en-us', 'header', 'privacy_policy', 'Privacy Policy', NOW(), NOW()),
  ('en-us', 'header', 'cookie_policy', 'Cookie Policy', NOW(), NOW()),
  ('en-us', 'header', 'header_licenses', 'Licenses', NOW(), NOW()),
  ('en-us', 'header', 'contact_us', 'Contact', NOW(), NOW());

-- UI MESSAGES
INSERT IGNORE INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)
VALUES
  ('pt-br', 'ui_messages', 'detecting_location', 'Detectando localização...', NOW(), NOW()),
  ('pt-br', 'ui_messages', 'use_precise_location', 'Usar minha localização precisa', NOW(), NOW()),
  ('pt-br', 'ui_messages', 'unknown_city_text', 'Localização desconhecida', NOW(), NOW()),
  ('en-us', 'ui_messages', 'detecting_location', 'Detecting location...', NOW(), NOW()),
  ('en-us', 'ui_messages', 'use_precise_location', 'Use my precise location', NOW(), NOW()),
  ('en-us', 'ui_messages', 'unknown_city_text', 'Unknown location', NOW(), NOW());

-- AD LEVELS (usado em TODAS as páginas de resultados)
INSERT IGNORE INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)
VALUES
  ('pt-br', 'results_clubs', 'ad_level_city', 'Anúncios da cidade de {city}', NOW(), NOW()),
  ('pt-br', 'results_clubs', 'ad_level_region', 'Anúncios da região de {region}', NOW(), NOW()),
  ('pt-br', 'results_clubs', 'ad_level_country', 'Anúncios de {country}', NOW(), NOW()),
  ('pt-br', 'results_clubs', 'ad_level_global', 'Anúncios globais', NOW(), NOW()),
  
  ('pt-br', 'results_business', 'ad_level_city', 'Anúncios da cidade de {city}', NOW(), NOW()),
  ('pt-br', 'results_business', 'ad_level_region', 'Anúncios da região de {region}', NOW(), NOW()),
  ('pt-br', 'results_business', 'ad_level_country', 'Anúncios de {country}', NOW(), NOW()),
  ('pt-br', 'results_business', 'ad_level_global', 'Anúncios globais', NOW(), NOW()),
  
  ('pt-br', 'results_services', 'ad_level_city', 'Anúncios da cidade de {city}', NOW(), NOW()),
  ('pt-br', 'results_services', 'ad_level_region', 'Anúncios da região de {region}', NOW(), NOW()),
  ('pt-br', 'results_services', 'ad_level_country', 'Anúncios de {country}', NOW(), NOW()),
  ('pt-br', 'results_services', 'ad_level_global', 'Anúncios globais', NOW(), NOW()),
  
  ('pt-br', 'results_streets', 'ad_level_city', 'Anúncios da cidade de {city}', NOW(), NOW()),
  ('pt-br', 'results_streets', 'ad_level_region', 'Anúncios da região de {region}', NOW(), NOW()),
  ('pt-br', 'results_streets', 'ad_level_country', 'Anúncios de {country}', NOW(), NOW()),
  ('pt-br', 'results_streets', 'ad_level_global', 'Anúncios globais', NOW(), NOW());
```

---

## 13. PRÓXIMOS PASSOS

1. ✅ Executar o script SQL no banco de produção
2. ✅ Limpar cache APCu (se habilitado): `apcu_clear_cache()`
3. ✅ Testar navegação header em todas as páginas
4. ✅ Verificar se "header_favorites" agora aparece traduzido
5. ✅ Implementar monitor de traduções faltantes (opcional)

---

**FIM DO RELATÓRIO**
