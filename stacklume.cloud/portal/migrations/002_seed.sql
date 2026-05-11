-- Stack Compli — seed data: frameworks, controls, demo platform admin

BEGIN;

-- Frameworks
INSERT INTO frameworks (slug, name, short_name, summary) VALUES
('soc2',     'SOC 2 Type II',                          'SOC 2',    'AICPA Trust Services Criteria covering security, availability, processing integrity, confidentiality, and privacy.'),
('hipaa',    'HIPAA Security Rule',                    'HIPAA',    'US healthcare protected health information administrative, physical, and technical safeguards.'),
('iso27001', 'ISO/IEC 27001:2022',                     'ISO 27001','Information security management systems — Annex A controls and ISMS clauses.'),
('iso42001', 'ISO/IEC 42001:2023',                     'ISO 42001','First international standard for AI management systems.'),
('nist_airmf','NIST AI Risk Management Framework',     'NIST AI RMF','Govern, Map, Measure, Manage functions for trustworthy AI.'),
('eu_aiact', 'EU AI Act',                              'EU AI Act','EU regulation on AI risk classification, transparency, and conformity.'),
('fedramp',  'FedRAMP Moderate',                       'FedRAMP',  'US federal cloud baseline derived from NIST 800-53.'),
('cmmc',     'CMMC 2.0 Level 2',                       'CMMC L2',  'Cybersecurity Maturity Model Certification for the Defense Industrial Base.');

-- A small subset of controls per framework (illustrative, not exhaustive).
-- SOC 2 (using AICPA TSC numbering)
INSERT INTO controls (framework_id, code, title, description, sort_order)
SELECT id, c.code, c.title, c.description, c.sort_order FROM frameworks f, (VALUES
    ('CC1.1', 'Organizational integrity and ethical values', 'COSO control environment principle 1.', 1),
    ('CC2.1', 'Information requirements for internal control', 'Quality information to support the functioning of internal control.', 2),
    ('CC5.1', 'Selects and develops control activities', 'Designs control activities that mitigate risks.', 3),
    ('CC6.1', 'Logical access — implements controls', 'Restricts logical access to authorized users.', 4),
    ('CC6.2', 'Logical access — registration and authorization', 'Authorizes and registers users prior to granting access.', 5),
    ('CC6.6', 'Logical access — security in transit', 'Implements boundary protections.', 6),
    ('CC7.1', 'System operations — detects vulnerabilities', 'Detects and alerts on configuration changes and known vulnerabilities.', 7),
    ('CC7.2', 'System operations — monitors anomalies', 'Monitors for anomalies that indicate potential security events.', 8),
    ('CC8.1', 'Change management', 'Authorizes, designs, develops, and tests changes.', 9)
) AS c(code, title, description, sort_order)
WHERE f.slug = 'soc2';

-- HIPAA Security Rule (sample)
INSERT INTO controls (framework_id, code, title, description, sort_order)
SELECT id, c.code, c.title, c.description, c.sort_order FROM frameworks f, (VALUES
    ('164.308(a)(1)', 'Security management process', 'Risk analysis, risk management, sanction policy, information system activity review.', 1),
    ('164.308(a)(3)', 'Workforce security', 'Authorization and supervision, clearance, termination procedures.', 2),
    ('164.308(a)(4)', 'Information access management', 'Isolating health care clearinghouse functions, access authorization.', 3),
    ('164.310(a)(1)', 'Facility access controls', 'Contingency operations, facility security plan, access control and validation.', 4),
    ('164.312(a)(1)', 'Access control', 'Unique user identification, emergency access procedure, encryption, automatic logoff.', 5),
    ('164.312(b)',    'Audit controls', 'Implement hardware, software, and procedural mechanisms that record and examine activity.', 6),
    ('164.312(e)(1)', 'Transmission security', 'Integrity controls and encryption for ePHI in transit.', 7)
) AS c(code, title, description, sort_order)
WHERE f.slug = 'hipaa';

-- ISO 42001 (sample Annex A)
INSERT INTO controls (framework_id, code, title, description, sort_order)
SELECT id, c.code, c.title, c.description, c.sort_order FROM frameworks f, (VALUES
    ('A.2.2',  'AI policy', 'Establish, document, and approve an AI policy.', 1),
    ('A.3.2',  'AI system roles and responsibilities', 'Roles and responsibilities defined for AI development and use.', 2),
    ('A.4.2',  'Resources for AI systems', 'Identify and provide adequate resources for AI systems.', 3),
    ('A.5.2',  'AI risk assessment', 'Conduct AI risk assessments at planned intervals.', 4),
    ('A.6.1',  'AI system lifecycle management', 'Establish AI lifecycle management processes.', 5),
    ('A.7.2',  'Data quality for AI systems', 'Define and implement data quality criteria for AI.', 6),
    ('A.8.2',  'Information for users of AI systems', 'Provide information to users on AI capabilities and limits.', 7),
    ('A.9.2',  'Use of AI systems', 'Define responsible use of AI systems.', 8),
    ('A.10.2', 'Third-party relationships', 'Manage third-party AI relationships and dependencies.', 9)
) AS c(code, title, description, sort_order)
WHERE f.slug = 'iso42001';

-- NIST AI RMF (sample subcategories)
INSERT INTO controls (framework_id, code, title, description, sort_order)
SELECT id, c.code, c.title, c.description, c.sort_order FROM frameworks f, (VALUES
    ('GOVERN-1.1', 'Legal and regulatory requirements', 'Understand and apply legal/regulatory requirements involving AI.', 1),
    ('GOVERN-2.1', 'Roles and responsibilities', 'Roles and lines of communication related to AI risk are documented.', 2),
    ('MAP-1.1',    'Context establishment', 'Intended purposes, beneficial uses, contexts, and laws/regulations are understood.', 3),
    ('MEASURE-2.3','Performance assessment', 'AI system performance and effectiveness are evaluated.', 4),
    ('MANAGE-2.2', 'Mechanisms for sustained risk management', 'Resources are allocated for ongoing AI risk management.', 5)
) AS c(code, title, description, sort_order)
WHERE f.slug = 'nist_airmf';

COMMIT;
