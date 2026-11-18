# Isto não é um Vinho — site

Site estático (HTML/CSS/JS) + um script PHP para enviar os emails do formulário por SMTP.

## Estrutura

| Ficheiro | Para que serve |
|---|---|
| `index.html` | Página única: entrada 18+, hero, história, o vinho, contador, encomendar |
| `politica-privacidade.html` | Política de privacidade (preencher os `[CAMPOS]`) |
| `config.js` | Preço, garrafas que restam, total, endpoint do formulário |
| `assets/css/styles.css` | Estilos |
| `assets/js/main.js` | Entrada 18+, contador, validação e envio do formulário |
| `assets/img/` | Logótipos (SVG/PNG do design), favicon, og.jpg |
| `assets/fonts/` | Instrument Serif + Inter (OFL), alojadas localmente |
| `api/encomenda.php` | Recebe o formulário, valida e envia os emails |
| `api/smtp.php` | Cliente SMTP sem dependências (SSL/STARTTLS) |
| `api/config.php` | **Dados do servidor SMTP** e destinatários |

## Formulário / emails

1. Preencher `api/config.php` com host, porta, cifra, utilizador e password SMTP.
2. O alojamento tem de ter PHP 7.4+ (com OpenSSL). Apache: o `api/.htaccess` bloqueia o acesso direto a `config.php`, `smtp.php` e ao CSV.
3. Cada encomenda:
   - envia um email para `to` com os dados (o "Responder" vai direto ao cliente);
   - envia ao cliente um email "Recebido" com o resumo (`confirmacao_cliente`);
   - guarda uma linha em `api/dados/encomendas.csv` (`guardar_csv`).
4. `origens` em `config.php` só aceita pedidos vindos do domínio do site. Para testar noutro domínio, acrescenta-o ou deixa a lista vazia.

## Deploy automático (Google Cloud Run)

Cada push para `main` corre `.github/workflows/deploy.yml`: constrói a imagem Docker (PHP 8.3 + Apache),
envia-a para o Artifact Registry e publica o serviço `istonaoeumvinho` no Cloud Run (`europe-west1`,
projeto `istonaoeumvinho`). Também se pode correr à mão em GitHub → Actions → "Run workflow".

- Autenticação GitHub → Google Cloud por Workload Identity Federation (sem chaves). Só o repositório
  `tiagomendesmarques/istonaoeumvinho.pt`, ramo `main`, pode fazer deploy.
- Servidor, porta e utilizador SMTP estão no bloco `env:` do workflow.
- A password SMTP está no Secret Manager (segredo `smtp-password`). Para a mudar:
  `gcloud secrets versions add smtp-password --data-file=ficheiro.txt --project istonaoeumvinho`
  e fazer um novo deploy.

## Cache

Os HTML carregam o CSS/JS com `?v=__VERSION__`. No build, o Dockerfile troca `__VERSION__` pelos
8 primeiros caracteres do commit, por isso cada deploy obriga o browser a ir buscar os ficheiros novos.
O `.htaccess` da raiz define: HTML sem cache (confirma sempre), CSS/JS 1 ano, imagens e fontes 1 semana.
Ao acrescentar um novo CSS/JS a um HTML, pôr também `?v=__VERSION__` no endereço.

## No dia a dia

Atualizar `restam` em `config.js`. Com `0`, o formulário desaparece.

## Fotografia

Guardar em `assets/img/garrafa.jpg` e trocar `<div class="foto-vazia">` em `index.html` por
`<img src="assets/img/garrafa.jpg" alt="Garrafa de Isto não é um Vinho">`.
