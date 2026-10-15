<?php

declare(strict_types=1);
require_once __DIR__ . '/Database.php';
function registrationStats(PgSql\Connection $connection, int $eventId): array
{
    $sql = 'SELECT e.id AS event_id, e.title, COUNT(r.id) AS total
            FROM events e
            LEFT JOIN registrations r ON e.id = r.event_id
            WHERE e.id = $1
            GROUP BY e.id, e.title';

    $result = queryParams($connection, $sql, [$eventId]);
    $row = pg_fetch_assoc($result);

    if ($row === false) {
        return [];
    }

    return [
        'event_id' => (int)$row['event_id'],
        'title' => (string)$row['title'],
        'total' => (int)$row['total'],
    ];
}
