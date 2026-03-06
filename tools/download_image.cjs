const fs = require('fs');
const path = require('path');
const https = require('https');

const imageUrl = 'https://storage.googleapis.com/sh-opencode-public/6561f67f-479e-4cae-908a-669527e05697/81721539-78a0-40e1-b4f0-4660e1d51a65.png';
const destPath = path.join(process.cwd(), 'public', 'assets', 'business-hero.png');

const file = fs.createWriteStream(destPath);
https.get(imageUrl, function(response) {
  response.pipe(file);
  file.on('finish', function() {
    file.close();
    console.log('✅ Immagine salvata in: ' + destPath);
  });
}).on('error', function(err) {
  fs.unlink(destPath);
  console.error('❌ Errore nel download: ' + err.message);
});
