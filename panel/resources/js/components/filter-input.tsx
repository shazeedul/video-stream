import { Search } from 'lucide-react';

interface FilterInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    className?: string;
}

export default function FilterInput({ className = '', type = 'text', ...props }: FilterInputProps) {
    return (
        <div className={`relative ${className}`}>
            <div className="relative flex items-center">
                <span className="absolute left-3 z-10 inline-block">
                    <Search className="text-gray-400 dark:text-gray-500" size={18} />
                </span>
                <input
                    type={type}
                    className="w-full border-0 border-b border-gray-200 bg-transparent py-2.5 pr-4 pl-10 text-sm text-gray-900 transition-colors duration-200 placeholder:text-gray-500 focus:border-gray-700 focus:ring-0 focus:outline-none dark:border-gray-700 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-gray-700"
                    {...props}
                />
            </div>
        </div>
    );
}
