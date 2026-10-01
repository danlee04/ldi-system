import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
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
            fonts: [
                // One family throughout. Fetched at build time and served
                // from this app, so no request leaves for a font CDN.
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
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
