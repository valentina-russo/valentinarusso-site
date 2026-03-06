const fs = require('fs');
const path = require('path');

const directoryPath = __dirname;

const newFooter = `  <footer>
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <img src="/assets/logo.png" alt="Valentina Russo Logo" style="height: 40px; margin-bottom: var(--space-sm);">
          <p class="text-small">Consulente BG5 | Human Design Analyst</p>
        </div>
        <div class="footer-links">
          <h3>Pagine</h3>
          <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="chi-sono.html">Chi Sono</a></li>
            <li><a href="servizi.html">Servizi</a></li>
            <li><a href="blog.html">Blog</a></li>
            <li><a href="contatti.html">Contatti</a></li>
          </ul>
        </div>
        <div class="footer-links">
          <h3>Legale</h3>
          <ul>
            <li><a href="privacy.html">Privacy Policy</a></li>
            <li><a href="terms.html">Termini e Condizioni</a></li>
          </ul>
        </div>
        <div class="footer-links">
          <h3>Contatti</h3>
          <ul>
            <li><a href="https://www.instagram.com/valentinarussobg5/" target="_blank">Instagram</a></li>
            <li><a href="mailto:info@valentinarussobg5.com">Email</a></li>
          </ul>
        </div>
      </div>
      <div style="margin-top: var(--space-lg); border-top: 1px solid #dcd7d3; padding-top: var(--space-md); font-size: 0.75rem; text-align: center;">
        &copy; 2025 Valentina Russo. Tutti i diritti riservati.
      </div>
    </div>
  </footer>`;

fs.readdir(directoryPath, function (err, files) {
    if (err) {
        return console.log('Unable to scan directory: ' + err);
    }

    files.forEach(function (file) {
        if (path.extname(file) === '.html') {
            const filePath = path.join(directoryPath, file);
            let content = fs.readFileSync(filePath, 'utf8');

            // Replace footer
            const result = content.replace(/<footer[\s\S]*?<\/footer>/i, newFooter);

            if (content !== result) {
                fs.writeFileSync(filePath, result, 'utf8');
                console.log('Updated footer in ' + file);
            }
        }
    });
});
