const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

(async () => {
  const url = process.argv[2];
  if (!url) {
    console.log(JSON.stringify({ status: 'error', error: 'No URL provided' }));
    process.exit(1);
  }

  let browser;
  try {
    browser = await puppeteer.launch({
      headless: 'new',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-blink-features=AutomationControlled',
      ],
    });
    const page = await browser.newPage();
    await page.setExtraHTTPHeaders({
      'Accept-Language': 'fr-FR,fr;q=0.9,en;q=0.8',
    });

    const response = await page.goto(url, {
      waitUntil: 'networkidle2',
      timeout: 30000,
    });

    // If Cloudflare challenge page, wait extra time for it to resolve
    const content = await page.content();
    if (content.includes('challenge-platform') || content.includes('Just a moment')) {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {});
    }

    const html = await page.content();
    const finalUrl = page.url();
    const httpCode = response ? response.status() : 0;

    console.log(JSON.stringify({ status: 'ok', html, finalUrl, httpCode }));
  } catch (err) {
    console.log(JSON.stringify({ status: 'error', error: err.message }));
  } finally {
    if (browser) await browser.close();
  }
})();
