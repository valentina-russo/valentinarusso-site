const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  console.log('Navigazione in corso verso Netlify Domain Settings...');
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });
  await page.screenshot({ path: 'netlify_domain_verification.png' });
  const text = await page.evaluate(() => document.body.innerText);
  console.log('--- STATO ATTUALE ---');
  if (text.includes('valentinarussobg5.com')) {
    console.log('RISULTATO: Il dominio è stato aggiunto al pannello.');
    if (text.includes('Waiting for DNS propagation') || text.includes('Check DNS configuration')) {
      console.log('DETTAGLIO: In attesa di propagazione DNS o configurazione manuale richiesta.');
    } else {
      console.log('DETTAGLIO: Dominio configurato correttamente.');
    }
  } else {
    console.log('RISULTATO: Dominio non ancora presente.');
  }
  await browser.close();
})();