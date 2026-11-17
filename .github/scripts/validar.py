#!/usr/bin/env python3
"""Gate de pré-publicação do plg_captcha_recaptcha.

Roda na raiz do repositório (CI e local). Sai com código != 0 se houver FAIL.
AVISO/INFO são heurísticas e não bloqueiam.

Verificações (FAIL):
  - manifesto e joomla.asset.json válidos e com a mesma versão
  - todo arquivo/pasta declarado no manifesto existe
  - namespace do manifesto confere com os arquivos PHP de src/
  - php -l em todos os .php (obrigatório no CI: REQUIRE_PHP=1)
  - node --check no JavaScript (obrigatório no CI: REQUIRE_NODE=1)
  - guarda _JEXEC em todo PHP
  - index.html em cada pasta do plugin (convenção Joomla)
  - idiomas obrigatórios presentes (pt-BR, en-GB, es-ES, de-DE, fr-FR, zh-CN, ja-JP, it-IT)
  - paridade de idiomas: mesmas chaves em todos os idiomas, .ini e .sys.ini presentes
  - toda chave PLG_* usada no PHP/manifesto existe em todos os idiomas
"""
from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PLUGIN_DIR = ROOT / "plugins" / "plg_captcha_recaptcha"
MANIFEST = PLUGIN_DIR / "recaptcha.xml"
BASE_LANG = "pt-BR"
# Idiomas obrigatórios (plugins/DEVELOPMENT_GUIDELINES.md). en-US é extra e opcional.
REQUIRED_LANGS = ["pt-BR", "en-GB", "es-ES", "de-DE", "fr-FR", "zh-CN", "ja-JP", "it-IT"]

fails: list[str] = []
warns: list[str] = []


def fail(msg: str) -> None:
    fails.append(msg)
    print(f"FAIL  {msg}")


def warn(msg: str) -> None:
    warns.append(msg)
    print(f"AVISO {msg}")


def ok(msg: str) -> None:
    print(f"OK    {msg}")


def rel(p: Path) -> str:
    return p.relative_to(ROOT).as_posix()


def plugin_files(suffix: str) -> list[Path]:
    return sorted(p for p in PLUGIN_DIR.rglob(f"*{suffix}") if p.is_file())


def run_tool(label: str, cmd_name: str, args_for, files: list[Path], required: bool) -> None:
    exe = shutil.which(cmd_name)
    if not exe:
        (fail if required else warn)(f"{cmd_name} não encontrado — {label} não executado")
        return
    bad = 0
    for f in files:
        r = subprocess.run([exe, *args_for(f)], capture_output=True, text=True)
        if r.returncode != 0:
            bad += 1
            fail(f"{label}: {rel(f)}\n      {(r.stdout + r.stderr).strip()}")
    if not bad:
        ok(f"{label}: {len(files)} arquivo(s)")


# --------------------------------------------------------------------------- manifesto
def check_manifest() -> ET.Element | None:
    if not MANIFEST.is_file():
        fail(f"manifesto não encontrado: {rel(MANIFEST)}")
        return None
    try:
        root = ET.parse(MANIFEST).getroot()
    except ET.ParseError as e:
        fail(f"manifesto com XML inválido: {e}")
        return None
    ok("manifesto é XML válido")

    if root.get("type") != "plugin" or root.get("group") != "captcha":
        fail('manifesto: <extension type="plugin" group="captcha"> esperado')

    declared = []
    files = root.find("files")
    if files is not None:
        for el in files:
            declared.append((PLUGIN_DIR / (el.text or "").strip(), el.tag))
    media = root.find("media")
    if media is not None:
        folder = media.get("folder", "media")
        for el in media:
            declared.append((PLUGIN_DIR / folder / (el.text or "").strip(), el.tag))
    missing = [rel(p) for p, _ in declared if not p.exists()]
    if missing:
        fail("declarado no manifesto mas inexistente: " + ", ".join(missing))
    else:
        ok(f"manifesto: {len(declared)} item(ns) declarados existem")

    plugin_entry = [el for el in (files if files is not None else []) if el.get("plugin")]
    if not plugin_entry:
        fail('manifesto: nenhuma <folder plugin="..."> em <files>')
    elif not (PLUGIN_DIR / (plugin_entry[0].text or "").strip() / "provider.php").is_file():
        fail("manifesto: services/provider.php não encontrado")

    if root.find("updateservers") is None:
        warn("manifesto sem <updateservers>: o Joomla não vai enxergar as atualizações publicadas")
    return root


def check_versions(root: ET.Element | None) -> None:
    asset = PLUGIN_DIR / "media" / "joomla.asset.json"
    try:
        data = json.loads(asset.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as e:
        fail(f"joomla.asset.json inválido: {e}")
        return
    ok("joomla.asset.json é JSON válido")
    if root is not None:
        v_xml = (root.findtext("version") or "").strip()
        v_json = str(data.get("version", "")).strip()
        if v_xml != v_json:
            fail(f"versão divergente: manifesto={v_xml!r} joomla.asset.json={v_json!r}")
        else:
            ok(f"versão consistente: {v_xml}")
    # cada uri de asset deve apontar para um arquivo que existe em media/
    for a in data.get("assets", []):
        uri = a.get("uri", "")
        if uri.startswith("plg_captcha_recaptcha/"):
            name = uri.split("/", 1)[1]
            sub = "js" if name.endswith(".js") else "css" if name.endswith(".css") else ""
            if not (PLUGIN_DIR / "media" / sub / name).is_file():
                fail(f"asset {a.get('name')}: arquivo inexistente em media/{sub}/{name}")


# --------------------------------------------------------------------------- PHP / JS
def check_php(root: ET.Element | None) -> None:
    php = plugin_files(".php")
    run_tool("php -l", "php", lambda f: ["-l", str(f)], php, os.environ.get("REQUIRE_PHP") == "1")

    ns_manifest = ((root.findtext("namespace") or "").strip() if root is not None else "")
    ns_re = re.compile(r"^\s*namespace\s+([^;]+);", re.M)
    for f in php:
        txt = f.read_text(encoding="utf-8")
        if "_JEXEC" not in txt:
            fail(f"sem guarda _JEXEC: {rel(f)}")
        m = ns_re.search(txt)
        if m and "/src/" in f.as_posix() and ns_manifest and not m.group(1).strip().startswith(ns_manifest):
            fail(f"namespace {m.group(1).strip()!r} fora de {ns_manifest!r}: {rel(f)}")
    ok(f"guarda _JEXEC e namespaces verificados em {len(php)} arquivo(s) PHP")


def check_js() -> None:
    run_tool("node --check", "node", lambda f: ["--check", str(f)], plugin_files(".js"), os.environ.get("REQUIRE_NODE") == "1")


def check_index_html() -> None:
    faltando = [rel(d) for d in [PLUGIN_DIR, *sorted(p for p in PLUGIN_DIR.rglob("*") if p.is_dir())] if not (d / "index.html").is_file()]
    if faltando:
        fail("pasta(s) sem index.html: " + ", ".join(faltando))
    else:
        ok("index.html presente em todas as pastas")


def check_stray_files() -> None:
    for pat in ("*.zip", "node_modules", "tests"):
        for p in PLUGIN_DIR.rglob(pat):
            warn(f"{rel(p)} está dentro do plugin — é removido do pacote, mas não deveria estar versionado")


# --------------------------------------------------------------------------- i18n
def parse_ini(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith(";"):
            continue
        m = re.match(r'^([A-Z0-9_]+)\s*=\s*"(.*)"\s*$', line)
        if m:
            out[m.group(1)] = m.group(2)
        else:
            fail(f"linha .ini inválida em {path.name}: {line[:70]}")
    return out


def used_keys(root: ET.Element | None) -> set[str]:
    keys: set[str] = set()
    pat = re.compile(r"(?:Text::(?:_|sprintf|plural)|->_)\(\s*'((?:PLG_)[A-Z0-9_]+)'")
    for f in plugin_files(".php"):
        keys.update(pat.findall(f.read_text(encoding="utf-8")))
    if root is not None:
        for el in root.iter():
            for attr in ("label", "description"):
                v = el.get(attr, "")
                if v.startswith("PLG_"):
                    keys.add(v)
            if el.tag in ("option", "description") and (el.text or "").strip().startswith("PLG_"):
                keys.add(el.text.strip())
    return keys


def check_i18n(root: ET.Element | None) -> None:
    lang_dir = PLUGIN_DIR / "language"
    langs = sorted(d.name for d in lang_dir.iterdir() if d.is_dir())
    if BASE_LANG not in langs:
        fail(f"idioma base {BASE_LANG} ausente")
        return
    ausentes_obrig = [lg for lg in REQUIRED_LANGS if lg not in langs]
    if ausentes_obrig:
        fail("idioma(s) obrigatório(s) ausente(s): " + ", ".join(ausentes_obrig))
    tables: dict[tuple[str, str], dict[str, str]] = {}
    for lg in langs:
        for kind in ("plg_captcha_recaptcha.ini", "plg_captcha_recaptcha.sys.ini"):
            f = lang_dir / lg / kind
            if not f.is_file():
                fail(f"arquivo de idioma ausente: {rel(f)}")
            else:
                tables[(lg, kind)] = parse_ini(f)

    for kind in ("plg_captcha_recaptcha.ini", "plg_captcha_recaptcha.sys.ini"):
        base = set(tables.get((BASE_LANG, kind), {}))
        for lg in langs:
            cur = set(tables.get((lg, kind), {}))
            if lg == BASE_LANG or (lg, kind) not in tables:
                continue
            if base - cur:
                fail(f"{lg}/{kind}: faltam chaves presentes em {BASE_LANG}: {', '.join(sorted(base - cur))}")
            if cur - base:
                fail(f"{lg}/{kind}: chaves sobrando (inexistentes em {BASE_LANG}): {', '.join(sorted(cur - base))}")
    ok(f"idiomas verificados: {', '.join(langs)}")

    usadas = used_keys(root)
    for lg in langs:
        merged = {**tables.get((lg, "plg_captcha_recaptcha.ini"), {}), **tables.get((lg, "plg_captcha_recaptcha.sys.ini"), {})}
        ausentes = sorted(k for k in usadas if k not in merged)
        if ausentes:
            fail(f"{lg}: chave(s) usada(s) sem tradução: {', '.join(ausentes)}")
    ok(f"{len(usadas)} chave(s) PLG_* usadas no código/manifesto")


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    print(f"Validando {rel(PLUGIN_DIR)}\n")
    root = check_manifest()
    check_versions(root)
    check_php(root)
    check_js()
    check_index_html()
    check_stray_files()
    check_i18n(root)
    print(f"\nResultado: {len(fails)} FAIL, {len(warns)} AVISO")
    return 1 if fails else 0


if __name__ == "__main__":
    sys.exit(main())
