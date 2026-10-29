import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {PNG} from 'pngjs';
import jsQR from 'jsqr';

test('generated admit QR decodes to its exact signed verification URL and contains no PII', () => {
  const result=spawnSync(process.env.PHP_BINARY||'php',['tests/frontend/generate-qr.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stderr);
  const {url,image}=JSON.parse(result.stdout);
  const png=PNG.sync.read(Buffer.from(image.split(',')[1],'base64'));
  const decoded=jsQR(new Uint8ClampedArray(png.data),png.width,png.height);
  assert(decoded,'QR must be readable by an independent decoder');
  assert.equal(decoded.data,url);
  const address=new URL(decoded.data);
  assert.equal(address.pathname,'/verify/admit/00000000-0000-4000-8000-000000000001');
  assert.deepEqual([...address.searchParams.keys()],['signature']);
  assert.match(address.searchParams.get('signature'),/^[a-f0-9]{64}$/);
});
