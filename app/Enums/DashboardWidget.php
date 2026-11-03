<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The widgets of GET /api/v1/dashboard/{widget} (docs/api-contracts/reports.md).
 */
enum DashboardWidget: string
{
    case Cashflow = 'cashflow';
    case Categories = 'categories';
    case Trend = 'trend';
    case Members = 'members';
    case Wallets = 'wallets';
    case Budgets = 'budgets';
    case Goals = 'goals';
    case Recent = 'recent';
    case Insights = 'insights';
}
