const https = require('https');

const token = 'nfp_XZcPBN2dkbsV8uCvuJ9sjoqtWMA6sMURe8a9';
const siteId = 'e992e9b7-f483-49a1-b6c1-1e1a8364a473';

function apiRequest(path, method = 'GET', body = null) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'api.netlify.com',
      path: '/api/v1' + path,
      method: method,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(data ? JSON.parse(data) : {});
        } else {
          reject(new Error(`API Error: ${res.statusCode} - ${data}`));
        }
      });
    });

    req.on('error', (e) => reject(e));
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

async function run() {
  try {
    console.log('🔗 Colleghiamo il repository al sito...');
    await apiRequest(`/sites/${siteId}`, 'PUT', {
      build_settings: {
        provider: 'github',
        repo_path: 'valentina-russo/valentinarusso-site',
        repo_branch: 'main',
        cmd: 'npm run build',
        dir: 'dist'
      }
    });
    console.log('✅ Repository collegato con successo!');

    console.log('🔐 Attiviamo i servizi di gestione contenuti...');
    // Questo attiva il "motore" del blog
    await apiRequest(`/sites/${siteId}/services/git-gateway/enable`, 'POST');
    console.log('✅ Git Gateway attivato!');
    
    console.log('✨ Operazione completata. Il blog è pronto.');
  } catch (err) {
    console.error('❌ Errore durante l\'attivazione automatica:', err.message);
  }
}

run();
