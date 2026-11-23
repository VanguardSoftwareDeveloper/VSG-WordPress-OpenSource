#Requires -Version 7.0
<#
Consolidate PeruCommerce into VSG-WordPress-OpenSource.
Delete ONLY Vanguard-WordPress-Plugins after all published Git blobs match.
Never delete VSG-WordPress or the local backup checkout.
Usage: pwsh -NoProfile -File .\tools\consolidate-perucommerce.ps1 -DeletePrivate
#>
[CmdletBinding()]
param([switch]$DeletePrivate)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$privateRepo = 'VanguardSoftwareDeveloper/Vanguard-WordPress-Plugins'
$publicRepo = 'VanguardSoftwareDeveloper/VSG-WordPress-OpenSource'
$privateDir = Join-Path $HOME 'Vanguard-WordPress-Plugins'
$publicDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$peruPrefix = 'plugins/wp-peru-commerce/'
$extraDocs = @(
  'docs/CRITERIOS-DE-PRODUCTO.md',
  'docs/MONETIZACION.md',
  'docs/PORTAFOLIO-COMERCIALES.md',
  'docs/PORTAFOLIO-GRATUITOS.md',
  'docs/PORTAFOLIO-Y-SEPARACION.md',
  'shared/README.md'
)
function Check([string]$Message) {
  if ($LASTEXITCODE -ne 0) { throw "$Message (exit $LASTEXITCODE)" }
}
function GithubJson([string]$Endpoint) {
  $raw = & gh api $Endpoint
  Check "GitHub API: $Endpoint"
  return (($raw | Out-String) | ConvertFrom-Json)
}
function CheckRepo([string]$Folder, [string]$FullName) {
  if (-not (Test-Path -LiteralPath (Join-Path $Folder '.git'))) { throw "Git clone missing: $Folder" }
  $url = (& git -C $Folder remote get-url origin | Out-String).Trim()
  Check 'git origin'
  $rx = '(?i)github\.com[:/]' + [regex]::Escape($FullName) + '(?:\.git)?/?$'
  if ($url -notmatch $rx) { throw "Wrong Git origin: $url" }
  $branch = (& git -C $Folder branch --show-current | Out-String).Trim()
  Check 'git branch'
  if ($branch -cne 'main') { throw "Source branch must be main: $Folder" }
  $dirty = (& git -C $Folder status --porcelain | Out-String).Trim()
  Check 'git status'
  if ($dirty) { throw "Working tree not clean: $Folder" }
  & git -C $Folder fetch --quiet origin main
  Check 'git fetch'
  $head = (& git -C $Folder rev-parse HEAD | Out-String).Trim()
  Check 'git rev-parse HEAD'
  $remote = (& git -C $Folder rev-parse refs/remotes/origin/main | Out-String).Trim()
  Check 'git rev-parse origin/main'
  if ($head -cne $remote) { throw "Checkout not synchronized: $Folder" }
}
foreach ($command in @('git','gh')) {
  if (-not (Get-Command $command -ErrorAction SilentlyContinue)) { throw "Missing: $command" }
}
CheckRepo $privateDir $privateRepo
CheckRepo $publicDir $publicRepo
$sourceCommit = (& git -C $privateDir rev-parse HEAD | Out-String).Trim()
Check 'Private source commit'
$login = (& gh api user --jq '.login' | Out-String).Trim()
Check 'gh api user'
if ($login -cne 'VanguardSoftwareDeveloper') { throw "Unexpected GitHub account: $login" }
$accountId = (& gh api user --jq '.id' | Out-String).Trim()
Check 'gh api user id'
if ($accountId -notmatch '^\d+$') { throw 'Invalid GitHub user id' }
$privateVisibility = (& gh repo view $privateRepo --json visibility --jq '.visibility' | Out-String).Trim()
Check 'Private repository'
$publicVisibility = (& gh repo view $publicRepo --json visibility --jq '.visibility' | Out-String).Trim()
Check 'Public repository'
if ($privateVisibility -cne 'PRIVATE' -or $publicVisibility -cne 'PUBLIC') {
  throw 'Unexpected repository visibility. Nothing modified.'
}

$sourceTree = GithubJson "repos/$privateRepo/git/trees/main?recursive=1"
if ($sourceTree.truncated) { throw 'Incomplete source tree' }
$peruFiles = @($sourceTree.tree | Where-Object {
  $_.type -eq 'blob' -and $_.path.StartsWith($peruPrefix, [StringComparison]::Ordinal)
})
if ($peruFiles.Count -ne 297) {
  throw "Expected 297 files in PeruCommerce; found $($peruFiles.Count). Nothing modified."
}
$sourceSha = @{}
foreach ($file in $peruFiles) {
  if ($file.mode -notin @('100644','100755')) { throw "Unexpected mode for $($file.path)" }
  $sourceSha[$file.path] = $file.sha
}
foreach ($rel in $extraDocs) {
  $found = @($sourceTree.tree | Where-Object { $_.type -eq 'blob' -and $_.path -ceq $rel })
  if ($found.Count -ne 1) { throw "Missing required document: $rel" }
  $sourceSha[$rel] = $found[0].sha
}
$paths = @($sourceSha.Keys | Sort-Object)
$secretPatterns = [ordered]@{
  'private-key' = '-----BEGIN (?:[A-Z0-9 ]+ )?PRIVATE KEY-----'
  'github-token' = '(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{30,})'
  'openai-token' = 'sk-(?:proj-|svcacct-)[A-Za-z0-9_-]{20,}'
  'aws-access-key' = '\b(?:AKIA|ASIA)[0-9A-Z]{16}\b'
  'slack-token' = '\bxox[baprs]-[A-Za-z0-9-]{16,}\b'
  'stripe-live-key' = '\bsk_live_[A-Za-z0-9]{16,}\b'
  'mercadopago-live-token' = '\bAPP_USR-[0-9]{8,}-[0-9]{5,}-[A-Za-z0-9]{8,}\b'
}
foreach ($rel in $paths) {
  if ($rel -match '(^|/)(?:\.env(?:\..*)?|id_rsa|id_ed25519|.*\.(?:pem|p12|pfx|key)|secrets?\.json)$') {
    throw "Potential secret path: $rel"
  }
  if ($rel.Contains('..') -or $rel.StartsWith('/')) { throw "Unsafe path: $rel" }
  $source = Join-Path $privateDir ($rel.Replace('/', [IO.Path]::DirectorySeparatorChar))
  if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Missing source file: $rel" }
  if ((Get-Item -LiteralPath $source -Force).LinkType) { throw "Symlink rejected: $rel" }
  $content = [IO.File]::ReadAllText($source, [Text.Encoding]::UTF8)
  foreach ($p in $secretPatterns.Keys) {
    if ([regex]::IsMatch($content, $secretPatterns[$p])) {
      throw "Potential secret ($p) in $rel. No upload/deletion was performed."
    }
  }
}
Write-Host "Inspected $($peruFiles.Count) PeruCommerce files and $($extraDocs.Count) extra documents."

foreach ($rel in $paths) {
  $from = Join-Path $privateDir ($rel.Replace('/', [IO.Path]::DirectorySeparatorChar))
  $to = Join-Path $publicDir ($rel.Replace('/', [IO.Path]::DirectorySeparatorChar))
  $parent = Split-Path -Path $to -Parent
  if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
  Copy-Item -LiteralPath $from -Destination $to -Force
  if ((Get-FileHash -LiteralPath $from -Algorithm SHA256).Hash -cne
      (Get-FileHash -LiteralPath $to -Algorithm SHA256).Hash) {
    throw "Copy hash mismatch: $rel"
  }
}
$publicReadmePath = Join-Path $publicDir 'README.md'
$publicReadme = [IO.File]::ReadAllText($publicReadmePath)
$previousIntro = 'PeruCommerce VSG is developed separately, with a planned POS and pending final verification.'
$previousDisclosure = '**No PeruCommerce PHP/Angular source, internal contracts or private history are included here.**'
if (-not $publicReadme.Contains($previousIntro) -or -not $publicReadme.Contains($previousDisclosure)) {
  throw 'Public README has changed. Manual reconciliation required; private repo kept.'
}
$publicReadme = $publicReadme.Replace('# VSG WordPress — open-source planning catalog',
  '# VSG WordPress — open-source catalog and PeruCommerce development')
$publicReadme = $publicReadme.Replace($previousIntro,
  'PeruCommerce VSG PHP/Angular source is now in [plugins/wp-peru-commerce](plugins/wp-peru-commerce/README.md). POS and final verification are still pending.')
$publicReadme = $publicReadme.Replace($previousDisclosure,
  '**Development source is published as untested work in progress, not an installable release. The former private Git history was not copied.**')
$publicReadme += [Environment]::NewLine + '[Consolidation status](docs/SOURCE-CONSOLIDATION.md).' + [Environment]::NewLine
[IO.File]::WriteAllText($publicReadmePath, $publicReadme, [Text.UTF8Encoding]::new($false))

$releasePath = Join-Path $publicDir 'docs/RELEASE-STATUS.md'
$release = [IO.File]::ReadAllText($releasePath)
$oldRelease = '- PeruCommerce VSG: technical source is kept separately; its POS and validation remain incomplete.'
if (-not $release.Contains($oldRelease)) { throw 'Release status changed; private repo kept.' }
$release = $release.Replace($oldRelease,
  '- PeruCommerce VSG: PHP/Angular development source is present in plugins/wp-peru-commerce, but POS, build, installation and release validation remain incomplete.')
[IO.File]::WriteAllText($releasePath, $release, [Text.UTF8Encoding]::new($false))

$impactPath = Join-Path $publicDir 'docs/OPEN-SOURCE-IMPACT.md'
$impact = [IO.File]::ReadAllText($impactPath)
$wasPrivate = 'which is private, statically reviewed and not yet validated as a working release;'
if (-not $impact.Contains($wasPrivate)) { throw 'Impact document changed; private repo kept.' }
$impact = $impact.Replace($wasPrivate,
  'whose source is now publicly available as development code, statically reviewed and not yet validated as a working release;')
$impact = $impact.Replace('Present in **private** monorepository; not public releases',
  'Documented publicly in this repository, without plugin releases')
$impact = $impact.Replace('Prepare a **focused** public repository and usable, security-audited release',
  'Prepare a usable, security-audited release')
[IO.File]::WriteAllText($impactPath, $impact, [Text.UTF8Encoding]::new($false))

$recordPath = Join-Path $publicDir 'docs/SOURCE-CONSOLIDATION.md'
if (Test-Path -LiteralPath $recordPath) { throw 'Consolidation already recorded. Review before rerunning.' }
$record = @(
  '# Public source consolidation'
  ''
  'This public repository contains the development source and 131 project plans.'
  '297 original PeruCommerce files (PHP, Angular, docs) and six portfolio files were transferred.'
  'The former private Git history was NOT imported; the code was copied from its checked-out main.'
  "Original private source commit: $sourceCommit"
  'The other 130 plugin concepts remain planning only, not implementations.'
  'PeruCommerce is source-only, not a verified ZIP, build, test or production release.'
  'Integrated POS and IMP-72 validation are pending. Old plans about a separate future repo are historical.'
  'PeruCommerce declares GPL-2.0-or-later; dependency/asset licensing still requires release audit.'
)
[IO.File]::WriteAllText($recordPath, ($record -join [Environment]::NewLine) + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))

$stage = @('plugins/wp-peru-commerce','README.md','docs/RELEASE-STATUS.md',
  'docs/OPEN-SOURCE-IMPACT.md','docs/SOURCE-CONSOLIDATION.md') + $extraDocs
& git -C $publicDir add -- @stage
Check 'Stage public files'
$staged = @(& git -C $publicDir diff --cached --name-only)
Check 'Read staged paths'
if ($staged.Count -lt 296) { throw "Too few files staged: $($staged.Count). Private kept." }
$email = "$accountId+$login@users.noreply.github.com"
& git -C $publicDir -c "user.name=$login" -c "user.email=$email" commit -m 'feat(oss): consolidate PeruCommerce development source'
Check 'Commit public source'
& git -C $publicDir push origin main
Check 'Push public main'
& git -C $publicDir fetch --quiet origin main
Check 'Fetch public main'
$local = (& git -C $publicDir rev-parse HEAD | Out-String).Trim()
$remote = (& git -C $publicDir rev-parse refs/remotes/origin/main | Out-String).Trim()
if ($local -cne $remote) { throw 'Public branch mismatch. Private kept.' }

$publicTree = GithubJson "repos/$publicRepo/git/trees/main?recursive=1"
if ($publicTree.truncated) { throw 'Truncated public tree; private kept.' }
$publicSha = @{}
foreach ($entry in $publicTree.tree) {
  if ($entry.type -eq 'blob') { $publicSha[$entry.path] = $entry.sha }
}
foreach ($rel in $paths) {
  if (-not $publicSha.ContainsKey($rel) -or $publicSha[$rel] -cne $sourceSha[$rel]) {
    throw "Public GitHub blob verification failed: $rel. Private kept."
  }
}
$publicPeru = @($publicTree.tree | Where-Object {
  $_.type -eq 'blob' -and $_.path.StartsWith($peruPrefix, [StringComparison]::Ordinal)
})
if ($publicPeru.Count -ne 297) { throw 'Public PeruCommerce count mismatch. Private kept.' }
foreach ($required in @('README.md','docs/RELEASE-STATUS.md','docs/SOURCE-CONSOLIDATION.md')) {
  if (-not $publicSha.ContainsKey($required)) { throw "Missing destination doc: $required" }
}
Write-Host "VERIFIED: all 297 PeruCommerce Git blobs and 6 portfolio files on PUBLIC GitHub."
Write-Host "Public commit: $remote"
Write-Host "https://github.com/$publicRepo"
Write-Host 'The unrelated VSG-WordPress repository has not been touched.'
if (-not $DeletePrivate) {
  Write-Host 'Private origin preserved. Use -DeletePrivate after source verification.'
  return
}
Write-Host "Deleting only $privateRepo after verified remote migration."
& gh repo delete $privateRepo --yes
if ($LASTEXITCODE -ne 0) {
  throw 'Migration finished and verified, but deletion failed. If delete_repo permission is missing, run gh auth refresh -h github.com -s delete_repo; then gh repo delete VanguardSoftwareDeveloper/Vanguard-WordPress-Plugins --yes.'
}
Write-Host "DELETED private GitHub repository: $privateRepo"
Write-Host 'Existing private local clone was intentionally retained as an offline recovery copy.'
