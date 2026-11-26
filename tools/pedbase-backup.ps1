[CmdletBinding(DefaultParameterSetName = 'Create')]
param(
    [Parameter(Mandatory)][string]$Archive,
    [Parameter(Mandatory)][string]$Certificate,
    [Parameter(Mandatory)][string]$WorkRoot,
    [string]$OpenSsl = 'openssl',
    [Parameter(Mandatory, ParameterSetName = 'Create')][string]$SqlDump,
    [Parameter(Mandatory, ParameterSetName = 'Create')][string]$Documents,
    [Parameter(Mandatory, ParameterSetName = 'Restore')][string]$PrivateKey,
    [Parameter(Mandatory, ParameterSetName = 'Restore')][ValidatePattern('^[0-9a-fA-F]{64}$')][string]$ExpectedSha256,
    [Parameter(Mandatory, ParameterSetName = 'Restore')][string]$Destination
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Invoke-OpenSsl([string[]]$Arguments) {
    & $OpenSsl @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw 'Archive cryptographic verification failed.'
    }
}

function Get-FileManifest([string]$Directory) {
    Get-ChildItem -LiteralPath $Directory -Recurse -Force -File |
        Where-Object { $_.FullName -ne (Join-Path $Directory 'manifest.json') } |
        Sort-Object FullName |
        ForEach-Object {
            [pscustomobject][ordered]@{
                path = $_.FullName.Substring($Directory.Length + 1).Replace('\', '/')
                sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
            }
        }
}

$certificatePath = (Resolve-Path -LiteralPath $Certificate).ProviderPath
$archivePath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($Archive)
$root = (Resolve-Path -LiteralPath $WorkRoot).ProviderPath
$workName = 'pedbase-backup-' + [guid]::NewGuid().ToString('N')
$work = Join-Path $root $workName
$null = New-Item -ItemType Directory -Path $work
$zip = Join-Path $work 'payload.zip'
$payload = Join-Path $work 'payload'
$encrypted = Join-Path $work 'payload.cms'

try {
    if ($PSCmdlet.ParameterSetName -eq 'Create') {
        $documentsPath = (Resolve-Path -LiteralPath $Documents).ProviderPath
        $source = Get-Item -LiteralPath $documentsPath -Force
        if (!$source.PSIsContainer -or ($source.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
            throw 'Documents must be a regular private directory.'
        }
        $pdfRoot = Join-Path $payload 'teacher-documents'
        $null = New-Item -ItemType Directory -Path $pdfRoot -Force
        [IO.File]::Copy((Resolve-Path -LiteralPath $SqlDump).ProviderPath, (Join-Path $payload 'database.sql'))
        foreach ($item in Get-ChildItem -LiteralPath $documentsPath -Recurse -Force) {
            if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
                throw 'Document directory contains a reparse point.'
            }
            if (!$item.PSIsContainer -and $item.Extension -ieq '.pdf') {
                $target = Join-Path $pdfRoot $item.FullName.Substring($documentsPath.TrimEnd('\').Length + 1)
                $null = [IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($target))
                [IO.File]::Copy($item.FullName, $target)
            }
        }
        ConvertTo-Json -InputObject @(Get-FileManifest $payload) |
            Set-Content -LiteralPath (Join-Path $payload 'manifest.json') -Encoding UTF8
        [IO.Compression.ZipFile]::CreateFromDirectory($payload, $zip)
        Invoke-OpenSsl @('cms', '-encrypt', '-binary', '-aes-256-gcm', '-in', $zip,
            '-outform', 'DER', '-out', $encrypted, '-recip', $certificatePath,
            '-keyopt', 'rsa_padding_mode:oaep', '-keyopt', 'rsa_oaep_md:sha256')
        [IO.File]::Move($encrypted, $archivePath)
        Get-FileHash -LiteralPath $archivePath -Algorithm SHA256
    } else {
        $destinationPath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($Destination)
        $privateKeyPath = (Resolve-Path -LiteralPath $PrivateKey).ProviderPath
        [IO.File]::Copy($archivePath, $encrypted)
        if ((Get-FileHash -LiteralPath $encrypted -Algorithm SHA256).Hash -ne $ExpectedSha256) {
            throw 'Archive SHA-256 does not match the trusted manifest.'
        }
        Invoke-OpenSsl @('cms', '-decrypt', '-binary', '-inform', 'DER', '-in', $encrypted,
            '-recip', $certificatePath, '-inkey', $privateKeyPath, '-out', $zip)
        [IO.Compression.ZipFile]::ExtractToDirectory($zip, $payload)
        $expected = Get-Content -LiteralPath (Join-Path $payload 'manifest.json') -Raw | ConvertFrom-Json
        $actual = @(Get-FileManifest $payload)
        if (!(Test-Path -LiteralPath (Join-Path $payload 'database.sql') -PathType Leaf) -or
            (Compare-Object @($expected) $actual -Property path, sha256)) {
            throw 'Restored files do not match the archive manifest.'
        }
        [IO.Directory]::Move($payload, $destinationPath)
    }
} finally {
    $resolved = (Resolve-Path -LiteralPath $work).ProviderPath
    if ($resolved -eq (Join-Path $root $workName) -and $resolved -eq $work) {
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
