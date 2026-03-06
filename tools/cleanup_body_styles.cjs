const fs = require('fs');
const path = require('path');

const projectRoot = process.cwd();
const files = fs.readdirSync(projectRoot).filter(f => f.endsWith('.html'));

files.forEach(file => {
    const filePath = path.join(projectRoot, file);
    let content = fs.readFileSync(filePath, 'utf8');

    // Rimuove gli stili inline dal tag body per lasciar decidere al CSS
    const bodyRegex = /<body([\s\S]*?)style="[\s\S]*?"/i;
    
    if (bodyRegex.test(content)) {
        content = content.replace(/(<body[^>]*?)\s+style="[^"]*"/gi, '$1');
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`🧹 Pulito body style in: ${file}`);
    }
});
