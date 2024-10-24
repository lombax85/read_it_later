<?php

namespace App\Services;

use App\Database;

class Logger {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function logOpenAICall($userId, $endpoint, $tokensUsed) {
        $stmt = $this->db->prepare("
            INSERT INTO openai_logs (user_id, endpoint, tokens_used, created_at)
            VALUES (:user_id, :endpoint, :tokens_used, :created_at)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':tokens_used' => $tokensUsed,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
