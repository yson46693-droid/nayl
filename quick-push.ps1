# quick-push.ps1 - Push updates to GitHub
# Usage: .\quick-push.ps1 [branch] [commit message]

$ErrorActionPreference = "Stop"
$REPO_URL = "https://github.com/yson46693-droid/nayl.git"
$BRANCH = if ($args[0]) { $args[0] } else { "main" }

Write-Host "=== Push to repo ==="
Write-Host "Repo: $REPO_URL"
Write-Host "Branch: $BRANCH"
Write-Host ""

$statusOutput = git status --porcelain
if (-not $statusOutput)
{
    Write-Host "No changes to push."
    exit 0
}

Write-Host "Current changes:"
git status --short
Write-Host ""

git add -A

if ($args[1])
{
    $MSG = $args[1]
}
else
{
    $inputMsg = Read-Host "Commit message (or Enter for date)"
    if (-not $inputMsg -or ($inputMsg -match '^\s*$'))
    {
        $MSG = "Update: $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }
    else
    {
        $MSG = $inputMsg
    }
}

git commit -m "$MSG"
Write-Host ""
Write-Host "Pulling from remote..."
git pull --rebase origin $BRANCH
Write-Host ""
Write-Host "Pushing..."
git push -u origin $BRANCH
Write-Host ""
Write-Host "Done."
