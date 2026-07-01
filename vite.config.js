import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/pages/customer-purchase-order.js',
                'resources/js/pages/supplier-purchase-order.js',
                'resources/js/pages/warehouse-entry.js',
                'resources/js/pages/kardex.js',
            ],
            refresh: true,
        }),
    ],
});
