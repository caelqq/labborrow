<?php
require_once __DIR__ . '/../core/crypto.php';

function findDuplicates($db, $idNumber, $email, $username) {
    $checks = [
        'id_number' => ['SELECT 1 FROM users WHERE id_number = ?', $idNumber, 'This ID Number is already registered.'],
        'email' => ['SELECT 1 FROM users WHERE email = ?', strtolower($email), 'This email is already registered.'],
        'username' => ['SELECT 1 FROM users WHERE username = ?', $username, 'This username is already taken.'],
    ];
    $errors = [];
    foreach ($checks as $field => [$sql, $value, $message]) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$value]);
        if ($stmt->fetch()) {
            $errors[$field] = $message;
        }
    }
    return $errors;
}

// The user, the first password history row and the three answers are saved together, or not at all
function createUser($db, $d, $answers) {
    $db->beginTransaction();
    try {
        $passwordHash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare(
            'INSERT INTO users (id_number, first_name, middle_name, family_name, name_extension, birthdate,
                purok_street, barangay, municipality, province, country, zip_code, email, username,
                password_hash, course, year_level, contact_number_enc, guardian_name, consent_at)
             VALUES (:id_number, :first_name, :middle_name, :family_name, :name_extension, :birthdate,
                :purok_street, :barangay, :municipality, :province, :country, :zip_code, :email, :username,
                :password_hash, :course, :year_level, :contact_number_enc, :guardian_name, :consent_at)'
        );
        $stmt->execute([
            'id_number' => $d['id_number'],
            'first_name' => $d['first_name'],
            'middle_name' => $d['middle_name'],
            'family_name' => $d['family_name'],
            'name_extension' => $d['name_extension'],
            'birthdate' => $d['birthdate'],
            'purok_street' => $d['purok_street'],
            'barangay' => $d['barangay'],
            'municipality' => $d['municipality'],
            'province' => $d['province'],
            'country' => $d['country'],
            'zip_code' => $d['zip_code'],
            'email' => $d['email'],
            'username' => $d['username'],
            'password_hash' => $passwordHash,
            'course' => $d['course'],
            'year_level' => $d['year_level'],
            'contact_number_enc' => encryptField($d['contact_number']),
            'guardian_name' => $d['guardian_name'],
            'consent_at' => date('Y-m-d H:i:s'),
        ]);

        // The first password counts as history, so it cannot be reused later
        $db->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')
            ->execute([$d['id_number'], $passwordHash]);

        $stmt = $db->prepare('INSERT INTO security_answers (user_id, question_no, answer_hash) VALUES (?, ?, ?)');
        foreach ($answers as $no => $answer) {
            // Lowercase first, so "Bantay" and "bantay" give the same hash
            $stmt->execute([$d['id_number'], $no, password_hash(strtolower($answer), PASSWORD_BCRYPT, ['cost' => 12])]);
        }
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        throw $e;
    }
}