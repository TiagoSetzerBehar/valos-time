/**
 * VALO's Time: builds the report PDF in the browser of whoever clicks "Exportar como PDF".
 * The server only returns the same HTML it would send to the PDF renderer (Gotenberg), which the free
 * Render plan can not run. The HTML is drawn in a hidden frame, photographed and cut into A4 pages,
 * cutting between rows so no line is split in two.
 */

const PAGE_WIDTH_MM = 210;
const PAGE_HEIGHT_MM = 297;
const MARGIN_X_MM = 10;
const MARGIN_TOP_MM = 10;
const MARGIN_BOTTOM_MM = 16;
// Width the report is laid out at (about the printable width of an A4 page).
const FRAME_WIDTH_PX = 760;

function wait(ms: number) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitUntil(check: () => boolean, timeoutMs: number) {
    const limit = Date.now() + timeoutMs;
    while (!check() && Date.now() < limit) {
        await wait(100);
    }
}

let outfitHere: Promise<void> | null = null;

function loadOutfitHere(): Promise<void> {
    outfitHere ??= new FontFace('Outfit', `url(${window.location.origin}/valo-pdf/outfit.ttf)`, {
        weight: '100 900',
    })
        .load()
        .then((font) => {
            document.fonts.add(font);
        });
    return outfitHere;
}

/** Places (in CSS px from the top) where a page can end without cutting a row, title or chart. */
function safeBreaks(doc: Document): number[] {
    const scrollY = doc.defaultView?.scrollY ?? 0;
    const points: number[] = [];
    doc.body
        .querySelectorAll('tr, p, h1, h2, h3, table, canvas, svg, body > div, body > div > div')
        .forEach((el) => {
            const rect = el.getBoundingClientRect();
            points.push(rect.top + scrollY, rect.bottom + scrollY);
        });
    return points;
}

/**
 * Draws the HTML in a hidden frame `width` CSS px wide, waits for fonts and charts, and hands the
 * frame's document to `use`. The frame is removed afterwards.
 */
async function withRenderedFrame<T>(
    html: string,
    width: number,
    use: (doc: Document) => Promise<T>
): Promise<T> {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.tabIndex = -1;
    Object.assign(frame.style, {
        position: 'fixed',
        left: '-12000px',
        top: '0',
        width: `${width}px`,
        height: '1200px',
        border: '0',
        background: '#ffffff',
    });
    // Relative files of the report (the Outfit font) are served from /valo-pdf/.
    const base = `<base href="${window.location.origin}/valo-pdf/">`;
    frame.srcdoc = /<head[^>]*>/i.test(html)
        ? html.replace(/<head[^>]*>/i, (head) => head + base)
        : base + html;

    const loaded = new Promise<void>((resolve) =>
        frame.addEventListener('load', () => resolve(), { once: true })
    );
    document.body.appendChild(frame);
    try {
        await loaded;
        const win = frame.contentWindow as (Window & { status?: string }) | null;
        const doc = frame.contentDocument;
        if (!win || !doc) {
            throw new Error('PDF frame not available');
        }
        // The charts of the summary report say "ready" when they are drawn.
        await waitUntil(() => win.status !== 'processing', 15000);
        await doc.fonts.ready;
        // The picture is painted on this page, so the report font must be known here too, or the
        // letters are drawn with another font and words run together.
        await loadOutfitHere();
        doc.documentElement.style.background = '#ffffff';
        doc.body.style.margin = '0';
        // Shrink first so the frame is exactly as tall as the report (no empty last page).
        frame.style.height = '10px';
        frame.style.height = `${doc.documentElement.scrollHeight}px`;
        await wait(50);

        return await use(doc);
    } finally {
        frame.remove();
    }
}

async function loadLibraries() {
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import('html2canvas-pro'),
        import('jspdf'),
    ]);
    return { html2canvas, jsPDF };
}

/** A report of any length, cut into A4 pages between rows, with "Página X de Y" at the bottom. */
export async function downloadReportPdf(
    html: string,
    filename: string,
    pageLabel: (page: number, total: number) => string
): Promise<void> {
    const { html2canvas, jsPDF } = await loadLibraries();
    await withRenderedFrame(html, FRAME_WIDTH_PX, async (doc) => {
        const breaks = safeBreaks(doc);
        const canvas = await html2canvas(doc.body, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            windowWidth: FRAME_WIDTH_PX,
        });

        const contentWidthMm = PAGE_WIDTH_MM - 2 * MARGIN_X_MM;
        const canvasPxPerMm = canvas.width / contentWidthMm;
        const canvasPxPerCssPx = canvas.width / doc.body.scrollWidth;
        const pageHeightPx = (PAGE_HEIGHT_MM - MARGIN_TOP_MM - MARGIN_BOTTOM_MM) * canvasPxPerMm;
        const breakPx = breaks.map((point) => point * canvasPxPerCssPx);

        const slices: Array<[number, number]> = [];
        let top = 0;
        while (top < canvas.height - 1) {
            let bottom = Math.min(top + pageHeightPx, canvas.height);
            if (bottom < canvas.height) {
                const fitting = breakPx.filter(
                    (point) => point > top + pageHeightPx * 0.4 && point <= bottom
                );
                if (fitting.length > 0) {
                    bottom = Math.max(...fitting);
                }
            }
            slices.push([top, bottom]);
            top = bottom;
        }

        const pdf = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
        slices.forEach(([sliceTop, sliceBottom], index) => {
            if (index > 0) {
                pdf.addPage();
            }
            const height = Math.ceil(sliceBottom - sliceTop);
            const part = document.createElement('canvas');
            part.width = canvas.width;
            part.height = height;
            const context = part.getContext('2d');
            if (!context) {
                return;
            }
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, part.width, part.height);
            context.drawImage(
                canvas,
                0,
                sliceTop,
                canvas.width,
                height,
                0,
                0,
                canvas.width,
                height
            );
            pdf.addImage(
                part.toDataURL('image/jpeg', 0.92),
                'JPEG',
                MARGIN_X_MM,
                MARGIN_TOP_MM,
                contentWidthMm,
                height / canvasPxPerMm
            );
            pdf.setFontSize(8);
            pdf.setTextColor(113, 113, 122);
            pdf.text(pageLabel(index + 1, slices.length), MARGIN_X_MM, PAGE_HEIGHT_MM - 8);
        });
        pdf.save(filename);
    });
}

// The weekly summary is designed at exactly one A4 page: 794 x 1123 CSS px.
const A4_WIDTH_PX = 794;

/** A page designed at A4 size, saved as a single sharp PDF page (shrunk to fit if it is taller). */
export async function downloadOnePagePdf(html: string, filename: string): Promise<void> {
    const { html2canvas, jsPDF } = await loadLibraries();
    await withRenderedFrame(html, A4_WIDTH_PX, async (doc) => {
        const canvas = await html2canvas(doc.body, {
            scale: 3,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            windowWidth: A4_WIDTH_PX,
        });
        const pdf = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
        const widthMm = PAGE_WIDTH_MM;
        const heightMm = (canvas.height / canvas.width) * widthMm;
        const scale = Math.min(1, PAGE_HEIGHT_MM / heightMm);
        pdf.addImage(
            canvas.toDataURL('image/jpeg', 0.95),
            'JPEG',
            (PAGE_WIDTH_MM - widthMm * scale) / 2,
            0,
            widthMm * scale,
            heightMm * scale
        );
        pdf.save(filename);
    });
}

/** Saves the report HTML returned by the export endpoint as "informe-AAAA-MM-DD.pdf". */
export async function saveReportPdf(html: string): Promise<void> {
    const [{ trans }, { useNotificationsStore }] = await Promise.all([
        import('laravel-vue-i18n'),
        import('@/utils/notification'),
    ]);
    const today = new Date().toISOString().slice(0, 10);
    try {
        await downloadReportPdf(html, `informe-${today}.pdf`, (page, total) =>
            trans('Page :page of :total', { page: String(page), total: String(total) })
        );
    } catch (error) {
        console.error(error);
        useNotificationsStore().addNotification('error', trans('Export failed'));
    }
}
