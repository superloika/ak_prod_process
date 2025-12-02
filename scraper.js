const puppeteer = require("puppeteer");

(async () => {
    const url = process.argv[2]; // pass URL from command line
    const browser = await puppeteer.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: "networkidle2" });
    const html = await page.content();
    console.log(JSON.stringify(html, null, 2));
    await browser.close();
})();
