[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string] $SqlFile,

    [Parameter(Mandatory = $true)]
    [string] $PhpCgiPath,

    [Parameter(Mandatory = $true)]
    [string] $MySqlClientPath,

    [string] $ProjectPath = (Split-Path -Parent $PSScriptRoot),
    [string] $MySqlHost = '127.0.0.1',
    [ValidateRange(1, 65535)]
    [int] $MySqlPort = 3306,
    [ValidateRange(1, 65535)]
    [int] $WebPort = 8040
)

$ErrorActionPreference = 'Stop'

function Assert-StandalonePath {
    param([string] $Path, [string] $Label)

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    if ($fullPath -match '(?i)(^|\\)xampp(\\|$)') {
        throw "$Label must be installed outside any XAMPP directory: $fullPath"
    }
}

function Invoke-MySql {
    param([string] $InputSql)

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $MySqlClientPath
    $startInfo.Arguments = "--defaults-extra-file=`"$script:MySqlOptionFile`" --host=`"$MySqlHost`" --port=$MySqlPort --user=root --batch --skip-column-names"
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw 'Could not start the standalone MySQL client.'
    }

    $stdoutTask = $process.StandardOutput.ReadToEndAsync()
    $stderrTask = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.Write($InputSql)
    $process.StandardInput.Close()
    $process.WaitForExit()
    $output = $stdoutTask.Result
    $errorOutput = $stderrTask.Result
    $exitCode = $process.ExitCode
    $process.Dispose()

    if ($exitCode -ne 0) {
        throw "MySQL command failed (exit code $exitCode): $errorOutput"
    }

    return $output.Trim()
}

function Set-EnvValue {
    param([string] $Key, [string] $Value)

    $pattern = '^\s*' + [regex]::Escape($Key) + '='
    $replacement = $Key + '=' + $Value
    $updated = $false
    $lines = foreach ($line in $script:EnvLines) {
        if ($line -match $pattern) {
            if (-not $updated) {
                $replacement
                $updated = $true
            }
        } else {
            $line
        }
    }

    if (-not $updated) {
        $lines += $replacement
    }

    $script:EnvLines = @($lines)
}

function Invoke-ProjectCommand {
    param([string] $Executable, [string[]] $Arguments)

    Push-Location $ProjectPath
    try {
        & $Executable @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed: $Executable $($Arguments -join ' ')"
        }
    } finally {
        Pop-Location
    }
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script from an elevated PowerShell window (Run as administrator).'
}

$ProjectPath = [System.IO.Path]::GetFullPath($ProjectPath)
$SqlFile = [System.IO.Path]::GetFullPath($SqlFile)
$PhpCgiPath = [System.IO.Path]::GetFullPath($PhpCgiPath)
$MySqlClientPath = [System.IO.Path]::GetFullPath($MySqlClientPath)
$PhpExePath = Join-Path (Split-Path -Parent $PhpCgiPath) 'php.exe'
$AppCmd = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'

if ($MySqlHost -notin @('127.0.0.1', 'localhost')) {
    throw 'MySqlHost must be 127.0.0.1 or localhost; this setup creates a local-only database account.'
}

foreach ($entry in @(
    @{ Path = $PhpCgiPath; Label = 'PHP CGI' },
    @{ Path = $MySqlClientPath; Label = 'MySQL client' }
)) {
    Assert-StandalonePath -Path $entry.Path -Label $entry.Label
    if (-not (Test-Path -LiteralPath $entry.Path -PathType Leaf)) {
        throw "$($entry.Label) was not found: $($entry.Path)"
    }
}

if (-not (Test-Path -LiteralPath $PhpExePath -PathType Leaf)) {
    throw "PHP CLI was not found beside php-cgi.exe: $PhpExePath"
}
if (-not (Test-Path -LiteralPath $AppCmd -PathType Leaf)) {
    throw 'IIS is not installed. Enable IIS and its CGI feature first.'
}
if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath 'artisan') -PathType Leaf)) {
    throw "Laravel artisan was not found under $ProjectPath"
}
if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath '.env') -PathType Leaf)) {
    throw 'The project .env file is missing. Create it from .env.example before continuing.'
}
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'Node.js and npm are required to build the production assets.'
}

Import-Module WebAdministration
if (-not (Get-WebGlobalModule | Where-Object { $_.Name -eq 'FastCgiModule' })) {
    throw 'IIS CGI/FastCGI is not enabled. Enable the IIS CGI feature, then rerun this script.'
}
if (-not (Get-WebGlobalModule | Where-Object { $_.Name -eq 'RewriteModule' })) {
    throw 'IIS URL Rewrite is not installed. Install the Microsoft IIS URL Rewrite module, then rerun this script.'
}

$publicPath = [System.IO.Path]::GetFullPath((Join-Path $ProjectPath 'public')).TrimEnd('\')
$websites = @(Get-Website)
$matchingSites = @($websites | Where-Object {
    $physicalPath = [Environment]::ExpandEnvironmentVariables([string]$_.PhysicalPath)
    [System.IO.Path]::GetFullPath($physicalPath).TrimEnd('\') -ieq $publicPath
})
if ($matchingSites.Count -gt 1) {
    throw "More than one IIS site points to $publicPath. Review the IIS bindings before continuing."
}
if ($matchingSites.Count -eq 1) {
    $SiteName = $matchingSites[0].Name
} else {
    $SiteName = 'MRI'
    if ($websites | Where-Object { $_.Name -eq $SiteName }) {
        throw "An IIS website named '$SiteName' already points to a different directory."
    }
}

$portOwners = @(
    foreach ($website in $websites) {
        foreach ($binding in @(Get-WebBinding -Name $website.Name)) {
            if ($binding.bindingInformation -match (':{0}:' -f $WebPort)) {
                $website.Name
            }
        }
    }
) | Select-Object -Unique
if ($portOwners.Count -gt 0 -and ($portOwners.Count -ne 1 -or $portOwners[0] -ne $SiteName)) {
    throw "IIS port $WebPort belongs to another site: $($portOwners -join ', '). That binding was left unchanged."
}
if ($portOwners.Count -eq 0 -and (Get-NetTCPConnection -State Listen -LocalPort $WebPort -ErrorAction SilentlyContinue)) {
    throw "Another process is already listening on port $WebPort."
}

$appPoolName = 'MRI-Standalone'
$appPoolPath = "IIS:\AppPools\$appPoolName"
$otherPoolUsers = @($websites | Where-Object {
    $_.ApplicationPool -eq $appPoolName -and $_.Name -ne $SiteName
})
if ($otherPoolUsers.Count -gt 0) {
    throw "IIS application pool '$appPoolName' is used by another site. Review it before continuing."
}

$phpModules = & $PhpExePath -m
if ($LASTEXITCODE -ne 0 -or $phpModules -notcontains 'pdo_mysql') {
    throw 'The standalone PHP installation must have the pdo_mysql extension enabled.'
}
Invoke-ProjectCommand -Executable $PhpExePath -Arguments @('composer.phar', 'check-platform-reqs', '--no-dev')

Invoke-ProjectCommand -Executable 'npm' -Arguments @('run', 'build')

$rootPasswordSecure = Read-Host 'Enter the MySQL administrator password' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPasswordSecure)
$rootPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
$tempDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('mri-iis-' + [guid]::NewGuid().ToString('N'))
$script:MySqlOptionFile = Join-Path $tempDirectory 'mysql.cnf'
$script:EnvLines = @()
$envPath = Join-Path $ProjectPath '.env'
$envChanged = $false
$oldEnv = [System.IO.File]::ReadAllText($envPath)

try {
    New-Item -ItemType Directory -Path $tempDirectory | Out-Null
    $acl = Get-Acl $tempDirectory
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetAccessRule((New-Object Security.AccessControl.FileSystemAccessRule(
        $identity.Name,
        [Security.AccessControl.FileSystemRights]::FullControl,
        [Security.AccessControl.AccessControlType]::Allow
    )))
    Set-Acl -LiteralPath $tempDirectory -AclObject $acl

    $escapedPassword = $rootPassword.Replace('\', '\\').Replace('"', '\"').Replace('#', '\#')
    $optionText = "[client]`r`nuser=root`r`npassword=`"$escapedPassword`"`r`nhost=$MySqlHost`r`nport=$MySqlPort`r`n"
    [System.IO.File]::WriteAllBytes($script:MySqlOptionFile, (New-Object System.Text.UTF8Encoding($false)).GetBytes($optionText))

    $schemaExists = Invoke-MySql "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'mri';"
    if ($schemaExists -ne '0') {
        throw "The MySQL database 'mri' already exists. The supplied dump contains DROP TABLE statements; the script will not import over an existing database."
    }
    $accountExists = Invoke-MySql "SELECT COUNT(*) FROM mysql.user WHERE User = 'mri_app';"
    if ($accountExists -ne '0') {
        throw "The MySQL account 'mri_app' already exists. Review the account before continuing."
    }

    $randomBytes = New-Object byte[] 32
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $random.GetBytes($randomBytes)
    } finally {
        $random.Dispose()
    }
    $appPassword = [BitConverter]::ToString($randomBytes).Replace('-', '').ToLowerInvariant()

    $provisionSql = @"
CREATE DATABASE `mri` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mri_app'@'localhost' IDENTIFIED BY '$appPassword';
CREATE USER 'mri_app'@'127.0.0.1' IDENTIFIED BY '$appPassword';
GRANT ALL PRIVILEGES ON `mri`.* TO 'mri_app'@'localhost';
GRANT ALL PRIVILEGES ON `mri`.* TO 'mri_app'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    Invoke-MySql $provisionSql | Out-Null

    $dump = [System.IO.File]::ReadAllText($SqlFile, [System.Text.Encoding]::UTF8)
    Invoke-MySql ($dump + "`r`n") | Out-Null

    $requiredTables = Invoke-MySql "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'mri' AND TABLE_NAME IN ('users','bookings','time_slots','sessions','cache','jobs');"
    if ($requiredTables -ne '6') {
        throw "The database import completed without all expected MRI tables (found $requiredTables of 6). Check the SQL dump and MySQL error log."
    }

    $script:EnvLines = @([System.IO.File]::ReadAllLines($envPath, [System.Text.Encoding]::UTF8))
    Set-EnvValue 'APP_ENV' 'production'
    Set-EnvValue 'APP_DEBUG' 'false'
    Set-EnvValue 'APP_URL' "http://127.0.0.1:$WebPort"
    Set-EnvValue 'DB_CONNECTION' 'mysql'
    Set-EnvValue 'DB_HOST' '127.0.0.1'
    Set-EnvValue 'DB_PORT' "$MySqlPort"
    Set-EnvValue 'DB_DATABASE' 'mri'
    Set-EnvValue 'DB_USERNAME' 'mri_app'
    Set-EnvValue 'DB_PASSWORD' $appPassword
    [System.IO.File]::WriteAllLines($envPath, $script:EnvLines, (New-Object System.Text.UTF8Encoding($false)))
    $envChanged = $true

    $keyLine = $script:EnvLines | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
    if (-not $keyLine -or $keyLine -eq 'APP_KEY=') {
        Invoke-ProjectCommand -Executable $PhpExePath -Arguments @('artisan', 'key:generate', '--force')
    }
    Invoke-ProjectCommand -Executable $PhpExePath -Arguments @('artisan', 'optimize:clear')
    Invoke-ProjectCommand -Executable $PhpExePath -Arguments @('artisan', 'config:cache')

    $fastCgiConfig = & $AppCmd list config /section:system.webServer/fastCgi 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "IIS FastCGI is unavailable: $fastCgiConfig"
    }
    if (($fastCgiConfig -join "`n") -notlike "*$PhpCgiPath*") {
        & $AppCmd set config /section:system.webServer/fastCgi "/+[fullPath='$PhpCgiPath']" /commit:apphost | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw 'Could not register the standalone PHP FastCGI executable with IIS.'
        }
    }

    if (-not (Test-Path $appPoolPath)) {
        New-WebAppPool -Name $appPoolName | Out-Null
    }
    Set-ItemProperty $appPoolPath -Name managedRuntimeVersion -Value ''

    if ($matchingSites.Count -eq 0) {
        New-Website -Name $SiteName -PhysicalPath $publicPath -IPAddress '127.0.0.1' -Port $WebPort -ApplicationPool $appPoolName | Out-Null
    } else {
        Set-ItemProperty "IIS:\Sites\$SiteName" -Name applicationPool -Value $appPoolName
        $siteHasPort = @(Get-WebBinding -Name $SiteName | Where-Object { $_.bindingInformation -match (':{0}:' -f $WebPort) }).Count -gt 0
        if (-not $siteHasPort) {
            New-WebBinding -Name $SiteName -Protocol http -IPAddress '127.0.0.1' -Port $WebPort | Out-Null
        }
    }

    Add-WebConfigurationProperty -PSPath 'MACHINE/WEBROOT/APPHOST' -Location $SiteName -Filter 'system.webServer/handlers' -Name '.' -AtIndex 0 -Value @{
        name = 'MRI_PHP_FastCGI'
        path = '*.php'
        verb = '*'
        modules = 'FastCgiModule'
        scriptProcessor = $PhpCgiPath
        resourceType = 'Either'
    }

    $appPoolIdentity = "IIS AppPool\$appPoolName"
    & icacls.exe $ProjectPath /grant "${appPoolIdentity}:(OI)(CI)RX" /T /C | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not grant the IIS application pool read access to the project.'
    }
    foreach ($writablePath in @('storage', 'bootstrap\cache')) {
        & icacls.exe (Join-Path $ProjectPath $writablePath) /grant "${appPoolIdentity}:(OI)(CI)M" /T /C | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "Could not grant the IIS application pool write access to $writablePath."
        }
    }

    $response = Invoke-WebRequest -Uri "http://127.0.0.1:$WebPort/up" -UseBasicParsing -TimeoutSec 30
    if ($response.StatusCode -ne 200) {
        throw "IIS health check returned HTTP $($response.StatusCode)."
    }

    Write-Host "MRI is available at http://127.0.0.1:$WebPort (IIS site '$SiteName')"
    Write-Host 'The XAMPP installation was not stopped or removed. Verify the new site and back up any other XAMPP databases before uninstalling it.'
} catch {
    if ($envChanged) {
        [System.IO.File]::WriteAllText($envPath, $oldEnv, (New-Object System.Text.UTF8Encoding($false)))
        $configCache = Join-Path $ProjectPath 'bootstrap\cache\config.php'
        Remove-Item -LiteralPath $configCache -Force -ErrorAction SilentlyContinue
        Write-Warning 'The original .env was restored. A newly created MySQL database may remain; inspect it before retrying.'
    }
    throw
} finally {
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    $rootPassword = $null
    $appPassword = $null
    $rootPasswordSecure = $null
    if (Test-Path -LiteralPath $tempDirectory) {
        Remove-Item -LiteralPath $tempDirectory -Recurse -Force
    }
}
