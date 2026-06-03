/**
 * Create gridify-image-cards-for-rss.zip excluding WordPress.org directory assets.
 * Usage: node scripts/make-zip.js
 */
const fs   = require('fs');
const path = require('path');
const zlib = require('zlib');

const ROOT = path.join(__dirname, '..');
const SRC  = path.join(ROOT, 'gridify-image-cards-for-rss');
const OUT  = path.join(ROOT, 'gridify-image-cards-for-rss.zip');

const EXCLUDE = new Set([
  'assets/screenshot-1.png',
  'assets/screenshot-2.png',
  'assets/banner-772x250.png',
  'assets/banner-1544x500.png',
  'assets/icon-256x256.png',
  'assets/icon-128x128.png',
]);

// Minimal ZIP writer (Store or Deflate) — no external deps required.
function makeZip(srcDir, outPath, exclude) {
  const entries = [];

  function walk(dir, base) {
    for (const name of fs.readdirSync(dir).sort()) {
      const full = path.join(dir, name);
      const rel  = base ? `${base}/${name}` : name;
      const stat = fs.statSync(full);
      if (stat.isDirectory()) {
        walk(full, rel);
      } else {
        if (exclude.has(rel)) {
          console.log(`  skip: ${rel}`);
          continue;
        }
        entries.push({ full, rel });
      }
    }
  }
  walk(srcDir, '');

  const parts   = [];
  const central = [];
  let offset    = 0;

  for (const { full, rel } of entries) {
    const data       = fs.readFileSync(full);
    const compressed = zlib.deflateRawSync(data, { level: 6 });
    const useDeflate = compressed.length < data.length;
    const body       = useDeflate ? compressed : data;

    const nameBytes = Buffer.from('gridify-image-cards-for-rss/' + rel, 'utf8');
    const crc32     = crc(data);
    const now       = new Date();
    const dosDate   = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();
    const dosTime   = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1);

    // Local file header
    const lh = Buffer.alloc(30 + nameBytes.length);
    lh.writeUInt32LE(0x04034b50, 0);      // signature
    lh.writeUInt16LE(20, 4);              // version needed
    lh.writeUInt16LE(0, 6);               // flags
    lh.writeUInt16LE(useDeflate ? 8 : 0, 8);  // compression
    lh.writeUInt16LE(dosTime, 10);
    lh.writeUInt16LE(dosDate, 12);
    lh.writeUInt32LE(crc32 >>> 0, 14);
    lh.writeUInt32LE(body.length, 18);
    lh.writeUInt32LE(data.length, 22);
    lh.writeUInt16LE(nameBytes.length, 26);
    lh.writeUInt16LE(0, 28);
    nameBytes.copy(lh, 30);

    parts.push(lh, body);

    // Central directory entry
    const cd = Buffer.alloc(46 + nameBytes.length);
    cd.writeUInt32LE(0x02014b50, 0);
    cd.writeUInt16LE(20, 4);
    cd.writeUInt16LE(20, 6);
    cd.writeUInt16LE(0, 8);
    cd.writeUInt16LE(useDeflate ? 8 : 0, 10);
    cd.writeUInt16LE(dosTime, 12);
    cd.writeUInt16LE(dosDate, 14);
    cd.writeUInt32LE(crc32 >>> 0, 16);
    cd.writeUInt32LE(body.length, 20);
    cd.writeUInt32LE(data.length, 24);
    cd.writeUInt16LE(nameBytes.length, 28);
    cd.writeUInt16LE(0, 30);
    cd.writeUInt16LE(0, 32);
    cd.writeUInt16LE(0, 34);
    cd.writeUInt16LE(0, 36);
    cd.writeUInt32LE(0, 38);
    cd.writeUInt32LE(offset, 42);
    nameBytes.copy(cd, 46);
    central.push(cd);

    offset += lh.length + body.length;
  }

  const cdBuf   = Buffer.concat(central);
  const eocd    = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(0, 4);
  eocd.writeUInt16LE(0, 6);
  eocd.writeUInt16LE(central.length, 8);
  eocd.writeUInt16LE(central.length, 10);
  eocd.writeUInt32LE(cdBuf.length, 12);
  eocd.writeUInt32LE(offset, 16);
  eocd.writeUInt16LE(0, 20);

  fs.writeFileSync(outPath, Buffer.concat([...parts, cdBuf, eocd]));
}

// CRC-32
function crc(buf) {
  let c = 0xFFFFFFFF;
  for (let i = 0; i < buf.length; i++) {
    c ^= buf[i];
    for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
  }
  return (c ^ 0xFFFFFFFF) >>> 0;
}

makeZip(SRC, OUT, EXCLUDE);
const kb = Math.round(fs.statSync(OUT).size / 1024);
console.log(`Done: gridify-image-cards-for-rss.zip (${kb} KB)`);
