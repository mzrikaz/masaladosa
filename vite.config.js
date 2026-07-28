import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Fraunces', {
                    weights: [500, 600, 700],
                }),
                bunny('Poppins', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Baloo Thambi 2', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Noto Sans Sinhala', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
