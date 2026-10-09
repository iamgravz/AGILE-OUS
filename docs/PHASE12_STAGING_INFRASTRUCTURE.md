# AGILE OUS — Phase 12 Isolated HTTPS Staging Package

**Status: IMPLEMENTED AS CODE, NOT DEPLOYED.** This change is based on the verified Phase 11 draft PR #12. The only running environments validated automatically are disposable GitHub-hosted synthetic test containers. A real staging host requires separate approval and secure provisioning.

## Scope and security model

Phase 12 introduces `deploy/staging/compose.yaml`, a PHP-FPM Dockerfile, dedicated Nginx HTTPS front-end and a **separate MySQL 8.4 staging database**. All service-to-service traffic stays on a private Docker network. MySQL and PHP-FPM publish **no host ports**. The only externally reachable container port is Nginx 443, bound by default to **127.0.0.1:8443** on the host.

**Do not change `STAGING_BIND_IP` to `0.0.0.0` or open a firewall without explicit staging-host, network and privacy authorization.** An approved TLS reverse proxy can route to the loopback listener, enforcing the staging hostname and administrative access restrictions. For real use, replace the synthetic/self-signed CI certificate with an authorized certificate for your staging hostname.

The Nginx document root is **public/** only. Only the front controller executes PHP; `app/`, `storage/`, `database/`, tests, jobs, private backups and config files are unavailable from HTTP. HTTPS-only cookies are enabled. The Nginx configuration uses TLS 1.2/1.3 and strict default response headers. Browser and penetration checks are still required.

## Provisioning — authorized operator only, synthetic data only

Prerequisites: managed Linux staging host, Docker Engine and Docker Compose v2, an approved domain and certificates, enough resources for PHP/MySQL/ClamAV, separately encrypted offsite backups, privileged staff onboarding, approved security and privacy controls, and a documented owner for incident recovery.

1. **Prepare protected host secret files, outside the Git repository** (chmod directory 0700, secret files 0600). The 7 required files are:
   - `mysql_root_password`: random high-entropy database root password;
   - `mysql_app_password`: separate nonroot application database password;
   - `mfa_key_b64`: base64 of a randomly generated 32-byte MFA encryption key;
   - `db_backup_key_b64`: a **different** base64 32-byte encrypted SQL backup key;
   - `file_backup_key_b64`: a **different** base64 32-byte private-file backup key;
   - `tls.crt` and `tls.key`: authorized certificate chain and matching private key.

   Produce encryption keys on the approved host with `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`. **Never paste generated key contents into tickets, chat logs, GitHub, CI artifacts, a .env committed file, or screenshots.** Keys should be stored in a separately backed-up secret manager. In this package, credentials are mounted into containers using Docker Compose secrets and read via a limited `*_FILE` resolver. Plaintext environment credentials and a simultaneously configured `*_FILE` credential are rejected.

2. Create a protected local file from `deploy/staging/staging.env.template` (suggested `deploy/staging/.staging.env`). Replace **every placeholder**, particularly:
   - `STAGING_URL`: final approved `https://` URL;
   - `AGILE_STAGING_SECRET_DIR`: absolute host path to seven protected secret files;
   - `AGILE_CLAMAV_DB_DIR`: host location of ClamAV definitions managed by the operator;
   - `STAGING_BIND_IP`: leave `127.0.0.1` until approved otherwise;
   - `STAGING_HTTPS_PORT`: default 8443.

3. In the approved checked-out staging release directory, validate the manifest and build. These examples assume the operator runs commands **from repository root**:

```bash
docker compose --env-file deploy/staging/.staging.env -f deploy/staging/compose.yaml config --quiet
docker compose --env-file deploy/staging/.staging.env -f deploy/staging/compose.yaml build app
docker compose --env-file deploy/staging/.staging.env -f deploy/staging/compose.yaml up -d database
```

4. Run migrations **explicitly** only against the isolated synthetic database after confirming the host and database credentials. No automatic migration runs during PHP or web startup:

```bash
docker compose --env-file deploy/staging/.staging.env \
  -f deploy/staging/compose.yaml --profile admin run --rm migrate
docker compose --env-file deploy/staging/.staging.env \
  -f deploy/staging/compose.yaml up -d app web
```

5. Validate the TLS certificate and the public homepage using the approved domain; reject insecure certificate overrides. Perform application-role manual UAT using **synthetic `@example.invalid` data** only. Check the private network, database isolation, prohibited PHP source access, rate limiting at the approved edge, MFA enrollment and privacy notice. Do not use `curl -k` on real staging.
6. Execute the **read-only staging readiness handoff**. This never grants production authorization:

```bash
docker compose --env-file deploy/staging/.staging.env \
  -f deploy/staging/compose.yaml exec -T app \
  php scripts/staging_readiness_handoff.php
```

7. Run malware scans only after up-to-date **real ClamAV definitions** have been mounted read-only in the scanner service. A scanner error keeps uploads blocked. The scanner does not start automatically:

```bash
docker compose --env-file deploy/staging/.staging.env \
  -f deploy/staging/compose.yaml --profile tools run --rm scanner
```

   Maintain an authorized host-scheduled scanner/signature-update process with monitoring; this PR does **not** create that schedule.

8. After a controlled staging recovery exercise, operators can run the encrypted SQL and private-file backup CLI scripts from protected containers, using the host secret manager and authenticated offsite storage. **Database and private files are not yet backed up atomically**; reconcile inventories and grade/welfare attachments from a consistent, isolated recovery window. Never store encrypted archives under Nginx's public directory.

## Why the services are gated

- Application front end is bound to loopback by default and has no database port forwarding.
- The database and PHP application communicate solely over an isolated private Docker network.
- The PHP container's root filesystem is read-only; only named private-data and encrypted-backup volumes are writable, with a limited tmpfs for PHP sessions. The app runs as nonroot `www-data`, drops Linux capabilities and cannot run a Docker daemon.
- The separate `migrate` service is under an **admin profile** and is never part of automatic `up`; the scanner is under the optional **tools profile**.
- Secrets are mounted as runtime files. The Docker image excludes `.env`, credentials, Git history and `storage/`.
- Staging defaults: Gmail disabled, external AI disabled, academic role transitions disabled, scheduled automation disabled, file-scanning set to ClamAV, full HTTPS secure sessions.
- App health fails if keys, private storage, MySQL connectivity or migration initialization are missing. Staging Nginx depends on a healthy PHP application.
- Existing source draft bylaws remain **unratified**, and preliminary academic flags are advisory only.

## Verified scope of automated tests

A new workflow `Staging Package Validation` does more than parse YAML. It provisions short-lived **synthetic** encryption keys, passwords and a one-day self-signed certificate inside a GitHub runner. It validates the manifest and Nginx TLS syntax, builds the PHP-FPM container, starts isolated MySQL, runs approved CI-only migrations, starts app/Nginx and checks:

- TLS negotiation with an explicitly trusted synthetic certificate (not `curl -k`);
- Public landing/privacy pages and denial of private source routes;
- Redirect of anonymous staff requests to authentication;
- Read-only technical handoff with `production_release_authorized = false`;
- Teardown of containers, volumes and runner-only secret files, even on failed CI jobs.

The Quality Gate tests the allowlisted file-backed secret loader: conflict, empty, oversize, missing and symlinked secret files all fail closed.

**CI self-signed certificates and static synthetic test passwords are for disposable CI only**, not example deployment credentials. The package is not evidence that an actual staging hostname is live.

## Outstanding acceptance requirements

**Production: NO-GO.** Before deploying with any real BSIT OUS records: secure hosting/domain approval, PUP/AGILE privacy and lawful-processing review, ratified bylaws, verified member identity and staff MFA onboarding, actual scanner operations, consistent disaster-recovery rehearsal, hardened edge protections, independent penetration/accessibility assessments, authorized Gmail and any AI processing, incident response and approved retention schedule.

**Merge policy:** Phase 12 is a dependent draft PR on #12. Phase 10 PR #10 and #11 overlap; do not blindly merge all three. Review the consolidated dependency chain and obtain independent maintainers' approval before merging to `main`.
