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
