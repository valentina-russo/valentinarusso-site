const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  
  console.log('--- ISPEZIONE LIVE: rare-north.surge.sh ---');
  
  // Cattura errori in console
  page.on('console', msg => console.log('BROWSER LOG:', msg.text()));
  
  // Cattura fallimenti di rete
  page.on('requestfailed', request => {
    console.log('NETWORK ERROR:', request.url(), request.failure().errorText);
  });

  try {
    await page.goto('https://rare-north.surge.sh/blog.html', { waitUntil: 'networkidle2' });
    
    const articlesCount = await page.evaluate(() => {
        return document.querySelectorAll('.blog-card').length;
    });
    
    console.log(`Articoli trovati nella pagina: ${articlesCount}`);
    
    const firstImage = await page.evaluate(() => {
        const img = document.querySelector('.blog-card img');
        return img ? { src: img.src, naturalWidth: img.naturalWidth } : 'Nessuna immagine';
    });
    
    console.log('Stato prima immagine:', JSON.stringify(firstImage, null, 2));

    await page.screenshot({ path: 'live_debug.png' });
    
  } catch (e) {
    console.log('ERRORE CRITICO PUPPETEER:', e.message);
  }

  await browser.close();
})();
