const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  
  // Set viewport to a common mobile size (iPhone 12/13/14)
  await page.setViewport({ width: 390, height: 844, isMobile: true });

  console.log('Caricamento pagina marcomunich.com...');
  await page.goto('https://marcomunich.com/corso-come-parlare-in-video/', { waitUntil: 'networkidle2' });

  // CSS Correttivo ad alta specificità
  const customCSS = `
    @media screen and (max-width: 991px) {
        .video-page .vp-grid,
        .video-page main section > div[style*="display: grid"],
        .video-page .vp-talk,
        .video-page .vp-costs {
            display: flex !important;
            flex-direction: column !important;
            gap: 30px !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .video-page .vp-grid > div,
        .video-page .vp-costs > div,
        .video-page .vp-talk > div {
            width: 100% !important;
            max-width: 100% !important;
            flex: 1 1 auto !important;
        }

        .video-page .vp-hero-img {
            display: block !important;
            visibility: visible !important;
            order: -1 !important;
            margin-bottom: 20px !important;
        }

        .video-page section {
            padding-left: 20px !important;
            padding-right: 20px !important;
        }
    }
  `;

  console.log('Iniezione CSS correttivo...');
  await page.addStyleTag({ content: customCSS });

  console.log('Salvataggio screenshot di verifica...');
  await page.screenshot({ path: 'debug_mobile_fix.png', fullPage: false });

  await browser.close();
  console.log('Test completato. Screenshot salvato in debug_mobile_fix.png');
})();
