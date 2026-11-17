#!/bin/bash
# Empacota o plg_captcha_recaptcha e gera o atualizacao.xml (sem enviar nada).
#
# Uso: package.sh <versao|tag>        ex.: package.sh v1.2.0
# Saída (em ./dist): plg_captcha_recaptcha-plugin-<versao>.zip  e  atualizacao.xml
#
# Variáveis opcionais:
#   SKIP_REMOTE=1      não baixa o atualizacao.xml já publicado (gera um novo só com esta versão)
#   BASE_HTTP          URL pública da pasta no servidor (padrão: https://apps.sobieskiproducoes.com.br/plg_captcha_recaptcha)
#   MAINTAINER_URL     URL do mantenedor no update XML
set -euo pipefail

if [ "$#" -lt 1 ] || [ -z "$1" ]; then
  echo "Uso: $0 <versao|tag>" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PYTHON="${PYTHON:-python3}"

# --- Identidade da extensão (fixa: plugin do grupo captcha) -------------------------------------
APP_NAME="plg_captcha_recaptcha"     # nome do pacote/pasta no servidor
ELEMENT="recaptcha"                  # <element> no update XML (= atributo plugin= do manifesto)
TYPE="plugin"
SOURCE_DIR="${ROOT_DIR}/plugins/${APP_NAME}"
MANIFEST_NAME="recaptcha.xml"

PLAIN_VERSION="${1#v}"
if ! [[ "$PLAIN_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$ ]]; then
  echo "Erro: versão inválida: '${1}' (esperado v1.2.3)" >&2
  exit 1
fi

BASE_HTTP="${BASE_HTTP:-https://apps.sobieskiproducoes.com.br/${APP_NAME}}"
MAINTAINER_URL="${MAINTAINER_URL:-https://www.sobieskiproducoes.com.br}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "${BUILD_DIR}"' EXIT
ZIP_FILE="${APP_NAME}-${TYPE}-${PLAIN_VERSION}.zip"

[ -f "${SOURCE_DIR}/${MANIFEST_NAME}" ] || { echo "Erro: manifesto não encontrado: ${SOURCE_DIR}/${MANIFEST_NAME}" >&2; exit 1; }

echo "Empacotando ${APP_NAME} ${PLAIN_VERSION}"
rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"

# --- Cópia limpa: artefatos de desenvolvimento nunca entram no pacote -------------------------
cp -r "${SOURCE_DIR}/." "${BUILD_DIR}/"
find "${BUILD_DIR}" \( -name '*.zip' -o -name '.DS_Store' -o -name 'Thumbs.db' \) -type f -delete
rm -rf "${BUILD_DIR}/tests" "${BUILD_DIR}/node_modules"
find "${BUILD_DIR}" -name '.*' -not -name '.' -prune -exec rm -rf {} +

# --- Versão: manifesto e joomla.asset.json (só na cópia, o repositório não é alterado) ----------
sed -i "s|<version>[^<]*</version>|<version>${PLAIN_VERSION}</version>|" "${BUILD_DIR}/${MANIFEST_NAME}"
sed -i "s|\"version\": *\"[^\"]*\"|\"version\": \"${PLAIN_VERSION}\"|" "${BUILD_DIR}/media/joomla.asset.json"
grep -q "<version>${PLAIN_VERSION}</version>" "${BUILD_DIR}/${MANIFEST_NAME}" || { echo "Erro: versão não aplicada no manifesto" >&2; exit 1; }

# --- ZIP (manifesto na raiz do pacote) -----------------------------------------------------------
( cd "${BUILD_DIR}" && zip -qr "${DIST_DIR}/${ZIP_FILE}" . )
echo "Pacote criado: dist/${ZIP_FILE}"

SHA256=$(sha256sum "${DIST_DIR}/${ZIP_FILE}" | awk '{print $1}')
SHA384=$(sha384sum "${DIST_DIR}/${ZIP_FILE}" | awk '{print $1}')
SHA512=$(sha512sum "${DIST_DIR}/${ZIP_FILE}" | awk '{print $1}')

# --- Entrada do update XML ----------------------------------------------------------------------
NAME=$(sed -n 's:.*<name>\(.*\)</name>.*:\1:p' "${BUILD_DIR}/${MANIFEST_NAME}" | head -1)
MAINTAINER=$(sed -n 's:.*<author>\(.*\)</author>.*:\1:p' "${BUILD_DIR}/${MANIFEST_NAME}" | head -1)
GROUP=$(sed -n 's:.*<extension[^>]*group="\([^"]*\)".*:\1:p' "${BUILD_DIR}/${MANIFEST_NAME}" | head -1)

cat > "${DIST_DIR}/nova_entrada.xml" << EOL
    <update>
        <name>${NAME}</name>
        <element>${ELEMENT}</element>
        <folder>${GROUP}</folder>
        <type>${TYPE}</type>
        <version>${PLAIN_VERSION}</version>
        <infourl title="Sobieski Produções">${BASE_HTTP}/atualizacao.xml</infourl>
        <downloads>
            <downloadurl type="full" format="zip">${BASE_HTTP}/${ZIP_FILE}</downloadurl>
        </downloads>
        <sha256>${SHA256}</sha256>
        <sha384>${SHA384}</sha384>
        <sha512>${SHA512}</sha512>
        <tags>
            <tag>stable</tag>
        </tags>
        <maintainer>${MAINTAINER}</maintainer>
        <maintainerurl>${MAINTAINER_URL}</maintainerurl>
        <targetplatform name="joomla" version="(5|6)\.*"/>
        <php_minimum>8.1</php_minimum>
    </update>
EOL

# --- Junta com o update XML já publicado (sem o histórico, o Joomla perderia as versões antigas) -
REMOTE_OK=false
if [ "${SKIP_REMOTE:-0}" != "1" ]; then
  # --max-redirect=0: host fixo e próprio; um redirect aqui só faria sentido se estivesse comprometido.
  if wget -q --max-redirect=0 -O "${DIST_DIR}/atualizacao_remota.xml" "${BASE_HTTP}/atualizacao.xml"; then
    REMOTE_OK=true
  fi
fi

"${PYTHON}" - "${DIST_DIR}" "${ELEMENT}" "${TYPE}" "${PLAIN_VERSION}" "${REMOTE_OK}" << 'PYEOF'
import re
import sys
from pathlib import Path

dist, element, tipo, versao, remoto = Path(sys.argv[1]), *sys.argv[2:5], sys.argv[5] == "true"
nova = (dist / "nova_entrada.xml").read_text(encoding="utf-8").rstrip("\n")
mantidos = []

if remoto:
    xml = (dist / "atualizacao_remota.xml").read_text(encoding="utf-8")

    def tag(bloco, nome):
        m = re.search(r"<%s>\s*([^<]*?)\s*</%s>" % (nome, nome), bloco)
        return m.group(1) if m else ""

    blocos = re.findall(r"<update>.*?</update>", xml, re.S)
    # DEDUP: re-rodar a mesma tag não pode listar a versão duas vezes.
    mantidos = [b for b in blocos if not (tag(b, "element") == element and tag(b, "type") == tipo and tag(b, "version") == versao)]
    print("update XML remoto: %d entrada(s) preservada(s), %d substituída(s)." % (len(mantidos), len(blocos) - len(mantidos)))
else:
    print("update XML remoto indisponível/ignorado: gerando um novo.")

corpo = "\n".join([nova, *mantidos])
(dist / "atualizacao.xml").write_text('<?xml version="1.0" encoding="utf-8"?>\n<updates>\n' + corpo + "\n</updates>\n", encoding="utf-8")
PYEOF

rm -f "${DIST_DIR}/nova_entrada.xml" "${DIST_DIR}/atualizacao_remota.xml"
echo "Update XML criado: dist/atualizacao.xml"
echo "sha256: ${SHA256}"
