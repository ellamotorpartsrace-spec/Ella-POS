/**
 * assets/js/pos/receipt-preview.js
 * Reliable Window-Based Receipt Preview & Print
 * Supports: Thermal 80mm & A4
 */

window.ReceiptPreview = {
    openWindow(data, format = "thermal80", watermark = null) {
        const S = window.STORE_SETTINGS || {};
        const P = window.USER_PREFERENCES || {};

        const printerMode = P.printer_mode_override || S.printer_mode;
        const printerConn = P.printer_connection_override || S.printer_connection || 'network';
        const printerAddr = P.printer_address_override || S.printer_address || '';

        // --- DIRECT ESC/POS PRINTING INTERCEPT ---
        if (printerMode === 'direct') {
            const cmds = this.generateEscPos(data, format);
            if (cmds.length === 0) return; // e.g. A4 format fallback

            fetch('../../api/pos/print_direct.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    commands: cmds,
                    printer_connection: printerConn,
                    printer_address: printerAddr
                })
            })
                .then(res => res.json())
                .then(resData => {
                    if (!resData.success) {
                        EllaToast.error('Direct Print Failed: ' + (resData.error || 'Unknown error'));
                        // Fallback to browser print if direct fails
                        this._openBrowserPrint(data, format, watermark);
                    }
                })
                .catch(err => {
                    console.error("Print Error:", err);
                    EllaToast.error('Network error trying to reach print service.');
                    this._openBrowserPrint(data, format, watermark);
                });

            return; // Stop here, do not open browser popup
        }

        // --- RAWBT INTENT INTERCEPT (ANDROID BLUETOOTH) ---
        if (printerMode === 'rawbt') {
            const cmds = this.generateEscPos(data, format);
            if (cmds.length === 0) return;

            let rawString = "";
            cmds.forEach(cmd => {
                if (cmd.type === 'text') {
                    if (cmd.size === 'tall') {
                        // GS ! 16 (Double height)
                        rawString += "\x1d\x21\x10";
                    }
                    if (cmd.bold) {
                        // ESC E 1
                        rawString += "\x1b\x45\x01";
                    }

                    rawString += cmd.text + "\n";

                    if (cmd.size === 'tall') {
                        // GS ! 0 (Normal)
                        rawString += "\x1d\x21\x00";
                    }
                    if (cmd.bold) {
                        // ESC E 0
                        rawString += "\x1b\x45\x00";
                    }
                } else if (cmd.type === 'align') {
                    if (cmd.align === 'center') rawString += "\x1b\x61\x01"; // ESC a 1
                    else if (cmd.align === 'right') rawString += "\x1b\x61\x02"; // ESC a 2
                    else rawString += "\x1b\x61\x00"; // ESC a 0 (Left)
                } else if (cmd.type === 'feed') {
                    for (let i = 0; i < (cmd.lines || 1); i++) rawString += "\n";
                } else if (cmd.type === 'cut') {
                    rawString += "\x1d\x56\x42\x00"; // GS V 66 0 (Cut)
                } else if (cmd.type === 'drawer') {
                    rawString += "\x1b\x70\x00\x19\xfa"; // ESC p 0 25 250 (Kick Drawer)
                }
            });

            // Convert raw binary string to base64
            // Since JS strings are UTF-16, we need to convert to UTF-8 array then to btoa safely
            const utf8Bytes = new TextEncoder().encode(rawString);
            let binaryString = "";
            for (let i = 0; i < utf8Bytes.length; i++) {
                binaryString += String.fromCharCode(utf8Bytes[i]);
            }
            const b64 = btoa(binaryString);

            // Construct RawBT Intent URI
            const intentUri = "intent:base64," + b64 + "#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;";

            // Notify user
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Printing via Bluetooth',
                    text: 'Opening RawBT App...',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                console.log("Printing via RawBT Intent...");
            }

            // Trigger the intent via an invisible anchor (more reliable on modern mobile browsers)
            setTimeout(() => {
                const a = document.createElement('a');
                a.href = intentUri;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();

                // Cleanup
                setTimeout(() => document.body.removeChild(a), 500);
            }, 300);

            return;
        }

        // --- BROWSER PRINTING (Original) ---
        this._openBrowserPrint(data, format, watermark);
    },

    _openBrowserPrint(data, format = "thermal80", watermark = null) {
        const receiptHTML = this.generateHTML(data, format, watermark);

        const win = window.open("", "_blank", "width=420,height=750");

        if (!win) {
            EllaToast.error('Popup blocked! Please allow popups for this site.');
            return;
        }

        win.document.write(`
            <html>
            <head>
                <title></title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
                <style>
                    body {
                        margin: 0;
                        padding: 10px;
                        background: #f4f4f4;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    /* Container used for the capture to keep background clean */
                    #receipt-capture-area {
                        background: #fff;
                        display: inline-block;
                        width: 100%;
                    }
                    /* Floating button for download */
                    .no-print-btn {
                        position: fixed;
                        top: 10px;
                        right: 10px;
                        background: #0d6efd;
                        color: #fff;
                        border: none;
                        padding: 8px 12px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                        z-index: 9999;
                    }
                    .no-print-btn:hover { background: #0b5ed7; }
                    .no-print-btn i { margin-right: 5px; }

                    @media print {
                        body { background: #fff; padding: 0; margin: 0; }
                        #receipt-capture-area { background: transparent; display: block; }
                        .no-print-btn { display: none !important; }
                        @page { margin: 0; size: ${format === 'a4' ? 'A4' : '80mm auto'}; }
                    }
                </style>
            </head>
            <body>
                <button class="no-print-btn" onclick="downloadReceiptImage()">
                    Download Image
                </button>
                <div id="receipt-capture-area">
                    ${receiptHTML}
                </div>
                <script>
                    window.receiptFormat = '${format}';

                    window.onload = function () {
                        window.focus();
                        
                        // If auto-print is desired, we could call window.print() here, 
                        // but usually users want to preview and choose to print or download.
                    };

                    function downloadReceiptImage() {
                        const dlBtn = document.querySelector('.no-print-btn');
                        dlBtn.disabled = true;
                        dlBtn.textContent = 'Preparing...';

                        // ── A4: one image per page chunk ──────────────────────────────
                        if (window.receiptFormat === 'a4') {
                            const chunks = Array.from(document.querySelectorAll('.a4-page-chunk'));
                            if (chunks.length === 0) {
                                dlBtn.disabled = false;
                                dlBtn.textContent = 'Download Image';
                                return;
                            }

                            const timestamp = Date.now();
                            const total = chunks.length;

                            // Capture each chunk sequentially then trigger downloads
                            // with a small stagger so the browser does not drop them.
                            const captureAll = async () => {
                                const blobs = [];
                                for (let i = 0; i < total; i++) {
                                    dlBtn.textContent = 'Capturing page ' + (i + 1) + ' of ' + total + '...';
                                    const chunk = chunks[i];

                                    // Temporarily set explicit A4 width on the chunk
                                    const origWidth = chunk.style.width;
                                    chunk.style.width = '794px';

                                    try {
                                        const canvas = await html2canvas(chunk, {
                                            scale: 2,
                                            useCORS: true,
                                            backgroundColor: '#fff',
                                            logging: false,
                                            width: 794,
                                        });
                                        blobs.push(canvas.toDataURL('image/png'));
                                    } catch (err) {
                                        console.error('Capture error on page ' + (i + 1), err);
                                        blobs.push(null);
                                    } finally {
                                        chunk.style.width = origWidth;
                                    }
                                }

                                // Stagger downloads 300 ms apart so browser won't block them
                                for (let i = 0; i < blobs.length; i++) {
                                    if (!blobs[i]) continue;
                                    await new Promise(resolve => setTimeout(resolve, i === 0 ? 0 : 300));
                                    const link = document.createElement('a');
                                    link.download = 'receipt_page' + (i + 1) + '_' + timestamp + '.png';
                                    link.href = blobs[i];
                                    link.click();
                                }

                                dlBtn.disabled = false;
                                dlBtn.textContent = 'Download Image';
                            };

                            captureAll().catch(err => {
                                console.error('Download failed:', err);
                                alert('Failed to generate image download.');
                                dlBtn.disabled = false;
                                dlBtn.textContent = 'Download Image';
                            });

                        // ── Thermal: single image (unchanged behaviour) ────────────────
                        } else {
                            const captureArea = document.getElementById('receipt-capture-area');
                            html2canvas(captureArea, {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#fff',
                                logging: false
                            }).then(canvas => {
                                const link = document.createElement('a');
                                link.download = 'receipt_' + Date.now() + '.png';
                                link.href = canvas.toDataURL('image/png');
                                link.click();
                                dlBtn.disabled = false;
                                dlBtn.textContent = 'Download Image';
                            }).catch(err => {
                                console.error('Error capturing receipt:', err);
                                alert('Failed to generate image download.');
                                dlBtn.disabled = false;
                                dlBtn.textContent = 'Download Image';
                            });
                        }
                    }
                </script>
            </body>
            </html>
        `);

        win.document.close();
    },

    // Alias for backward compatibility
    showModal(data, format = "thermal80", watermark = null) {
        return this.openWindow(data, format, watermark);
    },

    numberToWords(amount) {
        if (amount === 0) return 'ZERO PESOS ONLY';

        const a = [
            '', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'
        ];
        const b = [
            '', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'
        ];

        const numToWords = (num) => {
            if (num === 0) return '';
            if (num < 20) return a[num] + ' ';
            if (num < 100) return b[Math.floor(num / 10)] + (num % 10 !== 0 ? '-' + a[num % 10] : '') + ' ';
            if (num < 1000) return a[Math.floor(num / 100)] + ' HUNDRED ' + numToWords(num % 100);
            if (num < 1000000) return numToWords(Math.floor(num / 1000)) + 'THOUSAND ' + numToWords(num % 1000);
            if (num < 1000000000) return numToWords(Math.floor(num / 1000000)) + 'MILLION ' + numToWords(num % 1000000);
            return numToWords(Math.floor(num / 1000000000)) + 'BILLION ' + numToWords(num % 1000000000);
        };

        const wholeValue = Math.floor(amount);
        const decimalValue = Math.round((amount - wholeValue) * 100);

        let words = numToWords(wholeValue).trim();
        words += words.length > 0 ? ' PESOS' : '';

        if (decimalValue > 0) {
            words += (words.length > 0 ? ' AND ' : '') + decimalValue + '/100';
        } else {
            words += ' ONLY';
        }

        return words;
    },

    /* =========================
         FORMAT SELECTOR
      ========================= */

    generateHTML(data, format, watermark = null) {
        switch (format) {
            case "a4":
                return this._generateA4(data, watermark);
            case "thermal80x3276":
                return this._generateThermal80x3276(data, watermark);
            case "thermal80":
            default:
                return this._generateThermal80(data, watermark);
        }
    },

    /* =========================
         ESC/POS DIRECT PRINTING
       ========================= */

    generateEscPos(data, format = "thermal80") {
        if (format === 'a4') {
            EllaToast.warning('A4 direct ESC/POS printing is not supported. Please use Browser print mode.');
            return [];
        }
        return this._generateEscPos(data);
    },

    _generateEscPos(data) {
        const { cart = [], buyer = {}, payment = {}, user = "" } = data;

        const subtotal = cart.reduce((s, i) => s + i.qty * (i.original_price || i.price), 0);
        const itemDiscountTotal = cart.reduce((s, i) => s + (i.item_discount || 0) * i.qty, 0);
        const cartTotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
        const globalDiscount = data.globalDiscount || 0;
        const grandTotal = cartTotal - globalDiscount;
        const totalDiscount = itemDiscountTotal + globalDiscount;
        const itemCount = cart.reduce((s, i) => s + i.qty, 0);

        const S = window.STORE_SETTINGS || {};
        const _show = (key) => S[key] !== '0';

        const cmds = [];

        // Center Alignment for Header
        cmds.push({ type: 'align', align: 'center' });

        if (_show('receipt_show_store_name')) {
            cmds.push({ type: 'text', text: S.store_name || 'ELLA MOTOR PARTS', bold: true, size: 'tall' });
        }
        if (_show('receipt_show_address')) {
            cmds.push({ type: 'text', text: S.store_address || '#79 Don Jose Canciller Ave. Cauayan City, Isabela' });
        }
        if (_show('receipt_show_facebook') && S.store_facebook) {
            cmds.push({ type: 'text', text: 'Follow Us On Facebook: ' + S.store_facebook });
        }
        if (_show('receipt_show_contact') && S.store_contact) {
            cmds.push({ type: 'text', text: 'Contact No: ' + S.store_contact });
        }
        if (_show('receipt_show_tax_id') && S.store_tax_id) {
            cmds.push({ type: 'text', text: 'Non-VAT Reg: ' + S.store_tax_id });
        }
        if (S.receipt_header_text) {
            cmds.push({ type: 'feed', lines: 1 });
            cmds.push({ type: 'text', text: S.receipt_header_text });
        }

        cmds.push({ type: 'text', text: '------------------------------------------------' });

        // Left Alignment for Meta Info
        cmds.push({ type: 'align', align: 'left' });
        cmds.push({ type: 'text', text: 'Ref: ' + (payment.reference || "N/A") });
        const receiptDate = data.date ? (data.date.includes(' ') ? data.date : new Date(data.date).toLocaleString()) : new Date().toLocaleString();
        cmds.push({ type: 'text', text: 'Date: ' + receiptDate });

        if (_show('receipt_show_customer')) {
            cmds.push({ type: 'text', text: 'Customer: ' + (buyer.name || "Walk-in") });
        }
        if (_show('receipt_show_cashier')) {
            cmds.push({ type: 'text', text: 'Cashier: ' + (window.CURRENT_USER_NAME || "Staff") });
        }
        cmds.push({ type: 'text', text: 'Items: ' + itemCount });

        cmds.push({ type: 'text', text: '------------------------------------------------' });

        // Items
        cart.forEach((i, idx) => {
            const showDisc = _show('receipt_show_item_discount');
            const hasDiscount = showDisc && i.item_discount && i.item_discount > 0;
            const lineTotal = (i.qty * i.price).toFixed(2);
            const rowNum = `#${idx + 1}`;

            cmds.push({ type: 'text', text: `${rowNum}. ${i.name}`, bold: true });

            const variantText = `${i.brand || ""} ${i.variation || ""} (${i.unit_type || "pc"})`.trim();
            if (variantText) cmds.push({ type: 'text', text: '    ' + variantText });

            const priceLine = `    ${i.qty} x \x50${i.price.toFixed(2)}`;
            const spaces = Math.max(1, 48 - priceLine.length - lineTotal.length - 1);
            cmds.push({ type: 'text', text: priceLine + ' '.repeat(spaces) + '\x50' + lineTotal });

            if (hasDiscount) {
                const discLabel = `    Disc: -\x50${(i.item_discount * i.qty).toFixed(2)}`;
                cmds.push({ type: 'text', text: discLabel });
            }

            if (i.returned_qty > 0) {
                const retLabel = `    Returned: ${i.returned_qty}`;
                cmds.push({ type: 'text', text: retLabel });
            }
        });

        cmds.push({ type: 'text', text: '------------------------------------------------' });

        // Totals
        const formatTotalLine = (label, amount) => {
            const amtStr = '\x50' + amount;
            const spaces = Math.max(1, 48 - label.length - amtStr.length);
            return label + ' '.repeat(spaces) + amtStr;
        };

        if (totalDiscount > 0) {
            cmds.push({ type: 'text', text: formatTotalLine('Subtotal:', subtotal.toFixed(2)) });
            if (itemDiscountTotal > 0) cmds.push({ type: 'text', text: formatTotalLine('Item Discounts:', '-' + itemDiscountTotal.toFixed(2)) });
            if (globalDiscount > 0) cmds.push({ type: 'text', text: formatTotalLine('Transaction Disc:', '-' + globalDiscount.toFixed(2)) });
        }

        cmds.push({ type: 'text', text: formatTotalLine('TOTAL:', grandTotal.toFixed(2)), bold: true, size: 'tall' });

        if (_show('receipt_show_payment_method')) {
            if (['mix', 'financing', 'home_credit'].includes(payment.method) && payment.mix_details && payment.mix_details.length > 0) {
                const methodLabelTop = (payment.method === 'home_credit' ? 'FINANCING' : payment.method.toUpperCase());
                cmds.push({ type: 'text', text: 'Method: ' + methodLabelTop });
                payment.mix_details.forEach(p => {
                    let methodLabel = p.method === 'bank_transfer' ? 'BANK' : p.method.toUpperCase();
                    if (p.ref && p.ref.startsWith('DP-')) {
                        methodLabel = `  DOWNPAYMENT (${methodLabel})`;
                    } else if (p.method === 'financing') {
                        methodLabel = `  FINANCED AMOUNT`;
                    } else {
                        methodLabel = `  ${methodLabel}`;
                    }
                    cmds.push({ type: 'text', text: `${methodLabel}: \x50${p.amount.toFixed(2)}` });
                });
            } else {
                cmds.push({ type: 'text', text: 'Method: ' + (payment.method || "cash").toUpperCase() });
            }
        }

        cmds.push({ type: 'text', text: '------------------------------------------------' });

        // Footer
        cmds.push({ type: 'align', align: 'center' });
        if (S.receipt_footer_note) {
            cmds.push({ type: 'text', text: S.receipt_footer_note });
            cmds.push({ type: 'feed', lines: 1 });
        }
        cmds.push({ type: 'text', text: S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!' });

        cmds.push({ type: 'feed', lines: 4 });
        cmds.push({ type: 'cut' });
        cmds.push({ type: 'drawer' });

        return cmds;
    },

    /* =========================
         THERMAL 80MM RECEIPT
      ========================= */

    _generateThermal80(data, watermark = null) {
        const { cart = [], buyer = {}, payment = {}, user = "" } = data;

        const subtotal = cart.reduce((s, i) => s + i.qty * (i.original_price || i.price), 0);
        const itemDiscountTotal = cart.reduce((s, i) => s + (i.item_discount || 0) * i.qty, 0);
        const cartTotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
        const globalDiscount = data.globalDiscount || 0;
        const grandTotal = cartTotal - globalDiscount;
        const totalDiscount = itemDiscountTotal + globalDiscount;
        const itemCount = cart.reduce((s, i) => s + i.qty, 0);

        const watermarkHTML = watermark
            ? `
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-35deg);
                font-size: 48px;
                font-weight: bold;
                color: rgba(0, 0, 0, 0.1);
                pointer-events: none;
                white-space: nowrap;
                z-index: 1000;
                letter-spacing: 8px;
            ">${watermark}</div>
        `
            : "";

        const S = window.STORE_SETTINGS || {};
        const _show = (key) => S[key] !== '0';

        return `
        <style>
            @media print {
                @page { margin: 0; size: 80mm auto; }
                body { margin: 0; padding: 0; }
            }
        </style>
        <div style="
            font-family: Courier New, monospace;
            font-size: 12px;
            max-width: 300px;
            width: 100%;
            margin: auto;
            position: relative;
            color: #000;
        ">
            ${watermarkHTML}
            <div style="text-align:center;">
                ${_show('receipt_show_store_name') ? `<strong style="font-size:16px;">${S.store_name || 'ELLA MOTOR PARTS'}</strong><br>` : ''}
                ${_show('receipt_show_address') ? `${S.store_address || '#79 Don Jose Canciller Ave. Cauayan City, Isabela'}<br>` : ''}
                ${_show('receipt_show_facebook') && S.store_facebook ? 'Follow Us On Facebook: ' + S.store_facebook + '<br>' : ''}
                ${_show('receipt_show_contact') && S.store_contact ? 'Contact No: ' + S.store_contact + '<br>' : ''}
                ${_show('receipt_show_tax_id') && S.store_tax_id ? 'Non-VAT Registered: ' + S.store_tax_id : ''}
                ${S.receipt_header_text ? `<br><span style="font-size:10px;">${S.receipt_header_text}</span>` : ''}
            </div>

            <hr>

            <div>Ref: <strong>${payment.reference || "N/A"}</strong></div>
            <div>Date: ${data.date ? (data.date.includes(' ') ? data.date.replace(/-/g, '/').replace(' ', ' @ ') : new Date(data.date).toLocaleString()) : new Date().toLocaleString()}</div>
            ${_show('receipt_show_customer') ? `<div>Customer: ${buyer.name || "Walk-in"}</div>` : ''}
            ${_show('receipt_show_cashier') ? `<div>Cashier: ${window.CURRENT_USER_NAME || "Staff"}</div>` : ''}
            <div>Items: ${itemCount}</div>

            <hr>

            ${cart
                .map(
                    (i, idx) => {
                        const showDisc = _show('receipt_show_item_discount');
                        const hasDiscount = showDisc && i.item_discount && i.item_discount > 0;
                        const origPrice = i.original_price || i.price;
                        const returnedQty = parseInt(i.returned_qty || 0);
                        const isFullyReturned = returnedQty >= i.qty;
                        return `
                    <div style="margin-bottom:6px; ${isFullyReturned ? 'text-decoration:line-through; opacity:0.6;' : ''}">
                        <span style="font-size:11px; color:#111; font-family:monospace; font-weight:700;">#${idx + 1}</span>
                        <strong>${i.name}</strong><br>
                        <span style="font-size:11px; padding-left:18px; display:inline-block;">
                            ${i.brand || ""} ${i.variation || ""}
                            (${i.unit_type || "pc"})
                        </span><br>
                        <span style="padding-left:14px; display:inline-block;">${i.qty} x ${hasDiscount ? `<span style="text-decoration:line-through;">₱${origPrice.toFixed(2)}</span> ` : ''}₱${i.price.toFixed(2)}</span>
                        <span style="float:right;">
                            ₱${(i.qty * i.price).toFixed(2)}
                        </span>
                        ${hasDiscount ? `<br><span style="font-size:10px; padding-left:14px;">Disc: -₱${(i.item_discount * i.qty).toFixed(2)}</span>` : ''}
                        ${returnedQty > 0 ? `<br><span style="font-size:10px; padding-left:14px; color:#888;">(Returned: ${returnedQty})</span>` : ''}
                    </div>
                `},
                )
                .join("")}

            <hr>

            ${totalDiscount > 0 ? `
                <div>
                    Subtotal:
                    <span style="float:right;">₱${subtotal.toFixed(2)}</span>
                </div>
                ${itemDiscountTotal > 0 ? `
                <div>
                    Item Discounts:
                    <span style="float:right;">-₱${itemDiscountTotal.toFixed(2)}</span>
                </div>
                ` : ''}
                ${globalDiscount > 0 ? `
                <div>
                    Transaction Disc:
                    <span style="float:right;">-₱${globalDiscount.toFixed(2)}</span>
                </div>
                ` : ''}
            ` : ''}
            <div style="font-weight:bold;">
                TOTAL:
                <span style="float:right;">₱${grandTotal.toFixed(2)}</span>
            </div>

            ${_show('receipt_show_payment_method') ? `
                <div style="font-weight:bold;">Method: ${(payment.method === 'home_credit' ? 'FINANCING' : (payment.method || "cash")).toUpperCase()} ${payment.financing_provider ? `(${payment.financing_provider})` : ''}</div>
                ${['mix', 'financing', 'home_credit'].includes(payment.method) && payment.mix_details && payment.mix_details.length > 0 ?
                    payment.mix_details.map(p => {
                        let methodLabel = p.method === 'bank_transfer' ? 'BANK' : p.method.toUpperCase();
                        if (p.ref && p.ref.startsWith('DP-')) {
                            methodLabel = `DOWNPAYMENT (${methodLabel})`;
                        } else if (p.method === 'financing') {
                            methodLabel = `FINANCED AMOUNT`;
                        }
                        return `<div style="padding-left:10px; font-size:11px;">${methodLabel}: ₱${p.amount.toFixed(2)}</div>`;
                    }).join('')
                    : ''}
                ${(payment.wallet_supplement > 0) ? `<div style="font-size:11px; color:#166534;">  Wallet Used: ₱${parseFloat(payment.wallet_supplement).toFixed(2)}</div>` : ''}
                ${(payment.paid_by_wallet > 0) ? `<div style="font-size:11px; color:#1d4ed8;">  Paid by Wallet: ₱${parseFloat(payment.paid_by_wallet).toFixed(2)}</div>` : ''}
                ${(payment.shortfall_deducted > 0) ? `<div style="font-size:11px; color:#92400e;">  Shortfall (Wallet): ₱${parseFloat(payment.shortfall_deducted).toFixed(2)}</div>` : ''}
                ${(payment.shortfall_as_credit > 0) ? `<div style="font-size:11px; color:#6366f1;">  Balance Due (Credit): ₱${parseFloat(payment.shortfall_as_credit).toFixed(2)}</div>` : ''}
                ${(payment.saved_to_wallet > 0) ? `<div style="font-size:11px; color:#0e7490;">  Change Saved to Wallet: +₱${parseFloat(payment.saved_to_wallet).toFixed(2)}</div>` : ''}
            ` : ''}

            <hr>

            <div style="text-align:center;font-size:10px;">
                ${S.receipt_footer_note ? `<div style="margin-bottom:4px;">${S.receipt_footer_note}</div>` : ''}
                ${S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!'}
            </div>
        </div>
        `;
    } /* REQUIRED COMMA */,

    /* =========================
         THERMAL 80x3276MM RECEIPT
         (80mm width, continuous roll)
       ========================= */

    _generateThermal80x3276(data, watermark = null) {
        const { cart = [], buyer = {}, payment = {}, user = "" } = data;

        const subtotal = cart.reduce((s, i) => s + i.qty * (i.original_price || i.price), 0);
        const itemDiscountTotal = cart.reduce((s, i) => s + (i.item_discount || 0) * i.qty, 0);
        const cartTotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
        const globalDiscount = data.globalDiscount || 0;
        const grandTotal = cartTotal - globalDiscount;
        const totalDiscount = itemDiscountTotal + globalDiscount;
        const itemCount = cart.reduce((s, i) => s + i.qty, 0);

        const watermarkHTML = watermark
            ? `
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-35deg);
                font-size: 48px;
                font-weight: bold;
                color: rgba(0, 0, 0, 0.1);
                pointer-events: none;
                white-space: nowrap;
                z-index: 1000;
                letter-spacing: 8px;
            ">${watermark}</div>
        `
            : "";

        const S = window.STORE_SETTINGS || {};
        const _show = (key) => S[key] !== '0';

        return `
        <style>
            @media print {
                @page {
                    size: 80mm auto;
                    margin: 0;
                }
                body {
                    margin: 0;
                    padding: 0;
                }
            }
        </style>
        <div style="
            font-family: Courier New, monospace;
            font-size: 12px;
            max-width: 300px;
            width: 80mm;
            margin: auto;
            position: relative;
            color: #000;
        ">
            ${watermarkHTML}
            <div style="text-align:center;">
                ${_show('receipt_show_store_name') ? `<strong style="font-size:16px;">${S.store_name || 'ELLA MOTOR PARTS'}</strong><br>` : ''}
                ${_show('receipt_show_address') ? `${S.store_address || '#79 Don Jose Canciller Ave. Cauayan City, Isabela'}<br>` : ''}
                ${_show('receipt_show_facebook') && S.store_facebook ? 'Follow Us On Facebook: ' + S.store_facebook + '<br>' : ''}
                ${_show('receipt_show_contact') && S.store_contact ? 'Contact No: ' + S.store_contact + '<br>' : ''}
                ${_show('receipt_show_tax_id') && S.store_tax_id ? 'Non-VAT Registered: ' + S.store_tax_id : ''}
                ${S.receipt_header_text ? `<br><span style="font-size:10px;">${S.receipt_header_text}</span>` : ''}
            </div>

            <hr>

            <div>Ref: <strong>${payment.reference || "N/A"}</strong></div>
            <div>Date: ${data.date ? new Date(data.date).toLocaleString() : new Date().toLocaleString()}</div>
            ${_show('receipt_show_customer') ? `<div>Customer: ${buyer.name || "Walk-in"}</div>` : ''}
            ${_show('receipt_show_cashier') ? `<div>Cashier: ${window.CURRENT_USER_NAME || "Staff"}</div>` : ''}
            <div>Items: ${itemCount}</div>

            <hr>

            ${cart
                .map(
                    (i, idx) => {
                        const showDisc = _show('receipt_show_item_discount');
                        const hasDiscount = showDisc && i.item_discount && i.item_discount > 0;
                        const origPrice = i.original_price || i.price;
                        const returnedQty = parseInt(i.returned_qty || 0);
                        const isFullyReturned = returnedQty >= i.qty;
                        return `
                    <div style="margin-bottom:6px; ${isFullyReturned ? 'text-decoration:line-through; opacity:0.6;' : ''}">
                        <span style="font-size:9px; color:#888; font-family:monospace;">#${idx + 1}</span>
                        <strong>${i.name}</strong><br>
                        <span style="font-size:11px; padding-left:14px; display:inline-block;">
                            ${i.brand || ""} ${i.variation || ""}
                            (${i.unit_type || "pc"})
                        </span><br>
                        <span style="padding-left:14px; display:inline-block;">${i.qty} x ${hasDiscount ? `<span style="text-decoration:line-through;">₱${origPrice.toFixed(2)}</span> ` : ''}₱${i.price.toFixed(2)}</span>
                        <span style="float:right;">
                            ₱${(i.qty * i.price).toFixed(2)}
                        </span>
                        ${hasDiscount ? `<br><span style="font-size:10px; padding-left:14px;">Disc: -₱${(i.item_discount * i.qty).toFixed(2)}</span>` : ''}
                        ${returnedQty > 0 ? `<br><span style="font-size:10px; padding-left:14px; color:#888;">(Returned: ${returnedQty})</span>` : ''}
                    </div>
                `},
                )
                .join("")}

            <hr>

            ${totalDiscount > 0 ? `
                <div>
                    Subtotal:
                    <span style="float:right;">₱${subtotal.toFixed(2)}</span>
                </div>
                ${itemDiscountTotal > 0 ? `
                <div>
                    Item Discounts:
                    <span style="float:right;">-₱${itemDiscountTotal.toFixed(2)}</span>
                </div>
                ` : ''}
                ${globalDiscount > 0 ? `
                <div>
                    Transaction Disc:
                    <span style="float:right;">-₱${globalDiscount.toFixed(2)}</span>
                </div>
                ` : ''}
            ` : ''}
            <div style="font-weight:bold;">
                TOTAL:
                <span style="float:right;">₱${grandTotal.toFixed(2)}</span>
            </div>

            ${_show('receipt_show_payment_method') ? `
                <div style="font-weight:bold;">Method: ${(payment.method === 'home_credit' ? 'FINANCING' : (payment.method || "cash")).toUpperCase()} ${payment.financing_provider ? `(${payment.financing_provider})` : ''}</div>
                ${['mix', 'financing', 'home_credit'].includes(payment.method) && payment.mix_details && payment.mix_details.length > 0 ?
                    payment.mix_details.map(p => {
                        let methodLabel = p.method === 'bank_transfer' ? 'BANK' : p.method.toUpperCase();
                        if (p.ref && p.ref.startsWith('DP-')) {
                            methodLabel = `DOWNPAYMENT (${methodLabel})`;
                        } else if (p.method === 'financing') {
                            methodLabel = `FINANCED AMOUNT`;
                        }
                        return `<div style="padding-left:10px; font-size:11px;">${methodLabel}: ₱${p.amount.toFixed(2)}</div>`;
                    }).join('')
                    : ''}
                ${(payment.wallet_supplement > 0) ? `<div style="font-size:11px; color:#166534;">  Wallet Used: ₱${parseFloat(payment.wallet_supplement).toFixed(2)}</div>` : ''}
                ${(payment.paid_by_wallet > 0) ? `<div style="font-size:11px; color:#1d4ed8;">  Paid by Wallet: ₱${parseFloat(payment.paid_by_wallet).toFixed(2)}</div>` : ''}
                ${(payment.shortfall_deducted > 0) ? `<div style="font-size:11px; color:#92400e;">  Shortfall (Wallet): ₱${parseFloat(payment.shortfall_deducted).toFixed(2)}</div>` : ''}
                ${(payment.shortfall_as_credit > 0) ? `<div style="font-size:11px; color:#6366f1;">  Balance Due (Credit): ₱${parseFloat(payment.shortfall_as_credit).toFixed(2)}</div>` : ''}
                ${(payment.saved_to_wallet > 0) ? `<div style="font-size:11px; color:#0e7490;">  Change Saved to Wallet: +₱${parseFloat(payment.saved_to_wallet).toFixed(2)}</div>` : ''}
            ` : ''}

            <hr>

            <div style="text-align:center;font-size:10px;">
                ${S.receipt_footer_note ? `<div style="margin-bottom:4px;">${S.receipt_footer_note}</div>` : ''}
                ${S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!'}
            </div>

            <!-- Auto-cut feed space (~1 inches) -->
            <div style="height: 20mm;"></div>

            <!-- End marker -->
            <div style="text-align: center; font-size: 10px; font-weight: bold; border-top: 2px dashed #000; padding-top: 5px;">
                --- END OF RECEIPT ---
            </div>
        </div>
        `;
    } /* REQUIRED COMMA */,

    /* =========================
         A4 INVOICE
       ========================= */

    _generateA4(data, watermark = null) {
        const { cart = [], buyer = {}, payment = {}, user = "" } = data;

        const subtotal = cart.reduce((s, i) => s + i.qty * (i.original_price || i.price), 0);
        const itemDiscountTotal = cart.reduce((s, i) => s + (i.item_discount || 0) * i.qty, 0);
        const cartTotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
        const globalDiscount = data.globalDiscount || 0;
        const grandTotal = cartTotal - globalDiscount;
        const totalDiscount = itemDiscountTotal + globalDiscount;
        const itemCount = cart.reduce((s, i) => s + i.qty, 0);
        const ref = payment.reference || "INV-" + Date.now().toString().slice(-6);

        const watermarkHTML = watermark
            ? `
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-35deg);
                font-size: 80px;
                font-weight: bold;
                color: ${watermark === "VOIDED" ? "rgba(220, 53, 69, 0.15)" : "rgba(100, 100, 100, 0.12)"};
                pointer-events: none;
                white-space: nowrap;
                z-index: 1000;
                letter-spacing: 12px;
            ">${watermark}</div>
        `
            : "";

        const S = window.STORE_SETTINGS || {};
        const _show = (key) => S[key] !== '0';
        const showDiscA4 = _show('receipt_show_item_discount');
        const fmt = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Build totals HTML 
        let totalsHTML = '';
        if (totalDiscount > 0) {
            totalsHTML += `
                <div style="display:flex; justify-content:space-between; padding:1px 0; font-size:10px;">
                    <span>Subtotal</span>
                    <span>₱${fmt(subtotal)}</span>
                </div>`;

            if (itemDiscountTotal > 0) {
                totalsHTML += `
                <div style="display:flex; justify-content:space-between; padding:1px 0; font-size:10px; color:#dc2626;">
                    <span>Item Discounts</span>
                    <span>-₱${fmt(itemDiscountTotal)}</span>
                </div>`;
            }

            if (globalDiscount > 0) {
                totalsHTML += `
                <div style="display:flex; justify-content:space-between; padding:1px 0; font-size:10px; color:#dc2626;">
                    <span>Transaction Discount</span>
                    <span>-₱${fmt(globalDiscount)}</span>
                </div>`;
            }
        }
        totalsHTML += `
            <div style="
                display:flex;
                justify-content:space-between;
                padding-top:4px;
                margin-top:2px;
                border-top:1px solid #111;
                font-size:14px;
                font-weight:700;
            ">
                <span>TOTAL</span>
                <span>₱${fmt(grandTotal)}</span>
            </div>
            <div style="font-size:9px; text-align:right; font-style:italic; font-weight:600; color:#444; margin-top:3px;">
                *** ${this.numberToWords(grandTotal)} ***
            </div>`;
        if (totalDiscount > 0) {
            totalsHTML += `
            <div style="font-size:9px; color:#dc2626; text-align:right; margin-top:1px;">
                Saved ₱${fmt(totalDiscount)}
            </div>`;
        }
        if (_show('receipt_show_payment_method')) {
            if (['mix', 'financing', 'home_credit'].includes(payment.method) && payment.mix_details && payment.mix_details.length > 0) {
                const methodLabelTop = (payment.method === 'home_credit' ? 'FINANCING' : payment.method.toUpperCase());
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#555; text-align:right;">
                        Method: ${methodLabelTop} ${payment.financing_provider ? `(${payment.financing_provider})` : ''}
                    </div>`;
                payment.mix_details.forEach(p => {
                    let methodLabel = p.method === 'bank_transfer' ? 'BANK' : p.method.toUpperCase();
                    if (p.ref && p.ref.startsWith('DP-')) {
                        methodLabel = `DOWNPAYMENT (${methodLabel})`;
                    } else if (p.method === 'financing') {
                        methodLabel = `FINANCED AMOUNT`;
                    }
                    totalsHTML += `
                        <div style="font-size:10px; color:#666; text-align:right;">
                            ${methodLabel}: ₱${fmt(p.amount)}
                        </div>`;
                });

                if (['financing', 'home_credit'].includes(payment.method)) {
                    let dpAmount = 0;
                    payment.mix_details.forEach(p => {
                        if (p.ref && p.ref.startsWith('DP-')) {
                            dpAmount += p.amount;
                        }
                    });
                    if (dpAmount > 0) {
                        totalsHTML += `
                            <div style="margin-top:4px; font-size:10px; color:#111; text-align:right; font-style:italic;">
                                * Note: Downpayment is ₱${fmt(dpAmount)}
                            </div>`;
                    }
                }
            } else {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#555; text-align:right;">
                        Method: ${(payment.method || "cash").toUpperCase()}
                    </div>`;
            }

            // Wallet activity lines
            if ((payment.wallet_supplement || 0) > 0) {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#166534; text-align:right;">
                        Wallet Used: -\u20b1${fmt(payment.wallet_supplement)}
                    </div>`;
            }
            if ((payment.paid_by_wallet || 0) > 0) {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#1d4ed8; text-align:right;">
                        Paid by Wallet: -\u20b1${fmt(payment.paid_by_wallet)}
                    </div>`;
            }
            if ((payment.shortfall_deducted || 0) > 0) {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#92400e; text-align:right;">
                        Shortfall (Wallet): -\u20b1${fmt(payment.shortfall_deducted)}
                    </div>`;
            }
            if ((payment.shortfall_as_credit || 0) > 0) {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#6366f1; text-align:right;">
                        Balance Due (Credit): \u20b1${fmt(payment.shortfall_as_credit)}
                    </div>`;
            }
            if ((payment.saved_to_wallet || 0) > 0) {
                totalsHTML += `
                    <div style="margin-top:2px; font-size:10px; color:#0e7490; text-align:right;">
                        Change Saved to Wallet: +\u20b1${fmt(payment.saved_to_wallet)}
                    </div>`;
            }
        }

        const ITEMS_PER_PAGE = 30;
        const totalPages = Math.ceil(cart.length / ITEMS_PER_PAGE) || 1;
        let allPagesHTML = '';

        for (let p = 0; p < totalPages; p++) {
            const isFirstPage = (p === 0);
            const isLastPage = (p === totalPages - 1);
            const chunk = cart.slice(p * ITEMS_PER_PAGE, (p + 1) * ITEMS_PER_PAGE);

            const itemRowsHTML = chunk.map((i, idxInChunk) => {
                const idx = p * ITEMS_PER_PAGE + idxInChunk;
                const hasDiscount = showDiscA4 && i.item_discount && i.item_discount > 0;
                const origPrice = i.original_price || i.price;
                const hasUnit = i.unit_id && i.multiplier > 1;
                const pricePerPc = hasUnit ? (i.price / i.multiplier) : 0;
                const unitBreakdownA4 = hasUnit
                    ? `<div style="font-size:12px; color:#555; margin-top:1px; font-style:italic;">
                           &#8369;${fmt(pricePerPc)}/pc &times; ${i.multiplier} pcs = &#8369;${fmt(i.price)}</div>`
                    : '';
                const skuSpan = i.sku ? `<span style="font-family: monospace; font-size:12px; color: #555; background:#f3f4f6; padding:0 3px; border-radius:3px; margin-right:4px;">SKU: ${i.sku}</span>` : '';

                const netQty = i.qty - i.returned_qty;
                const isFullyReturned = i.returned_qty >= i.qty;

                return `
                    <tr style="border-bottom:1px solid #eee; ${isFullyReturned ? 'text-decoration:line-through; opacity:0.5; background:#f9f9f9;' : ''}">
                        <td style="padding:0px 4px; text-align:center; font-size:11px; vertical-align:middle;">
                            <div style="font-size:12px; color:#111; font-family:monospace; line-height:1; font-style: italic;">#${idx + 1}</div>
                        </td>
                        <td style="padding:0px 4px; text-align:center; font-size:13px; vertical-align:middle;">
                            <div style="font-weight:600;">${i.qty}</div>
                        </td>
                        <td style="padding:2px 4px;">
                            <div style="font-weight:600; font-size:13px; line-height:1.2;">${i.name}</div>
                            ${i.sku ? `<div style="font-size:10px; font-family:monospace; color:#6b7280; background:#f3f4f6; display:inline-block; padding:1px 5px; border-radius:3px; margin-top:1px; letter-spacing:0.3px;">SKU: ${i.sku}</div>` : ''}
                            <div style="font-size:12px; color:#374151; line-height:1.2; margin-top:1px;">
                                ${i.brand || ""} ${i.variation || ""} (${i.unit_type || "pc"})
                                ${hasDiscount ? `<span style="color:#dc2626; margin-left:5px;">(Disc: -₱${fmt(i.item_discount)})</span>` : ''}
                                ${i.returned_qty > 0 ? `<span style="color:#881337; font-weight:bold; margin-left:5px;">(Returned: ${i.returned_qty})</span>` : ''}
                            </div>
                            ${unitBreakdownA4}
                        </td>
                        <td style="padding:0px 4px; text-align:right; font-size:13px;">
                            ${hasDiscount ? `<span style="text-decoration:line-through;color:#999;font-size:12px;">₱${fmt(origPrice)}</span> ` : ''}₱${fmt(i.price)}
                        </td>
                        <td style="padding:0px 4px; text-align:right; font-weight:600; font-size:13px;">
                            ₱${fmt(i.qty * i.price)}
                        </td>
                    </tr>`;
            }).join("");

            let headerHTML = `<thead>`;
            headerHTML += `
                    <tr>
                        <th colspan="5" style="padding:0; text-align:left; font-weight:normal;">
                            <div style="
                                display:flex;
                                justify-content:space-between;
                                align-items:flex-start;
                                border-bottom:1px solid #111;
                                padding: 5px 0 10px 0;
                            ">
                                <div>
                                    ${_show('receipt_show_store_name') ? `<div style="font-size:17px; font-weight:700;">${S.store_name || 'ELLA MOTOR PARTS'}</div>` : ''}
                                    <div style="font-size:11px; color:#555; margin-top:2px; line-height:1.2;">
                                        ${_show('receipt_show_address') ? `${S.store_address || '#79 Don Jose Canciller Ave. Cauayan City, Isabela'}<br>` : ''}
                                        ${_show('receipt_show_facebook') && S.store_facebook ? 'Follow Us On Facebook: ' + S.store_facebook + '<br>' : ''}
                                        ${_show('receipt_show_contact') && S.store_contact ? 'Contact No: ' + S.store_contact : ''}${_show('receipt_show_tax_id') && S.store_tax_id ? ' &bull; Non-VAT Reg: ' + S.store_tax_id : ''}
                                        ${S.receipt_header_text ? `<br><span style="font-size:10px;">${S.receipt_header_text}</span>` : ''}
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size: 17px; font-weight: 700; color: #9ca3af;">INVOICE</div>
                                    <div style="font-size: 11px; color: #555;">Ref #: ${ref} &bull; ${data.date ? new Date(data.date).toLocaleString() : new Date().toLocaleString()}</div>
                                    <div style="margin-top:2px; font-size:11px; font-weight:600; color:#555;">PAGE: ${p + 1} of ${totalPages}</div>
                                    <div style="margin-top:5px; font-size:11px; color:#333; text-align:right;">
                                        ${_show('receipt_show_customer') ? `<div>BILL TO: <strong>${buyer.name || "Walk-in Customer"}</strong></div>` : ''}
                                        ${_show('receipt_show_cashier') ? `<div>CASHIER: <strong>${window.CURRENT_USER_NAME || "Staff"}</strong></div>` : ''}
                                    </div>
                                </div>
                            </div>
                        </th>
                    </tr>`;

            headerHTML += `
                    <tr style="background:#111; color:#fff;">
                        <th style="padding:4px; text-align:center; font-size:11px; width:30px;">#</th>
                        <th style="padding:4px; text-align:center; font-size:13px; width:35px;">QTY</th>
                        <th style="padding:4px; text-align:left; font-size:13px;">DESCRIPTION</th>
                        <th style="padding:4px; text-align:right; font-size:13px; width:80px;">UNIT PRICE</th>
                        <th style="padding:4px; text-align:right; font-size:13px; width:80px;">AMOUNT</th>
                    </tr>
                </thead>`;

            let totalsAndSignatureHTML = '';
            if (isLastPage) {
                totalsAndSignatureHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:20px;">
                        <div style="display:flex; gap:24px;">
                            <div style="width:140px; text-align:center;">
                                <div style="border-top:1px solid #111; margin-bottom:2px; height:20px;"></div>
                                <div style="font-size:10px; font-weight:600;">Customer Signature</div>
                            </div>
                            <div style="width:140px; text-align:center;">
                                <div style="border-top:1px solid #111; margin-bottom:2px; height:20px;"></div>
                                <div style="font-size:10px; font-weight:600;">Checked By</div>
                            </div>
                        </div>
                        <div style="width:250px;">
                            ${totalsHTML}
                        </div>
                    </div>
                `;
            } else {
                totalsAndSignatureHTML = `
                    <div style="text-align:right; font-size:10px; font-style:italic; margin-top:20px; border-top:1px solid #eee; padding-top:10px;">
                        Continued on next page...
                    </div>
                `;
            }

            allPagesHTML += `
                <div class="a4-page-chunk" style="position: relative; box-sizing: border-box; width: 100%;">
                    ${watermarkHTML}
                    <div style="width:100%;">
                        <table style="width:100%; border-collapse:collapse;">
                            ${headerHTML}
                            <tbody>
                                ${isFirstPage ? `
                                <tr>
                                    <td colspan="5" style="padding:0; text-align:left;">
                                        <div style="display:flex; justify-content:flex-end; gap:16px; margin-top:5px; margin-bottom: 5px; font-size:12px; border-bottom:1px solid #eee; padding-bottom:5px;">
                                            <div><strong>LINES:</strong> ${cart.length}</div>
                                            <div><strong>ITEMS:</strong> ${itemCount}</div>
                                        </div>
                                    </td>
                                </tr>
                                ` : ''}
                                ${itemRowsHTML}
                            </tbody>
                        </table>
                        ${totalsAndSignatureHTML}
                    </div>
                </div>
            `;
        }

        return `
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 0.5in;
                }
                body, html { 
                    margin: 0; 
                    padding: 0; 
                    background: #fff;
                }
                .a4-page-chunk {
                    display: block;
                    page-break-after: always;
                    page-break-inside: avoid;
                    width: 100%;
                }
                /* Hide empty pages native to chrome bugs */
                .a4-page-chunk:last-child {
                    page-break-after: auto;
                }
                
                tr { page-break-inside: avoid; }
            }
            @media screen {
                body { background: #f4f4f4; padding: 20px; }
                .a4-page-chunk {
                    min-height: 297mm; /* Screen preview mapping */
                    width: 100%;
                    max-width: 210mm;
                    margin: 0 auto 30px auto;
                    background: #fff;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    padding: 40px;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-start;
                }
            }
        </style>

        <div class="a4-print-container" style="
            width: 100%;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
        ">
            ${allPagesHTML}
        </div>
        `;
    },
};
