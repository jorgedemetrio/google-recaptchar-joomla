# PADRÕES DE DESENVOLVIMENTO - APLICAÇÃO JOOMLA 3

## 1. Diretrizes Gerais

- Sempre que possível, **utilizar recursos nativos do Joomla 3**.
- Aplicação multilíngue obrigatória com suporte à internacionalização nos seguintes idiomas:
  - Português Brasil (padrão)
  - Inglês
  - Espanhol
  - Alemão
  - Francês
  - Chinês
  - Japonês
  - Italiano
- As páginas devem respeitar as boas práticas de SEO:
  - Utilizar `Breadcrumbs`
  - Definir **título**, **meta-descrição** e **keywords**
  - Gerar **JSON-LD (Google Data Structure)** sempre que possível
  - Utilizar **alias únicos** nos registros que tenham página própria (usado na URL amigável)
- As páginas devem ser **totalmente compatíveis com HTML5**
- Evitar requisições Ajax para montar a estrutura da tela; usar apenas para:
  - Validações
  - Componentes dinâmicos (ex: combobox)
- Todas as páginas devem ter **título visível**
- Utilizar **rotas Joomla com `JRoute::_()`** para geração de URLs

---

## 2. Interface do Usuário

- Toda tela deve conter, no **canto superior direito**, um **botão de ajuda**, que abre um modal explicativo sobre a funcionalidade da tela.
- Todos os **botões devem utilizar Bootstrap Icons** representativos e conter **tooltip (legenda ao passar o mouse)** com `title=""`.
- **Layout responsivo obrigatório**, utilizando:
  - Bootstrap 5
  - jQuery (quando necessário)
- Tabelas:
  - Devem ser responsivas usando:
    ```html
    <div class="table-responsive">
      <table class="table table-striped table-bordered table-hover">
      </table>
    </div>
    ```
  - Devem permitir:
    - Ordenação por coluna (ASC/DESC alternado ao clicar)
    - Paginação
    - Definição de quantidade de itens por página
    - Utilizar componentes nativos do Joomla para isso

---

## 3. Formulários e Segurança

- **Validação obrigatória** no **cliente (JavaScript)** e no **servidor (Controller PHP)**.
- Todos os formulários que criam ou alteram dados:
  - Devem usar o método `POST`
  - Devem utilizar **Token CSRF do Joomla**
  - Devem conter um campo `tracking` (`type="hidden"`) com valor UUID
- Formulários acessíveis externamente (ex: cadastro, login, contato) devem incluir:
  - Validação via Token
  - Proteção via **Google ReCAPTCHA**
- Campos sensíveis devem utilizar:
  - `$db->quote()` para evitar SQL Injection

---

## 4. Observabilidade e Registro

- Todas as tabelas editáveis por usuários devem conter os seguintes campos de observabilidade:
  - `alterado_por_user_id`
  - `criado_por_user_id`
  - `datahora_criado`
  - `datahora_alterado`
  - `criacao_session_id`
  - `criacao_tracking_id`
  - `criacao_ip`
  - `criacao_ip_proxy`
  - `alteracao_session_id`
  - `alteracao_tracking_id`
  - `alteracao_ip`
  - `alteracao_ip_proxy`
> Todos esses campos devem aceitar `NULL` quando não for possível obter o dado.

- Telas com **buscas** devem:
  - Utilizar o recurso de busca nativo do Joomla
  - **Registrar log de busca** para análise posterior

---

## 5. Cache e Performance

- Páginas que não mudam com frequência devem ser **cacheáveis**
- Manter atenção à:
  - **Usabilidade**
  - **Responsividade**
  - **Desempenho (tempo de resposta)**

> A **padronização** é essencial para garantir qualidade e produtividade na evolução do sistema.

