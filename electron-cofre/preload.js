/**
 * Ponte segura entre a tela (renderer) e o processo principal.
 *
 * A tela do cofre NÃO faz requisições HTTP diretamente: ela pede para o
 * processo principal, que fala com a API (assim não existe problema de CORS
 * e a URL da API nunca aparece na interface).
 */

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('cofreApi', {
  /**
   * Situação do cofre (bloqueado? dentro do raio? quantas tentativas?).
   * @returns {Promise<object>}
   */
  status: (lat, lng) => ipcRenderer.invoke('cofre:status', { lat, lng }),

  /**
   * Confere o código de 9 dígitos.
   * @returns {Promise<{status:number, data:object}>}
   */
  check: (code, lat, lng) => ipcRenderer.invoke('cofre:check', { code, lat, lng }),
});
