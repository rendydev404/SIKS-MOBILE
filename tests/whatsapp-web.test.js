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

function createContext() {
  const writes = [];
  class ClipboardItemMock {
    constructor(data) {
      this.data = data;
    }
  }

  const context = {
    ClipboardItem: ClipboardItemMock,
    navigator: {
      clipboard: {
        write: async (items) => writes.push(items),
      },
    },
    window: {
      isSecureContext: true,
      ClipboardItem: ClipboardItemMock,
    },
    console,
  };
  return { context, writes };
}

async function testImageClipboard(page, copyFunction) {
  const source = fs.readFileSync(page, 'utf8');
  const functions = extract(source, `async function ${copyFunction}`, 'function openWhatsApp');
  const { context, writes } = createContext();
  vm.createContext(context);
  new vm.Script(functions, { filename: page }).runInContext(context);

  const image = { type: 'image/png', marker: 'invoice' };
  const imagePromise = Promise.resolve(image);
  assert.strictEqual(await context[copyFunction](imagePromise), true, `${copyFunction} succeeds`);
  assert.strictEqual(writes.length, 1, `${copyFunction} writes once`);
  const formats = writes[0][0].data;
  assert.deepStrictEqual(Object.keys(formats), ['image/png'], `${copyFunction} copies image only`);
  assert.strictEqual(formats['image/png'], imagePromise, `${copyFunction} starts clipboard write with render promise`);

  const insecure = createContext();
  insecure.context.window.isSecureContext = false;
  vm.createContext(insecure.context);
  new vm.Script(functions, { filename: page }).runInContext(insecure.context);
  assert.strictEqual(await insecure.context[copyFunction](image), false, `${copyFunction} refuses insecure clipboard`);
  assert.strictEqual(insecure.writes.length, 0, `${copyFunction} does not write without secure context`);
}

function testBrowserFlow(page, copyFunction) {
  const source = fs.readFileSync(page, 'utf8');
  const nativeIndex = source.indexOf('WhatsAppShareChannel.postMessage');
  const browserCopyIndex = source.indexOf(`${copyFunction}(imagePromise)`);

  assert.notStrictEqual(nativeIndex, -1, `${page} retains native WhatsApp bridge`);
  assert.notStrictEqual(browserCopyIndex, -1, `${page} copies image in browser flow`);
  assert.match(source, new RegExp(`const clipboardPromise = useNativeShare\\s*\\?\\s*null\\s*:\\s*${copyFunction}\\(imagePromise\\)`), `${page} guards clipboard flow from native app`);
  assert.match(source, /const (invoice|receipt)WaLink =/i, `${page} creates a wa.me link`);
  assert.match(source, /openWhatsApp\(waWindow\)/, `${page} opens WhatsApp after clipboard preparation`);
  assert.match(source, /Ctrl\+V/, `${page} explains the paste step`);
  assert.doesNotMatch(source, /sikswa:|downloadWhatsAppHelper|navigator\.share|canShareFile|useWindowsHelper/, `${page} has no helper/share flow`);
}

async function run() {
  await testImageClipboard('pembayaran/invoice-view.php', 'copyInvoiceImageToClipboard');
  await testImageClipboard('pembayaran/verifikasi-wa.php', 'copyReceiptImageToClipboard');
  testBrowserFlow('pembayaran/invoice-view.php', 'copyInvoiceImageToClipboard');
  testBrowserFlow('pembayaran/verifikasi-wa.php', 'copyReceiptImageToClipboard');

  const listPage = fs.readFileSync('pembayaran/kirim-invoice.php', 'utf8');
  assert.match(listPage, /Caption otomatis terisi/);
  assert.match(listPage, /Ctrl\+V/);
  assert.doesNotMatch(listPage, /downloadWhatsAppHelper|whatsapp-helper-download|helper-download-button/);

  console.log('PASS: WhatsApp wa.me web tests');
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
