<?php
//  CarSoko Pakistan — post-listing.php
//  Seller / Dealer Listing Creation
require_once 'connection.php';

Auth::requireLogin('/login.php');
if (Auth::is('buyer')) {
    flash('error', 'You need a Seller or Dealer account to post listings.');
    redirect(BASE_URL . '/index.php');
}

$user   = Auth::user();
$editId = (int)($_GET['edit'] ?? 0);
$isEdit = false;
$car    = [];
$editImages  = [];
$carFeatures = [];

const MAX_USER_PHOTOS = 7;

// ============================================================
// EDIT MODE — load existing car
// ============================================================
if ($editId) {
    $car = DB::selectOne(
        "SELECT c.*, m.name AS make_name,
                (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name
         FROM cars c
         LEFT JOIN makes m ON m.id = c.make_id
         WHERE c.id = ? AND c.user_id = ?",
        [$editId, Auth::id()]
    );
    if (!$car) {
        flash('error', 'Listing not found or you do not have permission to edit it.');
        redirect(BASE_URL . '/dashboard.php');
    }
    $isEdit      = true;
    $editImages  = DB::select(
        "SELECT id, image_path, thumb_path, is_featured, sort_order
         FROM car_images WHERE car_id = ? ORDER BY is_featured DESC, sort_order ASC",
        [$editId]
    );
    $carFeatures = !empty($car['features']) ? json_decode($car['features'], true) : [];
}

// ============================================================
// MAKES for dropdown
// ============================================================
$makes = DB::select("SELECT id, name, slug FROM makes ORDER BY name");
if (empty($makes)) {
    $makes = [
        ['id'=>1,  'name'=>'Toyota',      'slug'=>'toyota'],
        ['id'=>2,  'name'=>'Nissan',      'slug'=>'nissan'],
        ['id'=>3,  'name'=>'Honda',       'slug'=>'honda'],
        ['id'=>4,  'name'=>'Mazda',       'slug'=>'mazda'],
        ['id'=>5,  'name'=>'Subaru',      'slug'=>'subaru'],
        ['id'=>6,  'name'=>'Mercedes',    'slug'=>'mercedes'],
        ['id'=>7,  'name'=>'BMW',         'slug'=>'bmw'],
        ['id'=>8,  'name'=>'Mitsubishi',  'slug'=>'mitsubishi'],
        ['id'=>9,  'name'=>'Volkswagen',  'slug'=>'volkswagen'],
        ['id'=>10, 'name'=>'Hyundai',     'slug'=>'hyundai'],
        ['id'=>11, 'name'=>'Isuzu',       'slug'=>'isuzu'],
        ['id'=>12, 'name'=>'Land Rover',  'slug'=>'land-rover'],
        ['id'=>13, 'name'=>'Ford',        'slug'=>'ford'],
        ['id'=>14, 'name'=>'Kia',         'slug'=>'kia'],
        ['id'=>15, 'name'=>'Peugeot',     'slug'=>'peugeot'],
        ['id'=>16, 'name'=>'Suzuki',      'slug'=>'suzuki'],
        ['id'=>17, 'name'=>'Jeep',        'slug'=>'jeep'],
        ['id'=>18, 'name'=>'Lexus',       'slug'=>'lexus'],
        ['id'=>19, 'name'=>'Audi',        'slug'=>'audi'],
        ['id'=>20, 'name'=>'Other',       'slug'=>'other'],
    ];
}

$errors = [];
$old    = $car; // pre-fill in edit mode

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF check (show error instead of die) ──
    if (!CSRF::verify()) {
        $errors['general'] = 'Security token expired. Please refresh the page and try again.';
    }
     else {

        // ── COLLECT ──
        $makeIdRaw   = cleanInput($_POST['make_id']      ?? '');
        $modelIdRaw  = cleanInput($_POST['model_id']     ?? '');
        $customMake  = cleanInput($_POST['custom_make']  ?? '');
        $customModel = cleanInput($_POST['custom_model'] ?? '');

        // Resolve make
        $resolvedMakeName = '';
        $finalMakeId      = 0;

        if ($makeIdRaw === 'other' || $makeIdRaw === '0' || $makeIdRaw === '') {
            $resolvedMakeName = $customMake;
        } else {
            $finalMakeId      = (int)$makeIdRaw;
            $resolvedMakeName = DB::value("SELECT name FROM makes WHERE id=?", [$finalMakeId]) ?? $customMake;
            if (!$resolvedMakeName) $resolvedMakeName = $customMake;
        }

        // Resolve model — model_id from dropdown may be a name string (from fallback list) or a numeric id
        $resolvedModelName = '';
        $finalModelId      = 0;

        if ($modelIdRaw === 'other' || $modelIdRaw === '0' || $modelIdRaw === '' ) {
            // Custom typed model
            $resolvedModelName = $customModel;
        } elseif (ctype_digit($modelIdRaw)) {
            // Numeric DB id
            $finalModelId      = (int)$modelIdRaw;
            $resolvedModelName = DB::value("SELECT name FROM models WHERE id=?", [$finalModelId]) ?? $customModel;
            if (!$resolvedModelName) $resolvedModelName = $customModel ?: $modelIdRaw;
        } else {
            // String model name from fallback list (e.g. "Corolla")
            $resolvedModelName = $modelIdRaw;
        }

        $old = [
            'make_id'          => $finalMakeId,
            'model_id'         => $finalModelId,
            'make_name'        => $resolvedMakeName,
            'model_name'       => $resolvedModelName,
            'custom_make'      => $customMake,
            'custom_model'     => $customModel,
            'year'             => (int)($_POST['year']         ?? 0),
            'price'            => (int)($_POST['price']        ?? 0),
            'mileage'          => (int)($_POST['mileage']      ?? 0),
            'condition'        => cleanInput($_POST['condition']    ?? ''),
            'fuel_type'        => cleanInput($_POST['fuel_type']    ?? ''),
            'transmission'     => cleanInput($_POST['transmission'] ?? ''),
            'body_type'        => cleanInput($_POST['body_type']    ?? ''),
            'drive_type'       => cleanInput($_POST['drive_type']   ?? ''),
            'engine_cc'        => (int)($_POST['engine_cc']    ?? 0),
            'color'            => cleanInput($_POST['color']        ?? ''),
            'doors'            => (int)($_POST['doors']        ?? 4),
            'seats'            => (int)($_POST['seats']        ?? 5),
            'city'             => cleanInput($_POST['city']        ?? ''),
            'county'           => cleanInput($_POST['county']      ?? ''),
            'description'      => cleanInput($_POST['description'] ?? ''),
            'price_negotiable' => !empty($_POST['price_negotiable']) ? 1 : 0,
            'is_urgent'        => !empty($_POST['is_urgent'])        ? 1 : 0,
            'youtube_url'      => cleanInput($_POST['youtube_url']  ?? ''),
            'features'         => $_POST['features'] ?? [],
        ];

        // ── VALIDATE ──
        if (!$resolvedMakeName)
            $errors['make_id']      = 'Please select or type a make.';
        if (!$resolvedModelName)
            $errors['model_id']     = 'Please select or type a model.';
        if ($old['year'] < 1970 || $old['year'] > (int)date('Y') + 1)
            $errors['year']         = 'Please enter a valid year (1970–' . ((int)date('Y')+1) . ').';
        if ($old['price'] < 10000)
            $errors['price']        = 'Price must be at least Rs. 10,000.';
        if ($old['mileage'] < 0)
            $errors['mileage']      = 'Please enter a valid mileage.';
        if (!$old['condition'])
            $errors['condition']    = 'Please select a condition.';
        if (!$old['fuel_type'])
            $errors['fuel_type']    = 'Please select a fuel type.';
        if (!$old['transmission'])
            $errors['transmission'] = 'Please select a transmission.';
        if (!$old['body_type'])
            $errors['body_type']    = 'Please select a body type.';
        if (!$old['city'])
            $errors['city']         = 'Please enter a city.';
        if (strlen($old['description']) < 30)
            $errors['description']  = 'Description must be at least 30 characters.';

        // ── IMAGE UPLOAD ──
        $uploadedImages = [];
        $hasNewFiles    = !empty($_FILES['images']['name'][0]) && $_FILES['images']['name'][0] !== '';

        if (!$isEdit && !$hasNewFiles) {
            $errors['images'] = 'Please upload at least one photo.';
        } elseif ($hasNewFiles) {
            if (!is_dir(UPLOAD_PATH))             mkdir(UPLOAD_PATH,            0755, true);
            if (!is_dir(UPLOAD_PATH . 'thumbs/')) mkdir(UPLOAD_PATH . 'thumbs/',0755, true);

            $totalExisting = $isEdit ? count($editImages) : 0;
            $allowedNew    = MAX_USER_PHOTOS - $totalExisting;
            $fileCount     = 0;

            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                if ($fileCount >= $allowedNew) {
                    $errors['images'] = 'Maximum ' . MAX_USER_PHOTOS . ' photos allowed per listing.';
                    break;
                }
                if ($_FILES['images']['size'][$idx] > MAX_IMAGE_SIZE) {
                    $errors['images'] = 'Each image must be under 5 MB. Resize and try again.';
                    break;
                }
                $mime = mime_content_type($tmp);
                if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
                    $errors['images'] = 'Only JPG, PNG and WebP images are allowed.';
                    break;
                }
                $ext       = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
                $filename  = 'car_' . uniqid('', true) . '_' . time() . '.' . $ext;
                $dest      = UPLOAD_PATH . $filename;
                $thumbRel  = 'thumbs/' . $filename;
                $thumbDest = UPLOAD_PATH . $thumbRel;

                if (move_uploaded_file($tmp, $dest)) {
                    if (function_exists('imagecreatefromjpeg')) {
                        try {
                            switch($ext) {
                                case 'png':  $img = @imagecreatefrompng($dest); break;
                                case 'webp': $img = @imagecreatefromwebp($dest); break;
                                default:     $img = @imagecreatefromjpeg($dest); break;
                            }
                            if ($img) {
                                [$w, $h] = getimagesize($dest);
                                $tw    = THUMB_WIDTH;
                                $th    = (int)($tw / max($w / $h, 0.01));
                                $thumb = imagecreatetruecolor($tw, $th);
                                if ($ext === 'png') {
                                    imagealphablending($thumb, false);
                                    imagesavealpha($thumb, true);
                                }
                                imagecopyresampled($thumb, $img, 0,0,0,0, $tw,$th, $w,$h);
                                switch($ext) {
                                    case 'png':  imagepng($thumb, $thumbDest); break;
                                    case 'webp': imagewebp($thumb, $thumbDest, 85); break;
                                    default:     imagejpeg($thumb, $thumbDest, 85); break;
                                }
                                imagedestroy($img);
                                imagedestroy($thumb);
                            } else {
                                copy($dest, $thumbDest);
                            }
                        } catch (Throwable $ex) {
                            copy($dest, $thumbDest);
                        }
                    } else {
                        copy($dest, $thumbDest);
                    }
                    $uploadedImages[] = ['path' => $filename, 'thumb' => $thumbRel];
                    $fileCount++;
                }
            }

            if (empty($uploadedImages) && !$isEdit) {
                $errors['images'] = 'Image upload failed. Check that your server allows file uploads.';
            }
        }

        // ── SAVE TO DATABASE ──
        if (empty($errors)) {
            $featuresJson = json_encode(array_values((array)$old['features']));

            // Build slug from resolved names
            $slugMake  = $resolvedMakeName  ?: 'car';
            $slugModel = $resolvedModelName ?: 'listing';
            $slug      = makeSlug($slugMake . ' ' . $slugModel . ' ' . $old['year'] . ' ' . $old['city']);
            $baseSlug  = $slug;
            $si        = 1;
            $slugCheck  = $isEdit ? "SELECT 1 FROM cars WHERE slug=? AND id!=?" : "SELECT 1 FROM cars WHERE slug=?";
            $slugParams = $isEdit ? [$slug, $editId] : [$slug];
            while (DB::exists($slugCheck, $slugParams)) {
                $slug = $baseSlug . '-' . $si++;
                $slugParams[0] = $slug;
            }

            // Auto-create make — check name AND slug to prevent duplicates
            if (!$finalMakeId && $resolvedMakeName) {
                $mkSlug = makeSlug($resolvedMakeName);
                $existing = DB::value(
                    "SELECT id FROM makes WHERE LOWER(name)=LOWER(?) OR slug=? ORDER BY id ASC LIMIT 1",
                    [$resolvedMakeName, $mkSlug]
                );
                if ($existing) {
                    $finalMakeId = (int)$existing;
                } else {
                    try {
                        DB::execute("INSERT IGNORE INTO makes (name, slug) VALUES (?, ?)", [$resolvedMakeName, $mkSlug]);
                        $finalMakeId = (int)(DB::value("SELECT id FROM makes WHERE slug=? LIMIT 1", [$mkSlug]) ?: 0);
                    } catch (Throwable $ex) {
                        $finalMakeId = (int)(DB::value("SELECT id FROM makes WHERE LOWER(name)=LOWER(?) LIMIT 1", [$resolvedMakeName]) ?: 0);
                    }
                }
                $old['make_id'] = $finalMakeId;
            }

            // Auto-create model — check name (case-insensitive) AND slug to prevent duplicates
            if (!$finalModelId && $resolvedModelName && $finalMakeId) {
                $moSlug = makeSlug($resolvedModelName);
                $existing = DB::value(
                    "SELECT id FROM models WHERE make_id=? AND (LOWER(name)=LOWER(?) OR slug=?) ORDER BY id ASC LIMIT 1",
                    [$finalMakeId, $resolvedModelName, $moSlug]
                );
                if ($existing !== null && $existing !== false) {
                    $finalModelId = (int)$existing;
                } else {
                    try {
                        DB::execute(
                            "INSERT IGNORE INTO models (make_id, name, slug) VALUES (?, ?, ?)",
                            [$finalMakeId, $resolvedModelName, $moSlug]
                        );
                        $finalModelId = (int)(DB::value(
                            "SELECT id FROM models WHERE make_id=? AND slug=? LIMIT 1",
                            [$finalMakeId, $moSlug]
                        ) ?: 0);
                    } catch (Throwable $ex) {
                        $finalModelId = (int)(DB::value(
                            "SELECT id FROM models WHERE make_id=? AND LOWER(name)=LOWER(?) LIMIT 1",
                            [$finalMakeId, $resolvedModelName]
                        ) ?: 0);
                    }
                }
                $old['model_id'] = $finalModelId;
            }

            DB::beginTransaction();
            try {
                if ($isEdit) {
                    DB::execute(
                        "UPDATE cars SET
                         make_id=?, model_id=?, year=?, price=?, mileage=?, `condition`=?,
                         fuel_type=?, transmission=?, body_type=?, drive_type=?, engine_cc=?,
                         color=?, doors=?, seats=?, city=?, county=?, description=?,
                         price_negotiable=?, is_urgent=?, youtube_url=?, features=?,
                         updated_at=NOW()
                         WHERE id=? AND user_id=?",
                        [
                            $old['make_id'], $old['model_id'], $old['year'], $old['price'],
                            $old['mileage'], $old['condition'], $old['fuel_type'],
                            $old['transmission'], $old['body_type'], $old['drive_type'],
                            $old['engine_cc'], $old['color'], $old['doors'], $old['seats'],
                            $old['city'], $old['county'], $old['description'],
                            $old['price_negotiable'], $old['is_urgent'], $old['youtube_url'],
                            $featuresJson, $editId, Auth::id()
                        ]
                    );
                    $carId = $editId;
                } else {
                    $carId = DB::insert(
                        "INSERT INTO cars
                         (user_id, make_id, model_id, year, price, mileage, `condition`,
                          fuel_type, transmission, body_type, drive_type, engine_cc, color,
                          doors, seats, city, county, description, price_negotiable, is_urgent,
                          youtube_url, features, slug, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW(),NOW())",
                        [
                            Auth::id(), $old['make_id'], $old['model_id'], $old['year'],
                            $old['price'], $old['mileage'], $old['condition'], $old['fuel_type'],
                            $old['transmission'], $old['body_type'], $old['drive_type'],
                            $old['engine_cc'], $old['color'], $old['doors'], $old['seats'],
                            $old['city'], $old['county'], $old['description'],
                            $old['price_negotiable'], $old['is_urgent'], $old['youtube_url'],
                            $featuresJson, $slug
                        ]
                    );

                    if (!$carId) {
                        throw new Exception(
                            'INSERT returned 0. Check: (1) cars table exists in DB "' . DB_NAME . '", ' .
                            '(2) all required columns exist, (3) MySQL is running.'
                        );
                    }
                }

                // Save images
                foreach ($uploadedImages as $i => $img) {
                    DB::execute(
                        "INSERT INTO car_images (car_id, image_path, thumb_path, is_featured, sort_order)
                         VALUES (?, ?, ?, ?, ?)",
                        [$carId, $img['path'], $img['thumb'], ($i === 0 && !$isEdit) ? 1 : 0, $i]
                    );
                }

                logActivity($isEdit ? 'edit_listing' : 'post_listing', $carId, 'car');
                DB::commit();

                flash('success', $isEdit ? 'Listing updated successfully!' : 'Your car is now live!');
                redirect(BASE_URL . '/listing.php?id=' . $carId);

            } catch (Throwable $ex) {
                DB::rollback();
                // Always show the real error — helps during development AND alerts users in production
                $errors['general'] = APP_DEBUG
                    ? '⚠️ DB Error: ' . $ex->getMessage()
                    : 'Failed to save your listing. Please try again or contact support.';
                error_log('[CarSoko post-listing] ' . $ex->getMessage());
            }
        }
    } // end else (passed CSRF + RateLimit)
} // end if POST

// ── STATIC OPTION ARRAYS ──
$conditions    = ['new'=>'Brand New','foreign_used'=>'Imported','locally_used'=>'Locally Used'];
$fuelTypes     = ['Petrol','Diesel','Hybrid','Electric','LPG'];
$transmissions = ['Automatic','Manual','CVT','Semi-Automatic'];
$bodyTypes     = ['Sedan','Hatchback','SUV','Pickup','Van','Wagon','Coupe','Minibus','Convertible','Bus','Truck'];
$driveTypes    = ['2WD','4WD','AWD'];
$pakistaniCities  = ['Karachi','Lahore','Islamabad','Rawalpindi','Faisalabad','Multan','Peshawar','Quetta','Gujranwala','Sialkot','Bahawalpur','Sargodha','Sukkur','Larkana','Sheikhupura','Rahim Yar Khan','Jhang','Dera Ghazi Khan'];
$featuresList  = ['Air Conditioning','Power Steering','Electric Windows','Alloy Wheels','Sunroof','Leather Seats','Reverse Camera','Cruise Control','Bluetooth','Push Start','Navigation/GPS','ABS Brakes','Airbags','Parking Sensors','Keyless Entry','Heated Seats','360° Camera','Tow Bar'];

$pageTitle          = $isEdit ? 'Edit Listing' : 'Post Your Car';
$existingPhotoCount = count($editImages);
$canAddMore         = MAX_USER_PHOTOS - $existingPhotoCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($pageTitle) ?> | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --black:    #000000;
    --dark:     #0a0a0b;
    --card-bg:  #111114;
    --border:   rgba(255,255,255,0.08);
    --white:    #ffffff;
    --muted:    #a0a0a0;
    --accent:   #e8b84b;
    --accent2:  #ff6b35;
    --green:    #22c55e;
    --red:      #ef4444;
    --gradient: linear-gradient(135deg,#e8b84b 0%,#ff6b35 100%);
    --fh:       'Bebas Neue', sans-serif;
    --fb:       'Inter', sans-serif;
    --radius:   14px;
    --radius-lg:24px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--fb);font-size:15px;line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
.container{max-width:900px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{max-width:1280px;display:flex;align-items:center;height:64px;gap:28px}
.logo{font-family:var(--fh);font-size:24px;font-weight:800;display:flex;align-items:center;flex-shrink:0}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:7px;height:7px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}
.nav-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .25s;border:none;font-family:var(--fb)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}

/* PAGE HEADER */
.page-header{padding:36px 0 24px;border-bottom:1px solid var(--border);margin-bottom:32px}
.page-header-inner{display:flex;align-items:center;gap:16px}
.page-header-icon{width:52px;height:52px;background:var(--gradient);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#0a0a0b;flex-shrink:0}
.page-header h1{font-family:var(--fh);font-size:clamp(22px,4vw,28px);font-weight:800}
.page-header p{font-size:14px;color:var(--muted);margin-top:4px}

/* STEP PROGRESS */
.steps{display:flex;margin-bottom:32px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.step{flex:1;display:flex;align-items:center;gap:10px;padding:14px 20px;border-right:1px solid var(--border)}
.step:last-child{border-right:none}
.step.active .step-num{background:var(--gradient);color:#0a0a0b}
.step.done .step-num{background:var(--green);color:#fff}
.step-num{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;transition:all .3s}
.step-label{font-size:13px;font-weight:600;color:var(--muted)}
.step.active .step-label,.step.done .step-label{color:var(--white)}
.step.done .step-label{color:var(--green)}

/* FORM SECTIONS */
.form-section{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:24px;overflow:hidden}
.form-section-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.form-section-header i{color:var(--accent);font-size:16px;width:20px;text-align:center}
.form-section-header h2{font-family:var(--fh);font-size:16px;font-weight:700}
.form-section-header .badge{margin-left:auto;font-size:11px;background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.25);color:var(--accent);padding:3px 10px;border-radius:20px;font-weight:600}
.form-body{padding:22px}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}
.col-span-2{grid-column:span 2}

.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
.form-group label .req{color:var(--red);margin-left:2px}
.form-group input,
.form-group select,
.form-group textarea{
    background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);
    padding:11px 14px;border-radius:var(--radius);font-size:14px;font-family:var(--fb);
    outline:none;transition:border-color .2s,box-shadow .2s;width:100%;-webkit-appearance:none
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
    border-color:var(--accent);box-shadow:0 0 0 3px rgba(232,184,75,.1)
}
.form-group select option{background:var(--dark)}
.form-group textarea{resize:vertical;min-height:130px;line-height:1.6}
.form-group .hint{font-size:11px;color:var(--muted);margin-top:3px}
.form-group.has-error input,.form-group.has-error select,.form-group.has-error textarea{border-color:var(--red)}
.field-error{font-size:12px;color:var(--red);margin-top:4px;display:flex;align-items:center;gap:4px}

/* CUSTOM MAKE/MODEL TEXT INPUT */
.custom-input-row{display:none;margin-top:8px;animation:slideDown .2s ease}
.custom-input-row.show{display:block}
@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.custom-input-row input{
    background:rgba(232,184,75,.06);border:1px solid rgba(232,184,75,.3);
    color:var(--white);padding:10px 14px;border-radius:var(--radius);
    font-size:14px;font-family:var(--fb);outline:none;width:100%;transition:border-color .2s
}
.custom-input-row input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(232,184,75,.1)}
.custom-input-row label{font-size:11px;color:var(--accent);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;display:block}

/* PRICE INPUT */
.price-input-wrap{position:relative}
.price-input-wrap input{padding-left:52px}
.price-prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:13px;font-weight:600;color:var(--muted);pointer-events:none}

/* TOGGLE */
.toggle-group{display:flex;align-items:center;gap:12px;padding:14px 0;cursor:pointer}
.toggle-group input[type=checkbox]{display:none}
.toggle-track{width:44px;height:24px;background:rgba(255,255,255,.1);border-radius:50px;position:relative;transition:background .25s;flex-shrink:0}
.toggle-track::after{content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;left:3px;transition:transform .25s}
.toggle-group input:checked + .toggle-track{background:var(--gradient)}
.toggle-group input:checked + .toggle-track::after{transform:translateX(20px)}
.toggle-label{font-size:14px}
.toggle-sub{font-size:12px;color:var(--muted)}

/* IMAGE UPLOAD */
.upload-zone{
    border:2px dashed rgba(255,255,255,.12);border-radius:var(--radius-lg);
    padding:44px 20px;text-align:center;cursor:pointer;transition:all .25s;
    background:rgba(255,255,255,.02);position:relative;
}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--accent);background:rgba(232,184,75,.04)}
.upload-zone input[type=file]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:10;font-size:0}
.upload-zone-inner{position:relative;z-index:1;pointer-events:none}
.upload-icon{font-size:40px;color:var(--accent);margin-bottom:14px;opacity:.8}
.upload-title{font-size:15px;font-weight:600;margin-bottom:6px}
.upload-sub{font-size:13px;color:var(--muted)}
.upload-limit{font-size:11px;color:var(--muted);margin-top:8px;opacity:.7}
.upload-btn{
    display:inline-flex;align-items:center;gap:7px;
    background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);
    color:var(--accent);padding:9px 22px;border-radius:50px;font-size:13px;
    font-weight:600;margin-top:14px;pointer-events:none;font-family:var(--fb)
}

/* Photo counter */
.photo-counter{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;font-size:13px}
.photo-counter-bar{flex:1;height:4px;background:rgba(255,255,255,.07);border-radius:4px;margin:0 12px;overflow:hidden}
.photo-counter-fill{height:100%;border-radius:4px;background:var(--gradient);transition:width .4s ease}
.photo-counter-text{font-size:12px;color:var(--muted);white-space:nowrap}

/* IMAGE PREVIEW GRID */
.image-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-top:16px}
.image-preview-item{position:relative;border-radius:var(--radius);overflow:hidden;aspect-ratio:4/3;background:#111;animation:imgIn .3s ease both}
@keyframes imgIn{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:scale(1)}}
.image-preview-item img{width:100%;height:100%;object-fit:cover}
.img-remove{position:absolute;top:4px;right:4px;width:22px;height:22px;background:rgba(239,68,68,.9);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;color:#fff;border:none;transition:transform .2s}
.img-remove:hover{transform:scale(1.15)}
.img-badge{position:absolute;bottom:4px;left:4px;background:var(--gradient);color:#0a0a0b;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px}
.img-number{position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.6);color:rgba(255,255,255,.8);font-size:10px;font-weight:600;padding:2px 5px;border-radius:4px}

/* FEATURES */
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.feature-check{display:flex;align-items:center;gap:8px;padding:9px 12px;background:rgba(0,0,0,.25);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:all .2s;font-size:13px;user-select:none}
.feature-check:hover{border-color:rgba(232,184,75,.3);background:rgba(232,184,75,.04)}
.feature-check input{accent-color:var(--accent);width:15px;height:15px;cursor:pointer;flex-shrink:0}
.feature-check.checked{border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.06);color:var(--white)}

/* ALERT */
.alert{padding:14px 18px;border-radius:var(--radius);margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;font-size:14px;line-height:1.5}
.alert i{flex-shrink:0;margin-top:1px}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}
.alert-info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#93c5fd}

/* STICKY SUBMIT */
.sticky-bar{position:sticky;bottom:0;z-index:100;background:rgba(17,17,20,.95);backdrop-filter:blur(20px);border-top:1px solid var(--border);padding:14px 0}
.sticky-bar .container{max-width:900px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.submit-btn{padding:13px 40px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:15px;border:none;border-radius:50px;cursor:pointer;font-family:var(--fb);display:flex;align-items:center;gap:9px;transition:all .25s}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}

@media(max-width:700px){
    .form-grid,.form-grid-3{grid-template-columns:1fr}
    .col-span-2{grid-column:span 1}
    .features-grid{grid-template-columns:1fr 1fr}
    .steps{display:none}
    .image-preview-grid{grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container" style="max-width:1280px">
        <a href="index.php" class="logo">
            <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
        </a>
        <div class="nav-right">
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php if ($isEdit): ?>
            <a href="listing.php?id=<?= $editId ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View Listing</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container" style="padding-top:36px;padding-bottom:120px">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-inner">
            <div class="page-header-icon">
                <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-car' ?>"></i>
            </div>
            <div>
                <h1><?= $isEdit ? 'Edit Your Listing' : 'Post Your Car for Sale' ?></h1>
                <p><?= $isEdit ? 'Update your listing details below.' : "It's free! Fill in the details — the more you add, the more buyers trust you." ?></p>
            </div>
        </div>
    </div>

    <!-- STEPS -->
    <div class="steps">
        <div class="step active" id="step1"><div class="step-num">1</div><div class="step-label">Car Details</div></div>
        <div class="step" id="step2"><div class="step-num">2</div><div class="step-label">Specs &amp; Location</div></div>
        <div class="step" id="step3"><div class="step-num">3</div><div class="step-label">Photos</div></div>
        <div class="step" id="step4"><div class="step-num">4</div><div class="step-label">Description</div></div>
    </div>

    <!-- GLOBAL ERRORS -->
    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>
    <?php showFlash('error'); ?>
    <?php if (!empty($errors) && count($errors) > 1): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span>Please fix the highlighted fields below before publishing.</span></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════
         FORM — submit button is INSIDE the form
    ════════════════════════════════════════════ -->
    <form method="POST" enctype="multipart/form-data" id="listingForm" novalidate>
        <?= CSRF::field() ?>

        <!-- SECTION 1 — CAR IDENTITY -->
        <div class="form-section" id="sec1">
            <div class="form-section-header">
                <i class="fas fa-car"></i>
                <h2>Car Details</h2>
                <span class="badge">Step 1 of 4</span>
            </div>
            <div class="form-body">
                <div class="form-grid">

                    <!-- MAKE -->
                    <div class="form-group <?= isset($errors['make_id']) ? 'has-error' : '' ?>">
                        <label>Make <span class="req">*</span></label>
                        <input type="text" name="custom_make" id="customMakeInput"
                               value="<?= e($old['custom_make'] ?? $old['make_name'] ?? '') ?>"
                               placeholder="e.g. Toyota, Honda, BMW, Nissan…"
                               maxlength="80" autocomplete="off">
                        <?php if (isset($errors['make_id'])): ?>
                        <div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['make_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- MODEL -->
                    <div class="form-group <?= isset($errors['model_id']) ? 'has-error' : '' ?>">
                        <label>Model <span class="req">*</span></label>
                        <input type="text" name="custom_model" id="customModelInput"
                               value="<?= e($old['custom_model'] ?? $old['model_name'] ?? '') ?>"
                               placeholder="e.g. Corolla, Fielder, Axio, Hilux…"
                               maxlength="80" autocomplete="off">
                        <?php if (isset($errors['model_id'])): ?>
                        <div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['model_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- YEAR -->
                    <div class="form-group <?= isset($errors['year']) ? 'has-error' : '' ?>">
                        <label>Year <span class="req">*</span></label>
                        <select name="year">
                            <option value="">— Select Year —</option>
                            <?php for ($y = (int)date('Y') + 1; $y >= 1970; $y--): ?>
                            <option value="<?= $y ?>" <?= (int)($old['year'] ?? 0) === $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <?php if (isset($errors['year'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['year']) ?></div><?php endif; ?>
                    </div>

                    <!-- CONDITION -->
                    <div class="form-group <?= isset($errors['condition']) ? 'has-error' : '' ?>">
                        <label>Condition <span class="req">*</span></label>
                        <select name="condition">
                            <option value="">— Select Condition —</option>
                            <?php foreach ($conditions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($old['condition'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['condition'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['condition']) ?></div><?php endif; ?>
                    </div>

                    <!-- PRICE -->
                    <div class="form-group <?= isset($errors['price']) ? 'has-error' : '' ?>">
                        <label>Asking Price (PKR) <span class="req">*</span></label>
                        <div class="price-input-wrap">
                            <span class="price-prefix">Rs.</span>
                            <input type="number" name="price" value="<?= e($old['price'] ?? '') ?>"
                                   placeholder="e.g. 1850000" min="10000" max="100000000">
                        </div>
                        <?php if (isset($errors['price'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>

                    <!-- MILEAGE -->
                    <div class="form-group <?= isset($errors['mileage']) ? 'has-error' : '' ?>">
                        <label>Mileage (km) <span class="req">*</span></label>
                        <input type="number" name="mileage" value="<?= e($old['mileage'] ?? '') ?>"
                               placeholder="e.g. 45000" min="0" max="9999999">
                        <?php if (isset($errors['mileage'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['mileage']) ?></div><?php endif; ?>
                    </div>
                </div>

                <!-- TOGGLES -->
                <label class="toggle-group" for="negotiable">
                    <input type="checkbox" id="negotiable" name="price_negotiable" value="1" <?= !empty($old['price_negotiable']) ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                    <div><div class="toggle-label">Price is Negotiable</div><div class="toggle-sub">Let buyers know they can make an offer</div></div>
                </label>
                <label class="toggle-group" for="urgent">
                    <input type="checkbox" id="urgent" name="is_urgent" value="1" <?= !empty($old['is_urgent']) ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                    <div><div class="toggle-label">Mark as Urgent Sale</div><div class="toggle-sub">Adds an "Urgent" badge — attracts more attention</div></div>
                </label>
            </div>
        </div>

        <!-- SECTION 2 — SPECS & LOCATION -->
        <div class="form-section" id="sec2">
            <div class="form-section-header">
                <i class="fas fa-sliders"></i>
                <h2>Specifications &amp; Location</h2>
                <span class="badge">Step 2 of 4</span>
            </div>
            <div class="form-body">
                <div class="form-grid">
                    <div class="form-group <?= isset($errors['fuel_type']) ? 'has-error' : '' ?>">
                        <label>Fuel Type <span class="req">*</span></label>
                        <select name="fuel_type">
                            <option value="">— Select Fuel —</option>
                            <?php foreach ($fuelTypes as $f): ?>
                            <option value="<?= $f ?>" <?= ($old['fuel_type'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['fuel_type'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['fuel_type']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['transmission']) ? 'has-error' : '' ?>">
                        <label>Transmission <span class="req">*</span></label>
                        <select name="transmission">
                            <option value="">— Select Transmission —</option>
                            <?php foreach ($transmissions as $t): ?>
                            <option value="<?= $t ?>" <?= ($old['transmission'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['transmission'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['transmission']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['body_type']) ? 'has-error' : '' ?>">
                        <label>Body Type <span class="req">*</span></label>
                        <select name="body_type">
                            <option value="">— Select Body Type —</option>
                            <?php foreach ($bodyTypes as $b): ?>
                            <option value="<?= $b ?>" <?= ($old['body_type'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['body_type'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['body_type']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Drive Type</label>
                        <select name="drive_type">
                            <option value="">— Select Drive —</option>
                            <?php foreach ($driveTypes as $d): ?>
                            <option value="<?= $d ?>" <?= ($old['drive_type'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Engine (cc)</label>
                        <input type="number" name="engine_cc" value="<?= e($old['engine_cc'] ?? '') ?>"
                               placeholder="e.g. 1800" min="0" max="10000">
                    </div>

                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color" value="<?= e($old['color'] ?? '') ?>"
                               placeholder="e.g. Pearl White" maxlength="50">
                    </div>

                    <div class="form-group">
                        <label>Doors</label>
                        <select name="doors">
                            <option value="">— Doors —</option>
                            <?php foreach ([2,3,4,5] as $d): ?>
                            <option value="<?= $d ?>" <?= (int)($old['doors'] ?? 0) === $d ? 'selected' : '' ?>><?= $d ?> doors</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Seats</label>
                        <select name="seats">
                            <option value="">— Seats —</option>
                            <?php foreach ([2,4,5,6,7,8,9,10,11,12,14] as $s): ?>
                            <option value="<?= $s ?>" <?= (int)($old['seats'] ?? 0) === $s ? 'selected' : '' ?>><?= $s ?> seats</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group <?= isset($errors['city']) ? 'has-error' : '' ?>">
                        <label>City <span class="req">*</span></label>
                        <select name="city">
                            <option value="">— Select City —</option>
                            <?php foreach ($pakistaniCities as $c): ?>
                            <option value="<?= $c ?>" <?= ($old['city'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['city'])): ?><div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['city']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Province / State</label>
                        <input type="text" name="county" value="<?= e($old['county'] ?? '') ?>" placeholder="e.g. Punjab" maxlength="80">
                    </div>
                </div>

                <!-- FEATURES -->
                <div style="margin-top:22px">
                    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">
                        Features &amp; Extras
                    </div>
                    <div class="features-grid">
                        <?php foreach ($featuresList as $feature):
                            $checked = in_array($feature, (array)($old['features'] ?? $carFeatures ?? [])); ?>
                        <label class="feature-check <?= $checked ? 'checked' : '' ?>">
                            <input type="checkbox" name="features[]" value="<?= e($feature) ?>"
                                   <?= $checked ? 'checked' : '' ?>
                                   onchange="this.closest('.feature-check').classList.toggle('checked',this.checked)">
                            <?= e($feature) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3 — PHOTOS -->
        <div class="form-section" id="sec3">
            <div class="form-section-header">
                <i class="fas fa-images"></i>
                <h2>Photos</h2>
                <span class="badge">Up to <?= MAX_USER_PHOTOS ?> photos</span>
            </div>
            <div class="form-body">

                <div class="photo-counter" id="photoCounter">
                    <span id="photoCountText" style="font-size:13px;color:var(--muted)">
                        <?= $existingPhotoCount ?> / <?= MAX_USER_PHOTOS ?> photos
                    </span>
                    <div class="photo-counter-bar">
                        <div class="photo-counter-fill" id="photoCountFill"
                             style="width:<?= $existingPhotoCount > 0 ? round($existingPhotoCount/MAX_USER_PHOTOS*100) : 0 ?>%"></div>
                    </div>
                    <span class="photo-counter-text" id="photoCountSlots">
                        <?= $canAddMore ?> slot<?= $canAddMore !== 1 ? 's' : '' ?> free
                    </span>
                </div>

                <?php if ($isEdit && !empty($editImages)): ?>
                <div style="margin-bottom:16px">
                    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px">
                        Current Photos
                    </div>
                    <div class="image-preview-grid" id="currentImages">
                        <?php foreach ($editImages as $i => $img): ?>
                        <div class="image-preview-item" id="eimg_<?= $img['id'] ?>">
                            <img src="<?= e(carImageUrl($img['image_path'], true)) ?>" alt="Car photo <?= $i+1 ?>">
                            <?php if ($img['is_featured']): ?>
                            <div class="img-badge">Cover</div>
                            <?php endif; ?>
                            <div class="img-number"><?= $i+1 ?></div>
                            <button type="button" class="img-remove" onclick="deleteExistingImage(<?= $img['id'] ?>, this)" title="Remove this photo">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($canAddMore > 0): ?>
                <div class="upload-zone" id="uploadZone">
                    <input type="file"
                           name="images[]"
                           id="imageInput"
                           multiple
                           accept="image/jpeg,image/jpg,image/png,image/webp"
                           onchange="onFilesSelected(this)">
                    <div class="upload-zone-inner">
                        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="upload-title">Drag &amp; drop photos here</div>
                        <div class="upload-sub">or click anywhere in this box to browse files</div>
                        <div class="upload-limit">
                            JPG, PNG or WebP &nbsp;·&nbsp; Max 5 MB each &nbsp;·&nbsp;
                            <strong style="color:var(--accent)"><?= $canAddMore ?> more photo<?= $canAddMore !== 1 ? 's' : '' ?> allowed</strong>
                        </div>
                        <div class="upload-btn"><i class="fas fa-folder-open"></i> Browse Files</div>
                    </div>
                </div>
                <div class="image-preview-grid" id="newImagePreview"></div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Maximum <?= MAX_USER_PHOTOS ?> photos reached. Remove existing photos to add new ones.
                </div>
                <?php endif; ?>

                <?php if (isset($errors['images'])): ?>
                <div class="field-error" style="margin-top:10px;font-size:13px">
                    <i class="fas fa-circle-exclamation"></i> <?= e($errors['images']) ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:20px" class="form-group">
                    <label>YouTube Video URL <span style="font-weight:400;text-transform:none;color:var(--muted)">(optional)</span></label>
                    <input type="url" name="youtube_url" value="<?= e($old['youtube_url'] ?? '') ?>"
                           placeholder="https://youtube.com/watch?v=...">
                    <div class="hint">💡 Listings with videos get 3× more inquiries</div>
                </div>
            </div>
        </div>

        <!-- SECTION 4 — DESCRIPTION -->
        <div class="form-section" id="sec4">
            <div class="form-section-header">
                <i class="fas fa-align-left"></i>
                <h2>Description</h2>
                <span class="badge">Step 4 of 4</span>
            </div>
            <div class="form-body">
                <div class="form-group <?= isset($errors['description']) ? 'has-error' : '' ?>">
                    <label>Your Listing Description <span class="req">*</span></label>
                    <textarea name="description" id="descTextarea" rows="9"
                              placeholder="Describe your car honestly and in detail. Include:
• Service history (or none)
• Any accidents or bodywork done
• Reason for selling
• Recent repairs / new parts
• Tyre condition
• What's included (spare tyre, jack, logbook, etc.)"><?= e($old['description'] ?? '') ?></textarea>
                    <div class="hint" id="charCount" style="display:flex;justify-content:space-between">
                        <span>Minimum 30 characters required.</span>
                        <span id="charNum" style="color:var(--muted)">0 / 2000</span>
                    </div>
                    <?php if (isset($errors['description'])): ?>
                    <div class="field-error"><i class="fas fa-circle-exclamation"></i><?= e($errors['description']) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:12px;padding:14px 16px;background:rgba(232,184,75,.07);border:1px solid rgba(232,184,75,.2);border-radius:var(--radius);font-size:13px;color:rgba(245,245,240,.7);line-height:1.7">
                    <strong style="color:var(--accent)">✦ Listing tips:</strong>
                    Mention service history, accident history (if any), why you're selling, recent repairs, tyre condition, and what's included.
                    <strong style="color:var(--white)">Honest listings sell faster.</strong>
                </div>
            </div>
        </div>

        <!-- ═══ SUBMIT BUTTON — INSIDE THE FORM ═══ -->
        <div class="sticky-bar">
            <div class="container">
                <div style="font-size:13px;color:var(--muted)">
                    <?php if ($isEdit): ?>
                    Editing: <strong style="color:var(--white)"><?= e(($car['make_name'] ?? '') . ' ' . ($car['model_name'] ?? '') . ' ' . ($car['year'] ?? '')) ?></strong>
                    <?php else: ?>
                    <strong style="color:var(--white)">Free listing</strong> — visible to thousands of buyers
                    <?php endif; ?>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas <?= $isEdit ? 'fa-save' : 'fa-paper-plane' ?>" id="submitIcon"></i>
                    <span id="submitText"><?= $isEdit ? 'Save Changes' : 'Publish Listing' ?></span>
                </button>
            </div>
        </div>

    </form>
</div>

<script>
// ================================================================
//  MAKE / MODEL DROPDOWNS
// ================================================================
const fallbackModels = {
    'toyota':     ['Corolla','Fielder','Axio','Land Cruiser','Land Cruiser Prado','Hilux','RAV4','Vitz','Rush','Harrier','Noah','Alphard','Voxy','Wish','Probox','Sienta','Fortuner','Yaris','Camry','CHR'],
    'nissan':     ['X-Trail','Note','March','Tiida','Juke','Navara','Leaf','Serena','Wingroad','Bluebird','Patrol','Murano'],
    'honda':      ['Fit/Jazz','Civic','CR-V','Freed','Stream','Vezel','Accord','HR-V','Pilot','Odyssey'],
    'mazda':      ['Demio','CX-5','CX-3','Axela','Atenza','BT-50','CX-9','MX-5'],
    'subaru':     ['Forester','Outback','Impreza','Legacy','XV','WRX','BRZ'],
    'mercedes':   ['C-Class','E-Class','GLE','GLC','A-Class','CLA','S-Class','ML-Class','B-Class'],
    'bmw':        ['3 Series','5 Series','7 Series','X1','X3','X5','X6','1 Series','M3','M5'],
    'mitsubishi': ['Outlander','Pajero','Eclipse Cross','L200','Mirage','ASX','Galant','Colt'],
    'volkswagen': ['Golf','Polo','Tiguan','Passat','Touareg','Amarok','Caddy'],
    'hyundai':    ['Tucson','Elantra','i10','i20','Santa Fe','Creta','Accent','Sonata','Kona'],
    'isuzu':      ['D-Max','MU-X','NQR','FVR','ELF','FSR'],
    'land-rover': ['Defender','Discovery','Range Rover','Range Rover Sport','Freelander','Discovery Sport'],
    'ford':       ['Ranger','F-150','Escape','Explorer','Everest','Focus','Mustang','Transit'],
    'kia':        ['Sportage','Picanto','Rio','Sorento','Stinger','Seltos','Carnival'],
    'peugeot':    ['3008','2008','408','508','Partner','Boxer','207','308'],
    'suzuki':     ['Swift','Vitara','Jimny','Alto','Baleno','Ertiga','Dzire'],
    'jeep':       ['Wrangler','Cherokee','Grand Cherokee','Compass','Renegade'],
    'lexus':      ['LX','RX','GX','NX','ES','IS','LS','UX'],
    'audi':       ['A3','A4','A6','Q5','Q7','Q3','TT','RS6'],
    'other':      [],
};

document.addEventListener('DOMContentLoaded', function() {
    const ta = document.getElementById('descTextarea');
    if (ta) ta.dispatchEvent(new Event('input'));
});

// ================================================================
//  PHOTO UPLOAD
// ================================================================
let selectedFiles = [];
const MAX_PHOTOS  = <?= MAX_USER_PHOTOS ?>;
let existingCount = <?= $existingPhotoCount ?>;

function onFilesSelected(input) {
    const files     = Array.from(input.files);
    const available = MAX_PHOTOS - existingCount;
    const toAdd     = files.slice(0, available);

    if (files.length > available) {
        showPhotoError('You can only add ' + available + ' more photo' + (available !== 1 ? 's' : '') + '. Extra files were ignored.');
    } else {
        clearPhotoError();
    }

    selectedFiles = toAdd;
    renderNewPreviews(toAdd);
    updatePhotoCounter(existingCount + toAdd.length);
}

function renderNewPreviews(files) {
    const grid = document.getElementById('newImagePreview');
    if (!grid) return;
    grid.innerHTML = '';
    files.forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'image-preview-item';
            div.id = 'nimg_' + i;
            div.innerHTML =
                '<img src="' + e.target.result + '" alt="Preview ' + (i+1) + '">' +
                (i === 0 && existingCount === 0 ? '<div class="img-badge">Cover</div>' : '') +
                '<div class="img-number">' + (existingCount + i + 1) + '</div>' +
                '<button type="button" class="img-remove" onclick="removeNewPreview(' + i + ')" title="Remove"><i class="fas fa-times"></i></button>';
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function removeNewPreview(idx) {
    selectedFiles.splice(idx, 1);
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('imageInput').files = dt.files;
    renderNewPreviews(selectedFiles);
    updatePhotoCounter(existingCount + selectedFiles.length);
    clearPhotoError();
}

function deleteExistingImage(imgId, btn) {
    if (!confirm('Remove this photo from the listing?')) return;
    btn.disabled = true;
    fetch('ajax/delete-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'image_id=' + imgId + '&_csrf=<?= e(CSRF::token()) ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const el = document.getElementById('eimg_' + imgId);
            if (el) {
                el.style.transition = 'all .3s';
                el.style.opacity    = '0';
                el.style.transform  = 'scale(.8)';
                setTimeout(() => {
                    el.remove();
                    existingCount = Math.max(0, existingCount - 1);
                    updatePhotoCounter(existingCount + selectedFiles.length);
                    const zone = document.getElementById('uploadZone');
                    if (zone) zone.style.display = '';
                }, 300);
            }
        } else {
            alert(d.message || 'Failed to delete photo.');
            btn.disabled = false;
        }
    })
    .catch(() => { alert('Delete failed. Try again.'); btn.disabled = false; });
}

function updatePhotoCounter(count) {
    const fill  = document.getElementById('photoCountFill');
    const text  = document.getElementById('photoCountText');
    const slots = document.getElementById('photoCountSlots');
    const free  = MAX_PHOTOS - count;
    if (fill)  fill.style.width = Math.min(100, count / MAX_PHOTOS * 100) + '%';
    if (text)  text.textContent = count + ' / ' + MAX_PHOTOS + ' photos';
    if (slots) slots.textContent = free + ' slot' + (free !== 1 ? 's' : '') + ' free';
    if (fill)  fill.style.background = count >= MAX_PHOTOS
        ? 'linear-gradient(90deg,#ef4444,#dc2626)'
        : 'linear-gradient(135deg,#e8b84b,#ff6b35)';
}

function showPhotoError(msg) {
    let el = document.getElementById('photoErrorMsg');
    if (!el) {
        el = document.createElement('div');
        el.id = 'photoErrorMsg';
        el.className = 'field-error';
        el.style.marginTop = '10px';
        document.getElementById('newImagePreview')?.parentNode?.appendChild(el);
    }
    el.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + msg;
}
function clearPhotoError() {
    const el = document.getElementById('photoErrorMsg');
    if (el) el.remove();
}

// DRAG & DROP
const zone = document.getElementById('uploadZone');
zone?.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone?.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
zone?.addEventListener('drop',      e  => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const input = document.getElementById('imageInput');
    const dt    = new DataTransfer();
    Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')).forEach(f => dt.items.add(f));
    input.files = dt.files;
    onFilesSelected(input);
});

// ================================================================
//  CHAR COUNTER
// ================================================================
const descArea = document.getElementById('descTextarea');
const charNum  = document.getElementById('charNum');
descArea?.addEventListener('input', function() {
    const len = this.value.length;
    if (charNum) {
        charNum.textContent = len + ' / 2000';
        charNum.style.color = len < 30 ? 'var(--red)' : len > 1800 ? 'var(--accent)' : 'var(--muted)';
    }
});

// ================================================================
//  STEP HIGHLIGHT (scroll-based)
// ================================================================
const secSteps = [
    [document.getElementById('sec1'), document.getElementById('step1')],
    [document.getElementById('sec2'), document.getElementById('step2')],
    [document.getElementById('sec3'), document.getElementById('step3')],
    [document.getElementById('sec4'), document.getElementById('step4')],
];
window.addEventListener('scroll', function() {
    let cur = 0;
    secSteps.forEach(([sec], i) => { if (sec && window.scrollY >= sec.offsetTop - 200) cur = i; });
    secSteps.forEach(([, step], i) => {
        if (!step) return;
        step.classList.toggle('active', i === cur);
        step.classList.toggle('done',   i < cur);
    });
}, { passive: true });

// ================================================================
//  SUBMIT GUARD
// ================================================================
document.getElementById('listingForm')?.addEventListener('submit', function(e) {
    const makeFinal  = document.getElementById('customMakeInput').value.trim();
    const modelFinal = document.getElementById('customModelInput').value.trim();
    const year      = document.querySelector('[name=year]').value;
    const price     = document.querySelector('[name=price]').value;
    const desc      = document.getElementById('descTextarea').value.trim();

    if (!makeFinal)               { alert('Please type a make (e.g. Toyota).');          e.preventDefault(); return; }
    if (!modelFinal)              { alert('Please type a model (e.g. Corolla).');        e.preventDefault(); return; }
    if (!year)                    { alert('Please select a year.');                      e.preventDefault(); return; }
    if (!price || price < 10000)  { alert('Please enter a valid price (min Rs. 10,000).'); e.preventDefault(); return; }
    if (desc.length < 30)         { alert('Description must be at least 30 characters.'); e.preventDefault(); return; }

    const btn  = document.getElementById('submitBtn');
    const icon = document.getElementById('submitIcon');
    const txt  = document.getElementById('submitText');
    btn.disabled    = true;
    icon.className  = 'fas fa-spinner fa-spin';
    txt.textContent = <?= $isEdit ? "'Saving…'" : "'Publishing…'" ?>;
});
</script>
</body>
</html>