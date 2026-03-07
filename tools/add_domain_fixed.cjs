const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 1000 });
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });

  console.log('Cerco il tasto Add custom domain...');
  const [button] = await page.$x("//button[contains(., 'Add custom domain')]");
  if (button) {
    await button.click();
    console.log('Tasto cliccato.');
  }

  // Attesa generica
  await new Promise(r => setTimeout(r, 2000));
  
  console.log('Inserisco il dominio...');
  // Cerchiamo l'input
  await page.keyboard.type('valentinarussobg5.com');
  await page.keyboard.press('Enter');

  await new Promise(r => setTimeout(r, 4000));
  await page.screenshot({ path: 'netlify_domain_final.png' });
  
  const body = await page.evaluate(() => document.body.innerText);
  console.log('RISULTATO LIVE:', body.includes('valentinarussobg5.com') ? 'DOMINIO AGGIUNTO!' : 'ERRORE O ATTESA');

  await browser.close();
})();