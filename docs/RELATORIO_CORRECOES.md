# Futsal Cuts — correções aplicadas

Esta versão foi reconstruída a partir do `videocut.zip` fornecido pelo utilizador. O ficheiro `data/app.json` e `data/users.json` foram mantidos sem alteração.

## Alterações
- Página Tags com adicionar, renomear e eliminar; renomeação/eliminação propagada aos momentos.
- Nova página Todos os momentos com filtros por tag/categoria e apresentação sequencial multi-jogo.
- Índice em 2 colunas por omissão e 4 a partir de 1024px; número em círculo amarelo alinhado por grid.
- Lista de momentos em 2 colunas em ecrãs largos e 1 coluna em telemóvel.
- Prancheta: duplo clique no jogador para editar nome e cor; guardar passos e reproduzir uma animação interpolada.
- PWA: manifesto e ligação no cabeçalho.
- Correção importante: o JS ativo é `assets/app.js` (incluído no footer); o JS atualizado foi copiado para esse caminho.

## Limitações/validação
- A apresentação multi-jogo carrega o primeiro vídeo e permite navegar entre momentos; ao mudar de vídeo, a API YouTube carrega o vídeo correspondente quando disponível.
- Testes locais de sintaxe devem ser repetidos no servidor cPanel. A exportação PDF e comportamento real do YouTube dependem do alojamento/browser e requerem validação funcional.
- Faça backup antes de publicar.
