const fs = require('fs');
const path = require('path');

const filesToUpdate = [
    'aziende-servizi.html',
    'aziende-blog.html',
    'aziende-contatti.html'
];

const projectRoot = process.cwd();

filesToUpdate.forEach(file => {
    const filePath = path.join(projectRoot, file);
    if (!fs.existsSync(filePath)) return;

    let content = fs.readFileSync(filePath, 'utf8');

    // 1. Update Body for full-page background
    const bodyOld = /<body class="corporate">/i;
    const bodyNew = `<body class="corporate" style="
    background-color: #f8fafc;
    background-image: 
        radial-gradient(circle at 15% 20%, rgba(49, 130, 206, 0.18) 0%, transparent 45%),
        radial-gradient(circle at 85% 50%, rgba(30, 58, 95, 0.12) 0%, transparent 50%),
        radial-gradient(circle at 50% 85%, rgba(49, 130, 206, 0.14) 0%, transparent 45%),
        radial-gradient(circle at 10% 90%, rgba(30, 58, 95, 0.10) 0%, transparent 35%);
    background-attachment: fixed;
    background-size: cover;
">`;
    content = content.replace(bodyOld, bodyNew);

    // 2. Update Footer to white
    const footerOld = /<footer>/i;
    const footerNew = `<footer style="background-color: var(--white); border-top: 1px solid var(--soft-gray);">`;
    content = content.replace(footerOld, footerNew);

    // 3. Make main sections transparent
    content = content.replace(/<section class="section-padding">/gi, '<section class="section-padding" style="background: transparent;">');
    content = content.replace(/style="background-color: var\(--corp-bg\);"/gi, 'style="background: transparent;"');

    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`Aligned background and footer in ${file}`);
});
