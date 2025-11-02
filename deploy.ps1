#!/usr/bin/env pwsh
# Script de deploy para HostGator
# Uso: ./deploy.ps1

# Configurações do servidor
$REMOTE_USER = "chefej82"          # usuário SSH no HostGator
$REMOTE_HOST = "br1076.hostgator.com.br"  # hostname do servidor
$REMOTE_PATH = "/home4/chefej82/bacosearch.com/"  # path de deploy
$EXCLUDE_FILE = ".deployignore"     # arquivo com lista de exclusões

# Verifica se há mudanças não commitadas
git status --porcelain
if ($LASTEXITCODE -ne 0) {
    Write-Error "❌ Há mudanças não commitadas no repositório. Commit ou stash antes de fazer deploy."
    exit 1
}

# Confirma com usuário
Write-Host "🚀 Iniciando deploy para $REMOTE_USER@$REMOTE_HOST`:$REMOTE_PATH"
Write-Host "⚠️  Isso vai sobrescrever arquivos no servidor. Ctrl+C para cancelar..."
Start-Sleep -Seconds 5

# Sincroniza arquivos (requer rsync no Windows - instale via chocolatey ou use WSL)
# Se não tiver rsync, podemos usar scp ou robocopy+ssh
if (Get-Command "rsync" -ErrorAction SilentlyContinue) {
    Write-Host "📤 Sincronizando via rsync..."
    rsync -avz --delete --exclude-from="$EXCLUDE_FILE" `
        --exclude=".git/" --exclude="node_modules/" `
        --exclude="vendor/" --exclude=".env" `
        ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"
} else {
    # Fallback para scp se não tiver rsync
    Write-Host "📤 Sincronizando via scp (mais lento)..."
    scp -r * "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"
}

# Verifica resultado
if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Deploy concluído com sucesso!"
} else {
    Write-Error "❌ Erro no deploy. Verifique as mensagens acima."
    exit 1
}