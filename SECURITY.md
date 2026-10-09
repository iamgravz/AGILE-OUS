# Security and Vulnerability Reporting

AGILE OUS handles potentially sensitive student records and Student Welfare concerns.

## Report privately
Please do **not** disclose a vulnerability, actual student information, academic grades, uploaded documents, OAuth tokens, or exploit details in public issues or pull requests.

Use GitHub's **Report a vulnerability** private advisory feature, if enabled by repository administrators, or contact the designated maintainers privately through an organization-approved channel. Never send credentials or full student files in a report.

## Security requirements
- Never commit `.env`, `config.php`, secret keys, backups or data dumps.
- All protected actions must authorize role, action and record on the PHP server.
- Treat public uploads as untrusted; keep confidential documents outside the public web root, strictly validate and serve via authorized download endpoints.
- Protect PHP sessions, CSRF tokens, password hashes, sensitive logs and OAuth tokens.
- Human review is mandatory for consequential membership/academic decisions.
- Perform a privacy impact assessment and obtain PUP/organization authorization before real student data is used.

## Current posture
Repository setup and CI checks do not constitute a security certification. A full penetration assessment, MySQL-backed integration tests, and hosting/privacy review are required before production use.
