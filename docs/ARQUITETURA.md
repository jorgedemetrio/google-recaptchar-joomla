# Arquitetura do plg_captcha_recaptcha

Plugin Joomla 5/6, grupo `captcha`, elemento `recaptcha`, namespace `Joomla\Plugin\Captcha\ReCaptcha`.

## Mapa dos arquivos

```
plugins/plg_captcha_recaptcha/
├── recaptcha.xml                    manifesto: parâmetros, arquivos, mídia e servidor de atualização
├── services/provider.php            registra o plugin no container de injeção de dependências
├── src/
│   ├── Extension/ReCaptcha.php      plugin (CMSPlugin + SubscriberInterface): assina os eventos
│   └── Provider/ReCaptchaProvider.php   provedor: renderiza o desafio e verifica a resposta
├── media/
│   ├── joomla.asset.json            registro dos assets (script e estilo)
│   ├── js/recaptcha.js              callback global + comportamento de cada modo
│   └── css/recaptcha.css            espaçamento e aviso legal
├── language/<idioma>/               pt-BR, en-GB, en-US, es-ES, de-DE, fr-FR, it-IT, zh-CN, ja-JP
│                                    cada um com .ini (runtime), .sys.ini (manifesto) e index.html
└── index.html                       (um em cada pasta, convenção Joomla)
```

O manifesto instala a pasta `media/` em `media/plg_captcha_recaptcha/`, por isso o `joomla.asset.json` referencia
`plg_captcha_recaptcha/recaptcha.js` e `plg_captcha_recaptcha/recaptcha.css`.

## Fluxo

```
Formulário com <field type="captcha">
        │
        ▼
CaptchaField ──► evento onCaptchaSetup
                     │  ReCaptcha::setupCaptcha()
                     ▼
        CaptchaRegistry.add(new ReCaptchaProvider(params, app))      nome do provedor: "recaptcha"
                     │
   exibição          ▼                                  envio
ReCaptchaProvider::display()                  ReCaptchaProvider::checkAnswer()
  • opções para o JS (addScriptOptions)         • lê g-recaptcha-response do POST
  • registra o joomla.asset.json                • consulta POST /recaptcha/api/siteverify
  • script do plugin, depois api.js (defer)     • v3: confere score ≥ threshold e action
  • devolve o HTML do modo escolhido            • lança RuntimeException em qualquer falha
```

### `ReCaptcha` (extensão)

Assina dois eventos:

| Evento | Método | Função |
| --- | --- | --- |
| `onCaptchaSetup` | `setupCaptcha` | Registra o `ReCaptchaProvider` no `CaptchaRegistry`. |
| `onPrivacyCollectAdminCapabilities` | `collectPrivacyCapabilities` | Declara que o IP do visitante é enviado ao Google. |

### `ReCaptchaProvider` (provedor)

Implementa `CaptchaProviderInterface` (`getName`, `display`, `checkAnswer`, `setupField`).

- **`display()`** só renderiza em aplicação web. Sem `public_key` lança `RuntimeException`. Registra o asset do
  plugin com `addExtensionRegistryFile('plg_captcha_recaptcha')` (plugins não têm o `joomla.asset.json` carregado
  automaticamente, ao contrário de componentes e templates) e monta a URL da `api.js` com `hl=<idioma do site>` e
  `onload=turismoRecaptchaInit`. O script do plugin é declarado como dependência da `api.js`, porque define o
  callback `onload` e precisa ser executado antes dela.
  - `checkbox` e `invisible`: `<div class="g-recaptcha" data-sitekey=…>` renderizado explicitamente (`render=explicit`).
  - `v3`: `<input type="hidden" name="g-recaptcha-response">` preenchido no envio (`render=<sitekey>`).
- **`checkAnswer()`** usa o token passado ou o campo `g-recaptcha-response` do POST. Falhas lançam
  `RuntimeException` com a mensagem traduzida (que o Joomla mostra ao usuário):

  | Situação | Código | Chave de idioma |
  | --- | --- | --- |
  | Chave secreta vazia | 500 | `PLG_RECAPTCHA_ERROR_NO_PRIVATE_KEY` |
  | Resposta vazia | 403 | `PLG_RECAPTCHA_ERROR_EMPTY_SOLUTION` |
  | Resposta reprovada pelo Google / score baixo | 403 | `PLG_RECAPTCHA_ERROR_INVALID_SOLUTION` |
  | Google inacessível ou resposta que não é JSON | 503 | `PLG_RECAPTCHA_ERROR_UNREACHABLE` |

  O token do Google é de **uso único**; por isso o resultado fica em cache estático por requisição
  (`$verificados`), permitindo que o Joomla chame a validação mais de uma vez sem reprovar a segunda.
- **v3**: aprova somente se `success`, `score >= threshold` (padrão 0.5) e `action == 'joomla_form'`.
- O IP do visitante (`IpHelper::getIp()`) vai como `remoteip`. Reprovações e falhas são registradas no log
  `plg_captcha_recaptcha` (INFO/WARNING/ERROR).

### `recaptcha.js`

Define `window.turismoRecaptchaInit` (nome herdado do projeto original; é o `onload` da `api.js`).

- **checkbox**: `grecaptcha.render()` em cada `.g-recaptcha`.
- **invisible**: renderiza com `callback`; no `submit` do formulário (fase de captura) cancela o envio, executa o
  desafio e, com o token, reenvia com `form.requestSubmit(botão)`. Se já havia token de tentativa anterior faz
  `reset` antes (token expira em ~2 min e é de uso único).
- **v3**: no `submit` obtém o token com `grecaptcha.execute(sitekey, {action})`, grava no campo oculto e reenvia.
- Um marcador `data-recaptcha-ok` evita laço infinito na segunda passagem.

## Parâmetros (`recaptcha.xml`)

Veja a tabela em [README.md](../README.md#configuração). Os campos específicos de cada modo usam `showon`.

## Internacionalização

Cada idioma tem dois arquivos:

- `plg_captcha_recaptcha.ini`: textos usados em runtime (mensagens, rótulos).
- `plg_captcha_recaptcha.sys.ini`: nome e descrição exibidos na instalação/lista de plugins.

Idiomas obrigatórios: `pt-BR`, `en-GB`, `es-ES`, `de-DE`, `fr-FR`, `zh-CN`, `ja-JP` e `it-IT` (`en-US` é extra).

Regras (verificadas por `.github/scripts/validar.py`): mesmas chaves em todos os idiomas, tomando `pt-BR` como base,
e nenhuma chave `PLG_*` usada no PHP ou no manifesto sem tradução.

## Decisões e limites

- Sem dependência de jQuery e sem a API legada de captcha (`onInit`/`onDisplay`/`onCheckAnswer`).
- A verificação tem timeout fixo de 8 s (`VERIFY_TIMEOUT`).
- Um formulário só é interceptado uma vez (`data-recaptcha-bound`), mesmo que a página tenha vários campos.
