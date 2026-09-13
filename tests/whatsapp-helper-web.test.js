const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

function extract(source, start, end) {
  const startIndex = source.indexOf(start);
  const endIndex = source.indexOf(end, startIndex);
  assert.notStrictEqual(startIndex, -1, `missing ${start}`);
  assert.notStrictEqual(endIndex, -1, `missing ${end}`);
  return source.slice(startIndex, endIndex);
}

function createContext(userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)') {
  const writes = [];
  class BlobMock {
    constructor(parts, options) {
      this.parts = parts;
      this.type = options.type;
    }
  }
  class ClipboardItemMock {
    constructor(data) {
      this.data = data;
    }
  }

  const context = {
    Blob: BlobMock,
    ClipboardItem: ClipboardItemMock,
    navigator: {
      userAgent,
      clipboard: {
        write: async (items) => writes.push(items),
      },
    },
    window: {
      isSecureContext: true,
      ClipboardItem: ClipboardItemMock,
      location: { href: '' },
    },
    console,
  };
  return { context, writes };
}

async function testPage(page, copyFunction) {
  const source = fs.readFileSync(page, 'utf8');
  const functions = extract(source, `async function ${copyFunction}`, 'function canShareFile');
  const { context, writes } = createContext();
  vm.createContext(context);
  new vm.Script(functions, { filename: page }).runInContext(context);

  const image = { type: 'image/png', marker: 'invoice' };
  assert.strictEqual(await context[copyFunction](image, 'Caption invoice'), true, `${copyFunction} succeeds`);
  assert.strictEqual(writes.length, 1, `${copyFunction} writes once`);
  const formats = writes[0][0].data;
  assert.strictEqual(formats['image/png'], image, `${copyFunction} keeps PNG`);
  assert.strictEqual(formats['text/plain'].type, 'text/plain', `${copyFunction} writes plain-text caption`);
  assert.strictEqual(formats['text/plain'].parts.length, 1, `${copyFunction} writes one caption part`);
  assert.strictEqual(formats['text/plain'].parts[0], 'Caption invoice', `${copyFunction} keeps caption text`);

  assert.strictEqual(context.isWindowsDesktop(), true, `${copyFunction} detects Windows`);
  context.openWindowsWhatsAppHelper('628123456789');
  assert.strictEqual(
    context.window.location.href,
    'sikswa://compose?phone=628123456789',
    `${copyFunction} invokes the constrained helper URI`,
  );

  const insecure = createContext();
  insecure.context.window.isSecureContext = false;
  vm.createContext(insecure.context);
  new vm.Script(functions, { filename: page }).runInContext(insecure.context);
  assert.strictEqual(await insecure.context[copyFunction](image, 'Caption invoice'), false, `${copyFunction} refuses insecure clipboard`);
  assert.strictEqual(insecure.writes.length, 0, `${copyFunction} does not write without secure context`);

  const nonWindows = createContext('Mozilla/5.0 (X11; Linux x86_64)');
  vm.createContext(nonWindows.context);
  new vm.Script(functions, { filename: page }).runInContext(nonWindows.context);
  assert.strictEqual(nonWindows.context.isWindowsDesktop(), false, `${copyFunction} does not intercept non-Windows browsers`);

  const helperBranch = extract(source, 'if (useWindowsHelper) {', '// Mobile browser: Web Share');
  assert.match(helperBranch, new RegExp(`${copyFunction}\\(blob, .*Message\\)`), `${copyFunction} prepares image plus caption before opening helper`);
  assert.match(helperBranch, /openWindowsWhatsAppHelper\(/, `${copyFunction} opens helper from Windows branch`);
  assert.doesNotMatch(helperBranch, /downloadImage\(/, `${copyFunction} never downloads in Windows helper branch`);

  const nativeIndex = source.indexOf('WhatsAppShareChannel.postMessage');
  const helperIndex = source.indexOf('if (useWindowsHelper) {');
  assert(nativeIndex !== -1 && nativeIndex < helperIndex, `${copyFunction} retains native branch before Windows helper`);
}

async function run() {
  await testPage('pembayaran/invoice-view.php', 'copyInvoiceToClipboard');
  await testPage('pembayaran/verifikasi-wa.php', 'copyReceiptToClipboard');
  console.log('PASS: WhatsApp helper web tests');
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
