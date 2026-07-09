import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/css/admin-modern.css',
                'resources/js/app.js',
                'resources/js/pages/article.js',
                'resources/js/pages/brand.js',
                'resources/js/pages/category.js',
                'resources/js/pages/customer-purchase-order.js',
                'resources/js/pages/customer.js',
                'resources/js/pages/market-study.js',
                'resources/js/pages/presentation.js',
                'resources/js/pages/quote.js',
                'resources/js/pages/supplier.js',
                'resources/js/pages/shipping-agency.js',
                'resources/js/pages/unit.js',
                'resources/js/pages/supplier-purchase-order.js',
                'resources/js/pages/warehouse-entry.js',
                'resources/js/pages/labeling.js',
                'resources/js/pages/electronic-invoice.js',
                'resources/js/pages/electronic-invoice-series.js',
                'resources/js/pages/electronic-invoice-setting.js',
                'resources/js/pages/sunat-catalog.js',
                'resources/js/pages/kardex.js',
                'resources/js/pages/roles.js',
                'resources/js/pages/user.js',
            ],
            refresh: true,
        }),
    ],
});
