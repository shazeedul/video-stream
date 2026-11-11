import Editor from '@monaco-editor/react';
import { useEffect, useState } from 'react';

interface CodeEditorFieldProps {
    value: string;
    onChange: (value: string) => void;
    rules?: Record<string, string | number | boolean>;
    disabled?: boolean;
}

export function CodeEditorField({ value, onChange, rules, disabled }: CodeEditorFieldProps) {
    const [editorValue, setEditorValue] = useState(value || '');

    useEffect(() => {
        setEditorValue(value || '');
    }, [value]);

    const theme = rules?.theme === 'light' ? 'light' : 'vs-dark';
    const language = String(rules?.language || 'html');
    const height = String(rules?.height || '300px');

    const handleEditorChange = (value: string | undefined) => {
        const newValue = value || '';
        setEditorValue(newValue);
        onChange(newValue);
    };

    return (
        <div className="overflow-hidden rounded-md border bg-background">
            <Editor
                height={height}
                language={language}
                value={editorValue}
                theme={theme}
                onChange={handleEditorChange}
                options={{
                    readOnly: disabled,
                    minimap: { enabled: false },
                    fontSize: 14,
                    lineNumbers: 'on',
                    scrollBeyondLastLine: false,
                    wordWrap: 'on',
                    automaticLayout: true,
                    padding: { top: 16, bottom: 16 },
                    scrollbar: {
                        verticalScrollbarSize: 10,
                        horizontalScrollbarSize: 10,
                    },
                }}
            />
        </div>
    );
}
