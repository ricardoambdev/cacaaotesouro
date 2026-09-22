/**
 * Cofre da Gincana — aplicativo Windows (Electron).
 *
 * O programa faz UMA coisa: abre a página do Cofre em tela cheia.
 * Não existe barra de endereços, menu, nem qualquer indicação da URL —
 * quem estiver na frente da máquina vê apenas o cofre.
 *
 * A URL NÃO fica escrita na interface. Ela é lida de um arquivo
 * `config.json` colocado AO LADO do executável:
 *
 *     { "url": "https://SEU-SITE/v/SEGREDO" }
 *
 * Se o arquivo não existir, usa a URL padrão definida abaixo.
 * (Assim, quando você gerar um link novo no painel, basta trocar o
 *  config.json — sem precisar recompilar.)
 */

const { app, BrowserWindow, Menu, screen, shell } = require('electron');
const path = require('path');
const fs = require('fs');

/**
 * URL padrão (produção). Troque pelo link secreto do cofre
 * (painel → Cofre → copiar link) OU use o config.json.
 */
const DEFAULT_URL = 'https://cacaaotesouro.colegiohelena.com.br/cofre';

/** Nome exibido na janela (nunca a URL). */
const WINDOW_TITLE = 'Cofre da Gincana';

/**
 * Descobre a URL do cofre:
 *   1) config.json ao lado do .exe   (preferido)
 *   2) config.json junto do app
 *   3) DEFAULT_URL
 */
function resolveUrl() {
  const candidates = [
    path.join(path.dirname(process.execPath), 'config.json'),
    path.join(__dirname, 'config.json'),
    path.join(process.resourcesPath || '', 'app', 'config.json'),
  ];

  for (const file of candidates) {
    try {
      if (file && fs.existsSync(file)) {
        const raw = fs.readFileSync(file, 'utf8');
        const data = JSON.parse(raw);

        if (data && typeof data.url === 'string' && data.url.trim() !== '') {
          return data.url.trim();
        }
      }
    } catch (_) {
      // config inválido: ignora e tenta o próximo
    }
  }

  return DEFAULT_URL;
}

let mainWindow = null;

function createWindow() {
  const { width, height } = screen.getPrimaryDisplay().workAreaSize;

  mainWindow = new BrowserWindow({
    width: Math.max(1024, Math.round(width * 0.9)),
    height: Math.max(700, Math.round(height * 0.9)),
    minWidth: 800,
    minHeight: 600,
    title: WINDOW_TITLE,
    backgroundColor: '#050B12',
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      devTools: false,
      spellcheck: false,
    },
  });

  // Sem menu (nem o menu "oculto" do Alt) e sem barra de endereços.
  Menu.setApplicationMenu(null);
  mainWindow.setMenuBarVisibility(false);

  // O título NUNCA vira a URL.
  mainWindow.on('page-title-updated', (event) => {
    event.preventDefault();
    mainWindow.setTitle(WINDOW_TITLE);
  });

  // Links externos abrem no navegador padrão, não dentro do programa.
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  // Nada de mostrar o endereço para quem está usando a máquina.
  mainWindow.webContents.on('context-menu', (event) => event.preventDefault());

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    mainWindow.maximize();
  });

  mainWindow.loadURL(resolveUrl());

  // Se a página falhar (sem internet, link trocado...), tenta de novo.
  mainWindow.webContents.on('did-fail-load', (_e, _code, _desc, validatedURL) => {
    setTimeout(() => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.loadURL(validatedURL || resolveUrl());
      }
    }, 4000);
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// ── Atalhos de operação (sem revelar nada na tela) ────────────────
// F11 alterna tela cheia · F5 recarrega · Ctrl+Shift+Q fecha
app.on('browser-window-created', (_event, win) => {
  win.webContents.on('before-input-event', (event, input) => {
    if (input.type !== 'keyDown') return;

    if (input.key === 'F11') {
      win.setFullScreen(!win.isFullScreen());
      event.preventDefault();
      return;
    }

    if (input.key === 'F5') {
      win.reload();
      event.preventDefault();
      return;
    }

    if (input.control && input.shift && input.key.toLowerCase() === 'q') {
      app.quit();
      event.preventDefault();
    }
  });
});

app.whenReady().then(() => {
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  app.quit();
});
