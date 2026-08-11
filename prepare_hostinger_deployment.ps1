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

Write-Host "=== 3. Creating hostinger_deploy.zip Package ===" -ForegroundColor Cyan
$destination = Join-Path (Get-Location) "hostinger_deploy.zip"
$stagingDir = Join-Path (Get-Location) "scratch\staging"

if (Test-Path $stagingDir) {
    Remove-Item $stagingDir -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null

$filesToCopy = Get-ChildItem -Path . -Exclude "node_modules", ".git", ".env", "scratch", "tests", ".phpunit.cache", "hostinger_deploy.zip", "hostinger_deploy_latest.zip", "prepare_hostinger_deployment.ps1"

foreach ($item in $filesToCopy) {
    Copy-Item -Path $item.FullName -Destination $stagingDir -Recurse -Force
}

if (Test-Path $destination) {
    Remove-Item $destination -Force -ErrorAction SilentlyContinue
}

# Use tar.exe to force POSIX forward slash '/' directory entries for Linux/Hostinger
tar -acf $destination -C $stagingDir .

Remove-Item $stagingDir -Recurse -Force -ErrorAction SilentlyContinue



if (Test-Path $destination) {
    $zipSize = (Get-Item $destination).Length / 1MB
    Write-Host ("=== Hostinger Deployment Package Created Successfully! ===") -ForegroundColor Green
    Write-Host ("Package: hostinger_deploy.zip ({0:N2} MB)" -f $zipSize) -ForegroundColor Yellow
} else {
    Write-Error "Failed to create deployment zip."
    exit 1
}
