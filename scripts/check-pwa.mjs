import { readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';

const root = process.cwd();
const publicDir = resolve(root, 'public');
const build = resolve(publicDir, 'build');
const manifestPath = resolve(build, 'manifest.webmanifest');

function fail(message) {
    console.error(`PWA check failed: ${message}`);
    process.exit(1);
}

function pngSize(path) {
    const data = readFileSync(path);
    const signature = '89504e470d0a1a0a';

    if (data.subarray(0, 8).toString('hex') !== signature) {
        fail(`${path} is not a valid PNG file`);
    }

    return {
        width: data.readUInt32BE(16),
        height: data.readUInt32BE(20),
    };
}

let manifest;
try {
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
} catch {
    fail(`missing or invalid ${manifestPath}; run npm run build first`);
}

for (const field of ['name', 'short_name', 'start_url', 'display', 'lang']) {
    if (!manifest[field]) {
        fail(`manifest field "${field}" is missing`);
    }
}

if (manifest.lang !== 'fr') {
    fail(`manifest lang must be "fr", got "${manifest.lang}"`);
}

if (!['standalone', 'fullscreen', 'minimal-ui'].includes(manifest.display)) {
    fail(`manifest display is invalid: ${manifest.display}`);
}

const requiredSizes = new Set(['192x192', '512x512']);
const icons = Array.isArray(manifest.icons) ? manifest.icons : [];

for (const icon of icons) {
    if (requiredSizes.has(icon.sizes)) {
        requiredSizes.delete(icon.sizes);
    }
}

if (requiredSizes.size > 0) {
    fail(`manifest is missing icons for ${[...requiredSizes].join(', ')}`);
}

// Vite writes its build inside public/build. Assets served from the public
// root (icons, favicon) stay at the root instead of being duplicated.
for (const icon of icons) {
    const path = resolve(publicDir, icon.src.replace(/^\//, ''));
    const size = pngSize(path);
    const [width, height] = icon.sizes.split('x').map(Number);

    if (size.width !== width || size.height !== height) {
        fail(`${icon.src} is ${size.width}x${size.height}, expected ${icon.sizes}`);
    }
}

for (const file of ['sw.js', 'registerSW.js']) {
    try {
        if (statSync(resolve(build, file)).size === 0) {
            fail(`${file} is empty`);
        }
    } catch {
        fail(`${file} is missing from the build`);
    }
}

try {
    if (statSync(resolve(publicDir, 'favicon.ico')).size === 0) {
        fail('favicon.ico is empty');
    }
} catch {
    fail('favicon.ico is missing from public/');
}

console.log(`PWA check passed: ${icons.length} icons, manifest ${manifest.lang}, service worker present.`);
