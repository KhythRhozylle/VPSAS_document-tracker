<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class PaginationHelper
{
    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function resolvePerPage(Request $request, string $sessionKey, int $default = 10): int
    {
        $session = $request->getSession();
        $raw = $request->query->get('per_page');

        if ($raw !== null && $raw !== '') {
            $session->set($sessionKey, (string) $raw);
        }

        $stored = (string) $session->get($sessionKey, (string) $default);
        if (strtolower($stored) === 'all') {
            return 0;
        }

        $value = (int) $stored;

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }

    public function getStoredPerPage(Request $request, string $sessionKey, int $default = 10): string
    {
        return (string) $request->getSession()->get($sessionKey, (string) $default);
    }
}
