<?php
if (!function_exists('ccdiEnsureNotificationsTable')) {
    function ccdiEnsureNotificationsTable(mysqli $conn): void {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `sender_id` INT NULL,
                `sender_role` VARCHAR(20) NOT NULL DEFAULT 'system',
                `target_role` VARCHAR(20) NOT NULL DEFAULT 'student',
                `recipient_id` INT NULL DEFAULT NULL,
                `user_id` INT NULL DEFAULT NULL,
                `program` VARCHAR(100) NULL DEFAULT NULL,
                `block` VARCHAR(50) NULL DEFAULT NULL,
                `type` VARCHAR(40) NOT NULL DEFAULT 'announcement',
                `notif_type` VARCHAR(40) NOT NULL DEFAULT 'announcement',
                `subject_code` VARCHAR(100) NOT NULL DEFAULT '',
                `title` VARCHAR(200) NOT NULL DEFAULT '',
                `message` TEXT NOT NULL,
                `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('ccdiNormalizeProg')) {
    function ccdiNormalizeProg(?string $raw): string {
        $s = trim((string)$raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('/\(([^()]+)\)\s*$/', $s, $m)) {
            $acr = strtolower(preg_replace('/[^a-z0-9]/i', '', $m[1]));
            if ($acr !== '') {
                return $acr;
            }
        }
        $low = strtolower($s);
        $map = [
            'bsis'  => ['information systems', 'bsis', 'bs is'],
            'bscs'  => ['computer science', 'bscs', 'bs cs'],
            'bsit'  => ['information technology', 'bsit', 'bs it'],
            'bsoad' => ['office administration', 'bsoa', 'bsoad', 'office admin'],
        ];
        foreach ($map as $code => $needles) {
            foreach ($needles as $needle) {
                if (strpos($low, $needle) !== false) {
                    return $code;
                }
            }
        }
        return strtolower(preg_replace('/[^a-z0-9]/', '', $s));
    }
}

if (!function_exists('ccdiNormalizeBlk')) {
    function ccdiNormalizeBlk(?string $raw): string {
        $s = strtolower(trim((string)$raw));
        $s = preg_replace('/\bblock\b/i', '', $s);
        return preg_replace('/[^a-z0-9]/', '', $s);
    }
}

if (!function_exists('ccdiSendNotification')) {
    function ccdiSendNotification(
        mysqli $conn,
        ?int $senderId,
        string $senderRole,
        string $targetRole,
        string $notifType,
        string $title,
        string $message,
        ?int $recipientId = null,
        ?string $program = null,
        ?string $block = null,
        string $subjectCode = ''
    ): bool {
        ccdiEnsureNotificationsTable($conn);

        $progClean = ($program !== null && trim($program) !== '') ? trim($program) : null;
        $blkClean  = ($block !== null && trim($block) !== '') ? trim($block) : null;
        $userIdVal = ($recipientId !== null && $recipientId > 0) ? $recipientId : null;
        $subjClean = trim($subjectCode);

        $stmt = $conn->prepare("
            INSERT INTO `notifications`
                (`sender_id`, `sender_role`, `target_role`, `recipient_id`, `user_id`, `program`, `block`, `type`, `notif_type`, `subject_code`, `title`, `message`, `is_read`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            "issiisssssss",
            $senderId,
            $senderRole,
            $targetRole,
            $recipientId,
            $userIdVal,
            $progClean,
            $blkClean,
            $notifType,
            $notifType,
            $subjClean,
            $title,
            $message
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ccdiFetchNotificationsForUser')) {
    function ccdiFetchNotificationsForUser(
        mysqli $conn,
        int $userId,
        string $role,
        string $program = '',
        string $block = '',
        int $limit = 15
    ): array {
        ccdiEnsureNotificationsTable($conn);

        $roleLow = strtolower(trim($role));
        $uProg = ccdiNormalizeProg($program);
        $uBlk  = ccdiNormalizeBlk($block);
        $safeLimit = max(1, min(50, $limit));

        $rows = [];
        $res = $conn->query("SELECT * FROM `notifications` ORDER BY `id` DESC LIMIT 120");
        if (!$res) {
            return [];
        }

        while ($r = $res->fetch_assoc()) {
            $tRole = strtolower(trim((string)($r['target_role'] ?? 'student')));
            $recId = isset($r['recipient_id']) ? intval($r['recipient_id']) : 0;
            $nProg = ccdiNormalizeProg($r['program'] ?? '');
            $nBlk  = ccdiNormalizeBlk($r['block'] ?? '');

            $match = false;
            if ($recId > 0) {
                $match = ($recId === $userId);
            } elseif ($roleLow === 'student') {
                if ($tRole === 'student' || $tRole === 'all') {
                    $progOk = ($nProg === '' || $uProg === '' || $nProg === $uProg);
                    $blkOk  = ($nBlk === ''  || $uBlk === ''  || $nBlk === $uBlk);
                    $match = ($progOk && $blkOk);
                }
            } elseif ($roleLow === 'teacher') {
                if ($tRole === 'teacher' || $tRole === 'all') {
                    $progOk = ($nProg === '' || $uProg === '' || $nProg === $uProg);
                    $blkOk  = ($nBlk === ''  || $uBlk === ''  || $nBlk === $uBlk);
                    $match = ($progOk && $blkOk);
                }
            } elseif ($roleLow === 'admin') {
                $match = ($tRole === 'admin' || $tRole === 'all' || $tRole === 'teacher' || $tRole === 'student');
            }

            if ($match) {
                if (empty($r['title']) && !empty($r['subject_code'])) {
                    $r['title'] = $r['subject_code'];
                }
                $rows[] = $r;
                if (count($rows) >= $safeLimit) {
                    break;
                }
            }
        }

        return $rows;
    }
}