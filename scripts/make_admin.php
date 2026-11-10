<?php
// Command line only: php scripts/make_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Page Not Found');
}

require __DIR__ . '/../php/core/db.php';
require __DIR__ . '/../php/core/validate.php';
require __DIR__ . '/../php/auth/users.php';
date_default_timezone_set('Asia/Manila');

function ask($question) {
    echo $question;
    return trim(fgets(STDIN));
}

$username = ask('Admin username (8 to 17 letters or digits): ');
$email = strtolower(ask('Admin email (must be a real inbox): '));
$password = ask('Admin password: ');

$problems = array_filter([
    validateUsername($username),
    validateEmail($email),
    validatePassword($password),
]);
if ($problems) {
    exit(implode("\n", $problems) . "\nNothing saved.\n");
}

$db = getDb();

// Admin IDs start with 0000-, so they never clash with student IDs
$count = (int)$db->query("SELECT COUNT(*) FROM users WHERE id_number LIKE '0000-%'")->fetchColumn();
$idNumber = sprintf('0000-%04d', $count + 1);

$taken = findDuplicates($db, $idNumber, $email, $username);
if ($taken) {
    exit(implode("\n", $taken) . "\nNothing saved.\n");
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db->beginTransaction();
$db->prepare(
    "INSERT INTO users (id_number, first_name, family_name, birthdate, purok_street, barangay, municipality,
        province, country, zip_code, email, username, password_hash, course, year_level, contact_number_enc,
        role, is_verified, consent_at)
     VALUES (?, 'Lab', 'Admin', '2000-01-01', 'Admin Office', 'Poblacion', 'Butuan City',
        'Agusan Del Norte', 'Philippines', '8600', ?, ?, ?, 'BSIT', '4th', ?,
        'admin', 1, ?)"
)->execute([$idNumber, $email, $username, $hash, encryptField('09000000000'), date('Y-m-d H:i:s')]);
$db->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$idNumber, $hash]);
$db->commit();

echo "Admin created. ID: $idNumber, username: $username\n";