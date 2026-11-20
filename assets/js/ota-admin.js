/* Local-only, conservative extraction. Nothing is saved until the booking form is submitted. */
(function () {
    'use strict';
    function parse(text) {
        const labels = {
            customer_name: /^(?:nome ospite|ospite principale|guest name|nome del cliente)$/i,
            platform_booking_code: /^(?:numero di prenotazione|numero prenotazione|codice prenotazione|codice di conferma|confirmation code|reservation number|booking number)$/i,
            check_in: /^(?:check-in|check in|arrivo|arrival)$/i,
            check_out: /^(?:check-out|check out|partenza|departure)$/i,
            guests: /^(?:numero ospiti|numero di ospiti|number of guests)$/i,
            total_amount: /^(?:importo totale soggiorno|totale soggiorno|total accommodation price)$/i,
            dl_ota_rate_name: /^(?:piano tariffario|nome tariffa|rate plan)$/i,
            dl_ota_cancellation: /^(?:condizioni di cancellazione|cancellation policy)$/i
        };
        const values = {}, conflicts = new Set();
        const lines = String(text).slice(0, 50000).split(/\r?\n/).map(x => x.trim()).filter(Boolean);
        function date(raw) {
            let m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!m) { const d = raw.match(/^(\d{1,2})[/.](\d{1,2})[/.](\d{4})$/); if (d) m = [null, d[3], d[2].padStart(2, '0'), d[1].padStart(2, '0')]; }
            if (!m) return '';
            const result = `${m[1]}-${m[2]}-${m[3]}`;
            const stamp = new Date(result + 'T00:00:00Z');
            return !isNaN(stamp) && stamp.toISOString().slice(0, 10) === result ? result : '';
        }
        lines.forEach((line, i) => {
            const pair = line.match(/^(.+?)(?:\s*:\s*|\t+| {2,})(.+)$/);
            const label = pair ? pair[1].trim() : line.replace(/:$/, '').trim();
            let value = pair ? pair[2].trim() : (lines[i + 1] || '');
            for (const [key, re] of Object.entries(labels)) {
                if (!re.test(label) || !value) continue;
                if (key === 'check_in' || key === 'check_out') value = date(value);
                if (key === 'guests' && !/^[1-9]\d{0,2}$/.test(value)) value = '';
                if (key === 'platform_booking_code' && !/^[A-Za-z0-9.-]{3,64}$/.test(value)) value = '';
                if (key === 'total_amount') {
                    value = value.replace(/EUR|€/gi, '').trim();
                    if (/^\d{1,3}(\.\d{3})+,\d{2}$/.test(value)) value = value.replace(/\./g, '').replace(',', '.');
                    else if (/^\d+(,\d{2})$/.test(value)) value = value.replace(',', '.');
                    else if (!/^\d+(\.\d{2})?$/.test(value)) value = '';
                }
                if (value && values[key] && values[key] !== value) conflicts.add(key);
                if (value) values[key] = value;
            }
        });
        conflicts.forEach(key => delete values[key]);
        return values;
    }
    if (typeof module !== 'undefined') module.exports = {parse};
    if (typeof document === 'undefined') return;
    const byId = id => document.getElementById(id);
    const plans = byId('dl-plan-select');
    const catalog = window.domilocusOtaCatalog || {};
    const pdfButton = byId('dl-import-pdf-read');
    pdfButton?.addEventListener('click', async () => {
        const file = byId('dl-import-pdf')?.files?.[0];
        const message = byId('dl-import-pdf-status');
        if (!file) { message.textContent = 'Seleziona prima un PDF.'; return; }
        if (file.size > 10 * 1024 * 1024) { message.textContent = 'Il PDF supera il limite di 10 MB.'; return; }
        pdfButton.disabled = true;
        message.textContent = 'Lettura del PDF nel browser…';
        try {
            const {extractPdfText} = await import(window.domilocusOtaPdf.moduleUrl);
            const text = await extractPdfText(await file.arrayBuffer());
            byId('dl-import-text').value = text;
            byId('dl-import-preview').click();
            message.textContent = 'Testo estratto. Controlla il riepilogo e l’anteprima: il file non è stato caricato sul server. Non è stata salvata alcuna prenotazione.';
        } catch (error) {
            message.textContent = error.message || 'Impossibile leggere il PDF. Puoi incollare il riepilogo.';
        } finally { pdfButton.disabled = false; }
    });
    let available = [];
    function refresh() {
        if (!plans) return;
        available = catalog[byId('apartment_id')?.value] || [];
        plans.replaceChildren(new Option('Seleziona per precompilare…', ''));
        available.forEach((p, i) => plans.add(new Option(`${p.portal} — ${p.name}`, String(i))));
        const source = byId('booking_source')?.value;
        const ota = !!byId('booking_external_platform')?.value || ['ota','ical','ical_import','booking','airbnb','vrbo','expedia'].includes(source);
        if (byId('customer_email')) byId('customer_email').required = !ota;
    }
    ['apartment_id','booking_source','booking_external_platform'].forEach(id => byId(id)?.addEventListener('change', refresh));
    refresh();
    byId('dl-use-plan')?.addEventListener('click', () => {
        const p = available[plans.value];
        if (!p) return;
        for (const [key, value] of Object.entries({portal:p.portal, rate_name:p.name, cancellation:p.cancellation, rules:p.rules})) {
            byId('dl_ota_' + key).value = value || '';
        }
        const platform = String(p.portal).toLowerCase();
        const known = ['booking.com','airbnb','vrbo','expedia'].includes(platform);
        if (byId('booking_source')) byId('booking_source').value = platform === 'booking.com' ? 'booking' : (known ? platform : 'ota');
        if (byId('booking_external_platform')) byId('booking_external_platform').value = known ? platform : 'other';
        refresh();
    });
    byId('dl-import-preview')?.addEventListener('click', () => {
        const result = byId('dl-import-result');
        result.replaceChildren();
        const values = parse(byId('dl-import-text').value);
        const names = {customer_name:'Nome prenotante',platform_booking_code:'Codice OTA',check_in:'Arrivo',check_out:'Partenza',guests:'Numero ospiti',total_amount:'Corrispettivo soggiorno (EUR)',dl_ota_rate_name:'Piano tariffario',dl_ota_cancellation:'Cancellazione'};
        const note = document.createElement('p');
        note.textContent = Object.keys(values).length ? 'Seleziona i campi da applicare. Controlla eventuali condizioni su più righe: la proposta potrebbe essere parziale. Totali ambigui e date non riconosciute sono omessi.' : 'Nessun campo riconosciuto con certezza. Compila manualmente; non è stato modificato nulla.';
        result.append(note);
        const choices = [];
        for (const [key, value] of Object.entries(values)) {
            const target = byId(key);
            if (!target || target.disabled) continue;
            const row = document.createElement('p'), label = document.createElement('label'), check = document.createElement('input'), input = document.createElement('input');
            check.type = 'checkbox'; check.checked = !target.value;
            input.type = 'text'; input.value = value; input.className = 'widefat';
            label.append(check, document.createTextNode(names[key] + (target.value ? ' — sostituisce: ' + target.value : '')), input);
            row.append(label); result.append(row); choices.push({check, input, target});
        }
        if (choices.length) {
            const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'button'; apply.textContent = 'Applica i campi selezionati';
            apply.addEventListener('click', () => {
                choices.filter(c => c.check.checked).forEach(c => { c.target.value = c.input.value; c.target.dispatchEvent(new Event('change', {bubbles:true})); });
                note.textContent = 'Campi applicati alla bozza. Controlla e salva la prenotazione per confermare.';
            }); result.append(apply);
        }
    });
})();
