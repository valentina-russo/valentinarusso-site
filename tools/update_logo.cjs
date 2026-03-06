const fs = require('fs');
const path = require('path');

const directoryPath = __dirname;

function updateHeader(dir) {
    fs.readdir(dir, (err, files) => {
        if (err) {
            return console.log('Unable to scan directory: ' + err);
        }

        files.forEach((file) => {
            const filePath = path.join(dir, file);

            // Skip node_modules and .git
            if (filePath.includes('node_modules') || filePath.includes('.git') || filePath.includes('dist')) {
                return;
            }

            fs.stat(filePath, (err, stats) => {
                if (stats.isDirectory()) {
                    updateHeader(filePath);
                } else if (filePath.endsWith('.html')) {
                    let content = fs.readFileSync(filePath, 'utf8');

                    // The target string to replace in private pages
                    const oldStr1 = '<span>VALENTINA RUSSO</span>';
                    const newStr1 = '<span style="display: flex; align-items: center; gap: 0.5rem;">VALENTINA RUSSO <span style="font-size: 0.7em; opacity: 0.8; font-weight: 400; text-transform: none;">- Consulente BG5</span></span>';

                    // The target string to replace in corporate pages (if they have similar)
                    const oldStr2 = '<span style="font-weight: 600;">AREA CORPORATE</span>';
                    const newStr2 = '<span style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">AREA CORPORATE <span style="font-size: 0.7em; opacity: 0.8; font-weight: 400; text-transform: none;">- Valentina Russo</span></span>';


                    let modified = false;

                    if (content.includes(oldStr1)) {
                        content = content.replace(new RegExp(oldStr1, 'g'), newStr1);
                        modified = true;
                    }

                    if (content.includes(oldStr2)) {
                        content = content.replace(new RegExp(oldStr2, 'g'), newStr2);
                        modified = true;
                    }

                    if (modified) {
                        fs.writeFileSync(filePath, content, 'utf8');
                        console.log(`Updated header logo in: ${file}`);
                    }
                }
            });
        });
    });
}

updateHeader(directoryPath);
