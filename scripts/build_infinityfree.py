#!/usr/bin/env python3
"""Build a shared-hosting upload package; no credentials are ever included."""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public"
PRIVATE_SOURCE = ROOT / "src"
MIGRATIONS = ROOT / "database"
REQUIRE_PATTERN = re.compile(r"require\s+dirname\(__DIR__\)\s*\.\s*'/src/([^']+)'\s*;")
PUBLIC_HTACCESS = """Options -Indexes
DirectoryIndex index.php
<IfModule mod_headers.c>
 Header always set X-Content-Type-Options "nosniff"
 Header always set X-Frame-Options "DENY"
 Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
<FilesMatch "(?i)^(\\.env|\\.git|composer\\.(json|lock)|.*\\.(sql|ini|log|bak))$">
 Require all denied
</FilesMatch>
"""
PRIVATE_HTACCESS = """# Private PHP application code and credentials: NEVER serve to a browser.
<IfModule mod_authz_core.c>
 Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
 Order allow,deny
 Deny from all
</IfModule>
Options -Indexes
"""
ENV_EXAMPLE = """# Keep this file PRIVATE. Rename to .env and put it ONLY in htdocs/_private/.
# DO NOT upload or commit this example with real credentials.
APP_ENV=testing
APP_DEBUG=false
APP_TIMEZONE=Asia/Manila
APPLICATIONS_OPEN=false
SESSION_SECURE=true
DB_HOST=REPLACE_WITH_MYSQL_HOST_FROM_INFINITYFREE_CONTROL_PANEL
DB_PORT=3306
DB_NAME=REPLACE_WITH_FULL_DATABASE_NAME
DB_USER=REPLACE_WITH_MYSQL_USERNAME
DB_PASSWORD=REPLACE_WITH_DATABASE_PASSWORD
# Generate both separately using a trusted offline random generator (32 bytes / 64 hex).
APP_KEY=REPLACE_WITH_64_RANDOM_HEX_CHARACTERS
WELFARE_ENCRYPTION_KEY=REPLACE_WITH_ANOTHER_64_RANDOM_HEX_CHARACTERS
"""


def build(output: Path) -> None:
    if not SOURCE.is_dir() or not PRIVATE_SOURCE.is_dir():
        raise RuntimeError("Run within AGILE-OUS repository")
    php_files = sorted(SOURCE.rglob("*.php"))
    if not php_files:
        raise RuntimeError("No PHP public endpoints found")
    changes: dict[str, bytes] = {
        "htdocs/.htaccess": PUBLIC_HTACCESS.encode(),
        "htdocs/_private/.htaccess": PRIVATE_HTACCESS.encode(),
        "SETUP/private-env.example": ENV_EXAMPLE.encode(),
    }

    for file in php_files:
        if file.parent != SOURCE:
            raise RuntimeError(f"New public subdirectory requires explicit packaging review: {file}")
        old = file.read_text(encoding="utf-8")
        converted, count = REQUIRE_PATTERN.subn(
            lambda m: "require __DIR__ . '/_private/src/" + m.group(1) + "';",
            old,
        )
        if "dirname(__DIR__)" in converted or "/src/" in old and count == 0:
            raise RuntimeError(f"Unreviewed source path in {file.name}")
        changes[f"htdocs/{file.name}"] = converted.encode("utf-8")

    for file in sorted(PRIVATE_SOURCE.glob("*.php")):
        changes[f"htdocs/_private/src/{file.name}"] = file.read_bytes()
    if not any(p.startswith("htdocs/_private/src/") for p in changes):
        raise RuntimeError("Private source tree not found")

    migration_files = sorted(MIGRATIONS.glob("0*.sql"))
    if len(migration_files) < 8:
        raise RuntimeError("Unexpected SQL migration count; review before packaging")
    migration_chunks = ["-- AGILE OUS TEST-ONLY schema import\n-- Import ONCE into a NEW EMPTY database using InfinityFree phpMyAdmin.\n"]
    for file in migration_files:
        migration_chunks.append(f"\n-- BEGIN {file.name}\n")
        migration_chunks.append(file.read_text(encoding="utf-8").rstrip() + "\n")
        migration_chunks.append(f"-- END {file.name}\n")
    changes["SETUP/import-once-fresh-mysql.sql"] = "".join(migration_chunks).encode()

    # Never include secrets, CLI scripts, archives, or administrative SQL under the web root.
    forbidden = (".env", ".git", "database", "scripts", "tests", "README", ".sql")
    for name in changes:
        if name.startswith("htdocs/") and (
            name.endswith(".sql") or name.endswith("/.env") or "/scripts/" in name
        ):
            raise RuntimeError(f"Refusing unsafe public file: {name}")
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name in sorted(changes):
            archive.writestr(name, changes[name])
    print(f"Created {output} ({len(changes)} entries). No secrets included.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    build(args.output)
