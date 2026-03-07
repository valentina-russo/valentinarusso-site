/**
 * Loads and renders blog article cards from /blog_index.json.
 * Supports optional category filtering via data-filter attribute on #blog-container.
 * @param {string|null} categoryFilter - Optional category ('privati' | 'aziende')
 */
export async function initBlog(categoryFilter = null) {
    const blogContainer = document.getElementById('blog-container');
    if (!blogContainer) return;

    // Filtro opzionale per categoria (es. data-filter="aziende")
    const filter = categoryFilter || blogContainer.dataset.filter || null;

    try {
        const response = await fetch('/blog_index.json');
        let articles = await response.json();

        // Filtra per categoria se specificata
        if (filter) {
            articles = articles.filter(a => a.category === filter);
        }

        // Nota: gli articoli con status "draft" sono stati filtrati durante la build
        if (articles.length === 0) {
            blogContainer.innerHTML = '<p class="no-posts">Nessun articolo disponibile al momento.</p>';
            return;
        }

        blogContainer.innerHTML = articles.map(article => `
            <article class="blog-card" data-category="${article.category}" data-geo="${article.geo || ''}">
                <div class="blog-card-image">
                    <img src="${article.image || '/assets/placeholder.jpg'}" alt="${article.title}">
                </div>
                <div class="blog-card-content">
                    <span class="blog-category">${article.category.toUpperCase()}</span>
                    <h3>${article.title}</h3>
                    <p class="blog-date">${new Date(article.date).toLocaleDateString('it-IT')}</p>
                    <p class="blog-excerpt">${article.description}</p>
                    <a href="${article.url}" class="blog-link">Leggi l'articolo →</a>

                    <!-- AEO Metadata (Hidden or Screen Reader) -->
                    <script type="application/ld+json">
                    {
                      "@context": "https://schema.org",
                      "@type": "Article",
                      "headline": "${article.seo.title || article.title}",
                      "description": "${article.seo.desc || article.description}",
                      "image": "${article.image}",
                      "author": { "@type": "Person", "name": "Valentina Russo" },
                      "contentLocation": { "@type": "Place", "name": "${article.geo || 'Italia'}" },
                      "abstract": "${article.aeo || ''}"
                    }
                    </script>
                </div>
            </article>
        `).join('');

    } catch (error) {
        console.error('Errore durante il caricamento del blog:', error);
        blogContainer.innerHTML = '<p class="error">Impossibile caricare gli articoli. Riprova più tardi.</p>';
    }
}

document.addEventListener('DOMContentLoaded', () => initBlog());
