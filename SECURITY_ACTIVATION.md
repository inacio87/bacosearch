# 🔒 PROTEÇÕES DE ATIVAÇÃO AUTOMÁTICA

## Status Atual: ✅ PROTEGIDO

O sistema está configurado para **NUNCA ativar providers automaticamente**.

---

## 📍 Arquivos Verificados

### ✅ **api/api_register_providers.php** - CORRETO
```php
// Linha ~311 e ~361
'status' => 'pending',  // ✅ Sempre pending
```
**Comentários adicionados:** ⚠️ Política de aprovação manual obrigatória

### ✅ **modules/dashboard/admin/providers.php** - CORRETO
```php
// Linha ~55 - ÚNICO LUGAR de ativação
UPDATE providers SET status = 'active', is_active = 1 WHERE id = ?
```
**Comentários adicionados:** ✅ Único lugar onde provider pode ser ativado

### ✅ **api/verify_registration.php** - CORRETO
```php
// Linha ~88 - Cria CONTA com status active (não provider)
INSERT INTO accounts (...) VALUES (..., 'active', ...)
```
**Status:** OK - É a conta do usuário, não o provider

### ❌ **api/stripe_webhook.php** - NÃO EXISTE
**Status:** OK - Evita ativação automática por webhook

---

## 🛡️ Validações de Segurança

### ✅ Busca no código:
```powershell
grep -r "status.*=.*'active'" --include="*.php"
```
**Resultado:** Apenas 1 local válido (admin approve action)

### ✅ Proteção na query pública:
```php
// providers.php linha ~64
WHERE p.status = 'active' AND p.is_active = 1
```
**Status:** ✅ Correto - Somente aprovados aparecem

### ✅ Proteção na API pública:
```php
// api/providers.php linha ~52
WHERE p.status = 'active'
```
**Status:** ✅ Correto - Filtra apenas ativos

---

## 📊 Fluxo de Estados

```
┌──────────────────────┐
│ CADASTRO COMPLETO    │
│ status='pending'     │
│ is_active=0          │
└──────────┬───────────┘
           │
           ↓
    ┌──────────────┐
    │  PAGAMENTO?  │
    └──┬───────┬───┘
       │       │
    SIM│       │NÃO
       │       │
       ↓       ↓
┌──────────────────────┐
│ CONTINUA PENDING     │
│ (payment_confirmed)  │
└──────────┬───────────┘
           │
           ↓
    ┌──────────────┐
    │ ADMIN APROVA │ ← ÚNICO PONTO DE ATIVAÇÃO
    └──────┬───────┘
           │
           ↓
┌──────────────────────┐
│ status='active'      │
│ is_active=1          │
│ APARECE NO SITE ✅   │
└──────────────────────┘
```

---

## 🚨 Alertas de Segurança

### ⚠️ SE ALGUÉM TENTAR:

**1. Ativar via webhook:**
```php
// ❌ PROIBIDO
case 'checkout.session.completed':
    UPDATE providers SET status = 'active' ...  // NUNCA!
```

**2. Ativar via API externa:**
```php
// ❌ PROIBIDO
$_POST['force_active'] = true;
if ($_POST['force_active']) {
    UPDATE providers SET status = 'active' ...  // NUNCA!
}
```

**3. Ativar via formulário:**
```php
// ❌ PROIBIDO
if ($_POST['auto_publish']) {
    $status = 'active';  // NUNCA!
}
```

### ✅ SEMPRE DEVE SER:

```php
// ✅ CORRETO
$status = 'pending';  // Padrão obrigatório

// ✅ ÚNICO EXCEPTION (admin aprovação)
if (is_admin_logged_in() && $_POST['action'] === 'approve') {
    UPDATE providers SET status = 'active' ...  // OK
}
```

---

## 📝 Checklist de Revisão de Código

Antes de fazer commit/deploy, verificar:

- [ ] Nenhum `status = 'active'` fora do módulo admin
- [ ] Nenhum `is_active = 1` fora do módulo admin
- [ ] Webhook (se existir) NÃO ativa providers
- [ ] Todas queries públicas filtram `status = 'active'`
- [ ] Formulários sempre usam `status = 'pending'`
- [ ] APIs públicas nunca retornam pending/rejected

---

## 🎯 Resumo Final

| Item | Status | Localização |
|------|--------|-------------|
| Cadastro inicial | ✅ pending | api/api_register_providers.php |
| Após pagamento | ✅ pending | (nenhuma alteração) |
| Aprovação admin | ✅ active | modules/dashboard/admin/providers.php |
| Webhook Stripe | ❌ N/A | (não implementado) |
| Query pública | ✅ filtrado | providers.php, api/providers.php |
| Search | ✅ filtrado | search.php |

---

## 📚 Documentação Relacionada

- **FLOW_CADASTRO_COMPLETO.md** - Fluxo completo detalhado
- **POLITICA_APROVACAO.md** - Política oficial de aprovação
- **modules/dashboard/admin/providers.php** - Código de aprovação

---

**✅ SISTEMA PROTEGIDO CONTRA ATIVAÇÃO AUTOMÁTICA**

**Última verificação:** 03/11/2025  
**Status:** APROVADO ✅
