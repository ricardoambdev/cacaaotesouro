/**
 * Cofre da Gincana — aplicativo Windows (Electron).
 *
 * O cofre RODA DENTRO do programa: a tela (renderer/) tem o visual e a
 * lógica do cofre, e conversa com a API direto daqui do processo principal
 * (sem barra de endereços, sem abrir navegador, sem arquivo de configuração).
 *
 * Tudo o que o cofre precisa (bloqueio, tentativas, raio, se está liberado)
 * vem da API. Só o endereço do servidor fica aqui, no código.
 */

const { app, BrowserWindow, Menu, ipcMain, session } = require('electron');
const path = require('path');

/** API do sistema (produção). */
const API_BASE = 'https://cacaaotesouro.colegiohelena.com.br/api';

/** Tempo máximo de cada chamada à API. */
const API_TIMEOUT_MS = 20000;

/** Nome exibido na janela (nunca a URL). */
const WINDOW_TITLE = 'Cofre da Gincana';

// Geolocalização no Windows: usa o serviço de localização do próprio
// sistema (Configurações → Privacidade → Localização precisa estar ligado).
app.commandLine.appendSwitch('enable-features', 'WinrtGeolocationImplementation');

let mainWindow = null;

/* ════════════════════════════════════════════════════════════════
   CHAMADAS À API (feitas aqui, no processo principal)
   ════════════════════════════════════════════════════════════════ */

/**
 * GET /api/cofre?lat=&lng= → situação atual.
 * Lança erro quando a API não responde (a tela mostra o aviso).
 */
async function apiStatus(lat, lng) {
  const params = new URLSearchParams();

  if (typeof lat === 'number' && typeof lng === 'number') {
    params.set('lat', String(lat));
    params.set('lng', String(lng));
  }

  params.set('t', String(Date.now())); // evita cache

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), API_TIMEOUT_MS);

  try {
    const res = await fetch(`${API_BASE}/cofre?${params.toString()}`, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) {
      throw new Error('HTTP ' + res.status);
    }

    return await res.json();
  } finally {
    clearTimeout(timer);
  }
}

/**
 * POST /api/cofre { code, lat, lng } → { status, data }.
 * Não lança em 4xx: a tela precisa ler o motivo (fora do raio, bloqueado...).
 */
async function apiCheck(code, lat, lng) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), API_TIMEOUT_MS);

  const payload = { code: String(code || '') };

  if (typeof lat === 'number' && typeof lng === 'number') {
    payload.lat = lat;
    payload.lng = lng;
  }

  try {
    const res = await fetch(`${API_BASE}/cofre`, {
      method: 'POST',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    });

    let data = {};

    try {
      data = await res.json();
    } catch (_) {
      data = { success: false, error: 'Resposta inválida do servidor.' };
    }

    return { status: res.status, data };
  } finally {
    clearTimeout(timer);
  }
}

ipcMain.handle('cofre:status', async (_event, { lat, lng } = {}) => {
  return apiStatus(lat, lng);
});

ipcMain.handle('cofre:check', async (_event, { code, lat, lng } = {}) => {
  try {
    return await apiCheck(code, lat, lng);
  } catch (err) {
    // Falha de rede: devolve no mesmo formato para a tela tratar
    return {
      status: 0,
      data: {
        success: false,
        error: 'Sem conexão com o servidor. Verifique a internet e tente novamente.',
      },
    };
  }
});

/* ════════════════════════════════════════════════════════════════
   JANELA
   ════════════════════════════════════════════════════════════════ */

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1100,
    height: 820,
    minWidth: 800,
    minHeight: 640,
    title: WINDOW_TITLE,
    backgroundColor: '#050B12',
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      devTools: false,
      spellcheck: false,
    },
  });

  Menu.setApplicationMenu(null);
  mainWindow.setMenuBarVisibility(false);

  // O título da janela nunca vira URL/endereço.
  mainWindow.on('page-title-updated', (event) => {
    event.preventDefault();
    mainWindow.setTitle(WINDOW_TITLE);
  });

  // Botão direito desabilitado (nada de "inspecionar").
  mainWindow.webContents.on('context-menu', (event) => event.preventDefault());

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    mainWindow.maximize();
  });

  mainWindow.loadFile(path.join(__dirname, 'renderer', 'index.html'));

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

/* ════════════════════════════════════════════════════════════════
   PERMISSÕES (somente localização) + ATALHOS
   ════════════════════════════════════════════════════════════════ */

app.whenReady().then(() => {
  // Sem isso o Chromium nega a geolocalização por padrão.
  session.defaultSession.setPermissionRequestHandler((_wc, permission, callback) => {
    callback(permission === 'geolocation');
  });

  session.defaultSession.setPermissionCheckHandler((_wc, permission) => {
    return permission === 'geolocation';
  });

  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

// F11 = tela cheia · F5 = recarregar · Ctrl+Shift+Q = sair
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

app.on('window-all-closed', () => {
  app.quit();
});
