<?php
// Script perbaikan untuk masalah kategorisasi
include 'includes/db.php';

// Aktifkan tampilan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head>";
echo "<title>Fix Categories</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    h1 { color: #0066cc; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .result { margin: 20px 0; padding: 15px; border-radius: 5px; background: #f9f9f9; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .btn { display: inline-block; padding: 8px 16px; margin: 5px 0; 
           text-decoration: none; color: white; border-radius: 4px; }
    .btn-primary { background-color: #0066cc; }
    .btn-warning { background-color: #ff9800; }
    .btn-danger { background-color: #f44336; }
    .btn:hover { opacity: 0.9; }
</style>";
echo "</head><body>";
echo "<h1>Perbaikan Kategori Posts</h1>";

// Cek apakah ada aksi yang dipilih
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Step 1: Periksa tabel kategori dan post_categories
function checkTables($pdo) {
    echo "<h2>Pemeriksaan Tabel</h2>";
    
    // Cek tabel categories
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        $categoryCount = $stmt->fetchColumn();
        echo "<p class='success'>✅ Tabel categories ditemukan dengan $categoryCount kategori.</p>";
        
        // Tampilkan beberapa kategori
        $stmt = $pdo->query("SELECT id, name FROM categories LIMIT 10");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table><tr><th>ID</th><th>Name</th></tr>";
        foreach ($categories as $cat) {
            echo "<tr><td>{$cat['id']}</td><td>{$cat['name']}</td></tr>";
        }
        echo "</table>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error dengan tabel categories: " . $e->getMessage() . "</p>";
    }
    
    // Cek tabel post_categories
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM post_categories");
        $relationCount = $stmt->fetchColumn();
        echo "<p class='success'>✅ Tabel post_categories ditemukan dengan $relationCount relasi.</p>";
        
        // Tampilkan beberapa relasi
        $stmt = $pdo->query("
            SELECT pc.id, pc.post_id, p.content, pc.category_id, c.name as category_name
            FROM post_categories pc
            JOIN posts p ON pc.post_id = p.id
            JOIN categories c ON pc.category_id = c.id
            LIMIT 10
        ");
        $relations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($relations) > 0) {
            echo "<table>
                <tr><th>ID</th><th>Post ID</th><th>Content</th><th>Category ID</th><th>Category Name</th></tr>";
            foreach ($relations as $rel) {
                echo "<tr>
                    <td>{$rel['id']}</td>
                    <td>{$rel['post_id']}</td>
                    <td>" . htmlspecialchars(substr($rel['content'], 0, 50)) . "...</td>
                    <td>{$rel['category_id']}</td>
                    <td>{$rel['category_name']}</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Tidak ada relasi post-category yang ditemukan.</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error dengan tabel post_categories: " . $e->getMessage() . "</p>";
    }
}

// Step 2: Periksa kategori Technology dan Politics
function checkSpecificCategories($pdo) {
    echo "<h2>Pemeriksaan Kategori Spesifik</h2>";
    
    // Periksa kategori Technology
    $stmt = $pdo->query("SELECT id FROM categories WHERE name = 'Technology'");
    $techId = $stmt->fetchColumn();
    
    if ($techId) {
        echo "<p class='success'>✅ Kategori Technology ditemukan dengan ID: $techId</p>";
        
        // Check posts with this category
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM post_categories 
            WHERE category_id = ?
        ");
        $stmt->execute([$techId]);
        $postCount = $stmt->fetchColumn();
        
        echo "<p>Ada $postCount post dengan kategori Technology</p>";
    } else {
        echo "<p class='warning'>⚠️ Kategori Technology tidak ditemukan.</p>";
    }
    
    // Periksa kategori Politics
    $stmt = $pdo->query("SELECT id FROM categories WHERE name = 'Politics'");
    $politicsId = $stmt->fetchColumn();
    
    if ($politicsId) {
        echo "<p class='success'>✅ Kategori Politics ditemukan dengan ID: $politicsId</p>";
        
        // Check posts with this category
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM post_categories 
            WHERE category_id = ?
        ");
        $stmt->execute([$politicsId]);
        $postCount = $stmt->fetchColumn();
        
        echo "<p>Ada $postCount post dengan kategori Politics</p>";
    } else {
        echo "<p class='warning'>⚠️ Kategori Politics tidak ditemukan.</p>";
    }
    
    return ['techId' => $techId, 'politicsId' => $politicsId];
}

// Step 3: Buat kategori yang tidak ada
function createMissingCategories($pdo, $categories) {
    echo "<h2>Pembuatan Kategori yang Hilang</h2>";
    
    $techId = $categories['techId'];
    $politicsId = $categories['politicsId'];
    
    // Buat kategori Technology jika belum ada
    if (!$techId) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute(['Technology']);
            $techId = $pdo->lastInsertId();
            echo "<p class='success'>✅ Kategori Technology berhasil dibuat dengan ID: $techId</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Gagal membuat kategori Technology: " . $e->getMessage() . "</p>";
        }
    }
    
    // Buat kategori Politics jika belum ada
    if (!$politicsId) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute(['Politics']);
            $politicsId = $pdo->lastInsertId();
            echo "<p class='success'>✅ Kategori Politics berhasil dibuat dengan ID: $politicsId</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Gagal membuat kategori Politics: " . $e->getMessage() . "</p>";
        }
    }
    
    return ['techId' => $techId, 'politicsId' => $politicsId];
}

// Step 4: Kategorikan post yang belum memiliki kategori
function categorizeUncategorizedPosts($pdo, $categories) {
    echo "<h2>Pengkategorian Post</h2>";
    
    $techId = $categories['techId'];
    $politicsId = $categories['politicsId'];
    
    // Dapatkan semua post
    $stmt = $pdo->query("SELECT id, content FROM posts");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Menemukan " . count($posts) . " post untuk dikategorikan</p>";
    
    $techCount = 0;
    $politicsCount = 0;
    
    foreach ($posts as $post) {
        $content = strtolower($post['content']);
        $postId = $post['id'];
        
        // Cek untuk kategori Technology
        if (strpos($content, 'teknologi') !== false || 
            strpos($content, 'ai') !== false || 
            strpos($content, 'data') !== false ||
            strpos($content, 'tech') !== false) {
            
            // Verifikasi apakah relasi sudah ada
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM post_categories 
                WHERE post_id = ? AND category_id = ?
            ");
            $stmt->execute([$postId, $techId]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO post_categories (post_id, category_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$postId, $techId]);
                    $techCount++;
                } catch (PDOException $e) {
                    echo "<p class='error'>❌ Error saat menambahkan kategori Technology ke post ID $postId: " . $e->getMessage() . "</p>";
                }
            }
        }
        
        // Cek untuk kategori Politics
        if (strpos($content, 'politik') !== false || 
            strpos($content, 'negara') !== false || 
            strpos($content, 'pemilu') !== false ||
            strpos($content, 'presiden') !== false || 
            strpos($content, 'oposisi') !== false) {
            
            // Verifikasi apakah relasi sudah ada
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM post_categories 
                WHERE post_id = ? AND category_id = ?
            ");
            $stmt->execute([$postId, $politicsId]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO post_categories (post_id, category_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$postId, $politicsId]);
                    $politicsCount++;
                } catch (PDOException $e) {
                    echo "<p class='error'>❌ Error saat menambahkan kategori Politics ke post ID $postId: " . $e->getMessage() . "</p>";
                }
            }
        }
    }
    
    echo "<p class='success'>✅ Menambahkan kategori Technology ke $techCount post</p>";
    echo "<p class='success'>✅ Menambahkan kategori Politics ke $politicsCount post</p>";
    
    // Tampilkan beberapa post yang sudah dikategorikan
    echo "<h3>Sample Post yang Dikategorikan</h3>";
    
    $stmt = $pdo->prepare("
        SELECT p.id, SUBSTRING(p.content, 1, 100) as content_preview, 
               GROUP_CONCAT(c.name SEPARATOR ', ') as categories
        FROM posts p
        JOIN post_categories pc ON p.id = pc.post_id
        JOIN categories c ON pc.category_id = c.id
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 10
    ");
    $stmt->execute();
    $categorizedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($categorizedPosts) > 0) {
        echo "<table>
            <tr><th>Post ID</th><th>Content</th><th>Categories</th></tr>";
        foreach ($categorizedPosts as $post) {
            echo "<tr>
                <td>{$post['id']}</td>
                <td>" . htmlspecialchars($post['content_preview']) . "...</td>
                <td>{$post['categories']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ Tidak ada post yang dikategorikan.</p>";
    }
}

// Eksekusi berdasarkan aksi
if ($action == 'fix_all') {
    try {
        // Run all fixes
        $pdo->beginTransaction();
        
        $categories = checkSpecificCategories($pdo);
        $categories = createMissingCategories($pdo, $categories);
        categorizeUncategorizedPosts($pdo, $categories);
        
        $pdo->commit();
        echo "<div class='result success'>
            <h3>✅ Semua perbaikan berhasil diterapkan!</h3>
            <p>Sistem kategori telah diperbaiki dan post telah dikategorikan ulang.</p>
        </div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='result error'>
            <h3>❌ Terjadi kesalahan saat memperbaiki kategorisasi:</h3>
            <p>" . $e->getMessage() . "</p>
        </div>";
    }
} else {
    // Just check tables by default
    checkTables($pdo);
    $categories = checkSpecificCategories($pdo);
}

// Opsi perbaikan
echo "<h2>Opsi Perbaikan</h2>";
echo "<p>Pilih salah satu opsi di bawah ini:</p>";
echo "<a href='?action=fix_all' class='btn btn-primary'>Fix Semua Masalah</a> ";
echo "<a href='index.php' class='btn btn-warning'>Kembali ke Beranda</a>";

echo "<div class='result'>
    <h3>Petunjuk</h3>
    <p>Jika post masih tidak muncul dalam filter kategori setelah menjalankan perbaikan, cobalah hal berikut:</p>
    <ol>
        <li>Bersihkan cache browser dengan menekan Ctrl+F5</li>
        <li>Verifikasi bahwa relasi post-kategori benar terbentuk di tabel post_categories</li>
        <li>Periksa apakah ID kategori di URL cocok dengan ID yang ada di database</li>
        <li>Uji dengan kategori lain untuk melihat apakah masalah hanya pada kategori tertentu</li>
    </ol>
</div>";

echo "</body></html>";
?>
