<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';

requireStaff(true);
// This is a real action, so it counts as activity and refreshes the idle timer
$_SESSION['last_active'] = time();

header('Content-Type: application/json');
header('Cache-Control: no-store');

const PHOTO_DIR = __DIR__ . '/../../images/items/';

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateItemName($name) {
    if ($name === '') {
        return 'Item name is required.';
    }
    if (strpos($name, '  ') !== false) {
        return 'Double space is not allowed.';
    }
    if ($name !== trim($name)) {
        return 'Spaces at the start or end are not allowed.';
    }
    if (preg_match('/[^A-Za-z0-9 \-]/', $name)) {
        return 'Item name may contain letters, digits, spaces and hyphens only.';
    }
    if (strlen($name) < 2 || strlen($name) > 60) {
        return 'Item name must be 2 to 60 characters.';
    }
    return '';
}

function validateItemQuantity($value) {
    if ($value === '') {
        return 'Quantity is required.';
    }
    if (!preg_match('/^\d{1,3}$/', $value) || (int)$value < 1) {
        return 'Quantity must be a whole number from 1 to 999.';
    }
    return '';
}

// Returns [error, extension]. finfo reads the real file content, so a renamed file cannot pass
function checkPhoto() {
    $file = $_FILES['photo'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['Please choose a photo.', ''];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > 2 * 1024 * 1024) {
        return ['Photo must be 2 MB or smaller.', ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['The photo could not be uploaded.', ''];
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime])) {
        return ['Photo must be a JPG, PNG or WEBP image.', ''];
    }
    return ['', $types[$mime]];
}

function addItem($db) {
    $name = $_POST['name'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

    $errors = [];
    $errors['name'] = validateItemName($name);
    if ($errors['name'] === '') {
        $stmt = $db->prepare('SELECT 1 FROM items WHERE name = ? AND is_deleted = 0');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors['name'] = 'This item is already in the list.';
        }
    }
    $errors['quantity'] = validateItemQuantity($quantity);
    [$errors['photo'], $ext] = checkPhoto();
    $errors = array_filter($errors);
    if ($errors) {
        reply(['errors' => $errors]);
    }

    // A random file name, so the uploaded name can never be used to attack the server
    $file = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], PHOTO_DIR . $file)) {
        reply(['errors' => ['photo' => 'The photo could not be saved.']]);
    }

    $db->prepare('INSERT INTO items (name, quantity, photo) VALUES (?, ?, ?)')->execute([$name, (int)$quantity, $file]);
    logEvent('ITEM_ADDED', $_SESSION['username'], $_SESSION['user_id'], $name . ' x' . (int)$quantity);
    reply(['ok' => true]);
}

function itemName($db, $id) {
    $stmt = $db->prepare('SELECT name FROM items WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

function addStock($db) {
    $id = (int)($_POST['id'] ?? 0);
    $amount = $_POST['amount'] ?? '';

    $error = validateItemQuantity($amount);
    if ($error !== '') {
        reply(['errors' => ['amount' => str_replace('Quantity', 'Stock', $error)]]);
    }

    // One query adds the stock and refuses a total above 999, so two staff clicking together cannot break it
    $stmt = $db->prepare('UPDATE items SET quantity = quantity + ? WHERE id = ? AND is_deleted = 0 AND quantity + ? <= 999');
    $stmt->execute([(int)$amount, $id, (int)$amount]);
    if ($stmt->rowCount() === 0) {
        reply(['errors' => ['amount' => 'Total stock cannot go above 999.']]);
    }

    logEvent('ITEM_STOCK_ADDED', $_SESSION['username'], $_SESSION['user_id'], itemName($db, $id) . ' +' . (int)$amount);
    reply(['ok' => true]);
}

// At least 1 must stay, and copies that are approved or borrowed cannot be removed
function removeStock($db) {
    $id = (int)($_POST['id'] ?? 0);
    $amount = $_POST['amount'] ?? '';

    $error = validateItemQuantity($amount);
    if ($error !== '') {
        reply(['errors' => ['amount' => str_replace('Quantity', 'Stock', $error)]]);
    }

    $taken = "COALESCE((SELECT SUM(b.quantity) FROM borrow_requests b WHERE b.item_id = items.id AND b.status IN ('approved', 'borrowed')), 0)";
    $stmt = $db->prepare(
        "UPDATE items SET quantity = quantity - ? WHERE id = ? AND is_deleted = 0 AND quantity - ? >= GREATEST(1, $taken)"
    );
    $stmt->execute([(int)$amount, $id, (int)$amount]);

    if ($stmt->rowCount() === 0) {
        $stmt = $db->prepare(
            "SELECT quantity, COALESCE((SELECT SUM(b.quantity) FROM borrow_requests b
                WHERE b.item_id = items.id AND b.status IN ('approved', 'borrowed')), 0) AS taken
             FROM items WHERE id = ? AND is_deleted = 0"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            reply(['error' => 'Item not found.'], 404);
        }
        $max = (int)$row['quantity'] - max(1, (int)$row['taken']);
        if ($max <= 0) {
            reply(['errors' => ['amount' => 'No stock can be removed. At least 1 must stay, and borrowed copies cannot be removed.']]);
        }
        reply(['errors' => ['amount' => "Over the stock count. You can remove at most $max."]]);
    }

    logEvent('ITEM_STOCK_REMOVED', $_SESSION['username'], $_SESSION['user_id'], itemName($db, $id) . ' -' . (int)$amount);
    reply(['ok' => true]);
}

function changeItem($db, $action) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT name, photo FROM items WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        reply(['error' => 'Item not found.'], 404);
    }
    $name = $item['name'];

    if ($action === 'delete') {
        // The server checks this itself; the disabled button in the page is only a hint
        $stmt = $db->prepare("SELECT COUNT(*) FROM borrow_requests WHERE item_id = ? AND status IN ('pending', 'approved', 'borrowed')");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            reply(['error' => 'This item has pending, approved or borrowed requests, so it cannot be deleted.'], 400);
        }

        // Old borrow records only need the item name, so that row stays hidden; the photo is never needed
        $stmt = $db->prepare('SELECT COUNT(*) FROM borrow_requests WHERE item_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $db->prepare('UPDATE items SET is_deleted = 1, is_active = 0, photo = NULL WHERE id = ?')->execute([$id]);
        } else {
            $db->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
        }

        $path = PHOTO_DIR . basename((string)$item['photo']);
        if ($item['photo'] && is_file($path)) {
            unlink($path);
        }
        logEvent('ITEM_DELETED', $_SESSION['username'], $_SESSION['user_id'], $name);
    } else {
        $db->prepare('UPDATE items SET is_active = ? WHERE id = ?')->execute([$action === 'show' ? 1 : 0, $id]);
        logEvent($action === 'show' ? 'ITEM_SHOWN' : 'ITEM_HIDDEN', $_SESSION['username'], $_SESSION['user_id'], $name);
    }
    reply(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Invalid request.'], 405);
}
if (!csrf_valid()) {
    logEvent('CSRF_REJECTED', $_SESSION['username'], $_SESSION['user_id'], 'staff items');
    reply(['error' => 'Your session expired. Please reload the page.'], 400);
}

$action = $_POST['action'] ?? '';
try {
    $db = getDb();
    if ($action === 'add') {
        addItem($db);
    } elseif ($action === 'stock') {
        addStock($db);
    } elseif ($action === 'unstock') {
        removeStock($db);
    } elseif (in_array($action, ['hide', 'show', 'delete'], true)) {
        changeItem($db, $action);
    } else {
        reply(['error' => 'Invalid request.'], 400);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    reply(['error' => 'Something went wrong. Please try again.'], 500);
}