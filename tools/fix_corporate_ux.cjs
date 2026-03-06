const fs = require('fs');
const path = require('path');

const filesToUpdate = [
    'aziende.html',
    'aziende-servizi.html',
    'aziende-blog.html',
    'aziende-contatti.html'
];

const projectRoot = process.cwd();

filesToUpdate.forEach(file => {
    const filePath = path.join(projectRoot, file);
    if (!fs.existsSync(filePath)) {
        console.log(`File not found: ${file}`);
        return;
    }

    let content = fs.readFileSync(filePath, 'utf8');

    // 1. Update Logo
    const logoOldRegex = /<div class="logo">[\s\S]*?<span>AREA CORPORATE<\/span>/i;
    const logoNew = `<div class="logo"><a href="aziende.html"><img src="/assets/logo.png" alt="Valentina Russo Logo" style="height: 50px;"><span style="display: flex; align-items: center; gap: 0.5rem;">VALENTINA RUSSO <span style="font-size: 0.7em; opacity: 0.8; font-weight: 400; text-transform: none;">- Consulente BG5</span></span>`;
    
    // Fallback for slightly different logo structures
    if (content.match(logoOldRegex)) {
        content = content.replace(logoOldRegex, logoNew);
    } else {
        // Try a more generic replacement if the above fails
        content = content.replace(/<span>AREA CORPORATE<\/span>/gi, `<span style="display: flex; align-items: center; gap: 0.5rem;">VALENTINA RUSSO <span style="font-size: 0.7em; opacity: 0.8; font-weight: 400; text-transform: none;">- Consulente BG5</span></span>`);
    }

    // 2. Update Nav Links
    content = content.replace(/>Home Aziende<\/a>/gi, '>Home</a>');
    content = content.replace(/>Servizi Corporate<\/a>/gi, '>Servizi</a>');
    content = content.replace(/>Blog Aziende<\/a>/gi, '>Blog</a>');
    content = content.replace(/>Contatti Aziende<\/a>/gi, '>Contatti</a>');

    // 3. Update Footer Brand text
    content = content.replace(/Consulente BG5 Corporate/gi, 'Consulente BG5 | Corporate Advisor');
    content = content.replace(/<h3>Pagine Aziende<\/h3>/gi, '<h3>Pagine</h3>');

    // 4. Cleanup redundant "Aziende" in some headings if needed
    // (This is more specific, but let's target the ones in the RECAP)
    content = content.replace(/aziende-servizi\.html" class="active" style="color: var\(--accent-color\);">Servizi\s+Corporate<\/a>/gi, 'aziende-servizi.html" class="active" style="color: var(--accent-color);">Servizi</a>');

    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`Updated ${file}`);
});
