# 📋 FLUXO COMPLETO DE CADASTRO E APROVAÇÃO - BACOSEARCH

## 🔄 VISÃO GERAL DO FLUXO

```
┌─────────────────┐
│  1. REGISTER    │ → Cadastro inicial (email, senha, tipo)
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  2. EMAIL       │ → Link de verificação enviado
│  VERIFICATION   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  3. VERIFY      │ → Cria conta ativa na tabela accounts
│  REGISTRATION   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  4. REGISTER    │ → Preenche perfil completo do provider
│  PROVIDERS      │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  5. CHECKOUT    │ → Escolhe plano (Free ou Premium)
└────────┬────────┘
         │
         ├─────────────┐
         ↓             ↓
   ┌─────────┐   ┌──────────┐
   │  FREE   │   │ PREMIUM  │ → Stripe Checkout
   └────┬────┘   └─────┬────┘
        │              │
        │              ↓
        │        ┌──────────┐
        │        │ PAYMENT  │ → Pagamento processado
        │        └─────┬────┘
        │              │
        └──────┬───────┘
               ↓
      ┌─────────────────┐
      │  6. SUCCESS     │ → Confirmação enviada
      └────────┬────────┘
               │
               ↓
      ┌─────────────────┐
      │  7. ADMIN       │ → Admin aprova/rejeita
      │  DASHBOARD      │
      └────────┬────────┘
               │
               ↓
      ┌─────────────────┐
      │  8. PROVIDERS   │ → Anúncio publicado (status: active)
      │  LISTING        │
      └─────────────────┘
```

---

## 📝 DETALHAMENTO POR ETAPA

### **ETAPA 1: CADASTRO INICIAL**

#### 📄 Arquivo: `register.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\register.php`

**Responsabilidades:**
- Exibe formulário de cadastro inicial
- Coleta: nome completo, data nascimento, email, senha, telefone, nacionalidade, tipo de conta
- Tipos permitidos: `provider`, `services`, `companies`
- Valida idade mínima (18+)
- Implementa proteção CSRF e honeypot anti-spam

**Campos coletados:**
```php
- real_name          // Nome completo
- birth_date         // Data de nascimento
- email              // Email
- password           // Senha (8+ chars, maiúscula, minúscula, número)
- repeat_password    // Confirmação de senha
- phone_code         // DDI (+351, +55, etc)
- phone_number       // Número de telefone
- nationality_id     // ID do país na tabela countries
- account_type       // provider | services | companies
- privacy_consent    // Aceite dos termos
```

**Submissão:** POST para `api/api_register.php`

---

### **ETAPA 2: PROCESSAMENTO DO CADASTRO**

#### 📄 Arquivo: `api/api_register.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\api\api_register.php`

**Responsabilidades:**
1. Valida todos os campos do formulário
2. Verifica se email já existe (tabela `accounts`)
3. Verifica pedidos pendentes (tabela `registration_requests`)
4. Hash da senha com `PASSWORD_DEFAULT`
5. Gera token único de verificação (válido por 24h)
6. Insere registro na tabela `registration_requests`
7. Envia email de verificação

**Tabela: `registration_requests`**
```sql
INSERT INTO registration_requests (
    visitor_id,
    token,
    email,
    phone_number,
    account_type_requested,
    data_payload,           -- JSON com todos os dados
    status,                 -- 'pending_email_verification'
    expires_at,             -- NOW() + 24 hours
    ip_address,
    created_at
)
```

**JSON `data_payload`:**
```json
{
    "full_name": "João Silva",
    "birth_date": "1990-01-15",
    "nationality_id": 177,
    "phone_code": "+351",
    "phone_number": "912345678",
    "privacy_consent": 1,
    "password_hash": "$2y$10$...",
    "role_id": 3,
    "role_slug": "providers"
}
```

**Email enviado:**
- Template: `templates/emails/register_verification.html`
- Link: `https://bacosearch.com/api/verify_registration.php?token=ABC123...`
- Chaves de tradução usadas:
  - `registration_verification_email_subject`
  - `greeting`
  - `registration_email_main_message`
  - `registration_email_follow_up_message`
  - `verify_email_button_text`
  - `spam_notice`
  - `all_rights_reserved`

**Redirecionamento:** `register.php?status=success_verification_sent&email=...`

---

### **ETAPA 3: VERIFICAÇÃO DE EMAIL**

#### 📄 Arquivo: `api/verify_registration.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\api\verify_registration.php`

**Responsabilidades:**
1. Recebe token via GET (?token=...)
2. Busca na tabela `registration_requests`
3. Valida token (existe, não expirou, status correto)
4. Cria conta na tabela `accounts`
5. Atualiza status do pedido para 'completed'
6. Inicia sessão temporária
7. Redireciona para preenchimento de perfil

**Validações:**
```php
// Token inválido → 404
if (!$request) {
    $_SESSION['general_error_message'] = 'invalid_verification_token';
    header('Location: register.php?status=error_invalid_token');
}

// Token já usado → redireciona para login
if ($request['status'] === 'completed') {
    header('Location: /auth/login.php?status=already_verified');
}

// Token expirado → deleta e redireciona
if (new DateTime() > new DateTime($request['expires_at'])) {
    db_execute("DELETE FROM registration_requests WHERE id = ?", [$request['id']]);
    header('Location: register.php?status=expired');
}
```

**Criação da conta:**
```sql
INSERT INTO accounts (
    email,
    full_name,
    birth_date,
    nationality_id,
    phone_code,
    phone_number,
    password_hash,          -- do payload
    role_id,
    role,
    status,                 -- 'active'
    visitor_id,
    ip_address,
    created_at
) VALUES (...)
```

**Sessão temporária:**
```php
$_SESSION['temp_user_id'] = $newAccountId;
$_SESSION['user_email'] = $request['email'];
$_SESSION['user_role'] = $payload['role_slug'];
```

**Redirecionamento:**
```
/pages/register_providers.php?account_id=123
// ou
/pages/register_services.php?account_id=123
// ou
/pages/register_businesses.php?account_id=123
```

---

### **ETAPA 4: CADASTRO DE PROVIDER**

#### 📄 Arquivo: `pages/register_providers.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\pages\register_providers.php`

**Responsabilidades:**
- Valida acesso (account_id obrigatório)
- Verifica se conta está ativa e tem senha definida
- Exibe formulário modular com 8 módulos
- Coleta informações completas do anunciante

**Módulos incluídos:**
1. **profile.php** - Nome artístico, título do anúncio, descrição, gênero, categoria
2. **body.php** - Características físicas (altura, peso, cor cabelo/olhos, etc)
3. **services.php** - Serviços oferecidos e status de cada um
4. **values.php** - Preços por tempo (15min, 30min, 1h, 2h, overnight)
5. **media.php** - Fotos (principal + galeria) e vídeos
6. **contact.php** - Telefone de anúncio, redes sociais
7. **logistics.php** - Cidade, estado, país do anúncio
8. **security.php** - Preferências de privacidade

**Submissão:** POST para `api/api_register_providers.php`

**Campos principais:**
```php
// Profile
artistic_name, ad_title, description, gender, provider_type,
category_id, nationality_id, languages_spoken

// Body
height, weight, hair_color, eye_color, body_type, bust_size,
has_tattoos, has_piercings, foot_size

// Services (N serviços, cada um com status)
service_XXX_status: 'included' | 'negotiable' | 'extra' | 'not_available'

// Prices
currency, base_hourly_rate, price_15_min, price_30_min,
price_2_hr, price_overnight

// Media
main_photo (file upload)
gallery_photos[] (multiple files)
videos[] (multiple files)
gallery_order (JSON array)
videos_order (JSON array)

// Contact
advertised_phone_code, advertised_phone_number,
instagram_username, twitter_username, onlyfans_url,
show_on_ad_whatsapp, show_on_ad_sms, show_on_ad_call

// Logistics
ad_city, ad_state, ad_country, ad_latitude, ad_longitude
```

---

### **ETAPA 5: PROCESSAMENTO DO PERFIL**

#### 📄 Arquivo: `api/api_register_providers.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\api\api_register_providers.php`

**Responsabilidades:**
1. Valida account_id
2. Calcula idade a partir de `accounts.birth_date`
3. Gera slug amigável para URL
4. Processa uploads de fotos e vídeos
5. Insere/atualiza em múltiplas tabelas

**Tabelas afetadas:**

**1. `providers` (tabela principal)**
```sql
INSERT/UPDATE providers SET
    account_id = ?,
    status = 'pending',              -- IMPORTANTE: inicia como pending
    display_name = ?,                -- nome artístico
    slug = ?,                        -- URL amigável
    category_id = ?,
    ad_title = ?,
    description = ?,
    gender = ?,
    age = ?,                         -- calculado
    provider_type = ?,
    nationality_id = ?,
    main_photo_url = ?,              -- /uploads/providers/123/photos/main_xxx.jpg
    gallery_photos = ?,              -- JSON array
    videos = ?,                      -- JSON array
    onlyfans_url = ?,
    instagram_username = ?,
    twitter_username = ?,
    currency = ?,
    base_hourly_rate = ?,
    price_15_min = ?,
    price_30_min = ?,
    price_2_hr = ?,
    price_overnight = ?,
    updated_at = NOW()
```

**2. `providers_body` (1:1)**
```sql
INSERT/UPDATE providers_body SET
    provider_id = ?,
    height_cm = ?,
    weight_kg = ?,
    hair_color = ?,
    eye_color = ?,
    body_type = ?,
    bust_cm = ?,
    tattoos = ?,                     -- boolean
    piercings = ?,                   -- boolean
    foot_size = ?
```

**3. `providers_contact` (1:1)**
```sql
INSERT/UPDATE providers_contact SET
    provider_id = ?,
    phone_code = ?,
    phone_number = ?,
    instagram = ?,
    twitter = ?,
    accepts_whatsapp = ?,
    accepts_sms = ?,
    accepts_calls = ?
```

**4. `providers_logistics` (1:1)**
```sql
INSERT/UPDATE providers_logistics SET
    provider_id = ?,
    ad_city = ?,
    ad_state = ?,
    ad_country = ?,
    ad_latitude = ?,
    ad_longitude = ?
```

**5. `providers_service_offerings` (N:N)**
```sql
-- Deleta todos os serviços anteriores
DELETE FROM providers_service_offerings WHERE provider_id = ?

-- Insere apenas os que foram marcados (não 'not_available')
INSERT INTO providers_service_offerings (
    provider_id,
    service_key,
    status,                          -- 'included' | 'negotiable' | 'extra'
    price,
    notes
) VALUES (?, ?, ?, ?, ?)
```

**Upload de arquivos:**
```
Diretório base: /uploads/providers/{visitor_id}/
    ├── photos/
    │   ├── main_1234567890.jpg
    │   ├── gallery_1234567890_abc123.jpg
    │   └── gallery_1234567891_def456.jpg
    └── videos/
        ├── video_1234567890_xyz789.mp4
        └── video_1234567891_uvw012.mp4

Tipos permitidos:
- Fotos: image/jpeg, image/png, image/webp (max 10MB)
- Vídeos: video/mp4, video/avi (max 50MB)
```

**Resposta JSON:**
```json
{
    "status": "success",
    "message": "Provider profile saved successfully",
    "data": {
        "provider_id": 456
    }
}
```

**Redirecionamento (front-end):**
```javascript
window.location.href = '/success.php?status=analysis_pending&provider_id=456';
```

---

### **ETAPA 6: CHECKOUT / ESCOLHA DE PLANO**

#### 📄 Arquivo: `checkout.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\checkout.php`

**Responsabilidades:**
- Valida account_id e provider_id (via GET ou sessão)
- Carrega planos da tabela `plans` (is_active = TRUE)
- Detecta moeda do usuário via `countries.currencies_icon/currencies`
- Exibe planos Free e Premium

**Tabela: `plans`**
```sql
SELECT * FROM plans WHERE is_active = TRUE ORDER BY price_monthly ASC

Colunas:
- id
- name                  -- 'Free Plan', 'Premium Plan'
- type                  -- 'free' | 'premium'
- price_monthly         -- 0.00 ou 29.99
- stripe_price_id       -- 'price_ABC123...' (para Premium)
- features              -- JSON array
- is_active
```

**Exemplo de features (JSON):**
```json
[
    {
        "text_key": "feature_ad_visibility",
        "icon": "fas fa-eye",
        "highlight": false
    },
    {
        "text_key": "feature_photo_limit",
        "icon": "fas fa-images"
    },
    {
        "text_key": "feature_premium_highlight",
        "icon": "fas fa-star",
        "highlight": true
    }
]
```

**Opções do usuário:**

**1. Plano FREE:**
- Botão: "Confirmar e Publicar"
- Ação: Link direto para `success.php`
- Sem pagamento necessário
- Provider fica com status 'pending' (aguarda aprovação admin)

**2. Plano PREMIUM:**
- Botão: "Upgrade Premium"
- Ação: POST para `api/create-checkout-session.php`
- Campos enviados:
  ```php
  csrf_token, price_id, provider_id, account_id
  ```

---

### **ETAPA 7A: CHECKOUT STRIPE (PREMIUM)**

#### 📄 Arquivo: `api/create-checkout-session.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\api\create-checkout-session.php`

**Responsabilidades:**
1. Valida CSRF token
2. Valida price_id, provider_id, account_id
3. Cria sessão Stripe Checkout
4. Redireciona para página de pagamento

**⚠️ IMPORTANTE: PAGAMENTO NÃO ATIVA AUTOMATICAMENTE**

**Mesmo pagando Premium, o provider NÃO é ativado automaticamente.**  
O admin precisa verificar o pagamento no Stripe Dashboard E aprovar manualmente.

**Validações:**
```php
// Price ID formato válido
preg_match('/^price_[A-Za-z0-9]+$/', $price_id)

// IDs numéricos válidos
is_numeric($provider_id) && is_numeric($account_id)
```

**Criação da sessão Stripe:**
```php
\Stripe\Checkout\Session::create([
    'mode'        => 'subscription',
    'locale'      => 'pt',                    // baseado em $_SESSION['language']
    'line_items'  => [[
        'price'    => $price_id,              // price_ABC123...
        'quantity' => 1,
    ]],
    'client_reference_id' => "$account_id:$provider_id",
    'metadata' => [
        'account_id'   => $account_id,
        'provider_id'  => $provider_id,
        'plan_price_id'=> $price_id,
    ],
    'success_url' => 'https://bacosearch.com/success.php?session_id={CHECKOUT_SESSION_ID}&provider_id=456&account_id=123',
    'cancel_url'  => 'https://bacosearch.com/checkout.php?provider_id=456&account_id=123',
], [
    'idempotency_key' => 'chk_' . hash('sha256', session_id() . '|' . $price_id . '|' . $provider_id . '|' . $account_id)
]);
```

**Redirecionamento:** `header('Location: ' . $checkout_session->url);`

**Métodos de pagamento aceitos:**
- Cartão de crédito/débito (padrão Stripe)
- **Nota:** Multibanco em subscriptions requer configuração `collection_method=send_invoice` no Stripe Dashboard

---

### **ETAPA 7B: PROCESSAMENTO DO PAGAMENTO**

**⚠️ POLÍTICA: NENHUM PROVIDER É ATIVADO AUTOMATICAMENTE**

**Razão:** Todos os cadastros (Free e Premium) precisam passar por análise do admin antes de serem publicados.

**Status após pagamento Premium:**
```sql
providers.status = 'pending'        -- Aguarda aprovação
providers.is_active = 0             -- Não aparece no site
providers.plan_type = 'premium'     -- Registra que escolheu premium (opcional)
```

**O que o webhook PODERIA fazer (mas NÃO FAZ por política):**
```php
// ❌ NÃO IMPLEMENTAR - Contra política do site
// 
// case 'checkout.session.completed':
//     // Marcar apenas que o pagamento foi confirmado
//     UPDATE providers SET payment_confirmed = 1 WHERE id = ?
//     
//     // MAS NUNCA: status = 'active' (somente admin pode fazer isso)
```

**Webhook sugerido (apenas para registro de pagamento):**
```php
// api/stripe_webhook.php
require_once __DIR__ . '/../core/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/stripe-php/init.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = 'whsec_...'; // do Stripe Dashboard

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $metadata = $session->metadata;
            $provider_id = (int)$metadata->provider_id;
            $account_id = (int)$metadata->account_id;
            
            // ✅ APENAS registra que o pagamento foi confirmado
            // NÃO ativa o provider - isso é responsabilidade do admin
            $db = getDBConnection();
            $stmt = $db->prepare("
                UPDATE providers 
                SET plan_type = 'premium',
                    payment_confirmed = 1,
                    payment_confirmed_at = NOW(),
                    stripe_session_id = :session_id,
                    updated_at = NOW() 
                WHERE id = :provider_id 
                  AND account_id = :account_id
                  AND status = 'pending'
            ");
            $stmt->execute([
                ':provider_id' => $provider_id,
                ':account_id' => $account_id,
                ':session_id' => $session->id
            ]);
            
            // Opcional: enviar notificação ao admin
            log_system_error(
                "Provider Premium ID $provider_id pagou e aguarda aprovação.", 
                'INFO', 
                'stripe_premium_payment_confirmed'
            );
            break;
            
        case 'customer.subscription.deleted':
            // Registrar cancelamento (mas provider continua ativo se já foi aprovado)
            $subscription = $event->data->object;
            // Implementar lógica de downgrade se necessário
            break;
            
        case 'invoice.payment_failed':
            // Notificar admin sobre falha de pagamento recorrente
            break;
    }
    
    http_response_code(200);
} catch (\Exception $e) {
    log_system_error('Stripe Webhook Error: ' . $e->getMessage(), 'CRITICAL', 'stripe_webhook');
    http_response_code(400);
}
```

**Configuração no Stripe Dashboard:**
- URL: `https://bacosearch.com/api/stripe_webhook.php`
- Eventos: `checkout.session.completed`, `customer.subscription.deleted`, `invoice.payment_failed`

**⚠️ IMPORTANTE:** O webhook apenas REGISTRA o pagamento. A APROVAÇÃO continua sendo manual via dashboard admin.

---

### **ETAPA 8: PÁGINA DE SUCESSO**

#### 📄 Arquivo: `success.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\success.php`

**Responsabilidades:**
- Mostra mensagem de confirmação
- Informa próximos passos
- Limpa dados temporários da sessão

**Status permitidos:**
```php
$allowed_status = ['analysis_pending', 'default'];
$status = $_GET['status'] ?? 'default';
```

**Mensagens por status:**

**1. `analysis_pending`** (padrão para novos cadastros)
```
Chave: success_message_analysis_pending
Texto: "Seu perfil foi enviado com sucesso! Nossa equipe irá analisar 
        suas informações e ativar seu anúncio em breve. Você receberá 
        um email quando estiver tudo pronto."
```

**2. `default`**
```
Chave: success_message_default
Texto: "Operação concluída com sucesso!"
```

**Limpeza de sessão:**
```php
unset($_SESSION['form_data_provider_form']);
unset($_SESSION['errors_provider_form']);
```

**Botão:** "Voltar para Home" → SITE_URL

---

### **ETAPA 9: DASHBOARD DO ADMIN**

#### 📄 Arquivo: `admin/dashboard.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\admin\dashboard.php`

**Responsabilidades:**
- Valida sessão admin ($_SESSION['admin_id'])
- Carrega módulos do dashboard
- Módulo padrão: stats

**Módulos disponíveis:**
```php
$allowed_modules = [
    'stats',           // Estatísticas gerais
    'users',           // Gerenciar contas
    'providers',       // ⭐ Aprovar/rejeitar providers
    'businesses',      // Gerenciar empresas
    'ads_management',  // Gerenciar anúncios
    'translations',    // Gerenciar traduções
    'system_logs',     // Logs do sistema
    'top_lists',       // Listas destacadas
    'services',        // Serviços disponíveis
    'create_admin'     // Criar novos admins
];
```

**Acesso ao módulo de providers:** `?module=providers`

---

### **ETAPA 10: MÓDULO DE APROVAÇÃO**

#### 📄 Arquivo: `modules/dashboard/admin/providers.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\modules\dashboard\admin\providers.php`

**Responsabilidades:**
- Lista todos os providers
- Filtra por status (all, active, pending, rejected, suspended)
- Permite aprovar, rejeitar ou suspender

**Query de listagem:**
```sql
SELECT 
    p.id AS provider_id,
    p.account_id,
    p.display_name,
    p.ad_title,
    p.status,
    a.email,
    a.full_name,
    a.phone_number,
    pl.ad_city,
    pl.ad_state,
    pl.ad_country,
    a.created_at AS registration_date
FROM 
    providers p
    INNER JOIN accounts a ON p.account_id = a.id
    LEFT JOIN providers_logistics pl ON p.id = pl.provider_id
WHERE 
    p.status = 'pending'              -- filtrável
ORDER BY 
    p.created_at DESC
```

**Ações disponíveis:**

**1. APROVAR (status: pending → active)**
```sql
UPDATE providers 
SET status = 'active', 
    is_active = 1, 
    updated_at = NOW() 
WHERE id = ?
```
**Efeito:** Provider aparece em `providers.php` e nos resultados de busca

**2. REJEITAR (qualquer status → rejected)**
```sql
UPDATE providers 
SET status = 'rejected', 
    is_active = 0, 
    updated_at = NOW() 
WHERE id = ?
```
**Efeito:** Provider não aparece publicamente

**3. SUSPENDER (active → suspended)**
```sql
UPDATE providers 
SET status = 'suspended', 
    is_active = 0, 
    updated_at = NOW() 
WHERE id = ?
```
**Efeito:** Remove temporariamente do site (pode reativar depois)

**Formulário de ação:**
```html
<form method="POST" onsubmit="return confirm('Tem certeza?');">
    <input type="hidden" name="provider_id" value="456">
    <button type="submit" name="action" value="approve">Aprovar</button>
    <button type="submit" name="action" value="reject">Rejeitar</button>
    <button type="submit" name="action" value="suspend">Suspender</button>
</form>
```

**Filtros de status:**
```
?module=providers&status=all        → Todos
?module=providers&status=active     → Ativos
?module=providers&status=pending    → Aguardando aprovação
?module=providers&status=rejected   → Rejeitados
?module=providers&status=suspended  → Suspensos
```

---

### **ETAPA 11: PUBLICAÇÃO DO ANÚNCIO**

#### 📄 Arquivo: `providers.php`
**Localização:** `c:\Users\Public\Bacosearch\bacosearch.com\providers.php`

**Responsabilidades:**
- Exibe perfil público do provider
- **APENAS** se status = 'active' E is_active = 1
- Gera URL amigável (SEO)
- Mostra galeria, serviços, preços, contatos

**Query de busca:**
```sql
SELECT
    p.*,
    l.ad_city, l.ad_country, l.ad_latitude, l.ad_longitude,
    cat.name AS category_name,
    cn.name AS nationality_name,
    cn.nationality_female,
    pb.height_cm, pb.weight_kg, pb.hair_color, pb.eye_color,
    pb.body_type, pb.bust_cm, pb.tattoos, pb.piercings
FROM providers p
LEFT JOIN providers_logistics l ON l.provider_id = p.id
LEFT JOIN providers_body pb ON pb.provider_id = p.id
LEFT JOIN categories cat ON cat.id = p.category_id
LEFT JOIN countries cn ON cn.id = p.nationality_id
WHERE 
    p.id = :id 
    AND p.status = 'active'        -- ⭐ FILTRO CRÍTICO
    AND p.is_active = 1            -- ⭐ FILTRO CRÍTICO
LIMIT 1
```

**URLs aceitas:**
```
// Por ID (redireciona para slug)
https://bacosearch.com/providers.php?id=456

// Por slug (canônico, SEO)
https://bacosearch.com/escort-portuguesa-em-lisboa-maria-456
```

**Geração do slug:**
```php
$slug = create_slug(
    $provider['category_name'] . '-' .       // escort
    $provider['nationality_name'] . '-' .    // portuguesa
    'em' . '-' .                             // conector
    $provider['ad_city'] . '-' .             // lisboa
    $provider['display_name'] . '-' .        // maria
    $provider['id']                          // 456
);
// Resultado: escort-portuguesa-em-lisboa-maria-456
```

**Redirecionamento automático para slug:**
```php
$current_path = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($current_path !== $slug) {
    header('Location: ' . SITE_URL . '/' . $slug, true, 301);
    exit;
}
```

**Seções exibidas:**
1. Header (nome, idade, nacionalidade, distância, verificado)
2. Galeria de fotos (lightbox)
3. Descrição completa
4. Detalhes físicos (altura, peso, cores, tatuagens, etc)
5. Serviços oferecidos (com status: incluído/negociável/taxa extra)
6. Preços (15min, 30min, 1h, 2h, overnight)
7. Informações adicionais (idiomas, localidades)
8. Redes sociais (Instagram, Twitter, OnlyFans)
9. Vídeos
10. Botões de contato (WhatsApp, Telegram, Call, SMS)

**Controles de visibilidade:**
```php
// Telefone só aparece se provider configurou
if ($accept_whatsapp) { /* mostra botão WhatsApp */ }
if ($accept_calls) { /* mostra botão Call */ }
if ($accept_sms) { /* mostra botão SMS */ }
if ($has_telegram) { /* mostra botão Telegram */ }

// Serviços só aparecem se status != 'not_available'
foreach ($services_offered as $service) {
    if ($service['status'] === 'not_available') continue;
    // exibe com badge: included | negotiable | extra_fee
}
```

---

## 📊 RESUMO DAS TABELAS E STATUS

### **Tabela: `registration_requests`**
```
Status possíveis:
- pending_email_verification → Email não verificado ainda
- completed                  → Conta criada, pode deletar registro
```

### **Tabela: `accounts`**
```
Status possíveis:
- active                     → Conta ativa (normal)
- pending_email_verification → (não usado neste fluxo)
- pending_admin_approval     → (não usado neste fluxo)
- suspended                  → Conta suspensa
- rejected                   → Conta rejeitada
```

### **Tabela: `providers`**
```
Status possíveis:
- pending    → Aguardando aprovação do admin
- active     → Aprovado e publicado
- rejected   → Rejeitado pelo admin
- suspended  → Suspenso (pode reativar)

Campos de controle:
- is_active (0|1)  → Flag adicional para mostrar/ocultar
- plan_type        → 'free' | 'premium'
- status + is_active = 1 → Aparece no site
```

---

## 🔍 PONTOS DE VERIFICAÇÃO PARA O ADMIN

### **1. Verificar se pagamento caiu (Premium)**

**⚠️ IMPORTANTE: PAGAMENTO NÃO ATIVA AUTOMATICAMENTE**

**Política do site:** Todos os providers (Free e Premium) precisam ser aprovados manualmente pelo admin.

**Onde verificar pagamento:**
- **Stripe Dashboard:** https://dashboard.stripe.com/subscriptions
- **Filtro:** Procurar por `client_reference_id` = "123:456" (account_id:provider_id)
- **Metadata:** Verificar `provider_id` e `account_id`

**Status de assinatura no Stripe:**
- `active` → Pagamento aprovado, pode aprovar o provider
- `incomplete` → Aguardando pagamento
- `past_due` → Pagamento atrasado
- `canceled` → Assinatura cancelada

**Fluxo atual (SEM webhook automático):**

1. **Provider paga no Stripe** → Subscription fica `active`
2. **Provider volta para success.php** → Vê mensagem "aguardando aprovação"
3. **Provider permanece:** `status='pending'` + `is_active=0` → **NÃO APARECE NO SITE**
4. **Admin vai no Stripe Dashboard** → Confirma que subscription está `active`
5. **Admin vai no BacoSearch** → `?module=providers&status=pending`
6. **Admin clica "Aprovar"** → `status='active'` + `is_active=1` → **APARECE NO SITE**

**Fluxo com webhook (opcional - apenas registra pagamento):**

Se você configurar o webhook sugerido acima:

1. **Provider paga no Stripe** → Webhook recebe `checkout.session.completed`
2. **Webhook atualiza:** `payment_confirmed=1` + `plan_type='premium'`
3. **Provider permanece:** `status='pending'` → **AINDA NÃO APARECE NO SITE**
4. **Admin vê indicador** → "✓ Pagamento confirmado" no dashboard
5. **Admin aprova manualmente** → `status='active'` → **APARECE NO SITE**

**Vantagem do webhook:** Admin sabe rapidamente quem já pagou, mas a aprovação continua manual.

### **2. Conferir dados do provider**

**No módulo `?module=providers&status=pending`:**

**Informações visíveis:**
- Nome completo (display_name)
- Email da conta
- Telefone
- Título do anúncio
- Localização (cidade, estado)
- Data de registro

**Verificações recomendadas:**
1. Fotos são reais e apropriadas
2. Descrição não contém spam/links externos
3. Preços estão razoáveis
4. Localização está correta
5. Serviços são legítimos

**Como ver detalhes completos:**
- Clicar no provider_id para abrir perfil
- Ou acessar diretamente: `providers.php?id=456` (mesmo com status pending, admin logado pode ver)

### **3. Aprovar ou rejeitar**

**Aprovar:**
```
Ação: Clique em "Aprovar"
Efeito:
- status = 'active'
- is_active = 1
- Provider aparece em providers.php
- Provider aparece nos resultados de busca (search.php)
```

**Rejeitar:**
```
Ação: Clique em "Rejeitar"
Efeito:
- status = 'rejected'
- is_active = 0
- Provider NÃO aparece publicamente
- Usuário pode refazer cadastro (?)
```

**Suspender (se já estava ativo):**
```
Ação: Clique em "Suspender"
Efeito:
- status = 'suspended'
- is_active = 0
- Remove temporariamente do site
- Pode reativar depois (mudando para active)
```

---

## 🔄 FLUXOS ALTERNATIVOS

### **A. Plano FREE**
```
register → email → verify → register_providers → checkout 
    → clica "Free Plan" → success (analysis_pending)
    → status='pending' + is_active=0 (NÃO APARECE NO SITE)
    → admin aprova manualmente → status='active' + is_active=1
    → AGORA aparece em providers.php e busca
```

### **B. Plano PREMIUM (sem webhook - ATUAL)**
```
register → email → verify → register_providers → checkout 
    → clica "Premium Plan" → Stripe Checkout → pagamento OK
    → success (analysis_pending)
    → status='pending' + is_active=0 (NÃO APARECE NO SITE)
    → admin verifica pagamento no Stripe Dashboard
    → admin aprova manualmente → status='active' + is_active=1
    → AGORA aparece em providers.php e busca
```

### **C. Plano PREMIUM (com webhook - RECOMENDADO)**
```
register → email → verify → register_providers → checkout 
    → clica "Premium Plan" → Stripe Checkout → pagamento OK
    → webhook marca payment_confirmed=1 + plan_type='premium'
    → MAS status='pending' + is_active=0 (AINDA NÃO APARECE)
    → success (analysis_pending)
    → admin vê "✓ Pago" no dashboard
    → admin aprova manualmente → status='active' + is_active=1
    → AGORA aparece em providers.php e busca
```

### **D. Token expirado**
```
register → email → espera +24h → clica link
    → verify_registration detecta expiração
    → deleta registro
    → redireciona register.php?status=expired
    → usuário precisa refazer cadastro
```

---

## ⚠️ REGRA DE OURO

**NENHUM PROVIDER É ATIVADO AUTOMATICAMENTE**

```sql
-- Após qualquer cadastro (Free ou Premium):
providers.status = 'pending'
providers.is_active = 0

-- Para aparecer no site, SEMPRE precisa:
providers.status = 'active'  -- ✅ Somente o admin pode mudar
providers.is_active = 1      -- ✅ Somente o admin pode mudar
```

**Razão:** Controle de qualidade. O admin verifica:
- Fotos são reais e apropriadas
- Descrição não tem spam
- Localização está correta
- Preços são razoáveis
- Serviços são legítimos
- (Se Premium) Pagamento foi confirmado no Stripe

---

## 📧 EMAILS ENVIADOS NO FLUXO

### **1. Email de verificação** (api_register.php)
```
Template: templates/emails/register_verification.html
Assunto: registration_verification_email_subject
Link: /api/verify_registration.php?token=ABC123...
Quando: Imediatamente após register.php
```

### **2. Email de confirmação** (success.php - futuro)
```
❌ NÃO IMPLEMENTADO AINDA
Sugestão: Enviar quando admin aprovar
Assunto: "Seu anúncio foi aprovado!"
Conteúdo: Link para o perfil público
```

### **3. Email de rejeição** (admin - futuro)
```
❌ NÃO IMPLEMENTADO AINDA
Sugestão: Enviar quando admin rejeitar
Assunto: "Seu cadastro precisa de correções"
Conteúdo: Motivo da rejeição + instruções
```

---

## 🔐 CONTROLES DE ACESSO

### **Área Pública**
- `register.php` → Qualquer visitante
- `providers.php` → Qualquer visitante (só vê status='active')

### **Área Protegida (requer temp_user_id)**
- `pages/register_providers.php` → Apenas quem verificou email

### **Área Admin (requer admin_id)**
- `admin/dashboard.php` → Apenas admins
- `modules/dashboard/admin/providers.php` → Apenas admins

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### ✅ **Já implementado e funcionando:**

- [x] **Fluxo de cadastro inicial** (register.php)
- [x] **Email de verificação** (api_register.php)
- [x] **Criação de conta após verificação** (verify_registration.php)
- [x] **Formulário modular de provider** (register_providers.php)
- [x] **Upload de fotos e vídeos** (api_register_providers.php)
- [x] **Integração com Stripe Checkout** (create-checkout-session.php)
- [x] **Dashboard admin com filtros** (admin/dashboard.php)
- [x] **Aprovação/rejeição/suspensão manual** (modules/dashboard/admin/providers.php)
- [x] **Página pública de provider** (providers.php)
- [x] **Validação: status='pending' por padrão** (todos os novos providers)
- [x] **Validação: somente status='active' + is_active=1 aparecem no site**

### 🔨 **Recomendado implementar:**

- [ ] **Webhook Stripe** (`api/stripe_webhook.php`) → **RECOMENDADO**
  - ✅ Registra pagamentos automaticamente
  - ✅ Marca `payment_confirmed=1` e `plan_type='premium'`
  - ❌ **NÃO** ativa o provider (mantém `status='pending'`)
  - ✅ Admin vê indicador "Pagamento confirmado" no dashboard
  - ✅ Admin ainda precisa aprovar manualmente

- [ ] **Email de aprovação** (quando admin aprovar)
  - Assunto: "Seu anúncio foi aprovado e está no ar!"
  - Conteúdo: Link direto para o perfil público
  - Trigger: após UPDATE providers SET status='active'

- [ ] **Email de rejeição** (quando admin rejeitar)
  - Assunto: "Seu cadastro precisa de correções"
  - Conteúdo: Motivo da rejeição + instruções
  - Novo campo: `providers.rejection_reason` (TEXT)

- [ ] **Notificações para admin** (novo provider cadastrado)
  - Email imediato: "Novo provider aguardando aprovação"
  - Badge no dashboard: contador de pendentes
  - Opcional: notificação push/Telegram

- [ ] **Sistema de reenvio de email** (se token expirou)
  - Botão "Reenviar email de verificação" em register.php
  - Gerar novo token + extender expires_at
  - Limitar: máximo 3 reenvios por email

- [ ] **Dashboard do provider** (área do anunciante)
  - Ver estatísticas do anúncio (views, cliques, leads)
  - Editar perfil completo
  - Gerenciar plano (upgrade/downgrade/cancelar)
  - Ver mensagens/leads recebidos
  - Status do anúncio (pending/active/rejected)

- [ ] **Campo payment_confirmed** (tabela providers)
  - Novo campo: `payment_confirmed` TINYINT(1) DEFAULT 0
  - Novo campo: `payment_confirmed_at` DATETIME NULL
  - Novo campo: `stripe_session_id` VARCHAR(255) NULL
  - Atualizado via webhook quando pagamento OK
  - Admin vê ✓ verde ao lado de providers Premium pagos

### 🔧 **Melhorias opcionais:**

- [ ] **Log de ações do admin**
  - Registrar quem aprovou/rejeitou cada provider
  - Tabela: `admin_actions` (admin_id, action, provider_id, reason, created_at)

- [ ] **Sistema de comentários internos**
  - Admins podem deixar notas sobre providers
  - Visível apenas no dashboard admin

- [ ] **Aprovação em lote**
  - Checkbox para selecionar múltiplos providers
  - Botão "Aprovar selecionados"

- [ ] **Filtro por plano no dashboard**
  - `?module=providers&plan=free`
  - `?module=providers&plan=premium`
  - `?module=providers&payment_confirmed=1`

---

## 🎯 POLÍTICA FINAL

### **Status na tabela `accounts`:**
```sql
-- Após verificar email:
status = 'active'  -- ✅ Usuário pode fazer login
```

### **Status na tabela `providers`:**
```sql
-- Após preencher perfil (Free ou Premium):
status = 'pending'   -- ⏳ Aguardando aprovação
is_active = 0        -- ❌ NÃO aparece no site

-- Após admin aprovar:
status = 'active'    -- ✅ Aprovado
is_active = 1        -- ✅ APARECE no site

-- Se admin rejeitar:
status = 'rejected'  -- ❌ Rejeitado
is_active = 0        -- ❌ NÃO aparece no site

-- Se admin suspender:
status = 'suspended' -- ⏸️ Suspenso (pode reativar)
is_active = 0        -- ❌ NÃO aparece no site
```

### **Controle de visibilidade:**
```sql
-- Query em providers.php e search.php:
WHERE status = 'active' AND is_active = 1

-- Se qualquer condição for falsa → NÃO APARECE
```

### **Responsabilidades:**

**Sistema automatizado:**
- ✅ Criar conta após verificar email
- ✅ Inserir provider com status='pending'
- ✅ Processar pagamento Stripe
- ✅ (Webhook) Marcar payment_confirmed=1
- ❌ **NUNCA** mudar status para 'active'

**Admin (ÚNICO responsável por ativação):**
- ✅ Verificar fotos, descrição, preços
- ✅ Verificar pagamento no Stripe (se Premium)
- ✅ Aprovar → muda status='active' + is_active=1
- ✅ Rejeitar → muda status='rejected'
- ✅ Suspender → muda status='suspended' + is_active=0

---

## 🗂️ ARQUIVOS DO FLUXO (ORDEM DE EXECUÇÃO)

```
1. register.php
2. api/api_register.php
3. templates/emails/register_verification.html
4. api/verify_registration.php
5. pages/register_providers.php
   ├── modules/providers/profile.php
   ├── modules/providers/body.php
   ├── modules/providers/services.php
   ├── modules/providers/values.php
   ├── modules/providers/media.php
   ├── modules/providers/contact.php
   ├── modules/providers/logistics.php
   └── modules/providers/security.php
6. api/api_register_providers.php
7. checkout.php
8. api/create-checkout-session.php (se Premium)
9. success.php
10. admin/dashboard.php?module=providers
11. modules/dashboard/admin/providers.php
12. providers.php
```

---

## 📞 CONTATOS DE INTEGRAÇÃO

### **Stripe**
- Dashboard: https://dashboard.stripe.com
- Webhook secret: `whsec_...` (configurar em Settings → Webhooks)
- Price IDs: Copiar de Products → Prices no Dashboard

### **PHPMailer**
- Config: `core/config.php` → MAIL_CONFIG
- Template base: `templates/emails/register_verification.html`
- Função: `core/functions.php` → send_email()

---

**🎯 CONCLUSÃO:** 

**O sistema está configurado corretamente para aprovação manual obrigatória.**

✅ **Todos os providers** (Free e Premium) ficam com `status='pending'` até o admin aprovar.  
✅ **Nenhum provider aparece automaticamente** no site após cadastro ou pagamento.  
✅ **Somente o admin** pode mudar `status='active'` e `is_active=1` para publicar o anúncio.

**Fluxo atual:**
1. Provider se cadastra → `status='pending'`
2. Provider paga Premium (opcional) → **continua** `status='pending'`
3. Admin verifica dados + pagamento → clica "Aprovar"
4. Sistema muda → `status='active'` + `is_active=1`
5. Anúncio **finalmente** aparece em providers.php

**Recomendação:** Implementar webhook Stripe apenas para **marcar** `payment_confirmed=1`, facilitando a vida do admin (ele vê quem já pagou), mas **mantendo a aprovação manual obrigatória**.
