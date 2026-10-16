[CmdletBinding()]
param(
	[string]$Version = '',
	[string]$Destination = ''
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$header = Get-Content -LiteralPath (Join-Path $pluginRoot 'mrn-form.php') -Raw
$declaredVersion = [regex]::Match($header, 'Version:\s*([0-9.]+)').Groups[1].Value
if (-not $Version) { $Version = $declaredVersion }
if ($Version -ne $declaredVersion) { throw 'Package version must match the plugin header.' }
$readme = Get-Content -LiteralPath (Join-Path $pluginRoot 'readme.txt') -Raw
if ([regex]::Match($readme, 'Stable tag:\s*([0-9.]+)').Groups[1].Value -ne $Version) {
	throw 'Stable tag must match the plugin version.'
}
if ([regex]::Match($header, "define\( 'MRNF_VERSION', '([0-9.]+)' \)").Groups[1].Value -ne $Version) {
	throw 'Runtime version must match the plugin header.'
}
if (-not $Destination) {
	$Destination = Join-Path (Split-Path -Parent $pluginRoot) "dist/mrn-forms-$Version.zip"
}
$destination = [System.IO.Path]::GetFullPath($Destination)
New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
$staging = Join-Path ([System.IO.Path]::GetTempPath()) ("mrnf-" + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $staging 'mrn-forms'

try {
	New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null
	# Explicit runtime allowlist excludes development files and credentials.
	foreach ($item in @('mrn-form.php', 'readme.txt', 'LICENSE', 'uninstall.php', 'includes', 'assets')) {
		Copy-Item -LiteralPath (Join-Path $pluginRoot $item) -Destination $packageRoot -Recurse -Force
	}

	Add-Type -AssemblyName System.IO.Compression
	Add-Type -AssemblyName System.IO.Compression.FileSystem
	if (Test-Path -LiteralPath $destination) {
		Remove-Item -LiteralPath $destination -Force
	}

	$archiveStream = [System.IO.File]::Open(
		$destination,
		[System.IO.FileMode]::CreateNew,
		[System.IO.FileAccess]::ReadWrite,
		[System.IO.FileShare]::None
	)
	try {
		$archive = [System.IO.Compression.ZipArchive]::new(
			$archiveStream,
			[System.IO.Compression.ZipArchiveMode]::Create,
			$false
		)
		try {
			Get-ChildItem -LiteralPath $packageRoot -File -Recurse | ForEach-Object {
				$relativePath = $_.FullName.Substring($staging.Length).TrimStart(
					[System.IO.Path]::DirectorySeparatorChar,
					[System.IO.Path]::AltDirectorySeparatorChar
				)
				$entryName = $relativePath.Replace('\', '/')
				[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
					$archive,
					$_.FullName,
					$entryName,
					[System.IO.Compression.CompressionLevel]::Optimal
				) | Out-Null
			}
		}
		finally {
			$archive.Dispose()
		}
	}
	finally {
		$archiveStream.Dispose()
	}

	Write-Host "Created $destination" -ForegroundColor Green
}
finally {
	if (Test-Path -LiteralPath $staging) {
		$resolvedStaging = [System.IO.Path]::GetFullPath($staging)
		$expectedParent = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd('\', '/')
		$actualParent = (Split-Path -Parent $resolvedStaging).TrimEnd('\', '/')
		if (-not $actualParent.Equals($expectedParent, [System.StringComparison]::OrdinalIgnoreCase) -or
			(Split-Path -Leaf $resolvedStaging) -notmatch '^mrnf-[a-f0-9]{32}$') {
			throw 'Refusing to remove an unexpected staging directory.'
		}
		Remove-Item -LiteralPath $staging -Recurse -Force
	}
}
