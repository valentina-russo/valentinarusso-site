const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const SESSION_FILE = path.join(__dirname, '.session.json');
const BASE_URL = 'https://valentinarussobg5.com/admin';

async function sync() {
    const browser = await puppeteer.launch({ headless: false }); // Visible for initial login/verification
    const page = await browser.newPage();

    // Load session if exists
    if (fs.existsSync(SESSION_FILE)) {
        const cookies = JSON.parse(fs.readFileSync(SESSION_FILE));
        await page.setCookie(...cookies);
    }

    await page.goto(BASE_URL);

    // Check if logged in
    const isLoggedIn = await page.evaluate(() => !document.querySelector('form.login-form'));

    if (!isLoggedIn) {
        console.log('⚠️ Sessione non valida o assente. Per favore effettua il login nel browser...');
        // Wait for login (selector for dashboard)
        try {
            await page.waitForSelector('#admin-sidebar', { timeout: 60000 });
            const cookies = await page.cookies();
            fs.writeFileSync(SESSION_FILE, JSON.stringify(cookies));
            console.log('✅ Login effettuato e sessione salvata!');
        } catch (e) {
            console.error('❌ Timeout login fallito.');
            await browser.close();
            return;
        }
    } else {
        console.log('✅ Sessione valida trovata.');
    }

    // Get local pages
    const pagesDir = path.join(__dirname, '../grav-site/user/pages');
    const folders = fs.readdirSync(pagesDir, { withFileTypes: true })
        .filter(dirent => dirent.isDirectory())
        .map(dirent => dirent.name);

    for (const folder of folders) {
        const mdPath = path.join(pagesDir, folder, folder.split('.').slice(1).join('.') + '.md');
        // Grav uses numeric prefixes for ordering, e.g., 01.home -> home.md
        // Try both folder name and stripped name
        let finalMdPath = mdPath;
        if (!fs.existsSync(mdPath)) {
            const strippedName = folder.replace(/^\d+\./, '');
            finalMdPath = path.join(pagesDir, folder, strippedName + '.md');
        }

        if (fs.existsSync(finalMdPath)) {
            console.log(`📄 Sincronizzazione pagina: ${folder}`);
            const content = fs.readFileSync(finalMdPath, 'utf8');

            const adminPath = folder.replace(/^\d+\./, '');
            await page.goto(`${BASE_URL}/pages/${adminPath}`);

            // Wait for editor
            await page.waitForSelector('.CodeMirror-code');

            // Update content (simplified for now: overwrite entire thing)
            await page.evaluate((newContent) => {
                const editor = document.querySelector('.CodeMirror').CodeMirror;
                editor.setValue(newContent);
            }, content);

            // Save
            await page.click('#admin-topbar .button-save');
            await page.waitForSelector('.alert.success');
            console.log(`✅ ${folder} sincronizzata.`);
        }
    }

    browser.close();
}

sync();
