/**
 * Keeps MathType / Wiris formulas as HTML when the Markdown data processor is active.
 * Other Markdown output is unchanged.
 */
import { Plugin } from '@ckeditor/ckeditor5-core';

export function preserveFormulaMarkup(html, parse) {
  const pattern = /<math\b[^>]*>[\s\S]*?<\/math>|<img\b[^>]*\bWirisformula\b[^>]*>/gi;
  const preserved = [];
  const tokenized = String(html).replace(pattern, (match) => {
    const token = `CKEFORMULA${preserved.length}CKEFORMULA`;
    preserved.push(match);
    return token;
  });
  let markdown = parse(tokenized);
  preserved.forEach((fragment, index) => {
    markdown = String(markdown).split(`CKEFORMULA${index}CKEFORMULA`).join(fragment);
  });
  return markdown;
}

export class PreserveMathInMarkdown extends Plugin {
  static get pluginName() {
    return 'PreserveMathInMarkdown';
  }

  afterInit() {
    const processor = this.editor.data.processor;
    if (
      !processor
      || typeof processor.toData !== 'function'
      || !processor._htmlDP
      || typeof processor._htmlDP.toData !== 'function'
      || !processor._html2markdown
      || typeof processor._html2markdown.parse !== 'function'
    ) {
      return;
    }

    const originalToData = processor.toData.bind(processor);
    processor.toData = (data) => {
      try {
        const html = processor._htmlDP.toData(data);
        return preserveFormulaMarkup(html, (tokenized) => processor._html2markdown.parse(tokenized));
      } catch (error) {
        return originalToData(data);
      }
    };
  }
}

export default PreserveMathInMarkdown;
