# Google reCAPTCHA para Joomla 5/6

[![Build](https://github.com/jorgedemetrio/google-recaptchar-joomla/actions/workflows/build.yml/badge.svg)](https://github.com/jorgedemetrio/google-recaptchar-joomla/actions/workflows/build.yml)
[![Validação pré-master](https://github.com/jorgedemetrio/google-recaptchar-joomla/actions/workflows/validacao-pre-master.yml/badge.svg)](https://github.com/jorgedemetrio/google-recaptchar-joomla/actions/workflows/validacao-pre-master.yml)

Plugin de **Captcha** (`plg_captcha_recaptcha`) que integra o **Google reCAPTCHA** ao Joomla 5 e 6 usando o
mecanismo de provedores de captcha introduzido no Joomla 5 (evento `onCaptchaSetup`). Não usa a API legada
`onInit`/`onDisplay` nem jQuery.

O Joomla 6 não traz mais um plugin de reCAPTCHA, por isso este plugin existe: ele devolve o recurso ao Joomla 6 (e
funciona também no 5).

Baseado no plugin reCAPTCHA do projeto Joomla! (GPL).

## Recursos

- Três modos, escolhidos na configuração do plugin:
  - **v2 – Caixa "Não sou um robô"** (tema claro/escuro, tamanho normal/compacto, `tabindex`);
  - **v2 – Invisível** (selo no canto inferior direito/esquerdo ou na linha);
  - **v3 – Por pontuação** (sem interação; verifica a pontuação mínima e a *action* `joomla_form`).
- Verificação no servidor (`siteverify`) com timeout de 8 s e mensagens de erro traduzidas.
- Opção de usar o domínio `recaptcha.net` em países onde `google.com` é bloqueado.
- Aviso legal do Google exibido automaticamente quando o selo fica oculto (invisível e v3).
- Registro na área de **Privacidade** do Joomla informando que o IP do visitante é enviado ao Google.
- Idiomas: Português (Brasil), Inglês (`en-GB` e `en-US`), Espanhol, Alemão, Francês, Italiano, Chinês (simplificado) e Japonês.
- Log (`plg_captcha_recaptcha`) de reprovações e de falhas de comunicação com o Google.

## Requisitos

| Item | Versão |
| --- | --- |
| Joomla | 5.x ou 6.x |
| PHP | 8.1 ou superior |
| Google reCAPTCHA | par de chaves (site/secreta) criado em <https://www.google.com/recaptcha/admin> |

> As chaves do Google são específicas do tipo de reCAPTCHA. Crie o par para o **mesmo tipo** configurado no
> plugin (v2 caixa, v2 invisível ou v3) e para o domínio do site.

## Instalação

1. Gere o pacote (veja [Gerar o ZIP localmente](#gerar-o-zip-localmente)) ou baixe o ZIP publicado
   pelo fluxo de deploy.
2. No Joomla: **Sistema → Instalar → Extensões** e envie o ZIP.
3. **Sistema → Plugins**, procure por *Captcha - Google reCAPTCHA* e **habilite** o plugin.
4. Abra o plugin e preencha **Tipo de reCAPTCHA**, **Chave do site** e **Chave secreta**.

O pacote registra o servidor de atualização `https://apps.sobieskiproducoes.com.br/plg_captcha_recaptcha/atualizacao.xml`;
novas versões aparecem em **Sistema → Atualizar → Extensões**.

## Configuração

| Parâmetro | Valores | Observação |
| --- | --- | --- |
| `mode` | `checkbox` (padrão), `invisible`, `v3` | Precisa corresponder ao tipo das chaves. |
| `public_key` | texto | Chave do site. Obrigatória. |
| `private_key` | senha | Chave secreta. Obrigatória. |
| `theme` | `light`, `dark` | Somente `checkbox`. |
| `size` | `normal`, `compact` | Somente `checkbox`. |
| `tabindex` | inteiro ≥ 0 | Somente `checkbox`. |
| `badge` | `bottomright`, `bottomleft`, `inline` | Somente `invisible`. |
| `threshold` | 0.1 a 0.9 (padrão 0.5) | Somente `v3`. Envios abaixo da pontuação são recusados. |
| `hide_badge_text` | sim/não (padrão sim) | `invisible` e `v3`. Mostra o texto legal do Google. |
| `use_recaptchanet` | `google.com` / `recaptcha.net` | Domínio usado para carregar o script e verificar. |

## Como usar nos formulários

1. **Sistema → Configuração Global → Padrão Captcha**: escolha *Captcha - Google reCAPTCHA*.
2. Ative o captcha nos formulários do Joomla que o suportam (cadastro de usuário, contato, redefinição de
   senha etc., geralmente na opção **Captcha** do componente).
3. Em formulários próprios (XML de formulário Joomla):

   ```xml
   <field name="captcha" type="captcha" label="COM_EXEMPLO_CAPTCHA" validate="captcha" namespace="contato" />
   ```

   O campo `captcha` usa o provedor padrão da Configuração Global; para fixar este provedor use
   `plugin="recaptcha"`.

Em modo **invisível** e **v3** o envio do formulário é interceptado: o token é obtido e o formulário é reenviado
(`requestSubmit`) preservando a validação do Joomla e o botão clicado.

## Estrutura do repositório

```
.
├── .github/
│   ├── scripts/            validar.py · package.sh · deploy.sh
│   └── workflows/          build.yml · validacao-pre-master.yml · deploy.yml
├── docs/
│   ├── ARQUITETURA.md      como o plugin funciona por dentro
│   └── DEPLOY.md           release, secrets e servidor de atualização
├── plugins/
│   ├── DEVELOPMENT_GUIDELINES.md   padrões gerais dos projetos Joomla
│   └── plg_captcha_recaptcha/      o plugin (manifesto recaptcha.xml)
│       └── language/               pt-BR · en-GB · en-US · es-ES · de-DE · fr-FR · it-IT · zh-CN · ja-JP
├── AGENTS.md · CLAUDE.md   instruções para agentes de IA
├── CHANGELOG.md
├── LICENSE.txt             GNU GPL v2
└── README.md
```

Detalhes do plugin em [docs/ARQUITETURA.md](docs/ARQUITETURA.md).

## Desenvolvimento

Pré-requisitos locais: PHP 8.1+, Node.js e Python 3.

```bash
# Gate de validação (o mesmo que roda no CI)
python3 .github/scripts/validar.py

# No CI o PHP e o Node são obrigatórios; local, a falta deles vira AVISO.
REQUIRE_PHP=1 REQUIRE_NODE=1 python3 .github/scripts/validar.py
```

O validador reprova (exit ≠ 0) quando: o manifesto ou o `joomla.asset.json` são inválidos ou têm versões
diferentes; algum arquivo declarado no manifesto não existe; falta `index.html` em alguma pasta; falta a guarda
`_JEXEC` ou o namespace foge do manifesto; `php -l`/`node --check` falham; falta algum dos 8 idiomas obrigatórios
ou os idiomas divergem de `pt-BR` (chaves faltando ou sobrando); ou uma chave `PLG_*` usada no código/manifesto não está traduzida.

### Gerar o ZIP localmente

```bash
DRY_RUN=1 bash .github/scripts/deploy.sh v1.0.0
# → dist/plg_captcha_recaptcha-plugin-1.0.0.zip  e  dist/atualizacao.xml (nada é enviado)
```

Requer `zip`, `sha256sum` e `python3`. O ZIP traz o manifesto na raiz e a versão informada aplicada em
`recaptcha.xml` e `joomla.asset.json` (o repositório não é alterado).

## Publicação (CI/CD)

| Workflow | Quando roda | O que faz |
| --- | --- | --- |
| [`build.yml`](.github/workflows/build.yml) | push em `master`/`release`/`develop` e PRs | `php -l` (8.1, 8.3, 8.4), `node --check`, valida manifestos e empacota em modo de teste |
| [`validacao-pre-master.yml`](.github/workflows/validacao-pre-master.yml) | PR para `master` e push na `master` | Gate `validar.py` (bloqueia o merge em caso de FAIL) |
| [`deploy.yml`](.github/workflows/deploy.yml) | tag `v*` | Valida, empacota e envia ZIP + `atualizacao.xml` por lftp |

Para publicar uma versão:

```bash
git tag v1.1.0
git push origin v1.1.0
```

Os *secrets* necessários e o funcionamento do servidor de atualização estão em [docs/DEPLOY.md](docs/DEPLOY.md).

## Privacidade e LGPD/GDPR

A cada verificação o plugin envia ao Google a resposta do desafio e o **IP do visitante** (`remoteip`). O plugin
informa isso em **Usuários → Privacidade → Recursos**. Lembre-se de citar o reCAPTCHA na política de
privacidade do site e de manter o aviso legal ativo quando o selo estiver oculto (exigência dos Termos do Google).

## Solução de problemas

| Sintoma | Causa provável |
| --- | --- |
| "reCAPTCHA: a chave do site não está configurada." | `public_key` vazia no plugin. |
| "reCAPTCHA: a chave secreta não está configurada." | `private_key` vazia no plugin. |
| "A verificação do reCAPTCHA falhou." | Tipo das chaves diferente do `mode`, domínio não cadastrado no Google ou, no v3, pontuação abaixo do `threshold`. Veja o log `plg_captcha_recaptcha`. |
| "Não foi possível acessar o serviço reCAPTCHA." | Servidor sem saída HTTPS para o Google; tente `recaptcha.net`. |
| O desafio não aparece | Plugin desabilitado, ou o provedor padrão da Configuração Global não é este. |

Para ver as reprovações, habilite o log do Joomla (**Sistema → Configuração Global → Logs**); as mensagens usam a
categoria `plg_captcha_recaptcha`.

## Pendências conhecidas

- As traduções de `de-DE`, `fr-FR`, `it-IT`, `zh-CN` e `ja-JP` foram geradas por IA e merecem revisão de um falante
  nativo antes de um uso amplo.

## Licença

GNU General Public License v2 ou superior — texto completo em [LICENSE.txt](LICENSE.txt). Baseado no plugin
reCAPTCHA do Joomla! Project.
