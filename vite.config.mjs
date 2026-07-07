import { defineConfig } from 'vite';
import { spawn } from 'child_process';
import { existsSync } from 'fs';

// Resolve the local XAMPP PHP executable path with fallback to global PHP CLI
const phpPath = existsSync('D:\\xampp\\php\\php.exe') ? 'D:\\xampp\\php\\php.exe' : 'php';
let phpServerProcess = null;

export default defineConfig({
  server: {
    port: 5173,
    proxy: {
      // Matches any path ending with .php or having query parameters (e.g. login.php?action=logout)
      '^/.*\\.php($|\\?)': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  plugins: [
    {
      name: 'php-dev-server',
      configureServer(server) {
        // Start PHP server on port 8000 in background
        phpServerProcess = spawn(phpPath, ['-S', 'localhost:8000'], {
          stdio: 'ignore'
        });

        console.log(`\n  ➜  PHP Backend Server started at http://localhost:8000/ using ${phpPath}`);

        // Ensure process gets terminated when Vite closes
        const killPHPServer = () => {
          if (phpServerProcess) {
            phpServerProcess.kill();
            phpServerProcess = null;
          }
        };

        server.httpServer?.on('close', killPHPServer);
        process.on('exit', killPHPServer);
        process.on('SIGINT', killPHPServer);
        process.on('SIGTERM', killPHPServer);
      }
    }
  ]
});
