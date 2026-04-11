#requires -Version 7.2
<#
.SYNOPSIS
    Export Microsoft 365 unified audit log entries for a single user (e.g. offboarded account).

.DESCRIPTION
    Connects with Exchange Online PowerShell (ExchangeOnlineManagement) and runs
    Search-UnifiedAuditLog for the given UPN and date range. Output is one CSV file.

    Suitable for interactive use on macOS/Linux/Windows with PowerShell 7.x.

.PARAMETER UserPrincipalName
    Target user UPN (e.g. leaver@contoso.com). Required unless provided at prompt (interactive only).

.PARAMETER StartDate
    Inclusive start of the range (local or unspecified kind; normalized for EXO).

.PARAMETER EndDate
    Exclusive end recommended as end-of-day; script uses the date you pass with time 00:00:00 unless you pass a full DateTime.

.PARAMETER OutputDirectory
    Folder for the CSV. Default: current directory.

.PARAMETER WindowMinutes
    Size of each time slice when querying the audit log (default 1440 = 1 day). Smaller windows if a slice returns the max page size.

.PARAMETER MaxRetentionDays
    Unified audit search limit varies by tenant/license (often 180 days). Dates older than this window from today are rejected.

.PARAMETER Organization
    Tenant org name for app-only auth (e.g. contoso.onmicrosoft.com).

.PARAMETER ClientId
    Azure AD app (client) ID for certificate auth.

.PARAMETER CertificateThumbprint
    Certificate thumbprint (Windows certificate store). On macOS, prefer interactive auth or use parameters supported by your EXO module version.

.PARAMETER AdminName
    Sign-in name for legacy basic credential (not recommended). Use interactive or certificate auth when possible.

.PARAMETER Password
    Plain password for AdminName (avoid; use only for locked-down automation with secrets management).

.PARAMETER NonInteractive
    Fail instead of Read-Host (for CI/schedulers).

.PARAMETER InstallModuleIfMissing
    Install ExchangeOnlineManagement for CurrentUser without prompting.

.PARAMETER SkipModuleCheck
    Assume ExchangeOnlineManagement is already loaded.

.EXAMPLE
    ./TrackOffboardedM365UserActivities.ps1 -UserPrincipalName 'former.user@contoso.com'

.EXAMPLE
    ./TrackOffboardedM365UserActivities.ps1 -UserPrincipalName 'u@contoso.com' -StartDate (Get-Date).AddDays(-30) -EndDate (Get-Date) -NonInteractive -InstallModuleIfMissing

.NOTES
    Required EXO role / permissions: access to unified audit log (e.g. View-Only Audit Log or equivalent; often Organization Management / compliance roles).
    Reference: Search-UnifiedAuditLog in Exchange Online PowerShell.
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [Alias('UserID')]
    [string] $UserPrincipalName,

    [Parameter(Mandatory = $false)]
    [Nullable[DateTime]] $StartDate,

    [Parameter(Mandatory = $false)]
    [Nullable[DateTime]] $EndDate,

    [Parameter(Mandatory = $false)]
    [string] $OutputDirectory = (Get-Location).Path,

    [Parameter(Mandatory = $false)]
    [ValidateRange(1, 10080)]
    [int] $WindowMinutes = 1440,

    [Parameter(Mandatory = $false)]
    [ValidateRange(1, 366)]
    [int] $MaxRetentionDays = 180,

    [Parameter(Mandatory = $false)]
    [string] $Organization,

    [Parameter(Mandatory = $false)]
    [string] $ClientId,

    [Parameter(Mandatory = $false)]
    [string] $CertificateThumbprint,

    [Parameter(Mandatory = $false)]
    [string] $AdminName,

    [Parameter(Mandatory = $false)]
    [string] $Password,

    [switch] $NonInteractive,

    [switch] $InstallModuleIfMissing,

    [switch] $SkipModuleCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-SafeFileNameSegment {
    param([string] $Text)
    if ([string]::IsNullOrWhiteSpace($Text)) { return 'User' }
    $invalid = [IO.Path]::GetInvalidFileNameChars() + @('@', ':', '/', '\')
    $s = $Text
    foreach ($c in $invalid) { $s = $s.Replace([string]$c, '_') }
    return ($s.Trim() -replace '\s+', '_')
}

function Get-BoundDateTime {
    <#
    .SYNOPSIS
        Normalize bound date values to [datetime] without using .Value (avoids failures when
        the input is already [datetime] or when Exchange Online session helpers shadow $StartDate).
    #>
    param(
        [Parameter(Mandatory)]
        [AllowNull()]
        $InputObject
    )
    if ($null -eq $InputObject) {
        throw 'Get-BoundDateTime: unexpected null.'
    }
    try {
        return [System.Management.Automation.LanguagePrimitives]::ConvertTo(
            $InputObject,
            [datetime],
            [System.Globalization.CultureInfo]::InvariantCulture)
    }
    catch {
        throw "Get-BoundDateTime: cannot convert '$InputObject' ($($InputObject.GetType().FullName)) to [datetime]: $_"
    }
}

function Ensure-ExchangeOnlineModule {
    if ($SkipModuleCheck) { return }
    $available = Get-Module -ListAvailable -Name ExchangeOnlineManagement |
        Sort-Object Version -Descending |
        Select-Object -First 1
    if ($available) {
        Import-Module ExchangeOnlineManagement -ErrorAction Stop
        return
    }
    if ($InstallModuleIfMissing) {
        Write-Verbose 'Installing ExchangeOnlineManagement (CurrentUser)...'
        Install-Module ExchangeOnlineManagement -Repository PSGallery -Scope CurrentUser -AllowClobber -Force -AcceptLicense -ErrorAction Stop
        Import-Module ExchangeOnlineManagement -ErrorAction Stop
        return
    }
    if ($NonInteractive) {
        throw 'ExchangeOnlineManagement is not installed. Install with: Install-Module ExchangeOnlineManagement -Scope CurrentUser, or use -InstallModuleIfMissing.'
    }
    Write-Host 'Exchange Online PowerShell module is not installed.' -ForegroundColor Yellow
    $confirm = Read-Host 'Install ExchangeOnlineManagement from PSGallery? [Y/N]'
    if ($confirm -match '^[yY]') {
        Install-Module ExchangeOnlineManagement -Repository PSGallery -Scope CurrentUser -AllowClobber -Force -AcceptLicense -ErrorAction Stop
        Import-Module ExchangeOnlineManagement -ErrorAction Stop
    }
    else {
        throw 'ExchangeOnlineManagement is required. Install-Module ExchangeOnlineManagement -Scope CurrentUser'
    }
}

function Connect-ExoSession {
    Write-Host 'Connecting to Exchange Online...' -ForegroundColor Cyan
    if (-not [string]::IsNullOrEmpty($AdminName) -and -not [string]::IsNullOrEmpty($Password)) {
        $sec = ConvertTo-SecureString -String $Password -AsPlainText -Force
        $cred = [PSCredential]::new($AdminName, $sec)
        Connect-ExchangeOnline -Credential $cred -ShowBanner:$false
    }
    elseif (-not [string]::IsNullOrEmpty($Organization) -and
        -not [string]::IsNullOrEmpty($ClientId) -and
        -not [string]::IsNullOrEmpty($CertificateThumbprint)) {
        Connect-ExchangeOnline -AppId $ClientId -CertificateThumbprint $CertificateThumbprint -Organization $Organization -ShowBanner:$false
    }
    else {
        Connect-ExchangeOnline -ShowBanner:$false
    }
}

function Read-DateInteractive {
    param(
        [string] $Prompt,
        [DateTime] $MinDate
    )
    while ($true) {
        $raw = Read-Host $Prompt
        try {
            $dt = [DateTime]::Parse($raw, [System.Globalization.CultureInfo]::CurrentCulture,
                [System.Globalization.DateTimeStyles]::AssumeLocal)
            if ($dt.Date -lt $MinDate.Date) {
                Write-Host "Date must be on or after $($MinDate.ToString('yyyy-MM-dd')) (audit retention)." -ForegroundColor Red
                continue
            }
            return $dt
        }
        catch {
            Write-Host 'Not a valid date.' -ForegroundColor Red
        }
    }
}

try {
    Ensure-ExchangeOnlineModule

    # Resolve dates before Connect-ExchangeOnline: the EXO module may set session variables
    # such as $StartDate / $EndDate and break later .Value / coercion logic.
    $maxStart = ([DateTime]::UtcNow.Date).AddDays(-($MaxRetentionDays - 1))
    $reportStart = $StartDate
    $reportEnd = $EndDate

    if ($null -eq $reportStart -and $null -eq $reportEnd) {
        $reportEnd = [DateTime]::UtcNow.Date
        $reportStart = $maxStart
    }

    if ($null -eq $reportStart) {
        if ($NonInteractive) { throw 'StartDate is required when -NonInteractive is set.' }
        $reportStart = Read-DateInteractive -Prompt 'Start date for report (e.g. 2024-01-15)' -MinDate $maxStart
    }

    $startNorm = Get-BoundDateTime -InputObject $reportStart

    if ($null -eq $reportEnd) {
        if ($NonInteractive) { throw 'EndDate is required when -NonInteractive is set.' }
        $endMin = if ($startNorm -gt $maxStart) { $startNorm } else { $maxStart }
        $reportEnd = Read-DateInteractive -Prompt 'End date for report (e.g. 2024-02-15)' -MinDate $endMin
    }

    $endNorm = Get-BoundDateTime -InputObject $reportEnd
    if ($startNorm -lt $maxStart) {
        throw "StartDate must be on or after $($maxStart.ToString('yyyy-MM-dd')) (MaxRetentionDays=$MaxRetentionDays)."
    }
    if ($endNorm -lt $startNorm) {
        throw 'EndDate must be greater than or equal to StartDate.'
    }

    if ([string]::IsNullOrWhiteSpace($UserPrincipalName)) {
        if ($NonInteractive) { throw 'UserPrincipalName is required when -NonInteractive is set.' }
        $UserPrincipalName = Read-Host 'User UPN (e.g. user@domain.com)'
    }
    $targetUpn = $UserPrincipalName.Trim()

    Connect-ExoSession

    $safeName = Get-SafeFileNameSegment -Text $targetUpn
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
    $csvName = "${safeName}_ActivityLogReport_${stamp}.csv"
    $outputCsv = Join-Path -Path $OutputDirectory -ChildPath $csvName

    if (-not (Test-Path -LiteralPath $OutputDirectory)) {
        New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    }

    Write-Host "Retrieving audit log for $targetUpn from $startNorm to $endNorm ..." -ForegroundColor Yellow

    $rows = [System.Collections.Generic.List[object]]::new()
    $totalRecords = 0
    $currentStart = $startNorm
    $interval = [TimeSpan]::FromMinutes($WindowMinutes)

    while ($true) {
        $currentEnd = $currentStart + $interval
        if ($currentEnd -gt $endNorm) { $currentEnd = $endNorm }

        if ($currentStart -eq $currentEnd) { break }

        $sessionId = [guid]::NewGuid().ToString()
        $batchTotalForWindow = 0

        do {
            $results = Search-UnifiedAuditLog -StartDate $currentStart -EndDate $currentEnd `
                -UserIds $targetUpn -SessionId $sessionId -SessionCommand ReturnLargeSet -ResultSize 5000

            $batchCount = if ($null -eq $results) { 0 } else { @($results).Count }
            $batchTotalForWindow += $batchCount

            foreach ($result in $results) {
                $audit = $result.AuditData | ConvertFrom-Json -ErrorAction SilentlyContinue
                if (-not $audit) { continue }

                $activityTime = if ($audit.CreationTime) {
                    try { (Get-Date $audit.CreationTime).ToString('u') } catch { [string]$audit.CreationTime }
                } else { '' }

                $recordUser = [string]$audit.UserId
                $operation = [string]$audit.Operation
                $resultStatus = [string]$audit.ResultStatus
                $workload = [string]$audit.Workload

                $rows.Add([PSCustomObject]@{
                    'Activity Time' = $activityTime
                    'User Name'     = $recordUser
                    'Operation'     = $operation
                    'Result'        = $resultStatus
                    'Workload'      = $workload
                    'More Info'     = $result.AuditData
                })
                $totalRecords++
            }

            Write-Progress -Activity 'Unified audit log' -Status "Window $currentStart -> $currentEnd | Records: $totalRecords"
        } while ($batchCount -ge 5000)

        if ($batchTotalForWindow -ge 50000) {
            Write-Warning "Time window hit ~50k records; consider rerunning with -WindowMinutes smaller than $WindowMinutes for $currentStart to $currentEnd."
        }

        if ($currentEnd -ge $endNorm) { break }
        $currentStart = $currentEnd
        if ($currentStart -gt [DateTime]::UtcNow.AddDays(1)) { break }
    }

    if ($rows.Count -eq 0) {
        Write-Host 'No records found for the specified user and range.' -ForegroundColor Yellow
    }
    else {
        $rows | Export-Csv -LiteralPath $outputCsv -NoTypeInformation -Encoding UTF8
        Write-Host "Exported $($rows.Count) records to:" -ForegroundColor Green
        Write-Host $outputCsv

        if (-not $NonInteractive -and $IsWindows) {
            $open = Read-Host 'Open CSV in default application? [Y/N]'
            if ($open -match '^[yY]') {
                Invoke-Item -LiteralPath $outputCsv
            }
        }
    }
}
finally {
    Write-Progress -Activity 'Unified audit log' -Completed
    if (Get-Command Disconnect-ExchangeOnline -ErrorAction SilentlyContinue) {
        try { Disconnect-ExchangeOnline -Confirm:$false -ErrorAction SilentlyContinue } catch { }
    }
}
