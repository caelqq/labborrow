<?php
// Command line only: php scripts/make_staff.php
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

$username = ask('Staff username (8 to 17 letters or digits): ');
$email = strtolower(ask('Staff email (must be a real inbox): '));
$firstName = ask('Staff first name: ');
$password = ask('Staff password: ');

$problems = array_filter([
    validateUsername($username),
    validateEmail($email),
    validateName($firstName, 'First Name', true),
    validatePassword($password),
]);
if ($problems) {
    exit(implode("\n", $problems) . "\nNothing saved.\n");
}

$db = getDb();

// Staff IDs start with 0001- (admins use 0000-), so they never clash with each other
$count = (int)$db->query("SELECT COUNT(*) FROM users WHERE id_number LIKE '0001-%'")->fetchColumn();
$idNumber = sprintf('0001-%04d', $count + 1);

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
     VALUES (?, ?, 'Staff', '2000-01-01', 'Lab Office', 'Poblacion', 'Butuan City',
        'Agusan Del Norte', 'Philippines', '8600', ?, ?, ?, 'BSIT', '4th', ?,
        'staff', 1, ?)"
)->execute([$idNumber, $firstName, $email, $username, $hash, encryptField('09000000000'), date('Y-m-d H:i:s')]);
$db->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$idNumber, $hash]);
$db->commit();

echo "Staff created. ID: $idNumber, username: $username, name: $firstName Staff\n";