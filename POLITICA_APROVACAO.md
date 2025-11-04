# 🛡️ POLÍTICA DE APROVAÇÃO DE PROVIDERS

## ⚠️ REGRA FUNDAMENTAL

**TODOS OS PROVIDERS PRECISAM SER APROVADOS MANUALMENTE PELO ADMIN**

Nenhum anunciante (provider) aparece automaticamente no site após:
- Completar o cadastro
- Verificar o email
- Preencher o perfil completo
- Pagar plano Premium

**Razão:** Controle de qualidade e moderação de conteúdo.

---

## 📋 STATUS DO PROVIDER

### **Após completar o cadastro:**
```sql
providers.status = 'pending'
providers.is_active = 0
```
**Resultado:** Provider **NÃO APARECE** no site (providers.php, search.php, etc)

### **Após admin aprovar:**
```sql
providers.status = 'active'
providers.is_active = 1
```
**Resultado:** Provider **APARECE** no site

---

## 🔐 ÚNICO LUGAR DE ATIVAÇÃO

**Arquivo:** `modules/dashboard/admin/providers.php`  
**Linha:** ~55  
**Ação:** Botão "Aprovar" no dashboard admin

```php
// ✅ ÚNICO LUGAR onde um provider pode ser ativado
$stmt = $pdo->prepare("
    UPDATE providers 
    SET status = 'active', 
        is_active = 1, 
        updated_at = NOW() 
    WHERE id = ?
");
```

**Nenhum outro código pode mudar `status='active'`**

---

## 🚫 O QUE NÃO FAZER

### ❌ **NÃO ativar automaticamente após pagamento**

```php
// ❌ PROIBIDO - Mesmo que pagamento seja confirmado
case 'checkout.session.completed':
    UPDATE providers SET status = 'active' WHERE id = ?  // ❌ NUNCA!
```

### ✅ **PERMITIDO - Apenas marcar que pagou**

```php
// ✅ OK - Registra pagamento mas mantém pending
case 'checkout.session.completed':
    UPDATE providers 
    SET payment_confirmed = 1,
        plan_type = 'premium'
    WHERE id = ?  
    // MAS status continua 'pending' ✅
```

---

## 📊 FLUXO CORRETO

### **Plano FREE:**
1. Provider preenche perfil → `status='pending'`
2. Admin verifica dados → clica "Aprovar"
3. Sistema muda → `status='active'` + `is_active=1`
4. Anúncio aparece no site ✅

### **Plano PREMIUM:**
1. Provider preenche perfil → `status='pending'`
2. Provider paga no Stripe → **continua** `status='pending'`
3. (Webhook) Sistema marca → `payment_confirmed=1`
4. Admin verifica dados + confirma pagamento → clica "Aprovar"
5. Sistema muda → `status='active'` + `is_active=1`
6. Anúncio aparece no site ✅

---

## 🔍 COMO ADMIN VERIFICA

### **No Dashboard Admin:**
Acesso: `admin/dashboard.php?module=providers&status=pending`

**Informações visíveis:**
- Nome artístico (display_name)
- Título do anúncio (ad_title)
- Email da conta
- Telefone
- Localização (cidade, estado)
- Data de cadastro
- ✓ Indicador de pagamento confirmado (se webhook implementado)

### **Verificações recomendadas:**
- [ ] Fotos são reais e apropriadas
- [ ] Descrição não contém spam/links externos
- [ ] Preços estão razoáveis
- [ ] Localização está correta
- [ ] Serviços são legítimos
- [ ] (Se Premium) Pagamento foi confirmado no Stripe

### **No Stripe Dashboard** (para Premium):
1. Acessar: https://dashboard.stripe.com/subscriptions
2. Filtrar por: `client_reference_id = "account_id:provider_id"`
3. Verificar: Status da subscription = `active`
4. Voltar para BacoSearch e aprovar

---

## 🛠️ IMPLEMENTAÇÕES FUTURAS

### **Webhook Stripe (RECOMENDADO):**

**Objetivo:** Facilitar trabalho do admin (não automatizar aprovação)

**O que faz:**
- ✅ Recebe evento `checkout.session.completed` do Stripe
- ✅ Marca `payment_confirmed=1` na tabela providers
- ✅ Marca `plan_type='premium'`
- ✅ Registra `stripe_session_id` e `payment_confirmed_at`
- ❌ **NÃO** muda `status` para 'active'

**Vantagem:**
- Admin vê ✓ verde ao lado de providers que já pagaram
- Não precisa abrir Stripe Dashboard toda vez
- Aprovação continua manual

**Arquivo:** `api/stripe_webhook.php` (ver `FLOW_CADASTRO_COMPLETO.md`)

### **Notificações para Admin:**

- Email quando novo provider se cadastra
- Badge com contador de pendentes no dashboard
- Push notification (opcional)

### **Emails para Provider:**

- **Após aprovação:** "Seu anúncio foi aprovado e está no ar!"
- **Após rejeição:** "Seu cadastro precisa de correções" + motivo

---

## 📝 TABELAS ENVOLVIDAS

### **accounts** (conta do usuário)
```sql
-- Após verificar email:
status = 'active'  -- ✅ Usuário pode fazer login
```

### **providers** (anúncio/perfil)
```sql
-- Status possíveis:
status IN ('pending', 'active', 'rejected', 'suspended')

-- Controle adicional:
is_active IN (0, 1)

-- Para aparecer no site (providers.php, search.php):
WHERE status = 'active' AND is_active = 1
```

---

## 🎯 RESPONSABILIDADES

### **Sistema (código):**
- ✅ Criar conta após verificar email → `accounts.status='active'`
- ✅ Criar provider após preencher perfil → `providers.status='pending'`
- ✅ Processar pagamento Stripe → marca `payment_confirmed=1`
- ❌ **NUNCA** mudar provider para `status='active'`

### **Admin (humano):**
- ✅ Verificar qualidade do conteúdo
- ✅ Verificar pagamento (se Premium)
- ✅ Decidir: Aprovar / Rejeitar / Suspender
- ✅ **ÚNICO** com poder de ativar provider

---

## ⚠️ AVISOS PARA DESENVOLVEDORES

### **Se você está:**

**Criando novo código que mexe com providers:**
- ❌ Nunca use `UPDATE providers SET status = 'active'` fora do módulo admin
- ✅ Sempre use `status = 'pending'` ao criar/atualizar

**Implementando webhook Stripe:**
- ❌ Não ative o provider automaticamente
- ✅ Apenas marque `payment_confirmed = 1`
- ✅ Deixe aprovação para o admin

**Criando API pública:**
- ❌ Nunca exponha providers com `status != 'active'`
- ✅ Sempre filtrar: `WHERE status = 'active' AND is_active = 1`

**Modificando formulário de cadastro:**
- ❌ Não adicione opção de "publicar imediatamente"
- ✅ Sempre redirecionar para success.php com mensagem de aprovação pendente

---

## 📞 DÚVIDAS?

Consulte a documentação completa em:  
**`FLOW_CADASTRO_COMPLETO.md`**

Ou entre em contato com a equipe de desenvolvimento.

---

**Última atualização:** 03/11/2025  
**Versão:** 1.0
