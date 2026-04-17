# Moda Tropical - README Técnico Completo

> Última atualização: `2026-04-17`
>
> Raiz do projeto: `C:\modatropical` no workspace local e `/var/www/modatropical` na VPS
>
> Ambiente principal documentado aqui: VPS de produção com Nginx + PHP-FPM + MariaDB + Node.js + workers CLI em PHP

---

## Regra global de UTF-8 e acentuação

Todos os arquivos de texto do projeto devem permanecer em **UTF-8** do início ao fim. Não reencodar arquivos, não trocar acentos por versões sem acento e não substituir caracteres como `ç`, `á`, `é`, `í`, `ó`, `ú`, `ã`, `õ`, `â`, `ê` e `ô` por equivalentes ASCII.

Antes de finalizar qualquer alteração em textos visíveis, revise o arquivo-fonte e, quando possível, a página renderizada. Palavras como `política`, `privacidade`, `proteção`, `informação`, `avaliação`, `atualização`, `cobrança`, `condições`, `revisão` e `endereço` devem aparecer com acentuação correta e sem mojibake. Para detectar texto corrompido, procure caracteres como `U+00C3`, `U+00C2` e `U+FFFD` no contexto errado. A raiz do projeto possui `.editorconfig` com `charset = utf-8` para reforçar essa regra nos editores compatíveis.

## Regra principal de atualização do README

Esta regra passa a ser obrigatória no projeto:

- toda alteração relevante feita no código, na infra, no banco, no Nginx, no deploy, nos workers ou na VPS deve ser refletida neste `README.md` no mesmo ciclo da mudança
- nenhuma mudança deve ser considerada concluída sem atualizar a documentação correspondente neste arquivo
- sempre que algo for ajustado diretamente na VPS, o estado final aplicado em produção deve ser registrado aqui
- o final deste arquivo deve sempre conter um bloco de `onde estamos/paramos` com o ponto exato da última intervenção técnica

Objetivo prático dessa regra:

- evitar perda de contexto entre sessões
- evitar que a VPS fique diferente do que está documentado
- deixar claro o último estado válido conhecido do projeto

## 1. Objetivo deste README

Este README existe para ser a documentacao tecnica mais completa possivel do projeto **Moda Tropical** no estado atual.

Ele foi reescrito para refletir o que realmente existe no codigo hoje, e nao apenas o que era plano em iteracoes anteriores.

O foco aqui e:

- explicar o que o sistema entrega hoje
- registrar a arquitetura real da aplicacao
- mapear a estrutura canonica do repositorio
- documentar bootstrap, configuracao, modulos publicos e admin
- detalhar o modulo de mensagens, que continua sendo a area mais sensivel do sistema
- registrar o estado atual da fila de email/notificacao
- registrar o estado atual da fila de WhatsApp
- deixar claro que o provider atual de WhatsApp e `Evolution Go`
- documentar o modo `link preview`, que hoje e a alternativa mais estavel ao CTA em botao
- registrar onde ficam cache, logs, filas, webhooks e workers
- reduzir dependencia de memoria, conversa solta e contexto perdido

Este documento nao tenta simplificar artificialmente o projeto. O objetivo e ser util para manutencao real.

---

## 2. Resumo executivo

### 2.1 O que e o projeto

O Moda Tropical e uma loja virtual propria em PHP procedural, sem framework grande como base, com:

- vitrine publica
- area do cliente
- carrinho e checkout
- integracoes de pagamento
- painel administrativo
- modulo de mensagens promocionais
- notificacao interna
- email com renderizacao de arte
- WhatsApp em background

### 2.2 Stack principal

- PHP procedural
- MariaDB / MySQL
- Nginx
- PHP-FPM
- JavaScript vanilla no frontend
- Fabric.js no editor de mensagens V2
- Node.js + Puppeteer + Sharp no pipeline de render de artes
- workers CLI em PHP para processamento em background

### 2.3 Estado geral atual

Hoje o sistema principal da loja esta operacional e o modulo de mensagens ja nao esta mais no estado antigo de envio completamente sincrono.

O estado correto hoje e:

- loja publica funcional
- painel administrativo funcional
- editor de mensagens V2 funcional
- fila de email/notificacao implementada
- fila de WhatsApp implementada
- cache de render implementado
- SMTP persistente implementado no worker de email
- provider atual de WhatsApp consolidado em `Evolution Go`
- botao interativo de WhatsApp mantido como opcional, mas nao tratado como UX garantida
- modo de `link preview` implementado como alternativa mais estavel para CTA no WhatsApp

### 2.4 O que mudou em relacao a documentacoes antigas

Documentacoes antigas do projeto ainda refletiam um momento em que:

- a fila de mensagens era apenas plano
- o worker CLI ainda nao existia
- o SMTP persistente ainda nao existia
- o WhatsApp ainda passava por WPPConnect / Evolution API
- o preview Open Graph global ainda nao usava a imagem principal de promocoes

Isso nao corresponde mais ao estado real do codigo.

Hoje:

- a fila de email/notificacao ja existe no banco e no codigo
- o worker `scripts/message_worker.php` ja existe
- o worker `scripts/whatsapp_worker.php` ja existe
- a integracao canonica de WhatsApp foi consolidada em `includes/whatsapp_evolution_go.php`
- `includes/whatsapp_evolution.php` hoje e apenas um wrapper de compatibilidade
- o fallback global de `og:image` da loja passou a usar `assets/img/og-promocoes.png`

### 2.5 Gargalo principal atual

O maior gargalo tecnico atual nao e mais "como mandar em background".

Esse passo ja foi dado.

O gargalo atual e mais operacional e de UX:

- manter confiabilidade do envio em lote
- manter qualidade de render e cache
- administrar limites de throughput do WhatsApp
- conviver com a limitacao de CTA/botao nao-oficial em clientes WhatsApp
- continuar reduzindo variacoes entre preview do admin, email final e mensagem final no WhatsApp

### 2.6 Leitura honesta do estado do projeto

Se alguem perguntar hoje "onde estamos?", a resposta honesta e:

- o projeto esta funcional
- o modulo de mensagens esta muito mais maduro do que no inicio das iteracoes
- a arquitetura de fila ja saiu do papel
- a integracao de WhatsApp continua funcional, mas com limitacoes naturais do ecossistema nao-oficial
- o caminho mais estavel para CTA no WhatsApp hoje e `imagem + legenda + preview de link`

---

## 3. O que o sistema entrega

### 3.1 Loja publica

O frontend publico entrega:

- pagina inicial
- navegacao por categoria
- navegacao por marca
- pagina de produto
- pagina de promocoes
- busca
- carrinho
- checkout
- paginas institucionais e legais

### 3.2 Area do cliente

O cliente pode:

- criar conta
- fazer login e logout
- verificar email
- redefinir senha
- editar dados
- editar enderecos
- acompanhar pedidos
- visualizar cupons
- visualizar notificacoes
- salvar favoritos / itens

### 3.3 Painel admin

O painel administrativo cobre:

- dashboard
- produtos
- categorias
- marcas
- sabores
- tamanhos
- pedidos
- clientes
- cupons
- configuracoes da loja
- mensagens promocionais
- status e diagnostico de batches
- status da conexao de WhatsApp

### 3.4 Integracoes

O projeto contem integracoes para:

- SMTP
- Asaas
- login Google
- login Facebook
- login Apple
- TikTok
- WhatsApp via Evolution Go

---

## 4. Filosofia e estilo da aplicacao

### 4.1 Arquitetura geral

Hoje o sistema e majoritariamente:

- PHP procedural
- sem Laravel
- sem Symfony
- sem CodeIgniter
- sem ORM como camada principal

Isso tem implicacoes objetivas:

- a leitura dos arquivos costuma ser direta
- a relacao entre entrada HTTP e regra de negocio e curta
- o custo de rastrear fluxo entre arquivo publico, include e query costuma ser baixo
- a disciplina de organizacao de includes, helpers e convencoes importa muito

### 4.2 Estrategia de crescimento

O projeto nao foi convertido para um framework completo.

Em vez disso, ele cresceu por camadas:

- bootstrap unico
- includes compartilhados
- arquivos publicos simples na raiz
- admin em `admin/`
- workers CLI em `scripts/`
- filas persistidas no banco
- cache e logs em `storage/`

### 4.3 Regra pratica de manutencao

Quando existir duplicidade aparente entre um arquivo antigo e um arquivo novo, a regra e:

- tratar como canonicos os arquivos carregados por `includes/bootstrap.php`, pelos endpoints publicos reais e pelos workers reais
- tratar backups, forks internos e arquivos `tmp` como nao canonicos, salvo necessidade especifica de investigacao

---

## 5. Bootstrap e inicializacao

### 5.1 Arquivo de bootstrap canonico

O arquivo central de bootstrap e:

- `includes/bootstrap.php`

### 5.2 O que o bootstrap faz

Na pratica, ele:

- inicia sessao se necessario
- define timezone da aplicacao como `America/Sao_Paulo`
- define `BASE_PATH`
- carrega `.env` por `load_app_env_file()`
- resolve `APP_URL`
- define `APP_NAME`
- carrega os arquivos de configuracao de `config/`
- carrega os includes principais
- tenta restaurar login persistente de admin e cliente
- garante diretorios essenciais de upload

### 5.3 Ordem de carregamento relevante

Hoje o bootstrap carrega, nesta ordem funcional:

- `config/database.php`
- `config/mail.php`
- `config/whatsapp.php`
- `config/asaas.php`
- `config/google.php`
- `config/facebook.php`
- `config/apple.php`
- `config/tiktok.php`
- `includes/functions.php`
- `includes/mailer.php`
- `includes/customer_email_marketing.php`
- `includes/auth.php`
- `includes/google_auth.php`
- `includes/social_auth.php`
- `includes/customer_verification.php`
- `includes/customer_addresses.php`
- `includes/notifications.php`
- `includes/message_queue.php`
- `includes/coupons.php`
- `includes/storefront.php`
- `includes/customer_favorites.php`
- `includes/asaas.php`
- `includes/orders.php`

### 5.4 `APP_URL`

`APP_URL` nao e um valor puramente hardcoded.

Hoje ele pode vir de:

- `$_SERVER['APP_URL']`
- variavel de ambiente `APP_URL`
- deteccao automatica baseada em `DOCUMENT_ROOT`

Isso significa que o projeto ja tem uma camada de flexibilidade maior do que a documentacao antiga indicava.

---

## 6. Configuracao e segredos

### 6.1 Estado atual real

O estado atual nao e "100% arquivo PHP fixo" nem "100% variavel de ambiente".

O estado correto e misto:

- existe suporte a `.env` no bootstrap
- a configuracao de WhatsApp ja e fortemente baseada em variaveis de ambiente
- banco e mail ainda dependem de arquivos PHP de config

### 6.2 Arquivos de configuracao principais

- `config/database.php`
- `config/mail.php`
- `config/whatsapp.php`
- `config/asaas.php`
- `config/pagbank.php`
- `config/google.php`
- `config/facebook.php`
- `config/apple.php`
- `config/tiktok.php`

### 6.3 Leitura correta do debito tecnico

O debito tecnico de segredo ainda existe, mas precisa ser descrito com precisao:

- a migracao para `.env` comecou
- ela nao terminou
- `config/whatsapp.php` ja esta modelado para ambiente
- `config/mail.php` e `config/database.php` ainda merecem futura migracao mais limpa

### 6.4 Regras para documentacao

Este README pode citar:

- quais arquivos concentram configuracao
- quais modulos dependem dessas configs
- onde a app busca secrets

Este README nao deve copiar:

- senhas
- tokens
- chaves privadas
- API keys reais

---

## 7. Estrutura real do repositorio

### 7.1 Pastas principais canonicas

As pastas principais observadas na raiz sao:

- `admin/`
- `assets/`
- `config/`
- `database/`
- `includes/`
- `oauth/`
- `scripts/`
- `storage/`
- `templates/`
- `uploads/`

Tambem existem:

- `node_modules/`
- `.local-browser/`

Essas duas sao relevantes para o pipeline de render, mas nao sao o centro da logica de negocio.

### 7.2 Pastas nao canonicas ou auxiliares

Existem diretorios que nao devem ser tratados como fonte principal da aplicacao:

- `backupwhastcerto/`
- `manusia/`
- `modimanusia/`
- `tmp/`
- `tmp_codex_uploads/`
- `.tmp-evolution-api/`
- `.tmp-evolution-docs/`

Esses diretorios podem conter:

- snapshots
- forks internos
- testes
- material temporario
- experimentos de integracao

Eles nao devem ser lidos como a versao oficial do sistema, salvo quando a manutencao exigir comparacao historica.

### 7.3 Artefatos temporarios ou de trabalho

Na raiz tambem existem arquivos de apoio que nao sao fonte canonica do fluxo principal, por exemplo:

- `carrinho.php.codexbak-20260405154630`
- `tmp_whatsapp_preview_check.js`
- `_tmp_evolution_manager.js`
- `_tmp_evolution_manager.css`
- `_tmp_evolution_nginx.conf`
- `mensagens_ultimo_envio.txt`
- `respostasia.txt`

### 7.4 Arquivo `functions.php` na raiz

Existe um `functions.php` na raiz do projeto, mas ele nao e o helper principal carregado pelo sistema.

O helper canonico carregado pelo bootstrap e:

- `includes/functions.php`

Ao mexer em helpers, o alvo correto geralmente e `includes/functions.php`.

---

## 8. Mapa das paginas publicas na raiz

Arquivos PHP publicos observados na raiz:

- `index.php`
- `busca.php`
- `categoria.php`
- `marca.php`
- `produto.php`
- `promocoes.php`
- `carrinho.php`
- `finalizar-pedido.php`
- `rastreio.php`
- `cadastro.php`
- `entrar.php`
- `sair.php`
- `completar-cadastro.php`
- `editar-contato.php`
- `editar-enderecos.php`
- `esqueci-senha.php`
- `redefinir-senha.php`
- `criando-conta.php`
- `minha-conta.php`
- `meus-pedidos.php`
- `meus-cupons.php`
- `favoritos.php`
- `itens-salvos.php`
- `notificacoes.php`
- `notificacoes-widget.php`
- `imagem-critica.php`
- `asaas-webhook.php`
- `pagbank-webhook.php`
- `pagbank-homologacao.php`
- `politica-de-privacidade.php`
- `termos-de-servico.php`
- `exclusao-de-dados.php`
- `whatsapp-webhook.php`

---

## 9. Mapa dos modulos canonicos

### 9.1 `admin/`

Arquivos principais confirmados:

- `index.php`
- `login.php`
- `logout.php`
- `produtos.php`
- `produto_form.php`
- `categorias.php`
- `categoria_form.php`
- `marcas.php`
- `marca_form.php`
- `sabores.php`
- `sabor_form.php`
- `tamanhos.php`
- `tamanho_form.php`
- `pedidos.php`
- `clientes.php`
- `cliente_form.php`
- `cupons.php`
- `cupom_form.php`
- `configuracoes.php`
- `mensagens.php`
- `message_batch_status.php`
- `message_debug_ingest.php`
- `whatsapp_connection.php`
- `evolution-contatos.php`

### 9.2 `includes/`

Arquivos particularmente relevantes hoje:

- `bootstrap.php`
- `functions.php`
- `auth.php`
- `mailer.php`
- `mailer_smtp_session.php`
- `storefront.php`
- `orders.php`
- `notifications.php`
- `coupons.php`
- `customer_messages.php`
- `customer_message_scene.php`
- `customer_message_render_cache.php`
- `customer_message_queue_dispatch.php`
- `message_queue.php`
- `message_queue_batch_store.php`
- `message_queue_lease.php`
- `message_queue_runtime.php`
- `message_queue_shared.php`
- `admin_message_queue.php`
- `admin_message_batch_status.php`
- `admin_message_send_log.php`
- `admin_whatsapp_queue.php`
- `whatsapp_evolution.php`
- `whatsapp_evolution_go.php`
- `whatsapp_queue.php`
- `asaas.php`
- `google_auth.php`
- `social_auth.php`
- `social_auth_cluster.php`

### 9.3 `assets/`

Subpastas principais:

- `assets/css/`
- `assets/js/`
- `assets/img/`
- `assets/vendor/`

Arquivos de destaque:

- `assets/css/public.css`
- `assets/css/admin.css`
- `assets/js/app.js`
- `assets/js/admin.js`
- `assets/js/admin-message-editor.js`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/admin-message-batch-status.js`
- `assets/js/message-scene-renderer.js`
- `assets/vendor/fabric.min.js`
- `assets/img/og-promocoes.png`

### 9.4 `scripts/`

Scripts principais:

- `scripts/message_worker.php`
- `scripts/whatsapp_worker.php`
- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`
- `scripts/render_message_scene.m`

### 9.5 `templates/`

Template confirmado:

- `templates/message-renderer.html`

### 9.6 `database/`

Arquivos relevantes:

- `database/cardapio_digital.sql`
- `database/vps_fresh_install.sql`
- `database/update_message_queue.sql`
- `database/update_whatsapp_queue.sql`
- `database/update_whatsapp_queue_reconciliation.sql`
- `database/update_*.sql` diversos

---

## 10. Frontend publico e URLs

### 10.1 Contexto publico compartilhado

O frontend publico usa como base:

- `includes/storefront.php`
- `includes/header.php`
- `includes/footer.php`
- `includes/storefront_top.php`
- `includes/storefront_footer.php`
- `includes/storefront_product_card.php`
- `includes/storefront_floating_cart.php`

As paginas publicas principais chamam `storefront_build_context()` para montar o contexto compartilhado da vitrine.

### 10.2 Papel de `includes/functions.php`

`includes/functions.php` concentra boa parte dos helpers utilitarios da aplicacao.

Alguns helpers importantes para roteamento e assets:

- `app_url()`
- `absolute_app_url()`
- `app_url_with_query()`
- `asset_url()`
- `critical_image_url()`
- `promotions_url()`
- `absolute_promotions_url()`
- `app_public_route_path()`

### 10.3 Friendly URLs

O projeto ainda tem arquivos `.php` reais na raiz, mas parte da construcao de links ja trabalha com rotas amigaveis.

Exemplo de responsabilidade de `app_public_route_path()`:

- retornar caminho amigavel para home
- retornar `/promocoes`
- retornar rotas de categoria, marca e produto sem necessariamente expor `.php` na URL final

### 10.4 Responsabilidades de `includes/storefront.php`

Este include concentra a maior parte da consulta e montagem de contexto da loja publica.

Responsabilidades resumidas:

- buscar configuracoes publicas da loja
- carregar categorias ativas
- carregar marcas ativas
- carregar produtos ativos
- montar produtos em promocao
- resolver slugs
- construir contexto da home
- construir contexto de categoria
- construir contexto de marca
- construir contexto de produto
- agrupar produtos por categoria e marca
- normalizar busca e aliases

### 10.5 Pagina inicial

Arquivo:

- `index.php`

Papel:

- montar vitrine principal
- exibir destaques
- exibir secoes por agrupamento
- usar o ecossistema compartilhado do storefront

### 10.6 Pagina de categoria

Arquivo:

- `categoria.php`

Papel:

- resolver categoria por slug
- listar produtos da categoria
- reaproveitar o contexto compartilhado do storefront

### 10.7 Pagina de marca

Arquivo:

- `marca.php`

Papel:

- resolver marca por slug
- listar produtos da marca

### 10.8 Pagina de produto

Arquivo:

- `produto.php`

Papel:

- resolver produto por slug
- carregar detalhes, imagens e contexto de compra

### 10.9 Pagina de promocoes

Arquivo:

- `promocoes.php`

Papel:

- listar os produtos em promocao
- definir metadados especificos de Open Graph
- usar imagem canonica de preview de promocoes

---

## 11. Open Graph, favicon e preview social

### 11.1 Arquivo responsavel

O cabecalho HTML compartilhado da loja publica e:

- `includes/header.php`

### 11.2 O que ele define

Entre outras coisas, ele controla:

- `<title>`
- `meta description`
- `og:title`
- `og:description`
- `og:url`
- `og:image`
- favicon
- preloads de imagens criticas
- estilos globais

### 11.3 Fallback global de `og:image`

Hoje o fallback global de preview da loja passou a priorizar:

- `assets/img/og-promocoes.png`

Se esse arquivo nao existir, o fallback tenta:

- `assets/img/og-default.png`

Se isso tambem nao existir, ele cai para:

- favicon / logo padrao

### 11.4 Implicacao pratica

Isso significa que, quando uma pagina publica nao definir `og:image` manualmente, ela tende a herdar a imagem global de promocoes.

Na pratica, isso afeta:

- home
- categoria
- marca
- produto individual
- outras paginas publicas que usam `includes/header.php` sem sobrescrever imagem

### 11.5 Caso especifico de `promocoes.php`

`promocoes.php` define explicitamente:

- `openGraphTitle`
- `openGraphDescription`
- `openGraphUrl`
- `openGraphImage = assets/img/og-promocoes.png`

### 11.6 Observacao importante sobre miniatura no WhatsApp

O projeto consegue influenciar o preview por:

- `og:image`
- `og:title`
- `og:description`

Mas nao controla:

- tamanho final da miniatura exibida no WhatsApp
- layout do card do WhatsApp
- cache interno do WhatsApp / Meta

Ou seja, a loja controla os metadados, mas o layout final do preview continua sendo decisao do cliente WhatsApp.

---

## 12. Area do cliente

### 12.1 Arquivos publicos relacionados

- `cadastro.php`
- `entrar.php`
- `sair.php`
- `completar-cadastro.php`
- `editar-contato.php`
- `editar-enderecos.php`
- `esqueci-senha.php`
- `redefinir-senha.php`
- `criando-conta.php`
- `minha-conta.php`
- `meus-pedidos.php`
- `meus-cupons.php`
- `favoritos.php`
- `itens-salvos.php`
- `notificacoes.php`
- `notificacoes-widget.php`

### 12.2 Includes principais desta area

- `includes/auth.php`
- `includes/customer_verification.php`
- `includes/customer_addresses.php`
- `includes/customer_favorites.php`
- `includes/notifications.php`
- `includes/orders.php`
- `includes/coupons.php`

### 12.3 Responsabilidades por modulo

`auth.php`:

- login de admin e cliente
- sessao
- remember login
- validacao de estado autenticado

`customer_verification.php`:

- verificacao de email
- fluxo de token de verificacao

`customer_addresses.php`:

- CRUD de enderecos
- normalizacao de dados de endereco

`customer_favorites.php`:

- favoritos
- itens salvos

`notifications.php`:

- listar notificacoes
- contar nao lidas
- marcar como lidas
- apagar
- criar notificacao individual
- criar notificacao em massa

`orders.php`:

- regras de status
- rastreio
- snapshots de endereco
- totalizacao e estado do pedido

---

## 13. Painel administrativo

### 13.1 Arquivos principais

O admin esta em `admin/` e os pontos mais importantes hoje sao:

- `index.php`
- `login.php`
- `logout.php`
- `produtos.php`
- `produto_form.php`
- `categorias.php`
- `categoria_form.php`
- `marcas.php`
- `marca_form.php`
- `sabores.php`
- `sabor_form.php`
- `tamanhos.php`
- `tamanho_form.php`
- `pedidos.php`
- `clientes.php`
- `cliente_form.php`
- `cupons.php`
- `cupom_form.php`
- `configuracoes.php`
- `mensagens.php`
- `message_batch_status.php`
- `message_debug_ingest.php`
- `whatsapp_connection.php`

### 13.2 Estrutura visual compartilhada do admin

Os includes visuais centrais do admin sao:

- `includes/admin_header.php`
- `includes/admin_footer.php`

### 13.3 Modulo de mensagens como centro sensivel

Entre todos os modulos do admin, o arquivo mais sensivel hoje e:

- `admin/mensagens.php`

Motivo:

- ele concentra edicao de campanha
- persiste projeto visual
- prepara filas de notificacao e email
- prepara fila de WhatsApp
- consulta status de conexao do provider
- registra debug de cliente
- monta snapshots operacionais para analise

---

## 14. Modulo de mensagens: arquitetura atual

### 14.1 Arquivos principais do modulo

Hoje o modulo de mensagens depende principalmente de:

- `admin/mensagens.php`
- `includes/customer_messages.php`
- `includes/customer_message_scene.php`
- `includes/customer_message_render_cache.php`
- `includes/admin_message_queue.php`
- `includes/admin_whatsapp_queue.php`
- `includes/admin_message_send_log.php`
- `includes/whatsapp_evolution.php`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `assets/js/admin-message-batch-status.js`
- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`
- `scripts/message_worker.php`
- `scripts/whatsapp_worker.php`
- `templates/message-renderer.html`

### 14.2 Objetivo do modulo

O modulo foi construido para permitir campanhas e comunicacoes como:

- promo geral
- reengajamento
- boas-vindas
- estoque novo
- comunicacao individual
- notificacao interna
- email com arte
- WhatsApp com imagem e CTA

### 14.3 Canais de saida suportados hoje

No estado atual, uma campanha pode gerar:

- notificacao interna
- email
- WhatsApp

Cada canal ja tem seu proprio pipeline e nao precisa mais ser processado inteiramente no request web.

### 14.4 Persistencia de projeto do admin

Os projetos de mensagem sao persistidos hoje em:

- `storage/messages/projects.json`

Na abertura de projeto salvo pelo painel:

- o projeto continua unico em `projects.json`, com payload compartilhado entre email e WhatsApp
- o link de abertura deve carregar `project=<id>` com `channel=<aba_ativa>`
- quando existir `project` ou `channel` na URL, o admin deve respeitar a aba pedida no request
- a aba salva em `sessionStorage` nao deve sobrescrever a aba pedida no clique de abrir

### 14.5 Campos importantes persistidos no draft/projeto

Entre os campos relevantes hoje estao:

- `project_id`
- `project_name`
- `recipient_mode`
- `customer_id`
- `message_kind`
- `title`
- `message`
- `hero_image_path`
- `link_url`
- `image_link_url`
- `button_label`
- `scene_json`
- `fabric_scene_json`
- `editor_layers_json`
- `editor_engine`
- `whatsapp_message`
- `whatsapp_image_path`
- `whatsapp_button_enabled`
- `whatsapp_link_preview_enabled`
- `whatsapp_button_title`
- `whatsapp_button_label`
- `whatsapp_button_url`
- `whatsapp_button_footer`
- `send_notification`
- `send_email`
- `send_whatsapp`

### 14.6 Motores e formatos de cena

Hoje os formatos de estado relevantes sao:

- `scene_json`
- `fabric_scene_json`
- `editor_layers_json`

Eles coexistem porque o sistema precisa:

- manter compatibilidade com estado visual salvo
- reconstruir o editor
- alimentar o renderer
- manter uma camada de normalizacao entre legado e V2

### 14.7 Arquivo de normalizacao

O arquivo central para lidar com cena e compatibilidade e:

- `includes/customer_message_scene.php`

### 14.8 Editor visual atual

O editor principal do admin hoje e o V2:

- `assets/js/admin-message-editor-v2.js`

Ele usa:

- Fabric.js
- renderizacao auxiliar da cena
- sincronizacao constante dos estados hidden do formulario

### 14.9 Template do renderer

O template base do renderer HTML/browser-based e:

- `templates/message-renderer.html`

### 14.10 Pipeline de render

O pipeline atual de render envolve:

- preparacao de cena em PHP
- renderer browser-based com Puppeteer
- composicao final via scripts Node

Arquivos principais:

- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`

### 14.11 Dependencias Node do renderer

O `package.json` atual deixa claro o foco do pipeline:

- script `render:scene`
- script `render:composicao`
- `@puppeteer/browsers`
- `fabric`
- `puppeteer-core`
- `sharp`

Isso confirma que o renderer do projeto nao e meramente PHP. Ele depende de um pipeline Node/browser para gerar as artes finais.

---

## 15. Presets, tokenizacao e personalizacao

### 15.1 Arquivo principal

O arquivo central de composicao textual da campanha e:

- `includes/customer_messages.php`

### 15.2 Presets disponiveis hoje

O codigo atual define presets de campanha como:

- `manual`
- `ofertao`
- `descontos_incriveis`
- `oi_sumido`
- `boas_vindas`
- `estoque_novo`

### 15.3 Papel dos presets

Os presets fornecem:

- titulo inicial
- mensagem inicial
- variacoes de saudacao
- base de personalizacao

### 15.4 Tokens centrais do sistema

Os tokens principais confirmados no codigo hoje sao:

- `{{nome}}`
- `{{primeiro_nome}}`
- `{{loja}}`
- `{{saudacao_sumido}}`
- `{{sumido_ou_sumida}}`
- `{{bem_vindo_ou_vinda}}`
- `{{promocoes_url}}`
- `{{loja_url}}`
- `{{produto_nome}}`
- `{{produto_url}}`

### 15.5 Tokens extras usados no WhatsApp

O modulo de WhatsApp adiciona contexto complementar quando existe pedido relacionado:

- `{{pedido}}`
- `{{pedido_id}}`
- `{{status_pedido}}`
- `{{pedido_total}}`

### 15.6 Alias de compatibilidade

O helper do WhatsApp tambem aceita aliases com uma chave simples para evitar quebrar textos antigos, por exemplo:

- `{primeiro_nome}`
- `{nome}`

### 15.7 Como a tokenizacao e aplicada

Em termos funcionais:

- o admin salva texto com placeholders
- no momento de enfileirar e/ou despachar, o sistema resolve tokens por cliente
- a personalizacao afeta titulo, corpo, links e, quando aplicavel, elementos visuais

---

## 16. Cache de render e personalizacao visual

### 16.1 Arquivo principal

- `includes/customer_message_render_cache.php`

### 16.2 O que o cache tenta resolver

Renderizar arte por cliente e caro. O cache existe para:

- evitar render desnecessario
- reaproveitar arte quando a parte visual nao muda
- separar melhor custo de render e custo de envio

### 16.3 Regra correta do cache

O cache nao pode assumir que toda campanha e estatica.

Ele precisa considerar:

- hash da cena normalizada
- hash dos tokens visuais
- versao do renderer
- versao do normalizador
- fingerprint do background
- identificacao do projeto

### 16.4 Versao de normalizacao

Hoje existe no codigo:

- `CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION = cm_scene_norm_v2_project_cache`

### 16.5 Informacoes que entram no descriptor

O descriptor de cache carrega, entre outras coisas:

- `project_id`
- `project_name`
- `project_key`
- `cache_key`
- `scene_hash`
- `visual_token_hash`
- `visual_token_names`
- `visual_token_payload`
- `filename_slug`
- `has_visual_tokens`
- `normalizer_version`
- `scene_version`
- `renderer_version`

### 16.6 Onde o cache fica

Metadados e runtime internos:

- `storage/messages/render-cache`

Imagens renderizadas reutilizaveis publicas:

- `uploads/messages/render-cache`

### 16.7 Leitura operacional correta

O cache de render nao e mais uma ideia futura. Ele ja faz parte da arquitetura atual do modulo de mensagens.

---

## 17. Fila de email e notificacao

### 17.1 Estado atual

A fila de email/notificacao ja esta implementada.

Isso precisa ficar claro porque documentacoes antigas ainda a descreviam como "proxima fase".

### 17.2 Arquivos principais

- `includes/message_queue.php`
- `includes/message_queue_shared.php`
- `includes/message_queue_batch_store.php`
- `includes/message_queue_lease.php`
- `includes/message_queue_runtime.php`
- `includes/admin_message_queue.php`
- `includes/customer_message_queue_dispatch.php`
- `includes/admin_message_batch_status.php`
- `includes/admin_message_send_log.php`
- `scripts/message_worker.php`

### 17.3 Migracao SQL principal

- `database/update_message_queue.sql`

### 17.4 Tabelas principais

- `message_batches`
- `message_batch_jobs`
- `message_send_log`

### 17.5 O que o request web faz hoje

Ao clicar em enviar no admin, o fluxo correto hoje e:

- resolver destinatarios
- montar payloads
- criar batch
- persistir jobs
- devolver resposta rapida ao admin

O request web nao precisa mais processar tudo ate o fim.

### 17.6 O que o worker faz

O worker real e:

- `scripts/message_worker.php`

Ele:

- roda apenas via CLI
- faz lease de jobs pendentes
- processa canais `notification` e `email`
- classifica falhas como transientes ou permanentes
- reaproveita sessao SMTP persistente quando o driver e `smtp`

### 17.7 Parametros CLI do worker

O worker aceita opcoes como:

- `--once`
- `--limit=`
- `--lease-seconds=`
- `--sleep-ms=`
- `--worker-id=`

### 17.8 SMTP persistente

O SMTP persistente tambem ja saiu do papel.

Arquivos relevantes:

- `includes/mailer.php`
- `includes/mailer_smtp_session.php`

### 17.9 Regras atuais da sessao SMTP

Os defaults observados hoje sao:

- `max_messages = 30`
- `max_idle_seconds = 90`
- `noop_after_seconds = 15`

Isso significa:

- o worker pode enviar varios emails na mesma sessao SMTP
- a conexao e reciclada de forma controlada
- a app tenta manter throughput sem reabrir autenticacao a cada email

### 17.10 Responsabilidades de `customer_message_queue_dispatch.php`

Esse arquivo cuida de pontos importantes como:

- despacho de notificacao interna
- despacho de email
- dedupe operacional
- montagem de metadata de email
- respeito a opt-out de marketing quando aplicavel

### 17.11 Status e progresso no admin

O admin ja tem endpoint para acompanhar status de batch de email/notificacao:

- `admin/message_batch_status.php`

E o polling visual do admin usa:

- `assets/js/admin-message-batch-status.js`

---

## 18. Fila de WhatsApp

### 18.1 Estado atual

A fila de WhatsApp tambem ja esta implementada.

Ela nao e mais uma ideia futura nem um envio diretamente sincrono no request do admin.

### 18.2 Arquivos principais

- `includes/whatsapp_queue.php`
- `includes/admin_whatsapp_queue.php`
- `includes/whatsapp_evolution.php`
- `includes/whatsapp_evolution_go.php`
- `scripts/whatsapp_worker.php`
- `admin/whatsapp_connection.php`
- `whatsapp-webhook.php`

### 18.3 Migracoes SQL principais

- `database/update_whatsapp_queue.sql`
- `database/update_whatsapp_queue_reconciliation.sql`

### 18.4 Tabelas principais

- `whatsapp_batches`
- `whatsapp_batch_jobs`
- `whatsapp_send_log`

### 18.5 Status de batch de WhatsApp

Status de batch observados no codigo:

- `queued`
- `processing`
- `completed`
- `completed_with_failures`
- `failed`
- `cancelled`

### 18.6 Status de job de WhatsApp

Status de job observados no codigo:

- `pending`
- `reserved`
- `processing`
- `retry`
- `accepted_by_evolution`
- `transport_ack`
- `delivered`
- `read`
- `render_suspect`
- `fallback_executed`
- `sent`
- `failed`
- `failed_fallback`
- `cancelled`

### 18.7 Enriquecimento de schema em runtime

`includes/whatsapp_queue.php` contem rotinas que garantem colunas operacionais adicionais, como:

- `accepted_jobs`
- `transport_ack_jobs`
- `delivered_jobs`
- `read_jobs`
- `render_suspect_jobs`
- `fallback_jobs`

E tambem campos de job como:

- `send_variant`
- `button_type`
- `has_thumbnail`
- `provider_message_id`
- `provider_remote_jid`
- `provider_from_me`
- `accepted_http_status`
- `accepted_at`
- `last_provider_event`
- `last_provider_status`
- `last_provider_event_at`
- `transport_ack_at`
- `delivered_at`
- `read_at`
- `render_suspect_at`
- `render_suspect_reason`
- `fallback_parent_job_id`
- `fallback_trigger_at`
- `fallback_trigger_reason`
- `fallback_attempt_no`

### 18.8 O que o request web faz

Ao enviar campanha com WhatsApp no admin, o request hoje:

- resolve os destinatarios
- aplica tokenizacao
- decide o modo de envio
- monta snapshot do payload
- cria batch
- cria jobs
- responde ao navegador

### 18.9 O que o worker faz

O worker real e:

- `scripts/whatsapp_worker.php`

Ele:

- roda apenas via CLI
- faz lease de jobs pendentes
- classifica falhas
- envia texto, media, button ou link preview
- registra aceite do provider
- tenta acompanhar reconciliacao via webhook/eventos

### 18.10 Parametros CLI do worker

Ele aceita opcoes como:

- `--once`
- `--limit=`
- `--lease-seconds=`
- `--sleep-ms=`
- `--worker-id=`

### 18.11 Delay e espacamento anti-abuso

Hoje a configuracao relevante em `config/whatsapp.php` ja define:

- `WHATSAPP_SEND_DELAY_MS`, com default observado de `7000`
- `WHATSAPP_QUEUE_RANDOM_GAP_MIN_SECONDS`, default `5`
- `WHATSAPP_QUEUE_RANDOM_GAP_MAX_SECONDS`, default `30`

Na pratica:

- cada envio pode levar um delay de provider
- jobs em lote sao espacados aleatoriamente em uma faixa conservadora

Isso ja responde a preocupacao operacional de burst agressivo.

---

## 19. Provider atual de WhatsApp: Evolution Go

### 19.1 Estado canonico atual

Hoje o provider oficial do projeto e:

- `Evolution Go`

### 19.2 Arquivo principal de configuracao

- `config/whatsapp.php`

### 19.3 Arquivo principal de implementacao

- `includes/whatsapp_evolution_go.php`

### 19.4 Arquivo de compatibilidade

- `includes/whatsapp_evolution.php`

Hoje ele existe basicamente para manter compatibilidade de include e apontar para o modulo real do Evolution Go.

### 19.5 Variaveis e constantes principais

Entre as constantes relevantes hoje estao:

- `WHATSAPP_PROVIDER`
- `WHATSAPP_EVOLUTION_GO_ENABLED`
- `WHATSAPP_EVOLUTION_GO_BASE_URL`
- `WHATSAPP_EVOLUTION_GO_API_KEY`
- `WHATSAPP_EVOLUTION_GO_INSTANCE_NAME`
- `WHATSAPP_EVOLUTION_GO_INSTANCE_TOKEN`
- `WHATSAPP_EVOLUTION_GO_INSTANCE_ID`
- `WHATSAPP_EVOLUTION_GO_TIMEOUT`
- `WHATSAPP_SEND_DELAY_MS`
- `WHATSAPP_QUEUE_RANDOM_GAP_MIN_SECONDS`
- `WHATSAPP_QUEUE_RANDOM_GAP_MAX_SECONDS`
- `WHATSAPP_PUBLIC_BASE_URL`
- `WHATSAPP_WEBHOOK_ENABLED`
- `WHATSAPP_WEBHOOK_TOKEN`
- `WHATSAPP_WEBHOOK_BASE64`
- `WHATSAPP_FALLBACK_WAIT_SECONDS`

### 19.6 Aliases legados

O config ainda define aliases de compatibilidade para:

- `WHATSAPP_EVOLUTION_*`
- `WHATSAPP_WPPCONNECT_*`

Isso nao significa que WPPConnect segue ativo como provider canonico. Significa apenas que o codigo preserva nomes antigos para nao quebrar referencias legadas.

### 19.7 Responsabilidades de `includes/whatsapp_evolution_go.php`

Este arquivo centraliza:

- leitura e validacao de config
- normalizacao de telefone
- helpers de request HTTP
- redacao de logs sensiveis
- log de debug do provider
- status de conexao
- envio de texto
- envio de media
- envio de button
- helpers de webhook

### 19.8 Logs operacionais do provider

Os logs de WhatsApp ficam em:

- `storage/logs/whatsapp/whatsapp-AAAA-MM-DD.ndjson`

---

## 20. Modos de envio de WhatsApp no admin

### 20.1 Modos efetivos hoje

O admin trabalha hoje com tres modos efetivos:

- `media`
- `button`
- `link_preview`

### 20.2 Como o modo e decidido

A decisao e baseada principalmente em:

- `whatsapp_button_enabled`
- `whatsapp_link_preview_enabled`
- `whatsapp_button_url`

### 20.3 Modo `media`

E o modo padrao.

Comportamento:

- envia imagem + legenda
- sem tentar CTA interativo

### 20.4 Modo `button`

E opcional.

Ele foi mantido no projeto porque o usuario quis preservar a possibilidade, mas a leitura tecnica correta e:

- existe implementacao
- o worker consegue montar payload de button
- a renderizacao no cliente WhatsApp nao e confiavel
- nao deve ser vendido internamente como UX garantida

### 20.5 Modo `link_preview`

Hoje e a alternativa mais estavel para CTA.

Comportamento:

1. envia a imagem com legenda
2. envia uma segunda mensagem so com a URL
3. deixa o proprio WhatsApp gerar o card de preview

### 20.6 Motivo da escolha por `link_preview`

Na pratica de testes recentes:

- ele funciona melhor que botao nao-oficial
- evita o problema de mensagem quebrada no WhatsApp Web
- produz um resultado visual mais profissional
- depende menos de renderizacao interativa experimental

### 20.7 Limpeza da legenda no modo `link_preview`

Para evitar texto duplicado, o codigo atual remove linhas redundantes de URL da legenda quando o modo preview esta ativo.

Funcoes relevantes:

- `admin_whatsapp_strip_link_preview_url()`
- `whatsapp_worker_strip_link_preview_url()`

### 20.8 Importante sobre preview do link

O preview final depende dos metadados da pagina alvo.

Entao o sistema de WhatsApp sozinho nao define:

- titulo do card final
- descricao do card final
- miniatura final do card

Quem define isso e principalmente a pagina de destino, via Open Graph.

---

## 21. Conexao, webhook e observabilidade de WhatsApp

### 21.1 Endpoint admin para status de conexao

O endpoint administrativo de status de conexao e:

- `admin/mensagens.php?whatsapp_connection_status=1`

Ele:

- exige auth de admin
- aceita `refresh=1`
- consulta o provider
- devolve payload JSON com estado da conexao

### 21.2 Uso no painel

`admin/mensagens.php` usa esse endpoint para:

- mostrar se o WhatsApp esta conectado
- mostrar QR quando necessario
- atualizar status sob demanda

Configuracao operacional validada em producao em `2026-04-17`:

- `.env` na raiz da aplicacao: `/var/www/modatropical/.env`
- `WHATSAPP_EVOLUTION_GO_BASE_URL=http://127.0.0.1:8090`
- `WHATSAPP_EVOLUTION_GO_API_KEY=<GLOBAL_API_KEY do container modatropical_evolution_go>`
- `WHATSAPP_EVOLUTION_GO_INSTANCE_NAME=modatropical`

Observacoes operacionais importantes:

- existe outro processo Node ouvindo `127.0.0.1:8080` na VPS
- a instancia correta do projeto `modatropical_evolution_go` esta publicada em `127.0.0.1:8090`
- sem a API key no `.env`, o admin marca a integracao como desabilitada
- o bootstrap do QR precisa tratar `Connected=true` sem `jid` como desconectado para buscar um QR novo

### 21.3 Webhook publico

O webhook publico e:

- `whatsapp-webhook.php`

### 21.4 O que o webhook faz

Ele:

- aceita apenas `POST`
- verifica se o provider esta habilitado
- verifica se as tabelas da fila existem
- valida autenticacao do webhook
- decodifica JSON
- chama `whatsapp_queue_apply_webhook_payload()`
- devolve informacoes de evento, job e batch relacionados

### 21.5 Importancia operacional

Esse webhook e importante para reconciliacao de status, porque o job de WhatsApp nao vive apenas do aceite imediato do provider.

Ele precisa tambem acompanhar:

- aceite
- ack de transporte
- entrega
- leitura
- falhas ou sinais suspeitos

### 21.6 Logs de debug e runtime

Pontos importantes de observabilidade hoje:

- `storage/logs/whatsapp/*.ndjson`
- logs de batches no banco
- logs de send de email/notificacao
- debug do editor no admin
- snapshots de payload e cena em `admin/mensagens.php`

---

## 22. Banco de dados

### 22.1 Banco principal

O projeto continua trabalhando sobre o banco principal configurado em:

- `config/database.php`

Historicamente o schema principal e o da loja `cardapio_digital`, mas o ponto mais importante aqui e a divisao funcional das tabelas.

### 22.2 Grupos de tabelas da loja

Grupos principais:

- administracao e configuracao
- clientes e identidade
- catalogo
- cupons
- notificacoes
- pedidos

### 22.3 Tabelas recorrentes da aplicacao principal

Entre as tabelas principais da loja estao:

- `admins`
- `configuracoes`
- `clientes`
- `cliente_identities`
- `cliente_email_verificacoes`
- `cliente_email_alteracoes`
- `cliente_enderecos`
- `cliente_password_resets`
- `cliente_remember_tokens`
- `categorias`
- `marcas`
- `sabores`
- `produtos`
- `produto_sabores`
- `produto_imagens`
- `cupons`
- `cupom_produtos`
- `cupom_marcas`
- `cliente_cupons`
- `cliente_notificacoes`
- `pedidos`
- `pedido_itens`
- `pedido_historico`

### 22.4 Tabelas de fila de mensagem

Fila de email/notificacao:

- `message_batches`
- `message_batch_jobs`
- `message_send_log`

Fila de WhatsApp:

- `whatsapp_batches`
- `whatsapp_batch_jobs`
- `whatsapp_send_log`

### 22.5 Estrategia de evolucao de schema

O projeto continua usando SQL versionado por arquivo.

Ou seja:

- nao ha uma engine de migrations de framework
- a disciplina de aplicar os `update_*.sql` corretamente continua sendo importante

### 22.6 Leitura correta do estado do banco

O ponto mais importante hoje e que o banco ja contem as tabelas necessarias para filas de mensagens e WhatsApp.

Documentacoes antigas que falavam dessas tabelas como "futuras" ja nao servem mais.

---

## 23. Storage, uploads e artefatos gerados

### 23.1 Pastas em `storage/`

Pastas observadas:

- `storage/apple`
- `storage/google`
- `storage/logs`
- `storage/mail`
- `storage/messages`

### 23.2 Pastas importantes dentro de `storage/messages`

Uso atual do modulo de mensagens:

- `storage/messages/projects.json`
- `storage/messages/emoji-cache`
- `storage/messages/render-cache`
- `storage/messages/render-runtime`
- `storage/messages/render-tmp`

### 23.3 Uso das pastas de storage

`storage/mail`:

- fallback e artefatos de email

`storage/logs`:

- logs tecnicos
- logs de runtime
- logs do WhatsApp

`storage/messages`:

- projetos
- cache
- runtime do renderer
- temporarios do modulo de mensagens

### 23.4 Pastas em `uploads/`

Pastas observadas:

- `uploads/brands`
- `uploads/messages`
- `uploads/products`
- `uploads/store`

### 23.5 Uso atual de `uploads/store`

`uploads/store` concentra artefatos importantes do admin de mensagens, incluindo:

- `uploads/store/message-assets`
- `uploads/store/whatsapp-assets`

### 23.6 Uso atual de `uploads/messages`

`uploads/messages/render-cache` concentra imagens renderizadas reaproveitaveis do modulo de mensagens.

---

## 24. Integracoes externas

### 24.1 Email

Arquivos centrais:

- `config/mail.php`
- `includes/mailer.php`
- `includes/mailer_smtp_session.php`

### 24.2 Asaas

Arquivos centrais:

- `config/asaas.php`
- `includes/asaas.php`
- `asaas-webhook.php`

Estado atual:

- a integracao real da Asaas esta temporariamente desativada em `config/asaas.php`
- `Pix` abre modal proprio com QR Code + copia-e-cola dentro do checkout
- `Cartao online` voltou a aparecer no checkout para ajuste de layout e UX, mas continua sem processamento real enquanto a Asaas estiver desligada
- `asaas-webhook.php` atualiza o pedido a partir dos eventos de pagamento recebidos do Asaas
- existe um modo temporario de teste visual do Pix controlado por `ASAAS_PIX_UI_TEST_MODE` em `config/asaas.php`
- com `ASAAS_PIX_UI_TEST_MODE = true`, o checkout Pix em AJAX nao chama a API da Asaas e nao cria pedido real; ele devolve um QR de teste local apenas para lapidar o popup
- com esse modo ativo, `includes/asaas.php` tambem bloqueia chamadas reais de `POST /payments` com `billingType = PIX` e `GET /payments/{id}/pixQrCode`
- nesse modo de teste, o contador expira localmente no frontend e os links finais voltam para `finalizar-pedido.php`

### 24.3 PagBank

Integracao removida do codigo canonico em `2026-04-14`.

O gateway online ativo da loja voltou a ser:

- `Asaas`

### 24.4 Social login

Arquivos centrais:

- `config/google.php`
- `config/facebook.php`
- `config/apple.php`
- `includes/google_auth.php`
- `includes/social_auth.php`
- `includes/social_auth_cluster.php`

### 24.5 TikTok

Arquivos centrais:

- `config/tiktok.php`

### 24.6 WhatsApp

Arquivos centrais:

- `config/whatsapp.php`
- `includes/whatsapp_evolution_go.php`
- `admin/whatsapp_connection.php`
- `whatsapp-webhook.php`
- `scripts/whatsapp_worker.php`

---

## 25. Workers, runtime e operacao

### 25.1 Workers reais

Os dois workers principais hoje sao:

- `scripts/message_worker.php`
- `scripts/whatsapp_worker.php`

### 25.2 Responsabilidade de cada worker

`message_worker.php`:

- notificacao interna
- email
- retries e classificacao de erro
- SMTP persistente

`whatsapp_worker.php`:

- envio de texto / midia / button / preview
- retries e classificacao de erro
- reconciliacao com webhook e provider

### 25.3 Modo de execucao

Ambos sao scripts CLI.

Nao devem ser acessados via web.

### 25.4 Polling da fila

Os workers usam um loop com lease, limite de jobs e sleep configuravel.

O `sleep-ms` nao e o mesmo conceito do delay anti-spam entre mensagens do WhatsApp.

### 25.5 Servicos de sistema

Em producao, a forma correta e gerenciar esses workers por `systemd` ou mecanismo equivalente.

Pelo historico operacional recente, o ambiente ja trabalhou com um servico de WhatsApp do tipo:

- `modatropical-whatsapp-worker.service`

O nome exato das units pode variar por ambiente, mas o ponto principal e:

- o projeto foi desenhado para rodar com workers persistentes em background

---

## 26. Debug, diagnostico e inspecao

### 26.1 Debug no admin de mensagens

`admin/mensagens.php` ja concentra bastante informacao de diagnostico.

Entre os pontos observados no fluxo recente:

- debug de clique no botao enviar
- snapshots de formulario
- snapshots de cena
- resumo de batch criado
- resumo de jobs criados
- leitura de runtime log
- leitura de cache de projeto

### 26.2 Endpoint de ingestao de debug

Arquivo:

- `admin/message_debug_ingest.php`

### 26.3 Status de batch

Arquivo:

- `admin/message_batch_status.php`

### 26.4 JS de polling do batch

Arquivo:

- `assets/js/admin-message-batch-status.js`

### 26.5 Valor pratico desses pontos

Eles permitem responder perguntas como:

- o clique realmente aconteceu?
- o draft foi salvo corretamente?
- quantos destinatarios foram resolvidos?
- batch foi criado?
- quantos jobs foram enfileirados?
- qual canal foi ativado?
- qual arquivo de imagem foi usado?
- o cache de render foi encontrado?
- qual worker consumiu o job?

---

## 27. Validacoes e comandos uteis

### 27.1 Validacao de sintaxe PHP

```bash
php -l admin/mensagens.php
php -l includes/customer_messages.php
php -l includes/admin_message_queue.php
php -l includes/admin_whatsapp_queue.php
php -l includes/whatsapp_evolution_go.php
php -l scripts/message_worker.php
php -l scripts/whatsapp_worker.php
```

### 27.2 Validacao de sintaxe JS

```bash
node --check assets/js/admin-message-editor-v2.js
node --check assets/js/message-scene-renderer.js
node --check assets/js/admin-message-batch-status.js
```

### 27.3 Renderer Node

```bash
npm run render:scene
npm run render:composicao
```

### 27.4 Procurar tabelas / updates de fila

```bash
rg "update_message_queue|update_whatsapp_queue|message_batches|whatsapp_batches" database includes scripts
```

### 27.5 Procurar referencias do provider atual

```bash
rg "whatsapp_evolution_go|WHATSAPP_EVOLUTION_GO|link_preview|whatsapp_button_enabled" admin includes scripts config
```

### 27.6 Conferir assets de preview Open Graph

```bash
dir assets\\img\\og-*.png
```

### 27.7 Conferir dependencias do renderer

```bash
type package.json
```

---

## 28. Quick start real (rodar do zero)

Esta secao e um roteiro objetivo para subir o projeto do zero.

### 28.1 Pre-requisitos

Alinhe com as versoes que a VPS usa hoje. Se estiver em outro ambiente, mantenha o mesmo range de major.

Comandos para conferir versoes:

```bash
php -v
node -v
npm -v
mysql --version
```

Requisitos funcionais minimos:

- PHP com extensoes `curl`, `gd`, `PDO`, `pdo_mysql`
- Node.js para o renderer (`puppeteer-core` + `sharp`)
- MariaDB/MySQL
- Nginx + PHP-FPM para ambiente web

### 28.2 Ordem recomendada de setup

1. Clonar o projeto para `/var/www/modatropical`
2. Criar o banco e o usuario
3. Importar o schema base e aplicar updates
4. Configurar `.env` e arquivos de `config/`
5. Garantir permissoes em `storage/` e `uploads/`
6. Configurar Nginx + PHP-FPM
7. Instalar dependencias Node para o renderer
8. Subir os workers (message e WhatsApp)

### 28.3 Banco de dados

Comandos tipicos:

```bash
mysql -u root -p
CREATE DATABASE cardapio_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Aplicar schema base e updates:

```bash
mysql -u root -p cardapio_digital < database/cardapio_digital.sql
mysql -u root -p cardapio_digital < database/update_message_queue.sql
mysql -u root -p cardapio_digital < database/update_whatsapp_queue.sql
mysql -u root -p cardapio_digital < database/update_whatsapp_queue_reconciliation.sql
```

### 28.4 `.env` e configs

O bootstrap ja le `.env`, mas ainda existem arquivos de configuracao em `config/`.

Recomendacao pratica:

- colocar secrets sensiveis no `.env`
- manter `config/*.php` com valores basicos que leem do ambiente quando disponivel
- evitar commitar secrets reais no git

### 28.5 Permissoes de runtime

Diretorios que precisam de escrita no servidor:

- `storage/`
- `storage/logs/`
- `storage/mail/`
- `storage/messages/`
- `uploads/`
- `uploads/store/`
- `uploads/messages/`

### 28.6 Nginx + PHP-FPM

Configurar vhost com:

- root em `/var/www/modatropical`
- `index index.php index.html`
- `location /` com `try_files $uri $uri/ @extensionless_php`
- `location @extensionless_php` com `rewrite ^/(.*?)/?$ /$1.php last`
- PHP via socket do PHP-FPM

Exemplo seguro:

```nginx
root /var/www/modatropical;
index index.php index.html index.htm;

location / {
    try_files $uri $uri/ @extensionless_php;
}

location @extensionless_php {
    rewrite ^/(.*?)/?$ /$1.php last;
}

location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

Nao usar `try_files $uri $uri/ $uri.php ...`, porque isso pode servir o arquivo `.php` como estatico em vez de executar pelo PHP-FPM.

### 28.7 Dependencias do renderer

```bash
npm install
npm run render:scene
npm run render:composicao
```

### 28.8 Subindo os workers

Exemplo manual:

```bash
php scripts/message_worker.php --once --limit=5 --worker-id=manual-message
php scripts/whatsapp_worker.php --once --limit=5 --worker-id=manual-whatsapp
```

Em producao, a forma correta e systemd (exemplo abaixo).

---

## 29. Deploy, update e rollback

### 29.1 Fluxo seguro de deploy

1. Fazer backup do banco
2. Fazer backup do codigo atual (ou garantir rollback via git)
3. Atualizar codigo
4. Aplicar migrations SQL novas
5. Limpar caches apenas se necessario
6. Reiniciar PHP-FPM e workers
7. Validar health do site e filas

### 29.2 Backup do banco

```bash
mysqldump -u root -p cardapio_digital > /var/backups/cardapio_digital_$(date +%F).sql
```

### 29.3 Ordem segura de update

- atualizar codigo
- aplicar SQL
- reiniciar PHP-FPM
- reiniciar workers
- validar batch/queue

### 29.4 Rollback basico

Se algo falhar:

- voltar codigo para tag/commit anterior
- se migrations foram aplicadas, avaliar rollback manual do schema
- reiniciar PHP-FPM e workers
- validar novamente

---

## 30. Runbook de incidentes

### 30.1 Email parou de enviar

Checklist:

- `systemctl status` do worker de mensagens
- logs do worker (stdout/stderr)
- `storage/logs` e `message_send_log`
- `MAIL_*` em `config/mail.php` e `.env`
- tabela `message_batch_jobs` travada em `processing`?

### 30.2 WhatsApp conectado, mas jobs nao andam

Checklist:

- worker de WhatsApp ativo?
- fila `whatsapp_batch_jobs` com `pending`?
- `config/whatsapp.php` com base URL e API key corretas?
- em producao, confirmar que o app aponta para `127.0.0.1:8090` e nao para `127.0.0.1:8080`
- logs em `storage/logs/whatsapp/*.ndjson`
- webhook respondendo `200`?

### 30.3 Preview nao aparece

Checklist:

- modo `link_preview` ativado?
- URL correta no CTA?
- pagina alvo com `og:image`, `og:title`, `og:description`?
- WhatsApp pode estar com cache; teste com `?preview=2`

### 30.4 Render falhando

Checklist:

- `node -v` e `npm -v`
- `npm install` rodou?
- caminho do Chromium/Puppeteer valido?
- `storage/messages/render-tmp` com permissao de escrita?

### 30.5 Batch preso em processing

Checklist:

- worker ainda rodando?
- `lease` expirou?
- logs do worker mostrando falha repetida?
- status do batch em `message_batches` ou `whatsapp_batches`

### 30.6 Worker rodando mas sem consumir jobs

Checklist:

- worker com `--limit` muito baixo?
- jobs em `pending` ou ja `reserved` por outro worker?
- DB acessivel? (erro de conexao)
- `message_queue_tables_ready()` ou `whatsapp_queue_tables_ready()` retornando falso?

---

## 31. Exemplos praticos de operacao

### 31.1 Exemplo de comando real do worker

```bash
php scripts/message_worker.php --once --limit=10 --lease-seconds=60 --worker-id=manual-message
php scripts/whatsapp_worker.php --once --limit=5 --lease-seconds=60 --worker-id=manual-whatsapp
```

### 31.2 Exemplo de unit systemd (modelo)

```ini
[Unit]
Description=Moda Tropical - WhatsApp Worker
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/modatropical
ExecStart=/usr/bin/php /var/www/modatropical/scripts/whatsapp_worker.php --limit=1 --sleep-ms=2000
Restart=always
RestartSec=2
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

### 31.3 Exemplo de payload de webhook

```json
{
  "event": "message.status",
  "instance": "modatropical",
  "data": {
    "key": { "id": "ABC123", "remoteJid": "5524999999999@s.whatsapp.net" },
    "status": "DELIVERED"
  }
}
```

### 31.4 Exemplo de estrutura de batch/job (WhatsApp)

```json
{
  "batch": { "public_id": "wb_20260412041226_4da12b86", "status": "queued" },
  "job": { "public_id": "wjob_20260412041226_45b0ee4a3a", "status": "pending" }
}
```

### 31.5 Exemplo de fluxo completo de envio

1. Admin salva projeto no `admin/mensagens.php`
2. Admin clica em enviar
3. Batch e jobs sao persistidos
4. Worker processa jobs
5. WhatsApp envia imagem + legenda
6. Se `link_preview`, envia URL em seguida
7. Webhook atualiza status
8. Admin visualiza progresso

---

## 32. Matriz de dependencias

| Componente | Funcao | Obrigatorio | Impacto se falhar |
|---|---|---|---|
| PHP + PHP-FPM | Servir a aplicacao web | Sim | Site fora do ar |
| MariaDB/MySQL | Dados e filas | Sim | Site e filas param |
| Node.js + Puppeteer + Sharp | Render de email | Parcial | Email com arte falha |
| SMTP | Envio de email | Parcial | Email nao sai |
| Evolution Go | WhatsApp | Parcial | WhatsApp nao sai |
| Workers CLI | Processar filas | Sim (para fila) | Batches ficam presos |
| Webhook WhatsApp | Reconciliacao | Opcional | Status menos confiavel |
| Storage/Uploads | Artefatos e logs | Sim | Erros em runtime |

---

## 33. Permissoes e paths criticos

### 33.1 Diretorios que precisam de escrita

- `storage/`
- `storage/logs/`
- `storage/mail/`
- `storage/messages/`
- `uploads/`
- `uploads/store/`
- `uploads/messages/`

### 33.2 Logs que devem existir

- `storage/logs/whatsapp/whatsapp-AAAA-MM-DD.ndjson`
- logs de workers em stdout/stderr
- logs de envio no banco (`message_send_log`, `whatsapp_send_log`)

### 33.3 Arquivos que nunca devem ir para git

- `.env` real com secrets
- qualquer dump de banco com dados reais
- logs de producao

### 33.4 Diretorios que podem ser limpos com seguranca

Com cuidado operacional, podem ser limpos:

- `storage/messages/render-tmp`
- `storage/messages/render-runtime`

Podem ser limpos, mas geram reprocessamento:

- `uploads/messages/render-cache`

Nao apagar sem avaliar impacto:

- `storage/messages/projects.json`
- `uploads/store/message-assets`
- `uploads/store/whatsapp-assets`

---

## 34. Arquivos mais sensiveis hoje

### 28.1 Core da aplicacao

- `includes/bootstrap.php`
- `includes/functions.php`
- `config/database.php`
- `config/mail.php`
- `config/whatsapp.php`

### 28.2 Loja e checkout

- `includes/storefront.php`
- `includes/orders.php`
- `carrinho.php`
- `finalizar-pedido.php`

### 28.3 Pagamentos

- `includes/asaas.php`
- `asaas-webhook.php`

### 28.4 Modulo de mensagens

- `admin/mensagens.php`
- `includes/customer_messages.php`
- `includes/customer_message_scene.php`
- `includes/customer_message_render_cache.php`
- `includes/admin_message_queue.php`
- `includes/admin_whatsapp_queue.php`
- `includes/whatsapp_queue.php`
- `includes/whatsapp_evolution_go.php`
- `scripts/message_worker.php`
- `scripts/whatsapp_worker.php`
- `templates/message-renderer.html`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`

### 28.5 Open Graph global da loja

- `includes/header.php`
- `promocoes.php`
- `assets/img/og-promocoes.png`

---

## 35. Debitos tecnicos e riscos conhecidos

### 29.1 Configuracao ainda parcialmente acoplada

O projeto ja avancou para `.env`, mas ainda nao concluiu a migracao completa de secrets.

### 29.2 Sem suite automatica robusta

Ainda nao existe uma suite automatizada equivalente ao tamanho do sistema.

Boa parte da seguranca operacional continua vindo de:

- `php -l`
- `node --check`
- testes manuais
- leitura de log
- testes reais de envio

### 29.3 Button nao-oficial no WhatsApp nao e UX garantida

O modo button existe no codigo, mas a renderizacao em clientes reais nao e confiavel.

Hoje ele deve ser tratado como:

- opcional
- experimental
- nao prioritario frente ao `link_preview`

### 29.4 Dependencia de pipeline de render externo

O modulo de mensagens depende de:

- Node
- browser local / Chromium
- Puppeteer
- Sharp

Falhas nessas camadas afetam diretamente o email renderizado.

### 29.5 Migrations manuais

O schema continua dependente de aplicacao disciplinada de arquivos SQL.

### 29.6 Cache e filas exigem operacao atenta

Como o projeto saiu do modelo simples e entrou em:

- batches
- jobs
- workers
- cache
- webhook

ele ganhou capacidade, mas tambem ganhou mais superfice operacional.

---

## 36. Regras praticas para mexer no projeto

Antes de alterar partes sensiveis, seguir estas regras:

- nao tratar backups ou forks auxiliares como fonte canonica
- nao mexer em `includes/header.php` sem entender impacto em todos os previews publicos
- nao mexer em `config/whatsapp.php` sem entender aliases de compatibilidade
- nao mexer em `includes/whatsapp_evolution_go.php` sem considerar webhook, connection status e worker
- nao mexer em `scripts/whatsapp_worker.php` sem revisar os tres modos `media`, `button` e `link_preview`
- nao mexer em `scripts/message_worker.php` sem revisar SMTP persistente e classificacao de falha
- nao mexer em `customer_message_render_cache.php` sem considerar invalidacao de cache por versao
- nao assumir que a UX do WhatsApp Web reflete exatamente a UX do destinatario final
- nao sobrescrever `assets/img/og-promocoes.png` sem alinhar com a estrategia de preview global da loja

---

## 37. Retrato final do projeto em 2026-04-12

Se alguem precisar entender rapidamente "o que e este sistema hoje", a leitura correta e:

- a loja esta no ar e com arquitetura procedural organizada por includes
- o bootstrap ja suporta `.env`, mas ainda convive com configs PHP tradicionais
- a vitrine publica e baseada em `includes/storefront.php`
- o cabecalho publico centraliza Open Graph e hoje usa `og-promocoes.png` como fallback global
- o modulo de mensagens amadureceu e agora trabalha com fila, worker, cache e SMTP persistente
- o email/notificacao em background ja esta implementado
- o WhatsApp em background ja esta implementado
- o provider atual de WhatsApp e `Evolution Go`
- o botao interativo foi preservado como opcional
- o CTA mais estavel hoje no WhatsApp e `imagem + legenda + preview de link`
- os pontos mais sensiveis do projeto continuam concentrados em `admin/mensagens.php`, no pipeline de render e nas filas

Em outras palavras:

- a parte de "como enviar em background" deixou de ser apenas plano
- a parte de "como manter UX estavel no WhatsApp" continua sendo o principal ajuste fino
- a parte de preview social da loja foi centralizada em torno da imagem de promocoes

Esse README deve ser mantido vivo sempre que houver mudancas relevantes em:

- provider de WhatsApp
- estrategia de CTA
- pipeline de render
- tabelas de fila
- estrutura de cache
- metadados Open Graph globais

---

## 38. Atualizacoes de cadastro, login, cupons e popups em 2026-04-14

As alteracoes abaixo foram adicionadas sem remover a documentacao anterior. Elas registram o comportamento novo que passou a existir no fluxo publico da loja.

### 38.1 Conta pendente de confirmacao nao deve ficar meia criada

O fluxo de cadastro passou a registrar explicitamente uma verificacao pendente em sessao, com:

- `customer_id`
- `email`
- `context`

Essa estrutura fica centralizada em `includes/auth.php` por meio de:

- `pending_customer_verification()`
- `set_pending_customer_verification()`
- `clear_pending_customer_verification()`
- `pending_customer_verification_is_signup()`

Quando a conta foi criada, mas ainda esta aguardando confirmacao, o sistema agora trata esse estado como uma conta pendente, nao como conta definitiva.

Se a pessoa voltar no fluxo de cadastro, o sistema apaga a conta incompleta imediatamente.

Os pontos principais desse comportamento sao:

- `cadastro.php`
  - ao abrir o cadastro novamente em `GET`, se houver uma verificacao pendente de `signup`, o sistema chama `abandon_pending_customer_signup()`
- `criando-conta.php`
  - a tela de confirmacao registra saida por links proprios e por retorno do navegador
  - quando o contexto e `signup`, a saida dispara uma requisicao de abandono para excluir a conta pendente

O helper que faz a limpeza e:

- `abandon_pending_customer_signup(int $customerId, string $email): bool`

Esse helper so remove a conta quando:

- o `customer_id` bate
- o email bate
- o email ainda nao foi confirmado
- a conta nao tem pedido

Se qualquer uma dessas condicoes falhar, a conta nao e removida.

### 38.2 Exclusao tecnica da conta pendente

Para a limpeza da conta pendente, `delete_customer_account()` passou a remover tambem os registros relacionados mais comuns do cliente.

Hoje a limpeza cobre:

- `cliente_remember_tokens`
- `cliente_password_resets`
- `cliente_email_alteracoes`
- `cliente_email_verificacoes`
- `cliente_enderecos`
- `cliente_cupons`
- `cliente_notificacoes`
- `cliente_favoritos`
- `cliente_identities`

Depois disso, a linha principal em `clientes` tambem e excluida.

Esse cleanup nao remove o historico antifraude por CPF, porque esse historico deve sobreviver justamente para impedir recriacao abusiva de conta so para recuperar cupom de boas-vindas.

### 38.3 Historico de CPF para antifraude de cupons de boas-vindas

Foi adicionado um historico dedicado para CPF:

- tabela: `cliente_cpf_historico`

Essa tabela e criada automaticamente pelo codigo, se ainda nao existir, por:

- `ensure_customer_cpf_history_schema()`

O historico guarda:

- CPF normalizado
- primeiro cliente relacionado
- ultimo cliente relacionado
- primeira data de cadastro
- ultima data de cadastro
- data em que os cupons de boas-vindas foram concedidos
- cliente que recebeu os cupons

Helpers centrais:

- `register_customer_cpf_history(int $customerId, string $cpf): void`
- `customer_cpf_received_welcome_coupons(string $cpf): bool`
- `mark_customer_cpf_welcome_coupons(int $customerId, string $cpf): void`

### 38.4 Regra nova dos cupons de boas-vindas

Os cupons de boas-vindas agora obedecem a tres barreiras ao mesmo tempo:

1. a conta precisa ter um CPF valido salvo
2. a conta nao pode ter pedido anterior
3. o CPF nao pode ter recebido cupons de boas-vindas antes

Essa logica passou a ficar concentrada em:

- `grant_customer_welcome_coupons(int $customerId): bool`

O comportamento atual e:

- cadastro tradicional:
  - a conta e criada sem conceder cupom imediatamente
  - o cupom so entra na carteira quando o cadastro e confirmado
- cadastro social:
  - a conta pode nascer sem CPF
  - os cupons so entram depois que o perfil e completado com CPF valido

Com isso, o fluxo impede o abuso classico:

- criar conta
- receber cupom
- apagar conta
- recriar conta com o mesmo CPF

Se o CPF ja recebeu os cupons em algum momento, a nova conta nao ganha novamente.

### 38.5 Cupons vao direto para a carteira e continuam exclusivos entre si

Os cupons de boas-vindas continuam indo direto para a carteira do cliente, sem etapa manual de resgate.

Eles continuam agrupados por exclusividade usando:

- `grupo_exclusivo`
- `primeira_compra_apenas`

Quando um cupom do grupo e usado, o outro e bloqueado automaticamente por:

- `coupon_mark_wallet_coupon_as_used(int $customerId, int $couponId): void`

Esse bloqueio aparece para o cliente na area de cupons e continua valendo so para primeira compra.

### 38.6 Login por email, CPF ou celular

O login tradicional da area do cliente deixou de aceitar apenas email.

Agora o campo principal do login permite:

- email
- CPF
- celular

O campo visual em `entrar.php` foi atualizado para:

- label: `Email, CPF ou celular`
- `input type="text"`

A resolucao do identificador agora fica em:

- `find_customer_by_login_identifier(string $identifier): ?array`

Essa funcao tenta resolver, nessa ordem pratica:

- email valido
- telefone quando o valor tem cara de telefone
- CPF quando o valor tem cara de CPF
- fallback por digitos para telefone e CPF

Isso foi conectado ao login principal por:

- `attempt_customer_login_result()`
- `attempt_customer_login()`

Os logins por rede social continuam existindo pelos botoes proprios.

### 38.7 Confirmacao de cadastro por email e WhatsApp usando o mesmo codigo e o mesmo link

O fluxo de criacao de conta agora envia confirmacao nos dois canais:

- email
- WhatsApp

O envio continua centralizado em:

- `request_customer_email_verification()`

Mas agora esse fluxo tambem aciona:

- envio do email visual de confirmacao
- envio do WhatsApp com imagem
- mesmo codigo de 6 digitos
- mesmo link de confirmacao

O asset do WhatsApp usado para confirmacao de cadastro e:

- `assets/img/confirmacaodeconta.png`

O texto da mensagem de WhatsApp de cadastro foi alinhado para:

- saudacao personalizada
- codigo de confirmacao da criacao da conta
- link de confirmacao automatica

### 38.8 Novo nome da tela publica de confirmacao

O arquivo antigo `verificar-email.php` deixou de ser o endpoint canonico do fluxo publico.

O fluxo principal agora esta em:

- `criando-conta.php`

Esse arquivo concentra:

- confirmacao por codigo
- confirmacao por token
- reenvio com cooldown
- exibicao de email e WhatsApp do cadastro
- logica de abandono da conta pendente

### 38.9 Cooldown de reenvio no fluxo de criacao de conta

O reenvio do codigo de confirmacao no cadastro passou a usar o mesmo padrao de cooldown visual ja usado em outros fluxos da loja.

Hoje:

- cooldown padrao: `60 segundos`
- o botao mostra contador no proprio texto
- o servidor tambem valida o bloqueio
- o frontend espelha isso em `localStorage` para manter o contador durante a sessao da pagina

Helpers relevantes:

- `customer_email_verification_mark_resend()`
- `customer_email_verification_resend_remaining()`
- `customer_email_verification_resend_storage_key()`

### 38.10 Popup global novo para carregamento e mensagens no frontend publico

O frontend publico passou a usar um popup global unico para:

- carregamento
- mensagens curtas de sucesso
- mensagens curtas de erro

Arquivos centrais:

- `includes/header.php`
- `includes/footer.php`
- `assets/css/public.css`
- `assets/js/app.js`

Comportamento atual:

- o carregamento inicial usa um card quadrado com loader circular e o texto `carregando`
- as mensagens usam o mesmo popup, mas sem o circulo
- o fundo fica desfocado enquanto o popup estiver visivel
- os antigos flashes visuais do frontend publico deixaram de aparecer em pilha na tela
- os flashes do servidor continuam existindo como fonte de dados oculta e agora sao lidos pelo JavaScript e transformados em popup global

Isso tambem foi ligado aos carregamentos por:

- navegacao normal por links internos
- submit de formularios
- instant navigation da vitrine

Com isso, o frontend publico passa a ter um padrao mais coerente para feedback visual.

### 38.11 Conclusao de cadastro social e momento correto de entregar cupons

O fluxo de completar cadastro de contas sociais foi ajustado para registrar o CPF no historico e tentar conceder os cupons no momento correto.

Arquivo relevante:

- `completar-cadastro.php`

Hoje, ao concluir os dados principais com CPF valido:

- o CPF entra no historico antifraude
- o sistema tenta conceder os cupons de boas-vindas
- se os cupons forem concedidos, a mensagem de boas-vindas e disparada

Isso evita tanto a perda indevida do beneficio quanto o abuso por recriacao de conta.

---

## 39. Onde estamos / paramos

### 39.1 Última intervenção técnica registrada

Data:

- `2026-04-17`

Contexto:

- correção da configuração pública do Nginx na VPS de produção
- alinhamento da documentação do roteamento sem extensão `.php`
- criação do arquivo canônico `config/nginx/public-router.conf` no projeto

Problema que existia:

- URLs amigáveis como `/promocoes`, `/categoria?slug=...` e `/produto?slug=...` não estavam sendo roteadas corretamente
- em parte dos acessos, a URL mudava, mas a resposta renderizada continuava sendo a home
- em `/promocoes`, a configuração incorreta chegou a servir o conteúdo bruto de `promocoes.php` em vez de executar via PHP-FPM

Correção aplicada:

- ajuste do vhost ativo em `/etc/nginx/sites-available/modatropical.store`
- uso de `location /` com `try_files $uri $uri/ @extensionless_php`
- uso de `location @extensionless_php` com `rewrite ^/(.*?)/?$ /$1.php last`
- manutenção da execução PHP apenas em `location ~ \.php$`
- validação com `nginx -t`
- reload do serviço `nginx`

Arquivos envolvidos:

- `README.md`
- `config/nginx/public-router.conf`
- `/etc/nginx/sites-available/modatropical.store`
- `/var/www/modatropical/config/nginx/public-router.conf`

Validação feita após a correção:

- `https://modatropical.store/promocoes` respondeu HTML da página de promoções
- `https://modatropical.store/categoria?slug=blusinhas` respondeu HTML da categoria correta
- `https://modatropical.store/produto?slug=bata-de-linho-azul-netuno` respondeu HTML do produto correto

Ponto exato onde paramos:

- Nginx de produção corrigido e recarregado
- README atualizado com a regra obrigatória de documentação
- roteamento público sem extensão funcionando novamente
- próximo passo natural, se necessário, é revisar logs para medir o alcance do período em que `/promocoes` ficou exposto como código-fonte bruto
### 39.2 Intervencao seguinte registrada no mesmo dia

Data:

- `2026-04-17`

Contexto:

- diagnostico do QR do WhatsApp que nao aparecia no admin
- diagnostico do botao `Abrir` em projetos salvos que podia recarregar a pagina na aba errada
- sincronizacao do codigo local com a VPS para alinhar comportamento de admin e integracao Evolution Go

Problemas encontrados:

- o app PHP estava sem `.env` na raiz, entao `WHATSAPP_EVOLUTION_GO_API_KEY` chegava vazia e o admin marcava a integracao como desabilitada
- o provider do projeto esta em `127.0.0.1:8090`, mas o fallback padrao do app apontava para `127.0.0.1:8080`
- existe outro processo Node ouvindo `127.0.0.1:8080`, entao a URL errada podia atingir um servico diferente
- a funcao `whatsapp_evolution_connection_payload(false)` podia receber `Connected=true` sem `jid` e encerrar o fluxo sem pedir um QR novo
- ao abrir um projeto salvo, a ultima aba gravada em `sessionStorage` podia reabrir `whatsapp` e esconder o editor de `email`

Correcao aplicada:

- criacao de `/var/www/modatropical/.env` com as variaveis do Evolution Go em producao
- base configurada para `http://127.0.0.1:8090`
- API key global configurada para a instancia `modatropical_evolution_go`
- ajuste em `includes/whatsapp_evolution_go.php` e `admin/includes/whatsapp_evolution_go.php` para tratar `Connected=true` sem `jid` como estado desconectado e buscar QR automaticamente
- ajuste em `admin/mensagens.php` e `admin/admin/mensagens.php` para abrir projeto salvo com o `channel` da aba ativa
- ajuste no script do seletor de abas para respeitar `project` ou `channel` da URL antes de restaurar a aba anterior da sessao

Arquivos envolvidos:

- `README.md`
- `admin/admin/mensagens.php`
- `includes/whatsapp_evolution_go.php`
- `admin/includes/whatsapp_evolution_go.php`
- `/var/www/modatropical/admin/mensagens.php`
- `/var/www/modatropical/admin/admin/mensagens.php`
- `/var/www/modatropical/includes/whatsapp_evolution_go.php`
- `/var/www/modatropical/admin/includes/whatsapp_evolution_go.php`
- `/var/www/modatropical/.env`

Validacao feita apos a correcao:

- `whatsapp_evolution_connection_payload(false)` passou a retornar `enabled=1`, `configured=1` e `qr_len > 0`
- o HTML renderizado do admin passou a sair com QR embutido quando a instancia esta desconectada
- o card de projeto salvo passou a gerar `href` com `project=<id>` e `channel` coerente com a aba ativa
- o admin renderizado com `?project=teste-20260407114634` passou a sair com `data-default-tab="email"`

Ponto exato onde paramos agora:

- QR do WhatsApp operacional novamente no admin, com configuracao real da VPS documentada
- abertura de projeto salvo respeita a aba pedida na URL e nao fica mais refem da ultima aba da sessao
- backups dos arquivos alterados existem na VPS com o sufixo `.20260417-1454-before-whatsapp-project-fix`
- proximo passo natural, se necessario, e revisar a UX final no navegador com login real de admin e confirmar se a instancia sera conectada por QR ou por pareamento

### 39.3 Ajuste de contexto por canal no mesmo projeto

Data:

- `2026-04-17`

Contexto:

- o comportamento anterior passou a forcar `email` ao abrir projeto salvo
- isso eliminava o erro de aba escondida, mas nao respeitava o uso real do admin quando o clique acontecia no contexto de `whatsapp`

Decisao aplicada:

- o projeto continua sendo unico, com o mesmo `project_id`
- email e WhatsApp continuam no mesmo payload salvo
- o canal virou contexto de navegacao e redirect, nao separacao de projeto

Correcao aplicada:

- criacao do helper `admin_message_channel_value()` em `admin/admin/mensagens.php`
- `admin_message_redirect_url()` passou a aceitar `channel`
- o formulario passou a enviar `active_channel`
- `Salvar projeto` agora retorna para o mesmo projeto na mesma aba ativa
- os links `Abrir` dos cards passaram a usar `data-open-base` e receber `channel` dinamico pela aba atual
- a troca de abas atualiza tanto o hidden `active_channel` quanto o `href` dos cards de projeto

Validacao feita:

- em `email`, o card salvo continua abrindo o mesmo projeto no painel de email
- em `whatsapp`, o mesmo card agora abre o mesmo projeto no painel de WhatsApp
- o projeto continua unico no arquivo `storage/messages/projects.json`

Ponto exato onde paramos agora:

- projeto unico mantido
- abertura e salvamento contextualizados por canal
- proximo passo natural, se necessario, e criar uma UX visual mais explicita nos cards para mostrar que o mesmo projeto pode ser aberto em `email` ou `whatsapp` sem duplicacao

### 39.4 Debug completo para clique em Abrir

Data:

- `2026-04-17`

Contexto:

- o projeto seguia unico e contextualizado por canal
- ainda faltava visibilidade total do que acontece quando o admin clica em `Abrir` e a tela do WhatsApp parece vir vazia

Correcao aplicada:

- criacao de um painel fixo no final de `admin/mensagens.php` com o titulo `Log de abertura de projeto`
- inclusao de botao `Copiar log` dedicado a esse bloco
- o clique em `Abrir` agora gera rastro client-side antes da navegacao
- o reload seguinte recompõe esse rastro e mistura com o boot atual da pagina
- o log inclui snapshot do PHP, query string, aba ativa, projeto carregado, campos hidden, resumo do editor e estado do painel de WhatsApp
- o armazenamento temporario desse fluxo usa `sessionStorage` com a chave `mt_message_project_open_debug`
- o script generico de copia de logs passou a suportar multiplos botoes `Copiar log` na mesma pagina

Arquivos envolvidos:

- `README.md`
- `admin/admin/mensagens.php`
- `/var/www/modatropical/admin/mensagens.php`
- `/var/www/modatropical/admin/admin/mensagens.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- existe um debug completo e copiavel no final da pagina de mensagens
- o clique em `Abrir` passa a deixar trilha antes e depois do reload
- o proximo passo natural e reproduzir o caso no painel de WhatsApp e analisar o log gerado para decidir se o problema e ausencia real de payload do WhatsApp, falha de bind no frontend ou sobrescrita de estado no boot

### 39.5 Abertura do WhatsApp com fallback do email

Data:

- `2026-04-17`

Contexto:

- o log de abertura confirmou que o clique em `Abrir` estava correto
- a query chegava com `project=...&channel=whatsapp`
- o `project_id` oculto e a aba ativa batiam com o projeto aberto
- o problema real era outro: o projeto salvo tinha arte e camadas de `email`, mas estava com `whatsapp_message` e `whatsapp_image_path` vazios
- isso fazia a aba de WhatsApp parecer quebrada, quando na pratica o projeto carregava certo e o payload especifico de WhatsApp e que nao existia ainda

Correcao aplicada:

- criacao do helper `admin_message_apply_whatsapp_boot_fallback()` em `admin/admin/mensagens.php`
- ao abrir um projeto com `channel=whatsapp`, se o payload proprio de WhatsApp vier vazio, o boot agora reutiliza temporariamente a base do email
- os campos reaproveitados no fallback sao:
- `whatsapp_message` a partir de `message`
- `whatsapp_image_path` a partir de `hero_image_path`
- `whatsapp_button_url` a partir de `image_link_url` ou `link_url`, quando existir
- a tela do WhatsApp passou a exibir um aviso deixando claro que o conteudo foi carregado como ponto de partida a partir do email
- o bloco `Log de abertura de projeto` agora registra `whatsapp_boot_fallback` no snapshot do servidor e tambem expõe esse estado no snapshot do cliente

Arquivos envolvidos:

- `README.md`
- `admin/admin/mensagens.php`
- `/var/www/modatropical/admin/mensagens.php`
- `/var/www/modatropical/admin/admin/mensagens.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- o fluxo `Abrir` no WhatsApp continua abrindo o projeto correto
- quando o projeto nao tiver payload proprio de WhatsApp, a tela passa a nascer com a base do email em vez de parecer vazia
- o debug passa a deixar explicito quando esse fallback foi aplicado
- proximo passo natural, se necessario, e validar no navegador se essa base temporaria atende bem ou se voce quer um comportamento ainda mais rigido, como exigir salvamento independente do canal antes de permitir envio

### 39.6 Falha ao salvar projeto por pasta de assets da loja

Data:

- `2026-04-17`

Contexto:

- ao salvar projeto no admin, a tela passou a exibir o erro `Nao foi possivel criar a biblioteca de imagens da loja.`
- a causa nao era o JSON do projeto, e sim a persistencia das imagens ao salvar
- o codigo atual de `admin/admin/mensagens.php` usa os diretorios:
- `uploads/store/message-assets`
- `uploads/store/whatsapp-assets`
- na VPS existia apenas a arvore antiga em `uploads/messages/...`
- `uploads/store/` existia, mas estava em `root:root` sem os subdiretorios novos, entao o PHP-FPM (`www-data`) nao conseguia criar `message-assets` e `whatsapp-assets`

Correcao aplicada:

- criacao na VPS de:
- `/var/www/modatropical/uploads/store/message-assets`
- `/var/www/modatropical/uploads/store/whatsapp-assets`
- ownership ajustado para `www-data:www-data`
- permissao ajustada para `0775`
- validacao feita com escrita real via `www-data`, incluindo `touch` e `cp` dentro de `message-assets`

Arquivos envolvidos:

- `README.md`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- o erro de criacao da biblioteca de imagens da loja foi corrigido na VPS
- o fluxo de salvar projeto voltou a ter permissao para persistir assets nos diretorios esperados pelo codigo atual
- se ainda houver falha ao salvar depois disso, o proximo passo e capturar a nova mensagem exata porque o bloqueio anterior de filesystem ja foi eliminado

### 39.7 Falha ao mover a imagem enviada no upload inicial

Data:

- `2026-04-17`

Contexto:

- depois da correcao de `uploads/store/...`, o admin passou a falhar com `Falha ao mover a imagem enviada.`
- a causa era outro ponto do fluxo
- o upload inicial do form nao grava primeiro em `uploads/store/...`
- ele grava em:
- `uploads/messages`
- `uploads/messages/whatsapp-assets`
- so depois, no salvamento do projeto, a imagem pode ser persistida para os diretorios canonicos de projeto
- na VPS, `uploads/messages` estava em `root:root` e `uploads/messages/whatsapp-assets` nem existia
- por isso `move_uploaded_file()` falhava no primeiro passo do upload

Correcao aplicada:

- na VPS, criacao de:
- `/var/www/modatropical/uploads/messages/whatsapp-assets`
- `/var/www/modatropical/admin/uploads/messages/whatsapp-assets`
- ownership ajustado para `www-data:www-data` em:
- `/var/www/modatropical/uploads/messages`
- `/var/www/modatropical/uploads/messages/whatsapp-assets`
- `/var/www/modatropical/admin/uploads/messages`
- `/var/www/modatropical/admin/uploads/messages/whatsapp-assets`
- permissao ajustada para `0775`
- validacao feita com escrita real via `www-data` nas quatro pastas
- ajuste de codigo em `includes/functions.php` e `admin/includes/functions.php` para:
- criar tambem os diretorios `uploads/messages/...` e `uploads/store/...` no bootstrap
- falhar com mensagem mais precisa caso a pasta de upload nao possa ser criada ou escrita

Arquivos envolvidos:

- `README.md`
- `includes/functions.php`
- `admin/includes/functions.php`
- `/var/www/modatropical/includes/functions.php`
- `/var/www/modatropical/admin/includes/functions.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- o fluxo de upload inicial das imagens do modulo de mensagens voltou a ter diretivos e permissoes coerentes com o codigo
- o helper de upload agora protege melhor contra erro silencioso de `mkdir`
- se o admin ainda falhar ao salvar imagem depois disso, a nova mensagem deve apontar com mais precisao se o problema e tmp, mime ou gravacao

### 39.8 Alinhamento da VPS com a arquitetura canonica do README

Data:

- `2026-04-17`

Contexto:

- a auditoria da VPS mostrou divergencia entre a arquitetura descrita neste README e o runtime real do admin
- este README trata como canonica a arvore da raiz:
- `storage/messages/projects.json`
- `storage/messages/*`
- `uploads/messages/*`
- `uploads/store/*`
- na VPS, parte do admin ainda estava operando com uma arvore espelhada em `admin/storage` e `admin/uploads`
- isso abria espaco para divergencia de runtime, permissao, arquivo salvo em lugar errado e diagnostico inconsistente

Correcao aplicada na VPS:

- `admin/storage` passou a apontar para `../storage`
- `admin/uploads` passou a apontar para `../uploads`
- a arvore canonica da raiz foi normalizada para escrita por `www-data:www-data` em:
- `/var/www/modatropical/storage`
- `/var/www/modatropical/storage/messages`
- `/var/www/modatropical/uploads`
- `/var/www/modatropical/uploads/messages`
- `/var/www/modatropical/uploads/store`
- `projects.json` canonico em `/var/www/modatropical/storage/messages/projects.json` ficou gravavel e acessivel tambem pelo alias do admin
- foi validado round-trip de escrita em `projects.json` via caminho do admin
- foi validada copia de arte para `uploads/store/message-assets` via caminho do admin
- foi validada execucao real de `admin_message_project_store()` na VPS com restauracao imediata do JSON original

Resultado pratico:

- o runtime do modulo de mensagens agora converge para a arvore descrita neste README
- `admin/mensagens.php` e o alias interno do admin deixam de trabalhar com arvores separadas de `storage` e `uploads`
- o risco de salvar projeto em um lugar e ler de outro ficou removido nesse eixo

Arquivos envolvidos:

- `README.md`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- a VPS foi alinhada com a arquitetura canonica descrita neste README para `storage/` e `uploads/`
- o modulo de mensagens passou a usar um runtime unificado e gravavel
- a validacao tecnica de escrita em `projects.json`, de copia de assets e do helper real de salvar projeto passou na VPS
- se ainda houver erro funcional no `Salvar projeto`, o proximo passo ja nao e mais estrutura da VPS e sim regra de negocio ou excecao especifica do fluxo

### 39.9 Auditoria funcional completa da VPS contra o README

Data:

- `2026-04-17`

Contexto:

- foi executada uma auditoria completa da VPS com foco apenas nos arquivos e componentes necessarios para o site funcionar
- o objetivo foi alinhar a VPS ao que este README define como arquitetura canonica, sem considerar imagens de exemplo ou diferencas cosmeticas
- a auditoria cobriu bootstrap, `.env`, `config/`, `storage/`, `uploads/`, workers, renderer Node, webhook publico, endpoints administrativos criticos e Nginx

Correcao aplicada na VPS:

- `admin/.env` passou a apontar para `../.env`
- `admin/config` passou a apontar para `../config`
- `APP_URL` na raiz da VPS foi normalizado para `APP_URL=/`, para evitar resolucao incorreta de base URL no bootstrap
- foi confirmada a presenca dos arquivos criticos do runtime, incluindo:
- `includes/bootstrap.php`
- `admin/includes/bootstrap.php`
- `admin/mensagens.php`
- `admin/admin/mensagens.php`
- `includes/functions.php`
- `admin/includes/functions.php`
- `includes/customer_messages.php`
- `includes/admin_message_queue.php`
- `includes/admin_whatsapp_queue.php`
- `includes/whatsapp_evolution_go.php`
- `scripts/message_worker.php`
- `scripts/whatsapp_worker.php`
- `scripts/render_message_scene.mjs`
- `scripts/render_composicao.js`
- `templates/message-renderer.html`
- `admin/message_batch_status.php`
- `admin/message_debug_ingest.php`
- `admin/whatsapp_connection.php`
- `whatsapp-webhook.php`
- `config/nginx/public-router.conf`
- foram validadas as permissoes e a gravacao nos diretorios criticos:
- `storage/messages`
- `storage/messages/render-cache`
- `storage/messages/render-runtime`
- `storage/messages/render-tmp`
- `storage/logs/whatsapp`
- `uploads/messages`
- `uploads/messages/render-cache`
- `uploads/store`
- `uploads/store/message-assets`
- `uploads/store/whatsapp-assets`
- foi validada a sintaxe de PHP e JS dos arquivos mais sensiveis do site
- foi validado `nginx -t`
- foram confirmados ativos:
- `nginx`
- `php8.3-fpm`
- `mariadb`
- `modatropical-whatsapp-worker.service`
- `modatropical-message-worker@1.service`
- `modatropical-message-worker@2.service`
- foi validado acesso ao banco e readiness das filas de email e WhatsApp
- foi validado que `whatsapp-webhook.php` responde com `405 Method Not Allowed` para `GET`, como esperado
- foi validado que os endpoints administrativos criticos respondem com `302` sem auth, como esperado

Correcao de renderer aplicada:

- a VPS tinha `node_modules`, `puppeteer-core` e `sharp`, mas nao tinha navegador Chromium/Chrome executavel em nenhum dos caminhos suportados pelo projeto
- isso deixava o pipeline de render montado pela metade e contrariava o requisito funcional descrito neste README
- foi instalado um browser local via `@puppeteer/browsers` em:
- `/var/www/modatropical/.cache/puppeteer/chrome/linux-147.0.7727.57/chrome-linux64/chrome`
- foi criado o alias canonico:
- `/usr/local/bin/modatropical-chromium`
- foi validado render real com `scripts/render_message_scene.mjs`, gerando um PNG `1080x1620` na VPS

Resultado pratico:

- a VPS agora bate com este README nos pontos de runtime realmente necessarios para o site funcionar
- o admin e a raiz compartilham o mesmo `.env`, o mesmo `config/`, o mesmo `storage/` e o mesmo `uploads/`
- o bootstrap raiz e o bootstrap do admin resolvem `APP_URL` de forma coerente
- os workers e o renderer estao montados com dependencias efetivamente operacionais

Arquivos envolvidos:

- `README.md`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- a auditoria funcional da VPS contra este README foi concluida
- a divergencia estrutural restante mais critica era a ausencia de navegador local para o renderer, e isso foi corrigido
- os componentes essenciais do site passaram por validacao de existencia, permissao, sintaxe e operacao basica
- o proximo passo, se ainda houver erro funcional no admin, ja nao e mais arquitetura base da VPS e sim um fluxo especifico da aplicacao

### 39.10 Hotspot do email com link clicavel apenas na area demarcada

Data:

- `2026-04-17`

Contexto:

- o editor de mensagens permitia desenhar um hotspot invisivel sobre o botao da arte do email
- o projeto estava persistindo corretamente o `hotspot` no payload e o renderer de composicao devolvia as coordenadas da area clicavel
- o problema estava na saida HTML final do email
- ate esta correcao, o email tentava aplicar o clique com um `<a>` absoluto sobre a imagem renderizada
- isso e pouco confiavel em clientes de email e fazia o botao visual nao redirecionar mesmo com o hotspot salvo corretamente

Correcao aplicada:

- o pipeline de email deixou de depender de overlay absoluto para hotspots na arte renderizada
- quando a arte final vem com hotspots configurados, o sistema agora:
- gera uma grade a partir dos limites do hotspot
- recorta a arte final em slices reais de imagem
- publica os slices em `uploads/messages/render-cache/hotspot-slices`
- monta uma tabela HTML de email com as fatias
- coloca `<a href>` apenas nas fatias que pertencem ao hotspot
- o restante da arte continua visualmente igual, mas sem clique
- o fallback de `full_image_link` continua existindo apenas quando nao ha hotspot configurado
- quando existe hotspot configurado e os slices nao puderem ser gerados, o sistema nao transforma a imagem inteira em link

Arquivos envolvidos:

- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`
- `/var/www/modatropical/includes/customer_messages.php`
- `/var/www/modatropical/admin/includes/customer_messages.php`
- `/var/www/modatropical/README.md`

Validacao feita na VPS:

- `php -l` passou em `includes/customer_messages.php` e `admin/includes/customer_messages.php`
- teste direto da funcao de slices retornou:
- HTML valido
- `has_absolute_overlay=false`
- `anchor_count=1`
- `img_count=9`
- `status=success`
- o diretorio publico passou a conter arquivos em:
- `/var/www/modatropical/uploads/messages/render-cache/hotspot-slices`
- teste do `customer_message_build_editor_email_html()` confirmou:
- o HTML final nao contem `position:absolute`
- o HTML final referencia `render-cache/hotspot-slices`
- o fluxo nao caiu em `full_image_link` quando havia hotspot configurado

Ponto exato onde paramos agora:

- o email continua com apenas a area demarcada clicavel
- o hotspot do botao deixou de depender de tecnica fraca para cliente de email
- a saida final passou a ser compatibilizada por slices de imagem, preservando o visual da arte inteira
- se houver algum caso restante, o proximo passo e validar em cliente real especifico como Gmail, Outlook ou Apple Mail para medir compatibilidade por cliente

### 39.11 Hotspot do email ainda falhando por worker antigo carregado em memoria

Data:

- `2026-04-17`

Contexto:

- depois da correcao do hotspot por slices, o usuario ainda reportou que o botao do email nao redirecionava no celular
- a investigacao do envio real mostrou que o ultimo email entregue ainda nao carregava nenhum `hotspot_link_mode` nem `hotspot_slice_debug` no `render_trace`
- isso indicou que o envio real nao estava usando o codigo novo, apesar de os arquivos ja estarem atualizados na VPS
- a causa era operacional:
- os envios de email passam por `modatropical-message-worker@1.service` e `modatropical-message-worker@2.service`
- esses workers sao processos PHP persistentes e mantem os includes em memoria
- portanto, subir `includes/customer_messages.php` na VPS sem reiniciar os workers nao atualiza o comportamento do envio ate o proximo restart da unit

Correcao aplicada:

- reinicio de:
- `modatropical-message-worker@1.service`
- `modatropical-message-worker@2.service`
- ajuste adicional em `includes/customer_messages.php` e `admin/includes/customer_messages.php` para gravar de volta o `render_trace` final apos definir:
- `hotspot_link_mode`
- `hotspot_slice_debug`
- com isso, o diagnostico dos proximos envios passa a refletir o resultado real do HTML final do email

Validacao feita:

- o ultimo evento historico do banco ainda aparecia sem:
- `render_trace.hotspot_link_mode`
- `render_trace.hotspot_slice_debug`
- isso confirmou que o email problemático foi processado antes do restart
- depois do restart dos workers, a reconstrução do ultimo job com o codigo atual retornou:
- `contains_absolute_overlay=false`
- `contains_hotspot_slices=true`
- `contains_full_image_link=false`
- `hotspot_link_mode=sliced_hotspot_only`
- `hotspot_slice_status=success`
- `hotspot_slice_count=9`
- os novos `MainPID` dos workers confirmaram recarga de processo apos o restart

Regra operacional reforcada:

- qualquer mudanca em codigo usado por worker CLI exige restart explicito do worker correspondente na VPS
- no caso do modulo de mensagens, isso vale pelo menos para:
- `modatropical-message-worker@*.service`
- `modatropical-whatsapp-worker.service`

Arquivos envolvidos:

- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`
- `/var/www/modatropical/includes/customer_messages.php`
- `/var/www/modatropical/admin/includes/customer_messages.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- os workers de email foram reiniciados e passaram a carregar a versao atual do hotspot por slices
- se um cliente ainda falhar, a proxima verificacao precisa separar problema de HTML final, de cliente de email e de runtime da VPS

### 39.12 Hotspot por slices falhando por permissao de cache e condicao nula no HTML

Data:

- `2026-04-17`

Contexto:

- apos reativar o hotspot por slices, um novo teste de email exibiu apenas o rodape de descadastro e nao mostrou a arte principal
- a investigacao do ultimo envio real (`job_id=193`) mostrou:
- `render_trace.hotspot_slice_debug.status=failed`
- `render_trace.hotspot_slice_debug.reason=cache_dir_unavailable`
- a pasta `/var/www/modatropical/uploads/messages/render-cache/hotspot-slices` estava com `owner=root:root` e permissao `755`, enquanto o worker roda como `www-data`
- alem disso, o PHP considerava `null` como se fosse um HTML de hotspot valido porque testava apenas `!== ''`
- quando o slicer falhava e retornava `null`, a arte principal era substituida por valor nulo e o email ficava praticamente vazio, restando apenas o rodape automatico

Correcao aplicada:

- a condicao que aceita o HTML de slices passou a exigir `is_string($hotspotsHtml) && $hotspotsHtml !== ''`
- com isso, falha de slicer nao apaga mais a arte principal do email
- na falha, o fluxo preserva a imagem renderizada normal e segue o ramo `configured_but_slice_missing`
- na VPS, a pasta `uploads/messages/render-cache/hotspot-slices` foi corrigida para `www-data:www-data` com permissao gravavel para o worker
- os workers de email foram reiniciados apos o ajuste de infraestrutura

Arquivos envolvidos:

- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`
- `/var/www/modatropical/includes/customer_messages.php`
- `/var/www/modatropical/admin/includes/customer_messages.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- a arte principal do email nao deve mais desaparecer quando a geracao dos slices falhar
- o cache publico de `hotspot-slices` na VPS voltou a ficar gravavel para `www-data`
- o proximo teste precisa confirmar duas coisas ao mesmo tempo:
- a arte principal voltou a aparecer
- a area demarcada do botao passou a ser clicavel

### 39.13 Hotspot por slices com merge horizontal para preservar layout no cliente de email

Data:

- `2026-04-17`

Contexto:

- depois da correcao de permissao e fallback nulo, o clique passou a funcionar, mas o layout visual ainda ficou quebrado em cliente real
- a arte completa estava sendo fatiada por toda a grade gerada pelos limites do hotspot, o que produzia `3x3` slices mesmo quando apenas a faixa do botao precisava ser dividida
- na pratica, isso criava colunas altas e estreitas no email, deixando o HTML mais fragil em clientes como Gmail

Correcao aplicada:

- o algoritmo de slices passou a fazer `merge` horizontal de segmentos contiguos com o mesmo `href` dentro de cada linha
- com um hotspot central unico, o resultado deixa de ser `3x3` e passa a ser estruturalmente:
- linha superior inteira em uma unica imagem
- linha do hotspot dividida em esquerda, centro clicavel e direita
- linha inferior inteira em uma unica imagem
- isso reduz a quantidade de imagens, preserva melhor a proporcao do layout e diminui o risco de quebra visual no cliente de email

Arquivos envolvidos:

- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`
- `/var/www/modatropical/includes/customer_messages.php`
- `/var/www/modatropical/admin/includes/customer_messages.php`
- `/var/www/modatropical/README.md`

Ponto exato onde paramos agora:

- o hotspot continua clicando apenas na area demarcada
- a reconstrucao da arte no email passou a usar menos slices e uma estrutura mais estavel
- o proximo teste deve confirmar se o visual voltou a respeitar o tamanho e a composicao do projeto salvo

- o ultimo email que falhou no celular provavelmente foi enviado por worker antigo, antes do restart
- os workers de mensagem ja foram reiniciados e agora estao com o codigo novo em memoria
- a reconstrução do job com o runtime atual confirmou slices clicaveis e ausencia de overlay absoluto
- o proximo passo correto e enviar um novo email de teste e validar novamente no celular

### 39.14 CTA real do email passa a ser controlado pela camada `button` do editor

Data:

- `2026-04-17`

Contexto:

- a estrategia de hotspot invisivel sobre a arte ficou estruturalmente fragil no Gmail
- a decisao canonica do projeto passou a ser: quando houver camada `button` valida no scene do editor, o email deve usar um CTA HTML real
- o usuario precisava controlar esse CTA pelo proprio editor, sem perder:
- movimentacao da camada no canvas
- alteracao do texto
- alteracao da URL
- alteracao do tamanho da fonte do texto

Correcao aplicada:

- a tela `admin/admin/mensagens.php` ganhou campos visiveis para:
- `button_label`
- `link_url`
- `button_font_size`
- o entrypoint `admin/mensagens.php` foi normalizado como wrapper para `admin/admin/mensagens.php`, evitando deixar duas rotas locais divergentes
- o editor Fabric (`assets/js/admin-message-editor-v2.js`) passou a:
- manter `fontSize` da camada `button` no scene salvo
- restaurar esse `fontSize` ao reabrir projeto
- sincronizar texto, link e tamanho da fonte entre formulario e camada do botao
- criar a camada `button` automaticamente quando o CTA comeca a ser preenchido
- os renderizadores locais (`assets/js/message-scene-renderer.js`, `templates/message-renderer.html`, `scripts/render_composicao.js`) passaram a respeitar `fontSize` da camada `button`
- o builder de email (`includes/customer_messages.php` e `admin/includes/customer_messages.php`) passou a:
- detectar uma camada `button` valida no scene
- remover essa camada da arte raster usada como base do email
- reconstruir o email em modo hibrido:
- imagem superior
- faixa central com fundo recortado da arte e CTA HTML real
- imagem inferior
- manter o fallback antigo de hotspot apenas quando nao existir camada `button` valida

Arquivos envolvidos:

- `admin/admin/mensagens.php`
- `admin/mensagens.php`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `templates/message-renderer.html`
- `scripts/render_composicao.js`
- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`

Validacao local:

- `php -l includes/customer_messages.php`
- `php -l admin/includes/customer_messages.php`
- `php -l admin/admin/mensagens.php`
- `node --check assets/js/admin-message-editor-v2.js`
- `node --check assets/js/message-scene-renderer.js`
- `node --check scripts/render_composicao.js`

Ponto exato onde paramos agora:

- o botao real do email deixou de depender do hotspot invisivel quando existir camada `button`
- o controle canonico do CTA passou a ser:
- posicao e tamanho pelo canvas
- texto, link e tamanho da fonte pelo formulario e pelo scene salvo
- o proximo passo obrigatorio e testar um envio novo na VPS para validar o visual final do CTA real em cliente de email
### 39.15 Drag da camada `button` no editor deixa de ficar preso no cursor

Data:

- `2026-04-17`

Contexto:

- depois da entrada do CTA real controlado pelo editor, a camada `button` passou a ser um `fabric.Group`
- ao tentar mover o botao no canvas, o usuario via um "fantasma"/clone visual e o drag podia parecer preso no cursor
- o problema estava no acoplamento entre o `Group` do Fabric e o repaint do canvas durante/fim da transformacao

Correcao aplicada:

- o `assets/js/admin-message-editor-v2.js` passou a tratar o botao como grupo mais estavel no editor:
- `rect` e `label` internos agora nao recebem eventos diretamente
- o `Group` do botao deixou de forcar `width/height` manualmente na criacao
- o `Group` passou a usar configuracao mais segura para drag/resize (`subTargetCheck: false`, sem rotacao)
- a rotina `syncButtonGroupText()` passou a preservar a ancora visual do grupo e evitar regravar `group.width/group.height` manualmente
- o editor ganhou `forceCanvasRepaint()` e listeners de `mouse:up`, `object:moving` e `object:scaling` para limpar o estado visual do drag e repintar o canvas corretamente
- a sincronizacao de inputs do CTA com a camada do canvas agora ignora updates enquanto houver transformacao de ponteiro em andamento

Arquivos envolvidos:

- `assets/js/admin-message-editor-v2.js`
- `README.md`

Validacao local:

- `node --check assets/js/admin-message-editor-v2.js`

Ponto exato onde paramos agora:

- o CTA real do email continua configuravel por texto, link, fonte e posicao
- a camada `button` do editor recebeu ajuste especifico para nao deixar o drag preso visualmente
- o proximo passo obrigatorio e validar no navegador da VPS se o arraste do botao voltou ao comportamento normal

### 39.16 UI do hotspot invisivel sai do admin de mensagens

Data:

- `2026-04-17`

Contexto:

- depois da migracao do email para CTA HTML real, a opcao visual de `Link invisivel sobre a imagem` ficou obsoleta na tela do admin
- o usuario pediu para remover esse bloco da interface e tambem parar de oferecer o atalho `Novo link` no toolbar do editor

Correcao aplicada:

- o bloco visivel `Link invisivel sobre a imagem` foi removido de `admin/admin/mensagens.php`
- o botao `Novo link` tambem foi removido do toolbar do editor visual
- a compatibilidade interna com hotspots antigos foi preservada por enquanto, para nao quebrar projetos salvos que ainda dependam desse fallback

Arquivos envolvidos:

- `admin/admin/mensagens.php`
- `README.md`

Validacao local:

- `php -l admin/admin/mensagens.php`

Ponto exato onde paramos agora:

- a interface do admin passou a expor apenas o CTA real do email como caminho canonico
- hotspots antigos nao foram apagados do runtime, apenas deixaram de ser oferecidos na UI
- o proximo passo correto e validar na VPS se o bloco desapareceu da tela e se o editor segue funcionando sem o botao `Novo link`

### 39.17 Limpeza visual do admin de mensagens

Data:

- `2026-04-17`

Contexto:

- o usuario pediu para remover da tela do admin os blocos e textos auxiliares que estavam poluindo a interface
- isso incluiu:
- painel de debug de envio
- painel de debug de abertura de projeto
- hints operacionais do editor
- hint de upload da imagem
- hint de status do SMTP
- barra de status do editor
- botoes secundarios `Editar texto`, `Duplicar` e `Excluir`
- labels duplicadas de `Editor visual do email` e `Editor visual`

Correcao aplicada:

- a UI de `admin/admin/mensagens.php` foi enxugada para manter apenas os controles principais do fluxo
- o canvas do editor continua no mesmo lugar, mas sem os hints e sem a barra de status
- o debug continua existindo no runtime para suporte tecnico, mas deixou de ser renderizado na interface

Arquivos envolvidos:

- `admin/admin/mensagens.php`
- `README.md`

Validacao local:

- `php -l admin/admin/mensagens.php`

Ponto exato onde paramos agora:

- a tela do admin ficou mais limpa e sem os blocos auxiliares que o usuario marcou
- os controles principais de montagem e envio permaneceram intactos
- o proximo passo correto e fazer um refresh forte no navegador e validar a interface limpa diretamente na VPS

### 39.18 Campo `Botao real do email` aceita digitacao completa novamente

Data:

- `2026-04-17`

Contexto:

- depois da entrada do CTA real controlado pelo editor, o campo `button_label` passou a aceitar apenas a primeira letra digitada
- a causa era a sincronizacao entre formulario e canvas:
- o grupo do botao no Fabric nao atualizava o `label.text` interno a cada tecla
- na serializacao, o editor priorizava o texto antigo do label interno antes de `meta.textRaw`
- o `syncLegacyHiddenState()` ainda podia reescrever o input enquanto o usuario estava digitando

Correcao aplicada:

- `syncButtonGroupText()` agora atualiza explicitamente o texto do label interno do botao
- `objectToSceneLayer()` passou a priorizar `meta.textRaw` na serializacao da camada `button`
- `syncLegacyHiddenState()` passou a respeitar foco ativo em `button_label`, `link_url` e `button_font_size`, evitando sobrescrever o valor enquanto o usuario digita

Arquivos envolvidos:

- `assets/js/admin-message-editor-v2.js`
- `README.md`

Validacao local:

- `node --check assets/js/admin-message-editor-v2.js`

Ponto exato onde paramos agora:

- o texto do CTA do email volta a sincronizar corretamente entre campo e canvas
- o campo `Botao real do email` nao deve mais travar na primeira letra
- o proximo passo correto e fazer refresh forte no admin e validar digitacao normal direto na VPS

### 39.19 Campo `button_label` volta a aceitar string vazia

Data:

- `2026-04-17`

Contexto:

- depois da correcao anterior, ainda existia um sintoma residual: o usuario conseguia digitar o texto inteiro do CTA, mas nao conseguia apagar a ultima letra
- a causa era um fallback restante no editor:
- ao serializar a camada `button`, o sistema ainda podia restaurar um texto default quando `textRaw` ficava vazio
- ao sincronizar o input com o canvas, `meta.textRaw` tambem voltava para um placeholder quando o campo era apagado por completo

Correcao aplicada:

- o editor passou a separar:
- `textRaw` salvo, que agora pode ser string vazia de verdade
- texto visual de placeholder no canvas, usado apenas para nao deixar o botao invisivel durante a edicao
- `syncButtonInputsToCanvasLayer()` agora aceita `requestedLabel = ''` sem restaurar o valor antigo
- `objectToSceneLayer()` agora preserva `meta.textRaw` vazio sem cair para o texto do label interno

Arquivos envolvidos:

- `assets/js/admin-message-editor-v2.js`
- `README.md`

Validacao local:

- `node --check assets/js/admin-message-editor-v2.js`

Ponto exato onde paramos agora:

- o CTA do email continua aparecendo no canvas com placeholder visual quando o texto esta vazio
- o valor salvo do campo `Botao real do email` agora pode ser apagado completamente
- o proximo passo correto e fazer refresh forte no admin e validar que apagar todo o texto nao restaura mais a primeira letra

### 39.20 Botao real do email passa a usar o modelo canonico do CTA

Data:

- `2026-04-17`

Contexto:

- o usuario reportou que a implementacao do CTA real do email estava inconsistente:
- ao redimensionar e depois mover o botao, ele podia voltar para o tamanho anterior
- o preview do editor ainda usava um botao generico e nao o mesmo estilo do HTML canonico aprovado
- o tamanho do texto ainda podia ser recalculado pela altura do botao, o que fugia do comportamento desejado
- o texto precisava ficar sempre centralizado dentro do botao, sem camada de texto solta ou movivel

Correcao aplicada:

- `assets/js/admin-message-editor-v2.js` passou a tratar o CTA como um componente canonico:
- default do botao alinhado a `483x156` proporcional ao canvas `1080x1620`
- `fontSize` default fixo em `24`
- `fontSize` desacoplado da altura do botao
- persistencia explicita de `width`, `height` e `fontSize` no `meta` da camada
- recalculo do grupo do Fabric preservando tamanho depois de resize + move
- texto sempre centralizado no grupo, com `Helvetica Neue, Arial, sans-serif`
- preview do editor com textura do mesmo botao real usado no HTML do email
- `assets/js/message-scene-renderer.js` e `templates/message-renderer.html` passaram a usar o mesmo CTA canonico no preview/export, sem voltar a derivar a fonte pela altura
- `includes/customer_messages.php` e `admin/includes/customer_messages.php` passaram a tratar `fontSize` do CTA com fallback fixo em `24`, em vez de recalcular pelo `buttonHeight`

Arquivos envolvidos:

- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `templates/message-renderer.html`
- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`

Validacao local:

- `node --check assets/js/admin-message-editor-v2.js`
- `node --check assets/js/message-scene-renderer.js`
- `php -l includes/customer_messages.php`
- `php -l admin/includes/customer_messages.php`

Ponto exato onde paramos agora:

- o CTA do email passou a ter uma base unica entre editor, preview e HTML final
- o tamanho do texto deixou de depender automaticamente da altura do botao
- a proxima validacao correta e testar no admin se:
- resize + move mantem o tamanho final
- o texto continua centralizado no botao
- o visual do botao no editor ficou aderente ao CTA canonico aprovado
