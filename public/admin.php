<?php
// VERSION: 2026-03-08-CMS-V4-FINAL
header('Content-Type: text/html; charset=utf-8');
require_once 'admin_auth.php';

// Percorsi cartelle
$dirs = [
    'privati' => 'content/blog-privati',
    'aziende' => 'content/blog-aziende'
];

$message = '';

// Gestione Eliminazione
if (isset($_GET['delete']) && isAdmin()) {
    $file = $_GET['delete'];
    $cat = $_GET['cat'];
    $path = $dirs[$cat] . '/' . $file . '.md';
    if (file_exists($path)) {
        unlink($path);
        $message = "Articolo eliminato con successo!";
    }
}

// Gestione Salvataggio
if (isset($_POST['save']) && isAdmin()) {
    $id = $_POST['id'] ?: preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $_POST['title'])));
    $category = $_POST['category'];
    $title = $_POST['title'];
    $date_input = $_POST['date'];
    if ($date_input) {
        // Converte datetime-local in formato ISO 8601 o compatibile strtotime
        $date = date('Y-m-d H:i:s', strtotime($date_input));
    } else {
        $date = date('Y-m-d H:i:s');
    }
    $status = $_POST['status'];
    $description = $_POST['description'];
    $body = $_POST['body'];

    // Gestione Immagine
    $image = $_POST['existing_image'] ?: '/assets/placeholder.jpg';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageName = $id . '.' . $ext;
        $uploadDir = 'assets/blog/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadPath = $uploadDir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath);
        $image = '/assets/blog/' . $imageName;
    }

    // Nuovi campi
    $author = $_POST['author'] ?: 'Valentina Russo';
    $tags = $_POST['tags'] ?: '';
    $image_alt = $_POST['image_alt'] ?: $title;
    $faq_json = $_POST['faq_json'] ?: '[]';

    // Nuovi campi V4
    $focus_point = $_POST['focus_point'] ?: 'center';
    $image_title = $_POST['image_title'] ?: '';
    $image_caption = $_POST['image_caption'] ?: '';
    $image_desc = $_POST['image_desc'] ?: '';

    // Protezione virgolette per YAML
    $title_esc = addslashes($title);
    $desc_esc = addslashes($description);

    $content = "---\n";
    $content .= "title: \"$title_esc\"\n";
    $content .= "date: $date\n";
    $content .= "status: \"$status\"\n";
    $content .= "author: \"$author\"\n";
    $content .= "tags: \"$tags\"\n";
    $content .= "featured_image: \"$image\"\n";
    $content .= "image_focus: \"$focus_point\"\n";
    $content .= "image_title: \"$image_title\"\n";
    $content .= "image_caption: \"$image_caption\"\n";
    $content .= "image_desc: \"$image_desc\"\n";
    $content .= "image_alt: \"$image_alt\"\n";
    $content .= "description: \"$desc_esc\"\n";
    $content .= "seo_title: \"" . addslashes($_POST['seo_title'] ?? '') . "\"\n";
    $content .= "seo_desc: \"" . addslashes($_POST['seo_desc'] ?? '') . "\"\n";
    $content .= "geo_location: \"" . addslashes($_POST['geo_location'] ?? '') . "\"\n";
    $content .= "aeo_answer: \"" . addslashes($_POST['aeo_answer'] ?? '') . "\"\n";
    $content .= "faq: $faq_json\n";
    $content .= "---\n\n";
    $content .= $body;

    file_put_contents($dirs[$category] . '/' . $id . '.md', $content);
    $message = "Articolo salvato con successo!";
}

// Lettura articoli
$articles = [];
$totalFiles = 0;
foreach ($dirs as $cat => $path) {
    if (is_dir($path)) {
        $files = scandir($path);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                $totalFiles++;
                $id = pathinfo($file, PATHINFO_FILENAME);
                $content = file_get_contents($path . '/' . $file);

                $data = [
                    'id' => $id,
                    'category' => $cat,
                    'title' => $id,
                    'date' => date('Y-m-d', filemtime($path . '/' . $file)),
                    'status' => 'draft',
                    'featured_image' => '/assets/placeholder.jpg',
                    'body' => ''
                ];

                // Regex ultra-robusta per il frontmatter (V5.4)
                if (preg_match('/^---\s*(.*?)\n---\s*/s', $content, $matches)) {
                    $lines = explode("\n", str_replace("\r", "", $matches[1]));
                    foreach ($lines as $line) {
                        if (trim($line) === '')
                            continue;
                        $parts = explode(':', $line, 2);
                        if (count($parts) === 2) {
                            $k = trim($parts[0]);
                            $v = trim(trim($parts[1]), '"\' ');
                            $data[$k] = $v;
                        }
                    }
                    $data['body'] = explode('---', $content, 3)[2] ?? '';

                    // Assicurati che la data sia formattata bene per il JS
                    if (isset($data['date'])) {
                        $data['date'] = date('c', strtotime($data['date']));
                    }
                }
                $articles[] = $data;
            }
        }
    }
}

// Ordina per data decrescente
usort($articles, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platinum Admin | Valentina Russo</title>
    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- EasyMDE -->
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #B68397;
            --primary-light: rgba(182, 131, 151, 0.1);
            --secondary: #5DAEB1;
            --accent: #E6A756;
            --bg-color: #FAF7F5;
            --card-bg: rgba(255, 255, 255, 0.7);
            --text-main: #2D2926;
            --text-muted: #7A7570;
            --sidebar-width: 280px;
            --glass-border: rgba(255, 255, 255, 0.4);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Outfit', sans-serif;
            --radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-color);
            background-image:
                radial-gradient(circle at 10% 20%, rgba(182, 131, 151, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(93, 174, 177, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-body);
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
        }

        /* Login Screen */
        .login-overlay {
            position: fixed;
            inset: 0;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            padding: 50px;
            border-radius: 30px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-right: 1px solid var(--glass-border);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .logo-area {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 50px;
            padding: 0 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-menu {
            list-style: none;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 20px rgba(182, 131, 151, 0.2);
            transform: translateY(-2px);
        }

        .nav-link i {
            width: 20px;
            height: 20px;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 60px;
            max-width: 1400px;
            width: 100%;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 50px;
        }

        .welcome-msg h1 {
            font-family: var(--font-heading);
            font-size: 2.8rem;
            margin-bottom: 10px;
        }

        .welcome-msg p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        /* Grid Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: var(--radius);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-info h4 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .stat-info span {
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Blog Grid */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }

        .post-card {
            background: var(--card-bg);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            transition: all 0.4s;
            position: relative;
        }

        .post-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .post-thumb {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .post-body {
            padding: 25px;
        }

        .post-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .post-title {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--text-main);
        }

        .card-actions {
            display: flex;
            gap: 10px;
            padding: 0 25px 25px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid #ddd;
            color: var(--text-muted);
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Form / Editor Overlay */
        .editor-overlay {
            position: fixed;
            inset: 0;
            background: var(--bg-color);
            z-index: 1000;
            display: none;
            flex-direction: column;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
            }

            to {
                transform: translateY(0);
            }
        }

        .editor-header {
            padding: 20px 60px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .editor-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            flex-grow: 1;
            overflow: hidden;
        }

        .editor-main {
            padding: 40px 60px;
            overflow-y: auto;
        }

        .editor-sidebar {
            padding: 40px;
            background: rgba(0, 0, 0, 0.02);
            border-left: 1px solid var(--glass-border);
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #EAE5E1;
            font-family: var(--font-body);
            font-size: 1rem;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .badge-published {
            background: #dcfce7;
            color: #166534;
        }

        .badge-draft {
            background: #fef9c3;
            color: #854d0e;
        }

        /* Pulsante AI */
        .btn-ai {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-ai:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-ai:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(182, 131, 151, 0.2);
            border-radius: 10px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 40px 10px;
            }

            .logo-area span,
            .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 80px;
                padding: 40px;
            }

            .editor-container {
                grid-template-columns: 1fr;
            }

            .editor-sidebar {
                height: 300px;
            }
        }
    </style>
</head>

<body>
    <?php if (!isAdmin()): ?>
        <div class="login-overlay">
            <div class="login-card">
                <div class="logo-area" style="justify-content: center; margin-bottom: 30px;">
                    <i data-lucide="sparkles"></i>
                    Valentina Russo
                </div>
                <h2 style="font-family: var(--font-heading); margin-bottom: 10px;">Benvenuta</h2>
                <p style="color: var(--text-muted); margin-bottom: 40px;">Accedi per curare il tuo mondo digitale.</p>
                <form method="POST">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="••••••••" required autofocus
                            style="text-align: center; font-size: 1.5rem; letter-spacing: 4px; padding: 20px;">
                    </div>
                    <?php if (isset($error))
                        echo "<p style='color:var(--primary); margin-bottom: 20px;'>$error</p>"; ?>
                    <button type="submit" name="login" class="btn btn-primary" style="width: 100%; padding: 18px;">Entra
                        nella Dashboard</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="logo-area">
                <i data-lucide="sparkles"></i>
                <span>Platinum v5</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" onclick="showDashboard()">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="openNewEditor()">
                        <i data-lucide="plus-circle"></i>
                        <span>Nuova Ispirazione</span>
                    </a>
                </li>
            </ul>
            <div class="user-area" style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--glass-border);">
                <a href="?logout=1" class="nav-link">
                    <i data-lucide="log-out"></i>
                    <span>Disconnetti</span>
                </a>
            </div>
        </nav>

        <!-- Main Workspace -->
        <main class="main-content" id="dashboard-view">
            <div class="dashboard-header">
                <div class="welcome-msg">
                    <h1>Il tuo Giardino, Valentina</h1>
                    <p>Cosa vorresti trasmettere oggi alle anime del blog?</p>
                </div>
                <button class="btn btn-primary" onclick="openNewEditor()">
                    <i data-lucide="pen-tool"></i> Nuova Ispirazione
                </button>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i data-lucide="file-text"></i></div>
                    <div class="stat-info">
                        <h4>Articoli Totali</h4>
                        <span><?php echo $totalFiles; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(93, 174, 177, 0.1); color: var(--secondary);"><i
                            data-lucide="globe"></i></div>
                    <div class="stat-info">
                        <h4>Online</h4>
                        <span><?php echo count(array_filter($articles, fn($a) => $a['status'] === 'published')); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(230, 167, 86, 0.1); color: var(--accent);"><i
                            data-lucide="calendar"></i></div>
                    <div class="stat-info">
                        <h4>Ultimo Aggiornamento</h4>
                        <span><?php echo !empty($articles) ? date('d M', strtotime($articles[0]['date'])) : '-'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Blog Archive -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 style="margin: 0; font-size: 1.5rem;">Archivio Recente</h2>
                <div class="filter-group" style="background: rgba(0,0,0,0.05); padding: 5px; border-radius: 12px; display: flex; gap: 5px;">
                    <button onclick="filterArticles('all')" class="btn btn-filter active" id="btn-filter-all" style="padding: 8px 15px; font-size: 0.75rem;">Tutti</button>
                    <button onclick="filterArticles('privati')" class="btn btn-filter" id="btn-filter-privati" style="padding: 8px 15px; font-size: 0.75rem;">Privati</button>
                    <button onclick="filterArticles('aziende')" class="btn btn-filter" id="btn-filter-aziende" style="padding: 8px 15px; font-size: 0.75rem;">Aziende</button>
                </div>
            </div>
            <div class="blog-grid">
                <?php foreach ($articles as $a): ?>
                    <div class="post-card" data-category="<?php echo $a['category']; ?>">
                        <img src="<?php echo $a['featured_image']; ?>" class="post-thumb" alt="">
                        <div class="post-body">
                            <div class="post-meta">
                                <span
                                    class="badge badge-<?php echo $a['status']; ?>"><?php echo strtoupper($a['status']); ?></span>
                                <span><?php echo date('d/m/Y', strtotime($a['date'])); ?></span>
                            </div>
                            <h3 class="post-title"><?php echo htmlspecialchars($a['title']); ?></h3>
                            <p
                                style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; height: 3.2em; overflow: hidden;">
                                <?php echo htmlspecialchars($a['description']); ?>
                            </p>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-outline" style="flex: 1;"
                                onclick="editPost('<?php echo $a['id']; ?>')">Modifica</button>
                            <a href="?delete=<?php echo $a['id']; ?>&cat=<?php echo $a['category']; ?>" class="btn btn-danger"
                                onclick="return confirm('Eliminare definitivamente?')">
                                <i data-lucide="trash-2" style="width: 16px;"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

        <!-- Editor Workspace (Overlay) -->
        <div class="editor-overlay" id="editor-workspace">
            <form method="POST" enctype="multipart/form-data" id="main-editor-form"
                style="height: 100%; display: flex; flex-direction: column;">
                <input type="hidden" name="id" id="form-id">
                <input type="hidden" name="existing_image" id="form-existing-image">
                <input type="hidden" name="save" value="1">

                <div class="editor-header">
                    <div>
                        <h2 id="editor-title-display">Nuova Ispirazione</h2>
                        <span style="font-size: 0.8rem; color: var(--text-muted);" id="editor-subtitle">Bozza automatica
                            salvata</span>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="btn btn-outline" onclick="closeEditor()">Chiudi</button>
                        <button type="button" class="btn btn-ai" id="btn-generate-meta" onclick="generateMetadata()">
                            <i data-lucide="sparkles" style="width:15px;height:15px;"></i> Genera Metadati AI
                        </button>
                        <button type="button" class="btn btn-primary" onclick="submitEditorForm()">Salva e Pubblica</button>
                    </div>
                </div>

                <div class="editor-container">
                    <div class="editor-main">

                        <div class="form-group">
                            <input type="text" name="title" id="form-title" placeholder="Titolo dell'ispirazione..."
                                style="font-family: var(--font-heading); font-size: 2.5rem; border: none; background: transparent; padding: 0; outline: none;"
                                required>
                        </div>

                        <div class="form-group">
                            <textarea name="description" id="form-description"
                                placeholder="Scrivi un breve estratto che incuriosisca..." rows="2"
                                style="font-size: 1.2rem; color: var(--text-muted); border: none; background: transparent; padding: 0; outline: none;"></textarea>
                        </div>

                        <div class="form-group" style="margin-top: 40px;">
                            <textarea id="form-body"></textarea>
                        </div>
                    </div>
                    <aside class="editor-sidebar">
                        <h3 style="font-size: 0.9rem; text-transform: uppercase; margin-bottom: 25px;">Configurazione</h3>

                        <div class="form-group">
                            <label>Canale Blog</label>
                            <select name="category" id="form-category">
                                <option value="privati">Percorso Individuale</option>
                                <option value="aziende">Corporate & Leadership</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Stato</label>
                            <select name="status" id="form-status">
                                <option value="published">Pubblicato</option>
                                <option value="draft">Bozza</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Autore</label>
                            <input type="text" name="author" id="form-author" placeholder="Valentina Russo">
                        </div>

                        <div class="form-group">
                            <label>Tag (separati da virgola)</label>
                            <input type="text" name="tags" id="form-tags" placeholder="Human Design, BG5...">
                        </div>

                        <div class="form-group">
                            <label>Data Pubblicazione</label>
                            <input type="datetime-local" name="date" id="form-date">
                        </div>

                        <div class="form-group">
                            <label>Immagine Copertina</label>
                            <div id="image-preview-area" style="margin-bottom: 10px;">
                                <img id="img-preview" src="/assets/placeholder.jpg"
                                    style="width: 100%; border-radius: 12px; height: 150px; object-fit: cover;">
                            </div>
                            <input type="file" name="image" id="form-image-file" accesskey="i">
                        </div>

                        <div class="form-group">
                            <label>Focus Immagine (CSS)</label>
                            <input type="text" name="focus_point" id="form-focus-point" placeholder="center">
                        </div>

                        <!-- Metadati Immagine -->
                        <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.03); border-radius: 10px;">
                            <h4 style="font-size: 0.7rem; text-transform: uppercase; margin-bottom: 10px;">Metadati Immagine
                            </h4>
                            <div class="form-group">
                                <label style="font-size: 0.7rem;">Titolo Immagine</label>
                                <input type="text" name="image_title" id="form-image-title" style="padding: 8px;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.7rem;">Alt Text</label>
                                <input type="text" name="image_alt" id="form-image-alt" style="padding: 8px;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.7rem;">Didascalia</label>
                                <input type="text" name="image_caption" id="form-image-caption" style="padding: 8px;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.7rem;">Descrizione SEO immagine</label>
                                <textarea name="image_desc" id="form-image-desc" rows="2" style="padding: 8px; font-size: 0.85rem;"></textarea>
                            </div>
                        </div>

                        <!-- SEO Section (Accordion style simple) -->
                        <div style="margin-top: 40px;">
                            <h4 style="font-size: 0.8rem; text-transform: uppercase; margin-bottom: 15px; cursor: pointer;"
                                onclick="toggleSEO()">SEO & Digital Identity <i data-lucide="chevron-down"
                                    style="width: 14px; vertical-align: middle;"></i></h4>
                            <div id="seo-fields" style="display: none;">
                                <div class="form-group">
                                    <label>SEO Title</label>
                                    <input type="text" name="seo_title" id="form-seo-title">
                                </div>
                                <div class="form-group">
                                    <label>SEO Description</label>
                                    <textarea name="seo_desc" id="form-seo-desc" rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>GEO Location</label>
                                    <input type="text" name="geo_location" id="form-geo-location"
                                        placeholder="Milano, Italia">
                                </div>
                                <div class="form-group">
                                    <label>AEO Essential Info</label>
                                    <textarea name="aeo_answer" id="form-aeo-answer" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>FAQ (JSON)</label>
                                    <textarea name="faq_json" id="form-faq-json" rows="2" placeholder="[]"></textarea>
                                </div>
                            </div>
                    </aside>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- EasyMDE & Marked -->
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        /* Personalizzazione EasyMDE per Platinum Look */
        .EasyMDEContainer .CodeMirror {
            border-radius: 12px;
            border: 1px solid #EAE5E1;
            font-family: var(--font-body);
            font-size: 1.1rem;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
        }

        .editor-toolbar {
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            border: 1px solid #EAE5E1;
            background: white;
            opacity: 0.9;
        }

        /* Fix Grassetti e Header nell'editor */
        .CodeMirror-code .cm-strong {
            font-weight: 800 !important;
            color: var(--primary-color);
        }

        .CodeMirror-code .cm-header {
            font-family: var(--font-heading);
            color: var(--primary-color);
        }

        .editor-statusbar {
            display: none;
        }
    </style>
    <script>
        let editorInstance;
        const registry = <?php echo json_encode($articles); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();

            editorInstance = new EasyMDE({
                element: document.getElementById('form-body'),
                spellChecker: false,
                autosave: { enabled: false },
                status: false,
                minHeight: "450px",
                placeholder: "Scrivi qui il cuore della tua ispirazione...",
                toolbar: ["bold", "italic", "heading", "|", "quote", "unordered-list", "ordered-list", "|", "link", "image", "|", "preview", "side-by-side", "fullscreen"]
            });

            // Preview immagine live
            document.getElementById('form-image-file')?.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (re) {
                        document.getElementById('img-preview').src = re.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        function showDashboard() {
            document.getElementById('dashboard-view').style.display = 'block';
            document.getElementById('editor-workspace').style.display = 'none';
        }

        function openNewEditor() {
            resetAdminForm();
            document.getElementById('editor-workspace').style.display = 'flex';
            document.getElementById('editor-title-display').innerText = "Nuova Ispirazione";
            lucide.createIcons();
        }

        function editPost(id) {
            const art = registry.find(a => a.id === id);
            if (!art) return;

            document.getElementById('form-id').value = art.id;
            document.getElementById('form-title').value = art.title || '';
            document.getElementById('form-description').value = art.description || '';
            document.getElementById('form-category').value = art.category;
            document.getElementById('form-status').value = art.status || 'published';
            document.getElementById('form-existing-image').value = art.featured_image || '';
            document.getElementById('img-preview').src = art.featured_image || '/assets/placeholder.jpg';
            document.getElementById('form-focus-point').value = art.image_focus || 'center';
            document.getElementById('form-author').value = art.author || 'Valentina Russo';
            document.getElementById('form-tags').value = art.tags || '';
            document.getElementById('form-seo-title').value = art.seo_title || '';
            document.getElementById('form-seo-desc').value = art.seo_desc || '';
            document.getElementById('form-geo-location').value = art.geo_location || '';
            document.getElementById('form-aeo-answer').value = art.aeo_answer || '';
            document.getElementById('form-faq-json').value = art.faq || '[]';
            document.getElementById('form-image-title').value = art.image_title || '';
            document.getElementById('form-image-alt').value = art.image_alt || '';
            document.getElementById('form-image-caption').value = art.image_caption || '';
            document.getElementById('form-image-desc').value = art.image_desc || '';

            // Gestione data per datetime-local
            if (art.date) {
                const date = new Date(art.date);
                const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                document.getElementById('form-date').value = localDate;
            }

            if (editorInstance) {
                editorInstance.value(art.body || '');
            }

            document.getElementById('editor-workspace').style.display = 'flex';
            document.getElementById('editor-title-display').innerText = "Modifica Ispirazione";
            window.scrollTo(0, 0);
        }

        // Gestione messaggi PHP
        <?php if ($message): ?>
            document.addEventListener('DOMContentLoaded', () => {
                const toast = document.createElement('div');
                toast.style.cssText = "position: fixed; bottom: 30px; right: 30px; background: var(--secondary); color: white; padding: 15px 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); z-index: 10001; animation: slideUp 0.5s forwards;";
                toast.innerHTML = `<i data-lucide="check-circle" style="width:18px; vertical-align:middle; margin-right:10px;"></i> <?php echo $message; ?>`;
                document.body.appendChild(toast);
                lucide.createIcons();
                setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }, 4000);
            });
        <?php endif; ?>

        function resetAdminForm() {
            document.getElementById('form-id').value = '';
            document.getElementById('form-title').value = '';
            document.getElementById('form-description').value = '';
            document.getElementById('form-existing-image').value = '';
            document.getElementById('img-preview').src = '/assets/placeholder.jpg';
            if (editorInstance) editorInstance.value('');
        }

        function closeEditor() {
            document.getElementById('editor-workspace').style.display = 'none';
        }

        function submitEditorForm() {
            if (editorInstance) {
                // Sincronizza EasyMDE con la textarea originale
                const content = editorInstance.value();

                // Rimuovi eventuali input body precedenti
                const oldBody = document.querySelector('input[name="body"]');
                if (oldBody) oldBody.remove();

                const bodyInput = document.createElement('input');
                bodyInput.type = 'hidden';
                bodyInput.name = 'body';
                bodyInput.value = content;
                document.getElementById('main-editor-form').appendChild(bodyInput);
            }
            document.getElementById('main-editor-form').submit();
        }

        function showToast(message, color) {
            color = color || 'var(--secondary)';
            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:30px;right:30px;background:' + color + ';color:white;padding:15px 30px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.1);z-index:10001;font-family:var(--font-body);font-size:0.95rem;';
            toast.innerHTML = message;
            document.body.appendChild(toast);
            setTimeout(function() { toast.style.opacity = '0'; toast.style.transition = 'opacity .4s'; setTimeout(function() { toast.remove(); }, 400); }, 4000);
        }

        async function generateMetadata() {
            var body = editorInstance ? editorInstance.value() : '';
            if (body.trim().length < 80) {
                alert('Scrivi prima il testo dell\'articolo (almeno qualche paragrafo) prima di generare i metadati.');
                return;
            }
            var btn = document.getElementById('btn-generate-meta');
            var originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" style="width:15px;height:15px;"></i> Generazione...';
            lucide.createIcons();

            try {
                var formData = new FormData();
                formData.append('body', body);
                formData.append('title', document.getElementById('form-title').value || '');
                formData.append('category', document.getElementById('form-category').value || 'privati');

                var response = await fetch('ai_meta.php', { method: 'POST', body: formData });
                var data = await response.json();

                if (data.error) {
                    alert('Errore: ' + data.error);
                    return;
                }

                // Compila i campi — MAI tocca il corpo articolo (editorInstance)
                if (data.description)    document.getElementById('form-description').value    = data.description;
                if (data.seo_title)      document.getElementById('form-seo-title').value      = data.seo_title;
                if (data.seo_desc)       document.getElementById('form-seo-desc').value       = data.seo_desc;
                if (data.geo_location)   document.getElementById('form-geo-location').value   = data.geo_location;
                if (data.aeo_answer)     document.getElementById('form-aeo-answer').value     = data.aeo_answer;
                if (data.faq_json)       document.getElementById('form-faq-json').value       = data.faq_json;
                if (data.image_title)    document.getElementById('form-image-title').value    = data.image_title;
                if (data.image_alt)      document.getElementById('form-image-alt').value      = data.image_alt;
                if (data.image_caption)  document.getElementById('form-image-caption').value  = data.image_caption;
                if (data.image_desc)     document.getElementById('form-image-desc').value     = data.image_desc;

                // Apri sezione SEO se era chiusa
                document.getElementById('seo-fields').style.display = 'block';

                showToast('✨ Metadati generati con successo!', 'linear-gradient(135deg,#667eea,#764ba2)');

            } catch (err) {
                alert('Errore di rete: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                lucide.createIcons();
            }
        }

        function filterArticles(category) {
            const cards = document.querySelectorAll('.post-card');
            const buttons = document.querySelectorAll('.btn-filter');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('btn-filter-' + category);
            if(activeBtn) activeBtn.classList.add('active');

            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
    <style>
        .btn-filter {
            background: transparent;
            color: var(--text-muted);
            border: none;
            box-shadow: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-filter.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
    </style>

        function toggleSEO() {
            const el = document.getElementById('seo-fields');
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>

</html>