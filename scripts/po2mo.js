/**
 * Minimal PO → MO converter.
 * Usage: node scripts/po2mo.js <input.po> <output.mo>
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const poPath = process.argv[2];
const moPath = process.argv[3];

// 引数なし: languages/ 配下の .po を全て変換
if (!poPath) {
  const langDir = path.join(__dirname, '../rssemble-cards-for-rss-feeds/languages');
  const files = fs.readdirSync(langDir).filter(f => f.endsWith('.po'));
  if (files.length === 0) { console.log('No .po files found.'); process.exit(0); }
  let exitCode = 0;
  for (const f of files) {
    const src = path.join(langDir, f);
    const dst = src.replace(/\.po$/, '.mo');
    const result = spawnSync(process.execPath, [__filename, src, dst], { stdio: 'inherit' });
    if (result.status !== 0) { exitCode = 1; }
  }
  process.exit(exitCode);
}

if (!moPath) {
  console.error('Usage: node po2mo.js <input.po> <output.mo>');
  process.exit(1);
}

const po = fs.readFileSync(poPath, 'utf8');

const entries = [];
const blocks = po.split(/\n\n+/);

for (const block of blocks) {
  const msgidMatch = block.match(/^msgid "((?:[^"\\]|\\.)*)"\s*$/m);
  const msgstrMatch = block.match(/^msgstr "((?:[^"\\]|\\.)*)"\s*$/m);
  if (!msgidMatch || !msgstrMatch) continue;
  const msgid = msgidMatch[1].replace(/\\n/g, '\n').replace(/\\"/g, '"');
  const msgstr = msgstrMatch[1].replace(/\\n/g, '\n').replace(/\\"/g, '"');
  if (msgid !== '' && msgstr !== '') {
    entries.push({ msgid, msgstr });
  }
}

function encodeStr(s) {
  return Buffer.from(s + '\0', 'utf8');
}

const originals    = entries.map(e => encodeStr(e.msgid));
const translations = entries.map(e => encodeStr(e.msgstr));

const nstrings         = entries.length;
const origTableOffset  = 28;
const transTableOffset = origTableOffset + nstrings * 8;
const stringsOffset    = transTableOffset + nstrings * 8;

let strData   = Buffer.alloc(0);
const origTable  = [];
const transTable = [];
let offset = stringsOffset;

for (let i = 0; i < nstrings; i++) {
  origTable.push({ len: originals[i].length - 1, off: offset });
  strData = Buffer.concat([strData, originals[i]]);
  offset += originals[i].length;
}
for (let i = 0; i < nstrings; i++) {
  transTable.push({ len: translations[i].length - 1, off: offset });
  strData = Buffer.concat([strData, translations[i]]);
  offset += translations[i].length;
}

const header = Buffer.alloc(28);
header.writeUInt32LE(0x950412de, 0); // magic
header.writeUInt32LE(0, 4);          // revision
header.writeUInt32LE(nstrings, 8);
header.writeUInt32LE(origTableOffset, 12);
header.writeUInt32LE(transTableOffset, 16);
header.writeUInt32LE(0, 20);
header.writeUInt32LE(0, 24);

const origBuf  = Buffer.alloc(nstrings * 8);
const transBuf = Buffer.alloc(nstrings * 8);
for (let i = 0; i < nstrings; i++) {
  origBuf.writeUInt32LE(origTable[i].len, i * 8);
  origBuf.writeUInt32LE(origTable[i].off, i * 8 + 4);
  transBuf.writeUInt32LE(transTable[i].len, i * 8);
  transBuf.writeUInt32LE(transTable[i].off, i * 8 + 4);
}

const mo = Buffer.concat([header, origBuf, transBuf, strData]);
fs.writeFileSync(moPath, mo);
console.log('Generated ' + moPath + ' (' + nstrings + ' entries)');
