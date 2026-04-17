# Contexto para avaliar o CTA do email

Objetivo:

- o CTA do email precisa seguir o HTML canônico aprovado pelo usuário
- o texto do botão deve ficar centralizado dentro do botão
- o texto não pode ser movido de forma independente
- só o tamanho da fonte deve ser ajustável
- o botão precisa aparecer corretamente no email final

Sintomas atuais reportados pelo usuário:

- ao digitar no campo `Botao real do email`, o espaço entre palavras some durante a edição
- no canvas do editor, o texto do CTA não fica centralizado corretamente dentro do botão
- em alguns testes, o botão some do email final
- o usuário quer que o botão use exatamente o visual do asset/textura aprovado

Arquivos separados nesta pasta:

- `admin/admin/mensagens.php`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `templates/message-renderer.html`
- `includes/customer_messages.php`
- `admin/includes/customer_messages.php`
- `README.md`

Pontos de atenção mais prováveis:

- `assets/js/admin-message-editor-v2.js`
  - sincronização do campo `button_label`
  - centralização vertical/horizontal do texto dentro do grupo do Fabric
  - persistência de `width/height/fontSize` depois de resize + move
- `includes/customer_messages.php` e `admin/includes/customer_messages.php`
  - resolução do `buttonLayer`
  - fallback de `hrefRaw`, `textRaw` e `fontSize`
  - montagem final do HTML híbrido com CTA real
- `admin/admin/mensagens.php`
  - normalização/tratamento de `button_label`, `link_url`, `scene_json` e `fabric_scene_json`
