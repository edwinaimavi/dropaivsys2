<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate([
            'name' => 'Administrador',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'Vendedor',
            'guard_name' => 'web',
        ]);

        foreach ($this->permissions() as $name => $description) {
            Permission::updateOrCreate(
                [
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                [
                    'description' => $description,
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('guard_name', 'web')
            ->get()
            ->each(fn (Permission $permission) => $adminRole->givePermissionTo($permission));
    }

    private function permissions(): array
    {
        return [
            'admin.users.index' => 'Ver usuarios',
            'admin.users.store' => 'Crear usuarios',
            'admin.users.update' => 'Actualizar usuarios',
            'admin.users.destroy' => 'Eliminar usuarios',
            'admin.users.show' => 'Ver detalle de usuarios',

            'admin.roles.index' => 'Ver roles',
            'admin.roles.store' => 'Crear roles',
            'admin.roles.update' => 'Actualizar roles',
            'admin.roles.destroy' => 'Eliminar roles',
            'admin.roles.show' => 'Ver detalle de roles',

            'admin.categories.index' => 'Ver categorias',
            'admin.categories.store' => 'Crear categorias',
            'admin.categories.update' => 'Actualizar categorias',
            'admin.categories.destroy' => 'Eliminar categorias',
            'admin.categories.show' => 'Ver detalle de categorias',

            'admin.subcategories.index' => 'Ver subcategorias',
            'admin.subcategories.store' => 'Crear subcategorias',
            'admin.subcategories.update' => 'Actualizar subcategorias',
            'admin.subcategories.destroy' => 'Eliminar subcategorias',

            'admin.units.index' => 'Ver unidades',
            'admin.units.store' => 'Crear unidades',
            'admin.units.update' => 'Actualizar unidades',
            'admin.units.destroy' => 'Eliminar unidades',
            'admin.units.show' => 'Ver detalle de unidades',

            'admin.presentations.index' => 'Ver presentaciones',
            'admin.presentations.store' => 'Crear presentaciones',
            'admin.presentations.update' => 'Actualizar presentaciones',
            'admin.presentations.destroy' => 'Eliminar presentaciones',
            'admin.presentations.show' => 'Ver detalle de presentaciones',

            'admin.suppliers.index' => 'Ver proveedores',
            'admin.suppliers.store' => 'Crear proveedores',
            'admin.suppliers.update' => 'Actualizar proveedores',
            'admin.suppliers.destroy' => 'Eliminar proveedores',
            'admin.suppliers.show' => 'Ver detalle de proveedores',
            'admin.suppliers.accounts' => 'Ver cuentas bancarias de proveedores',

            'admin.supplier-accounts.index' => 'Ver cuentas bancarias de proveedores',
            'admin.supplier-accounts.store' => 'Crear cuentas bancarias de proveedores',
            'admin.supplier-accounts.update' => 'Actualizar cuentas bancarias de proveedores',
            'admin.supplier-accounts.destroy' => 'Eliminar cuentas bancarias de proveedores',

            'admin.shipping-agencies.index' => 'Ver agencias de envio',
            'admin.shipping-agencies.store' => 'Crear agencias de envio',
            'admin.shipping-agencies.update' => 'Actualizar agencias de envio',
            'admin.shipping-agencies.destroy' => 'Eliminar agencias de envio',
            'admin.shipping-agencies.show' => 'Ver detalle de agencias de envio',
            'admin.shipping-agencies.branches' => 'Ver sedes de agencias de envio',
            'admin.shipping-agencies.contacts' => 'Ver contactos de agencias de envio',
            'admin.shipping-agency-branches.store' => 'Crear sedes de agencias de envio',
            'admin.shipping-agency-branches.update' => 'Actualizar sedes de agencias de envio',
            'admin.shipping-agency-branches.destroy' => 'Eliminar sedes de agencias de envio',
            'admin.shipping-agency-contacts.store' => 'Crear contactos de agencias de envio',
            'admin.shipping-agency-contacts.update' => 'Actualizar contactos de agencias de envio',
            'admin.shipping-agency-contacts.destroy' => 'Eliminar contactos de agencias de envio',

            'admin.brands.index' => 'Ver marcas',
            'admin.brands.store' => 'Crear marcas',
            'admin.brands.update' => 'Actualizar marcas',
            'admin.brands.destroy' => 'Eliminar marcas',
            'admin.brands.show' => 'Ver detalle de marcas',

            'admin.customers.index' => 'Ver clientes',
            'admin.customers.store' => 'Crear clientes',
            'admin.customers.update' => 'Actualizar clientes',
            'admin.customers.destroy' => 'Eliminar clientes',
            'admin.customers.show' => 'Ver detalle de clientes',

            'admin.customer-branches.index' => 'Ver sedes de clientes',
            'admin.customer-branches.store' => 'Crear sedes de clientes',
            'admin.customer-branches.update' => 'Actualizar sedes de clientes',
            'admin.customer-branches.destroy' => 'Eliminar sedes de clientes',

            'admin.customer-branch-contacts.index' => 'Ver contactos de sedes',
            'admin.customer-branch-contacts.store' => 'Crear contactos de sedes',
            'admin.customer-branch-contacts.update' => 'Actualizar contactos de sedes',
            'admin.customer-branch-contacts.destroy' => 'Eliminar contactos de sedes',

            'admin.articles.index' => 'Ver articulos',
            'admin.articles.store' => 'Crear articulos',
            'admin.articles.update' => 'Actualizar articulos',
            'admin.articles.destroy' => 'Eliminar articulos',
            'admin.articles.show' => 'Ver detalle de articulos',
            'admin.articles.documents' => 'Gestionar documentos de articulos',

            'admin.market-studies.index' => 'Ver estudios de mercado',
            'admin.market-studies.store' => 'Crear estudios de mercado',
            'admin.market-studies.update' => 'Actualizar estudios de mercado',
            'admin.market-studies.destroy' => 'Eliminar estudios de mercado',
            'admin.market-studies.show' => 'Ver detalle de estudios de mercado',
            'admin.market-studies.quotes' => 'Gestionar cotizaciones de estudios de mercado',
            'admin.market-studies.adjudicate' => 'Adjudicar estudios de mercado',

            'admin.market-study-quotes.index' => 'Ver cotizaciones de proveedores',
            'admin.market-study-quotes.store' => 'Crear cotizaciones de proveedores',
            'admin.market-study-quotes.update' => 'Actualizar cotizaciones de proveedores',
            'admin.market-study-quotes.destroy' => 'Eliminar cotizaciones de proveedores',
            'admin.market-study-quotes.show' => 'Ver detalle de cotizaciones de proveedores',

            'admin.market-study-comparisons.show' => 'Ver comparacion de estudio de mercado',
            'admin.market-study-comparisons.save' => 'Guardar comparacion de estudio de mercado',

            'admin.quotes.index' => 'Ver cotizaciones',
            'admin.quotes.store' => 'Crear cotizaciones',
            'admin.quotes.update' => 'Actualizar cotizaciones',
            'admin.quotes.destroy' => 'Eliminar cotizaciones',
            'admin.quotes.show' => 'Ver detalle de cotizaciones',
            'admin.quotes.pdf' => 'Ver PDF de cotizaciones',
            'admin.quotes.export' => 'Exportar cotizaciones',

            'admin.customer-purchase-orders.index' => 'Ver ordenes de compra de clientes',
            'admin.customer-purchase-orders.store' => 'Crear ordenes de compra de clientes',
            'admin.customer-purchase-orders.update' => 'Actualizar ordenes de compra de clientes',
            'admin.customer-purchase-orders.destroy' => 'Eliminar ordenes de compra de clientes',
            'admin.customer-purchase-orders.show' => 'Ver detalle de ordenes de compra de clientes',
            'admin.customer-purchase-orders.pdf' => 'Ver PDF de ordenes de compra de clientes',
            'admin.customer-purchase-orders.load-items' => 'Cargar items de cotizacion',

            'admin.supplier-purchase-orders.index' => 'Ver ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.store' => 'Crear ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.update' => 'Actualizar ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.destroy' => 'Eliminar ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.show' => 'Ver detalle de ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.pdf' => 'Ver PDF de ordenes de compra a proveedores',
            'admin.supplier-purchase-orders.load-items' => 'Cargar items de orden de cliente',

            'admin.warehouse-entries.index' => 'Ver ingresos de almacen',
            'admin.warehouse-entries.store' => 'Crear ingresos de almacen',
            'admin.warehouse-entries.update' => 'Actualizar ingresos de almacen',
            'admin.warehouse-entries.destroy' => 'Eliminar ingresos de almacen',
            'admin.warehouse-entries.show' => 'Ver detalle de ingresos de almacen',
            'admin.warehouse-entries.load-order' => 'Cargar orden de compra proveedor',
            'admin.warehouse-entries.load-items' => 'Cargar items de orden de compra proveedor',
            'admin.warehouse-entries.pdf' => 'Ver PDF de ingresos de almacen',

            'admin.labelings.index' => 'Ver rotulaciones',
            'admin.labelings.list' => 'Listar rotulaciones',
            'admin.labelings.store' => 'Crear rotulaciones',
            'admin.labelings.update' => 'Actualizar rotulaciones',
            'admin.labelings.destroy' => 'Anular rotulaciones',
            'admin.labelings.show' => 'Ver detalle de rotulación',
            'admin.labelings.pdf' => 'Generar PDF de rotulación',
            'admin.labelings.customer-order' => 'Cargar orden de cliente para rotulación',

            'admin.electronic-invoices.index' => 'Ver facturacion electronica',
            'admin.electronic-invoices.store' => 'Crear comprobantes electronicos',
            'admin.electronic-invoices.show' => 'Ver detalle de comprobantes electronicos',
            'admin.electronic-invoices.update' => 'Actualizar comprobantes electronicos',
            'admin.electronic-invoices.destroy' => 'Eliminar comprobantes electronicos',
            'admin.electronic-invoices.pdf' => 'Ver PDF de comprobantes electronicos',
            'admin.electronic-invoices.payload' => 'Ver payload de comprobantes electronicos',
            'admin.electronic-invoices.send' => 'Enviar comprobantes electronicos',
            'admin.electronic-invoices.xml' => 'Ver XML de comprobantes electronicos',
            'admin.electronic-invoices.cdr' => 'Ver CDR de comprobantes electronicos',

            'admin.electronic-invoice-settings.index' => 'Ver configuracion de facturacion electronica',
            'admin.electronic-invoice-settings.store' => 'Crear configuracion de facturacion electronica',
            'admin.electronic-invoice-settings.update' => 'Actualizar configuracion de facturacion electronica',
            'admin.electronic-invoice-settings.show' => 'Ver detalle de configuracion de facturacion electronica',

            'admin.electronic-invoice-series.index' => 'Ver series de facturacion electronica',
            'admin.electronic-invoice-series.store' => 'Crear series de facturacion electronica',
            'admin.electronic-invoice-series.update' => 'Actualizar series de facturacion electronica',
            'admin.electronic-invoice-series.destroy' => 'Eliminar series de facturacion electronica',
            'admin.electronic-invoice-series.show' => 'Ver detalle de series de facturacion electronica',

            'admin.sunat-catalogs.index' => 'Ver catalogos SUNAT',

            'admin.kardex.index' => 'Ver Kardex',
            'admin.kardex.show' => 'Ver detalle de Kardex',
            'admin.kardex.stock' => 'Ver stock de Kardex',
            'admin.kardex.export' => 'Exportar Kardex',

            'admin.profile.index' => 'Ver perfil',
            'admin.profile.update' => 'Actualizar perfil',
        ];
    }
}
