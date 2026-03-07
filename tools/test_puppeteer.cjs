const puppeteer = require('puppeteer');

(async () => {
  try {
    console.log('🚀 Avvio Puppeteer...');
    const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    console.log('✅ Browser avviato correttamente!');
    
    const page = await browser.newPage();
    console.log('🔍 Navigazione verso Google per test...');
    await page.goto('https://www.google.com');
    
    const title = await page.title();
    console.log(`✨ Titolo pagina: ${title}`);
    
    await browser.close();
    console.log('🏁 Puppeteer è perfettamente funzionante per questo progetto!');
  } catch (err) {
    console.error('❌ Errore durante il test di Puppeteer:', err.message);
    process.exit(1);
  }
})();
