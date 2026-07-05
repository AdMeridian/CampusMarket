<?php
/**
 * Managed listings — agent-only owner contact storage (not shown to buyers).
 */

function requireAgent(): void {
    requireLogin();
    if (!isAgent()) {
        setFlash('error', __('agent.access_denied'));
        redirect(BASE_URL);
    }
}

/**
 * @return array{owner_name:string,owner_phone:string,owner_email:?string,owner_notes:?string}
 */
function parseManagedOwnerContactFromRequest(array $source): array {
    return [
        'owner_name' => trim((string)($source['owner_name'] ?? '')),
        'owner_phone' => trim((string)($source['owner_phone'] ?? '')),
        'owner_email' => trim((string)($source['owner_email'] ?? '')),
        'owner_notes' => trim((string)($source['owner_notes'] ?? '')),
    ];
}

/**
 * @return array<string, string> field => error message
 */
function validateManagedOwnerContact(array $contact, bool $required = true): array {
    $errors = [];
    $name = $contact['owner_name'] ?? '';
    $phone = $contact['owner_phone'] ?? '';
    $email = $contact['owner_email'] ?? '';

    if ($required && $name === '') {
        $errors['owner_name'] = __('agent.owner_name_required');
    } elseif ($name !== '' && mb_strlen($name) > 120) {
        $errors['owner_name'] = __('agent.owner_name_too_long');
    }

    if ($required && $phone === '') {
        $errors['owner_phone'] = __('agent.owner_phone_required');
    } elseif ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone)) {
        $errors['owner_phone'] = __('agent.owner_phone_invalid');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['owner_email'] = __('agent.owner_email_invalid');
    }

    if (isset($contact['owner_notes']) && mb_strlen((string)$contact['owner_notes']) > 2000) {
        $errors['owner_notes'] = __('agent.owner_notes_too_long');
    }

    return $errors;
}

function managedListingsTableExists(PDO $pdo): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = 'managed_listings'
            LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'managed_listings'
            LIMIT 1
        ");
    }
    $stmt->execute();
    $exists = (bool)$stmt->fetchColumn();
    return $exists;
}

function saveManagedListingContact(PDO $pdo, int $productId, array $contact): bool {
    if (!managedListingsTableExists($pdo)) {
        return false;
    }

    $email = ($contact['owner_email'] ?? '') !== '' ? $contact['owner_email'] : null;
    $notes = ($contact['owner_notes'] ?? '') !== '' ? $contact['owner_notes'] : null;

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("
            INSERT INTO managed_listings (product_id, owner_name, owner_phone, owner_email, owner_notes)
            VALUES (:pid, :name, :phone, :email, :notes)
            ON CONFLICT (product_id) DO UPDATE SET
                owner_name = EXCLUDED.owner_name,
                owner_phone = EXCLUDED.owner_phone,
                owner_email = EXCLUDED.owner_email,
                owner_notes = EXCLUDED.owner_notes,
                updated_at = NOW()
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO managed_listings (product_id, owner_name, owner_phone, owner_email, owner_notes)
            VALUES (:pid, :name, :phone, :email, :notes)
            ON DUPLICATE KEY UPDATE
                owner_name = VALUES(owner_name),
                owner_phone = VALUES(owner_phone),
                owner_email = VALUES(owner_email),
                owner_notes = VALUES(owner_notes),
                updated_at = CURRENT_TIMESTAMP
        ");
    }

    return $stmt->execute([
        ':pid' => $productId,
        ':name' => $contact['owner_name'],
        ':phone' => $contact['owner_phone'],
        ':email' => $email,
        ':notes' => $notes,
    ]);
}

function agentOwnsProduct(PDO $pdo, int $productId, int $agentId): bool {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$productId, $agentId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * @return array<int, array<string, mixed>>
 */
function getManagedListingsForAgent(PDO $pdo, int $agentId, string $statusFilter = 'all', string $search = ''): array {
    if (!managedListingsTableExists($pdo)) {
        return [];
    }

    $sql = "
        SELECT p.id, p.title, p.price, p.price_currency, p.status, p.created_at, p.discount_percent,
               c.name AS category_name,
               i.image_path,
               ml.owner_name, ml.owner_phone, ml.owner_email, ml.owner_notes, ml.updated_at AS contact_updated_at
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_images i ON i.product_id = p.id AND i.is_primary = TRUE
        LEFT JOIN managed_listings ml ON ml.product_id = p.id
        WHERE p.user_id = :agent_id
    ";
    $params = [':agent_id' => $agentId];

    if ($statusFilter !== 'all' && in_array($statusFilter, ['active', 'pending_approval', 'sold', 'flagged'], true)) {
        $sql .= ' AND p.status = :status';
        $params[':status'] = $statusFilter;
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= ' AND (
            p.title ILIKE :q
            OR ml.owner_name ILIKE :q
            OR ml.owner_phone ILIKE :q
            OR COALESCE(ml.owner_email, \'\') ILIKE :q
        )';
        $params[':q'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY p.created_at DESC';

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql' && $search !== '') {
        $sql = str_replace('ILIKE', 'LIKE', $sql);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
