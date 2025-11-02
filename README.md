# Bacosearch

Resumo rápido

Este repositório contém o código do site Bacosearch (PHP). O código principal está na pasta `bacosearch.com/` (há uma pasta aninhada `bacosearch.com/bacosearch.com/` com os arquivos públicos como `index.php`).

Objetivo deste README

- Fornecer instruções mínimas para preparar o ambiente de desenvolvimento local.
- Indicar boas práticas (não versionar segredos, não trabalhar diretamente no OneDrive, backups).

Assunções

- Projeto em PHP (versão recomendada: PHP 7.4+ ou idealmente PHP 8.0+). Se você tiver um `composer.json` no root, use-o; se não, o projeto atualmente tem `vendor/` commitado.

Requisitos mínimos (sugestão)

- PHP 7.4+ (recomendo PHP 8.0+)
- Composer (para dependências PHP)
- MySQL/MariaDB
- Opcional: Docker + Docker Compose para ambiente reproduzível

Como começar (local)

1. Mover o repositório para fora do OneDrive (recomendado):

   - Por exemplo: `C:\code\bacosearch` — trabalhar em pastas sincronizadas pode causar corrupção e conflitos.

2. Verificar se há `composer.json` no root do projeto:

   - Se existir, rode:
     ```powershell
     composer install
     ```

   - Se não existir, o repositório inclui `vendor/` — isso funciona, mas não é ideal. Recomendo gerar um `composer.json` apropriado (ou obter do upstream) e executar `composer install`.

3. Copiar variáveis de ambiente:

   - Crie um arquivo `.env` a partir de `.env.example` e preencha os valores.

4. Rodar servidor PHP embutido (teste rápido):

   - A partir da pasta que contém `index.php` (ex.: `bacosearch.com/`):
     ```powershell
     php -S 127.0.0.1:8000 -t .\bacosearch.com\
     ```

Boas práticas

- Nunca commite arquivos com segredos (credenciais, chaves API, `.env`). Use `.env.example` para documentar as chaves esperadas.
- Remova `vendor/` do repositório e use `composer` para instalar dependências. Se `vendor/` foi commitado deliberadamente, documente por que.
- Mova backups e dumps SQL para uma pasta `backups/` fora do root do código.

Estrutura observada

- `bacosearch.com/` (contém o site) — dentro pode haver outra pasta `bacosearch.com/` com os arquivos públicos.
- `api/`, `assets/`, `logs/`, `uploads/`, `vendor/` — mantenha `uploads/` e `logs/` fora do VCS.

Próximos passos recomendados

1. Criar `.gitignore` para ignorar `vendor/`, `.env`, `uploads/`, `logs/`, `error_log` e arquivos temporários.
2. Gerar `.env.example` que liste todas as variáveis de ambiente necessárias.
3. Se desejar, eu posso criar um `docker-compose.yml` para rodar PHP+MySQL localmente.

Contato e notas

Se quiser, eu posso aplicar as mudanças automaticamente (criar `.gitignore` e `.env.example`) — já adicionei esses arquivos aqui. Se preferir outro local para os arquivos (por causa das duas cópias do projeto), diga onde.
