const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electron', {
  quitApp: () => ipcRenderer.send('quitApp')
});
