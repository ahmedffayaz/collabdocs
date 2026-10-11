function fontStyle(style) {
    if (style.bold && style.italic) return 'bolditalic';
    if (style.bold) return 'bold';
    if (style.italic) return 'italic';
    return 'normal';
}

function readInlineRuns(node, inherited = {}) {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.nodeValue ? [{ text: node.nodeValue, ...inherited }] : [];
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return [];

    const tag = node.tagName.toLowerCase();
    if (tag === 'br') return [{ text: '\n', ...inherited }];
    if (['script', 'style'].includes(tag)) return [];

    const style = {
        bold: inherited.bold || ['strong', 'b'].includes(tag),
        italic: inherited.italic || ['em', 'i'].includes(tag),
        underline: inherited.underline || tag === 'u',
        strike: inherited.strike || ['s', 'del'].includes(tag),
        code: inherited.code || tag === 'code',
    };

    return [...node.childNodes].flatMap((child) => readInlineRuns(child, style));
}

function collectBlocks(root) {
    const blocks = [];

    function addBlock(type, nodes, extra = {}) {
        const runs = [...nodes].flatMap((node) => readInlineRuns(node));
        const text = runs.map((run) => run.text).join('').trim();
        if (text || ['h1', 'h2', 'h3', 'p', 'pre'].includes(type)) blocks.push({ type, runs, ...extra });
    }

    function visitList(list, depth = 0, quote = false) {
        const ordered = list.tagName.toLowerCase() === 'ol';
        let number = Number.parseInt(list.getAttribute('start') || '1', 10);
        for (const item of list.children) {
            if (item.tagName.toLowerCase() !== 'li') continue;
            const innerLists = [...item.children].filter((child) => ['ul', 'ol'].includes(child.tagName.toLowerCase()));
            const contentNodes = [...item.childNodes].filter((child) => !innerLists.includes(child));
            const runs = contentNodes.flatMap((node) => readInlineRuns(node));
            if (runs.map((run) => run.text).join('').trim()) {
                blocks.push({ type: 'list', runs, depth, quote, prefix: ordered ? `${number}. ` : '• ' });
            }
            number += 1;
            innerLists.forEach((nested) => visitList(nested, depth + 1, quote));
        }
    }

    function visit(container, inQuote = false) {
        for (const node of container.childNodes) {
            if (node.nodeType === Node.TEXT_NODE) {
                if (node.nodeValue.trim()) addBlock('p', [node], { quote: inQuote });
                continue;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) continue;

            const tag = node.tagName.toLowerCase();
            if (['h1', 'h2', 'h3', 'p', 'pre'].includes(tag)) addBlock(tag, node.childNodes, { quote: inQuote });
            else if (tag === 'ul' || tag === 'ol') visitList(node, 0, inQuote);
            else if (tag === 'blockquote') visit(node, true);
            else if (tag === 'hr') blocks.push({ type: 'rule', runs: [] });
            else if (['div', 'section', 'article'].includes(tag)) visit(node, inQuote);
            else addBlock('p', [node], { quote: inQuote });
        }
    }

    visit(root);
    return blocks;
}

function setFont(pdf, style, size) {
    pdf.setFont(style.code ? 'courier' : 'helvetica', fontStyle(style));
    pdf.setFontSize(size);
}

function appendRun(line, run) {
    if (!run.text) return;
    const last = line.at(-1);
    if (last && last.bold === run.bold && last.italic === run.italic && last.underline === run.underline && last.strike === run.strike && last.code === run.code) {
        last.text += run.text;
    } else {
        line.push({ ...run });
    }
}

function wrapRuns(pdf, runs, maxWidth, size) {
    const lines = [[]];
    let lineWidth = 0;

    for (const run of runs) {
        const parts = run.text.split(/(\s+|\n)/).filter(Boolean);
        for (const part of parts) {
            if (part === '\n') {
                lines.push([]);
                lineWidth = 0;
                continue;
            }
            setFont(pdf, run, size);
            let token = part;
            let tokenWidth = pdf.getTextWidth(token);
            if (/^\s+$/.test(token) && lineWidth === 0) continue;

            if (lineWidth && lineWidth + tokenWidth > maxWidth) {
                lines.push([]);
                lineWidth = 0;
                if (/^\s+$/.test(token)) continue;
            }

            if (tokenWidth > maxWidth) {
                let fragment = '';
                for (const character of token) {
                    const candidate = fragment + character;
                    if (fragment && pdf.getTextWidth(candidate) > maxWidth) {
                        appendRun(lines.at(-1), { ...run, text: fragment });
                        lines.push([]);
                        lineWidth = 0;
                        fragment = character;
                    } else fragment = candidate;
                }
                token = fragment;
                tokenWidth = pdf.getTextWidth(token);
            }

            appendRun(lines.at(-1), { ...run, text: token });
            lineWidth += tokenWidth;
        }
    }

    return lines;
}

function renderLine(pdf, runs, x, y, size, color) {
    let cursor = x;
    for (const run of runs) {
        setFont(pdf, run, size);
        pdf.setTextColor(...color);
        pdf.text(run.text, cursor, y);
        const width = pdf.getTextWidth(run.text);
        if (run.underline) pdf.setDrawColor(...color).setLineWidth(0.18).line(cursor, y + 0.65, cursor + width, y + 0.65);
        if (run.strike) pdf.setDrawColor(...color).setLineWidth(0.18).line(cursor, y - size * 0.12, cursor + width, y - size * 0.12);
        cursor += width;
    }
}

export async function downloadDocumentPdf(title, html) {
    const { jsPDF } = await import('jspdf');
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4', compress: true });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const marginX = 18;
    const marginTop = 19;
    const marginBottom = 18;
    const bodyWidth = pageWidth - marginX * 2;
    let y = marginTop;

    pdf.setProperties({ title });
    const pageBreak = () => {
        pdf.addPage();
        y = marginTop;
    };
    const ensureSpace = (height) => {
        if (y + height > pageHeight - marginBottom) pageBreak();
    };

    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(20);
    pdf.setTextColor(49, 47, 64);
    const titleLines = pdf.splitTextToSize(title || 'Untitled document', bodyWidth);
    for (const line of titleLines) {
        ensureSpace(9);
        pdf.text(line, marginX, y);
        y += 8.5;
    }
    y += 4;
    pdf.setDrawColor(225, 223, 232).setLineWidth(0.3).line(marginX, y, pageWidth - marginX, y);
    y += 8;

    const parsed = new DOMParser().parseFromString(html || '', 'text/html');
    const blocks = collectBlocks(parsed.body);
    for (const block of blocks) {
        if (block.type === 'rule') {
            ensureSpace(8);
            y += 3;
            pdf.setDrawColor(225, 223, 232).setLineWidth(0.25).line(marginX, y, pageWidth - marginX, y);
            y += 5;
            continue;
        }

        const heading = ['h1', 'h2', 'h3'].includes(block.type);
        const size = block.type === 'h1' ? 17 : block.type === 'h2' ? 14 : block.type === 'h3' ? 12 : block.type === 'pre' ? 9 : 10.5;
        const baseStyle = { bold: heading, italic: false, underline: false, code: block.type === 'pre' };
        const runs = block.runs.length ? block.runs : [{ text: ' ', ...baseStyle }];
        if (heading) runs.forEach((run) => { run.bold = true; });

        const indent = block.type === 'list' ? 5 + (block.depth || 0) * 5 : 0;
        const prefix = block.prefix || '';
        const prefixStyle = { bold: false, italic: false, underline: false, strike: false, code: false };
        const layoutRuns = prefix ? [{ text: prefix, ...prefixStyle }, ...runs] : runs;
        const lines = wrapRuns(pdf, layoutRuns, bodyWidth - indent, size);
        const lineHeight = size * 0.3528 * (heading ? 1.4 : 1.48);
        const before = block.type === 'p' ? 1 : heading ? 3 : 1;
        const after = block.type === 'p' ? 3.1 : heading ? 3.8 : 2.4;
        y += before;

        for (const line of lines) {
            ensureSpace(lineHeight);
            const quote = block.quote || block.type === 'blockquote';
            const x = marginX + indent + (quote ? 4 : 0);
            const color = quote ? [105, 101, 122] : [50, 48, 64];
            if (quote) pdf.setDrawColor(157, 145, 222).setLineWidth(0.7).line(marginX + indent, y - lineHeight + 1, marginX + indent, y + 1);
            renderLine(pdf, line, x, y, size, color);
            y += lineHeight;
        }
        y += after;
    }

    const filename = (title || 'document')
        .replace(/[<>:"/\\|?*\x00-\x1f]/g, '-')
        .trim()
        .replace(/\s+/g, '-')
        .slice(0, 100) || 'document';
    pdf.save(`${filename}.pdf`);
}
