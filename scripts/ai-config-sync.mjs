#!/usr/bin/env node
/**
 * ai-config-sync.mjs
 *
 * Canonical source : .opencode/{rules,skills,commands}  +  AGENTS.md
 * Mirrors          : .cursor/  and  .claude/
 *
 * Never edit a mirror by hand — the next `npm run syn` overwrites it, and
 * `npm run syn:check` (part of `npm run verify`) fails while they drift.
 *
 * Usage:
 *   node scripts/ai-config-sync.mjs            # write mirrors + prune stale
 *   node scripts/ai-config-sync.mjs --check    # report drift, exit 1
 *   node scripts/ai-config-sync.mjs --verbose
 *
 * No dependencies — Node builtins only.
 */

import {
    readdirSync,
    readFileSync,
    writeFileSync,
    mkdirSync,
    rmSync,
    existsSync,
    statSync,
} from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(ROOT, '.opencode');

/** .opencode skill name -> mirror skill name. opencode is lowercase, the rest are SCREAMING_CASE. */
const SKILL_NAME_MAP = {
    'backend-php': 'BACKEND-PHP',
    frontend: 'FRONTEND',
    owasp: 'OWASP',
};

const SKIP = new Set(['.DS_Store', 'node_modules', 'Thumbs.db', '.gitkeep']);

/**
 * Paths inside a mirror that this script must never prune — tool state and
 * user-owned config, not generated content.
 *
 * Only matters for the PRUNE pass. `compare()` runs before this check, so a
 * generated file that also appears here is still written; listing it only stops
 * the pruner from deleting it. Do NOT add generated trees (e.g. `.cursor/commands`)
 * — they are in `expected` already, and listing them here hides a real orphan
 * from the prune report.
 */
const PROTECTED = [
    '.claude/agents',
    '.claude/projects',
    '.claude/plugins',
    '.claude/settings.json',
    '.claude/settings.local.json',
    '.cursor/mcp.json',
    '.cursor/project-facts.json',
    '.cursor/README.md',
];

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

let written = 0;
let same = 0;
const drift = [];

const rel = (p) => relative(ROOT, p).replaceAll('\\', '/');

function isProtected(abs) {
    const r = rel(abs);
    return PROTECTED.some((p) => r === p || r.startsWith(p + '/'));
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
    for (const e of entries) {
        if (SKIP.has(e.name)) continue;
        const full = join(dir, e.name);
        if (e.isDirectory()) out.push(...listFiles(full));
        else out.push(full);
    }
    return out;
}

function compare(src, dest) {
    if (!existsSync(src)) return;
    const source = readFileSync(src, 'utf8');
    const current = readIfExists(dest);

    if (current === source) {
        same++;
        if (VERBOSE) console.log(`  ${c.dim}same  ${rel(dest)}${c.reset}`);
        return;
    }

    drift.push({ dest, reason: current === null ? 'missing' : 'stale' });

    if (CHECK) {
        console.log(
            `  ${c.yellow}${(current === null ? 'missing' : 'stale').padEnd(8)}${c.reset}${rel(dest)}`,
        );
        return;
    }

    mkdirSync(dirname(dest), { recursive: true });
    writeFileSync(dest, source, 'utf8');
    written++;
    console.log(
        `  ${c.green}wrote  ${c.reset}${rel(dest)} ${c.dim}(was ${current === null ? 'missing' : 'stale'})${c.reset}`,
    );
}

/** Map a relative path under .opencode to its path inside a given mirror. */
function targetFor(mirrorRoot, relFromOpencode) {
    const parts = relFromOpencode.split('/');

    if (parts[0] === 'rules') {
        // rules.md -> rules.mdc for cursor; plain .md for claude
        const name = parts[parts.length - 1];
        const renamed = name.endsWith('.md') ? name.replace(/\.md$/, '.mdc') : name;
        return join(mirrorRoot, 'rules', ...parts.slice(1, -1), renamed);
    }

    if (parts[0] === 'commands') {
        return join(mirrorRoot, 'commands', ...parts.slice(1));
    }

    if (parts[0] === 'skills') {
        const name = SKILL_NAME_MAP[parts[1]] ?? parts[1];
        return join(mirrorRoot, 'skills', name, ...parts.slice(2));
    }

    return null;
}

function syncMirror(mirrorName) {
    const mirrorRoot = join(ROOT, mirrorName);
    if (!existsSync(mirrorRoot)) {
        console.log(`${c.yellow}skip  ${mirrorName} (does not exist)${c.reset}`);
        return { expected: new Set() };
    }

    console.log(`${c.bold}${mirrorName}${c.reset}`);

    const expected = new Set();
    const srcFiles = listFiles(SRC).filter(
        (f) => f.startsWith(SRC + join('\\')) || f.startsWith(SRC + '/'),
    );

    for (const src of srcFiles) {
        const relFromOpencode = relative(SRC, src).replaceAll('\\', '/');
        const dest = targetFor(mirrorRoot, relFromOpencode);
        if (!dest) continue;
        expected.add(dest.toLowerCase());
        compare(src, dest);
    }

    // AGENTS.md -> CLAUDE.md (root level only)
    const agents = join(ROOT, 'AGENTS.md');
    if (existsSync(agents)) {
        const claudeMd = join(mirrorRoot, 'CLAUDE.md');
        expected.add(claudeMd.toLowerCase());
        compare(agents, claudeMd);
    }

    // Prune: anything under the managed trees that the source no longer produces.
    const managed = ['rules', 'skills', 'commands'];
    const stale = [];

    for (const sub of managed) {
        for (const existing of listFiles(join(mirrorRoot, sub))) {
            if (expected.has(existing.toLowerCase())) continue;
            if (isProtected(existing)) continue;
            stale.push(existing);
        }
    }

    // Root-level skill dirs left over from an older layout (e.g. .claude/FRONTEND/)
    for (const e of readdirSync(mirrorRoot, { withFileTypes: true })) {
        if (!e.isDirectory()) continue;
        const full = join(mirrorRoot, e.name);
        if (isProtected(full)) continue;
        if (managed.includes(e.name)) continue;
        const files = listFiles(full);
        // Only prune a root dir if every file in it is a generated one
        if (
            files.length > 0 &&
            files.every((f) => stale.includes(f) || !expected.has(f.toLowerCase()))
        ) {
            for (const f of files) if (!stale.includes(f)) stale.push(f);
        }
    }

    for (const f of stale) {
        drift.push({ dest: f, reason: 'orphan' });
        if (CHECK) {
            console.log(`  ${c.red}orphan ${c.reset}${rel(f)}`);
        } else {
            rmSync(f, { force: true });
            written++;
            console.log(`  ${c.red}pruned ${c.reset}${rel(f)}`);
        }
    }

    console.log('');
    return { expected };
}

function main() {
    if (!existsSync(SRC)) {
        console.error(`${c.red}Canonical source missing: .opencode/${c.reset}`);
        process.exit(1);
    }

    console.log(
        `${c.cyan}ai-config-sync${c.reset} ${c.dim}(${CHECK ? 'check only' : 'write + prune'})${c.reset}\n`,
    );

    for (const mirror of ['.claude', '.cursor']) syncMirror(mirror);

    console.log('');
    if (CHECK) {
        if (drift.length === 0) {
            console.log(`${c.green}In sync.${c.reset} ${c.dim}${same} file(s) compared.${c.reset}`);
            process.exit(0);
        }
        console.log(
            `${c.red}Out of sync: ${drift.length} file(s)${c.reset} ${c.dim}run "npm run syn".${c.reset}`,
        );
        process.exit(1);
    }

    console.log(`${c.green}Done.${c.reset} ${written} written/pruned, ${same} already in sync.`);
}

main();
