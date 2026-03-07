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
        let allArticles = await response.json();

        // Filtra per categoria se specificata
        if (filter) {
            allArticles = allArticles.filter(a => a.category === filter);
        }

        if (allArticles.length === 0) {
            blogContainer.innerHTML = '<p class="no-posts">Nessun articolo disponibile al momento.</p>';
            return;
        }

        blogContainer.innerHTML = ''; // svuota in caso di ricaricamento

        const ITEMS_PER_PAGE = 6;
        let currentIndex = 0;

        const renderBatch = (articlesToRender) => {
            const html = articlesToRender.map(article => `
                <article class="blog-card" data-category="${article.category}" data-geo="${article.geo || ''}">
                    <div class="blog-card-image">
                        <img src="${article.image || '/assets/placeholder.jpg'}" alt="${article.title}" loading="lazy">
                    </div>
                    <div class="blog-card-content">
                        <span class="blog-category">${article.category.toUpperCase()}</span>
                        <h3>${article.title}</h3>
                        <p class="blog-date">${new Date(article.date).toLocaleDateString('it-IT')}</p>
                        <p class="blog-excerpt">${article.description}</p>
                        <a href="${article.url}" class="blog-link">Leggi l'articolo →</a>

                        <!-- AEO Metadata -->
                        <script type="application/ld+json">
                        {
                          "@context": "https://schema.org",
                          "@type": "Article",
                          "headline": "${(article.seo && article.seo.title) ? article.seo.title : article.title}",
                          "description": "${(article.seo && article.seo.desc) ? article.seo.desc : article.description}",
                          "image": "${article.image || ''}",
                          "author": { "@type": "Person", "name": "Valentina Russo" },
                          "contentLocation": { "@type": "Place", "name": "${article.geo || 'Italia'}" },
                          "abstract": "${article.aeo || ''}"
                        }
                        </script>
                    </div>
                </article>
            `).join('');

            blogContainer.insertAdjacentHTML('beforeend', html);
        };

        const loadMore = () => {
            const nextBatch = allArticles.slice(currentIndex, currentIndex + ITEMS_PER_PAGE);
            if (nextBatch.length > 0) {
                renderBatch(nextBatch);
                currentIndex += ITEMS_PER_PAGE;
            }

            if (currentIndex >= allArticles.length) {
                const trigger = document.getElementById('load-more-trigger');
                if (trigger && observer) {
                    observer.unobserve(trigger);
                    trigger.remove();
                }
            }
        };

        // Crea il trigger element
        let triggerDiv = document.getElementById('load-more-trigger');
        if (!triggerDiv) {
            triggerDiv = document.createElement('div');
            triggerDiv.id = 'load-more-trigger';
            triggerDiv.style.width = '100%';
            triggerDiv.style.height = '1px';
            blogContainer.parentNode.insertBefore(triggerDiv, blogContainer.nextSibling);
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                loadMore();
            }
        }, { rootMargin: '300px' });

        observer.observe(triggerDiv);

        // Chiamata iniziale manuale
        loadMore();

    } catch (error) {
        console.error('Errore durante il caricamento del blog:', error);
        blogContainer.innerHTML = '<p class="error">Impossibile caricare gli articoli. Riprova più tardi.</p>';
    }
}

document.addEventListener('DOMContentLoaded', () => initBlog());
