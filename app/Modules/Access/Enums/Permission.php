<?php

declare(strict_types=1);

namespace App\Modules\Access\Enums;

enum Permission: string
{
    case UsersManage = 'users.manage';
    case CompanySettings = 'company.settings';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case WarehousesManage = 'warehouses.manage';
    case StockView = 'stock.view';
    case StockOperate = 'stock.operate';
    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case InvoicesConfirm = 'invoices.confirm';
    case InvoicesCancel = 'invoices.cancel';
    case InvoicesOverrideCreditLimit = 'invoices.override_credit_limit';
    case CollectionsView = 'collections.view';
    case CollectionsRecord = 'collections.record';
    case CollectionsVoid = 'collections.void';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
