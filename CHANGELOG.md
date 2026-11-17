# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [Não publicado]

### Adicionado
- `LICENSE.txt` com o texto da GNU GPL v2 (citado no manifesto).
- Idiomas `de-DE`, `fr-FR`, `it-IT`, `zh-CN` e `ja-JP` (completam os 8 idiomas do guia de desenvolvimento).
- `validar.py` passa a exigir os 8 idiomas obrigatórios.
- Documentação: `README.md`, `docs/ARQUITETURA.md`, `docs/DEPLOY.md`, `AGENTS.md` e `CLAUDE.md`.
- GitHub Actions: `build.yml`, `validacao-pre-master.yml` e `deploy.yml` (publicação por tag `v*`).
- Scripts de CI: `validar.py` (gate), `package.sh` (ZIP + `atualizacao.xml`) e `deploy.sh` (envio por lftp).
- `<updateservers>` no manifesto, apontando para `apps.sobieskiproducoes.com.br/plg_captcha_recaptcha`.
- `.gitignore` e `.gitattributes` (scripts sempre com LF).

## [1.0.0] - 2026-11

### Adicionado
- Plugin de captcha para Joomla 5/6 no padrão de provedores (`onCaptchaSetup`).
- Modos reCAPTCHA v2 caixa, v2 invisível e v3 (pontuação e *action*).
- Domínio alternativo `recaptcha.net`, aviso legal do Google e registro na área de Privacidade do Joomla.
- Idiomas `pt-BR`, `en-GB`, `en-US` e `es-ES`.
