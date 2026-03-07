const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  
  console.log('Controllo impostazioni Dominio su Netlify...');
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });
  
  await page.screenshot({ path: 'netlify_domain_settings.png' });
  
  const bodyText = await page.evaluate(() => document.body.innerText);
  
  if (bodyText.includes('valentinarussobg5.com')) {
    console.log('CONFERMATO: Il dominio valentinarussobg5.com è presente nelle impostazioni.');
  } else {
    console.log('ATTENZIONE: Il dominio valentinarussobg5.com NON sembra essere configurato.');
  }

  await browser.close();
})();