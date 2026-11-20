/** Read a local text PDF. No uploads, remote AI, PDF scripts or automatic form saves. */
export async function extractPdfText(bytes, { timeoutMs = 30000 } = {}) {
    const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    if (data.byteLength > 10 * 1024 * 1024) throw new Error('Il PDF supera il limite di 10 MB.');
    if (new TextDecoder('ascii').decode(data.slice(0, 5)) !== '%PDF-') throw new Error('Il file selezionato non è un PDF valido.');
    const pdfjs = await import('../vendor/pdfjs/pdf.mjs');
    pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.mjs', import.meta.url).href;
    const task = pdfjs.getDocument({data, isEvalSupported: false, useSystemFonts: true, useWasm: false, stopAtErrors: true});
    let timer;
    try {
        const extraction = (async () => {
            const pdf = await task.promise;
            if (pdf.numPages > 30) throw new Error('Il documento supera 30 pagine. Seleziona il solo riepilogo della prenotazione.');
            const pages = [];
            let count = 0;
            for (let n = 1; n <= pdf.numPages; n++) {
                const page = await pdf.getPage(n);
                const content = await page.getTextContent();
                let lines = '', previous = null;
                for (const item of content.items) {
                    if (typeof item.str !== 'string') continue;
                    if (previous && Math.abs(item.transform[5] - previous.transform[5]) > 3) lines += '\n';
                    else if (previous && !lines.endsWith('\n')) {
                        const gap = item.transform[4] - (previous.transform[4] + previous.width);
                        if (gap > 8) lines += '\t';
                        else if (gap > 1) lines += ' ';
                    }
                    lines += item.str;
                    if (item.hasEOL) lines += '\n';
                    previous = item;
                }
                count += lines.length;
                if (count > 50000) throw new Error('Il riepilogo contiene troppo testo. Seleziona soltanto le pagine della prenotazione.');
                pages.push(lines.trim());
                page.cleanup();
            }
            const text = pages.join('\n\n').trim();
            if (!text) throw new Error('Il PDF non contiene testo estraibile: potrebbe essere una scansione. Usa una stampa PDF con testo selezionabile oppure incolla il riepilogo.');
            return text;
        })();
        return await Promise.race([extraction, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Lettura del PDF troppo lenta. Prova un documento più piccolo.')), timeoutMs); })]);
    } catch (error) {
        if (error?.name === 'PasswordException') throw new Error('Il PDF è protetto da password. Salva una copia accessibile dal portale e riprova.');
        if (error?.name === 'InvalidPDFException') throw new Error('Il PDF è danneggiato o non leggibile. Prova a salvarlo nuovamente dal portale.');
        throw error;
    } finally {
        clearTimeout(timer);
        await task.destroy();
    }
}
