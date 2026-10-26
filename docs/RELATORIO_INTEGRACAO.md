# Futsal Cuts — integração seletiva de funcionalidades

## Base utilizada
Foi usada a versão do `videocut.zip` enviado pelo utilizador como base. Os ficheiros de dados `data/app.json` e `data/users.json` foram preservados byte a byte.

## Funcionalidades integradas
- Página `moments.php`: repositório dos momentos/painéis existentes nos jogos, com filtros por tipo e tag.
- Edição dos dados do jogo: título, equipa, adversário, data, URL do YouTube e notas.
- Gestão de tags: adicionar, editar e eliminar tags; ao renomear uma tag, atualiza também as referências existentes nos momentos.
- Verificação do painel antes de o atualizar, com resposta 404 se o painel já não existir.
- Cabeçalhos HTTP de segurança básicos e `.htaccess` para desativar listagem de diretórios e bloquear acesso direto a ficheiros internos.
- Logout: expira também o cookie da sessão.
- Proteção de integridade: JSON de dados vazio/ilegível/inválido já não é substituído automaticamente por dados vazios.
- Fallback para `substr()` quando `mbstring` não está instalado, para evitar erro fatal nas novas rotinas e na limpeza da prancheta.

## Validação executada
- 17 ficheiros PHP passaram `php -l`.
- Os ficheiros JavaScript passaram `node --check`.
- Teste isolado de atualização de jogo, renomeação de tag e eliminação de tag passou.
- Teste isolado confirmou que JSON inválido é rejeitado sem alterar o conteúdo original.
- `data/app.json` e `data/users.json` são idênticos aos ficheiros da versão de origem.

## Deliberadamente não importado
Não substituí `app.js` / `assets/app.js` pela versão do pacote anterior, porque essa versão tinha alterações extensas na prancheta e removia comportamentos existentes (incluindo exportação PNG e ordenação de slides). Também não foi incluída a criação de animações globais independentes: a versão anterior tinha código parcial/inconsistente para essa funcionalidade. Assim, a prancheta existente fica preservada e evita-se reintroduzir os erros reportados.

## Limitações
Os testes foram feitos em ambiente local/CLI, não no cPanel real. Ainda é necessário testar no servidor: login, criação/edição/eliminação de jogos e momentos, prancheta, apresentação, exportação PDF, uploads e comportamento de `.htaccess`. Fazer backup antes da instalação.
