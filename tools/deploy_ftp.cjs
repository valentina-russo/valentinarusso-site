require('dotenv').config();
const ftp = require("basic-ftp");
const path = require("path");

async function deployStructural() {
    const client = new ftp.Client();
    client.ftp.verbose = true;

    try {
        if (!process.env.ARUBA_FTP_HOST || process.env.ARUBA_FTP_HOST === 'ftp.valentinarussobg5.com' || process.env.ARUBA_FTP_HOST.includes('tuo_utente')) {
            throw new Error("Credenziali FTP non valide. Assicurati di aver rinominato .env.example in .env e di aver inserito i tuoi dati reali Aruba.");
        }

        console.log("Connessione FTP in corso verso: ", process.env.ARUBA_FTP_HOST);
        await client.access({
            host: process.env.ARUBA_FTP_HOST,
            user: process.env.ARUBA_FTP_USER,
            password: process.env.ARUBA_FTP_PASS,
            secure: false // Imposta a true se Aruba supporta FTPS
        });
        console.log("✅ Connesso con successo.");

        const remotePath = process.env.ARUBA_FTP_PATH || "/";
        await client.cd(remotePath);

        console.log("Upload di aziende.html...");
        await client.uploadFrom(path.join(__dirname, "../aziende.html"), "aziende.html");

        console.log("Upload di workshop-proposta-LOCALE.html (su live sarà workshop-proposta.html)...");
        await client.uploadFrom(path.join(__dirname, "../workshop-proposta-LOCALE.html"), "workshop-proposta.html");

        console.log("Upload di src/style.css...");
        await client.ensureDir("src");
        await client.uploadFrom(path.join(__dirname, "../src/style.css"), "style.css");
        await client.cd(remotePath);

        console.log("Upload del template twig Grav aziende_home.html.twig...");
        await client.ensureDir("user/themes/valentina/templates");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/themes/valentina/templates/aziende_home.html.twig"), "aziende_home.html.twig");
        await client.cd(remotePath);

        console.log("Upload del template twig Grav hd_relazionale.html.twig...");
        await client.ensureDir("user/themes/valentina/templates");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/themes/valentina/templates/hd_relazionale.html.twig"), "hd_relazionale.html.twig");
        await client.cd(remotePath);

        console.log("Upload del template twig Grav carta_hd.html.twig...");
        await client.ensureDir("user/themes/valentina/templates");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/themes/valentina/templates/carta_hd.html.twig"), "carta_hd.html.twig");
        await client.cd(remotePath);

        console.log("Upload della pagina carta-hd...");
        await client.ensureDir("user/pages/carta-hd");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/carta-hd/carta_hd.md"), "carta_hd.md");
        await client.cd(remotePath);

        console.log("Upload llms.txt...");
        await client.uploadFrom(path.join(__dirname, "../grav-site/root/llms.txt"), "llms.txt");
        await client.cd(remotePath);

        console.log("Upload bodygraph.svg...");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/assets/bodygraph.svg"), "bodygraph.svg");
        await client.cd(remotePath);

        console.log("Upload chart.svg...");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/assets/chart.svg"), "chart.svg");
        await client.cd(remotePath);

        console.log("Upload la-mente-innamorata-1 (published: false)...");
        await client.ensureDir("user/pages/04.blog/la-mente-innamorata-1");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/04.blog/la-mente-innamorata-1/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("Upload la-mente-innamorata-2 (published: false)...");
        await client.ensureDir("user/pages/04.blog/la-mente-innamorata-2");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/04.blog/la-mente-innamorata-2/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("Upload la-mente-innamorata-3 (published: false)...");
        await client.ensureDir("user/pages/04.blog/la-mente-innamorata-3");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/04.blog/la-mente-innamorata-3/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("Upload bg5-selezione-personale-assumere-design...");
        await client.ensureDir("user/pages/05.aziende/02.blog/bg5-selezione-personale-assumere-design");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/05.aziende/02.blog/bg5-selezione-personale-assumere-design/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("Upload bg5-partnership-scegliere-socio...");
        await client.ensureDir("user/pages/05.aziende/02.blog/bg5-partnership-scegliere-socio");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/05.aziende/02.blog/bg5-partnership-scegliere-socio/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("Upload human-design-azienda-team-leadership...");
        await client.ensureDir("user/pages/05.aziende/02.blog/human-design-azienda-team-leadership");
        await client.uploadFrom(path.join(__dirname, "../grav-site/user/pages/05.aziende/02.blog/human-design-azienda-team-leadership/item.md"), "item.md");
        await client.cd(remotePath);

        console.log("✅ Deploy strutturale completato.");
    }
    catch (err) {
        console.error("❌ Errore durante il deploy:", err);
    }
    finally {
        client.close();
    }
}

deployStructural();
