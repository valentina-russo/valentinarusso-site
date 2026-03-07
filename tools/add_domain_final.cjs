const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 1000 });
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });

  console.log('Cerco il tasto Add custom domain...');
  const buttons = await page.$$('button');
  for (const button of buttons) {
    const text = await page.evaluate(el => el.innerText, button);
    if (text.includes('Add custom domain')) {
      await button.click();
      console.log('Tasto cliccato.');
      break;
    }
  }

  await page.waitForTimeout(2000);
  console.log('Inserisco il dominio...');
  await page.type('input[type="text"]', 'valentinarussobg5.com');
  await page.keyboard.press('Enter');

  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'netlify_domain_added.png' });
  console.log('Operazione completata. Screenshot salvato in netlify_domain_added.png');

  await browser.close();
})();