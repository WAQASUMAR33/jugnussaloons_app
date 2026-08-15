# PowerShell script to prepare and package Laravel project for Hostinger deployment

Write-Host "=== 1. Compiling Frontend Assets with Vite ===" -ForegroundColor Cyan
npm run build

if (-not (Test-Path "public\build")) {
    Write-Error "Vite build failed! 'public\build' directory not found."
    exit 1
}

Write-Host "=== 2. Verifying Core Production Files ===" -ForegroundColor Cyan
$requiredFiles = @("public\index.php", "public\.htaccess", ".htaccess", "index.php", "artisan", "vendor\autoload.php")
foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        Write-Error "Missing required file/directory: $file"
        exit 1
    }
}
Write-Host "All required core files verified!" -ForegroundColor Green


Write-Host "=== 3. Creating 'deploy' Folder, 'saloon.zip', and 'hostinger_deploy.zip' Package ===" -ForegroundColor Cyan
$deployDir = Join-Path (Get-Location) "deploy"
$saloonZip = Join-Path (Get-Location) "saloon.zip"
$hostingerZip = Join-Path (Get-Location) "hostinger_deploy.zip"

if (Test-Path $deployDir) {
    Remove-Item $deployDir -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Path $deployDir -Force | Out-Null

$filesToCopy = Get-ChildItem -Path . -Exclude "node_modules", ".git", ".env", ".phpunit.cache", "hostinger_deploy.zip", "saloon.zip", "prepare_hostinger_deployment.ps1", "deploy"

foreach ($item in $filesToCopy) {
    Copy-Item -Path $item.FullName -Destination $deployDir -Recurse -Force
}

# Remove existing zip files
if (Test-Path $saloonZip) { Remove-Item $saloonZip -Force -ErrorAction SilentlyContinue }
if (Test-Path $hostingerZip) { Remove-Item $hostingerZip -Force -ErrorAction SilentlyContinue }

# Use System.IO.Compression.ZipFile for fast, reliable zip packaging
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($deployDir, $saloonZip)
[System.IO.Compression.ZipFile]::CreateFromDirectory($deployDir, $hostingerZip)

if ((Test-Path $saloonZip) -and (Test-Path $hostingerZip)) {
    $saloonSize = (Get-Item $saloonZip).Length / 1MB
    $hostingerSize = (Get-Item $hostingerZip).Length / 1MB
    Write-Host ("=== Hostinger Deployment Packages Created Successfully! ===") -ForegroundColor Green
    Write-Host ("Deploy Folder: d:\saloon_app\deploy") -ForegroundColor Yellow
    Write-Host ("saloon.zip Package: ({0:N2} MB)" -f $saloonSize) -ForegroundColor Yellow
    Write-Host ("hostinger_deploy.zip Package: ({0:N2} MB)" -f $hostingerSize) -ForegroundColor Yellow
} else {
    Write-Error "Failed to create deployment zip packages."
    exit 1
}

