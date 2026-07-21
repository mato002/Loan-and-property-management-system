<?php

namespace App\Support;

/**
 * Loan MFI workspace keys — sidebar shows these domains only; lists live in workspace tabs / flyouts.
 */
final class LoanWorkspaces
{
    public const DASHBOARD = 'dashboard';

    public const CLIENTS = 'clients';

    public const LOANBOOK = 'loanbook';

    public const COLLECTIONS = 'collections';

    public const PAYMENTS = 'payments';

    public const COMMUNICATIONS = 'communications';

    public const ACCOUNTING = 'accounting';

    public const FINANCIAL = 'financial';

    public const HR = 'hr';

    public const BRANCHES = 'branches';

    public const ANALYTICS = 'analytics';

    public const ASSETS = 'assets';

    public const SETTINGS = 'settings';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::CLIENTS,
            self::LOANBOOK,
            self::COLLECTIONS,
            self::PAYMENTS,
            self::COMMUNICATIONS,
            self::ACCOUNTING,
            self::FINANCIAL,
            self::HR,
            self::BRANCHES,
            self::ANALYTICS,
            self::ASSETS,
            self::SETTINGS,
        ];
    }
}
