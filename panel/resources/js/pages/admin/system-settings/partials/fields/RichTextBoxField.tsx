import JoditEditor from 'jodit-react';
import { useMemo, useRef } from 'react';

interface RichTextBoxFieldProps {
    value: string;
    onChange: (value: string) => void;
    rules?: Record<string, string | number | boolean>;
    disabled?: boolean;
}

export function RichTextBoxField({ value, onChange, rules, disabled }: RichTextBoxFieldProps) {
    const height = Number(rules?.height) || 300;
    const editor = useRef(null);

    const config = useMemo(
        () => ({
            readonly: disabled,
            height: height,
            toolbar: true,
            spellcheck: true,
            language: 'en',
            toolbarButtonSize: 'middle' as const,
            toolbarAdaptive: false,
            showCharsCounter: false,
            showWordsCounter: false,
            showXPathInStatusbar: false,
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            defaultActionOnPaste: 'insert_clear_html' as const,
            enter: 'p' as const,
            buttons: [
                'bold',
                'italic',
                'underline',
                'strikethrough',
                '|',
                'ul',
                'ol',
                '|',
                'outdent',
                'indent',
                '|',
                'font',
                'fontsize',
                'brush',
                'paragraph',
                '|',
                'image',
                'video',
                'table',
                'link',
                '|',
                'align',
                'undo',
                'redo',
                '|',
                'hr',
                'eraser',
                'copyformat',
                '|',
                'symbol',
                'fullsize',
                'source',
            ],
            uploader: {
                insertImageAsBase64URI: true,
            },
            controls: {
                paragraph: {
                    list: {
                        blockquote: 'Quote',
                        pre: 'Code',
                    },
                },
            },
            style: {
                background: 'transparent',
            },
            editorCssClass: 'jodit-wysiwyg-custom',
        }),
        [disabled, height],
    );

    return (
        <div className="overflow-hidden rounded-md border bg-background">
            <JoditEditor ref={editor} value={value} config={config} onBlur={(newContent) => onChange(newContent)} />
        </div>
    );
}
