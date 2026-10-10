#!/usr/bin/env python3
"""Audit structure and PHP include paths in an InfinityFree staging ZIP."""
from pathlib import Path
import sys
import zipfile

archive = Path(sys.argv[1])
with zipfile.ZipFile(archive) as z:
    names = set(z.namelist())
    assert "htdocs/index.php" in names
    assert "htdocs/.htaccess" in names
    assert "htdocs/_private/.htaccess" in names
    assert "SETUP/import-once-fresh-mysql.sql" in names
    assert "SETUP/private-env.example" in names
    for name in names:
        assert name.startswith(("htdocs/", "SETUP/")), name
        if name.startswith("htdocs/"):
            assert not name.endswith((".sql", ".env", ".py", ".sh")), name
            assert not any(segment in name for segment in ("/database/", "/scripts/", "/tests/")), name
        if name.startswith("htdocs/") and name.endswith(".php") and not name.startswith("htdocs/_private/"):
            body = z.read(name).decode()
            assert "dirname(__DIR__)" not in body, name
            if "/_private/src/" in body:
                assert "require __DIR__ . '/_private/src/" in body, name
    env = z.read("SETUP/private-env.example").decode()
    assert "APPLICATIONS_OPEN=false" in env
    assert "SESSION_SECURE=true" in env
    assert "DB_PASSWORD=REPLACE_" in env
    assert "Require all denied" in z.read("htdocs/_private/.htaccess").decode()
    schema = z.read("SETUP/import-once-fresh-mysql.sql").decode()
    for name in ("001_initial.sql", "005_vacancies_notifications.sql",
                 "006_vacancy_integrity.sql", "008_public_submission_guard.sql"):
        assert "-- BEGIN " + name in schema
print("InfinityFree deployment ZIP structure and secrets safety checks passed")
