import { marked } from 'marked';

document.addEventListener('DOMContentLoaded', async () => {
    const articleContainer = document.getElementById('article-content');
    if (!articleContainer) return;

    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    const category = urlParams.get('category') || 'privati'; // 'privati' o 'aziende'

    if (!id) {
        articleContainer.innerHTML = '<h1>Articolo non trovato</h1><p>ID articolo mancante.</p>';
        return;
    }

    try {
        const folder = category === 'aziende' ? 'blog-aziende' : 'blog-privati';
        const response = await fetch(`/content/${folder}/${id}.md`);
        
        if (!response.ok) throw new Error('Articolo non trovato');
        
        const rawContent = await response.text();
        // Nota: gray-matter lato client potrebbe richiedere un polyfill o una gestione diversa 
        // se non configurata in Vite. Per ora usiamo una regex semplice per il frontmatter.
        const parts = rawContent.split('---');
        const markdown = parts.length >= 3 ? parts.slice(2).join('---') : rawContent;
        
        // Carichiamo l'indice per i metadati (titolo, immagine, etc)
        const indexResponse = await fetch('/blog_index.json');
        const articles = await indexResponse.json();
        const meta = articles.find(a => a.id === id);

        if (meta) {
            document.title = `${meta.seo?.title || meta.title} | Valentina Russo`;

            // Iniezione LD+JSON nell'<head> per SEO/AEO
            const existingLd = document.getElementById('ld-json-article');
            if (existingLd) existingLd.remove();
            const ldScript = document.createElement('script');
            ldScript.id = 'ld-json-article';
            ldScript.type = 'application/ld+json';
            ldScript.textContent = JSON.stringify({
                "@context": "https://schema.org",
                "@type": "Article",
                "headline": meta.seo?.title || meta.title,
                "description": meta.seo?.desc || meta.description,
                "image": meta.image || '/assets/placeholder.jpg',
                "author": { "@type": "Person", "name": "Valentina Russo" },
                "datePublished": meta.date,
                "contentLocation": { "@type": "Place", "name": meta.geo || 'Italia' },
                "abstract": meta.aeo || ''
            });
            document.head.appendChild(ldScript);

            articleContainer.innerHTML = `
                <div class="blog-article-header" style="margin-bottom: 2rem;">
                    <img src="${meta.image || '/assets/placeholder.jpg'}" style="width: 100%; border-radius: 8px; margin-bottom: 2rem;">
                    <span class="blog-category" style="color: var(--accent-color); font-weight: bold;">${meta.category.toUpperCase()}</span>
                    <h1 style="font-size: clamp(2.5rem, 5vw, 4rem); margin: 1rem 0;">${meta.title}</h1>
                    <p class="blog-date" style="opacity: 0.6;">${new Date(meta.date).toLocaleDateString('it-IT')}</p>
                </div>
                <div class="blog-article-body" style="line-height: 1.8; font-size: 1.1rem;">
                    ${marked.parse(markdown)}
                </div>
            `;
        }
    } catch (error) {
        console.error('Errore durante il rendering dell\'articolo:', error);
        articleContainer.innerHTML = '<h1>Errore</h1><p>Impossibile caricare l\'articolo richiesto.</p>';
    }
});
