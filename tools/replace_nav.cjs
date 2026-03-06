const fs = require('fs');
const path = require('path');

const directoryPath = __dirname;

fs.readdir(directoryPath, function (err, files) {
    if (err) {
        return console.log('Unable to scan directory: ' + err);
    }

    files.forEach(function (file) {
        if (path.extname(file) === '.html') {
            const filePath = path.join(directoryPath, file);
            let content = fs.readFileSync(filePath, 'utf8');

            // Trova il blocco nav in modo flessibile
            const navRegex = /<nav>[\s\S]*?<ul>([\s\S]*?)<\/ul>[\s\S]*?<\/nav>/i;
            const match = content.match(navRegex);

            if (match) {
                let ulContent = match[1];

                // Rimuovi eventuale link Percorso Aziende se già presente (per pulizia)
                ulContent = ulContent.replace(/<li><a href="aziende\.html"[\s\S]*?<\/li>/gi, '');

                // Aggiungiamo il nuovo link alla fine dell'ul
                const newLink = `\n          <li><a href="aziende.html" class="btn" style="background-color: var(--corp-primary); color: white; padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 4px;">Percorso Aziende</a></li>\n        `;

                if (!ulContent.includes('Percorso Aziende')) {
                    const newUlContent = ulContent + newLink;
                    const newNav = match[0].replace(ulContent, newUlContent);
                    const result = content.replace(navRegex, newNav);

                    fs.writeFileSync(filePath, result, 'utf8');
                    console.log('Updated nav in ' + file);
                }
            }
        }
    });
});
