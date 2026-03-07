const fs = require('fs');
const path = require('path');
const matter = require('gray-matter');

const CONTENT_DIRS = [
  { name: 'privati', path: './content/blog-privati' },
  { name: 'aziende', path: './content/blog-aziende' }
];

const OUTPUT_FILE = './public/blog_index.json';

function generateIndex() {
  const allPosts = [];

  CONTENT_DIRS.forEach(dir => {
    const fullPath = path.resolve(dir.path);
    if (!fs.existsSync(fullPath)) return;

    const files = fs.readdirSync(fullPath).filter(file => file.endsWith('.md'));

    files.forEach(file => {
      const filePath = path.join(fullPath, file);
      const fileContent = fs.readFileSync(filePath, 'utf-8');
      const { data } = matter(fileContent);

      // Logica di visualizzazione condizionale: solo se pubblicato
      if (data.status === 'published') {
        allPosts.push({
          id: file.replace('.md', ''),
          category: dir.name,
          title: data.title,
          date: data.date,
          image: data.featured_image,
          description: data.description,
          url: `/articolo.html?id=${file.replace('.md', '')}&category=${dir.name}`,
          // Metadata per SEO/GEO/AEO
          seo: {
            title: data.seo_title,
            desc: data.seo_desc
          },
          geo: data.geo_location,
          aeo: data.aeo_answer
        });
      }
    });
  });

  // Ordina per data (più recenti prima)
  allPosts.sort((a, b) => new Date(b.date) - new Date(a.date));

  fs.writeFileSync(OUTPUT_FILE, JSON.stringify(allPosts, null, 2));
  console.log(`✅ Blog index generato con ${allPosts.length} articoli.`);
}

generateIndex();
