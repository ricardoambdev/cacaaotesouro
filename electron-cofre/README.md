# Cofre da Gincana — app Windows (Electron)

Aplicativo **standalone** do Cofre: o cofre roda **dentro do programa** e fala
direto com a API do sistema. Não abre navegador, não tem barra de endereços e
**não usa arquivo de configuração** — tudo (se está liberado, raio, tentativas,
bloqueio) vem da API.

## Como funciona

```
┌───────────────────────────────┐
│  Cofre-da-Gincana.exe         │
│                               │
│  renderer/  → visual + lógica │  (HTML/CSS/JS embutidos)
│       │                       │
│       │ IPC (preload)         │
│       ▼                       │
│  main.js  → fetch para a API  │  (sem CORS, URL nunca aparece)
└───────────────────────────────┘
```

- **`renderer/`** — o cofre (visual do cofre com a porta, campos de dígito,
  modal de aviso, animação de abertura e revelação lenta da senha).
- **`main.js`** — janela + chamadas HTTP para a API (`/api/cofre`).
- **`preload.js`** — ponte segura (`window.cofreApi.status/check`).
- **Geolocalização** — usa o serviço de localização do Windows
  (`WinrtGeolocationImplementation`); a permissão é liberada só para
  `geolocation`.

## Onde a API está configurada

Só um lugar, no topo de `main.js`:

```js
const API_BASE = 'https://cacaaotesouro.colegiohelena.com.br/api';
```

## Gerar o .exe

```bash
cd electron-cofre
npm install
npm run dist
```

Saída: `dist/Cofre-da-Gincana.exe` (portable, ~71 MB, arquivo único).

## Atalhos

| Tecla | Ação |
|---|---|
| `F11` | alterna tela cheia |
| `F5` | recarrega |
| `Ctrl+Shift+Q` | fecha o programa |

## No PC do cofre

1. Ligue os **Serviços de Localização do Windows**:
   Configurações → Privacidade e segurança → Localização → **Ativar**
2. Duplo clique no `Cofre-da-Gincana.exe`
3. O cofre só abre se o PC estiver dentro do raio configurado (100 m do local)

> A página/API recusa a conferência do código fora do raio — a trava é do
> servidor, não só da tela.
