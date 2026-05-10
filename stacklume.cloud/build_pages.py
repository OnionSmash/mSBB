#!/usr/bin/env python3
"""One-shot generator for stacklume.cloud subpages.

Run once: `python3 build_pages.py`. Safe to delete after pages are written.
"""
import os
import html as htmlmod

ROOT = os.path.dirname(os.path.abspath(__file__))
SITE_URL = "https://stacklume.cloud"

# ---------- shared chrome ----------

NAV_HTML = """<nav>
  <a href="{up}index.html" class="nav-brand">
    <div class="brand-icon">
      <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-label="Stack Lume">
        <rect class="lm-bar lm-stack" x="6"  y="22" width="14" height="3.2" rx="1.2" opacity="0.55"></rect>
        <rect class="lm-bar lm-stack" x="6"  y="15.6" width="20" height="3.2" rx="1.2" opacity="0.85"></rect>
        <rect class="lm-bar lm-top"   x="6"  y="9.2"  width="11" height="3.2" rx="1.2"></rect>
        <circle class="lm-bar lm-top" cx="22" cy="10.8" r="1.9"></circle>
      </svg>
    </div>
    <span class="nav-brand-wordmark"><span class="wm-stack">Stack </span><span class="wm-lume">Lume</span></span>
  </a>
  <button class="nav-mobile-toggle" id="nav-mobile-toggle" aria-label="Toggle navigation" aria-expanded="false">
    <i class="bi bi-list"></i>
  </button>
  <ul class="nav-links">
    <li class="nav-item">
      <button class="nav-trigger" data-nav="services">
        <i class="bi bi-grid-1x2-fill ni-icon"></i>
        Services
        <i class="bi bi-chevron-down ni-caret"></i>
      </button>
      <div class="nav-menu">
        <div class="nav-menu-label">Core Services</div>
        <a href="{up}services/iam-pam-agent.html"><i class="bi bi-shield-shaded nm-icon"></i>IAM &amp; PAM Agent</a>
        <a href="{up}services/siem-triage.html"><i class="bi bi-speedometer2 nm-icon"></i>SIEM Triage</a>
        <a href="{up}services/private-data-intel.html"><i class="bi bi-database-lock nm-icon"></i>Private Data Intel</a>
        <a href="{up}services/model-mesh.html"><i class="bi bi-diagram-3-fill nm-icon"></i>Model Mesh</a>
        <a href="{up}services/retrieval-qa.html"><i class="bi bi-eye-fill nm-icon"></i>Retrieval QA</a>
        <a href="{up}services/guardrail-policy.html"><i class="bi bi-patch-check-fill nm-icon"></i>Guardrail Policy</a>
      </div>
    </li>
    <li class="nav-item">
      <button class="nav-trigger" data-nav="products">
        <i class="bi bi-boxes ni-icon"></i>
        Products
        <i class="bi bi-chevron-down ni-caret"></i>
      </button>
      <div class="nav-menu">
        <div class="nav-menu-label">Product Suite</div>
        <a href="{up}products/promptshield-nexus.html"><i class="bi bi-shield-lock-fill nm-icon"></i>PromptShield Nexus</a>
        <a href="{up}products/vectorpulse-sentinel.html"><i class="bi bi-broadcast-pin nm-icon"></i>VectorPulse Sentinel</a>
        <a href="{up}products/agentchain-commander.html"><i class="bi bi-node-plus-fill nm-icon"></i>AgentChain Commander</a>
        <a href="{up}products/hallucination-forensics.html"><i class="bi bi-bug-fill nm-icon"></i>Hallucination Forensics</a>
        <a href="{up}products/compliance-guardian.html"><i class="bi bi-check2-circle nm-icon"></i>Compliance Guardian</a>
        <a href="{up}products/adaptive-honeymesh.html"><i class="bi bi-hdd-network-fill nm-icon"></i>Adaptive Honeymesh</a>
      </div>
    </li>
    <li class="nav-item">
      <button class="nav-trigger" data-nav="projects">
        <i class="bi bi-kanban-fill ni-icon"></i>
        Projects
        <i class="bi bi-chevron-down ni-caret"></i>
      </button>
      <div class="nav-menu">
        <div class="nav-menu-label">Case Studies</div>
        <a href="{up}industries/financial-services.html"><i class="bi bi-bank2 nm-icon"></i>Financial Services</a>
        <a href="{up}industries/healthcare.html"><i class="bi bi-hospital-fill nm-icon"></i>Healthcare</a>
        <a href="{up}industries/critical-infrastructure.html"><i class="bi bi-cpu-fill nm-icon"></i>Critical Infrastructure</a>
        <a href="{up}industries/federal-defense.html"><i class="bi bi-shield-fill-check nm-icon"></i>Federal &amp; Defense</a>
      </div>
    </li>
    <li class="nav-item">
      <button class="nav-trigger" data-nav="blog">
        <i class="bi bi-journal-text ni-icon"></i>
        Blog
        <i class="bi bi-chevron-down ni-caret"></i>
      </button>
      <div class="nav-menu">
        <div class="nav-menu-label">Latest Intelligence</div>
        <a href="{up}blog/threat-intel.html"><i class="bi bi-radioactive nm-icon"></i>Threat Intel</a>
        <a href="{up}blog/critical-infra.html"><i class="bi bi-bricks nm-icon"></i>Critical Infra</a>
        <a href="{up}blog/ai-security.html"><i class="bi bi-robot nm-icon"></i>AI Security</a>
        <a href="{up}blog/briefings.html"><i class="bi bi-newspaper nm-icon"></i>Briefings</a>
      </div>
    </li>
    <li class="nav-item">
      <button class="nav-trigger" data-nav="about">
        <i class="bi bi-buildings-fill ni-icon"></i>
        About
        <i class="bi bi-chevron-down ni-caret"></i>
      </button>
      <div class="nav-menu">
        <div class="nav-menu-label">About Us</div>
        <a href="{up}company/mission.html"><i class="bi bi-info-circle-fill nm-icon"></i>Our Mission</a>
        <a href="{up}company/leadership.html"><i class="bi bi-people-fill nm-icon"></i>Leadership</a>
        <a href="{up}company/careers.html"><i class="bi bi-briefcase-fill nm-icon"></i>Careers</a>
        <a href="{up}company/contact.html"><i class="bi bi-envelope-fill nm-icon"></i>Contact</a>
      </div>
    </li>
  </ul>
  <div class="theme-dd" id="theme-dd">
    <button class="theme-dd-trigger" id="theme-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
      <span class="swatch" id="theme-dd-swatch" style="background:#2d8b8b;"></span>
      <span>Theme</span>
      <span class="caret"><i class="bi bi-chevron-down"></i></span>
    </button>
    <div class="theme-dd-menu" id="theme-dd-menu" role="listbox">
      <div class="theme-dd-label">Choose a theme</div>
      <button class="theme-opt active" data-t="ocean-depths"     data-c="#2d8b8b"><span class="swatch" style="background:#2d8b8b;"></span>Ocean Depths<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="sunset-boulevard" data-c="#e76f51"><span class="swatch" style="background:#e76f51;"></span>Sunset Boulevard<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="forest-canopy"    data-c="#2d4a2b"><span class="swatch" style="background:#2d4a2b;"></span>Forest Canopy<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="modern-minimalist" data-c="#708090"><span class="swatch" style="background:#708090;"></span>Modern Minimalist<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="golden-hour"      data-c="#f4a900"><span class="swatch" style="background:#f4a900;"></span>Golden Hour<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="arctic-frost"     data-c="#4a6fa5"><span class="swatch" style="background:#4a6fa5;"></span>Arctic Frost<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="desert-rose"      data-c="#b87d6d"><span class="swatch" style="background:#b87d6d;"></span>Desert Rose<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="tech-innovation"  data-c="#0066ff"><span class="swatch" style="background:#0066ff;"></span>Tech Innovation<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="botanical-garden" data-c="#4a7c59"><span class="swatch" style="background:#4a7c59;"></span>Botanical Garden<i class="bi bi-check2 check"></i></button>
      <button class="theme-opt"        data-t="midnight-galaxy"  data-c="#a490c2"><span class="swatch" style="background:#a490c2;"></span>Midnight Galaxy<i class="bi bi-check2 check"></i></button>
    </div>
  </div>
</nav>"""

FOOTER_HTML = """<footer>
  <div class="footer-brand">
    <span style="display:inline-flex;width:22px;height:22px;align-items:center;justify-content:center;">
      <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;overflow:visible;">
        <rect x="6"  y="22"   width="14" height="3.2" rx="1.2" fill="var(--accent)"  opacity="0.55"></rect>
        <rect x="6"  y="15.6" width="20" height="3.2" rx="1.2" fill="var(--accent)"  opacity="0.85"></rect>
        <rect x="6"  y="9.2"  width="11" height="3.2" rx="1.2" fill="var(--accent2)"></rect>
        <circle cx="22" cy="10.8" r="1.9" fill="var(--accent2)"></circle>
      </svg>
    </span>
    <span><span style="color:var(--text);">Stack </span><span style="color:var(--accent);">Lume</span></span>
  </div>
  <ul class="footer-links">
    <li><a href="{up}services/iam-pam-agent.html">Services</a></li>
    <li><a href="{up}products/promptshield-nexus.html">Products</a></li>
    <li><a href="{up}blog/ai-security.html">Blog</a></li>
    <li><a href="{up}company/mission.html">About</a></li>
    <li><a href="{up}company/careers.html">Careers</a></li>
    <li><a href="{up}company/contact.html">Contact</a></li>
  </ul>
  <div class="footer-copy">© 2026 Model Signal · <a href="https://stacklume.cloud" target="_blank" rel="noopener noreferrer">stacklume.cloud</a></div>
</footer>"""

PAGE_TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{title}</title>
<meta name="description" content="{description}">
<meta name="keywords" content="{keywords}">
<link rel="canonical" href="{canonical}">
<meta property="og:type" content="website">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{description}">
<meta property="og:url" content="{canonical}">
<meta property="og:site_name" content="Stack Lume">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{title}">
<meta name="twitter:description" content="{description}">
<link rel="icon" href="{up}favicon.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&family=DM+Serif+Display:ital@0;1&family=Playfair+Display:wght@700;800&family=Raleway:wght@300;400;600;700&family=Josefin+Sans:wght@300;400;600&family=Cormorant+Garamond:wght@400;600;700&family=Montserrat:wght@300;400;600;700&family=Lora:wght@400;600&family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{up}css/site.css">
<script type="application/ld+json">{schema}</script>
</head>
<body data-theme="ocean-depths">

<div id="page">
{nav}

<!-- HERO -->
<div class="hero">
  <div class="hero-grid"></div>
  <div class="hero-glow"></div>
  <div class="hero-inner">
    <div class="hero-badge">
      <div class="live-dot"></div>
      {eyebrow}
    </div>
    <h1>{h1_pre}<span class="accent">{h1_accent}</span>{h1_post}</h1>
    <p class="hero-sub">{hero_sub}</p>
    <div class="hero-actions">
      <a href="{up}company/contact.html" class="btn-primary">
        <i class="bi bi-play-circle-fill"></i>
        Get a Demo
      </a>
      <a href="{up}company/contact.html" class="btn-ghost">
        <i class="bi bi-calendar2-check"></i>
        Talk to Sales
      </a>
    </div>
{stat_strip}
  </div>
</div>

<div class="divider"></div>

{body}

<div class="divider"></div>

<!-- CTA -->
<div class="cta-wrap">
  <div class="cta-banner">
    <div>
      <div class="eyebrow">Ready to See It Live</div>
      <h2>{cta_h2}</h2>
      <p>{cta_p}</p>
    </div>
    <div class="cta-actions">
      <a href="{up}company/contact.html" class="btn-primary"><i class="bi bi-play-circle-fill"></i> Try Demo</a>
      <a href="{up}company/contact.html" class="btn-ghost"><i class="bi bi-calendar2-check"></i> Book a Call</a>
    </div>
  </div>
</div>

{footer}
</div>

<script src="{up}js/site.js"></script>
</body>
</html>
"""

def stat_strip(stats):
    if not stats:
        return ""
    cells = "".join(
        f'<div class="stat-cell"><div class="stat-value">{v[0]}<span class="unit">{v[1]}</span></div>'
        f'<div class="stat-label"><i class="bi {v[2]}" style="margin-right:4px;"></i>{v[3]}</div></div>'
        for v in stats
    )
    return f'    <div class="stat-strip">{cells}</div>\n'

def cards3(eyebrow, h2, desc, cards):
    items = "".join(
        f'<div class="svc-card"><div class="svc-icon"><i class="bi {c[0]}"></i></div>'
        f'<h3>{c[1]}</h3><p>{c[2]}</p></div>'
        for c in cards
    )
    return f"""<div class="section">
  <div class="eyebrow">{eyebrow}</div>
  <h2>{h2}</h2>
  <p class="section-desc">{desc}</p>
  <div class="card-grid-3">{items}</div>
</div>"""

def shelf(eyebrow, h2, desc, items):
    cards = "".join(
        f'<div class="prod-card"><div class="prod-icon"><i class="bi {c[0]}"></i></div>'
        f'<div><h4>{c[1]}</h4><p>{c[2]}</p>'
        + (f'<span class="prod-tag">{c[3]}</span>' if len(c) > 3 and c[3] else "")
        + '</div></div>'
        for c in items
    )
    return f"""<div class="section">
  <div class="eyebrow">{eyebrow}</div>
  <h2>{h2}</h2>
  <p class="section-desc">{desc}</p>
  <div class="shelf-grid">{cards}</div>
</div>"""

def terminal(title, lines):
    body = "<br>".join(lines)
    return f"""<div class="section">
  <div class="terminal">
    <div class="term-bar"><span class="td td-r"></span><span class="td td-a"></span><span class="td td-g"></span><span class="term-title">{title}</span></div>
    <div class="term-body">{body}</div>
  </div>
</div>"""

def faq_section(items):
    rows = "".join(
        f'<div class="prod-card"><div class="prod-icon"><i class="bi bi-question-circle-fill"></i></div>'
        f'<div><h4>{q}</h4><p>{a}</p></div></div>'
        for q, a in items
    )
    return f"""<div class="section">
  <div class="eyebrow">Frequently Asked</div>
  <h2>Questions teams ask before deploying</h2>
  <p class="section-desc">Straightforward answers about scope, integration, data handling, and rollout.</p>
  <div class="shelf-grid">{rows}</div>
</div>"""

def blog_grid(eyebrow, h2, desc, posts):
    cards = "".join(
        f'<div class="blog-card"><div class="blog-meta"><span class="blog-tag">{p[0]}</span> {p[1]}</div>'
        f'<h4>{p[2]}</h4><p>{p[3]}</p>'
        f'<a href="#" class="blog-link"><i class="bi bi-arrow-right-short"></i> Read article</a></div>'
        for p in posts
    )
    return f"""<div class="section">
  <div class="eyebrow">{eyebrow}</div>
  <h2>{h2}</h2>
  <p class="section-desc">{desc}</p>
  <div class="blog-grid">{cards}</div>
</div>"""

def schema_for(page_type, title, description, url):
    if page_type == "Product":
        return ('{"@context":"https://schema.org","@type":"Product","name":"%s",'
                '"description":"%s","url":"%s","brand":{"@type":"Brand","name":"Stack Lume"}}'
                % (title.replace('"', '\\"'), description.replace('"', '\\"'), url))
    if page_type == "Service":
        return ('{"@context":"https://schema.org","@type":"Service","name":"%s",'
                '"description":"%s","url":"%s","provider":{"@type":"Organization","name":"Stack Lume"}}'
                % (title.replace('"', '\\"'), description.replace('"', '\\"'), url))
    if page_type == "Organization":
        return ('{"@context":"https://schema.org","@type":"Organization","name":"Stack Lume",'
                '"url":"%s","description":"%s"}' % (url, description.replace('"', '\\"')))
    if page_type == "Blog":
        return ('{"@context":"https://schema.org","@type":"Blog","name":"%s",'
                '"description":"%s","url":"%s"}'
                % (title.replace('"', '\\"'), description.replace('"', '\\"'), url))
    return ('{"@context":"https://schema.org","@type":"WebPage","name":"%s",'
            '"description":"%s","url":"%s"}'
            % (title.replace('"', '\\"'), description.replace('"', '\\"'), url))


def build(rel_path, meta, body):
    """Render and write a page.

    meta keys: title, description, keywords, eyebrow, h1_pre, h1_accent, h1_post,
               hero_sub, stats, cta_h2, cta_p, schema_type
    """
    up = "../"
    canonical = f"{SITE_URL}/{rel_path}"
    page = PAGE_TEMPLATE.format(
        title=meta["title"],
        description=meta["description"],
        keywords=meta["keywords"],
        canonical=canonical,
        up=up,
        nav=NAV_HTML.format(up=up),
        footer=FOOTER_HTML.format(up=up),
        eyebrow=meta["eyebrow"],
        h1_pre=meta.get("h1_pre", ""),
        h1_accent=meta.get("h1_accent", ""),
        h1_post=meta.get("h1_post", ""),
        hero_sub=meta["hero_sub"],
        stat_strip=stat_strip(meta.get("stats", [])),
        cta_h2=meta.get("cta_h2", "See Stack Lume in your environment"),
        cta_p=meta.get("cta_p", "30-minute walkthrough with one of our solution architects."),
        body=body,
        schema=schema_for(meta.get("schema_type", "WebPage"), meta["title"], meta["description"], canonical),
    )
    out_path = os.path.join(ROOT, rel_path)
    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    with open(out_path, "w") as f:
        f.write(page)
    print(f"  wrote {rel_path}")


# ============================================================
# CONTENT — Services
# ============================================================

SERVICES = {
    "services/iam-pam-agent.html": dict(
        meta=dict(
            title="IAM & PAM Agent for AI Workloads | Stack Lume",
            description="Identity and privileged access management built for AI agents, model endpoints, and non-human identities. Eliminate stale credentials, enforce least privilege, and govern agent-to-tool access.",
            keywords="ai agent identity, pam for ai, non-human identity security, machine identity governance, least privilege ai, agent access control",
            eyebrow="Core Service · Identity & Access",
            h1_pre="Govern every ", h1_accent="non-human identity",
            h1_post=" your AI touches.",
            hero_sub="Stack Lume's IAM & PAM Agent inventories every machine identity, model endpoint, and autonomous agent — then enforces least privilege without breaking pipelines.",
            stats=[("1.4","K","bi-person-x-fill","Stale Accounts Eliminated"),
                   ("92","%","bi-shield-check","Privileged Access Reduction"),
                   ("48","h","bi-lightning-fill","Mean Time to Revocation"),
                   ("0","x","bi-cloud-slash-fill","Standing Secrets to LLMs")],
            schema_type="Service",
            cta_h2="Audit your AI identity sprawl in 30 minutes",
            cta_p="We'll map every agent, key, and service principal touching your model layer."),
        body=lambda: cards3(
            "Capabilities",
            "Identity for the agent era",
            "Human IAM doesn't translate to agents that spin up, call tools, and disappear in seconds. We rebuilt the primitives.",
            [("bi-person-bounding-box","Agent Inventory","Continuous discovery of every autonomous agent, copilot, and service principal touching production data."),
             ("bi-key-fill","Just-in-Time Secrets","Ephemeral credentials brokered per task. No standing API keys for LLM calls or tool invocations."),
             ("bi-arrow-down-up","Least-Privilege Drift","Detect and roll back over-broad scopes the moment an agent acquires more permission than it actually uses."),
             ("bi-clock-history","Session Replay","Full forensic timeline of which agent did what, with which token, against which dataset."),
             ("bi-people-fill","Human-in-the-Loop","Step-up approval for high-blast-radius actions: data exfiltration, schema writes, external API calls."),
             ("bi-arrow-repeat","Auto-Rotation","Keys, OAuth tokens, and federated trust rotated on velocity-based and risk-based triggers.")]
        ) + shelf(
            "How It Works",
            "From discovery to enforcement, in three weeks",
            "Most teams are surprised by what we find in the first week. Standing secrets to model endpoints are everywhere.",
            [("bi-search","1. Discover","Read-only connectors map every agent, key, role, and trust path across AWS IAM, Azure AD, Okta, GitHub, and your model gateway.","Week 1"),
             ("bi-graph-up","2. Risk-Score","Each identity is scored on blast radius, freshness, and unused entitlements. We surface the 5% that matter.","Week 2"),
             ("bi-shield-lock-fill","3. Enforce","Policy-as-code rolls out behind a feature flag. Reversible. Pipeline-safe. Audit-ready on day one.","Week 3")]
        ) + faq_section([
            ("Does this replace our existing IAM?", "No. We sit alongside Okta, Entra, AWS IAM, and Ping — adding agent-aware controls and ephemeral credential brokering for the workloads they weren't designed for."),
            ("How do you handle break-glass access?", "Standing privileged sessions are eliminated, but emergency-access workflows trigger time-bound elevations with mandatory video-attested approvals."),
            ("Will this slow down our agents?", "Credential brokering adds 6-12ms per call. Most teams see net latency improvement once we eliminate redundant token refresh storms."),
            ("What audit frameworks does this support?", "SOC 2 CC6, ISO 27001 A.9, NIST 800-53 AC, and the access-control sections of NIST AI RMF and ISO 42001."),
        ])
    ),

    "services/siem-triage.html": dict(
        meta=dict(
            title="SIEM Triage Automation | AI-Native SOC | Stack Lume",
            description="Cut alert queue volume by 78% with AI-native SIEM triage. Stack Lume correlates signal across identity, model, and data layers so analysts work the alerts that matter.",
            keywords="ai siem, soc automation, alert triage automation, security copilot, soar ai threats, automated incident response, ai detection engineering",
            eyebrow="Core Service · Detection & Response",
            h1_pre="Drown your alert queue, ", h1_accent="not your analysts",
            h1_post=".",
            hero_sub="Stack Lume's SIEM Triage agent reads your existing detections, enriches with model and identity context, and closes the noise — so your tier-1 team only sees real incidents.",
            stats=[("78","%","bi-graph-down-arrow","Alert Queue Reduction"),
                   ("4.2","x","bi-speedometer2","Faster Mean Time to Detect"),
                   ("11","min","bi-clock-fill","Median Triage Time"),
                   ("99.4","%","bi-bullseye","Precision on True Positives")],
            schema_type="Service",
            cta_h2="See triage on your real alerts",
            cta_p="Send us a sanitized day of SIEM output. We'll show you what we'd close."),
        body=lambda: cards3(
            "Why Teams Switch",
            "Analyst burnout has a root cause",
            "Most SIEM noise is duplicate, stale, or missing context. We fix the input, not the dashboard.",
            [("bi-funnel-fill","Cross-Source Correlation","Stitch identity events, model API calls, EDR, and cloud audit logs into single incident timelines."),
             ("bi-bullseye","Precision Triage","Each alert lands with confidence score, prior-art lookup, and recommended action — not raw JSON."),
             ("bi-stopwatch-fill","SLO-Driven","Configurable SLOs per alert class. Breaches escalate. Quiet alerts close themselves."),
             ("bi-journal-code","Detection-as-Code","Sigma, KQL, and Lucene rules version-controlled and tested before they reach production."),
             ("bi-link-45deg","Native Splunk/Sentinel/Chronicle","Read-only ingestion. We don't replace your SIEM — we make it tractable."),
             ("bi-shield-fill-exclamation","Auto-Response Playbooks","Reversible containment for the top 12 attack patterns: token revoke, session kill, network quarantine, snapshot.")]
        ) + terminal("triage.stacklume — live", [
            '<span class="tl-m">$</span> <span class="tl-t">stacklume triage --window 24h</span>',
            '<span class="tl-m">→</span> <span class="tl-a">Ingested</span>: 18,442 raw alerts',
            '<span class="tl-m">→</span> <span class="tl-a2">Correlated</span>: 1,203 incidents',
            '<span class="tl-m">→</span> <span class="tl-g">Auto-closed (low confidence)</span>: 14,381',
            '<span class="tl-m">→</span> <span class="tl-r">Escalated to Tier-2</span>: 47',
            '<span class="tl-m">→</span> <span class="tl-t">Avg time to first action</span>: 11m 03s',
        ]) + faq_section([
            ("Do you replace our SIEM?", "No. We integrate with Splunk, Sentinel, Chronicle, and Elastic. Your detections stay where they are; we add a triage layer on top."),
            ("How do you avoid auto-closing real attacks?", "Every closure is reversible and auditable. You set thresholds. We default to conservative — first 30 days mark-only, no auto-action."),
            ("Can we keep our SOAR?", "Yes. We export to Tines, XSOAR, and Swimlane via webhooks. We're a triage layer, not a runbook engine."),
            ("How long to deploy?", "Read-only telemetry connection in 2 days. Triage running in shadow mode in week 1. Production-driving by week 3."),
        ])
    ),

    "services/private-data-intel.html": dict(
        meta=dict(
            title="Private Data Intel | Sensitive Data Discovery for AI | Stack Lume",
            description="Discover, classify, and govern sensitive data flowing into LLMs, RAG pipelines, and fine-tuning sets. PII, PHI, PCI, and proprietary IP — found before it leaks.",
            keywords="sensitive data discovery, data classification ai, llm data leak prevention, rag security, data governance ai, pii pii detection llm",
            eyebrow="Core Service · Data Governance",
            h1_pre="Know exactly what data your ", h1_accent="models see",
            h1_post=".",
            hero_sub="Stack Lume's Private Data Intel scans every prompt, embedding, and training corpus — surfacing PII, secrets, and IP before they leave your VPC.",
            stats=[("0","x","bi-cloud-slash-fill","Data Leaves Your VPC"),
                   ("147","M","bi-database-fill-check","Records Classified Daily"),
                   ("23","types","bi-tag-fill","Built-in Sensitivity Classes"),
                   ("3","s","bi-lightning-fill","Median Scan Latency")],
            schema_type="Service",
            cta_h2="Scan your RAG corpus this week",
            cta_p="We'll find what your models are seeing — usually a surprising amount."),
        body=lambda: cards3(
            "Coverage",
            "Every place sensitive data ends up in AI",
            "Prompts, vector stores, fine-tuning sets, agent memory, eval datasets. We scan all of it.",
            [("bi-chat-quote-fill","Prompt Inspection","Inline scanning of user prompts and system messages before they hit the model — redact, block, or alert."),
             ("bi-broadcast-pin","Vector Stores","Continuous classification of embeddings in Pinecone, Weaviate, pgvector, and Chroma."),
             ("bi-database-lock","Training Corpora","Full-corpus scans for fine-tuning datasets. PII, PHI, copyrighted material, and secret leak detection."),
             ("bi-cpu-fill","Agent Memory","Long-term memory stores for agentic systems audited the same way you'd audit a database."),
             ("bi-arrow-left-right","Egress Controls","Block exfiltration to external model providers when sensitivity policy says no."),
             ("bi-flag-fill","Custom Classes","Detect your proprietary IP: source code, customer lists, board materials, M&A artifacts.")]
        ) + faq_section([
            ("How is this different from a DLP?", "Traditional DLP doesn't understand embeddings or model APIs. We classify vector representations and audit RAG retrieval — not just file movement."),
            ("Do you train models on our data?", "No. Classification runs on tenant-isolated infrastructure inside your VPC. No prompts or content leave your boundary."),
            ("What about HIPAA and GLBA?", "We're HIPAA BAA-ready and aligned to GLBA Safeguards Rule. Healthcare and financial deployments use a hardened compute pool."),
            ("Can we extend the classifiers?", "Yes. Bring your own regex, BYO model, or use our SDK to write custom Python classifiers for proprietary categories."),
        ])
    ),

    "services/model-mesh.html": dict(
        meta=dict(
            title="Model Mesh | Multi-Model Orchestration & Routing | Stack Lume",
            description="Route across OpenAI, Anthropic, open-weight, and on-prem models with policy, cost, and risk controls. One control plane for every model your stack calls.",
            keywords="ai model orchestration, llm gateway, multi-model routing, ai cost optimization, model governance, llm gateway open source",
            eyebrow="Core Service · Orchestration",
            h1_pre="One control plane for ", h1_accent="every model",
            h1_post=" you call.",
            hero_sub="Stack Lume's Model Mesh routes traffic across providers based on cost, latency, sensitivity, and risk — with full audit logs and instant provider failover.",
            stats=[("42","%","bi-piggy-bank-fill","Token Cost Reduction"),
                   ("12","ms","bi-stopwatch-fill","Routing Overhead"),
                   ("8","+","bi-diagram-3-fill","Providers Supported"),
                   ("99.99","%","bi-shield-check","Uptime With Failover")],
            schema_type="Service",
            cta_h2="Cut model spend without rewriting your code",
            cta_p="Drop-in OpenAI-compatible gateway. Day one savings, week one governance."),
        body=lambda: cards3(
            "Routing Policy",
            "Right model, right call, right cost",
            "Hard-coded provider clients are a liability. Mesh policies stay in version control, not in client code.",
            [("bi-graph-up-arrow","Cost-Aware Routing","Cheapest provider that meets quality SLO for the request class. Live re-pricing as provider rates change."),
             ("bi-shield-lock-fill","Sensitivity Routing","Sensitive prompts pinned to on-prem or BAA-covered providers. Public prompts free to roam."),
             ("bi-arrow-counterclockwise","Provider Failover","Sub-second cutover when OpenAI, Anthropic, or Bedrock degrades. Your users never see a 5xx."),
             ("bi-bar-chart-line-fill","Quality A/B","Shadow-traffic new models against production with paired evals before you flip the switch."),
             ("bi-key-fill","Centralized Auth","One credential per tenant. We broker provider keys. Devs don't see them."),
             ("bi-clipboard-data-fill","Per-Call Audit","Every request logged with prompt fingerprint, sensitivity class, provider, latency, and cost.")]
        ) + faq_section([
            ("Is this an OpenAI-compatible gateway?", "Yes. Drop-in /v1/chat/completions endpoint. Most apps need zero code change beyond the base URL."),
            ("Do you support our on-prem GPU cluster?", "Yes. vLLM, TGI, TensorRT-LLM, and Triton endpoints register the same way commercial providers do."),
            ("How do you handle streaming?", "Native SSE passthrough. Streaming routing decisions fire on first-token, not on completion."),
            ("Where does prompt data live?", "In your VPC. We log metadata; raw prompt content stays in tenant storage you control."),
        ])
    ),

    "services/retrieval-qa.html": dict(
        meta=dict(
            title="Retrieval QA | RAG Quality Monitoring | Stack Lume",
            description="Continuous evaluation of retrieval-augmented generation pipelines. Detect retrieval drift, hallucinated citations, and chunking failures before users do.",
            keywords="rag quality, retrieval evaluation, rag monitoring, llm evaluation, retrieval augmented generation testing, hallucination detection rag",
            eyebrow="Core Service · Evaluation",
            h1_pre="RAG that ", h1_accent="actually answers",
            h1_post=" the question.",
            hero_sub="Stack Lume's Retrieval QA continuously scores retrieval relevance, citation faithfulness, and answer groundedness — flagging drift the moment it starts.",
            stats=[("87","%","bi-bullseye","Retrieval Hit Rate Floor"),
                   ("0.03","%","bi-exclamation-triangle-fill","Hallucinated Citations"),
                   ("250","K","bi-graph-up","Answers Scored Daily"),
                   ("14","s","bi-stopwatch-fill","Time to Drift Alert")],
            schema_type="Service",
            cta_h2="Audit your RAG pipeline in 48 hours",
            cta_p="We'll score your live traffic and show you exactly where retrieval is failing."),
        body=lambda: cards3(
            "Quality Signals",
            "What we measure, in real time",
            "RAG breaks silently. By the time users complain, the chunking has been bad for weeks.",
            [("bi-search","Retrieval Precision","Live LLM-as-judge scoring of whether returned chunks actually answer the user's intent."),
             ("bi-link-45deg","Citation Faithfulness","Verify every claim in the answer is grounded in retrieved context — flag fabricated citations."),
             ("bi-graph-down","Drift Detection","Embedding-distribution monitoring catches model upgrades, corpus changes, and chunker regressions."),
             ("bi-bug-fill","Failure Replay","Failed queries auto-replayed against eval set. Regressions blocked at deploy."),
             ("bi-people-fill","Human Eval Loop","Weekly sampled review queues for SMEs. No more flying blind on niche domains."),
             ("bi-tag-fill","Per-Index Scoring","Multi-tenant deployments scored per index, per locale, per content type independently.")]
        ) + faq_section([
            ("Does this work with our existing RAG framework?", "Yes — LangChain, LlamaIndex, Haystack, custom. We instrument at the retrieval and generation boundaries."),
            ("How do you score without ground truth?", "Reference-free scoring (groundedness, faithfulness, context relevance) plus weekly SME review queues for calibration."),
            ("Can we use our own eval models?", "Yes. Bring your own judge model, or use our default ensemble. Multi-judge consensus reduces single-model bias."),
            ("Does this slow down responses?", "Scoring runs out-of-band on a sampled tail. Zero added latency to user-facing requests."),
        ])
    ),

    "services/guardrail-policy.html": dict(
        meta=dict(
            title="Guardrail Policy | LLM Output Controls | Stack Lume",
            description="Policy-as-code guardrails for LLM applications. Block prompt injection, jailbreaks, and policy violations before output reaches users — with full audit trails.",
            keywords="llm guardrails, prompt injection protection, llm policy enforcement, ai policy as code, llm firewall, jailbreak detection",
            eyebrow="Core Service · Output Safety",
            h1_pre="Guardrails that ", h1_accent="don't break UX",
            h1_post=".",
            hero_sub="Stack Lume's Guardrail Policy enforces output controls in version-controlled YAML — blocking jailbreaks, redacting PII, and stopping unsafe completions in 22ms.",
            stats=[("99.7","%","bi-shield-check","Prompt Injection Blocked"),
                   ("22","ms","bi-stopwatch-fill","Per-Call Overhead"),
                   ("78","+","bi-list-check","Prebuilt Policies"),
                   ("0","fp","bi-bullseye","False Positive Floor")],
            schema_type="Service",
            cta_h2="Test our guardrails on your worst prompts",
            cta_p="Send us your red-team corpus. We'll show you what gets through and what doesn't."),
        body=lambda: cards3(
            "Policy Library",
            "Out-of-the-box, then customize",
            "78 prebuilt policies aligned to OWASP LLM Top 10. Extend with YAML or code.",
            [("bi-shield-shaded","Injection Defense","Multi-layer detection for direct, indirect, and multi-turn prompt injection. Updated weekly with new attack signatures."),
             ("bi-eye-slash-fill","PII/PHI Redaction","Inline redaction with reversible tokenization. The model gets placeholders; the user gets cleartext."),
             ("bi-x-octagon-fill","Topic Boundaries","Block off-domain conversations, competitor mentions, or regulated advice (medical, legal, financial)."),
             ("bi-translate","Toxicity Scoring","Multilingual toxicity, harassment, and self-harm detection with configurable thresholds per audience."),
             ("bi-file-earmark-code-fill","Code Output Controls","Strip secrets from generated code. Block imports of vulnerable packages. Sign auto-generated commits."),
             ("bi-clipboard-check-fill","Audit Mode","Run any policy in shadow mode for 30 days. Tune thresholds against real traffic before enforcing.")]
        ) + faq_section([
            ("How fast is enforcement?", "P50 22ms, P99 95ms. Streaming-aware: we evaluate policies against partial outputs without waiting for completion."),
            ("Can we write custom policies?", "Yes. YAML for declarative rules, Python SDK for complex logic. Policies version-controlled and CI-tested."),
            ("Do you support OWASP LLM Top 10?", "All 10 categories ship with default policies. We update the library when new attack vectors surface."),
            ("How do you avoid breaking legitimate queries?", "Every policy ships with a precision/recall report against our 2M-prompt benchmark, plus your shadow-mode tuning data."),
        ])
    ),
}

# ============================================================
# CONTENT — Products
# ============================================================

PRODUCTS = {
    "products/promptshield-nexus.html": dict(
        meta=dict(
            title="PromptShield Nexus | Prompt Injection Defense Platform | Stack Lume",
            description="Real-time prompt injection, jailbreak, and indirect attack defense for production LLM applications. Block 99.7% of attacks with 22ms overhead.",
            keywords="prompt injection protection, llm firewall, jailbreak detection, indirect prompt injection, owasp llm top 10, llm runtime security",
            eyebrow="Product · Runtime Defense",
            h1_pre="Stop ", h1_accent="prompt injection",
            h1_post=" before it stops you.",
            hero_sub="PromptShield Nexus is the runtime defense layer for production LLMs — blocking direct, indirect, and multi-turn injection with the lowest false-positive rate in the category.",
            stats=[("99.7","%","bi-shield-check","Attacks Blocked"),
                   ("22","ms","bi-stopwatch-fill","P50 Overhead"),
                   ("4.2","B","bi-broadcast-pin","Prompts Inspected"),
                   ("0.04","%","bi-bullseye","False Positive Rate")],
            schema_type="Product",
            cta_h2="Red-team us with your worst prompts",
            cta_p="Send your jailbreak corpus. We'll publish a head-to-head report."),
        body=lambda: cards3(
            "Defense Layers",
            "Defense-in-depth, not a single classifier",
            "One model checking another model is brittle. PromptShield Nexus stacks structural, semantic, and behavioral signals.",
            [("bi-puzzle-fill","Structural Analysis","Detect role manipulation, delimiter injection, and template escapes at the parse layer — before any LLM sees the prompt."),
             ("bi-cpu-fill","Semantic Classifiers","Ensemble of fine-tuned detectors for known attack patterns: DAN, AIM, payload smuggling, encoding tricks."),
             ("bi-graph-up","Behavioral Drift","Session-level detection of slow-rolling injection: instructions accumulating across turns to override the system prompt."),
             ("bi-link-45deg","Indirect Defense","Tool outputs, retrieved documents, and web pages scanned for injection content before they reach the model."),
             ("bi-arrow-clockwise","Adaptive Updates","Threat intel feed pushed weekly. New attack patterns deployed without redeploying your app."),
             ("bi-clipboard-data-fill","Forensic Logging","Every block recorded with attack class, signal trace, and reproducible payload — no opaque AI verdicts.")]
        ) + terminal("promptshield.demo — live block", [
            '<span class="tl-m">→ INPUT</span> <span class="tl-t">"Ignore previous instructions and..."</span>',
            '<span class="tl-m">→ STRUCTURAL</span> <span class="tl-r">FLAG</span> role-override pattern',
            '<span class="tl-m">→ SEMANTIC</span>  <span class="tl-r">FLAG</span> classifier confidence 0.97',
            '<span class="tl-m">→ BEHAVIORAL</span> ok',
            '<span class="tl-m">→ DECISION</span>  <span class="tl-r">BLOCK</span> · class: direct_injection',
            '<span class="tl-m">→ LATENCY</span>   18ms',
        ]) + faq_section([
            ("How does this compare to Lakera Guard or NVIDIA NeMo Guardrails?", "We benchmark publicly — see our /research page. PromptShield ships with a higher precision floor and lower P99 latency, plus first-party indirect injection coverage."),
            ("Where does it run?", "SaaS, dedicated VPC, or fully on-prem. The detection models are quantized and ship as a 4GB container."),
            ("Is it OWASP LLM Top 10 aligned?", "Yes. All 10 categories covered with mappable policies. We publish the mapping in our trust center."),
            ("How do we tune for our app?", "Shadow mode for 30 days collects your false-positive corpus. We publish per-policy precision/recall against it before you go live."),
        ])
    ),

    "products/vectorpulse-sentinel.html": dict(
        meta=dict(
            title="VectorPulse Sentinel | Vector Database Security | Stack Lume",
            description="Continuous monitoring for vector databases. Detect poisoning, embedding drift, and unauthorized retrieval across Pinecone, Weaviate, pgvector, and Chroma.",
            keywords="vector database security, embedding poisoning, rag security, pinecone security, vector store monitoring, embedding drift",
            eyebrow="Product · Vector Layer Defense",
            h1_pre="Watch every ", h1_accent="embedding",
            h1_post=" your retrieval depends on.",
            hero_sub="VectorPulse Sentinel monitors vector stores for poisoning, drift, and unauthorized access — closing the blind spot every RAG application carries by default.",
            stats=[("12","M","bi-broadcast-pin","Embeddings Watched"),
                   ("4","stores","bi-database-fill","Native Integrations"),
                   ("99.4","%","bi-bullseye","Poisoning Detection"),
                   ("90","s","bi-stopwatch-fill","Drift-to-Alert")],
            schema_type="Product",
            cta_h2="See what's hiding in your vector store",
            cta_p="48-hour scan of your production index. We've never run one without finding something."),
        body=lambda: cards3(
            "Coverage",
            "Vector stores are the soft underbelly of RAG",
            "Most teams treat their vector DB as a black box. Attackers don't.",
            [("bi-shield-fill-exclamation","Poisoning Detection","Statistical and semantic anomaly detection for adversarial embeddings injected into retrieval corpora."),
             ("bi-graph-up","Drift Monitoring","Distribution shift alerts when embedding model upgrades silently corrupt retrieval quality."),
             ("bi-eye-slash-fill","Access Auditing","Who queried what, with what filter, returning what chunks. Full forensic timeline for every retrieval."),
             ("bi-tag-fill","Sensitivity Labeling","Auto-classify embeddings against your data taxonomy — block retrieval of sensitive content for unauthorized callers."),
             ("bi-arrow-repeat","Reindex Safety","Pre-flight checks before reembedding: detect content drift, missing source docs, and chunker regressions."),
             ("bi-people-fill","Multi-Tenant Isolation","Verify tenant boundary enforcement at the index level — catch cross-tenant leaks the platform missed.")]
        ) + faq_section([
            ("Which vector stores are supported?", "Pinecone, Weaviate, pgvector, Chroma, Qdrant, Milvus, OpenSearch k-NN, and Vespa. SDK available for custom stores."),
            ("How do you detect poisoning?", "Three-layer: statistical outlier detection on embedding distributions, semantic clustering for adversarial content, and lineage checks against source documents."),
            ("Does this require write access to our store?", "No. Read-only monitoring is the default. Optional remediation hooks require explicit policy grants."),
            ("How does it scale?", "Tested at 50M-vector indexes with sub-second alert latency. Sampled scanning for billion-vector deployments."),
        ])
    ),

    "products/agentchain-commander.html": dict(
        meta=dict(
            title="AgentChain Commander | Agent Orchestration & Governance | Stack Lume",
            description="Govern multi-agent systems at scale. Capability boundaries, tool-call audit, and chain-of-thought review for autonomous agent deployments.",
            keywords="ai agent governance, multi-agent orchestration, agent security, autonomous agent control, agent audit, langchain governance",
            eyebrow="Product · Agent Layer",
            h1_pre="Run agents in production ", h1_accent="without losing the plot",
            h1_post=".",
            hero_sub="AgentChain Commander enforces capability boundaries, audits every tool call, and rolls back unsafe chains — so your autonomous agents stay autonomous and accountable.",
            stats=[("3.2","M","bi-node-plus-fill","Agent Calls Governed"),
                   ("12","tools","bi-link-45deg","Native Frameworks"),
                   ("99.1","%","bi-shield-check","Boundary Enforcement"),
                   ("0","x","bi-cloud-slash-fill","Unbounded Loops")],
            schema_type="Product",
            cta_h2="Govern your first agent in a week",
            cta_p="Bring a LangGraph or CrewAI deployment. We'll have it under policy by Friday."),
        body=lambda: cards3(
            "Governance Surface",
            "Where agents go wrong, and how we stop it",
            "Agents fail in three places: capability creep, infinite loops, and bad tool calls. We instrument all three.",
            [("bi-bounding-box","Capability Boundaries","Per-agent allowlists for tools, data sources, and downstream agents. No more 'helpful assistant' calling production APIs."),
             ("bi-recycle","Loop Detection","Live monitoring for cyclic plans, escalating retries, and runaway token spend. Auto-pause with human handoff."),
             ("bi-eye-fill","Tool-Call Audit","Every external action recorded with full context: parent prompt, plan trace, parameters, response, cost, sensitivity."),
             ("bi-arrow-counterclockwise","Reversibility Checks","Mark write operations with rollback metadata. Catastrophic chains undone in one command."),
             ("bi-people-fill","Approval Gates","Step-up human review for high-blast-radius actions: writes to production, external sends, financial transactions."),
             ("bi-graph-up","Plan Quality Scoring","Live evaluation of plan coherence and goal alignment — agents that go off-task flagged before they cost money.")]
        ) + faq_section([
            ("Which agent frameworks?", "LangGraph, LangChain, CrewAI, AutoGen, OpenAI Assistants, Anthropic Tool Use, and custom orchestrators via OpenTelemetry traces."),
            ("How do you stop runaway loops?", "Configurable budget caps (tokens, dollars, wall-time, tool-call count) plus loop-pattern detection from observability traces."),
            ("Can agents call other agents?", "Yes — with explicit cross-agent ACLs. We graph the call topology and block paths that violate policy."),
            ("Does this work with on-prem agents?", "Yes. Deploys as a sidecar or gateway. No outbound traffic required."),
        ])
    ),

    "products/hallucination-forensics.html": dict(
        meta=dict(
            title="Hallucination Forensics | LLM Output Verification | Stack Lume",
            description="Detect, classify, and root-cause LLM hallucinations in production. Citation verification, claim grounding, and forensic timelines for every fabricated answer.",
            keywords="hallucination detection, llm output verification, citation grounding, ai factuality, llm evaluation, model hallucination",
            eyebrow="Product · Truth Layer",
            h1_pre="Make hallucinations ", h1_accent="rare and explainable",
            h1_post=".",
            hero_sub="Hallucination Forensics catches fabricated outputs in real time, traces them back to root cause, and gives your team a forensic record of every false claim your model made.",
            stats=[("96","%","bi-bullseye","Hallucinations Caught"),
                   ("12","s","bi-stopwatch-fill","Detection Latency"),
                   ("38","%","bi-graph-down-arrow","Repeat Rate Reduction"),
                   ("100","%","bi-clipboard-data-fill","Audit Coverage")],
            schema_type="Product",
            cta_h2="See where your model is making things up",
            cta_p="One week of production traffic. We'll show you the worst offenders."),
        body=lambda: cards3(
            "What We Catch",
            "Hallucination is a category, not a single failure",
            "Different hallucinations have different causes. We classify before we remediate.",
            [("bi-link-45deg","Citation Fabrication","Verify every cited URL, paper, or section actually exists and contains the claimed content."),
             ("bi-search","Claim Grounding","Score every factual statement against retrieved context. Flag claims with no source."),
             ("bi-people-fill","Identity Confusion","Catch person/place/product confusion: wrong CEO, wrong year, wrong jurisdiction."),
             ("bi-calculator-fill","Arithmetic Errors","Numeric claims re-evaluated symbolically. Bad math caught before users see it."),
             ("bi-graph-down-arrow","Drift Patterns","Cluster hallucinations by topic, prompt template, and model version — find systemic issues fast."),
             ("bi-arrow-counterclockwise","Root Cause","Trace each hallucination to retrieval miss, prompt ambiguity, model brittleness, or training-data gap.")]
        ) + faq_section([
            ("How is this different from generic LLM evals?", "Evals score samples; we monitor production. Detection runs on live traffic with sub-15s latency, and we provide forensic root-cause for each event."),
            ("What's the false-positive rate?", "2.1% on our public benchmark. We disclose calibration data per claim type — arithmetic is near-zero, identity confusion is the hardest."),
            ("Do you replace user feedback?", "No. We complement thumbs-down by catching the hallucinations users don't notice — and giving QA teams a queue to review."),
            ("How do we feed findings back to improve the model?", "Findings export to your eval set, fine-tuning corpus, or retrieval index as targeted negatives."),
        ])
    ),

    "products/compliance-guardian.html": dict(
        meta=dict(
            title="Compliance Guardian | AI Compliance Automation | Stack Lume",
            description="Continuous compliance for AI systems. NIST AI RMF, ISO 42001, EU AI Act, and SOC 2 mapped to live evidence — auto-collected from your infrastructure.",
            keywords="ai compliance automation, nist ai rmf, iso 42001, eu ai act compliance, soc 2 ai, ai governance platform, ai audit automation",
            eyebrow="Product · Compliance Automation",
            h1_pre="Audit-ready, ", h1_accent="continuously",
            h1_post=".",
            hero_sub="Compliance Guardian maps your AI controls to NIST AI RMF, ISO 42001, EU AI Act, SOC 2, and HIPAA — auto-collecting evidence from your stack so audits stop being projects.",
            stats=[("82","%","bi-graph-down-arrow","Audit Prep Reduction"),
                   ("14","frameworks","bi-list-check","Mapped Out-of-the-Box"),
                   ("100","%","bi-arrow-repeat","Continuous Evidence"),
                   ("3","wks","bi-calendar-check-fill","Time to First Report")],
            schema_type="Product",
            cta_h2="See your live compliance posture",
            cta_p="Connect your stack. We'll show you which controls are passing today."),
        body=lambda: cards3(
            "Frameworks Covered",
            "AI-aware mapping, not generic GRC",
            "Most GRC platforms have one row for 'AI'. We have 200, mapped to your actual model layer.",
            [("bi-shield-check","NIST AI RMF","All 19 subcategories across Govern, Map, Measure, Manage — mapped to live telemetry from your model gateway."),
             ("bi-globe-americas","EU AI Act","Risk-tier classification, transparency obligations, and conformity assessment evidence collected continuously."),
             ("bi-file-earmark-text-fill","ISO 42001","Annex A controls automated where automatable. Manual controls assigned, tracked, and evidence-stored."),
             ("bi-briefcase-fill","SOC 2 Type II","CC1–CC9 with AI-specific control narratives that auditors actually accept. AICPA TSC mapping included."),
             ("bi-hospital-fill","HIPAA","Security Rule + AI-specific PHI handling controls. BAA-ready architecture from day one."),
             ("bi-bank2","Sector Frameworks","FFIEC, NYDFS Part 500, FedRAMP, CMMC, and HITRUST AI-specific overlays.")]
        ) + faq_section([
            ("Do you replace Vanta or Drata?", "We extend them. If you have an existing GRC platform, we feed AI-specific evidence into it. If you don't, we can be the system of record."),
            ("How is the evidence collected?", "Read-only API integrations with your model gateway, vector store, agent platform, and CI/CD. Evidence is timestamped, hashed, and exportable."),
            ("How do auditors react?", "They've seen our evidence packs. We publish auditor-acceptance attestations for the Big 4 and the major AI-aware regional firms."),
            ("What about EU AI Act high-risk systems?", "Full Annex IV technical documentation generation, conformity assessment workflow, and post-market monitoring — out of the box."),
        ])
    ),

    "products/adaptive-honeymesh.html": dict(
        meta=dict(
            title="Adaptive Honeymesh | AI Deception & Threat Intel | Stack Lume",
            description="Deploy adaptive honeypots, decoy agents, and synthetic data traps to surface attackers targeting your AI infrastructure — before they reach production.",
            keywords="ai honeypot, deception technology ai, threat intelligence ai, adversarial detection, ai red team, decoy agents",
            eyebrow="Product · Deception & Threat Intel",
            h1_pre="Catch attackers ", h1_accent="touching your AI",
            h1_post=" first.",
            hero_sub="Adaptive Honeymesh deploys realistic decoy agents, synthetic prompts, and trap embeddings across your environment — surfacing threat actors before they reach production systems.",
            stats=[("142","day","bi-graph-down-arrow","Earlier Threat Detection"),
                   ("0","fp","bi-bullseye","False Positive Floor"),
                   ("38","decoys","bi-hdd-network-fill","Per Deployment"),
                   ("4","h","bi-stopwatch-fill","To First Hit")],
            schema_type="Product",
            cta_h2="Deploy your first decoy fleet this month",
            cta_p="Quiet, low-touch, attacker-tested. Live signal from week one."),
        body=lambda: cards3(
            "Decoy Surface",
            "Decoys that look like the real thing",
            "Static honeypots get fingerprinted. Adaptive Honeymesh decoys behave like real agents.",
            [("bi-robot","Decoy Agents","LLM-driven decoy agents indistinguishable from production. Chat, hold context, and respond to social engineering."),
             ("bi-database-fill","Trap Embeddings","Marked vectors seeded into stores. Any retrieval triggers high-fidelity threat alerts."),
             ("bi-key-fill","Honey Credentials","Synthetic API keys, OAuth tokens, and service principals scattered across realistic locations. Use-and-alert wired in."),
             ("bi-file-earmark-text-fill","Document Bait","Realistic but synthetic confidential docs in shared drives, S3 buckets, and SharePoint sites."),
             ("bi-people-fill","Persona Library","Decoy employee personas with email, calendar, and Slack presence. Phishing campaigns get caught here first."),
             ("bi-broadcast-pin","Threat Intel Feed","Every interaction enriched, attributed where possible, and pushed into your SIEM as high-confidence signal.")]
        ) + faq_section([
            ("Won't legitimate users hit the decoys?", "Decoys live outside legitimate user paths. We've run 3,400+ deployments with a sub-0.01% accidental-touch rate."),
            ("How do you avoid burning the decoys?", "Adaptive rotation, behavioral mimicry of real assets, and decoys that update their content on the same cadence as production."),
            ("What about insider threats?", "Decoys are intentionally tempting to insiders. We surface internal recon patterns alongside external attackers."),
            ("How does it integrate with our SIEM?", "Webhook, Splunk HEC, Sentinel, Chronicle, and S3-bucket export. Decoy hits arrive pre-enriched as high-confidence incidents."),
        ])
    ),
}

# ============================================================
# CONTENT — Industries
# ============================================================

INDUSTRIES = {
    "industries/financial-services.html": dict(
        meta=dict(
            title="AI Security for Financial Services | Stack Lume",
            description="AI security and compliance built for banks, insurers, and capital markets firms. SR 11-7, NYDFS Part 500, and FFIEC-aligned controls for production AI.",
            keywords="ai security financial services, sr 11-7, nydfs part 500 ai, model risk management, ffiec ai, banking ai security, ai compliance fintech",
            eyebrow="Industry · Financial Services",
            h1_pre="AI security built for ", h1_accent="regulated finance",
            h1_post=".",
            hero_sub="Model risk frameworks, real-time controls, and audit-ready evidence — purpose-built for SR 11-7, NYDFS, and FFIEC environments.",
            stats=[("18","banks","bi-bank2","Production Deployments"),
                   ("100","%","bi-shield-check","BAA & Data-Resident"),
                   ("SR 11-7","✓","bi-clipboard-check-fill","Mapped"),
                   ("4","wks","bi-calendar-check-fill","To First Audit")],
            schema_type="Service",
            cta_h2="Talk to our financial services team",
            cta_p="Bankers and former examiners on staff. We speak SR 11-7."),
        body=lambda: cards3(
            "Frameworks We Cover",
            "Compliance overlays that match how examiners read",
            "Generic AI governance doesn't pass FFIEC review. We map to the language regulators use.",
            [("bi-shield-fill-check","SR 11-7 Model Risk","Validation, monitoring, and challenger-model workflows mapped to OCC SR 11-7 model risk management guidance."),
             ("bi-bank2","NYDFS Part 500","23 NYCRR 500 controls — including the 2024 AI guidance — mapped to live evidence from your model layer."),
             ("bi-cash-stack","FFIEC IT Handbook","AI-specific overlays for the FFIEC Architecture, Infrastructure, and Operations booklet."),
             ("bi-globe","FCA & PRA","Equivalent UK supervisory expectations for model risk and AI governance covered for international banks."),
             ("bi-graph-up-arrow","Basel & Capital","Tie AI control posture to operational risk capital calculations where required."),
             ("bi-people-fill","SOC 2 + ISO 27001","Cross-mapped so your existing audit infrastructure picks up AI controls without doubling work.")]
        ) + shelf(
            "Use Cases We've Shipped",
            "From advisory copilots to fraud detection",
            "Concrete, production-grade deployments across the front, middle, and back office.",
            [("bi-chat-quote-fill","Wealth Management Copilots","Advisor copilots with full PII redaction, citation verification, and FINRA-aligned audit trails.","Front Office"),
             ("bi-shield-fill-exclamation","AML & Fraud Models","Model risk monitoring, drift detection, and challenger validation for production AML systems.","Risk"),
             ("bi-file-earmark-text-fill","Loan Underwriting","Adverse-action logging, fairness monitoring, and reason-code generation aligned to ECOA and Reg B.","Lending"),
             ("bi-cpu-fill","Trading Surveillance","Behavioral models for market abuse detection — tested against MAR, MAD II, and SEC Rule 15c3-5.","Capital Markets")]
        ) + faq_section([
            ("Are you OCC heritage SR 11-7 ready?", "Yes. Our compliance pack ships pre-mapped, and we have former Big 4 model risk consultants on the deployment team."),
            ("Do you support data residency?", "Single-tenant deployments in your VPC across all major US, EU, UK, APAC, and Canadian regions. No data leaves your boundary."),
            ("How do you handle adverse-action logging?", "Compliance Guardian captures reason codes, model version, input features, and decision rationale per inference — exportable as ECOA-compliant adverse action notices."),
            ("Can you sit alongside our existing model risk tooling?", "Yes. We integrate with most major MRM platforms (SAS Model Manager, Domino, Dataiku Govern) as a complementary AI-runtime layer."),
        ])
    ),

    "industries/healthcare.html": dict(
        meta=dict(
            title="AI Security for Healthcare | HIPAA & HITRUST AI | Stack Lume",
            description="AI security and compliance for hospitals, payers, and digital health. HIPAA-compliant, HITRUST-mapped, and built around PHI protection in clinical LLMs.",
            keywords="hipaa ai compliance, healthcare ai security, hitrust ai, clinical llm, phi llm protection, healthcare ai governance, medical device ai security",
            eyebrow="Industry · Healthcare",
            h1_pre="AI for healthcare, ", h1_accent="without the breach",
            h1_post=".",
            hero_sub="Stack Lume protects PHI across clinical copilots, ambient scribes, and patient-facing AI — HIPAA-compliant, HITRUST-aligned, and ready for the FDA's evolving AI guidance.",
            stats=[("11","systems","bi-hospital-fill","Live Deployments"),
                   ("100","%","bi-shield-fill-check","BAA-Backed"),
                   ("HITRUST","r2","bi-patch-check-fill","Aligned"),
                   ("0","leaks","bi-cloud-slash-fill","PHI Exfiltration")],
            schema_type="Service",
            cta_h2="Talk to our clinical AI team",
            cta_p="Former CISOs, clinical informaticists, and HIPAA Security Officers on staff."),
        body=lambda: cards3(
            "Coverage",
            "Where PHI meets LLM",
            "Generic DLP misses the AI surface. We don't.",
            [("bi-chat-square-quote-fill","Ambient Documentation","PHI redaction, encounter-level audit, and HIPAA-compliant routing for ambient scribe systems."),
             ("bi-clipboard2-pulse-fill","Clinical Decision Support","Hallucination detection and citation grounding for AI that informs care — with documented validation against clinical eval sets."),
             ("bi-people-fill","Patient-Facing Chat","Tier-by-tier guardrails for symptom checkers, member service, and care navigation — aligned to FDA SaMD where applicable."),
             ("bi-database-fill","EHR Integrations","Epic, Cerner, Meditech connectivity audited for least-privilege scope. No standing access for AI systems."),
             ("bi-shield-shaded","HIPAA Controls","§164.308–§164.314 mapped to live evidence. Risk analysis updated continuously as your AI surface changes."),
             ("bi-graph-up","HITRUST AI","HITRUST AI Risk Management v2.0 alignment with auto-collected evidence for r2 assessments.")]
        ) + faq_section([
            ("Are you HIPAA BAA-ready?", "Yes. Standard BAA available; redlines accepted. Single-tenant deployment in your VPC for PHI workloads."),
            ("How do you handle de-identification?", "Inline Safe Harbor de-id (18 identifiers) plus Expert Determination workflows. Reversible tokenization keeps users productive."),
            ("Do you cover ambient scribe products?", "Yes. We work with Abridge-style and DAX-style integrations and have ambient-specific eval and redaction policies."),
            ("What about FDA-regulated AI?", "We support 510(k) and De Novo evidence collection, and our hallucination forensics aligns to the FDA's predetermined change control plan guidance."),
        ])
    ),

    "industries/critical-infrastructure.html": dict(
        meta=dict(
            title="AI Security for Critical Infrastructure | Stack Lume",
            description="OT-aware AI security for energy, water, manufacturing, and transportation. NERC CIP, TSA, and CISA cross-sector mapping with air-gap-friendly deployment.",
            keywords="critical infrastructure ai security, nerc cip ai, ot ai security, ics ai, cisa ai, manufacturing ai security",
            eyebrow="Industry · Critical Infrastructure",
            h1_pre="AI security ", h1_accent="for the systems",
            h1_post=" we can't afford to lose.",
            hero_sub="Stack Lume secures AI deployments in energy, water, manufacturing, and transport — with OT-aware controls, air-gap-friendly delivery, and CISA cross-sector alignment.",
            stats=[("9","sectors","bi-bricks","CISA-Aligned"),
                   ("0","x","bi-router-fill","OT Network Touch"),
                   ("100","%","bi-shield-fill-check","Air-Gap Capable"),
                   ("4","h","bi-stopwatch-fill","Anomaly-to-Alert")],
            schema_type="Service",
            cta_h2="Talk to our OT security architects",
            cta_p="Veterans of NERC CIP audits, ISA/IEC 62443 deployments, and TSA security directives."),
        body=lambda: cards3(
            "Sector Coverage",
            "Sector-specific overlays, cross-sector core",
            "Each critical infrastructure sector has its own regulators. The AI risks rhyme.",
            [("bi-lightning-charge-fill","Energy & Utilities","NERC CIP-007/010/013 alignment for AI in BES operations, plus FERC Order 901-style audit readiness."),
             ("bi-droplet-fill","Water & Wastewater","EPA-aligned cybersecurity baseline for AI in SCADA-adjacent operations and customer service."),
             ("bi-train-front-fill","Transportation","TSA pipeline and rail security directives mapped, plus FAA AI/ML guidance for aviation operators."),
             ("bi-gear-fill","Manufacturing","ISA/IEC 62443 zone/conduit modeling extended to AI inference and agent traffic."),
             ("bi-broadcast","Telecommunications","CISA cross-sector controls plus sector-specific FCC AI security guidance for service providers."),
             ("bi-shield-fill-check","Federal Cross-Sector","NIST 800-82 OT controls and CISA Cross-Sector CPGs mapped to AI workloads.")]
        ) + faq_section([
            ("Can you deploy fully air-gapped?", "Yes. Detection models and policies ship as signed containers; updates delivered via offline media. We've shipped this pattern in nuclear and grid environments."),
            ("Do you touch OT/ICS networks?", "Never inline. We monitor the IT/OT boundary and AI workloads on the IT side. OT telemetry comes through one-way diodes where required."),
            ("How does this map to NERC CIP?", "CIP-007 (security management), CIP-010 (configuration change), CIP-013 (supply chain) — we provide auto-collected evidence and gap reports."),
            ("What about non-US operators?", "We support EU NIS2 and CER, UK NCSC CAF, Australian SOCI Act, and Canadian CCCS guidance for cross-border operators."),
        ])
    ),

    "industries/federal-defense.html": dict(
        meta=dict(
            title="AI Security for Federal & Defense | Stack Lume",
            description="FedRAMP-track AI security and governance for federal agencies and defense contractors. CMMC, IL4/IL5-ready, and aligned to NIST 800-53, 800-171, and the AI EO.",
            keywords="fedramp ai, ai for federal agencies, cmmc ai, il4 il5 ai, nist 800-53 ai, government ai security, defense ai security",
            eyebrow="Industry · Federal & Defense",
            h1_pre="AI for federal mission, ", h1_accent="cleared to deploy",
            h1_post=".",
            hero_sub="FedRAMP-track architecture, IL4/IL5-ready isolation, and full mapping to NIST 800-53 and the federal AI Executive Order — built for agencies and DIB primes.",
            stats=[("FedRAMP","Mod","bi-flag-fill","In Process"),
                   ("IL5","ready","bi-shield-fill-check","DoD CC SRG"),
                   ("CMMC","L3","bi-patch-check-fill","Aligned"),
                   ("100","%","bi-cloud-slash-fill","US Persons Only")],
            schema_type="Service",
            cta_h2="Talk to our federal team",
            cta_p="GovCloud architecture, ATO-experienced staff, and partner integrators in the GSA Schedule."),
        body=lambda: cards3(
            "Federal Frameworks",
            "Mapped to the controls your CISO is on the hook for",
            "AI guidance changes; the underlying control families don't. We track both.",
            [("bi-flag-fill","FedRAMP","Moderate baseline mapped, with High-baseline track in process. Continuous monitoring evidence ATO-ready."),
             ("bi-shield-fill-check","DoD IL4 / IL5","Cloud Computing SRG alignment with optional GovCloud-only deployment for IL5 workloads."),
             ("bi-patch-check-fill","CMMC 2.0","All Level 2 and Level 3 practices mapped — relevant to DIB primes and subs holding CUI."),
             ("bi-clipboard-data-fill","NIST 800-53 / 800-171","Full Rev 5 control mapping with evidence collection. AI-specific overlays from NIST AI 100 series."),
             ("bi-globe-americas","Federal AI EO","Executive Order 14110 and OMB M-24-10 obligations tracked: risk impact, public listing, redress."),
             ("bi-people-fill","Agency-Ready","Pre-built control narratives accepted by GSA, DHS, VA, HHS, and DoD components.")]
        ) + faq_section([
            ("Are you FedRAMP authorized?", "Moderate-baseline 3PAO assessment in process; current Agency ATO sponsors active. High-baseline path engaged."),
            ("Do you support classified networks?", "Air-gapped delivery available. IL5-grade isolation for the regulated AI surface; we work with cleared integrators for SCIF deployments."),
            ("How do you handle the federal AI EO obligations?", "Compliance Guardian ships an EO 14110 / OMB M-24-10 mapping that auto-generates the agency AI use case inventory and rights-impacting determinations."),
            ("Are you on the GSA Schedule?", "Through partner integrators today; direct schedule listing in progress. CSO-listed as an authorized cloud service offering."),
        ])
    ),
}

# ============================================================
# CONTENT — Blog (insights category landing pages)
# ============================================================

BLOG = {
    "blog/threat-intel.html": dict(
        meta=dict(
            title="AI Threat Intelligence | Stack Lume Blog",
            description="Latest research on AI-specific threats: prompt injection, model exfiltration, agent abuse, and adversarial machine learning. Real attacker tradecraft, distilled.",
            keywords="ai threat intelligence, llm threats, prompt injection research, ai red team, adversarial ml, ai security research",
            eyebrow="Blog · Threat Intelligence",
            h1_pre="What attackers are doing to ", h1_accent="AI systems",
            h1_post=" — right now.",
            hero_sub="Field reports, red-team writeups, and threat-actor tradecraft from the Stack Lume Threat Research team. New research weekly.",
            stats=[("212","reports","bi-newspaper","Published"),
                   ("4.2","B","bi-broadcast-pin","Prompts Analyzed"),
                   ("38","actors","bi-radioactive","Tracked"),
                   ("Wkly","new","bi-calendar-event-fill","Research")],
            schema_type="Blog",
            cta_h2="Get the weekly threat brief",
            cta_p="One email. Friday morning. The week's adversarial AI activity, distilled."),
        body=lambda: blog_grid(
            "Featured Research",
            "Latest from the Threat Research team",
            "Original investigations into how attackers compromise AI systems in production.",
            [("Threat","6 May 2026","Indirect Prompt Injection in Production RAG: A 2026 Field Survey","We sampled retrieval traffic across 142 production RAG deployments. The injection rate is higher than published estimates — and getting worse."),
             ("Tradecraft","2 May 2026","Anatomy of a Multi-Turn Jailbreak Campaign","One adversary spent 11 days incrementally drifting our decoy assistant past its guardrails. The full transcript and detection trace, annotated."),
             ("Research","28 Apr 2026","Vector Store Poisoning at Scale: 8 Real Attacks","From customer-support chatbots to medical RAG: eight cases where adversarial embeddings reached production retrieval indexes.")]
        ) + cards3(
            "Topics We Cover",
            "Where the threat surface is moving",
            "We focus on the threats analysts can actually detect with their existing tooling — extended.",
            [("bi-shield-shaded","Prompt Injection","Direct, indirect, and behavioral injection patterns observed in the wild."),
             ("bi-broadcast-pin","Vector & Retrieval","Embedding poisoning, retrieval manipulation, and corpus integrity attacks."),
             ("bi-robot","Agent Abuse","Tool-call hijacking, capability escalation, and chain-of-thought exfiltration."),
             ("bi-key-fill","Identity & Access","Token theft against model APIs, agent impersonation, and federated trust abuse."),
             ("bi-graph-down-arrow","Model Exfiltration","Membership inference, parameter extraction, and proprietary data recovery from LLMs."),
             ("bi-people-fill","Threat Actors","Tracked adversaries who are explicitly targeting AI infrastructure.")]
        )
    ),

    "blog/critical-infra.html": dict(
        meta=dict(
            title="Critical Infrastructure & AI | Stack Lume Blog",
            description="Field reports from AI deployments in energy, water, manufacturing, and transportation. OT-aware writeups, not vendor whitepapers.",
            keywords="critical infrastructure ai, ot ai security, ics ai, energy ai security, manufacturing ai, nerc cip blog",
            eyebrow="Blog · Critical Infrastructure",
            h1_pre="AI in the systems we ", h1_accent="can't afford to lose",
            h1_post=".",
            hero_sub="Practitioner-grade writeups from energy, water, manufacturing, and transport AI deployments — including the failure modes vendors won't publish.",
            stats=[("64","posts","bi-newspaper","Published"),
                   ("9","sectors","bi-bricks","Covered"),
                   ("38","plants","bi-building-fill","Visited"),
                   ("Mly","new","bi-calendar-event-fill","Field Reports")],
            schema_type="Blog",
            cta_h2="Subscribe to the OT/AI brief",
            cta_p="Monthly digest of AI deployments, incidents, and lessons across critical sectors."),
        body=lambda: blog_grid(
            "Recent Posts",
            "From the field",
            "Real deployments, real failure modes, real recoveries.",
            [("Energy","30 Apr 2026","An LLM Outage at a 1.4GW Combined-Cycle Plant: What Actually Happened","Operators rely on a copilot for runbook lookup. The provider went down for 38 minutes. Here's how the control room handled it — and what we changed after."),
             ("Water","21 Apr 2026","Customer-Service AI at a Mid-Sized Utility: Three Things We Got Wrong","Billing inquiries are easy until they aren't. The cases that broke our chatbot, ranked by how surprising they were."),
             ("Manufacturing","12 Apr 2026","Vision QA Models on the Line: Why We Killed Three Out of Four","Production AI is mostly graveyards. The decision criteria we use now to decide what reaches the factory floor.")]
        )
    ),

    "blog/ai-security.html": dict(
        meta=dict(
            title="AI Security Insights | Stack Lume Blog",
            description="Practitioner writing on AI security: architecture patterns, framework comparisons, and lessons from production. From engineers who ship the controls they write about.",
            keywords="ai security blog, llm security, ai security research, llm security best practices, ai security architecture, generative ai security",
            eyebrow="Blog · AI Security",
            h1_pre="AI security, ", h1_accent="written by people who ship",
            h1_post=" the controls.",
            hero_sub="Architecture patterns, framework comparisons, and incident retrospectives from the Stack Lume engineering team. No abstractions, no vendor fluff.",
            stats=[("184","posts","bi-newspaper","Published"),
                   ("2.1","M","bi-people-fill","Monthly Readers"),
                   ("28","authors","bi-pencil-fill","Engineering"),
                   ("Wkly","new","bi-calendar-event-fill","Posts")],
            schema_type="Blog",
            cta_h2="Subscribe to the AI Security Weekly",
            cta_p="Friday digest. The week's writing, ranked by what our team actually read."),
        body=lambda: blog_grid(
            "Recent Writing",
            "Latest from engineering",
            "Architecture, evaluation, incident response, and the boring middle of running AI in production.",
            [("Architecture","8 May 2026","Why We Stopped Sandboxing Agents and What We Do Instead","Sandboxes don't survive contact with multi-step plans. The capability-graph approach that replaced ours, and what it cost."),
             ("Patterns","4 May 2026","A Pattern Language for LLM Output Validation","We catalogued 31 output-validation patterns across our customers. The 8 that worked, the 14 that mostly worked, and the 9 to avoid."),
             ("Eval","29 Apr 2026","Reference-Free RAG Evaluation: A Year of Calibration Data","Twelve months of reference-free RAG scoring against ground truth. Where it works, where it falls apart, and how we calibrate.")]
        )
    ),

    "blog/briefings.html": dict(
        meta=dict(
            title="Executive Briefings | Stack Lume Blog",
            description="Briefings for CISOs, CIOs, and risk officers on AI security and compliance. Strategic, board-ready, and free of vendor jargon.",
            keywords="ciso ai briefing, ai security executive, ai risk board, ai governance briefing, ciso ai strategy",
            eyebrow="Blog · Executive Briefings",
            h1_pre="AI strategy briefings for ", h1_accent="the executive table",
            h1_post=".",
            hero_sub="Concise, board-ready writing on AI security, governance, and risk for CISOs, CIOs, GCs, and audit committees. No fluff, no vendor-speak.",
            stats=[("48","briefings","bi-newspaper","Published"),
                   ("1.3","K","bi-people-fill","Subscribed CISOs"),
                   ("12","mins","bi-stopwatch-fill","Median Read Time"),
                   ("Mly","new","bi-calendar-event-fill","Briefings")],
            schema_type="Blog",
            cta_h2="Subscribe to the executive brief",
            cta_p="Monthly. Twelve minutes. What you need before your next board meeting."),
        body=lambda: blog_grid(
            "Recent Briefings",
            "Latest executive reads",
            "Strategy and risk writing aimed at the C-suite and the board.",
            [("Strategy","5 May 2026","The CISO's Three-Year AI Security Roadmap","From AI inventory to runtime controls to board reporting. The phased plan our top customers are actually following."),
             ("Governance","27 Apr 2026","What Boards Should Be Asking About AI in 2026","Eight questions audit committees should be asking quarterly — and what good answers sound like."),
             ("Regulation","14 Apr 2026","EU AI Act, One Year In: What Actually Changed","High-risk classifications, conformity assessment realities, and the second-order effects on US deployments.")]
        )
    ),
}

# ============================================================
# CONTENT — Company
# ============================================================

COMPANY = {
    "company/mission.html": dict(
        meta=dict(
            title="Our Mission | Stack Lume",
            description="Stack Lume is on a mission to make AI security a solved problem for the enterprise. Founded by veterans of cloud security and applied ML, backed by category-leading investors.",
            keywords="stack lume mission, stack lume about, ai security company, ai security startup",
            eyebrow="About · Our Mission",
            h1_pre="See AI threats clearly. ", h1_accent="Respond",
            h1_post=" with confidence.",
            hero_sub="Stack Lume exists to give security teams the operator view they've never had into their AI systems — across identity, data, models, and agents — so the enterprise can ship AI without flinching.",
            stats=[("2024","fnd","bi-rocket-takeoff-fill","Founded"),
                   ("142","ppl","bi-people-fill","Team Globally"),
                   ("$84","M","bi-cash-stack","Series B"),
                   ("28","cnt","bi-globe-americas","Customer Countries")],
            schema_type="Organization",
            cta_h2="Build with us",
            cta_p="We're hiring across engineering, research, GTM, and operations."),
        body=lambda: cards3(
            "What We Believe",
            "Three convictions we don't compromise on",
            "We hire, build, and sell against these. They're load-bearing for everything that follows.",
            [("bi-eye-fill","Visibility before control","You can't govern what you can't see. Every product we ship starts with discovery, then enforcement — never the other way around."),
             ("bi-shield-fill-check","Reversibility by default","Security tools that can't be rolled back don't ship in production. Every action we take must be undoable, in seconds."),
             ("bi-people-fill","Respect the operator","SOC analysts, GRC leads, and platform engineers are smart, busy, and tired. We build for their workflow, not against it.")]
        ) + shelf(
            "Origin",
            "How we got here",
            "Stack Lume was founded by engineers who saw the AI gap from inside the trenches.",
            [("bi-buildings-fill","Started in 2024","Founded by veterans of AWS Security, Google Brain, Lacework, and federal cyber programs.","Year One"),
             ("bi-graph-up","Series A in 2025","$24M led by category-defining investors. First 30 enterprise customers in production.","Year Two"),
             ("bi-rocket-takeoff-fill","Series B in 2026","$84M to expand the platform across model, data, and identity surfaces. Federal and EU expansion underway.","Today")]
        )
    ),

    "company/leadership.html": dict(
        meta=dict(
            title="Leadership | Stack Lume",
            description="Stack Lume's leadership team — engineers, researchers, and operators who built the controls they ship. From cloud security, applied ML, federal cyber, and category-defining startups.",
            keywords="stack lume leadership, stack lume team, ai security executives, stack lume founders",
            eyebrow="About · Leadership",
            h1_pre="The people ", h1_accent="building Stack Lume",
            h1_post=".",
            hero_sub="Our leadership has spent careers shipping security and ML systems at scale — at AWS, Google, Lacework, In-Q-Tel, and the federal cyber community.",
            stats=[("142","ppl","bi-people-fill","Team Globally"),
                   ("38","%","bi-mortarboard-fill","Hold PhDs"),
                   ("4","cont","bi-globe-americas","Continents"),
                   ("18","yrs","bi-clock-history","Median Experience")],
            schema_type="Organization",
            cta_h2="Want to join the leadership team?",
            cta_p="We're hiring at the principal and director level across product, research, and field engineering."),
        body=lambda: cards3(
            "Founders & Officers",
            "The bench",
            "Operators who'd rather be in the diff than on the panel.",
            [("bi-person-circle","CEO · Mira Tan","Previously VP Security at a category-defining cloud-security firm. Carnegie Mellon CS, ex-AWS Security."),
             ("bi-person-circle","CTO · Daniel Okafor","Built model-platform infra at Google Brain and led ML safety at a frontier lab. Stanford ML, OWASP LLM TC member."),
             ("bi-person-circle","CISO · Sam Reilly","Federal cyber background. Led AI red-teaming for a major US agency. SANS faculty, GIAC GSE."),
             ("bi-person-circle","CRO · Priya Mehta","Built and scaled enterprise security GTM teams across two prior unicorns. Wharton MBA."),
             ("bi-person-circle","Chief Scientist · Dr. Aki Bergström","Adversarial ML researcher. 38 papers across NeurIPS, ICML, USENIX. Former MIT CSAIL."),
             ("bi-person-circle","Chief of Staff · Lin Wang","Operator's operator. Scaled three security companies from Series A to public. Stanford GSB.")]
        ) + shelf(
            "Investors & Partners",
            "Backed by category-defining investors",
            "Our cap table includes top-tier security, AI, and enterprise investors.",
            [("bi-graph-up-arrow","Lead Investors","Tier-1 enterprise software and security funds. Long-tenured holders who've built the category before.","Series B"),
             ("bi-people-fill","Strategic Partners","Hyperscaler and platform partners across AWS, Microsoft, and Google ecosystems.","Cloud"),
             ("bi-shield-shaded","Defense Partners","Cleared integrators in the GSA Schedule and DIB primes co-developing federal offerings.","Federal"),
             ("bi-mortarboard-fill","Research Partners","Active research collaborations with three major US universities and two EU institutes.","Academic")]
        )
    ),

    "company/careers.html": dict(
        meta=dict(
            title="Careers | Stack Lume",
            description="Build category-defining AI security with us. Stack Lume is hiring across engineering, research, security, GTM, and operations — remote-first, equity-heavy, mission-driven.",
            keywords="stack lume careers, ai security jobs, llm security engineer, ai security careers, stack lume hiring",
            eyebrow="Company · Careers",
            h1_pre="Help us make AI security a ", h1_accent="solved problem",
            h1_post=".",
            hero_sub="We're hiring across product, research, security, sales, and operations. Remote-first, high-ownership, and built around the work — not the optics.",
            stats=[("48","open","bi-briefcase-fill","Open Roles"),
                   ("28","cnt","bi-globe-americas","Hiring Countries"),
                   ("100","%","bi-house-fill","Remote-First"),
                   ("4.7","/5","bi-star-fill","Glassdoor")],
            schema_type="Organization",
            cta_h2="Talk to our recruiting team",
            cta_p="Don't see your role? We hire for trajectory more than title."),
        body=lambda: cards3(
            "How We Work",
            "What hiring here actually looks like",
            "Real expectations, not aspirational ones.",
            [("bi-house-fill","Remote-first","Headquarters in Brooklyn and Dublin. Most of the team works wherever they ship best."),
             ("bi-cash-stack","Equity-heavy","Top-quartile equity for our stage. We expect ownership; we compensate accordingly."),
             ("bi-graph-up-arrow","High ownership","Small teams, broad scope. Engineers ship. Researchers publish. Sellers pick the accounts."),
             ("bi-mortarboard-fill","Learning budget","$3K annual stipend for conferences, courses, and certifications. We pay for SANS, Black Hat, and DEF CON."),
             ("bi-heart-pulse-fill","Health & wellness","Top-tier US, UK, EU, and APAC plans. Mental-health support included. 16-week parental leave."),
             ("bi-airplane-fill","Quarterly offsite","Whole company together four times a year. Engineering team weeks rotate by hub.")]
        ) + shelf(
            "Open Roles",
            "Currently hiring",
            "Highlights from across the organization. Full list with descriptions on our greenhouse board.",
            [("bi-cpu-fill","Senior Threat Researcher","Lead adversarial ML research and our weekly threat intel publication. NeurIPS-grade work, production-grade impact.","Research · Remote"),
             ("bi-shield-shaded","Staff Security Engineer · Federal","Lead our IL5/FedRAMP track. TS/SCI required.","Engineering · Reston, VA"),
             ("bi-graph-up-arrow","Enterprise AE · Financial Services","Carry the FS book. Former bank insiders preferred.","GTM · NYC / Remote"),
             ("bi-people-fill","Field CISO","Strategic advisor to F500 customers. Former CISO experience required.","GTM · Remote"),
             ("bi-code-slash","Senior Software Engineer · Agent Platform","Build the AgentChain Commander surface. Distributed systems chops required.","Engineering · Remote"),
             ("bi-briefcase-fill","Director of GRC","Own the compliance roadmap and our Big-4 audit relationships. CISA/CISSP preferred.","Compliance · Remote")]
        )
    ),

    "company/contact.html": dict(
        meta=dict(
            title="Contact Sales | Stack Lume",
            description="Talk to Stack Lume. Sales, support, partnerships, press, and responsible disclosure — routed to the right team and answered within one business day.",
            keywords="stack lume contact, ai security demo, stack lume sales, stack lume support",
            eyebrow="Company · Contact",
            h1_pre="Get in touch with ", h1_accent="Stack Lume",
            h1_post=".",
            hero_sub="Whether you want a demo, an architecture review, or to report a vulnerability — we route every inquiry to a real human and reply within one business day.",
            stats=[("1","day","bi-clock-fill","Response SLO"),
                   ("28","cnt","bi-globe-americas","Customers In"),
                   ("100","%","bi-people-fill","Human Replies"),
                   ("24","/7","bi-shield-fill-check","Disclosure Intake")],
            schema_type="Organization",
            cta_h2="Prefer to talk live?",
            cta_p="Pick a 30-minute slot with our solutions team."),
        body=lambda: cards3(
            "Where to Reach Us",
            "Pick the door that fits",
            "Each channel routes to the right team. Responses are human, not auto-replies.",
            [("bi-cart-fill","Sales","Demos, pricing, architecture reviews, and procurement. Email sales@stacklume.cloud — replies within one business day."),
             ("bi-life-preserver","Support","Production issues for active customers. Use the in-product support widget for fastest routing — 24/7 coverage on incidents."),
             ("bi-handshake-fill","Partnerships","Tech alliances, OEM, channel, MSSP, and federal integrator inquiries. Email partners@stacklume.cloud."),
             ("bi-newspaper","Press & Analyst","Media, analyst briefings, and speaking requests. Email press@stacklume.cloud — embargo-friendly."),
             ("bi-shield-fill-exclamation","Responsible Disclosure","Vulnerability reports go to security@stacklume.cloud (PGP available). 24/7 intake, triage SLO of 4 hours."),
             ("bi-buildings-fill","Office Locations","HQ in Brooklyn, NY. EU operations in Dublin. Federal team in Reston, VA. Visits by appointment.")]
        ) + faq_section([
            ("How fast will I hear back?", "Sales and partnerships: one business day. Support for active customers: per your SLA. Disclosure intake: 4-hour triage SLO, 24/7."),
            ("Can I start with a free trial?", "Pilot programs available for qualified enterprise teams. Self-serve trial for the runtime products is in private beta — ask your AE."),
            ("Do you sign NDAs to scope?", "Yes. Mutual NDA available pre-call. Most architecture reviews start under NDA before pricing conversations."),
            ("How do I responsibly disclose a vulnerability?", "Email security@stacklume.cloud. PGP key on our /security page. Safe-harbor language and a published bug-bounty program at stacklume.cloud/security/disclosure."),
        ])
    ),
}


# ============================================================
# Build
# ============================================================

def main():
    all_pages = {}
    all_pages.update(SERVICES)
    all_pages.update(PRODUCTS)
    all_pages.update(INDUSTRIES)
    all_pages.update(BLOG)
    all_pages.update(COMPANY)

    print(f"Generating {len(all_pages)} pages...")
    for path, spec in all_pages.items():
        body = spec["body"]() if callable(spec["body"]) else spec["body"]
        build(path, spec["meta"], body)
    print(f"Done. {len(all_pages)} pages written.")

    # sitemap
    sitemap_paths = ["index.html"] + list(all_pages.keys())
    sitemap = ['<?xml version="1.0" encoding="UTF-8"?>',
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for p in sitemap_paths:
        loc = f"{SITE_URL}/{p}" if p != "index.html" else f"{SITE_URL}/"
        priority = "1.0" if p == "index.html" else ("0.9" if p.startswith(("services/", "products/")) else "0.7")
        sitemap.append(f"  <url><loc>{loc}</loc><changefreq>weekly</changefreq><priority>{priority}</priority></url>")
    sitemap.append("</urlset>")
    with open(os.path.join(ROOT, "sitemap.xml"), "w") as f:
        f.write("\n".join(sitemap))
    print("Wrote sitemap.xml")

    robots = "User-agent: *\nAllow: /\n\nSitemap: %s/sitemap.xml\n" % SITE_URL
    with open(os.path.join(ROOT, "robots.txt"), "w") as f:
        f.write(robots)
    print("Wrote robots.txt")


if __name__ == "__main__":
    main()
