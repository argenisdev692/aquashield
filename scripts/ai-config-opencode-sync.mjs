#!/usr/bin/env node
/**
 * ai-config-opencode-sync.mjs
 *
 * Mirrors this repository's AI configuration into the global opencode config
 * directory so a single `opencode` install picks up the project's rules and
 * skills.
 *
 * Source  : .opencode/rules/  and  .opencode/skills/   (this repo)
 * Target  : ~/.config/opencode/rules/  and  ~/.config/opencode/skills/
 *
 * Project skills are namespaced under the target to avoid clobbering the
 * user's own global skills (e.g. ~/.config/opencode/skills/codebase-memory).
 *
 * Override the target with OPENCODE_CONFIG_DIR.
 *
 * Usage:
 *   node scripts/ai-config-opencode-sync.mjs            # write
 *   node scripts/ai-config-opencode-sync.mjs --check    # report drift, exit 1
 *   node scripts/ai-config-opencode-sync.mjs --verbose
 *
 * No dependencies - Node builtins only.
 */

import { readdirSync, readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const SRC_RULES = join(ROOT, '.opencode', 'rules');
const SRC_SKILLS = join(ROOT, '.opencode', 'skills');

const TARGET = process.env.OPENCODE_CONFIG_DIR
    ? resolve(process.env.OPENCODE_CONFIG_DIR)
    : join(homedir(), '.config', 'opencode');

const TARGET_RULES = join(TARGET, 'rules');
const TARGET_SKILLS = join(TARGET, 'skills');

const SKIP = new Set(['.DS_Store', 'node_modules', 'Thumbs.db']);

const argv = process.argv.slice(2);
const CHECK = argv.includes('--check');
const VERBOSE = argv.includes('--verbose');

const c = {
    reset: '\x1b[0m',
    dim: '\x1b[2m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    cyan: '\x1b[36m',
    bold: '\x1b[1m',
};

let changed = 0;
let unchanged = 0;
const drift = [];

function rel(p) {
    return relative(TARGET, p).replaceAll('\\', '/');
}

function readIfExists(file) {
    try {
        return readFileSync(file, 'utf8');
    } catch {
        return null;
    }
}

function listFiles(dir) {
    const out = [];
    let entries;
    try {
        entries = readdirSync(dir, { withFileTypes: true });
    } catch {
        return out;
    }
    for (const entry of entries) {
        if (SKIP.has(entry.name)) continue;
        const full = join(dir, entry.name);
        if (entry.isDirectory()) out.push(...listFiles(full));
        else out.push(full);
    }
    return out;
}

function syncFile(src, dest) {
    if (!existsSync(src)) return;

    const source = readFileSync(src, 'utf8');
    const current = readIfExists(dest);

    if (current === source) {
        unchanged++;
        if (VERBOSE) console.log(`  ${c.dim}same  ${rel(dest)}${c.reset}`);
        return;
    }

    const reason = current === null ? 'missing' : 'stale';
    drift.push({ dest, reason });

    if (CHECK) {
        console.log(`  ${c.yellow}${reason.padEnd(6)}${c.reset} ${rel(dest)}`);
        return;
    }

    mkdirSync(dirname(dest), { recursive: true });
    writeFileSync(dest, source, 'utf8');
    changed++;
    console.log(`  ${c.green}wrote ${c.reset} ${rel(dest)} ${c.dim}(was ${reason})${c.reset}`);
}

function main() {
    if (!existsSync(SRC_SKILLS)) {
        console.error(`${c.red}Canonical source not found: ${SRC_SKILLS}${c.reset}`);
        process.exit(1);
    }

    console.log(
        `${c.cyan}ai-config-opencode-sync${c.reset} ${c.dim}(${CHECK ? 'check only' : 'write'})${c.reset}\n`,
    );
    console.log(`${c.dim}target: ${TARGET}${c.reset}\n`);

    if (existsSync(SRC_RULES)) {
        console.log(`${c.bold}rules${c.reset}`);
        for (const file of listFiles(SRC_RULES)) {
            syncFile(file, join(TARGET_RULES, relative(SRC_RULES, file)));
        }
        console.log('');
    }

    console.log(`${c.bold}skills${c.reset}`);
    for (const entry of readdirSync(SRC_SKILLS, { withFileTypes: true })) {
        if (!entry.isDirectory() || SKIP.has(entry.name)) continue;

        const srcDir = join(SRC_SKILLS, entry.name);
        const destDir = join(TARGET_SKILLS, entry.name);
        const files = listFiles(srcDir);
        if (files.length === 0) continue;

        console.log(`${c.bold}${entry.name}${c.reset} ${c.dim}(${files.length} file(s))${c.reset}`);
        for (const file of files) {
            syncFile(file, join(destDir, relative(srcDir, file)));
        }
    }

    console.log('');
    if (CHECK) {
        if (drift.length === 0) {
            console.log(
                `${c.green}In sync.${c.reset} ${c.dim}${unchanged} file(s) compared.${c.reset}`,
            );
            process.exit(0);
        }
        console.log(
            `${c.red}Out of sync: ${drift.length} file(s)${c.reset} ${c.dim}run "npm run syn:opencode" to fix.${c.reset}`,
        );
        process.exit(1);
    }

    console.log(`${c.green}Done.${c.reset} ${changed} written, ${unchanged} already in sync.`);
}

main();
