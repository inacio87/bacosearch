# 🚀 PLANO DE PIVOT - BACOSEARCH BRASIL

**Data**: 06/11/2025
**Objetivo**: Lançar site simplificado focado em acompanhantes no mercado brasileiro

---

## 🎯 ESTRATÉGIA DO PIVOT

### ❌ REMOVER (Complexidade Desnecessária):
- ~~Clubes de swing~~
- ~~Empresas/Estabelecimentos~~
- ~~Serviços gerais~~
- ~~Multi-idioma completo~~ (apenas PT-BR no lançamento)
- ~~Sistema de ruas/endereços~~
- ~~Funcionalidades enterprise~~

### ✅ MANTER (Core do Negócio):
- **Acompanhantes** (foco principal)
- Cadastro simplificado
- Busca por cidade/estado
- Perfis com fotos
- Sistema de contato (WhatsApp/Telefone)
- Admin básico

### 🆕 ADICIONAR (Pós-Lançamento):
- Sistema de favoritos
- Categorias (loiras, morenas, etc)
- Dark mode
- Age gate profissional
- Verificação de perfis

---

## 📋 CHECKLIST DE SIMPLIFICAÇÃO

### FASE 1 - LIMPEZA (1-2 dias)

#### 1.1 Remover Módulos Desnecessários
- [ ] Desativar páginas de clubes (`/pages/results_clubs.php`)
- [ ] Desativar páginas de empresas (`/pages/results_business.php`)
- [ ] Desativar páginas de serviços (`/pages/results_services.php`)
- [ ] Remover navegação para esses módulos no header/footer
- [ ] Comentar código relacionado no banco de dados

#### 1.2 Simplificar Cadastro
- [ ] Remover campos desnecessários do registro
- [ ] Manter apenas: Nome, Email, Telefone, Cidade, Fotos, Descrição
- [ ] Remover: múltiplos idiomas, serviços complexos, logística avançada
- [ ] Formulário em 1 página (não 6 etapas)

#### 1.3 Simplificar Busca
- [ ] Busca por: Cidade/Estado apenas
- [ ] Filtros básicos: Idade, Disponibilidade
- [ ] Remover: filtros de serviços específicos, raio de distância complexo

#### 1.4 Ajustar Homepage
- [ ] Foco em "Encontre Acompanhantes no Brasil"
- [ ] Busca simples: Digite a cidade
- [ ] Grid de perfis em destaque
- [ ] Remover menções a clubes/empresas/serviços

---

### FASE 2 - CORE FEATURES (3-5 dias)

#### 2.1 Sistema de Perfis Simplificado
```
Campos do Perfil:
├── Informações Básicas
│   ├── Nome artístico
│   ├── Idade
│   ├── Cidade/Estado
│   └── Telefone/WhatsApp
│
├── Mídia
│   ├── Fotos (até 10)
│   └── Foto de capa
│
├── Descrição
│   └── Texto livre (500 caracteres)
│
└── Disponibilidade
    ├── Local próprio / Atende em hotel
    └── Horários (dia/noite/madrugada)
```

#### 2.2 Página de Resultados
- [ ] Grid 3-4 colunas (desktop)
- [ ] Grid 2 colunas (mobile)
- [ ] Card com: Foto, Nome, Idade, Cidade, WhatsApp
- [ ] Ordenação: Mais recentes, Mais visualizados
- [ ] Paginação simples

#### 2.3 Página de Perfil Individual
```html
Layout:
├── Galeria de Fotos (principal)
├── Nome + Idade + Cidade
├── Botão WhatsApp (destaque verde)
├── Botão Telefone
├── Descrição
└── Informações básicas (altura, peso - opcional)
```

---

### FASE 3 - AJUSTES DE BRANDING (1 dia)

#### 3.1 Identidade Visual
- [ ] Nome: **BacoSearch** ou **Baco Acompanhantes**
- [ ] Slogan: "Encontre Acompanhantes em Todo o Brasil"
- [ ] Cores principais: Manter rosa/roxo atual
- [ ] Logo simplificado (sem ícones complexos)

#### 3.2 SEO Básico
```
Títulos das Páginas:
- Home: "Acompanhantes Brasil | BacoSearch"
- Cidade: "Acompanhantes em [Cidade] | BacoSearch"
- Perfil: "[Nome] - Acompanhante em [Cidade] | BacoSearch"

Meta Descriptions:
- Foco em palavras-chave: acompanhantes, garotas de programa, [cidade]
```

---

### FASE 4 - BANCO DE DADOS (1 dia)

#### 4.1 Estrutura Simplificada
```sql
Tabelas Necessárias:
├── users (anunciantes)
├── profiles (perfis de acompanhantes)
├── photos (fotos dos perfis)
├── cities (cidades brasileiras)
├── states (estados do Brasil)
└── admin_users (administradores)

Remover/Desativar:
├── clubs
├── businesses
├── services
├── streets
└── amenities complexas
```

#### 4.2 Migração de Dados
- [ ] Manter apenas perfis de acompanhantes ativos
- [ ] Arquivar dados de clubes/empresas (não deletar)
- [ ] Limpar traduções desnecessárias

---

### FASE 5 - FUNCIONALIDADES ESSENCIAIS (2-3 dias)

#### 5.1 Cadastro Simplificado (1 Página)
```
Formulário Único:
1. Dados Pessoais
   - Nome artístico
   - Email
   - Telefone/WhatsApp
   - Data de nascimento

2. Localização
   - Estado (dropdown)
   - Cidade (dropdown dependente)

3. Fotos
   - Upload de 3-10 fotos
   - Foto de capa obrigatória

4. Descrição
   - Texto livre (máx 500 chars)

5. Disponibilidade
   - [ ] Local próprio
   - [ ] Atende em hotel
   - Horários: [ ] Dia [ ] Noite [ ] Madrugada

6. Aceite de Termos
   - [ ] Sou maior de 18 anos
   - [ ] Aceito os termos de uso
```

#### 5.2 Busca Simplificada
```html
<form action="/buscar">
  <input type="text" placeholder="Digite a cidade..." autocomplete>
  <button>Buscar</button>
</form>

Resultados:
- Grid de cards
- Filtro lateral (idade min/max)
- Ordenação (recentes/populares)
```

#### 5.3 Contato Direto
- [ ] Botão WhatsApp com link direto `wa.me/55[telefone]`
- [ ] Botão Telefone com `tel:[numero]`
- [ ] Contador de visualizações do perfil
- [ ] Log de cliques em contato (analytics)

---

## 🗂️ ESTRUTURA DE ARQUIVOS SIMPLIFICADA

```
bacosearch.com/
├── index.php                    # Homepage
├── buscar.php                   # Página de busca/resultados
├── perfil.php?id=123           # Perfil individual
├── cadastro.php                 # Cadastro simplificado (1 página)
├── login.php                    # Login de anunciantes
│
├── admin/
│   ├── dashboard.php           # Dashboard simples
│   ├── aprovar.php             # Aprovar novos perfis
│   └── moderar.php             # Moderar conteúdo
│
├── api/
│   ├── upload_foto.php         # Upload de fotos
│   ├── cadastro.php            # Processar cadastro
│   └── busca.php               # API de busca
│
├── assets/
│   ├── css/
│   │   └── style.css           # CSS único simplificado
│   ├── js/
│   │   └── app.js              # JS mínimo
│   └── images/
│       └── uploads/            # Fotos dos perfis
│
└── core/
    ├── config.php              # Configurações
    ├── database.php            # Conexão DB
    └── functions.php           # Funções essenciais
```

---

## 🎨 DESIGN SIMPLIFICADO

### Homepage
```
┌─────────────────────────────────────┐
│  LOGO    [Cadastrar] [Entrar]      │
├─────────────────────────────────────┤
│                                     │
│   Encontre Acompanhantes no Brasil │
│   ═══════════════════════════════  │
│                                     │
│   [Digite sua cidade...] [Buscar]  │
│                                     │
├─────────────────────────────────────┤
│                                     │
│   EM DESTAQUE                       │
│   ┌───┐ ┌───┐ ┌───┐ ┌───┐         │
│   │ 📷│ │ 📷│ │ 📷│ │ 📷│         │
│   └───┘ └───┘ └───┘ └───┘         │
│   Nome  Nome  Nome  Nome            │
│   Idade Idade Idade Idade           │
│                                     │
│   ┌───┐ ┌───┐ ┌───┐ ┌───┐         │
│   │ 📷│ │ 📷│ │ 📷│ │ 📷│         │
│   └───┘ └───┘ └───┘ └───┘         │
│                                     │
├─────────────────────────────────────┤
│  Footer - Links - Contato           │
└─────────────────────────────────────┘
```

### Página de Resultados
```
┌─────────────────────────────────────┐
│  LOGO    [Cadastrar] [Entrar]      │
├─────────────────────────────────────┤
│  Acompanhantes em São Paulo         │
│  123 resultados                     │
├──────────┬──────────────────────────┤
│ FILTROS  │  RESULTADOS              │
│          │                          │
│ Idade    │  ┌───┐ ┌───┐ ┌───┐     │
│ [18-60]  │  │ 📷│ │ 📷│ │ 📷│     │
│          │  └───┘ └───┘ └───┘     │
│ Ordenar  │  Nome  Nome  Nome        │
│ • Recen. │  23    25    21          │
│ • Popul. │  SP    SP    SP          │
│          │  💬WhatsApp              │
│          │                          │
│          │  [Ver mais...]           │
├──────────┴──────────────────────────┤
│  Paginação: « 1 2 3 »               │
└─────────────────────────────────────┘
```

---

## 📱 MOBILE FIRST

### Prioridades Mobile:
1. **Cards verticais** (1 coluna em mobile, 2 em tablet, 3-4 em desktop)
2. **Botão WhatsApp fixo** na página de perfil
3. **Galeria touch-friendly** (swipe entre fotos)
4. **Busca com geolocalização** (detectar cidade automaticamente)
5. **Performance** (lazy loading de imagens)

---

## 🚀 CRONOGRAMA DE LANÇAMENTO

### Semana 1 (7-13 Nov)
- [x] Análise de concorrentes (FEITO)
- [ ] Limpeza do código (remover módulos)
- [ ] Simplificar banco de dados
- [ ] Criar cadastro em 1 página

### Semana 2 (14-20 Nov)
- [ ] Homepage nova (design simplificado)
- [ ] Página de busca/resultados
- [ ] Página de perfil individual
- [ ] Integração WhatsApp/Telefone

### Semana 3 (21-27 Nov)
- [ ] Admin simplificado
- [ ] Testes de cadastro
- [ ] Testes de busca
- [ ] Ajustes de SEO

### Semana 4 (28 Nov - 4 Dez)
- [ ] Age gate profissional
- [ ] Políticas de privacidade/termos
- [ ] Testes finais
- [ ] **LANÇAMENTO BETA**

---

## 💰 MODELO DE NEGÓCIO SIMPLIFICADO

### Planos de Anúncio:

#### 🆓 GRATUITO
- 3 fotos
- Descrição básica (200 chars)
- Listagem padrão
- Renovação manual a cada 30 dias

#### 💎 PREMIUM (R$ 49/mês)
- 10 fotos
- Descrição completa (500 chars)
- Destaque no topo (badge "Premium")
- Renovação automática
- Estatísticas de visualizações
- Link verificado ✓

#### 🌟 VIP (R$ 99/mês)
- Fotos ilimitadas
- Vídeo de apresentação (15s)
- Sempre no topo da busca
- Badge "VIP"
- Verificação com foto + documento ✓✓
- Suporte prioritário
- Renovação automática

---

## 🔧 TECNOLOGIAS (STACK SIMPLIFICADO)

### Backend:
```php
- PHP 8.0+ (atual)
- MySQL (atual)
- Composer (atual)
```

### Frontend:
```html
- HTML5 puro
- CSS3 (sem frameworks pesados)
- JavaScript vanilla (mínimo)
- Lazy loading nativo
```

### Bibliotecas Essenciais:
```javascript
// Manter apenas o essencial:
- Lightbox para galeria (SimpleLightbox)
- Autocomplete de cidades (Awesomplete)
- Lazy loading (vanilla-lazyload)
```

### Remover:
```
❌ Tailwind CSS (muito pesado)
❌ Bootstrap completo (usar apenas grid)
❌ jQuery (usar vanilla JS)
❌ Bibliotecas de tradução (só PT-BR)
```

---

## 📊 MÉTRICAS DE SUCESSO

### Mês 1 (Beta):
- [ ] 50 perfis cadastrados
- [ ] 1.000 visitantes únicos
- [ ] 5 anúncios premium vendidos

### Mês 3:
- [ ] 200 perfis ativos
- [ ] 10.000 visitantes/mês
- [ ] 20 assinaturas pagas

### Mês 6:
- [ ] 500 perfis ativos
- [ ] 50.000 visitantes/mês
- [ ] 50+ assinaturas pagas
- [ ] Break-even operacional

---

## ⚠️ CONFORMIDADE LEGAL

### Obrigatórios no Lançamento:
- [ ] Age gate (verificação +18)
- [ ] Termos de uso claros
- [ ] Política de privacidade (LGPD)
- [ ] Moderação de conteúdo
- [ ] Sistema de denúncia
- [ ] Proibição de menores explícita
- [ ] Disclaimer legal no footer

### Texto do Age Gate:
```
ATENÇÃO: Este site contém conteúdo adulto.

Você confirma que:
✓ Tem 18 anos ou mais
✓ Está acessando por vontade própria
✓ Não se ofende com conteúdo adulto
✓ Aceita os Termos de Uso

[SIM, TENHO +18]  [NÃO, SAIR]
```

---

## 🎯 DIFERENCIAL COMPETITIVO

### Por que escolher BacoSearch?

1. **Simplicidade**: Cadastro em 1 página (vs. 6 passos da concorrência)
2. **Gratuito**: Plano básico 100% grátis
3. **Direto**: WhatsApp integrado (1 clique)
4. **Rápido**: Site leve e responsivo
5. **Brasileiro**: Focado no mercado BR (todas as cidades)

---

## 📞 PRÓXIMOS PASSOS IMEDIATOS

### HOJE (06/Nov):
1. ✅ Análise de concorrentes (FEITO)
2. ✅ Plano de pivot (ESTE DOCUMENTO)
3. [ ] Criar branch `pivot-brasil` no Git
4. [ ] Backup completo do código atual

### AMANHÃ (07/Nov):
1. [ ] Remover módulos de clubes/empresas/serviços
2. [ ] Simplificar formulário de cadastro
3. [ ] Criar nova homepage mockup

### ESTA SEMANA:
1. [ ] Implementar cadastro simplificado
2. [ ] Criar página de busca nova
3. [ ] Integrar WhatsApp nos perfis

---

## ✅ APROVAÇÃO DO PLANO

- [ ] Revisar e aprovar estratégia
- [ ] Definir prioridades
- [ ] Começar implementação

**Próxima ação**: Aguardando sua aprovação para iniciar o pivot! 🚀
