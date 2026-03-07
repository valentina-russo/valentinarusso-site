const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 1000 });
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });

  console.log('Eseguo il clic sul bottone tramite script JS...');
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const addBtn = buttons.find(b => b.innerText.includes('Add custom domain'));
    if (addBtn) addBtn.click();
  });

  await new Promise(r => setTimeout(r, 2000));
  console.log('Inserimento dominio...');
  await page.keyboard.type('valentinarussobg5.com');
  await page.keyboard.press('Enter');

  await new Promise(r => setTimeout(r, 5000));
  await page.screenshot({ path: 'netlify_result_final.png' });
  
  const text = await page.evaluate(() => document.body.innerText);
  console.log('STATO:', text.includes('valentinarussobg5.com') ? 'SUCCESSO' : 'NON ANCORA AGGIUNTO');

  await browser.close();
})();