# AGENTS.md

Instruções para agentes de IA (Claude Code, Copilot etc.) que trabalham neste repositório.

## O que é

Plugin Joomla **5/6** de captcha (`plg_captcha_recaptcha`) para o Google reCAPTCHA (v2 caixa, v2 invisível, v3).
Um único plugin, em `plugins/plg_captcha_recaptcha/`. A raiz do git é a pasta acima de `plugins/`.
Visão geral em [README.md](README.md); funcionamento interno em [docs/ARQUITETURA.md](docs/ARQUITETURA.md);
publicação em [docs/DEPLOY.md](docs/DEPLOY.md).

## Stack

- PHP 8.1+ (compatível até 8.4), Joomla 5/6 com namespaces `Joomla\CMS\…` e injeção de dependências.
- Namespace do plugin: `Joomla\Plugin\Captcha\ReCaptcha` (pasta `src/`). Elemento: `recaptcha`; grupo: `captcha`.
- JavaScript puro (sem jQuery). Bootstrap/jQuery não são usados neste plugin.

> [plugins/DEVELOPMENT_GUIDELINES.md](plugins/DEVELOPMENT_GUIDELINES.md) descreve os padrões gerais dos projetos
> irmãos, que são Joomla **3**. Aqui valem as regras gerais (i18n, segurança, SEO onde couber), mas **não** use
> `jimport`/`JFactory`/classes `J*`: este plugin é Joomla 5/6.

## Comandos

```bash
python3 .github/scripts/validar.py                       # gate de validação (rode antes de abrir PR)
DRY_RUN=1 bash .github/scripts/deploy.sh v1.0.0          # gera dist/ (ZIP + atualizacao.xml) sem enviar
```

Não há suíte de testes automatizados; o `validar.py` é o gate. `php -l` e `node --check` só rodam se o PHP/Node
estiverem instalados (no CI são obrigatórios).

## Regras

1. **Idiomas:** toda chave nova entra em **todos** os 9 idiomas de `language/` (`.ini`, e `.sys.ini` quando for do
   manifesto). Os 8 obrigatórios são pt-BR, en-GB, es-ES, de-DE, fr-FR, zh-CN, ja-JP e it-IT (en-US é extra). `pt-BR` é a base; o validador reprova divergência. Textos novos nunca ficam hardcoded.
2. **`index.html`** em toda pasta nova dentro do plugin (conteúdo igual aos existentes).
3. **Guarda `_JEXEC`** em todo PHP do plugin (o validador reprova se faltar).
4. **Versão:** `recaptcha.xml` e `media/joomla.asset.json` devem ter a mesma `version`. A versão publicada vem da tag
   do git (aplicada só no pacote); mude as duas juntas se alterar a do repositório.
5. **Arquivos declarados:** ao criar pasta/arquivo que deva ser instalado, declare-o no `<files>`/`<media>` do manifesto.
6. **Não versionar** ZIPs, `dist/`, `node_modules/` (o `.gitignore` já cobre). O `recaptcha.zip` dentro do plugin é
   resíduo local e não deve ser commitado.
7. **Segurança:** a chave secreta nunca vai para o JS nem para o HTML; escape toda saída (`htmlspecialchars`); a
   verificação do token é sempre no servidor.
8. Comentários e mensagens em **português do Brasil**, no estilo do código existente.
9. Scripts `.sh` sempre com LF (ver `.gitattributes`).

## Workflows

- `build.yml` — lint + empacotamento de teste.
- `validacao-pre-master.yml` — gate de PR/push na `master`.
- `deploy.yml` — publica ao criar tag `v*` (secrets `FTP_URL`, `FTP_USUARIO`, `FTP_SENHA`).

Não altere o formato de `atualizacao.xml` nem o nome do ZIP sem ajustar `package.sh`, o manifesto
(`<updateservers>`) e [docs/DEPLOY.md](docs/DEPLOY.md) juntos.
