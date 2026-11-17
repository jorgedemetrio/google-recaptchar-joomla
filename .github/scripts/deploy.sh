#!/bin/bash
# Empacota (package.sh) e envia o plg_captcha_recaptcha para o servidor de atualização via lftp.
#
# Uso: deploy.sh <versao|tag>
# Variáveis obrigatórias: FTP_URL, FTP_USER, FTP_PASSWORD
#   DRY_RUN=1  só empacota, não envia (não exige as variáveis FTP)
set -euo pipefail

if [ "$#" -lt 1 ] || [ -z "$1" ]; then
  echo "Uso: $0 <versao|tag>" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_NAME="plg_captcha_recaptcha"

if [ "${DRY_RUN:-0}" = "1" ]; then
  export SKIP_REMOTE=1
fi

bash "${ROOT_DIR}/.github/scripts/package.sh" "$1"

DIST_DIR="${ROOT_DIR}/dist"
ZIP_FILE="$(basename "$(ls "${DIST_DIR}"/${APP_NAME}-plugin-*.zip)")"

if [ "${DRY_RUN:-0}" = "1" ]; then
  echo "DRY_RUN=1: nada foi enviado. Arquivos em dist/."
  exit 0
fi

if [ -z "${FTP_URL:-}" ] || [ -z "${FTP_USER:-}" ] || [ -z "${FTP_PASSWORD:-}" ]; then
  echo "Erro: defina FTP_URL, FTP_USER e FTP_PASSWORD (secrets FTP_URL, FTP_USUARIO, FTP_SENHA no GitHub)." >&2
  exit 1
fi

echo "Enviando ${ZIP_FILE} e atualizacao.xml para /${APP_NAME} ..."
# Primeiro o ZIP, depois o update XML: o Joomla nunca enxerga uma versão cujo download ainda não existe.
lftp -c "set sftp:auto-confirm yes; set ftp:ssl-allow yes; set ssl:verify-certificate no;
open -u ${FTP_USER},${FTP_PASSWORD} ${FTP_URL};
mkdir -p /${APP_NAME};
cd /${APP_NAME};
put -O . ${DIST_DIR}/${ZIP_FILE};
put -O . ${DIST_DIR}/atualizacao.xml;
bye"

echo "Deploy de ${APP_NAME} concluído."
rm -rf "${DIST_DIR}"
