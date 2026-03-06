const fs = require('fs');
const path = require('path');

const projectRoot = process.cwd();
const files = fs.readdirSync(projectRoot).filter(f => f.endsWith('.html'));

// Configurazione Brand
const BRAND_HTML = `
    <img src="/assets/logo.png" alt="Valentina Russo Logo" class="brand-logo">
    <div class="brand-text">
        <span class="brand-name">VALENTINA RUSSO</span>
        <span class="brand-tagline">Consulente BG5</span>
    </div>`;

files.forEach(file => {
    const filePath = path.join(projectRoot, file);
    let content = fs.readFileSync(filePath, 'utf8');
    const isCorporate = content.includes('class="corporate"');

    // Definizione dei Menu
    const navPrivati = `
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="servizi.html">Servizi</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="chi-sono.html">Chi Sono</a></li>
                    <li><a href="contatti.html">Contatti</a></li>
                    <li><a href="aziende.html" class="btn" style="background-color: var(--corp-primary); color: white; padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 4px;">Percorso Aziende</a></li>
                </ul>`;

    const navCorporate = `
                <ul>
                    <li><a href="aziende.html">Home</a></li>
                    <li><a href="aziende-servizi.html">Servizi</a></li>
                    <li><a href="aziende-blog.html">Blog</a></li>
                    <li><a href="aziende-contatti.html">Contatti</a></li>
                    <li><a href="index.html" class="btn" style="background-color: #9E6D80; color: white; padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 4px;">Ritorna ai Privati</a></li>
                </ul>`;

    // Costruzione Header
    const headerHTML = `
    <header style="position: relative; z-index: 1000;">
        <div class="container">
            <div class="logo">
                <a href="${isCorporate ? 'aziende.html' : 'index.html'}">
                    ${BRAND_HTML}
                </a>
            </div>
            <button class="menu-toggle" aria-label="Menu">☰</button>
            <nav>
                ${isCorporate ? navCorporate : navPrivati}
            </nav>
        </div>
    </header>`;

    // Regex per rimpiazzare l'intero blocco <header>...</header>
    const headerRegex = /<header[\s\S]*?<\/header>/i;
    
    if (headerRegex.test(content)) {
        content = content.replace(headerRegex, headerHTML);
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`✅ Header sincronizzata in: ${file}`);
    } else {
        console.log(`⚠️  Nessuna header trovata in: ${file}`);
    }
});
