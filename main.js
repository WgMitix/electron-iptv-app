const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const { exec } = require('child_process');

let mainWindow;
let phpServer;



// Fonction pour démarrer le serveur PHP
function startPHPServer() {
  phpServer = exec('php -S localhost:8000 -t php/', (error, stdout, stderr) => {
    if (error) {
      console.error(`Erreur PHP: ${error.message}`);
      return;
    }
    if (stderr) {
      console.error(`Erreur PHP stderr: ${stderr}`);
      return;
    }
    console.log(`PHP Server: ${stdout}`);
  });

  phpServer.stdout.on('data', (data) => {
    console.log(`[PHP Server]: ${data}`);
  });

  phpServer.stderr.on('data', (data) => {
    console.error(`[PHP Error]: ${data}`);
  });

  phpServer.on('close', (code) => {
    console.log(`PHP server stopped with code ${code}`);
  });
}

app.whenReady().then(() => {
  // Démarrer les serveurs PHP et MySQL automatiquement à l'ouverture de l'application
  startPHPServer();
  // Créer la fenêtre principale
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    fullscreen: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
    },
    frame: false,
    autoHideMenuBar: true,
    resizable: true,
    transparent: true,
    alwaysOnTop: true,
    icon: path.join(__dirname, 'assets', 'app-icon.png') 
  });

  // Charger la page PHP via l'URL du serveur
  mainWindow.loadURL('http://localhost:8000/manage_profiles.php');

  mainWindow.on('closed', () => {
    mainWindow = null;
    app.quit();
    if (phpServer) {
      phpServer.kill('SIGTERM');
      console.log("Le serveur PHP a été arrêté avec SIGTERM.");
    }
  });

  // Ajouter un écouteur pour le message "quitApp"
  ipcMain.on('quitApp', () => {
    app.quit();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
