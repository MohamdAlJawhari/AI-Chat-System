import http from 'node:http';
import https from 'node:https';
import { spawn } from 'node:child_process';

const baseUrl = (process.env.OLLAMA_URL || process.env.LLM_BASE_URL || 'http://127.0.0.1:11434').trim();
const pingUrl = new URL('/api/tags', baseUrl);
const client = pingUrl.protocol === 'https:' ? https : http;

const ping = () =>
    new Promise((resolve) => {
        const req = client.get(pingUrl, { timeout: 1500 }, (res) => {
            res.resume();
            resolve(true);
        });
        req.on('timeout', () => {
            req.destroy();
            resolve(false);
        });
        req.on('error', () => resolve(false));
    });

const main = async () => {
    const running = await ping();
    if (running) {
        console.log('Ollama already running.');
        return;
    }

    console.log('Starting Ollama...');
    const child = spawn('ollama', ['serve'], { stdio: 'inherit' });
    child.on('exit', (code) => process.exit(code ?? 0));
};

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
