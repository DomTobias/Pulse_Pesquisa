# Instala o sincronizador Pulse como serviço do Windows usando o NSSM.
# Execute em um PowerShell como Administrador, dentro da pasta do sincronizador:
#   .\instalar-servico.ps1 -Nssm "C:\nssm\win64\nssm.exe"
#
# Por padrão o serviço roda como "NT AUTHORITY\LocalService" (sem privilégios de administrador), e não como
# LocalSystem. Para usar uma conta de serviço do domínio ou local:
#   .\instalar-servico.ps1 -Nssm "C:\nssm\win64\nssm.exe" -Conta "DOMINIO\svc_pulse" -Senha (Read-Host -AsSecureString)
#
# O Python precisa estar instalado "para todos os usuários" (ex.: C:\Program Files\Python313), senão a conta do
# serviço não consegue executá-lo. Instale a pasta do Pulse fora do perfil de usuário (ex.: C:\Pulse).
param(
  [string]$Nssm = "nssm.exe",
  [string]$Servico = "PulseSincronizador",
  [string]$Python = "",
  [string]$Conta = "NT AUTHORITY\LocalService",
  [SecureString]$Senha
)
$ErrorActionPreference = "Stop"
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path

if (-not (Test-Path "$pasta\.env")) { throw "Crie o arquivo .env a partir do .env.example antes de instalar." }
if ($pasta -like "$env:USERPROFILE*") {
  Write-Warning "A pasta está dentro do perfil de usuário ($pasta). Em servidor, use algo como C:\Pulse\sincronizador."
}

if (-not $Python) {
  if (Test-Path "$pasta\.venv\Scripts\python.exe") { $Python = "$pasta\.venv\Scripts\python.exe" }
  else { $Python = (Get-Command python).Source }
}

& $Python "$pasta\main.py" --verificar
if ($LASTEXITCODE -ne 0) { throw "A verificação de conexões falhou. Corrija o .env e tente de novo." }

# Conta do serviço e principal usado nas permissões (SIDs fixos: funcionam em Windows em qualquer idioma)
$localService = $Conta -match '^(NT AUTHORITY\\)?LocalService$'
$principal = if ($localService) { "*S-1-5-19" } else { $Conta }

# .env: só Administradores, SYSTEM e a conta do serviço (remove a herança da pasta)
& icacls "$pasta\.env" /inheritance:r /grant:r "*S-1-5-32-544:(F)" "*S-1-5-18:(F)" "${principal}:(R)" | Out-Null
# Código: leitura. Logs: gravação. Pasta de imagens do gerenciador: leitura.
& icacls "$pasta" /grant "${principal}:(OI)(CI)(RX)" | Out-Null
New-Item -ItemType Directory -Force "$pasta\logs" | Out-Null
& icacls "$pasta\logs" /grant "${principal}:(OI)(CI)(M)" | Out-Null
$uploads = Join-Path $pasta "..\gerenciador\storage\uploads"
if (Test-Path $uploads) { & icacls (Resolve-Path $uploads) /grant "${principal}:(OI)(CI)(RX)" | Out-Null }

& $Nssm install $Servico $Python "`"$pasta\main.py`""
& $Nssm set $Servico AppDirectory $pasta
& $Nssm set $Servico DisplayName "Pulse - Sincronizador"
& $Nssm set $Servico Description "Sincroniza respostas (Supabase -> MySQL) e publicações (MySQL -> Supabase) do Pulse."
& $Nssm set $Servico Start SERVICE_AUTO_START
if ($localService) {
  & $Nssm set $Servico ObjectName "NT AUTHORITY\LocalService" ""
} else {
  if (-not $Senha) { throw "Informe -Senha para a conta $Conta." }
  $texto = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($Senha))
  & $Nssm set $Servico ObjectName $Conta $texto
  $texto = $null
}
& $Nssm set $Servico AppStopMethodConsole 15000
& $Nssm set $Servico AppExit Default Restart
& $Nssm set $Servico AppRestartDelay 10000
& $Nssm set $Servico AppStdout "$pasta\logs\servico-stdout.log"
& $Nssm set $Servico AppStderr "$pasta\logs\servico-stderr.log"
& $Nssm set $Servico AppRotateFiles 1
& $Nssm set $Servico AppRotateOnline 1
& $Nssm set $Servico AppRotateBytes 5242880
& $Nssm start $Servico
Write-Host "Serviço $Servico instalado e iniciado como $Conta."
