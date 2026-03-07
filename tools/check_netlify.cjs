const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  
  console.log('Navigazione verso Netlify Admin...');
  // Nota: Questo mostrerà probabilmente la pagina di login
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/overview', { waitUntil: 'networkidle2' });
  
  await page.screenshot({ path: 'netlify_status.png' });
  
  const title = await page.title();
  console.log('Titolo pagina:', title);
  
  if (title.includes('Log in')) {
    console.log('AZIONE RICHIESTA: Netlify richiede il login. Non posso procedere oltre senza credenziali.');
  }

  await browser.close();
})();
