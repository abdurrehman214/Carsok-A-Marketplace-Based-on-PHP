<?php
// ============================================================
//  CarSoko Pakistan — blog.php
//  Blog Listing + Read Modal + Write Modal (all in one page)
// ============================================================
require_once 'connection.php';

// ============================================================
// HANDLE WRITE SUBMISSION
// ============================================================
$writeError   = '';
$writeSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['write_post'])) {
    if (!Auth::check()) {
        $writeError = 'Please sign in to write an article.';
    } elseif (!Auth::isModerator() && (empty($_POST['admin_password']) || $_POST['admin_password'] !== ADMIN_PASSWORD)) {
        $writeError = 'Admin password is required to write articles.';
    } else {
        CSRF::check();
        $title    = trim(cleanInput($_POST['title']    ?? ''));
        $excerpt  = trim(cleanInput($_POST['excerpt']  ?? ''));
        $content  = trim($_POST['content'] ?? '');
        $category = cleanInput($_POST['category'] ?? 'news');
        $tags     = trim(cleanInput($_POST['tags'] ?? ''));

        $validCats = ['buying_guide','market_trends','comparisons','news','tips'];
        if (!$title)                                            $writeError = 'Title is required.';
        elseif (strlen($title) < 5)                            $writeError = 'Title is too short.';
        elseif (!$content || strlen(strip_tags($content)) < 50) $writeError = 'Content must be at least 50 characters.';
        elseif (!in_array($category, $validCats))              $writeError = 'Invalid category.';
        else {
            $role   = Auth::user()['role'] ?? 'buyer';
            // Allow all registered users to publish immediately as per user request
            $status = in_array($role, ['admin','moderator','dealer','private_seller','buyer']) ? 'published' : 'draft';
            $pubAt  = $status === 'published' ? date('Y-m-d H:i:s') : null;

            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
            $slug = trim($slug, '-');
            $exists = DB::value("SELECT COUNT(*) FROM blog_posts WHERE slug LIKE ?", [$slug.'%']);
            if ($exists) $slug .= '-' . time();

            $coverImage = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $file    = $_FILES['cover_image'];
                $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (!in_array($ext, $allowed))       $writeError = 'Cover image must be JPG, PNG or WebP.';
                elseif ($file['size'] > 5*1024*1024) $writeError = 'Cover image must be under 5MB.';
                else {
                    $uploadDir = 'uploads/blog/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = 'blog_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) $coverImage = $filename;
                }
            }

            if (!$writeError) {
                DB::insert(
                    "INSERT INTO blog_posts (author_id, title, slug, excerpt, content, cover_image, category, tags, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [Auth::id(), $title, $slug, $excerpt ?: null, $content, $coverImage, $category, $tags ?: null, $status, $pubAt]
                );
                $writeSuccess = $status === 'published'
                    ? 'Your article has been published!'
                    : 'Your article has been submitted for review!';
            }
        }
    }
}

// ============================================================
// HANDLE DELETE ACTION
// ============================================================
if (isset($_POST['delete_post']) && is_numeric($_POST['post_id'])) {
    if (Auth::isModerator()) {
        CSRF::check();
        $pid = (int)$_POST['post_id'];
        DB::execute("DELETE FROM blog_posts WHERE id = ?", [$pid]);
        flash('success', 'Article deleted successfully.');
        redirect('blog.php');
    }
}

// ============================================================
// AJAX: FETCH SINGLE POST FOR READ MODAL
// ============================================================
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $postId = (int)$_GET['read'];
    $post   = DB::selectOne("
        SELECT bp.*, u.name AS author_name, u.profile_photo AS author_photo
        FROM blog_posts bp LEFT JOIN users u ON u.id = bp.author_id
        WHERE bp.id = ? AND bp.status = 'published'
    ", [$postId]);
    header('Content-Type: application/json');
    if ($post) {
        DB::execute("UPDATE blog_posts SET views = views + 1 WHERE id = ?", [$postId]);
        echo json_encode([
            'ok'          => true,
            'title'       => $post['title'],
            'content'     => $post['content'],
            'excerpt'     => $post['excerpt'],
            'category'    => $post['category'],
            'cover_image' => $post['cover_image'] ? 'uploads/blog/' . $post['cover_image'] : '',
            'author_name' => $post['author_name'],
            'author_photo'=> $post['author_photo'] ?? '',
            'published_at'=> $post['published_at'],
            'tags'        => $post['tags'] ?? '',
            'views'       => $post['views'] + 1,
            'is_admin'    => Auth::isModerator(),
            'id'          => $post['id']
        ]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ============================================================
// LISTING QUERY
// ============================================================
$category  = cleanInput($_GET['category'] ?? '');
$q         = cleanInput($_GET['q']        ?? '');
$page      = max(1, (int)($_GET['page']   ?? 1));
$perPage   = 9;
$validCats = ['buying_guide','market_trends','comparisons','news','tips'];
if ($category && !in_array($category, $validCats)) $category = '';

$where  = ["bp.status = 'published'"];
$params = [];
if ($category) { $where[] = "bp.category = ?"; $params[] = $category; }
if ($q) { $where[] = "(bp.title LIKE ? OR bp.excerpt LIKE ? OR bp.tags LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSQL   = implode(' AND ', $where);
$total      = (int)DB::value("SELECT COUNT(*) FROM blog_posts bp WHERE $whereSQL", $params);
$totalPages = max(1, ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$posts = DB::select("
    SELECT bp.*, u.name AS author_name, u.profile_photo AS author_photo
    FROM blog_posts bp 
    LEFT JOIN users u ON u.id = bp.author_id
    WHERE $whereSQL 
    ORDER BY bp.published_at DESC, bp.id DESC 
    LIMIT $offset, $perPage
", $params);

$featured = DB::selectOne("
    SELECT bp.id, bp.title, bp.slug, bp.excerpt, bp.cover_image,
           bp.category, bp.views, bp.published_at,
           u.name AS author_name, u.profile_photo AS author_photo
    FROM blog_posts bp LEFT JOIN users u ON u.id = bp.author_id
    WHERE bp.status = 'published' ORDER BY bp.published_at DESC LIMIT 1
");

$catCounts = DB::select("SELECT category, COUNT(*) as cnt FROM blog_posts WHERE status='published' GROUP BY category");
$catMap    = [];
foreach ($catCounts as $c) $catMap[$c['category']] = $c['cnt'];

$catMeta = [
    'buying_guide'  => ['label'=>'Buying Guide',  'icon'=>'fa-book-open',     'color'=>'#e8b84b'],
    'market_trends' => ['label'=>'Market Trends', 'icon'=>'fa-chart-line',    'color'=>'#3b82f6'],
    'comparisons'   => ['label'=>'Comparisons',   'icon'=>'fa-balance-scale', 'color'=>'#a855f7'],
    'news'          => ['label'=>'News',           'icon'=>'fa-newspaper',     'color'=>'#22c55e'],
    'tips'          => ['label'=>'Tips & Tricks', 'icon'=>'fa-lightbulb',     'color'=>'#ff6b35'],
];
function catLabel(string $c): string { global $catMeta; return $catMeta[$c]['label'] ?? ucfirst(str_replace('_',' ',$c)); }
function catColor(string $c): string { global $catMeta; return $catMeta[$c]['color'] ?? '#888896'; }
function catIcon(string $c):  string { global $catMeta; return $catMeta[$c]['icon']  ?? 'fa-tag'; }
function blogImg(string $p):  string { if(!$p) return ''; if(strpos($p,'http')===0||strpos($p,'/')===0) return $p; return 'uploads/blog/'.$p; }
function filterUrl(array $n): string { $p=array_merge($_GET,$n); unset($p['read']); return 'blog.php?'.http_build_query($p); }

$isLoggedIn = Auth::check();
$userRole   = $isLoggedIn ? (Auth::user()['role'] ?? 'buyer') : '';
$canPublish = in_array($userRole, ['admin','moderator','dealer','private_seller','buyer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Expert car buying guides, market trends and tips for Pakistan.">
<title><?= setting('site_name','CarSoko') ?> Blog — Car Tips, News &amp; Guides for Pakistan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--black:#0a0a0b;--dark:#111114;--card-bg:#18181c;--border:rgba(255,255,255,0.07);--white:#f5f5f0;--muted:#888896;--accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;--blue:#3b82f6;--gradient:linear-gradient(135deg,#e8b84b 0%,#ff6b35 100%);--font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--radius:10px;--radius-lg:16px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{scroll-behavior:smooth;max-width:100%;overflow-x:hidden}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.6}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
.container{max-width:1280px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:28px}
.logo{font-family:var(--font-head);font-size:24px;font-weight:800;display:flex;align-items:center;flex-shrink:0}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:7px;height:7px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 13px;border-radius:8px;transition:all .2s}
.nav-links a:hover,.nav-links a.active{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .25s;border:none;font-family:var(--font-body)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger span{width:20px;height:2px;background:var(--white);border-radius:2px}

/* PAGE HEADER */
.page-header{background:var(--dark);border-bottom:1px solid var(--border);padding:38px 0 30px}
.page-header .container{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}
.page-tag{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:10px}
.page-tag::before{content:'';width:20px;height:2px;background:var(--gradient);border-radius:2px}
.page-title{font-family:var(--font-head);font-size:clamp(26px,4vw,42px);font-weight:800;line-height:1.1}
.page-title span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-desc{color:var(--muted);font-size:15px;margin-top:10px;max-width:480px}
.header-right{display:flex;align-items:center;gap:12px;flex-shrink:0;flex-wrap:wrap}
.blog-search{display:flex;align-items:stretch;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;overflow:hidden;width:230px;transition:border-color .2s}
.blog-search:focus-within{border-color:var(--accent)}
.blog-search input{flex:1;background:none;border:none;outline:none;padding:10px 14px;font-size:13px;color:var(--white);font-family:var(--font-body);min-width:0}
.blog-search input::placeholder{color:var(--muted)}
.blog-search button{padding:0 14px;background:var(--gradient);border:none;cursor:pointer;color:#0a0a0b;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;min-width:44px}
.write-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:13px;border-radius:50px;cursor:pointer;border:none;font-family:var(--font-body);transition:all .25s;white-space:nowrap;flex-shrink:0}
.write-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}

/* CATEGORY TABS */
.cat-tabs{background:var(--dark);border-bottom:1px solid var(--border)}
.cat-tabs .container{display:flex;overflow-x:auto;scrollbar-width:none}
.cat-tabs .container::-webkit-scrollbar{display:none}
.cat-tab{display:flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:all .2s;flex-shrink:0}
.cat-tab:hover{color:var(--white)}
.cat-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.cat-tab .cnt{background:rgba(255,255,255,.08);font-size:10px;padding:2px 6px;border-radius:50px;font-weight:700}
.cat-tab.active .cnt{background:rgba(232,184,75,.15);color:var(--accent)}

/* LAYOUT */
.blog-layout{display:grid;grid-template-columns:1fr 290px;gap:30px;padding:36px 0 60px;align-items:start}

/* FEATURED */
.featured-post{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:28px;cursor:pointer;transition:all .3s;display:grid;grid-template-columns:1fr 1fr}
.featured-post:hover{border-color:rgba(232,184,75,.25);box-shadow:0 16px 48px rgba(0,0,0,.5)}
.featured-img{position:relative;min-height:260px;overflow:hidden;background:#111}
.featured-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.featured-post:hover .featured-img img{transform:scale(1.05)}
.featured-overlay{position:absolute;inset:0;background:linear-gradient(to right,transparent,rgba(24,24,28,.3))}
.featured-body{padding:28px;display:flex;flex-direction:column;justify-content:center}
.cat-pill{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:4px 10px;border-radius:5px;margin-bottom:14px;width:fit-content}
.featured-title{font-family:var(--font-head);font-size:clamp(18px,2.2vw,23px);font-weight:700;line-height:1.25;margin-bottom:10px}
.featured-excerpt{font-size:14px;color:var(--muted);line-height:1.7;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.featured-meta{display:flex;align-items:center;gap:10px;font-size:12px;color:var(--muted);flex-wrap:wrap}
.featured-meta .sep{opacity:.3}
.read-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--accent);margin-top:14px;transition:gap .2s;background:none;border:none;cursor:pointer;font-family:var(--font-body);padding:0}
.read-link:hover{gap:10px}

/* POSTS GRID */
.posts-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.post-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;cursor:pointer;transition:all .3s}
.post-card:hover{transform:translateY(-5px);border-color:rgba(232,184,75,.2);box-shadow:0 16px 40px rgba(0,0,0,.4)}
.post-img{height:175px;overflow:hidden;background:#111;position:relative}
.post-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.post-card:hover .post-img img{transform:scale(1.06)}
.post-cat-badge{position:absolute;top:10px;left:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:4px 9px;border-radius:5px}
.post-body{padding:16px}
.post-title{font-family:var(--font-head);font-size:14px;font-weight:700;line-height:1.35;margin-bottom:7px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-excerpt{font-size:12px;color:var(--muted);line-height:1.6;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-footer{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border)}
.post-author{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted)}
.author-av{width:22px;height:22px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#0a0a0b;flex-shrink:0;overflow:hidden}
.author-av img{width:100%;height:100%;object-fit:cover}
.post-views{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:3px}
.post-views i{color:var(--accent);font-size:10px}
.empty-blog{text-align:center;padding:80px 20px;grid-column:1/-1}
.empty-blog .ei{font-size:52px;margin-bottom:14px;opacity:.4}
.empty-blog h3{font-family:var(--font-head);font-size:22px;margin-bottom:8px}
.empty-blog p{color:var(--muted);font-size:14px}

/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:28px 0 0;flex-wrap:wrap}
.page-btn{min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;font-size:13px;font-weight:500;color:var(--muted);transition:all .2s;padding:0 10px}
.page-btn:hover{border-color:rgba(232,184,75,.4);color:var(--accent)}
.page-btn.active{background:var(--gradient);border-color:transparent;color:#0a0a0b;font-weight:700}
.page-btn.disabled{opacity:.3;pointer-events:none}

/* SIDEBAR */
.sidebar{display:flex;flex-direction:column;gap:18px;position:sticky;top:84px}
.sb-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.sb-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.sb-head h4{font-family:var(--font-head);font-size:13px;font-weight:700}
.sb-head i{color:var(--accent);font-size:12px}
.sb-cat{display:flex;align-items:center;justify-content:space-between;padding:11px 18px;border-bottom:1px solid var(--border);transition:background .2s}
.sb-cat:last-child{border-bottom:none}
.sb-cat:hover{background:rgba(255,255,255,.03)}
.sb-cat.active{background:rgba(232,184,75,.05)}
.sb-cat-l{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:500}
.sb-cat-l i{width:14px;text-align:center;font-size:11px}
.sb-cnt{font-size:11px;color:var(--muted);background:rgba(255,255,255,.06);padding:2px 7px;border-radius:50px}
.recent-row{display:flex;gap:10px;padding:11px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .2s}
.recent-row:last-child{border-bottom:none}
.recent-row:hover{background:rgba(255,255,255,.03)}
.r-thumb{width:52px;height:44px;border-radius:6px;overflow:hidden;background:#111;flex-shrink:0}
.r-thumb img{width:100%;height:100%;object-fit:cover}
.r-title{font-size:12px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:3px}
.r-date{font-size:10px;color:var(--muted)}
.cta-box{background:linear-gradient(135deg,rgba(232,184,75,.1),rgba(255,107,53,.06));border:1px solid rgba(232,184,75,.2);border-radius:var(--radius-lg);padding:20px;text-align:center}
.cta-box h4{font-family:var(--font-head);font-size:15px;font-weight:700;margin-bottom:7px}
.cta-box p{font-size:12px;color:var(--muted);margin-bottom:14px;line-height:1.6}
.cta-box a{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:12px;border-radius:8px;transition:all .2s}
.cta-box a:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.3)}

/* ═══ READ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;z-index:900;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);display:flex;align-items:flex-start;justify-content:center;padding:24px 20px;overflow-y:auto;opacity:0;pointer-events:none;transition:opacity .3s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal-box{background:var(--dark);border:1px solid var(--border);border-radius:20px;width:100%;max-width:760px;margin:auto;overflow:hidden;transform:translateY(30px);transition:transform .35s cubic-bezier(.4,0,.2,1);position:relative}
.modal-overlay.open .modal-box{transform:translateY(0)}
.modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,.6);border:1px solid var(--border);color:var(--white);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;transition:all .2s;backdrop-filter:blur(10px)}
.modal-close:hover{background:rgba(239,68,68,.25);border-color:rgba(239,68,68,.4);color:var(--red)}
.read-cover{width:100%;height:300px;object-fit:cover;display:block}
.read-cover-ph{width:100%;height:180px;background:linear-gradient(135deg,var(--card-bg),#1e1e26);display:flex;align-items:center;justify-content:center;font-size:52px;opacity:.3}
.read-inner{padding:32px}
.read-cat-pill{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:4px 10px;border-radius:5px;margin-bottom:14px}
.read-title{font-family:var(--font-head);font-size:clamp(20px,3vw,30px);font-weight:800;line-height:1.2;margin-bottom:12px}
.read-meta{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--muted);margin-bottom:24px;flex-wrap:wrap;padding-bottom:20px;border-bottom:1px solid var(--border)}
.read-av{width:30px;height:30px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#0a0a0b;overflow:hidden;flex-shrink:0}
.read-av img{width:100%;height:100%;object-fit:cover}
.read-body{font-size:15px;color:rgba(245,245,240,.82);line-height:1.85}
.read-body h2{font-family:var(--font-head);font-size:20px;margin:22px 0 8px;color:var(--white)}
.read-body h3{font-family:var(--font-head);font-size:17px;margin:18px 0 6px;color:var(--white)}
.read-body p{margin-bottom:14px}
.read-body ul,.read-body ol{padding-left:22px;margin-bottom:14px}
.read-body li{margin-bottom:5px}
.read-body strong{color:var(--white)}
.read-body a{color:var(--accent);text-decoration:underline}
.read-body img{border-radius:10px;margin:16px 0;max-width:100%}
.read-body blockquote{border-left:3px solid var(--accent);padding:12px 18px;background:rgba(232,184,75,.06);border-radius:0 8px 8px 0;margin:14px 0;font-style:italic;color:var(--muted)}
.read-tags{display:flex;flex-wrap:wrap;gap:7px;margin-top:22px;padding-top:18px;border-top:1px solid var(--border)}
.tag-pill{font-size:11px;padding:4px 10px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:50px;color:var(--muted)}
.read-spinner{display:flex;align-items:center;justify-content:center;padding:80px;gap:12px;color:var(--muted)}
.read-spinner i{font-size:22px;animation:spin 1s linear infinite;color:var(--accent)}
@keyframes spin{to{transform:rotate(360deg)}}

/* ═══ WRITE MODAL ═══ */
.write-overlay{position:fixed;inset:0;z-index:900;background:rgba(0,0,0,.88);backdrop-filter:blur(8px);display:flex;align-items:flex-start;justify-content:center;padding:24px 20px;overflow-y:auto;opacity:0;pointer-events:none;transition:opacity .3s}
.write-overlay.open{opacity:1;pointer-events:all}
.write-box{background:var(--dark);border:1px solid var(--border);border-radius:20px;width:100%;max-width:720px;margin:auto;overflow:hidden;transform:translateY(30px);transition:transform .35s cubic-bezier(.4,0,.2,1)}
.write-overlay.open .write-box{transform:translateY(0)}
.write-header{padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--dark);z-index:1}
.write-header h3{font-family:var(--font-head);font-size:18px;font-weight:700;display:flex;align-items:center;gap:9px}
.write-header h3 i{color:var(--accent)}
.write-body{padding:24px}
.wf{margin-bottom:18px}
.wf label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:7px}
.wf input,.wf select,.wf textarea{width:100%;background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:11px 14px;border-radius:9px;font-size:14px;font-family:var(--font-body);outline:none;transition:border-color .2s}
.wf input:focus,.wf select:focus,.wf textarea:focus{border-color:var(--accent)}
.wf select option{background:var(--dark)}
.wf textarea{resize:vertical;min-height:70px}
.wf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.editor-wrap{border:1px solid var(--border);border-radius:9px;overflow:hidden;transition:border-color .2s}
.editor-wrap:focus-within{border-color:var(--accent)}
.editor-toolbar{display:flex;gap:2px;padding:8px 10px;background:rgba(0,0,0,.3);border-bottom:1px solid var(--border);flex-wrap:wrap}
.tb-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--muted);cursor:pointer;border-radius:5px;font-size:12px;transition:all .2s;font-family:var(--font-body)}
.tb-btn:hover{background:rgba(232,184,75,.15);color:var(--accent)}
.tb-sep{width:1px;background:var(--border);margin:0 4px;align-self:stretch}
#editorArea{min-height:220px;max-height:360px;overflow-y:auto;padding:14px;outline:none;font-size:14px;line-height:1.8;color:rgba(245,245,240,.85);background:rgba(0,0,0,.2)}
#editorArea:empty::before{content:attr(data-placeholder);color:var(--muted);pointer-events:none}
#editorArea h2{font-family:var(--font-head);margin:14px 0 6px;color:var(--white);font-size:18px}
#editorArea h3{font-family:var(--font-head);margin:12px 0 5px;color:var(--white);font-size:15px}
#editorArea blockquote{border-left:3px solid var(--accent);padding:8px 14px;background:rgba(232,184,75,.06);border-radius:0 7px 7px 0;margin:10px 0;color:var(--muted);font-style:italic}
#editorArea ul,#editorArea ol{padding-left:20px}
.cover-upload{border:2px dashed var(--border);border-radius:9px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.cover-upload:hover,.cover-upload.dragover{border-color:var(--accent);background:rgba(232,184,75,.04)}
.cover-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.cover-preview{width:100%;height:150px;object-fit:cover;border-radius:7px;display:none;margin-top:10px}
.w-alert{padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px}
.w-alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.w-alert-ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.char-cnt{font-size:11px;color:var(--muted);text-align:right;margin-top:3px}
.write-footer{padding:0 24px 22px;display:flex;gap:10px;justify-content:flex-end;align-items:center;flex-wrap:wrap}
.draft-note{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;flex:1;min-width:200px}
.draft-note i{color:var(--accent);flex-shrink:0}
.w-submit{display:inline-flex;align-items:center;gap:7px;padding:12px 26px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:14px;border:none;border-radius:50px;cursor:pointer;font-family:var(--font-body);transition:all .25s;white-space:nowrap}
.w-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}
.w-submit:disabled{opacity:.5;pointer-events:none;transform:none}
.w-cancel{display:inline-flex;align-items:center;gap:6px;padding:12px 18px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted);font-weight:600;font-size:13px;border-radius:50px;cursor:pointer;font-family:var(--font-body);transition:all .2s}
.w-cancel:hover{color:var(--white);border-color:rgba(255,255,255,.2)}

/* RESPONSIVE */
@media(max-width:1024px){
    .blog-layout{grid-template-columns:1fr;gap:24px}
    .sidebar{position:static;display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .cta-box{grid-column:1/-1}
    .nav-links{display:none;position:absolute;top:64px;left:0;width:100%;background:var(--dark);flex-direction:column;padding:20px;gap:10px;border-bottom:1px solid var(--border);z-index:201}
    .nav-links.active{display:flex}
    .hamburger{display:flex}
    .container{padding:0 16px}
    .navbar .container{gap:12px}
    .nav-right{gap:8px}
}
@media(max-width:768px){
    .page-header{padding:24px 0 20px}
    .page-header .container{flex-direction:column;align-items:flex-start;gap:14px}
    .header-right{width:100%;gap:8px}
    .blog-search{flex:1;width:auto}
    .featured-post{grid-template-columns:1fr}
    .featured-img{min-height:200px;height:200px}
    .featured-body{padding:18px}
    .posts-grid{grid-template-columns:1fr}
    .sidebar{grid-template-columns:1fr}
    .wf-row{grid-template-columns:1fr}
    .container{padding:0 14px}
    .blog-layout{padding:20px 0 40px}
    .read-inner{padding:20px}
    .read-cover{height:220px}
    .write-body{padding:18px}
    .write-footer{padding:0 18px 18px}
}
@media(max-width:480px){
    .navbar .container{height:56px}
    .logo{font-size:19px}
    .nav-right{gap:6px}
    .nav-right .btn{padding:8px 12px;font-size:12px;gap:5px}
    .nav-right .btn span{display:none}
    .hamburger{padding:7px}
    .cat-tab{padding:12px 12px;font-size:12px}
    .post-img{height:155px}
    .featured-img{height:180px}
    .page-title{font-size:24px}
    .container{padding:0 12px}
    .modal-overlay,.write-overlay{padding:10px}
    .read-cover{height:180px}
    .write-btn span{display:none}
}
.reveal{opacity:0;transform:translateY(16px);transition:opacity .45s ease,transform .45s ease}
.reveal.visible{opacity:1;transform:none}
.footer { background:var(--dark); border-top:1px solid var(--border); padding:64px 0 40px; margin-top:60px; }
.footer-bottom { text-align:center; padding-top:40px; margin-top:40px; border-top:1px solid var(--border); font-size:13px; color:var(--muted); }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo"><span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div></a>
        <div class="nav-links" id="mobileNav">
            <a href="listings.php">Browse Cars</a>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="listings.php?seller=dealer">Dealers</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="blog.php" class="active">Blog</a>
        </div>
        <div class="nav-right">
            <?php if ($isLoggedIn): ?>
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-user"></i> <span>Dashboard</span></a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> <span>Sign In</span></a>
            <?php endif; ?>
            <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> <span>Sell Car</span></a>
            <div class="hamburger" onclick="document.getElementById('mobileNav').classList.toggle('active')"><span></span><span></span><span></span></div>
        </div>
    </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container">
        <div>
            <div class="page-tag"><i class="fas fa-pen-nib"></i> CarSoko Journal</div>
            <h1 class="page-title">Car <span>Insights</span> &amp; Guides</h1>
            <p class="page-desc">Expert tips, market trends and buying guides for Pakistani car buyers.</p>
        </div>
        <div class="header-right">
            <form class="blog-search" method="GET" action="blog.php">
                <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
                <input type="text" name="q" placeholder="Search articles…" value="<?= e($q) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <button class="write-btn" onclick="openWrite()">
                <i class="fas fa-pen"></i> <span>Write Article</span>
            </button>
        </div>
    </div>
</div>

<!-- CATEGORY TABS -->
<div class="cat-tabs">
    <div class="container">
        <a href="blog.php<?= $q?'?q='.urlencode($q):'' ?>" class="cat-tab <?= !$category?'active':'' ?>">
            <i class="fas fa-th" style="font-size:11px"></i> All
            <span class="cnt"><?= array_sum(array_column($catCounts,'cnt')) ?></span>
        </a>
        <?php foreach ($catMeta as $key => $cat): ?>
        <a href="<?= filterUrl(['category'=>$key,'page'=>1]) ?>" class="cat-tab <?= $category===$key?'active':'' ?>">
            <i class="fas <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>;font-size:11px"></i>
            <?= $cat['label'] ?>
            <?php if (!empty($catMap[$key])): ?><span class="cnt"><?= $catMap[$key] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- MAIN -->
<div class="container">
    <div class="blog-layout">

        <!-- LEFT: POSTS -->
        <div>
            <?php if ($featured && $page===1 && !$category && !$q):
                $fImg   = $featured['cover_image'] ? blogImg($featured['cover_image']) : '';
                $fColor = catColor($featured['category']);
            ?>
            <div class="featured-post reveal" onclick="openRead(<?= $featured['id'] ?>)">
                <div class="featured-img">
                    <?php if ($fImg): ?>
                    <img src="<?= e($fImg) ?>" alt="<?= e($featured['title']) ?>" onerror="this.style.display='none'">
                    <?php else: ?>
                    <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--card-bg),#1e1e24);display:flex;align-items:center;justify-content:center;font-size:64px;opacity:.25">📰</div>
                    <?php endif; ?>
                    <div class="featured-overlay"></div>
                </div>
                <div class="featured-body">
                    <span class="cat-pill" style="background:<?= $fColor ?>22;border:1px solid <?= $fColor ?>44;color:<?= $fColor ?>">
                        <i class="fas <?= catIcon($featured['category']) ?>"></i> <?= catLabel($featured['category']) ?>
                    </span>
                    <h2 class="featured-title"><?= e($featured['title']) ?></h2>
                    <?php if ($featured['excerpt']): ?><p class="featured-excerpt"><?= e($featured['excerpt']) ?></p><?php endif; ?>
                    <div class="featured-meta">
                        <span><?= e($featured['author_name']) ?></span>
                        <span class="sep">·</span>
                        <span><?= date('M j, Y', strtotime($featured['published_at'])) ?></span>
                        <span class="sep">·</span>
                        <span><i class="fas fa-eye" style="color:var(--accent);font-size:10px"></i> <?= number_format($featured['views']) ?></span>
                    </div>
                    <button class="read-link" onclick="event.stopPropagation();openRead(<?= $featured['id'] ?>)">
                        Read Article <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($q): ?>
            <div style="margin-bottom:18px;font-size:14px;color:var(--muted)">
                <i class="fas fa-search" style="color:var(--accent)"></i>
                <strong style="color:var(--white)"><?= $total ?></strong> result<?= $total!==1?'s':'' ?> for
                "<strong style="color:var(--white)"><?= e($q) ?></strong>"
                <a href="blog.php<?= $category?'?category='.$category:'' ?>" style="color:var(--accent);margin-left:8px;font-size:12px"><i class="fas fa-times"></i> Clear</a>
            </div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
            <div class="empty-blog">
                <div class="ei">📰</div>
                <h3>No Articles Found</h3>
                <p>Nothing here yet. <a href="blog.php" style="color:var(--accent)">Browse all articles</a> or <button onclick="openWrite()" style="background:none;border:none;color:var(--accent);cursor:pointer;font-family:var(--font-body);font-size:14px;padding:0">write the first one</button>.</p>
            </div>
            <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $i => $post):
                    $pImg = $post['cover_image'] ? blogImg($post['cover_image']) : '';
                    $pCol = catColor($post['category']);
                ?>
                <div class="post-card reveal" style="animation-delay:<?= ($i%2)*.07 ?>s" onclick="openRead(<?= $post['id'] ?>)">
                    <div class="post-img">
                        <?php if ($pImg): ?>
                        <img src="<?= e($pImg) ?>" alt="<?= e($post['title']) ?>" loading="lazy" onerror="this.style.display='none'">
                        <?php else: ?>
                        <div style="width:100%;height:100%;background:linear-gradient(135deg,#18181c,#1e1e26);display:flex;align-items:center;justify-content:center;font-size:36px;opacity:.25">📝</div>
                        <?php endif; ?>
                        <span class="post-cat-badge" style="background:<?= $pCol ?>22;border:1px solid <?= $pCol ?>44;color:<?= $pCol ?>"><?= catLabel($post['category']) ?></span>
                    </div>
                    <div class="post-body">
                        <h3 class="post-title"><?= e($post['title']) ?></h3>
                        <?php if ($post['excerpt']): ?><p class="post-excerpt"><?= e($post['excerpt']) ?></p><?php endif; ?>
                        <div class="post-footer">
                            <div class="post-author">
                                <div class="author-av">
                                    <?php if ($post['author_photo']): ?><img src="<?= e($post['author_photo']) ?>" alt=""><?php else: ?><?= strtoupper(substr($post['author_name'],0,1)) ?><?php endif; ?>
                                </div>
                                <?= e($post['author_name']) ?>
                                <span style="opacity:.3">·</span>
                                <?= date('M j', strtotime($post['published_at'])) ?>
                            </div>
                            <div class="post-views"><i class="fas fa-eye"></i> <?= number_format($post['views']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page>1): ?>
                <a href="<?= filterUrl(['page'=>$page-1]) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?><span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
                <?php for($p=1;$p<=$totalPages;$p++):
                    if($p!==1&&$p!==$totalPages&&abs($p-$page)>2){if($p===2||$p===$totalPages-1)echo '<span style="color:var(--muted);padding:0 4px">…</span>';continue;}
                ?><a href="<?= filterUrl(['page'=>$p]) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
                <?php if ($page<$totalPages): ?>
                <a href="<?= filterUrl(['page'=>$page+1]) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?><span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sb-card reveal">
                <div class="sb-head"><i class="fas fa-tags"></i><h4>Categories</h4></div>
                <a href="blog.php<?= $q?'?q='.urlencode($q):'' ?>" class="sb-cat <?= !$category?'active':'' ?>">
                    <span class="sb-cat-l"><i class="fas fa-th" style="color:var(--accent)"></i> All Articles</span>
                    <span class="sb-cnt"><?= array_sum(array_column($catCounts,'cnt')) ?></span>
                </a>
                <?php foreach ($catMeta as $key => $cat): ?>
                <a href="blog.php?category=<?= $key ?><?= $q?'&q='.urlencode($q):'' ?>" class="sb-cat <?= $category===$key?'active':'' ?>">
                    <span class="sb-cat-l" style="<?= $category===$key?'color:'.$cat['color']:'' ?>">
                        <i class="fas <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>"></i> <?= $cat['label'] ?>
                    </span>
                    <span class="sb-cnt"><?= $catMap[$key]??0 ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <?php $recent = DB::select("SELECT id,title,cover_image,published_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC LIMIT 5"); ?>
            <?php if ($recent): ?>
            <div class="sb-card reveal">
                <div class="sb-head"><i class="fas fa-clock"></i><h4>Recent Posts</h4></div>
                <?php foreach ($recent as $r): $rImg = $r['cover_image'] ? blogImg($r['cover_image']) : ''; ?>
                <div class="recent-row" onclick="openRead(<?= $r['id'] ?>)">
                    <div class="r-thumb">
                        <?php if ($rImg): ?><img src="<?= e($rImg) ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php else: ?>
                        <div style="width:100%;height:100%;background:#1e1e26;display:flex;align-items:center;justify-content:center;font-size:18px;opacity:.3">📝</div><?php endif; ?>
                    </div>
                    <div><div class="r-title"><?= e($r['title']) ?></div><div class="r-date"><?= date('M j, Y', strtotime($r['published_at'])) ?></div></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cta-box reveal">
                <h4>🚗 Sell Your Car Fast</h4>
                <p>Reach thousands of buyers across Pakistan with a free listing.</p>
                <a href="post-listing.php"><i class="fas fa-plus"></i> Post Free Listing</a>
            </div>
        </aside>
    </div>
</div>

<!-- ═══ READ MODAL ═══ -->
<div class="modal-overlay" id="readOverlay" onclick="if(event.target===this)closeReadModal()">
    <div class="modal-box" id="readBox">
        <button class="modal-close" onclick="closeReadModal()"><i class="fas fa-times"></i></button>
        <div id="readInner"><div class="read-spinner"><i class="fas fa-circle-notch"></i>&nbsp; Loading…</div></div>
    </div>
</div>

<!-- ═══ WRITE MODAL ═══ -->
<div class="write-overlay" id="writeOverlay" onclick="if(event.target===this)closeWriteModal()">
    <div class="write-box">
        <div class="write-header">
            <h3><i class="fas fa-pen-nib"></i> Write an Article</h3>
            <button class="modal-close" onclick="closeWriteModal()" style="position:static"><i class="fas fa-times"></i></button>
        </div>

        <?php if ($writeSuccess): ?>
        <div class="write-body" style="text-align:center;padding:32px">
            <div style="font-size:48px;margin-bottom:16px">🎉</div>
            <div class="w-alert w-alert-ok" style="justify-content:center;font-size:15px">
                <i class="fas fa-check-circle" style="font-size:18px"></i> <?= e($writeSuccess) ?>
            </div>
            <button class="w-cancel" onclick="closeWriteModal()" style="margin-top:8px">Close</button>
        </div>
        <?php elseif (!$isLoggedIn): ?>
        <div class="write-body" style="text-align:center;padding:32px">
            <div style="font-size:40px;margin-bottom:14px">✍️</div>
            <h4 style="font-family:var(--font-head);font-size:18px;margin-bottom:8px">Share Your Knowledge</h4>
            <p style="color:var(--muted);font-size:14px;margin-bottom:20px">Sign in to write articles and help fellow Pakistani car buyers.</p>
            <a href="login.php?redirect=<?= urlencode('blog.php') ?>" class="w-submit"><i class="fas fa-sign-in-alt"></i> Sign In to Write</a>
        </div>
        <?php else: ?>
        <form method="POST" enctype="multipart/form-data" onsubmit="return submitWrite(this)">
            <?= CSRF::field() ?>
            <input type="hidden" name="write_post" value="1">
            <input type="hidden" name="content" id="contentHidden">
            <div class="write-body">
                <?php if ($writeError): ?>
                <div class="w-alert w-alert-err"><i class="fas fa-exclamation-circle"></i> <?= e($writeError) ?></div>
                <?php endif; ?>

                <?php if (!Auth::isModerator()): ?>
                <div class="wf">
                    <label>Admin Password <span style="color:var(--red)">*</span></label>
                    <input type="password" name="admin_password" placeholder="Enter password to post..." required>
                </div>
                <?php endif; ?>

                <!-- Title -->
                <div class="wf">
                    <label>Article Title <span style="color:var(--red)">*</span></label>
                    <input type="text" name="title" placeholder="e.g. How to Spot a Clocked Car When Buying Used in Pakistan"
                           value="<?= e($_POST['title']??'') ?>" maxlength="255" oninput="document.getElementById('titleCnt').textContent=this.value.length" required>
                    <div class="char-cnt"><span id="titleCnt">0</span>/255</div>
                </div>

                <!-- Category + Tags -->
                <div class="wf-row">
                    <div class="wf">
                        <label>Category <span style="color:var(--red)">*</span></label>
                        <select name="category" required>
                            <?php foreach ($catMeta as $key => $cat): ?>
                            <option value="<?= $key ?>" <?= ($_POST['category']??'news')===$key?'selected':'' ?>><?= $cat['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="wf">
                        <label>Tags <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(comma separated)</small></label>
                        <input type="text" name="tags" placeholder="toyota, buying tips, karachi" value="<?= e($_POST['tags']??'') ?>" maxlength="255">
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="wf">
                    <label>Short Summary <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(optional · shown on cards)</small></label>
                    <textarea name="excerpt" rows="2" placeholder="1–2 sentence teaser for your article…" maxlength="320"
                              oninput="document.getElementById('excerptCnt').textContent=this.value.length"><?= e($_POST['excerpt']??'') ?></textarea>
                    <div class="char-cnt"><span id="excerptCnt">0</span>/320</div>
                </div>

                <!-- Rich Text Editor -->
                <div class="wf">
                    <label>Article Content <span style="color:var(--red)">*</span></label>
                    <div class="editor-wrap">
                        <div class="editor-toolbar">
                            <button type="button" class="tb-btn" onclick="fmt('bold')" title="Bold"><i class="fas fa-bold"></i></button>
                            <button type="button" class="tb-btn" onclick="fmt('italic')" title="Italic"><i class="fas fa-italic"></i></button>
                            <button type="button" class="tb-btn" onclick="fmt('underline')" title="Underline"><i class="fas fa-underline"></i></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="fmtBlock('h2')" title="Heading"><b style="font-size:11px">H2</b></button>
                            <button type="button" class="tb-btn" onclick="fmtBlock('h3')" title="Subheading"><b style="font-size:10px">H3</b></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="fmt('insertUnorderedList')" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                            <button type="button" class="tb-btn" onclick="fmt('insertOrderedList')" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="fmtBlock('blockquote')" title="Quote"><i class="fas fa-quote-left"></i></button>
                            <button type="button" class="tb-btn" onclick="insertLink()" title="Insert link"><i class="fas fa-link"></i></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="fmt('removeFormat')" title="Clear formatting"><i class="fas fa-eraser"></i></button>
                        </div>
                        <div id="editorArea" contenteditable="true"
                             data-placeholder="Write your article here… share tips, reviews, guides or your experience buying and selling cars in Pakistan."
                             oninput="document.getElementById('wordCnt').textContent=this.innerText.trim().split(/\s+/).filter(Boolean).length"></div>
                    </div>
                    <div class="char-cnt"><span id="wordCnt">0</span> words</div>
                </div>

                <!-- Cover Image -->
                <div class="wf">
                    <label>Cover Image <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(optional · JPG/PNG/WebP · max 5MB)</small></label>
                    <div class="cover-upload" id="coverUpload"
                         ondragover="event.preventDefault();this.classList.add('dragover')"
                         ondragleave="this.classList.remove('dragover')"
                         ondrop="this.classList.remove('dragover')">
                        <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" onchange="previewCover(this)">
                        <i class="fas fa-cloud-upload-alt" style="font-size:26px;color:var(--accent);margin-bottom:8px;display:block"></i>
                        <div style="font-size:13px;color:var(--muted)">Click to choose or drag &amp; drop an image</div>
                        <img id="coverPreview" class="cover-preview" alt="">
                    </div>
                </div>
            </div>
            <div class="write-footer">
                <?php if ($status !== 'published'): ?>
                <span class="draft-note"><i class="fas fa-info-circle"></i> Your article will be reviewed before publishing.</span>
                <?php endif; ?>
                <button type="button" class="w-cancel" onclick="closeWriteModal()">Cancel</button>
                <button type="submit" class="w-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i>
                    <?= $canPublish ? 'Publish Article' : 'Submit for Review' ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div style="text-align:center;">
            <div class="logo" style="justify-content:center; margin-bottom:20px; font-family:var(--font-head); font-size:26px;">
                <span style="background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Car</span><span style="color:var(--white)">Soko</span>
            </div>
            <p style="color:var(--muted); font-size:14px; max-width:400px; margin:0 auto;">Pakistan's most trusted car marketplace. Expert insights, reviews and buying guides at your fingertips.</p>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.
        </div>
    </div>
</footer>

<script>
// ── READ MODAL ──
const catColorsJs = <?= json_encode(array_map(fn($c)=>$c['color'], $catMeta)) ?>;
const catLabelsJs = <?= json_encode(array_map(fn($c)=>$c['label'], $catMeta)) ?>;
const catIconsJs  = <?= json_encode(array_map(fn($c)=>$c['icon'],  $catMeta)) ?>;

function openRead(id) {
    const overlay = document.getElementById('readOverlay');
    document.getElementById('readInner').innerHTML = '<div class="read-spinner"><i class="fas fa-circle-notch"></i>&nbsp; Loading…</div>';
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch('blog.php?read=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                document.getElementById('readInner').innerHTML = '<div style="padding:60px;text-align:center;color:var(--muted)">Post not found.</div>';
                return;
            }
            const color = catColorsJs[d.category] || '#e8b84b';
            const label = catLabelsJs[d.category] || d.category;
            const icon  = catIconsJs[d.category]  || 'fa-tag';
            const date  = d.published_at ? new Date(d.published_at).toLocaleDateString('en-PK',{year:'numeric',month:'long',day:'numeric'}) : '';
            const tags  = d.tags ? d.tags.split(',').map(t=>`<span class="tag-pill">${t.trim()}</span>`).join('') : '';
            const av    = d.author_photo ? `<img src="${d.author_photo}" alt="" onerror="this.style.display='none'">` : d.author_name.charAt(0).toUpperCase();

            document.getElementById('readInner').innerHTML = `
                ${d.cover_image
                    ? `<img class="read-cover" src="${d.cover_image}" alt="${d.title}" onerror="this.style.display='none'">`
                    : '<div class="read-cover-ph">📰</div>'}
                <div class="read-inner">
                    <span class="read-cat-pill" style="background:${color}22;border:1px solid ${color}44;color:${color}">
                        <i class="fas ${icon}"></i> ${label}
                    </span>
                    <h1 class="read-title">${d.title}</h1>
                    <div class="read-meta">
                        <div class="read-av">${av}</div>
                        <span>${d.author_name}</span>
                        <span style="opacity:.3">·</span>
                        <span>${date}</span>
                        <span style="opacity:.3">·</span>
                        <span><i class="fas fa-eye" style="color:var(--accent);font-size:10px"></i> ${Number(d.views).toLocaleString()} views</span>
                    </div>
                    <div class="read-body">${d.content}</div>
                    ${tags ? `<div class="read-tags">${tags}</div>` : ''}
                </div>
                ${d.is_admin ? `
                <div style="padding:0 32px 32px">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this article?')">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="delete_post" value="1">
                        <input type="hidden" name="post_id" value="${d.id}">
                        <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;border-radius:10px;padding:12px">
                            <i class="fas fa-trash-alt"></i> Delete Article
                        </button>
                    </form>
                </div>` : ''}`;
        })
        .catch(() => {
            document.getElementById('readInner').innerHTML = '<div style="padding:60px;text-align:center;color:var(--muted)">Failed to load. Please try again.</div>';
        });
}

function closeReadModal() {
    document.getElementById('readOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// ── WRITE MODAL ──
function openWrite() {
    document.getElementById('writeOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeWriteModal() {
    document.getElementById('writeOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// Re-open write modal if there was a server error or success
<?php if ($writeError || $writeSuccess): ?>
document.addEventListener('DOMContentLoaded', openWrite);
<?php endif; ?>

// Rich text helpers
function fmt(cmd) { document.getElementById('editorArea').focus(); document.execCommand(cmd, false, null); }
function fmtBlock(tag) { document.getElementById('editorArea').focus(); document.execCommand('formatBlock', false, '<'+tag+'>'); }
function insertLink() { const u = prompt('Enter URL (e.g. https://example.com):'); if (u) { document.getElementById('editorArea').focus(); document.execCommand('createLink', false, u); } }

// Cover preview
function previewCover(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => { const p = document.getElementById('coverPreview'); p.src = e.target.result; p.style.display = 'block'; };
        r.readAsDataURL(input.files[0]);
    }
}

// Sync contenteditable → hidden input before submit
function submitWrite(form) {
    const editor = document.getElementById('editorArea');
    const hidden = document.getElementById('contentHidden');
    if (!editor || !hidden) return true;
    hidden.value = editor.innerHTML.trim();
    if (editor.innerText.trim().length < 50) {
        alert('Please write at least 50 characters of content.');
        return false;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting…';
    return true;
}

// ESC to close
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeReadModal(); closeWriteModal(); }
});

// Scroll reveal
const revealObs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); } });
}, {threshold: 0.06});
document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));
</script>
</body>
</html>