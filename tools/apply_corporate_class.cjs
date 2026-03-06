const fs = require('fs');
const path = require('path');

const directoryPath = __dirname;
const corporateFiles = ['aziende.html', 'aziende-servizi.html', 'aziende-blog.html', 'aziende-contatti.html'];

corporateFiles.forEach((file) => {
    const filePath = path.join(directoryPath, file);
    if (fs.existsSync(filePath)) {
        let content = fs.readFileSync(filePath, 'utf8');

        // Replace <body> with <body class="corporate">
        content = content.replace(/<body>/g, '<body class="corporate">');

        // Remove the inline style block for :root overrides
        content = content.replace(/<style>[\s\S]*?<\/style>/g, '');

        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`Updated ${file} to use body.corporate`);
    }
});
