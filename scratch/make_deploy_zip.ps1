# Script to bundle production deployment package for Hostinger

$sourceDir = "d:\saloon_app"
$zipPath = "d:\saloon_app\saloon_app_deploy.zip"
$tempDir = "d:\saloon_app\scratch\deploy_temp"

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }

New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

$itemsToCopy = @(
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "storage",
    "vendor",
    "artisan",
    "composer.json",
    "composer.lock",
    ".htaccess"
)

foreach ($item in $itemsToCopy) {
    $src = Join-Path $sourceDir $item
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $tempDir -Recurse -Force
    }
}

# Copy .env.production as .env into deploy_temp
Copy-Item -Path (Join-Path $sourceDir ".env.production") -Destination (Join-Path $tempDir ".env") -Force

Write-Host "Creating ZIP archive at $zipPath..."
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipPath -Force

Remove-Item $tempDir -Recurse -Force
Write-Host "DEPLOY_ZIP_CREATED_SUCCESSFULLY"
