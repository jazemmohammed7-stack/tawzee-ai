<?php

declare(strict_types=1);

namespace App\Modules\Access\Enums;

enum DefaultRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Sales = 'sales';
    case Warehouse = 'warehouse';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => [
                Permission::UsersManage, Permission::CompanySettings,
                Permission::CustomersView, Permission::CustomersManage,
                Permission::ProductsView, Permission::ProductsManage,
                Permission::WarehousesManage, Permission::StockView, Permission::StockOperate,
                Permission::InvoicesView, Permission::InvoicesCreate, Permission::InvoicesConfirm,
                Permission::InvoicesCancel, Permission::InvoicesOverrideCreditLimit,
                Permission::CollectionsView, Permission::CollectionsRecord, Permission::CollectionsVoid,
                Permission::ReportsView, Permission::ReportsExport,
            ],
            self::Sales => [
                Permission::CustomersView, Permission::CustomersManage, Permission::ProductsView,
                Permission::StockView, Permission::InvoicesView, Permission::InvoicesCreate,
                Permission::InvoicesConfirm, Permission::CollectionsView, Permission::CollectionsRecord,
            ],
            self::Warehouse => [
                Permission::ProductsView, Permission::WarehousesManage,
                Permission::StockView, Permission::StockOperate,
            ],
            self::Accountant => [
                Permission::CustomersView, Permission::ProductsView, Permission::StockView,
                Permission::InvoicesView, Permission::InvoicesConfirm, Permission::InvoicesCancel,
                Permission::CollectionsView, Permission::CollectionsRecord, Permission::CollectionsVoid,
                Permission::ReportsView, Permission::ReportsExport,
            ],
            self::Viewer => [
                Permission::CustomersView, Permission::ProductsView, Permission::StockView,
                Permission::InvoicesView, Permission::CollectionsView, Permission::ReportsView,
            ],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
