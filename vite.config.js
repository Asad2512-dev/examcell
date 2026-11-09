import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({plugins:[laravel({input:['resources/css/app.css','resources/js/app.js','resources/css/landing.css','resources/js/landing.js','resources/css/auth-reference.css','resources/js/auth-reference.js'],refresh:true}),tailwindcss()],build:{chunkSizeWarningLimit:350}});
