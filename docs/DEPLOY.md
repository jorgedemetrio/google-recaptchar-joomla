# Deploy e publicação

O plugin é distribuído como ZIP instalável do Joomla e publicado, junto com um `atualizacao.xml`, em
`https://apps.sobieskiproducoes.com.br/plg_captcha_recaptcha/`. O mesmo padrão dos projetos `imoveis`, `turismo` e
`automoveis`.

## Visão geral

```
git tag vX.Y.Z  ──►  deploy.yml
                       1. validar.py (gate, PHP e Node obrigatórios)
                       2. package.sh : ZIP + checksums + atualizacao.xml (mescla o histórico remoto)
                       3. deploy.sh  : lftp → /plg_captcha_recaptcha/{ZIP, atualizacao.xml}
```

| Arquivo | Papel |
| --- | --- |
| `.github/workflows/build.yml` | Lint rápido (PHP 8.1/8.3/8.4, JS, manifestos) e empacotamento de teste. |
| `.github/workflows/validacao-pre-master.yml` | Gate de PR/push na `master`. |
| `.github/workflows/deploy.yml` | Publica ao criar tag `v*`. |
| `.github/scripts/validar.py` | Validador (mesmo código local e no CI). |
| `.github/scripts/package.sh` | Gera `dist/*.zip` e `dist/atualizacao.xml`. |
| `.github/scripts/deploy.sh` | Chama o `package.sh` e envia por lftp. |

## Configuração única

Em **Settings → Secrets and variables → Actions** do repositório, crie:

| Secret | Conteúdo |
| --- | --- |
| `FTP_URL` | Host/URL do servidor, no formato aceito pelo `lftp` (ex.: `sftp://host` ou `ftps://host`). |
| `FTP_USUARIO` | Usuário. |
| `FTP_SENHA` | Senha. |

São os mesmos nomes usados nos outros repositórios, então podem ser copiados de lá.

## Publicar uma versão

1. Atualize o [CHANGELOG](../CHANGELOG.md) e faça o merge na `master` (o gate precisa passar).
2. Crie e envie a tag. O número da tag vira a versão do pacote:

   ```bash
   git tag v1.1.0
   git push origin v1.1.0
   ```

3. Acompanhe a execução em **Actions → Deploy Plugin**.

O `package.sh` aplica a versão da tag em `recaptcha.xml` e `media/joomla.asset.json` **apenas dentro do pacote**; os
valores no repositório (`1.0.0`) só precisam ser consistentes entre si, o que o validador confere. Tags aceitas:
`vMAJOR.MINOR.PATCH` com sufixo opcional (ex.: `v1.2.0-rc1`).

## O que o pacote contém

- Manifesto `recaptcha.xml` na raiz do ZIP, mais `services/`, `src/`, `language/` e `media/`.
- Fora do pacote: qualquer `*.zip`, dotfiles, `tests/`, `node_modules/`.
- Nome do arquivo: `plg_captcha_recaptcha-plugin-<versão>.zip`.

## O `atualizacao.xml`

Cada deploy baixa o `atualizacao.xml` já publicado, **preserva as versões anteriores**, substitui a entrada da mesma
versão (re-executar uma tag não duplica) e coloca a nova no topo. A entrada declara:

- `<element>recaptcha</element>` e `<folder>captcha</folder>` (como o Joomla identifica o plugin);
- `sha256`, `sha384` e `sha512` do ZIP (o instalador do Joomla valida o download);
- `targetplatform` `(5|6)\.*` e `php_minimum` 8.1.

O manifesto aponta para esse arquivo em `<updateservers>`. Instalações feitas **antes** desse bloco existir
precisam ser reinstaladas uma vez (instalar o ZIP novo por cima, `method="upgrade"`) para registrar o servidor.

O ZIP é enviado **antes** do `atualizacao.xml`, para que nenhuma instalação veja uma versão cujo download ainda
não existe.

## Testar sem publicar

```bash
DRY_RUN=1 bash .github/scripts/deploy.sh v1.1.0   # gera dist/ e não envia nada
```

`SKIP_REMOTE=1` (já implícito no `DRY_RUN`) evita baixar o `atualizacao.xml` publicado. `BASE_HTTP` troca a URL
pública, útil para testar contra um servidor local.

## Retirar ou corrigir uma versão

- **Versão com defeito:** publique uma nova tag com a correção (o Joomla oferece a maior versão).
- **Retirar uma entrada:** edite o `atualizacao.xml` direto no servidor e remova o bloco `<update>` da versão.

## Pontos de atenção

- O `lftp` usa `set ssl:verify-certificate no` (igual aos outros repositórios), ou seja, **não valida o certificado
  do servidor**. Se o servidor tiver certificado válido, remova essa opção em `.github/scripts/deploy.sh`.
- Usuário e senha vão na linha de comando do `lftp` dentro do runner do GitHub (valores de *secrets* são mascarados
  nos logs). Prefira um usuário FTP restrito à pasta de apps.
- A tag não é verificada contra a branch: qualquer commit com tag `v*` é publicado, desde que passe no validador.
  Crie tags só a partir da `master`.
