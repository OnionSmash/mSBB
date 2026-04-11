#requires -Version 7.2
<#
.SYNOPSIS
  Microsoft 365 Users' Daily Sign-In Insights Report (CSV + HTML), Optional Graph Mail.

.DESCRIPTION
  Queries Entra ID Sign-In Logs Via Microsoft Graph (Beta), Aggregates Metrics,
  Writes CSV/HTML Under A Chosen Output Folder, And Optionally Emails Attachments.

.NOTES
  PowerShell 7.5+ on macOS, Windows, and Linux.
  Module: Microsoft.Graph (Beta) — install: Install-Module Microsoft.Graph.Beta -Scope CurrentUser

  Delegated scopes (interactive): AuditLog.Read.All, Directory.Read.All,
  Policy.Read.ConditionalAccess, Mail.Send (plus admin consent as required).

  App-only: TenantId, ClientId, CertificateThumbprint (certificate must be usable on the host OS).

.EXAMPLE
  ./UserSigninInsights.ps1 -ReportWindowHours 24

.EXAMPLE
  ./UserSigninInsights.ps1 -Recipients 'ravenell@modelmesh.cloud' -FromAddress 'reports@contoso.com' -InstallModuleIfMissing

.EXAMPLE
  ./UserSigninInsights.ps1 -OutputDirectory $HOME/reports -Interactive
#>
[CmdletBinding()]
param(
    [switch]$CreateSession,
    [string]$Recipients,
    [switch]$HideSummaryAtEnd,
    [string]$FromAddress,
    [string]$TenantId,
    [string]$ClientId,
    [string]$CertificateThumbprint,
    # Default output folder (cross-platform)
    [string]$OutputDirectory = '',
    # Hours to look back from UTC "now" (default 24)
    [ValidateRange(1, 168)]
    [int]$ReportWindowHours = 24,
    # Non-interactive / CI: install Microsoft.Graph.Beta without prompting
    [switch]$InstallModuleIfMissing,
    # Prompt to open exports (macOS: no COM popup; uses Read-Host). Windows: optional COM popup when -UseWindowsShellPopup
    [switch]$Interactive,
    [switch]$UseWindowsShellPopup
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-ScriptOutputDirectory {
    if (-not [string]::IsNullOrWhiteSpace($OutputDirectory)) {
        $resolved = [System.IO.Path]::GetFullPath($OutputDirectory)
        if (-not (Test-Path -LiteralPath $resolved)) {
            New-Item -ItemType Directory -Path $resolved -Force | Out-Null
        }
        return $resolved
    }
    return (Get-Location).Path
}

function Test-GraphBetaModule {
    $mod = Get-Module -Name Microsoft.Graph.Beta -ListAvailable -ErrorAction SilentlyContinue
    return ($null -ne $mod)
}

function Ensure-GraphBetaModule {
    if (Test-GraphBetaModule) { return }

    Write-Warning 'Microsoft.Graph.Beta Is Not Installed.'
    if ($InstallModuleIfMissing) {
        Write-Host 'Installing Microsoft.Graph.Beta From PSGallery...'
        Install-Module Microsoft.Graph.Beta -Repository PSGallery -Scope CurrentUser -AllowClobber -Force -ErrorAction Stop
        return
    }

    if ([Environment]::UserInteractive -and -not $env:CI) {
        $confirm = Read-Host 'Install Microsoft.Graph.Beta Now? [y/N]'
        if ($confirm -match '^[yY]') {
            Install-Module Microsoft.Graph.Beta -Repository PSGallery -Scope CurrentUser -AllowClobber -Force -ErrorAction Stop
            return
        }
    }

    throw 'Microsoft.Graph.Beta Is Required. Install With: Install-Module Microsoft.Graph.Beta -Scope CurrentUser, Or Re-Run With -InstallModuleIfMissing.'
}

function Connect-M365Graph {
    Ensure-GraphBetaModule

    if ($CreateSession.IsPresent) {
        try {
            $ctx = Get-MgContext -ErrorAction SilentlyContinue
            if ($null -ne $ctx) { Disconnect-MgGraph -ErrorAction SilentlyContinue }
        }
        catch { }
    }

    Write-Host 'Connecting To Microsoft Graph...'
    if (-not [string]::IsNullOrWhiteSpace($TenantId) -and
        -not [string]::IsNullOrWhiteSpace($ClientId) -and
        -not [string]::IsNullOrWhiteSpace($CertificateThumbprint)) {
        Connect-MgGraph -TenantId $TenantId -AppId $ClientId -CertificateThumbprint $CertificateThumbprint -NoWelcome -ErrorAction Stop
    }
    else {
        Connect-MgGraph -Scopes @(
            'AuditLog.Read.All',
            'Directory.Read.All',
            'Policy.Read.ConditionalAccess',
            'Mail.Send'
        ) -NoWelcome -ErrorAction Stop
    }
}

function Send-SignInReportEmail {
    param(
        [Parameter(Mandatory)][string]$From,
        [Parameter(Mandatory)][string]$ToCsv,
        [Parameter(Mandatory)][string]$HtmlPath,
        [Parameter(Mandatory)][string]$CsvPath,
        [Parameter(Mandatory)][string]$ReportLabel
    )

    $addresses = @(
        $ToCsv -split ',' |
        ForEach-Object { $_.Trim() } |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    )
    if ($addresses.Count -eq 0) { throw 'No Valid Recipient Email Addresses.' }

    $toRecipients = foreach ($addr in $addresses) {
        @{
            emailAddress = @{ address = $addr }
        }
    }

    $htmlBytes = [System.IO.File]::ReadAllBytes($HtmlPath)
    $csvBytes = [System.IO.File]::ReadAllBytes($CsvPath)
    $htmlB64 = [Convert]::ToBase64String($htmlBytes)
    $csvB64 = [Convert]::ToBase64String($csvBytes)

    $emailBody = @"
<html>
  <head><meta charset="utf-8"></head>
  <body>
    <p>Hello,</p>
    <p>Sign-in summary for: <strong>$([System.Net.WebUtility]::HtmlEncode($ReportLabel))</strong></p>
    <p>See the attached HTML summary and CSV detail. For live investigation:
    <a href="https://entra.microsoft.com/#view/Microsoft_AAD_IAM/SignInLogsList.ReactView/timeRangeType/last24hours/showApplicationSignIns~/true">Entra sign-in logs</a>.</p>
    <p>— IT Admin Team</p>
  </body>
</html>
"@

    $params = @{
        Message           = @{
            Subject      = "Microsoft 365 Users' Daily Sign-In Insights ($ReportLabel)"
            Body         = @{
                ContentType = 'HTML'
                Content     = $emailBody
            }
            Attachments  = @(
                @{
                    '@odata.type' = '#microsoft.graph.fileAttachment'
                    Name          = 'UserSignIn_Summary.html'
                    ContentBytes  = $htmlB64
                    ContentType   = 'text/html'
                },
                @{
                    '@odata.type' = '#microsoft.graph.fileAttachment'
                    Name          = 'UserSignIn_Report.csv'
                    ContentBytes  = $csvB64
                    ContentType   = 'text/csv'
                }
            )
            ToRecipients = @($toRecipients)
        }
        SaveToSentItems = $true
    }

    Send-MgBetaUserMail -UserId $From -BodyParameter $params -ErrorAction Stop
    Write-Host "Mail Sent From $From To $($addresses -join ', ')."
}

function Escape-HtmlCell {
    param([string]$Text)
    if ($null -eq $Text) { return '' }
    [System.Net.WebUtility]::HtmlEncode("$Text")
}

# --- Main ---
Connect-M365Graph

$outRoot = Get-ScriptOutputDirectory
$stamp = Get-Date -Format 'yyyy-MMM-dd-ddd_HH-mm-ss'
$ExportCSV = Join-Path $outRoot "M365Users_Signin_Report_$stamp.csv"
$ExportHTML = Join-Path $outRoot "M365Users_Signin_Summary_$stamp.html"

$Count = 0
$SuccessfulSigninCount = 0
$FailedSigninCount = 0
$MFASigninCount = 0
$NonMFASigninCount = 0
$SigninsBlockedByCACount = 0
$SigninsGrantedByCACount = 0
$ExternalUserSigninCount = 0
$ExternalUserSuccessfulSigninCount = 0
$ExternalUserFailedSigninCount = 0

$FailedSigninUsers = @{}
$CABlockedUsers = @{}
$CAGrantedUsers = @{}
$SuccessfulNonMFSignInUsers = @{}
$ExternalUserSignIns = @{}

$rowList = [System.Collections.Generic.List[object]]::new()

# UTC window for Graph filter (avoids local-midnight + fake 'Z' issues)
$endUtc = [datetime]::UtcNow
$startUtc = $endUtc.AddHours(-1 * $ReportWindowHours)
$startStr = $startUtc.ToString('yyyy-MM-ddTHH:mm:ss.fffZ')
$endStr = $endUtc.ToString('yyyy-MM-ddTHH:mm:ss.fffZ')
$filter = "createdDateTime ge $startStr and createdDateTime le $endStr"
$reportLabel = "$($startUtc.ToString('yyyy-MM-dd HH:mm')) UTC → $($endUtc.ToString('yyyy-MM-dd HH:mm')) UTC"

Write-Host "Generating Sign-In Report ($ReportWindowHours h window, UTC)..."
Write-Verbose "Filter: $filter"

try {
    Get-MgBetaAuditLogSignIn -All -Filter $filter -ErrorAction Stop | ForEach-Object {
        $Count++
        if (($Count % 50) -eq 0) {
            Write-Progress -Activity 'Processing Sign-Ins' -Status "Records: $Count" -PercentComplete -1
        }

        $rec = $_
        $upn = $rec.UserPrincipalName
        $createdRaw = $rec.CreatedDateTime
        $createdLocal = if ($createdRaw) { ([datetime]$createdRaw).ToLocalTime() } else { $null }

        $userDisplayName = $rec.UserDisplayName
        $authReq = $rec.AuthenticationRequirement
        $geoLocation = @(
            $rec.Location.City
            $rec.Location.State
            $rec.Location.CountryOrRegion
        ) -join ', '

        $deviceName = $rec.DeviceDetail.DisplayName
        $browser = $rec.DeviceDetail.Browser
        $operatingSystem = $rec.DeviceDetail.OperatingSystem
        $ipAddress = $rec.IpAddress
        $errorCode = $rec.Status.ErrorCode
        $failureReason = $rec.Status.FailureReason
        $userType = $rec.UserType
        $riskDetail = $rec.RiskDetail
        $isInteractive = $rec.IsInteractive
        $riskState = $rec.RiskState
        $appDisplayName = $rec.AppDisplayName
        $resourceDisplayName = $rec.ResourceDisplayName
        $conditionalAccessStatus = $rec.ConditionalAccessStatus
        $appliedConditionalAccessPolicies = $rec.AppliedConditionalAccessPolicies

        $appliedPolicies = [System.Collections.Generic.List[string]]::new()
        if ($appliedConditionalAccessPolicies) {
            foreach ($p in $appliedConditionalAccessPolicies) {
                $pr = [string]$p.Result
                $isOutcome = [string]::Equals($pr, 'Success', [StringComparison]::OrdinalIgnoreCase) -or
                    [string]::Equals($pr, 'Failure', [StringComparison]::OrdinalIgnoreCase)
                if ($isOutcome -and $p.DisplayName) { [void]$appliedPolicies.Add($p.DisplayName) }
            }
        }
        $appliedPoliciesText = if ($appliedPolicies.Count -eq 0) { 'None' } else { $appliedPolicies -join ', ' }

        if ($errorCode -eq 0) {
            $status = 'Success'
            $SuccessfulSigninCount++

            if ($authReq -eq 'singleFactorAuthentication') {
                $NonMFASigninCount++
                if ($SuccessfulNonMFSignInUsers.ContainsKey($upn)) {
                    $SuccessfulNonMFSignInUsers[$upn].Count++
                    $SuccessfulNonMFSignInUsers[$upn].LastAccessedTime = $createdLocal
                }
                else {
                    $SuccessfulNonMFSignInUsers[$upn] = @{
                        Count            = 1
                        LastAccessedTime = $createdLocal
                    }
                }
            }
            elseif ($authReq -eq 'multiFactorAuthentication') {
                $MFASigninCount++
            }
        }
        else {
            $status = 'Failed'
            $FailedSigninCount++
            if ($FailedSigninUsers.ContainsKey($upn)) {
                $FailedSigninUsers[$upn]++
            }
            else {
                $FailedSigninUsers[$upn] = 1
            }
        }

        # CA failure: Conditional Access status only (case-insensitive)
        $caFailed = [string]::Equals([string]$conditionalAccessStatus, 'Failure', [StringComparison]::OrdinalIgnoreCase)
        if ($caFailed) {
            $SigninsBlockedByCACount++
            if ($CABlockedUsers.ContainsKey($upn)) {
                $CABlockedUsers[$upn].Count++
                $CABlockedUsers[$upn].LastAccessedTime = $createdLocal
            }
            else {
                $CABlockedUsers[$upn] = @{
                    Count            = 1
                    LastAccessedTime = $createdLocal
                }
            }
        }

        # CA "granted" heuristic: CA success + MFA on that sign-in
        if ([string]::Equals([string]$conditionalAccessStatus, 'success', [StringComparison]::OrdinalIgnoreCase) -and $authReq -eq 'multiFactorAuthentication') {
            $SigninsGrantedByCACount++
            if ($CAGrantedUsers.ContainsKey($upn)) {
                $CAGrantedUsers[$upn].Count++
                $CAGrantedUsers[$upn].LastAccessedTime = $createdLocal
            }
            else {
                $CAGrantedUsers[$upn] = @{
                    Count            = 1
                    LastAccessedTime = $createdLocal
                }
            }
        }

        if ($userType -ne 'member') {
            $ExternalUserSigninCount++
            if (-not $ExternalUserSignIns.ContainsKey($upn)) {
                $ExternalUserSignIns[$upn] = @{
                    ExtSuccessfulSigninCount = 0
                    ExtFailedSigninCount     = 0
                    LastAccessedTime         = $createdLocal
                    LastSignInStatus         = 'NotDefined'
                }
            }
            if ($errorCode -eq 0) {
                $ExternalUserSuccessfulSigninCount++
                $ExternalUserSignIns[$upn].ExtSuccessfulSigninCount++
                if ($ExternalUserSignIns[$upn].LastSignInStatus -eq 'NotDefined') {
                    $ExternalUserSignIns[$upn].LastSignInStatus = 'Success'
                }
            }
            else {
                $ExternalUserFailedSigninCount++
                $ExternalUserSignIns[$upn].ExtFailedSigninCount++
                if ($ExternalUserSignIns[$upn].LastSignInStatus -eq 'NotDefined') {
                    $ExternalUserSignIns[$upn].LastSignInStatus = 'Failed'
                }
            }
            $ExternalUserSignIns[$upn].LastAccessedTime = $createdLocal
        }

        $fr = $failureReason
        if ($fr -eq 'Other.') { $fr = 'None' }

        $row = [PSCustomObject]@{
            'Signin Date'                        = $createdLocal
            'User Name'                          = $userDisplayName
            'SigninId'                           = $rec.Id
            'UPN'                                = $upn
            'Status'                             = $status
            'IP Address'                         = $ipAddress
            'Location'                           = $geoLocation
            'Device Name'                        = $deviceName
            'Browser'                            = $browser
            'Operating System'                   = $operatingSystem
            'User Type'                          = $userType
            'Authentication Requirement'       = $authReq
            'Risk Detail'                        = $riskDetail
            'Risk State'                         = $riskState
            'Conditional Access Status'          = $conditionalAccessStatus
            'Applied Conditional Access Policies' = $appliedPoliciesText
            'IsInteractive'                      = $isInteractive
            'App Display Name'                   = $appDisplayName
            'Resource Display Name'              = $resourceDisplayName
            'Failure Reason'                     = $fr
        }
        $rowList.Add($row)
    }
}
finally {
    Write-Progress -Activity 'Processing Sign-Ins' -Completed
}

if ($rowList.Count -gt 0) {
    $rowList | Export-Csv -Path $ExportCSV -NoTypeInformation -Encoding UTF8
}
else {
    # Empty CSV with headers for consistency
    [PSCustomObject]@{
        'Sign-In Date' = $null; 'User Name' = $null; 'UPN' = $null; 'Status' = 'No Records In Window'
    } | Export-Csv -Path $ExportCSV -NoTypeInformation -Encoding UTF8
}

$SortedFailedUsers = $FailedSigninUsers.GetEnumerator() | Sort-Object Value -Descending
$SortedCABlockedUsers = $CABlockedUsers.GetEnumerator() | Sort-Object { $_.Value.Count } -Descending
$SortedCAGrantedUsers = $CAGrantedUsers.GetEnumerator() | Sort-Object { $_.Value.Count } -Descending
$SortedSuccessfulNonMFSignInUsers = $SuccessfulNonMFSignInUsers.GetEnumerator() | Sort-Object { $_.Value.Count } -Descending
$SortedExternalUserSignins = $ExternalUserSignIns.GetEnumerator() | Sort-Object { $_.Value.ExtFailedSigninCount } -Descending

$sb = [System.Text.StringBuilder]::new()
[void]$sb.Append(@"
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: system-ui, sans-serif; background: #f9f9f9; padding: 20px; }
.card-container { display: flex; gap: 20px; flex-wrap: wrap; }
.card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,.1); flex: 1; min-width: 200px; }
.card h2 { margin: 0; font-size: 18px; color: #555; }
.card p { margin: 10px 0 0; font-size: 24px; font-weight: bold; color: #333; }
table { width: 100%; max-width: 900px; border-collapse: collapse; margin: 20px 0 30px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
th { background: #0078d4; color: #fff; }
caption { caption-side: top; font-size: 18px; font-weight: bold; margin-bottom: 10px; text-align: left; }
.sub-card-container { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; max-width: 360px; }
.sub-card { background: #f1f1f1; padding: 10px; border-radius: 8px; flex: 1; min-width: 140px; }
.sub-card h4 { margin: 0; font-size: 14px; color: #666; }
.sub-card p { margin: 5px 0 0; font-size: 20px; font-weight: bold; color: #222; }
.note { color: #666; font-size: 14px; margin-bottom: 16px; }
</style>
</head>
<body>
<h1>Daily User Sign-in Summary</h1>
<p class="note">Window: $([System.Net.WebUtility]::HtmlEncode($reportLabel)) · Records: $Count</p>
<p class="note">CA &quot;Blocked&quot; Counts Sign-Ins Where Conditional Access Status Is <strong>Failure</strong> Only.</p>
<div class="card-container">
<div class="card"><h2>Total Sign-Ins</h2><p>$Count</p></div>
<div class="card"><h2>Successful</h2><p>$SuccessfulSigninCount</p></div>
<div class="card"><h2>Failed</h2><p>$FailedSigninCount</p></div>
<div class="card"><h2>CA Failure</h2><p>$SigninsBlockedByCACount</p></div>
<div class="card"><h2>CA Success + MFA</h2><p>$SigninsGrantedByCACount</p></div>
<div class="card"><h2>MFA / Non-MFA (success)</h2>
<div class="sub-card-container">
<div class="sub-card"><h4>MFA</h4><p>$MFASigninCount</p></div>
<div class="sub-card"><h4>Non-MFA</h4><p>$NonMFASigninCount</p></div>
</div></div>
<div class="card"><h2>External users</h2>
<div class="sub-card-container">
<div class="sub-card"><h4>Success</h4><p>$ExternalUserSuccessfulSigninCount</p></div>
<div class="sub-card"><h4>Failed</h4><p>$ExternalUserFailedSigninCount</p></div>
</div></div>
</div>
"@)

if ($FailedSigninUsers.Count -gt 0) {
    [void]$sb.Append('<table><caption>Failed sign-ins by user</caption><tr><th>UPN</th><th>Count</th></tr>')
    foreach ($user in $SortedFailedUsers) {
        [void]$sb.AppendFormat('<tr><td>{0}</td><td>{1}</td></tr>', (Escape-HtmlCell $user.Key), (Escape-HtmlCell "$($user.Value)"))
    }
    [void]$sb.Append('</table>')
}

if ($SigninsBlockedByCACount -gt 0) {
    [void]$sb.Append('<table><caption>Conditional Access Failure (By User)</caption><tr><th>UPN</th><th>Count</th><th>Last seen</th></tr>')
    foreach ($user in $SortedCABlockedUsers) {
        [void]$sb.AppendFormat(
            '<tr><td>{0}</td><td>{1}</td><td>{2}</td></tr>',
            (Escape-HtmlCell $user.Key),
            (Escape-HtmlCell "$($user.Value.Count)"),
            (Escape-HtmlCell "$($user.Value.LastAccessedTime)")
        )
    }
    [void]$sb.Append('</table>')
}

if ($ExternalUserSigninCount -gt 0) {
    [void]$sb.Append('<table><caption>External User Sign-Ins</caption><tr><th>UPN</th><th>Success</th><th>Failed</th><th>Last time</th><th>Last status</th></tr>')
    foreach ($user in $SortedExternalUserSignins) {
        [void]$sb.AppendFormat(
            '<tr><td>{0}</td><td>{1}</td><td>{2}</td><td>{3}</td><td>{4}</td></tr>',
            (Escape-HtmlCell $user.Key),
            (Escape-HtmlCell "$($user.Value.ExtSuccessfulSigninCount)"),
            (Escape-HtmlCell "$($user.Value.ExtFailedSigninCount)"),
            (Escape-HtmlCell "$($user.Value.LastAccessedTime)"),
            (Escape-HtmlCell "$($user.Value.LastSignInStatus)")
        )
    }
    [void]$sb.Append('</table>')
}

if ($NonMFASigninCount -gt 0) {
    [void]$sb.Append('<table><caption>Successful Single-Factor Sign-Ins</caption><tr><th>UPN</th><th>Count</th><th>Last Sign-In</th></tr>')
    foreach ($user in $SortedSuccessfulNonMFSignInUsers) {
        [void]$sb.AppendFormat(
            '<tr><td>{0}</td><td>{1}</td><td>{2}</td></tr>',
            (Escape-HtmlCell $user.Key),
            (Escape-HtmlCell "$($user.Value.Count)"),
            (Escape-HtmlCell "$($user.Value.LastAccessedTime)")
        )
    }
    [void]$sb.Append('</table>')
}

if ($SigninsGrantedByCACount -gt 0) {
    [void]$sb.Append('<table><caption>CA Success + MFA (By User)</caption><tr><th>UPN</th><th>Count</th><th>Last seen</th></tr>')
    foreach ($user in $SortedCAGrantedUsers) {
        [void]$sb.AppendFormat(
            '<tr><td>{0}</td><td>{1}</td><td>{2}</td></tr>',
            (Escape-HtmlCell $user.Key),
            (Escape-HtmlCell "$($user.Value.Count)"),
            (Escape-HtmlCell "$($user.Value.LastAccessedTime)")
        )
    }
    [void]$sb.Append('</table>')
}

[void]$sb.Append('</body></html>')
$sb.ToString() | Out-File -FilePath $ExportHTML -Encoding utf8

# Email
if (-not [string]::IsNullOrWhiteSpace($Recipients)) {
    if ([string]::IsNullOrWhiteSpace($FromAddress)) {
        Write-Warning 'Recipients Set But FromAddress Is Empty; Skipping Send-MgBetaUserMail.'
    }
    elseif (-not (Test-Path -LiteralPath $ExportCSV) -or -not (Test-Path -LiteralPath $ExportHTML)) {
        Write-Warning 'Export Files Missing; Skipping Email.'
    }
    else {
        Send-SignInReportEmail -From $FromAddress -ToCsv $Recipients -HtmlPath $ExportHTML -CsvPath $ExportCSV -ReportLabel $reportLabel
    }
}

if (-not $HideSummaryAtEnd) {
    Write-Host ""
    Write-Host "Processed $Count Sign-In Record(s)."
    Write-Host "CSV:  $ExportCSV"
    Write-Host "HTML: $ExportHTML"

    if ($Interactive) {
        $open = $false
        if ($IsWindows -and $UseWindowsShellPopup) {
            try {
                $shell = New-Object -ComObject WScript.Shell
                $answer = $shell.Popup('Open CSV and HTML Now?', 0, 'Open Outputs', 4)
                $open = ($answer -eq 6)
            }
            catch {
                Write-Warning 'COM Popup Failed; Use Read-Host Or Open Files Manually.'
                $r = Read-Host 'Open CSV and HTML In Default Apps? [y/N]'
                $open = ($r -match '^[yY]')
            }
        }
        else {
            $r = Read-Host 'Open CSV and HTML In Default Apps? [y/N]'
            $open = ($r -match '^[yY]')
        }
        if ($open) {
            Invoke-Item -LiteralPath $ExportCSV
            Invoke-Item -LiteralPath $ExportHTML
        }
    }
}

Write-Host 'Done.'
