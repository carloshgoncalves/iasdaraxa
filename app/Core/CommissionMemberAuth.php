<?php

declare(strict_types=1);

namespace IasdCentral\Core;

use IasdCentral\Repositories\ComissaoRepository;

final class CommissionMemberAuth
{
    public function member(): ?array
    {
        $commissionId = (int) ($_SESSION['commission_member_access']['commission_id'] ?? 0);
        $memberId = (int) ($_SESSION['commission_member_access']['member_id'] ?? 0);
        if ($commissionId < 1 || $memberId < 1) {
            return null;
        }

        $member = (new ComissaoRepository())->findCommissionMemberAccess($commissionId, $memberId);
        if ($member === null) {
            unset($_SESSION['commission_member_access']);
        }

        return $member;
    }

    public function attempt(string $code): bool
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if ($this->tooManyAttempts()) {
            return false;
        }
        if (!preg_match('/^\d{6}$/', $digits)) {
            $this->recordFailedAttempt();
            return false;
        }

        $commissionId = (int) substr($digits, 0, 3);
        $memberId = (int) substr($digits, 3, 3);
        $member = (new ComissaoRepository())->findCommissionMemberAccess($commissionId, $memberId);
        if ($member === null) {
            $this->recordFailedAttempt();
            return false;
        }

        unset($_SESSION['commission_member_login_attempts'][$this->attemptKey()]);
        session_regenerate_id(true);
        $_SESSION['commission_member_access'] = [
            'commission_id' => $commissionId,
            'member_id' => $memberId,
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['commission_member_access']);
        session_regenerate_id(true);
    }

    private function tooManyAttempts(): bool
    {
        $attempt = $_SESSION['commission_member_login_attempts'][$this->attemptKey()] ?? null;
        return is_array($attempt)
            && (int) ($attempt['count'] ?? 0) >= 8
            && (time() - (int) ($attempt['last'] ?? 0)) < 60;
    }

    private function recordFailedAttempt(): void
    {
        $key = $this->attemptKey();
        $attempt = $_SESSION['commission_member_login_attempts'][$key] ?? ['count' => 0];
        $_SESSION['commission_member_login_attempts'][$key] = [
            'count' => ((int) ($attempt['count'] ?? 0)) + 1,
            'last' => time(),
        ];
    }

    private function attemptKey(): string
    {
        return sha1((string) ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    }
}
