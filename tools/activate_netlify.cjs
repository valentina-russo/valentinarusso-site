const https = require('https');

const token = 'nfp_XZcPBN2dkbsV8uCvuJ9sjoqtWMA6sMURe8a9';

function apiRequest(path, method = 'GET', body = null) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'api.netlify.com',
      path: '/api/v1' + path,
      method: method,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': body ? 'application/json' : 'text/plain'
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
    console.log('🔍 Cerco i tuoi siti su Netlify...');
    const sites = await apiRequest('/sites');
    const targetSite = sites.find(s => s.name.includes('comforting-sawine') || s.name.includes('rad-frangollo'));

    if (!targetSite) {
      console.log('❌ Sito non trovato. Ecco i tuoi siti disponibili:');
      sites.forEach(s => console.log(`- ${s.name} (ID: ${s.id})`));
      return;
    }

    console.log(`✅ Sito trovato: ${targetSite.name} (ID: ${targetSite.id})`);

    // Nota: L'attivazione di Identity e Git Gateway tramite API richiede permessi specifici
    // Provo ad attivare la configurazione dell'identità
    console.log('🚀 Tentativo di attivazione Identity...');
    // Endpoint documentato per Identity: POST /sites/{site_id}/identity
    await apiRequest(`/sites/${targetSite.id}/identity`, 'POST');
    console.log('✨ Identity attivata con successo!');

  } catch (err) {
    console.error('❌ Errore durante l\'operazione:', err.message);
  }
}

run();
