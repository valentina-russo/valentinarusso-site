import { defineConfig } from 'vite';
import { resolve } from 'path';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    build: {
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'index.html'),
                servizi: resolve(__dirname, 'servizi.html'),
                'amor-proprio': resolve(__dirname, 'amor-proprio.html'),
                relazioni: resolve(__dirname, 'relazioni.html'),
                carriera: resolve(__dirname, 'carriera.html'),
                blog: resolve(__dirname, 'blog.html'),
                articolo: 'articolo.html',
                archivio: resolve(__dirname, 'archivio.html'),
                'chi-sono': resolve(__dirname, 'chi-sono.html'),
                contatti: resolve(__dirname, 'contatti.html'),
                'genera-carta': resolve(__dirname, 'genera-carta.html'),
                'la-mente-innamorata-1': resolve(__dirname, 'la-mente-innamorata-1.html'),
                'la-mente-innamorata-2': resolve(__dirname, 'la-mente-innamorata-2.html'),
                'la-mente-innamorata-3': resolve(__dirname, 'la-mente-innamorata-3.html'),
                'potere-creativo-malinconia': resolve(__dirname, 'potere-creativo-malinconia.html'),
                'relazioni-bisogni-emotivi': resolve(__dirname, 'relazioni-bisogni-emotivi.html'),
                'esplosioni-emotive': resolve(__dirname, 'esplosioni-emotive.html'),
                aziende: resolve(__dirname, 'aziende.html'),
                'aziende-servizi': resolve(__dirname, 'aziende-servizi.html'),
                'aziende-blog': resolve(__dirname, 'aziende-blog.html'),
                'aziende-blog-1': resolve(__dirname, 'aziende-blog-1.html'),
                'aziende-contatti': resolve(__dirname, 'aziende-contatti.html'),
                admin: resolve(__dirname, 'admin/index.html'),
                privacy: resolve(__dirname, 'privacy.html'),
                terms: resolve(__dirname, 'terms.html'),
                'workshop-proposta': resolve(__dirname, 'workshop-proposta.html'),
            },
        },
    },
    plugins: [
        viteStaticCopy({
            targets: [
                {
                    src: 'content/*',
                    dest: 'content'
                },
                {
                    src: 'public/mailer.php',
                    dest: '.'
                },
                {
                    src: 'public/blog_api.php',
                    dest: '.'
                },
                {
                    src: 'public/admin.php',
                    dest: '.'
                },
                {
                    src: 'public/admin_auth.php',
                    dest: '.'
                },
                {
                    src: 'public/ai_meta.php',
                    dest: '.'
                },
                {
                    src: 'public/ai_bold.php',
                    dest: '.'
                },
                {
                    src: 'public/assets/blog/.htaccess',
                    dest: 'assets/blog'
                }
            ]
        })
    ]
});
