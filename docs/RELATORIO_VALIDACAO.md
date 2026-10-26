# Relatório de validação — Futsal Cuts

## Alterações incluídas
- Índice: círculos amarelos de numeração com largura/altura fixas; duas colunas em ecrãs estreitos e quatro colunas a partir de 1024 px.
- Navegação da apresentação mantida acessível em ecrã inteiro.
- Tags: adicionar, renomear e eliminar; renomear propaga o novo nome aos momentos existentes; eliminar remove as referências existentes.
- Nova página `moments.php`: filtros globais por tag e categoria, lista de todos os momentos e apresentação sequencial em ecrã inteiro, independentemente do jogo.
- Lista de momentos do jogo em duas colunas em ecrãs maiores e uma coluna em telemóveis.
- Prancheta: passos de animação com interpolação para jogadores e bola, cores por equipa e cor individual por jogador.
- PWA: manifesto, ícone de 512 px e service worker para recursos estáticos em HTTPS. Não faz cache de páginas PHP nem da pasta de dados.
- `load_data()` deixa de substituir automaticamente dados vazios/inválidos; lança erro preservando o ficheiro para recuperação.
- `clean_board()` mantém as cores e os passos de animação ao guardar painéis e limita o número de passos.
- Compatibilidade com ambientes PHP sem a extensão `mbstring` para as operações usadas nesta alteração.

## Testes locais executados
- `php -l` em todos os ficheiros PHP: aprovado.
- `node --check app.js`: aprovado.
- Leitura JSON de `data/app.json` e `manifest.webmanifest`: aprovado.
- Teste de `clean_board()` para preservar cor e dois passos de animação: aprovado.
- Não foi possível validar login, YouTube, exportação PDF ou comportamento em cPanel real neste ambiente; testar primeiro numa pasta de staging.

## Instalação segura
1. Fazer backup da pasta e de `data/app.json`/`data/users.json`.
2. Extrair a pasta `videocut` numa localização de teste.
3. Confirmar permissões de escrita em `data/` e acesso HTTPS.
4. Testar login, tags, filtros globais, edição de prancheta, animação, apresentação, exportação PDF e instalação PWA.
5. Só depois promover a versão para produção.
