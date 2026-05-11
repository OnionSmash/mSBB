<?php
declare(strict_types=1);

/**
 * Signup taxonomies and validators. Single source of truth used by
 * /portal/api/signup.php (validation) and login.html (form options).
 */

// ---------- Employee size buckets (SaaS-standard, mapped to compliance thresholds) ----------
const SC_EMPLOYEE_SIZES = [
    '1-10',
    '11-50',
    '51-200',
    '201-500',
    '501-1000',
    '1001-5000',
    '5000+',
];

// ---------- Industries (compliance buyer self-segmentation) ----------
const SC_INDUSTRIES = [
    'saas'        => 'SaaS / Software',
    'fintech'     => 'Financial Services / Fintech',
    'healthcare'  => 'Healthcare / HealthTech',
    'ai_ml'       => 'AI / ML',
    'ecommerce'   => 'E-commerce / Retail',
    'manufacturing'=>'Manufacturing / Industrial',
    'services'    => 'Professional Services / Consulting',
    'government'  => 'Government / Public Sector / Federal',
    'education'   => 'Education',
    'other'       => 'Other',
];

// ---------- Business functions (the buyer's department) ----------
const SC_BUSINESS_FUNCTIONS = [
    'security'    => 'Security',
    'compliance'  => 'Compliance / GRC',
    'engineering' => 'Engineering / DevOps',
    'it'          => 'IT',
    'legal'       => 'Legal / Privacy',
    'risk'        => 'Risk',
    'executive'   => 'Executive (CEO / COO / CFO)',
    'other'       => 'Other',
];

// ---------- Free-mail domain blocklist ----------
const SC_FREEMAIL_DOMAINS = [
    'gmail.com','googlemail.com',
    'yahoo.com','yahoo.co.uk','yahoo.ca','yahoo.fr','yahoo.de','ymail.com','rocketmail.com',
    'outlook.com','hotmail.com','hotmail.co.uk','live.com','msn.com',
    'aol.com',
    'icloud.com','me.com','mac.com',
    'protonmail.com','proton.me','pm.me',
    'gmx.com','gmx.net','gmx.de',
    'mail.com',
    'zoho.com',
    'fastmail.com','fastmail.fm',
    'tutanota.com','tutanota.de',
    'yandex.com','yandex.ru',
    'duck.com','hey.com',
    // disposable / temp mail
    'mailinator.com','guerrillamail.com','10minutemail.com','tempmail.com','throwaway.email',
];

function sc_is_freemail(string $email): bool {
    $at = strrpos($email, '@');
    if ($at === false) return false;
    $domain = strtolower(substr($email, $at + 1));
    return in_array($domain, SC_FREEMAIL_DOMAINS, true);
}

/**
 * Loose E.164 validator: + then 8–15 digits. Permissive on input
 * (strips spaces, dashes, parens) and normalizes. Returns the normalized
 * form, or null if invalid.
 *
 * Note: This is intentionally not a full libphonenumber port. It catches
 * the obvious bad inputs without bloating us with a dependency.
 */
function sc_normalize_phone(string $raw): ?string {
    $s = preg_replace('/[\s()\-\.]/', '', trim($raw)) ?? '';
    if ($s === '') return null;
    // Add + if user typed just digits and the length looks like E.164.
    if ($s[0] !== '+') {
        if (preg_match('/^\d{10,15}$/', $s)) {
            $s = '+' . $s;
        } else {
            return null;
        }
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $s) === 1 ? $s : null;
}

/** Validate ISO-3166 alpha-2 country code (very loose: 2 uppercase letters). */
function sc_valid_country(string $cc): bool {
    return (bool)preg_match('/^[A-Z]{2}$/', strtoupper($cc));
}

// ---------- Plan tiers ----------
const SC_PLANS = [
    'starter'    => 'Starter',
    'growth'     => 'Growth',
    'sentinel'   => 'Sentinel',
    'enterprise' => 'Enterprise',
];

/**
 * Compose a friendly display name from parts. Falls back to email local-part.
 */
function sc_display_name(?string $first, ?string $last, ?string $fallback = null): string {
    $n = trim(($first ?? '') . ' ' . ($last ?? ''));
    if ($n !== '') return $n;
    if ($fallback) {
        $at = strpos($fallback, '@');
        return $at !== false ? substr($fallback, 0, $at) : $fallback;
    }
    return 'New User';
}
