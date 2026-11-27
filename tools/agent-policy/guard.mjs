#!/usr/bin/env node
// VSG agent policy guard: one PreToolUse safety net shared by Claude Code, Codex and Antigravity.
//
// It is NOT an authorization and NOT the primary barrier. The primary controls are each client's native
// rules: .claude/settings.json (permissions deny/ask), .codex/rules/vsg.rules (execpolicy) and the
// user's Antigravity permission settings. A call this guard lets through may still need an owner order
// (AGENTS.md, authorization matrix).
//
// It fails closed: an unknown --client, an empty or non-JSON payload, a tool call whose shape it does
// not recognise, or an internal error blocks the call (Claude: exit 2; Codex and Antigravity: a "deny"
// decision on stdout with exit 0, which survives any shell; see emit()).
// Shell parsing is heuristic. Each command is read as POSIX sh and as PowerShell (cmd syntax inside
// `cmd /c`) and the stricter reading wins. Variables, aliases, functions, script files and generated
// code are not resolved, which is why native rules must keep covering every critical command family.
//
// Zero dependencies. Usage: node tools/agent-policy/guard.mjs --client claude|codex|antigravity
// The client's hook payload (JSON) arrives on stdin.

const CLIENTS = new Set(['claude', 'codex', 'antigravity']);
const at = process.argv.indexOf('--client');
const client = (at > 0 ? process.argv[at + 1] ?? '' : '').toLowerCase();

const MAX_DEPTH = 4; // nested shells (bash -c, pwsh -Command, cmd /c, eval, pipes into a shell)
const MAX_NEST = 16; // nested $( ) substitutions inside one command
const MAX_COMMAND = 100000;
const DIALECTS = ['posix', 'pwsh'];
const ESCAPE = { posix: '\\', pwsh: '`', cmd: '^' };
const RANK = { ask: 1, deny: 2 };

const deny = (reason) => ({ decision: 'deny', reason });
const ask = (reason) => ({ decision: 'ask', reason });
const worse = (a, b) => (!b ? a : !a || RANK[b.decision] > RANK[a.decision] ? b : a);
const has = (args, ...names) => args.some((a) => names.includes(a));
const shortHas = (args, ch) => args.some((a) => /^-[A-Za-z]+$/.test(a) && a.includes(ch));
const first = (args) => args.find((a) => !a.startsWith('-')) ?? '';
const versionOnly = (args) => args.length > 0 && args.every((a) => /^(-v|-V|--version|-h|--help|version|help)$/.test(a));
const exeName = (w) => String(w).replace(/\\/g, '/').split('/').pop().toLowerCase().replace(/\.(exe|cmd|bat|com|ps1)$/, '');

class TooDeep extends Error {}

// ---------- payload normalization ----------

const SHELL_TOOLS = new Set(['Bash', 'PowerShell', 'shell', 'exec_command', 'local_shell']);
const FILE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit', 'NotebookEdit']);
const AG_WRITE_TOOLS = new Set(['write_to_file', 'replace_file_content', 'multi_replace_file_content']);
const pick = (obj, keys) => keys.map((k) => obj?.[k]).find((v) => v !== undefined);

function patchPaths(patch) {
  const out = [];
  for (const m of patch.matchAll(/^\*\*\* (?:Add File|Update File|Delete File|Move to): (.+)$/gm)) out.push(m[1].trim());
  if (!out.length && patch.trim()) throw new Error('apply_patch payload without recognisable file headers');
  return out;
}

function normalize(p) {
  const none = { commands: [], argvs: [], paths: [] };
  if (client === 'antigravity') {
    const call = p.toolCall ?? p.tool_call;
    if (!call || typeof call !== 'object') throw new Error('payload has no toolCall');
    const name = String(call.name ?? '');
    const args = call.args ?? {};
    if (name === 'run_command') {
      const cmd = pick(args, ['CommandLine', 'commandLine', 'command', 'Command']);
      if (typeof cmd !== 'string') throw new Error('run_command without a command line');
      return { ...none, commands: [cmd] };
    }
    if (AG_WRITE_TOOLS.has(name)) {
      const file = pick(args, ['TargetFile', 'targetFile', 'AbsolutePath', 'file_path', 'path']);
      if (typeof file !== 'string') throw new Error(`${name} without a target file`);
      return { ...none, paths: [file] };
    }
    return none;
  }
  const name = p.tool_name;
  if (typeof name !== 'string' || !name) throw new Error('payload has no tool_name');
  const input = p.tool_input ?? {};
  if (typeof input !== 'object') throw new Error('tool_input is not an object');
  if (SHELL_TOOLS.has(name)) {
    const cmd = input.command ?? input.cmd;
    if (typeof cmd === 'string') return { ...none, commands: [cmd] };
    if (Array.isArray(cmd) && cmd.length && cmd.every((x) => typeof x === 'string')) return { ...none, argvs: [cmd] };
    throw new Error(`${name} without a command`);
  }
  if (name === 'apply_patch') {
    const patch = input.command ?? input.patch ?? input.input;
    if (Array.isArray(patch)) return { ...none, paths: patchPaths(patch.join('\n')) };
    if (typeof patch !== 'string') throw new Error('apply_patch without a patch');
    return { ...none, paths: patchPaths(patch) };
  }
  if (FILE_TOOLS.has(name)) {
    const file = [input.file_path, input.notebook_path, input.path].find((v) => typeof v === 'string');
    if (file === undefined) throw new Error(`${name} without a file path`);
    return { ...none, paths: [file] };
  }
  return none;
}

// ---------- shell parsing (heuristic: posix, pwsh and cmd dialects) ----------
// parse() returns lists of segments { words, stdin, targets, piped }. `stdin` holds heredoc, here-word and
// here-string text fed to the segment, `targets` its output redirections, `piped` whether its stdin comes
// from the previous segment. Command substitutions add their own lists.

function parse(src, dialect, start = 0, nested = false, level = 0) {
  if (level > MAX_NEST) throw new TooDeep('the command nests substitutions too deeply to inspect');
  const esc = ESCAPE[dialect];
  const list = [];
  const lists = [list];
  const pending = [];
  const newSeg = (piped) => ({ words: [], stdin: [], targets: [], piped });
  let seg = newSeg(false);
  let word = '';
  let started = false;
  let braces = 0;
  let mode = null; // what the next word is: 'out' redirect target, 'in' input file, 'here' stdin text
  let quote = null;
  let depth = 0;

  const endWord = () => {
    if (started) {
      if (mode === 'out') seg.targets.push(word);
      else if (mode === 'here') seg.stdin.push(word);
      else if (mode !== 'in') seg.words.push(word);
      mode = null;
    }
    word = '';
    started = false;
    braces = 0;
  };
  const endSeg = (piped) => {
    endWord();
    mode = null;
    if (seg.words.length || seg.stdin.length || seg.targets.length) list.push(seg);
    seg = newSeg(piped);
  };
  const nestedAt = (text, from, d) => {
    const r = parse(text, d, from, true, level + 1);
    lists.push(...r.lists);
    return r.end;
  };
  const scanDouble = (text) => {
    for (let k = 0; k < text.length; k++) {
      if (text[k] === '`') k++;
      else if (text[k] === '$' && text[k + 1] === '(') k = nestedAt(text, k + 2, 'pwsh');
    }
  };
  const backtick = (from) => {
    let k = from + 1;
    let body = '';
    for (; k < src.length && src[k] !== '`'; k++) {
      if (src[k] === '\\' && k + 1 < src.length) { body += src[k + 1]; k++; } else body += src[k];
    }
    lists.push(...parse(body, dialect, 0, false, level + 1).lists);
    return k;
  };
  const readHeredocs = (nl) => {
    let k = nl + 1;
    for (const h of pending) {
      const body = [];
      while (k < src.length) {
        let e = src.indexOf('\n', k);
        if (e < 0) e = src.length;
        let line = src.slice(k, e).replace(/\r$/, '');
        k = e + 1;
        if (h.strip) line = line.replace(/^\t+/, '');
        if (line === h.delim) break;
        body.push(line);
      }
      h.seg.stdin.push(body.join('\n'));
    }
    pending.length = 0;
    return k - 1;
  };
  const hereString = (i, q) => {
    const m = /^[ \t]*\r?\n/.exec(src.slice(i + 2, i + 258));
    if (!m) return -1;
    const open = i + 2 + m[0].length;
    let k = open;
    let body = null;
    while (k <= src.length) {
      if (src.startsWith(`${q}@`, k)) { body = src.slice(open, Math.max(open, k - 1)).replace(/\r$/, ''); break; }
      const nl = src.indexOf('\n', k);
      if (nl < 0) break;
      k = nl + 1;
    }
    if (body === null) { body = src.slice(open); k = src.length; }
    word += body;
    started = true;
    if (q === '"') scanDouble(body);
    return k + 1;
  };

  for (let i = start; i < src.length; i++) {
    const c = src[i];
    const n = src[i + 1];
    if (quote === "'") {
      if (c !== "'") word += c;
      else if (dialect === 'pwsh' && n === "'") { word += "'"; i++; } else quote = null;
      continue;
    }
    if (quote === '"') {
      if (c === esc && dialect !== 'cmd') {
        if (n === '\n') i++;
        else if (n === '\r' && src[i + 2] === '\n') i += 2;
        else if (n !== undefined && (dialect === 'pwsh' || '$`"\\'.includes(n))) { word += n; i++; } else word += c;
      } else if (c === '"') {
        if (dialect === 'pwsh' && n === '"') { word += '"'; i++; } else quote = null;
      } else if (c === '$' && n === '(' && dialect !== 'cmd') { i = nestedAt(src, i + 2, dialect); word += '$()'; }
      else if (c === '`' && dialect === 'posix') { i = backtick(i); word += '$()'; }
      else word += c;
      continue;
    }
    if (c === esc) {
      if (n === '\n') i++;
      else if (n === '\r' && src[i + 2] === '\n') i += 2;
      else if (n !== undefined) { word += n; started = true; i++; }
      continue;
    }
    if (dialect === 'pwsh' && c === '@' && !started && (n === "'" || n === '"')) {
      const end = hereString(i, n);
      if (end >= 0) { i = end; continue; }
    }
    if (c === "'" && dialect !== 'cmd') { quote = c; started = true; continue; }
    if (c === '"') { quote = c; started = true; continue; }
    if (c === '#' && !started && dialect !== 'cmd') {
      const nl = src.indexOf('\n', i);
      i = (nl < 0 ? src.length : nl) - 1;
      continue;
    }
    if (c === '<' && n === '#' && dialect === 'pwsh') {
      const close = src.indexOf('#>', i + 2);
      i = close < 0 ? src.length : close + 1;
      continue;
    }
    if (c === '$' && n === '{' && dialect !== 'cmd') {
      const close = src.indexOf('}', i + 2);
      const end = close < 0 ? src.length - 1 : close;
      word += src.slice(i, end + 1);
      started = true;
      i = end;
      continue;
    }
    if (n === '(' && dialect !== 'cmd' && (c === '$' || (c === '@' && dialect === 'pwsh'))) {
      i = nestedAt(src, i + 2, dialect);
      word += '$()';
      started = true;
      continue;
    }
    if (c === '`' && dialect === 'posix') { i = backtick(i); word += '$()'; started = true; continue; }
    if (c === '<' && n === '<' && dialect !== 'cmd') {
      // Heredoc in both readings: PowerShell has no `<<`, and reading it as a heredoc there avoids
      // mistaking a quoted heredoc body (a commit message, say) for commands.
      endWord();
      if (src[i + 2] === '<') { mode = 'here'; i += 2; continue; }
      let k = i + 2;
      const strip = src[k] === '-';
      if (strip) k++;
      while (src[k] === ' ' || src[k] === '\t') k++;
      let delim = '';
      let q = null;
      for (; k < src.length; k++) {
        const d = src[k];
        if (q) { if (d === q) q = null; else delim += d; continue; }
        if (d === "'" || d === '"') { q = d; continue; }
        if (d === '\\') { delim += src[k + 1] ?? ''; k++; continue; }
        if (/[\s;|&()<>]/.test(d)) break;
        delim += d;
      }
      pending.push({ delim, strip, seg });
      i = k - 1;
      continue;
    }
    if (c === '>' || (c === '&' && n === '>' && dialect === 'posix')) {
      if (c === '>' && started && mode === null && /^(\d+|\*)$/.test(word)) { word = ''; started = false; } else endWord();
      let k = i + (c === '&' ? 2 : 1);
      if (src[k] === '>') k++;
      if (src[k] === '|') k++;
      const dup = /^&(\d+|-)/.exec(src.slice(k, k + 12));
      if (dup) { i = k + dup[0].length - 1; continue; }
      if (src[k] === '&') k++;
      mode = 'out';
      i = k - 1;
      continue;
    }
    if (c === '<') { endWord(); mode = 'in'; continue; }
    if (c === '|') {
      if (n === '|') { endSeg(false); i++; } else { endSeg(true); if (n === '&') i++; }
      continue;
    }
    if (c === '&') { endSeg(false); if (n === '&') i++; continue; }
    if (c === ';') { if (dialect === 'cmd') endWord(); else endSeg(false); continue; }
    if (c === '\n') { endSeg(false); if (pending.length) i = readHeredocs(i); continue; }
    if (c === '(') { endSeg(false); depth++; continue; }
    if (c === ')') {
      endSeg(false);
      if (nested && depth === 0) return { lists, end: i };
      depth = Math.max(0, depth - 1);
      continue;
    }
    if (c === '{' && dialect !== 'cmd') {
      if (started && word.endsWith('@')) { word += c; braces++; } else endSeg(false);
      continue;
    }
    if (c === '}' && dialect !== 'cmd') {
      if (braces > 0) { word += c; braces--; } else endSeg(false);
      continue;
    }
    if (/\s/.test(c)) { endWord(); continue; }
    word += c;
    started = true;
  }
  endSeg(false);
  return { lists, end: src.length };
}

// ---------- command checks ----------

const DYNAMIC = /^[$%]/; // a word whose value only exists at run time: $var, $(...), %VAR%
const WRAPPERS = new Set(['sudo', 'doas', 'env', 'command', 'builtin', 'exec', 'time', 'nohup', 'call', 'nice', 'ionice', 'stdbuf',
  'timeout', 'xargs', 'runas', 'busybox', 'winpty', 'gsudo']);
const POSIX_SHELLS = new Set(['bash', 'sh', 'zsh', 'dash', 'ksh', 'ash', 'fish']);
const EVALS = new Set(['eval', 'iex', 'invoke-expression']);
const STARTERS = new Set(['start', 'start-process', 'saps']);
const READERS = new Set(['cat', 'tac', 'type', 'gc', 'get-content', 'more', 'less', 'head', 'tail', 'bat', 'nl', 'strings', 'xxd', 'od',
  'base64', 'certutil']);
const DELETERS = new Set(['rm', 'del', 'erase', 'remove-item', 'ri', 'rd', 'rmdir']);
const WRITERS = new Set([...DELETERS, 'set-content', 'add-content', 'ac', 'clear-content', 'clc', 'out-file', 'new-item', 'ni', 'tee',
  'tee-object', 'cp', 'copy', 'copy-item', 'cpi', 'xcopy', 'robocopy', 'mv', 'move', 'move-item', 'mi', 'ren', 'rename', 'rename-item',
  'rni', 'touch', 'truncate', 'sed', 'install', 'ln', 'mklink']);

function isKnown(w) {
  const e = exeName(w);
  return Object.hasOwn(RULES, e) || POSIX_SHELLS.has(e) || ['pwsh', 'powershell', 'cmd', 'wsl'].includes(e) || EVALS.has(e)
    || STARTERS.has(e) || READERS.has(e) || WRITERS.has(e) || WRAPPERS.has(e);
}

function checkCommand(cmd, depth, dialects = DIALECTS) {
  if (depth > MAX_DEPTH) return ask('the command nests shells too deeply to inspect');
  if (cmd.length > MAX_COMMAND) return ask('the command is too long to inspect');
  let worst = null;
  for (const dialect of dialects) {
    let lists;
    try {
      ({ lists } = parse(cmd, dialect));
    } catch (err) {
      if (!(err instanceof TooDeep)) throw err;
      worst = worse(worst, ask(err.message));
      continue;
    }
    for (const list of lists) {
      for (let k = 0; k < list.length; k++) {
        worst = worse(worst, checkSegment(list[k], list[k].piped ? list[k - 1] ?? null : null, depth));
        if (worst?.decision === 'deny') return worst;
      }
    }
  }
  return worst;
}

function checkSegment(seg, prev, depth) {
  let worst = null;
  for (const t of seg.targets) worst = worse(worst, checkPath(t));
  if (worst?.decision === 'deny') return worst;
  return worse(worst, checkWords(seg.words, { seg, prev, depth }));
}

function checkWords(words, ctx) {
  if (ctx.depth > MAX_DEPTH) return ask('the command nests shells too deeply to inspect');
  const nest = (cmd, dialects) => checkCommand(cmd, ctx.depth + 1, dialects);
  // Text a shell or eval would read from stdin: heredocs, here-strings and what the previous segment prints.
  const fed = (dialects = DIALECTS) => {
    const texts = [...ctx.seg.stdin];
    if (ctx.seg.piped && ctx.prev) {
      const pw = ctx.prev.words;
      texts.push(...ctx.prev.stdin, ...pw, pw.join(' '), pw.slice(1).join(' '));
    }
    let w = null;
    for (const t of texts) {
      if (!t.trim()) continue;
      w = worse(w, nest(t, dialects));
      if (w?.decision === 'deny') break;
    }
    return w;
  };

  let i = 0;
  while (i < words.length && /^[A-Za-z_][A-Za-z0-9_]*=/.test(words[i])) i++;
  // PowerShell assignment: `$r = git push` runs the command on the right-hand side.
  if (/^\$[\w:{}.]+$/.test(words[i] ?? '') && /^[-+*/]?=$/.test(words[i + 1] ?? '')) i += 2;
  while (i < words.length && WRAPPERS.has(exeName(words[i]))) {
    let j = i + 1;
    while (j < words.length && !isKnown(words[j]) && !DYNAMIC.test(words[j])) j++;
    if (j >= words.length) return null;
    i = j;
  }
  if (i >= words.length) return null;
  const exe = exeName(words[i]);
  const args = words.slice(i + 1);
  if (DYNAMIC.test(words[i]) && args.length) return ask('the program comes from a variable or substitution and cannot be inspected');

  if (POSIX_SHELLS.has(exe)) return posixShell(args, nest, fed);
  if (exe === 'pwsh' || exe === 'powershell') return powershell(args, nest, fed);
  if (exe === 'cmd') {
    const k = args.findIndex((a) => /^\/[ckr]/i.test(a));
    if (k < 0) return fed(['cmd']);
    return nest([args[k].slice(2), ...args.slice(k + 1)].filter(Boolean).join(' '), ['cmd']);
  }
  if (exe === 'wsl') return wsl(args, nest, fed);
  if (EVALS.has(exe)) {
    const rest = args.filter((a) => !/^-(c|co|com|comm|comma|comman|command)$/i.test(a));
    return rest.length ? nest(rest.join(' ')) : fed();
  }
  if (STARTERS.has(exe)) return starter(args, nest);

  let worst = null;
  if (READERS.has(exe)) for (const a of args) if (!a.startsWith('-')) worst = worse(worst, readSecret(a));
  if (WRITERS.has(exe) && (exe !== 'sed' || args.some((a) => /^(-[A-Za-z]*i|--in-place)/.test(a)))) {
    for (const a of args) if (!a.startsWith('-')) worst = worse(worst, checkPath(a, DELETERS.has(exe)));
  }
  if (Object.hasOwn(RULES, exe)) worst = worse(worst, RULES[exe](args));
  return worst;
}

function posixShell(args, nest, fed) {
  for (let k = 0; k < args.length; k++) {
    const a = args[k];
    if (/^-[A-Za-z]*c[A-Za-z]*$/.test(a)) return nest(args.slice(k + 1).join(' '));
    if (['-o', '+o', '-O', '+O', '--rcfile', '--init-file'].includes(a)) { k++; continue; }
    if (a === '-s') return fed();
    if (a === '--') return k + 1 < args.length ? null : fed();
    if (!/^[-+]/.test(a)) return null; // runs a script file; scripts are not resolved
  }
  return fed();
}

const PS_VALUE = ['executionpolicy', 'windowstyle', 'configurationname', 'configurationfile', 'workingdirectory', 'outputformat',
  'inputformat', 'version', 'psconsolefile', 'settingsfile', 'custompipename', 'encodedarguments'];

function powershell(args, nest, fed) {
  for (let k = 0; k < args.length; k++) {
    const m = /^(?:--?|\/)([A-Za-z]+)(?::(.*))?$/.exec(args[k]);
    if (!m) return nest(args.slice(k).join(' ')); // Windows PowerShell runs a positional argument as -Command
    const o = m[1].toLowerCase();
    const attached = m[2] !== undefined;
    const value = attached ? m[2] : args[k + 1];
    if (o === 'e' || o === 'ec' || (o.startsWith('en') && 'encodedcommand'.startsWith(o))) {
      if (value === undefined) return ask('PowerShell -EncodedCommand without a value');
      return nest(Buffer.from(value, 'base64').toString('utf16le'));
    }
    if (o === 'c' || o === 'cwa' || (o.length > 1 && ('command'.startsWith(o) || 'commandwithargs'.startsWith(o)))) {
      const rest = attached ? [m[2], ...args.slice(k + 1)] : args.slice(k + 1);
      return rest.length === 1 && rest[0] === '-' ? fed() : nest(rest.join(' '));
    }
    if ('file'.startsWith(o)) return value === '-' ? fed() : null; // script files are not resolved
    if (['ep', 'ex', 'w', 'wd', 'o', 'of', 'if', 'in', 'v'].includes(o) || (o.length >= 2 && PS_VALUE.some((x) => x.startsWith(o)))) {
      if (!attached && value !== undefined && !/^[-/]/.test(value)) k++;
    }
  }
  return fed();
}

function wsl(args, nest, fed) {
  if (args.some((a) => /^--(install|unregister|import|import-in-place|update|set-version|set-default-version|mount|manage)$/.test(a))) {
    return ask('wsl administration changes installed software');
  }
  let k = 0;
  for (; k < args.length; k++) {
    const a = args[k];
    if (['-d', '--distribution', '-u', '--user', '--cd', '--shell-type'].includes(a)) { k++; continue; }
    if (['--', '-e', '--exec'].includes(a)) { k++; break; }
    if (!a.startsWith('-')) break;
  }
  const rest = args.slice(k);
  return rest.length ? nest(rest.join(' ')) : fed();
}

const SP_VALUE = ['workingdirectory', 'verb', 'windowstyle', 'redirectstandardinput', 'redirectstandardoutput', 'redirectstandarderror',
  'credential', 'environment'];

// Start-Process / start: find the program among -FilePath and -ArgumentList values and check it as a command.
function starter(args, nest) {
  const parts = [];
  for (let k = 0; k < args.length; k++) {
    const m = /^-([A-Za-z]+)(?::.*)?$/.exec(args[k]);
    if (m) {
      const o = m[1].toLowerCase();
      if (o === 'wd' || (o.length >= 2 && SP_VALUE.some((x) => x.startsWith(o)))) k++;
      continue;
    }
    parts.push(...args[k].split(',').map((s) => s.trim()).filter(Boolean));
  }
  const j = parts.findIndex(isKnown);
  if (j < 0) return null;
  return nest([`"${parts[j]}"`, ...parts.slice(j + 1)].join(' '));
}

// ---------- program rules ----------

function riskyConfigKey(key) {
  return /^(alias\.|credential|url\.|include|remote\.|protocol\.|filter\.|core\.(hookspath|sshcommand|fsmonitor|gitproxy|askpass|pager|editor))/i.test(key)
    || /\.(textconv|command|driver|clean|smudge|process|program)$/i.test(key);
}

function git(args) {
  let i = 0;
  let worst = null;
  while (i < args.length && args[i].startsWith('-')) {
    const a = args[i];
    if (a === '-c' && riskyConfigKey(String(args[i + 1] ?? ''))) worst = ask('git -c can run commands through aliases or hooks');
    i += /^(-C|-c|--git-dir|--work-tree|--namespace|--config-env|--attr-source|--exec-path)$/.test(a) ? 2 : 1;
  }
  const sub = args[i] ?? '';
  const rest = args.slice(i + 1);
  if (DYNAMIC.test(sub)) return ask('the git subcommand comes from a variable or substitution and cannot be inspected');
  const positional = rest.filter((a) => !a.startsWith('-'));
  const verb = positional[0] ?? '';
  worst = worse(worst, gitSub(sub, rest, positional, verb));
  if (GIT_GUARDED.has(sub) && rest.some((a) => DYNAMIC.test(a))) {
    worst = worse(worst, ask(`git ${sub} arguments from variables or substitutions cannot be inspected`));
  }
  return worst;
}

// Subcommands whose risk depends on their flags; a flag hidden in a variable must not pass silently.
const GIT_GUARDED = new Set(['push', 'send-pack', 'http-push', 'reset', 'clean', 'add', 'worktree', 'checkout', 'switch', 'restore',
  'branch', 'tag', 'stash', 'rebase', 'merge', 'update-ref', 'reflog', 'gc']);

function gitSub(sub, rest, positional, verb) {
  switch (sub) {
    case 'push':
    case 'send-pack':
    case 'http-push':
      if (has(rest, '--force', '--mirror', '--force-if-includes') || shortHas(rest, 'f')
        || rest.some((a) => a.startsWith('--force-with-lease') || /^\+./.test(a))) return deny('force-push and history rewrite are forbidden');
      if (has(rest, '--delete', '--prune') || shortHas(rest, 'd') || rest.some((a) => /^:./.test(a))) return ask('deleting remote refs needs an owner order');
      return ask('git push needs explicit owner authorization');
    case 'reset':
      return rest.includes('--hard') ? deny('git reset --hard discards work and is forbidden') : null;
    case 'clean':
      if (has(rest, '-n', '--dry-run') || shortHas(rest, 'n')) return null;
      return has(rest, '--force') || shortHas(rest, 'f') ? deny('git clean deletes untracked files that may belong to someone else') : null;
    case 'add': {
      if (has(rest, '--all', '--no-ignore-removal', '.', './', '.\\', '*', './*', ':/', ':/.', ':/*', ':(top)') || shortHas(rest, 'A')) {
        return deny('stage explicit paths; git add -A / . is forbidden');
      }
      const s = positional.map(secretKind).find(Boolean);
      if (s) return deny(`never stage ${s}`);
      if (has(rest, '--force') || shortHas(rest, 'f')) return ask('git add -f stages ignored files');
      return has(rest, '--update') || shortHas(rest, 'u') ? ask('git add -u stages every tracked change; stage explicit paths') : null;
    }
    case 'commit':
      return has(rest, '--all', '--amend') || shortHas(rest, 'a') ? ask('commit -a/--amend: stage explicit paths and do not rewrite commits') : null;
    case 'worktree':
      if (verb === 'add') return deny('extra worktrees are forbidden unless the owner grants an exception');
      return !verb || verb === 'list' ? null : ask(`git worktree ${verb} changes checkouts`);
    case 'clone':
      return deny('extra clones are forbidden unless the owner grants an exception');
    case 'filter-branch':
    case 'filter-repo':
      return deny('history rewrite is forbidden');
    case 'rebase':
      return has(rest, '--abort', '--quit') ? null : ask('rebase rewrites history');
    case 'merge':
      return has(rest, '--abort') ? null : ask('merging changes main and needs an owner order');
    case 'checkout':
      if (has(rest, '-b', '-B', '--orphan')) return ask('temporary branches are not allowed by default');
      return has(rest, '--', '.', '-f', '--force') ? ask('this checkout can discard uncommitted changes') : null;
    case 'switch':
      if (has(rest, '-c', '-C', '--create', '--force-create', '--orphan')) return ask('temporary branches are not allowed by default');
      return has(rest, '--discard-changes', '-f', '--force') ? ask('this switch discards uncommitted changes') : null;
    case 'branch':
      if (has(rest, '-d', '-D', '--delete', '-m', '-M', '--move', '-c', '-C', '--copy', '-f', '--force')) return ask('deleting, renaming or forcing branches needs an owner order');
      if (rest.some((a) => /^(-a|-r|-l|-v|-vv|--all|--remotes|--list|--show-current|--contains|--merged|--no-merged|--points-at|--format|--sort)/.test(a))) return null;
      return positional.length ? ask('temporary branches are not allowed by default') : null;
    case 'tag':
      if (has(rest, '-d', '--delete')) return ask('deleting tags needs an owner order');
      if (rest.some((a) => /^(-l|-n\d*|--list|--contains|--points-at|--merged|--no-merged|--sort|--format)/.test(a))) return null;
      return positional.length ? ask('tags mark releases and need an owner order') : null;
    case 'stash':
      return ['drop', 'clear'].includes(verb) ? ask('stash drop/clear destroys saved work') : null;
    case 'restore':
      return rest.includes('--staged') && !has(rest, '--worktree', '-W') ? null : ask('git restore discards uncommitted changes');
    case 'update-ref':
      return ask('update-ref rewrites references');
    case 'init':
      return ask('git init creates a repository; one checkout per repository');
    case 'submodule':
      return !verb || ['status', 'summary'].includes(verb) ? null : ask(`git submodule ${verb} changes nested checkouts`);
    case 'remote':
      return ['add', 'set-url', 'remove', 'rm', 'rename', 'set-head', 'set-branches', 'prune'].includes(verb) ? ask(`git remote ${verb} changes where work is published`) : null;
    case 'config':
      if (has(rest, '-e', '--edit') || verb === 'edit') return ask('editing git config needs owner review');
      if (has(rest, '--get', '--get-all', '--get-regexp', '--list', '-l') || ['get', 'list'].includes(verb)) return null;
      return positional.some(riskyConfigKey) ? ask('this git config key can run commands or redirect pushes') : null;
    case 'reflog':
      return ['expire', 'delete'].includes(verb) ? ask('reflog expire/delete removes recovery points') : null;
    case 'gc':
      return rest.some((a) => a.startsWith('--prune')) ? ask('gc --prune removes recovery points') : null;
    case 'prune':
      return ask('git prune removes unreachable objects');
    case 'replace':
      return has(rest, '-l', '--list') || !rest.length ? null : ask('git replace rewrites object history');
    case 'symbolic-ref':
      return has(rest, '-d', '--delete') || positional.length > 1 ? ask('symbolic-ref changes references') : null;
    default:
      return null;
  }
}

const GH_API_VALUE = new Set(['-X', '--method', '-f', '-F', '--field', '--raw-field', '-H', '--header', '--input', '-q', '--jq', '-t',
  '--template', '--cache', '-p', '--preview', '--hostname', '-R', '--repo']);

function ghApi(args) {
  let method = null;
  let fields = false;
  let input = false;
  let endpoint = '';
  for (let k = args.indexOf('api') + 1; k < args.length; k++) {
    const a = args[k];
    const m = /^(?:-X|--method)=?(.*)$/.exec(a);
    if (m) method = (m[1] || args[k + 1] || '').toUpperCase();
    if (/^(-f|-F|--field|--raw-field)(=|$)/.test(a) || /^-[fF]./.test(a)) fields = true;
    if (/^--input(=|$)/.test(a)) input = true;
    if (GH_API_VALUE.has(a)) { k++; continue; }
    if (!a.startsWith('-') && !endpoint) endpoint = a;
  }
  method ??= fields || input ? 'POST' : 'GET';
  const text = args.join(' ');
  if (endpoint === 'graphql') {
    if (input) return ask('a GraphQL request read from a file cannot be inspected');
    if (!/\bmutation\b/i.test(text)) return null;
    return /visibility|deleteRepository|archiveRepository|transferRepository/i.test(text)
      ? deny('never delete, archive, transfer or change the visibility of a repository from an agent')
      : ask('GraphQL mutations change GitHub state');
  }
  if (method === 'GET' || method === 'HEAD') return null;
  if (/visibility|(^|[\s"'])private=/i.test(text)) return deny('never change repository visibility from an agent');
  if (method === 'DELETE' && /^\/?repos\/[^/]+\/[^/]+\/?$/.test(endpoint)) return deny('deleting a repository is forbidden');
  return ask('gh api write calls change GitHub state');
}

function gh(args) {
  const pos = [];
  for (let k = 0; k < args.length; k++) {
    if (['-R', '--repo', '--hostname'].includes(args[k])) { k++; continue; }
    if (!args[k].startsWith('-')) pos.push(args[k]);
  }
  const [area = '', verb = ''] = pos;
  const reads = (...ok) => (ok.includes(verb) ? null : ask(`gh ${area} ${verb} changes GitHub state or the checkout`));
  switch (area) {
    case 'repo':
      if (verb === 'delete') return deny('deleting a repository is forbidden');
      if (verb === 'clone') return deny('extra clones are forbidden unless the owner grants an exception');
      if (verb === 'edit' && args.some((a) => a.startsWith('--visibility'))) return deny('never change repository visibility from an agent');
      return !verb ? null : reads('view', 'list');
    case 'api':
      return ghApi(args);
    case 'release':
      return reads('list', 'view');
    case 'pr':
      return reads('list', 'view', 'diff', 'checks', 'status');
    case 'issue':
      return reads('list', 'view', 'status');
    case 'auth':
      return reads('status');
    case 'gist':
      return reads('list', 'view');
    case 'extension':
    case 'extensions':
    case 'ext':
      return reads('list', 'ls', 'search', 'browse');
    case 'alias':
    case 'config':
      return reads('list', 'ls', 'get');
    case 'workflow':
    case 'run':
    case 'secret':
    case 'variable':
    case 'ruleset':
    case 'label':
    case 'cache':
    case 'project':
    case 'codespace':
    case 'cs':
      return reads('list', 'ls', 'view', 'watch', 'check');
    default:
      return null;
  }
}

// Package managers and runners: read-only subcommands pass, everything else asks.
function readOnly(tool, ro, opts = {}) {
  return (args) => {
    if (versionOnly(args)) return null;
    const sub = first(args);
    if (!sub) return opts.bareAsks ? ask(`bare ${tool} installs dependencies`) : null;
    if (sub === 'audit' && !args.some((a) => /^(fix|--fix)$/.test(a))) return null;
    if (sub === 'config' && ['get', 'list', 'ls'].includes(args[args.indexOf('config') + 1])) return null;
    return ro.includes(sub) ? null : ask(`${tool} ${sub} installs, builds, tests or runs code and needs owner authorization`);
  };
}

function web(args) {
  let worst = null;
  for (let k = 0; k < args.length; k++) {
    const a = args[k];
    const v = args[k + 1];
    const m = /^(?:-X|--request|--method)=?(.*)$/.exec(a) ?? /^-method(?::(.*))?$/i.exec(a);
    if (m) {
      const method = (m[1] || v || '').toUpperCase();
      if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) worst = worse(worst, ask(`HTTP ${method} sends data and needs owner authorization`));
      continue;
    }
    if (/^(-d|--data.*|-F|--form.*|-T|--upload-file|--json|--post-data|--post-file|--body-data|--body-file)(=|$)/.test(a)
      || /^-d./.test(a) || /^-(body|infile|form)(:|$)/i.test(a)) worst = worse(worst, ask('uploading data needs owner authorization'));
    if (v && (/^(-o|--output|-O|--output-document)$/.test(a) || /^-outfile$/i.test(a))) worst = worse(worst, checkPath(v));
  }
  return worst;
}

const SVN_READ = new Set(['status', 'st', 'info', 'log', 'diff', 'di', 'cat', 'list', 'ls', 'help', 'blame', 'praise', 'annotate', 'update', 'up']);
const always = (what) => (args) => (versionOnly(args) ? null : ask(`${what} needs explicit owner authorization`));
const installers = (tool) => (args) => (['install', 'upgrade', 'uninstall', 'update', 'add', 'remove'].includes(first(args)) ? ask(`${tool} changes installed software`) : null);
const packs = (args) => (['a', 'u'].includes(args[0]) ? ask('packaging archives needs owner authorization') : null);

const RULES = {
  git,
  gh,
  npm: readOnly('npm', ['view', 'v', 'info', 'show', 'ls', 'list', 'la', 'll', 'outdated', 'help', 'explain', 'why', 'search', 'docs', 'prefix', 'root', 'bin', 'query', 'fund', 'ping']),
  pnpm: readOnly('pnpm', ['view', 'info', 'ls', 'list', 'll', 'la', 'outdated', 'why', 'help', 'licenses', 'root', 'bin']),
  yarn: readOnly('yarn', ['info', 'why', 'list', 'outdated', 'help', 'licenses'], { bareAsks: true }),
  bun: readOnly('bun', []),
  composer: readOnly('composer', ['show', 'info', 'outdated', 'why', 'depends', 'prohibits', 'why-not', 'licenses', 'validate', 'diagnose', 'help', 'list', 'search', 'check-platform-reqs', 'fund', 'suggests']),
  ng: readOnly('ng', ['version', 'v', 'help']),
  dotnet: readOnly('dotnet', ['help', 'list', 'sdk']),
  php: (args) => (args.every((a) => /^(-v|--version|-l|-m|-i|--ini|-h|--help)$/.test(a) || !a.startsWith('-')) && has(args, '-v', '--version', '-l', '-m', '-i', '--ini', '-h', '--help') ? null : ask('running PHP scripts needs owner authorization')),
  '7z': packs,
  '7za': packs,
  tar: (args) => (args.some((a) => /^-[A-Za-z]*c/.test(a)) || has(args, '--create') || (args[0] && !args[0].startsWith('-') && /c/.test(args[0])) ? ask('packaging archives needs owner authorization') : null),
  svn: (args) => { const sub = first(args); return !sub || SVN_READ.has(sub) ? null : ask(`svn ${sub} changes a repository or working copy`); },
  curl: web,
  wget: web,
  'invoke-webrequest': web,
  iwr: web,
  'invoke-restmethod': web,
  irm: web,
  winget: installers('winget'),
  choco: installers('choco'),
  scoop: installers('scoop'),
  pip: installers('pip'),
  pip3: installers('pip3'),
  pipx: installers('pipx'),
  gem: installers('gem'),
  cargo: installers('cargo'),
};
for (const t of ['npx', 'pnpx', 'bunx', 'corepack', 'node', 'deno', 'tsx', 'ts-node', 'nvm', 'fnm', 'volta', 'wp', 'wp-env', 'phpunit',
  'pest', 'phpcs', 'phpcbf', 'phpstan', 'psalm', 'playwright', 'jest', 'vitest', 'karma', 'cypress', 'webpack', 'vite', 'esbuild', 'rollup',
  'tsc', 'eslint', 'prettier', 'stylelint', 'docker', 'docker-compose', 'podman', 'nerdctl', 'zip', 'compress-archive', 'msbuild',
  'ssh', 'scp', 'sftp', 'rsync', 'ftp', 'lftp', 'rclone', 'signtool', 'python', 'python3', 'py', 'perl', 'ruby']) RULES[t] = always(t);

// ---------- file rules ----------

function secretKind(p) {
  const base = String(p).replace(/\\/g, '/').split('/').pop().toLowerCase();
  if (/^\.env(\.|$)/.test(base) && !/\.(example|sample|dist|template)$/.test(base)) return `${base}, which holds secrets`;
  if (/\.(pem|key|p12|pfx|jks|keystore|ppk)$/.test(base) || /^id_(rsa|ed25519|ecdsa|dsa)/.test(base)
    || ['.netrc', '_netrc', '.git-credentials'].includes(base)) return `${base}, which is key or credential material`;
  if (base === 'wp-config.php') return 'wp-config.php, which holds site credentials';
  return null;
}

function readSecret(p) {
  const s = secretKind(p);
  return s ? deny(`never print ${s}, into the transcript`) : null;
}

function dangerousTarget(n) {
  const t = n.trim().toLowerCase();
  const u = t.length > 1 ? t.replace(/\/+$/, '') : t;
  return ['/', '/*', '~', '~/*', '.', '..', './*', '*', '$home', '${home}', '$env:userprofile', '%userprofile%'].includes(u)
    || /^[a-z]:\/?\*?$/.test(u) || /(^|\/)\.git$/.test(u);
}

function checkPath(p, deleting = false) {
  const n = String(p).replace(/\\/g, '/');
  const s = secretKind(n);
  if (s) return deny(`agents never write ${s}`);
  if (deleting && dangerousTarget(n)) return deny(`deleting ${p} would destroy a root, a home folder, the checkout or its history`);
  const base = n.split('/').pop().toLowerCase();
  if (['auth.json', '.npmrc', '.pypirc'].includes(base)) return ask(`${base} may hold registry tokens`);
  if (/(^|\/)\.claude\/settings(\.local)?\.json$/i.test(n) || /(^|\/)\.codex(\/|$)/i.test(n) || /(^|\/)\.agents\/hooks\.json$/i.test(n)
    || /(^|\/)tools\/agent-policy(\/|$)/i.test(n) || /(^|\/)\.github\/workflows(\/|$)/i.test(n)) return ask('this file changes agent or CI controls and needs owner review');
  if (/(^|\/)\.git(\/|$)/i.test(n)) return ask('writing inside .git can corrupt history; use git commands');
  return null;
}

// ---------- client output ----------

function emit(result) {
  const reason = result ? `VSG policy: ${result.reason}. See AGENTS.md (authorization matrix).` : '';
  if (client === 'claude') {
    if (result) process.stdout.write(JSON.stringify({ hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: result.decision, permissionDecisionReason: reason } }));
    return 0;
  }
  if (client === 'codex') {
    // Codex 0.171 runs hooks in the session shell (PowerShell on Windows, else cmd.exe /C), and
    // PowerShell -Command turns exit code 2 into 1, which Codex treats as a failed hook and lets the call
    // through. A JSON deny on stdout with exit 0 blocks in every shell. Codex rejects "ask", so
    // authorisable commands only get a warning here; .codex/rules/vsg.rules prompts for them.
    if (result?.decision === 'deny') process.stdout.write(JSON.stringify(codexDeny(reason)));
    else if (result) process.stdout.write(JSON.stringify({ systemMessage: reason }));
    return 0;
  }
  // Antigravity: print a decision only when the policy objects. With no objection nothing is printed,
  // so the client's own permission settings still decide (never "allow" or "auto_approve" from here).
  if (result) process.stdout.write(JSON.stringify({ decision: result.decision, reason }));
  return 0;
}

const codexDeny = (reason) => ({ hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'deny', permissionDecisionReason: reason } });

function failClosed(message) {
  const reason = `VSG policy guard blocked this call because it could not check it (fail closed): ${message}`;
  process.stderr.write(`${reason}\n`);
  // Unknown or missing --client: exit 2 plus a generic deny, which most hook runners treat as a block.
  if (client === 'codex') process.stdout.write(JSON.stringify(codexDeny(reason)));
  else if (client !== 'claude') process.stdout.write(JSON.stringify({ decision: 'deny', reason }));
  process.exitCode = client === 'codex' || client === 'antigravity' ? 0 : 2;
}

try {
  if (!CLIENTS.has(client)) throw new Error(`unknown --client "${client}"`);
  const chunks = [];
  for await (const chunk of process.stdin) chunks.push(chunk);
  const text = Buffer.concat(chunks).toString('utf8').replace(/^﻿/, '').trim();
  if (!text) throw new Error('empty hook payload');
  const payload = JSON.parse(text);
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new Error('the hook payload is not a JSON object');
  const { commands, argvs, paths } = normalize(payload);
  let result = null;
  for (const c of commands) result = worse(result, checkCommand(c, 0));
  for (const a of argvs) result = worse(result, checkWords(a, { seg: { stdin: [], piped: false }, prev: null, depth: 0 }));
  for (const p of paths) result = worse(result, checkPath(p));
  process.exitCode = emit(result);
} catch (err) {
  failClosed(err?.message ?? String(err));
}
