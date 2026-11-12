import { copyFileSync, mkdtempSync, rmSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const temporaryDirectory = mkdtempSync(join(tmpdir(), 'sistem-toko-pages-'));

try {
    copyFileSync(
        resolve(projectRoot, 'wrangler.pages.toml'),
        join(temporaryDirectory, 'wrangler.toml'),
    );

    const result = spawnSync(
        process.platform === 'win32' ? 'npx.cmd' : 'npx',
        [
            'wrangler',
            'pages',
            'deploy',
            resolve(projectRoot, 'dist'),
            '--project-name=sistem-toko',
            '--cwd',
            temporaryDirectory,
        ],
        {
            cwd: projectRoot,
            shell: process.platform === 'win32',
            stdio: 'inherit',
        },
    );

    if (result.error) {
        throw result.error;
    }

    if (result.signal) {
        throw new Error(`Wrangler Pages deployment was interrupted by ${result.signal}.`);
    }

    if (result.status !== 0) {
        process.exitCode = result.status ?? 1;
    }
} finally {
    rmSync(temporaryDirectory, { force: true, recursive: true });
}
