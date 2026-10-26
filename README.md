# Futsal Cuts · Talent ID

Marca momentos de jogos de futsal (vídeo do YouTube), anota, cria quadros táticos com animação e apresenta à equipa.

## Instalar no alojamento (PHP 7.4+)
1. Faz upload de **todo o conteúdo** desta pasta (incluindo `.htaccess`, `sw.js` e `manifest.webmanifest`).
2. Dá permissão de escrita às pastas `data/` e `uploads/` (755 ou 775).
3. Abre `login.php`. No primeiro acesso é criado o administrador a partir do `config.php`
   (utilizador `coach`); muda a palavra-passe em **Conta → Utilizador**.
4. **Se já tens dados:** este pacote não leva `data/app.json` nem `data/users.json`, para não os
   substituir. Mantém os teus no servidor.
5. Ativa **HTTPS** no domínio (obrigatório para instalar como aplicação) e descomenta o
   redirecionamento no `.htaccess`.

## Instalar como aplicação (Windows, Android, iPad)
- **Windows (Edge/Chrome):** menu do utilizador → *Instalar app*, ou menu ⋯ → Aplicações → Instalar este site.
- **Android (Chrome):** menu ⋮ → *Instalar aplicação*.
- **iPad (Safari):** Partilhar → *Adicionar ao ecrã principal*.
Sem internet abre uma página de aviso; o vídeo do YouTube precisa de ligação.

## O que tem
- **Jogos**: vídeo (tamanho normal/grande/máximo com a tecla V, ou ecrã inteiro com «marcar rápido»), marcar rápido (tecla M), momentos, painéis (texto, capa, índice, quadro).
- **Momentos**: biblioteca com filtros por **tag**, tipo e **animação** (todos / só com animação / sem animação) e
  botão *Ver sequência*.
- **Quadro tático**: campo 5 vs 5; as cores das equipas e dos guarda-redes mudam-se tocando nas bolinhas da legenda («Casa», «GR», «GR», «Fora») no próprio campo; menu vertical de ferramentas;
  grava fotogramas e anima. Também dentro de cada jogo (painel *Quadro tático*).
- **Apresentação**: ecrã inteiro, menu para saltar, animações dos quadros, PDF.
- **Perfis**: administrador, editor e só visualização.
