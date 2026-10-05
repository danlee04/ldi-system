import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    // Relative, so what the built CSS points at is found wherever the app
    // is mounted. The default writes /build/assets/… into every url(),
    // which only resolves when the app sits at the root of its domain:
    // under localhost/hr-training-laravel, or 192.168.10.38/ldi-system,
    // every font 404s and the whole interface falls back to system sans.
    // The <link> and <script> tags are unaffected — Laravel builds those
    // from the request, so they already carry the right prefix.
    base: './',
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
