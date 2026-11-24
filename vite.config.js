import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = env.VITE_HOST || 'localhost';
    const port = Number(env.VITE_PORT || 5173);

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                // The application serves its own copies of the fonts; neither the build nor the browser fetches them.
                fonts: [
                    local('Jost', {
                        variants: [
                            { src: 'resources/fonts/jost/Jost-300-Light.otf', weight: 300 },
                            { src: 'resources/fonts/jost/Jost-400-Book.otf', weight: 400 },
                            { src: 'resources/fonts/jost/Jost-500-Medium.otf', weight: 500 },
                        ],
                        optimizedFallbacks: false,
                    }),
                    local('IBM Plex Sans', {
                        variants: [
                            { src: 'resources/fonts/ibm-plex-sans/IBMPlexSans-Regular.woff2', weight: 400 },
                            { src: 'resources/fonts/ibm-plex-sans/IBMPlexSans-Medium.woff2', weight: 500 },
                        ],
                        optimizedFallbacks: false,
                    }),
                    local('IBM Plex Mono', {
                        variants: [
                            { src: 'resources/fonts/ibm-plex-mono/IBMPlexMono-Regular.woff2', weight: 400 },
                            { src: 'resources/fonts/ibm-plex-mono/IBMPlexMono-Medium.woff2', weight: 500 },
                        ],
                        optimizedFallbacks: false,
                    }),
                ],
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: `http://${host}:${port}`,
            hmr: {
                host,
                clientPort: port,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
