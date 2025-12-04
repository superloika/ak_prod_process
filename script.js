// === Tab switching ===
document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".tab-btn").forEach(b => {
            b.classList.remove("text-indigo-600", "border-indigo-600");
        });
        btn.classList.add("text-indigo-600", "border-indigo-600");

        document.querySelectorAll(".tab-content").forEach(tab => tab.classList.add("hidden"));
        document.getElementById(btn.dataset.tab).classList.remove("hidden");
    });
});

const processBtn = document.getElementById('processBtn');
const sourceHTML = document.getElementById('sourceHTML');
const templatePreview = document.getElementById('templatePreview');
const pasteClipboardBtn = document.getElementById('pasteClipboardBtn');
const copyOutputBtn = document.getElementById('copyOutputBtn');

// watermarked img paths
let wm_img_paths = [

];

let finalProductBody = '';
let uploading_inprogress= false;

const tpDescription = `<div id="prod_desc" class="kcard">
<div class="kcard-b">
PROD_DESC
</div>
</div>`;

const tpSpecs = `<div id="prod_specs" class="kcard">
<div class="kcard-h">Product Specifications</div>
<div class="kcard-b">
SPECS_TABLE
</div>
</div>`;

const tpFitments = `<div id="prod_fitments" class="kcard not-hidden">
<div class="kcard-h">Fitments</div>
<div class="kcard-b">
<div id="Models" class="mb-5">
FITMENTS_TABLE
</div>
</div>
</div>`;

const tpXtraDetails = `<div id="prod_xdetails" class="kcard hidden">
<div class="kcard-h">Extra Details</div>
<div class="kcard-b">
<div id="Models" class="mb-5">
EXTRA_DETAILS
</div>
</div>
</div>`;

const tpAccessories = `<div id="prod_acc" class="kcard hidden">
<div class="kcard-h">Accessories</div>
<div class="kcard-b">[products name="accessories" skus="123"]</div>
</div>`;

const statusMessage = document.getElementById("statusMessage");

function showMessage(msg) {
    statusMessage.textContent = `✅ ${msg}`;
    statusMessage.style.opacity = 1;
    setTimeout(() => {
        statusMessage.style.opacity = 0;
        statusMessage.textContent = "";
    }, 3000);
}

function copyInputValueById(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const value = el.value ?? '';
    navigator.clipboard.writeText(value)
        .then(() => showMessage(`Copied value from #${elementId} to clipboard.`))
        .catch(err => console.error('Failed to copy:', err));
}

async function sendMultipleImagesToPhp(imageUrls, phpEndpoint) {
    const resultsDiv = document.getElementById('resultsContainer');
    const formData = new FormData();

    let counter = 1;
    for (const url of imageUrls) {
        const response = await fetch(url);
        const blob = await response.blob();

        // Generate a filename
        const extension = blob.type.split('/')[1] || 'jpg';
        const sku = document.getElementById("productSKU").value;
        const filename = `${sku}-${counter}.${extension}`;

        formData.append('images[]', blob, filename);
        counter++;
    }

    // Send all images to PHP at once
    fetch(phpEndpoint, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then((result) => {
        let html = `<table class="border border-slate-100 w-full">
                <tr class="bg-slate-50"><th class="p-2 border">Image URL</th><th class="p-2 border">Status</th><th class="p-2 border">Original</th><th class="p-2 border">Watermarked</th></tr>`;

        wm_img_paths = [];
        let ctr = 0;
        result.results.forEach(res => {
            html += `<tr>
                    <td class="p-2 border">${res.url}</td>
                    <td class="p-2 border">${res.status}</td>
                    <td class="p-2 border">${res.original ? `<img src="output/${encodeURIComponent(res.original)}" class="w-48 h-auto" />` : ''}</td>
                    <td class="p-2 border">${res.watermarked ? `<img src="output/${encodeURIComponent(res.watermarked)}" class="w-48 h-auto" />` : ''}</td>
                </tr>`;
            
            if(ctr === 0) {
                wm_img_paths.push(res.original);
            }
            wm_img_paths.push(res.watermarked);
            ctr++;
        });
        html += '</table>';
        resultsDiv.innerHTML = html;
        document.getElementById('txt_wc_img_paths').value = wm_img_paths.join('\n');
    })
    .catch((err) => {
        resultsDiv.innerHTML = `<p class="text-red-600">${data.error}</p>`;
    });
}

function extractProductImageLinks(htmlString, parentSelector) {
    // Parse HTML string into a DOM
    const parser = new DOMParser();
    const doc = parser.parseFromString(htmlString, 'text/html');

    // Find the parent container
    const parent = doc.querySelector(parentSelector);
    if (!parent) return []; // Return empty array if parent not found

    // Select all <a> elements inside the parent
    const aElements = parent.querySelectorAll('a');

    // Map to href values
    return Array.from(aElements).map(a => a.getAttribute('href'));
}

function parsePageSrc() {
    const srcHtml = sourceHTML.value;

    // parse product image links
    const links = extractProductImageLinks(srcHtml, '.cloud-zoom-thumb-container');
    let smallerImgLinks = links.map(link => link.replace('/public', '/w=600'));
    document.getElementById("ta_img_urls").value = smallerImgLinks.join('\n');
    
    // parse needed product details
    const parser = new DOMParser();
    const doc = parser.parseFromString(srcHtml, 'text/html');
    const skuMatch = srcHtml.match(/\bMK\d{5}\b/);
    const prodSku = skuMatch ? (skuMatch[0]).replace('MK', 'AK') : "";
    const prodTitle = doc.querySelector("#product-details-form > div > div.product-essential > div.overview > div.page-title > h1")?.innerHTML.trim() || "";
    // const alertDiv = doc.querySelector('.alert');
    const prodDesc = doc.querySelector('.alert')?.innerHTML.trim() || "";
    const prodShortDesc = `${prodDesc} / Product Number: ${prodSku}`;

    let specsHTML = '';
    // const specsContainer = doc.querySelector('.inside-product-specs2');
    // if (specsContainer) {
    //     const table = specsContainer.querySelector('.specsTable');
    //     if (table) specsHTML = table.outerHTML.trim();
    // }
    const table = doc.querySelector("#product-specification > div > div:nth-child(1) > div.product-specs-table.table-wrapper > table");
    if (table) specsHTML = table.outerHTML.trim()
        .replace('hidden-row', 'hidden')
        .replace('data-table', 'specsTable');

    // const fitmentsTable = doc.querySelector('.fitmentsTable');
    const fitmentsTable = doc.querySelector("#product-specification > div > div:nth-child(2) > div.brand-model-table.table-wrapper > table");
    const fitmentsHTML = fitmentsTable ? fitmentsTable.outerHTML.trim().replace('data-table', 'fitmentsTable') : '';

    document.getElementById("ta_description").value = tpDescription.replace('PROD_DESC', `<strong>${prodDesc} / Product Number: ${prodSku}</strong>`);
    document.getElementById("ta_specs").value = tpSpecs.replace('SPECS_TABLE', specsHTML);
    document.getElementById("ta_fitments").value = tpFitments.replace('FITMENTS_TABLE', fitmentsHTML);
    document.getElementById("ta_extra_details").value = tpXtraDetails;
    document.getElementById("ta_accessories").value = tpAccessories;

    document.getElementById("productSKU").value = prodSku;
    document.getElementById("productShortDesc").value = prodShortDesc;
    document.getElementById("productTitle").value = prodTitle;

    showMessage("Done parsing the HTML!");
}

processBtn.addEventListener('click', () => {
    parsePageSrc();
});

// pasteClipboardBtn.addEventListener('click', async () => {
//     try {
//         const text = await navigator.clipboard.readText();
//         sourceHTML.value = text;
//         showMessage("Pasted clipboard content into source!");
//     } catch (err) {
//         console.error("Failed to read clipboard:", err);
//     }
// });

copyOutputBtn.addEventListener('click', () => {
    const tpCombined = `${document.getElementById("ta_description").value}\n\n` + 
        `${document.getElementById("ta_specs").value}\n\n` + 
        `${document.getElementById("ta_fitments").value}\n\n` + 
        `${document.getElementById("ta_extra_details").value }\n\n` + 
        `${document.getElementById("ta_accessories").value}`;

    navigator.clipboard.writeText(tpCombined).then(() => {
        showMessage("Output HTML copied to clipboard!");
    });
});

// document.getElementById("fetchPageSourceForm").addEventListener("submit", function (e) {
//     e.preventDefault();
//     let url = document.getElementById("url").value.trim();
//     if (!url) {
//         alert("Please enter a valid URL.");
//         return;
//     }

//     document.getElementById('resultsContainerPageSrc').innerHTML = "⏳ Processing...";
//     fetch("get_page_source.php", {
//         method: "POST",
//         headers: { "Content-Type": "application/x-www-form-urlencoded" },
//         body: "url=" + encodeURIComponent(url)
//     })
//         .then(res => res.json())
//         .then(data => {
//             document.getElementById("sourceCode").value = data.source || "Failed to fetch source.";
//             document.getElementById("imageLinks").value = (data.images && data.images.length)
//                 ? data.images.join("\n")
//                 : "No images found.";

//             sourceHTML.value = data.source || "Failed to fetch source.";
//             parsePageSrc();
//         })
//         .catch(err => {
//             console.error(err);
//             alert("Error fetching data.");
//         }).finally(() => {
//             document.getElementById('resultsContainerPageSrc').innerHTML = "";
//         });
// });

// document.getElementById('watermarkForm').addEventListener('submit', async function (e) {
//     e.preventDefault();
//     const formData = new FormData(this);
//     const resultsDiv = document.getElementById('resultsContainer');
//     resultsDiv.innerHTML = "⏳ Processing...";
//     try {
//         const response = await fetch('process_imgs.php', { method: 'POST', body: formData });
//         const data = await response.json();
//         if (data.error) {
//             resultsDiv.innerHTML = `<p class="text-red-600">${data.error}</p>`;
//             return;
//         }
//         let html = `<table class="border border-gray-300 mt-4 w-full">
//             <tr class="bg-gray-100"><th class="p-2 border">Image URL</th><th class="p-2 border">Status</th><th class="p-2 border">Original</th><th class="p-2 border">Watermarked</th></tr>`;
//         data.results.forEach(res => {
//             html += `<tr>
//                 <td class="p-2 border">${res.url}</td>
//                 <td class="p-2 border">${res.status}</td>
//                 <td class="p-2 border">${res.original ? `<a href="output/${encodeURIComponent(res.original)}" target="_blank" class="text-indigo-600 underline">View</a>` : ''}</td>
//                 <td class="p-2 border">${res.watermarked ? `<a href="output/${encodeURIComponent(res.watermarked)}" target="_blank" class="text-indigo-600 underline">View</a>` : ''}</td>
//             </tr>`;
//         });
//         html += '</table>';
//         resultsDiv.innerHTML = html;
//     } catch (err) {
//         resultsDiv.innerHTML = `<p class="text-red-600">❌ Error: ${err.message}</p>`;
//     }
// });

// document.getElementById('btnCopyPageSource').addEventListener('click', () => {
//     copyInputValueById('sourceCode');
// });
document.getElementById('btn_productTitle').addEventListener('click', () => {
    copyInputValueById('productTitle');
});
document.getElementById('btn_productShortDesc').addEventListener('click', () => {
    copyInputValueById('productShortDesc');
});
document.getElementById("btn_dlimg").addEventListener("click", function (e) {
    e.preventDefault();
    const raw = document.getElementById('ta_img_urls').value;
    const links = raw
        .split(/\r?\n/)
        .map(l => l.trim())
        .filter(l => l.length > 0);
    const resultsDiv = document.getElementById('resultsContainer');

    resultsDiv.innerHTML = "⏳ Processing...";
    sendMultipleImagesToPhp(
        links,
        'process_imgs.php'
    );
});
// document.getElementById('btn_preview_tp').addEventListener('click', () => {
//     const prevHtml = `
//     ${document.getElementById("ta_description").value}

//     ${document.getElementById("ta_specs").value}

//     ${document.getElementById("ta_fitments").value}

//     ${document.getElementById("ta_extra_details").value }

//     ${document.getElementById("ta_accessories").value}
//     `;

//     templatePreview.innerHTML = prevHtml;    
// });

document.getElementById("btn_wp_upload").addEventListener("click", function (e) {
    const formData = new FormData();
    const wp_upload_progress_result = document.getElementById("wp_upload_progress_result");
    const btn_wp_upload = document.getElementById("btn_wp_upload");

    const sel_wp_category = document.getElementById('sel_wp_category');
    const selectedCategories = Array.from(sel_wp_category.selectedOptions).map(opt => opt.value);
    const sel_wp_brand = document.getElementById('sel_wp_brand');
    const selectedBrands = Array.from(sel_wp_brand.selectedOptions).map(opt => opt.value);
    const sel_wp_manufacturer = document.getElementById('sel_wp_manufacturer');
    const selectedManufacturers = Array.from(sel_wp_manufacturer.selectedOptions).map(opt => opt.value);
    
    finalProductBody = `${document.getElementById("ta_description").value}\n\n` + 
        `${document.getElementById("ta_specs").value}\n\n` + 
        `${document.getElementById("ta_fitments").value}\n\n` + 
        `${document.getElementById("ta_extra_details").value }\n\n` + 
        `${document.getElementById("ta_accessories").value}`;

    if(wm_img_paths.length < 1 || selectedCategories.length < 1 || selectedBrands.length < 1 || selectedManufacturers.length < 1){
        alert('Unable to proceed, please check some missing details');
        return;
    }

    wm_img_paths.forEach(path => {
        formData.append('wm_img_paths[]', path);
    });
    selectedCategories.forEach(cat => {
        formData.append('category_ids[]', cat);
    });
    selectedBrands.forEach(brand => {
        formData.append('brand_ids[]', brand);
    });
    selectedManufacturers.forEach(manufacturer => {
        formData.append('manufacturer_ids[]', manufacturer);
    });
    formData.append('name', document.getElementById('productTitle').value.trim());
    formData.append('sku', document.getElementById('productSKU').value.trim());
    formData.append('short_description', document.getElementById('productShortDesc').value.trim());
    formData.append('description', finalProductBody.trim());

    wp_upload_progress_result.innerHTML = "Uploading, please wait..."
    btn_wp_upload.setAttribute('disabled', true);
    fetch('./test/wc_create_product_test.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log(data);
        if(data.success) {
            wp_upload_progress_result.innerHTML = `<span>${data.message}: (${data.data.product_id})</span> <a href='${data.data.product_permalink}' target='_blank' class='text-indigo-400 hover:text-indigo-800'>View Product</a>`;
        } else {
            wp_upload_progress_result.innerHTML = `<span class='text-red-500'>${data.message}</span>`;
        }
    })
    .catch(error => { 
        console.error('Error:', error);
        wp_upload_progress_result.innerHTML = `<span class='text-red-500'>${error}</span>`;
    })
    .finally(() => btn_wp_upload.removeAttribute('disabled'));
});
