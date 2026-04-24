#!/usr/bin/env python3
"""Build a Drupal module inventory SQLite dataset for custom+contrib modules."""

from __future__ import annotations

import argparse
import json
import re
import sqlite3
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

import yaml


MODULE_NAME_RE = re.compile(r"[A-Za-z0-9_]+")


@dataclass(frozen=True)
class ModuleRecord:
    module_name: str
    source: str
    info_path: str
    enabled: int
    weight: int | None
    composer_package: str | None
    composer_in_require: int
    composer_in_require_dev: int
    composer_in_patches: int
    composer_present_any: int


@dataclass(frozen=True)
class DependencyRecord:
    module_name: str
    depends_on: str
    raw_dependency: str


@dataclass(frozen=True)
class ComposerSets:
    require: set[str]
    require_dev: set[str]
    patches: set[str]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Generate a queryable SQLite module inventory from Drupal "
            "core.extension.yml and module .info.yml files."
        )
    )
    parser.add_argument(
        "--db",
        default="docs/module_inventory.sqlite",
        help="Output SQLite database path.",
    )
    parser.add_argument(
        "--scope",
        default="custom,contrib",
        help="Comma-separated module scopes under web/modules (default: custom,contrib).",
    )
    parser.add_argument(
        "--core-extension",
        default="config/sync/core.extension.yml",
        help="Path to core.extension.yml.",
    )
    parser.add_argument(
        "--web-root",
        default="web",
        help="Path to Drupal web root.",
    )
    parser.add_argument(
        "--composer-json",
        default="composer.json",
        help="Path to composer.json.",
    )
    parser.add_argument(
        "--report",
        action="store_true",
        help="Print summary metrics and key findings.",
    )
    return parser.parse_args()


def run_parser_self_checks() -> None:
    checks = {
        "drupal:path": "path",
        "token:token": "token",
        'scheduler:scheduler ("^2")': "scheduler",
    }
    for raw, expected in checks.items():
        actual = normalize_dependency(raw)
        if actual != expected:
            raise ValueError(
                f"Dependency parser check failed for {raw!r}: "
                f"expected {expected!r}, got {actual!r}"
            )
    # contrib submodule package mapping check
    contrib_path = Path("web/modules/contrib/tome/modules/tome_sync/tome_sync.info.yml")
    actual_contrib_pkg = infer_contrib_composer_package(contrib_path)
    if actual_contrib_pkg != "drupal/tome":
        raise ValueError(
            "Composer package inference failed for contrib submodule "
            f"(expected 'drupal/tome', got {actual_contrib_pkg!r})"
        )
    # contrib root module package mapping check
    root_path = Path("web/modules/contrib/redirect/redirect.info.yml")
    actual_root_pkg = infer_contrib_composer_package(root_path)
    if actual_root_pkg != "drupal/redirect":
        raise ValueError(
            "Composer package inference failed for contrib root module "
            f"(expected 'drupal/redirect', got {actual_root_pkg!r})"
        )


def parse_scope(scope_arg: str) -> list[str]:
    scopes = [piece.strip() for piece in scope_arg.split(",") if piece.strip()]
    if not scopes:
        raise ValueError("At least one scope is required.")
    allowed = {"custom", "contrib"}
    invalid = [scope for scope in scopes if scope not in allowed]
    if invalid:
        raise ValueError(
            f"Invalid scope values: {', '.join(invalid)}. "
            f"Allowed values: {', '.join(sorted(allowed))}."
        )
    return scopes


def load_core_extension_map(core_extension_path: Path) -> dict[str, int]:
    with core_extension_path.open("r", encoding="utf-8") as handle:
        data = yaml.safe_load(handle) or {}
    module_map = data.get("module") or {}
    if not isinstance(module_map, dict):
        raise ValueError(f"Unexpected module map in {core_extension_path}")
    result: dict[str, int] = {}
    for key, value in module_map.items():
        try:
            result[str(key)] = int(value)
        except (TypeError, ValueError) as exc:
            raise ValueError(
                f"Non-numeric weight for module {key!r} in {core_extension_path}"
            ) from exc
    return result


def load_composer_sets(composer_json_path: Path) -> ComposerSets:
    with composer_json_path.open("r", encoding="utf-8") as handle:
        data = json.load(handle) or {}
    require = set((data.get("require") or {}).keys())
    require_dev = set((data.get("require-dev") or {}).keys())
    patches = set((((data.get("extra") or {}).get("patches") or {}).keys()))
    return ComposerSets(require=require, require_dev=require_dev, patches=patches)


def normalize_dependency(raw_dependency: str) -> str | None:
    text = str(raw_dependency).strip().strip("\"'")
    text = text.split("(", 1)[0].strip()
    if not text:
        return None
    if ":" in text:
        text = text.split(":", 1)[1].strip()
    match = MODULE_NAME_RE.search(text)
    if not match:
        return None
    return match.group(0)


def discover_module_info_files(repo_root: Path, web_root: Path, scopes: Iterable[str]) -> list[tuple[str, Path]]:
    records: list[tuple[str, Path]] = []
    for scope in scopes:
        base = repo_root / web_root / "modules" / scope
        if not base.exists():
            continue
        for info_path in sorted(base.rglob("*.info.yml")):
            if "/tests/" in info_path.as_posix():
                continue
            records.append((scope, info_path))
    return records


def infer_contrib_composer_package(info_path: Path) -> str | None:
    parts = info_path.as_posix().split("/")
    try:
        contrib_index = parts.index("contrib")
    except ValueError:
        return None
    package_index = contrib_index + 1
    if package_index >= len(parts):
        return None
    package_name = parts[package_index]
    if not package_name:
        return None
    return f"drupal/{package_name}"


def compute_composer_fields(
    module_name: str,
    source: str,
    info_path: Path,
    composer_sets: ComposerSets,
) -> tuple[str | None, int, int, int, int]:
    composer_package: str | None = None
    if source == "contrib":
        composer_package = infer_contrib_composer_package(info_path)
    elif source == "custom":
        explicit_package = f"drupal/{module_name}"
        if (
            explicit_package in composer_sets.require
            or explicit_package in composer_sets.require_dev
            or explicit_package in composer_sets.patches
        ):
            composer_package = explicit_package

    package_keys = {composer_package} if composer_package else {f"drupal/{module_name}"}

    in_require = 1 if package_keys & composer_sets.require else 0
    in_require_dev = 1 if package_keys & composer_sets.require_dev else 0
    in_patches = 1 if package_keys & composer_sets.patches else 0
    present_any = 1 if (in_require or in_require_dev or in_patches) else 0

    return composer_package, in_require, in_require_dev, in_patches, present_any


def extract_modules_and_dependencies(
    repo_root: Path,
    info_files: list[tuple[str, Path]],
    enabled_map: dict[str, int],
    composer_sets: ComposerSets,
) -> tuple[list[ModuleRecord], list[DependencyRecord]]:
    modules_by_name: dict[str, ModuleRecord] = {}
    dependencies: set[DependencyRecord] = set()

    for source, info_path in info_files:
        filename = info_path.name
        if not filename.endswith(".info.yml"):
            continue
        module_name = filename[: -len(".info.yml")]
        enabled = 1 if module_name in enabled_map else 0
        weight = enabled_map.get(module_name) if enabled else None
        (
            composer_package,
            composer_in_require,
            composer_in_require_dev,
            composer_in_patches,
            composer_present_any,
        ) = compute_composer_fields(
            module_name=module_name,
            source=source,
            info_path=info_path,
            composer_sets=composer_sets,
        )
        modules_by_name[module_name] = ModuleRecord(
            module_name=module_name,
            source=source,
            info_path=str(info_path.relative_to(repo_root)),
            enabled=enabled,
            weight=weight,
            composer_package=composer_package,
            composer_in_require=composer_in_require,
            composer_in_require_dev=composer_in_require_dev,
            composer_in_patches=composer_in_patches,
            composer_present_any=composer_present_any,
        )

        with info_path.open("r", encoding="utf-8") as handle:
            data = yaml.safe_load(handle) or {}
        raw_dependencies = data.get("dependencies") or []
        if isinstance(raw_dependencies, str):
            raw_dependencies = [raw_dependencies]
        elif isinstance(raw_dependencies, dict):
            raw_dependencies = list(raw_dependencies.keys())
        elif not isinstance(raw_dependencies, list):
            raw_dependencies = []

        for raw_dependency in raw_dependencies:
            normalized = normalize_dependency(str(raw_dependency))
            if not normalized:
                continue
            dependencies.add(
                DependencyRecord(
                    module_name=module_name,
                    depends_on=normalized,
                    raw_dependency=str(raw_dependency),
                )
            )

    modules = sorted(modules_by_name.values(), key=lambda row: row.module_name)
    dependency_rows = sorted(
        dependencies,
        key=lambda row: (row.module_name, row.depends_on, row.raw_dependency),
    )
    return modules, dependency_rows


def rebuild_database(
    db_path: Path,
    modules: list[ModuleRecord],
    dependencies: list[DependencyRecord],
) -> None:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path))
    try:
        conn.execute("PRAGMA foreign_keys = ON;")
        conn.executescript(
            """
            DROP VIEW IF EXISTS analysis_composer_presence;
            DROP VIEW IF EXISTS analysis_disabled_dependency;
            DROP VIEW IF EXISTS module_dependents;
            DROP TABLE IF EXISTS module_dependencies;
            DROP TABLE IF EXISTS modules;

            CREATE TABLE modules (
              module_name TEXT PRIMARY KEY,
              source TEXT NOT NULL,
              info_path TEXT NOT NULL,
              enabled INTEGER NOT NULL CHECK (enabled IN (0, 1)),
              weight INTEGER NULL,
              composer_package TEXT NULL,
              composer_in_require INTEGER NOT NULL CHECK (composer_in_require IN (0, 1)),
              composer_in_require_dev INTEGER NOT NULL CHECK (composer_in_require_dev IN (0, 1)),
              composer_in_patches INTEGER NOT NULL CHECK (composer_in_patches IN (0, 1)),
              composer_present_any INTEGER NOT NULL CHECK (composer_present_any IN (0, 1))
            );

            CREATE TABLE module_dependencies (
              module_name TEXT NOT NULL,
              depends_on TEXT NOT NULL,
              raw_dependency TEXT NOT NULL,
              PRIMARY KEY (module_name, depends_on, raw_dependency),
              FOREIGN KEY (module_name) REFERENCES modules(module_name)
            );

            CREATE INDEX idx_modules_enabled ON modules(enabled);
            CREATE INDEX idx_modules_composer_present_any ON modules(composer_present_any);
            CREATE INDEX idx_modules_source_composer_present_any ON modules(source, composer_present_any);
            CREATE INDEX idx_module_dependencies_depends_on ON module_dependencies(depends_on);
            CREATE INDEX idx_module_dependencies_module_name ON module_dependencies(module_name);

            CREATE VIEW module_dependents AS
            SELECT depends_on, module_name
            FROM module_dependencies;

            CREATE VIEW analysis_disabled_dependency AS
            SELECT
              m.module_name AS module_name,
              COUNT(md.module_name) AS dependent_count,
              SUM(CASE WHEN dep_mod.enabled = 1 THEN 1 ELSE 0 END) AS enabled_dependent_count
            FROM modules m
            LEFT JOIN module_dependencies md
              ON md.depends_on = m.module_name
            LEFT JOIN modules dep_mod
              ON dep_mod.module_name = md.module_name
            WHERE m.enabled = 0
            GROUP BY m.module_name
            HAVING COUNT(md.module_name) > 0;

            CREATE VIEW analysis_composer_presence AS
            SELECT
              module_name,
              source,
              composer_package,
              composer_in_require,
              composer_in_require_dev,
              composer_in_patches,
              composer_present_any
            FROM modules;
            """
        )
        conn.executemany(
            """
            INSERT INTO modules(
              module_name,
              source,
              info_path,
              enabled,
              weight,
              composer_package,
              composer_in_require,
              composer_in_require_dev,
              composer_in_patches,
              composer_present_any
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            [
                (
                    row.module_name,
                    row.source,
                    row.info_path,
                    row.enabled,
                    row.weight,
                    row.composer_package,
                    row.composer_in_require,
                    row.composer_in_require_dev,
                    row.composer_in_patches,
                    row.composer_present_any,
                )
                for row in modules
            ],
        )
        conn.executemany(
            """
            INSERT INTO module_dependencies(module_name, depends_on, raw_dependency)
            VALUES (?, ?, ?)
            """,
            [
                (
                    row.module_name,
                    row.depends_on,
                    row.raw_dependency,
                )
                for row in dependencies
            ],
        )
        conn.commit()
    finally:
        conn.close()


def print_report(db_path: Path) -> None:
    conn = sqlite3.connect(str(db_path))
    try:
        def scalar(sql: str) -> int:
            row = conn.execute(sql).fetchone()
            return int(row[0] if row and row[0] is not None else 0)

        present_count = scalar("SELECT COUNT(*) FROM modules;")
        enabled_count = scalar("SELECT COUNT(*) FROM modules WHERE enabled = 1;")
        disabled_count = scalar("SELECT COUNT(*) FROM modules WHERE enabled = 0;")
        disabled_dep_any_count = scalar("SELECT COUNT(*) FROM analysis_disabled_dependency;")
        disabled_dep_enabled_count = scalar(
            """
            SELECT COUNT(*)
            FROM analysis_disabled_dependency
            WHERE enabled_dependent_count > 0;
            """
        )
        composer_present_any_count = scalar(
            "SELECT COUNT(*) FROM modules WHERE composer_present_any = 1;"
        )
        composer_absent_any_count = scalar(
            "SELECT COUNT(*) FROM modules WHERE composer_present_any = 0;"
        )

        print(f"Database: {db_path}")
        print(f"Present modules: {present_count}")
        print(f"Enabled modules: {enabled_count}")
        print(f"Disabled modules: {disabled_count}")
        print(f"Disabled + dependency (any dependents): {disabled_dep_any_count}")
        print(
            "Disabled + dependency (enabled dependents): "
            f"{disabled_dep_enabled_count}"
        )
        print(f"Composer present (any section): {composer_present_any_count}")
        print(f"Composer absent (any section): {composer_absent_any_count}")
        print("")
        print("Disabled modules that are dependencies (any dependents):")
        for module_name, dependent_count, enabled_dependent_count in conn.execute(
            """
            SELECT module_name, dependent_count, enabled_dependent_count
            FROM analysis_disabled_dependency
            ORDER BY module_name;
            """
        ):
            print(
                f"- {module_name} "
                f"(dependents={dependent_count}, enabled_dependents={enabled_dependent_count})"
            )
        print("")
        print("Contrib modules absent from composer (any section):")
        absent_contrib = list(
            conn.execute(
                """
                SELECT module_name
                FROM modules
                WHERE source = 'contrib' AND composer_present_any = 0
                ORDER BY module_name;
                """
            )
        )
        if not absent_contrib:
            print("- none")
        else:
            for (module_name,) in absent_contrib:
                print(f"- {module_name}")
    finally:
        conn.close()


def main() -> None:
    args = parse_args()
    run_parser_self_checks()

    repo_root = Path.cwd()
    db_path = repo_root / args.db
    core_extension_path = repo_root / args.core_extension
    composer_json_path = repo_root / args.composer_json
    web_root = Path(args.web_root)
    scopes = parse_scope(args.scope)

    enabled_map = load_core_extension_map(core_extension_path)
    composer_sets = load_composer_sets(composer_json_path)
    info_files = discover_module_info_files(repo_root, web_root, scopes)
    modules, dependencies = extract_modules_and_dependencies(
        repo_root=repo_root,
        info_files=info_files,
        enabled_map=enabled_map,
        composer_sets=composer_sets,
    )
    rebuild_database(db_path, modules, dependencies)

    if args.report:
        print_report(db_path)


if __name__ == "__main__":
    main()
