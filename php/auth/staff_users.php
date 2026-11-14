<?php
require_once __DIR__ . '/staff_penalty.php';

// Starts-with search: the name (or any word of it), username, email and ID number
function userSearch($q) {
    if ($q === '') {
        return ['', []];
    }
    $start = addcslashes($q, '%_\\') . '%';
    $word = '% ' . addcslashes($q, '%_\\') . '%';
    $fullName = "CONCAT(first_name, ' ', family_name)";

    $sql = " AND ($fullName LIKE ? OR $fullName LIKE ? OR username LIKE ? OR email LIKE ? OR id_number LIKE ?)";
    return [$sql, [$start, $word, $start, $start, $start]];
}

// Returns [rows to show, true if there are more, cards]
function userList($db, $q, $limit, $now) {
    $penalties = penaltyInfo($db, $now);

    [$searchSql, $params] = userSearch($q);
    $stmt = $db->prepare(
        "SELECT id_number, first_name, family_name, username, email, course, year_level FROM users
         WHERE role = 'user'$searchSql ORDER BY first_name, family_name, id_number LIMIT " . ($limit + 1)
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $p = $penalties[$r['id_number']] ?? null;
        $rows[] = [
            'name' => $r['first_name'] . ' ' . $r['family_name'],
            'username' => $r['username'],
            'email' => $r['email'],
            'id_number' => $r['id_number'],
            'course' => $r['course'],
            'year' => $r['year_level'],
            'standing' => standingOf($p, $now),
            'balance' => $p ? $p['balance'] : 0,
            'since' => $p ? $p['since'] : null,
        ];
    }

    $cards = [
        'total' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'owing' => 0,
        'bad' => 0,
    ];
    foreach ($penalties as $p) {
        if ($p['balance'] > 0) {
            $cards['owing']++;
        }
        if (standingOf($p, $now) === 'bad') {
            $cards['bad']++;
        }
    }

    return [array_slice($rows, 0, $limit), count($rows) > $limit, $cards];
}