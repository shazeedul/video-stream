import { fileTypeFromBlob } from 'file-type';

/**
 * Validates if a file matches the accepted file types
 * @param file - The file to validate
 * @param accept - Comma-separated list of accepted MIME types or extensions (e.g., "image/*,.pdf,image/png")
 * @returns true if valid, false otherwise
 */
export async function validateFileType(file: File, accept?: string): Promise<boolean> {
    if (!accept) return true;

    const acceptedTypes = accept.split(',').map((type) => type.trim());

    // Get actual file type from magic bytes
    const detectedType = await fileTypeFromBlob(file);
    const fileExtension = file.name.split('.').pop()?.toLowerCase();
    const browserMimeType = file.type;

    return acceptedTypes.some((type) => {
        // Handle file extensions (e.g., .ico, .jpg, .pdf)
        if (type.startsWith('.')) {
            return `.${fileExtension}` === type;
        }

        // Handle wildcard MIME types (e.g., image/*, application/*)
        if (type.includes('/*')) {
            const baseType = type.split('/')[0];
            // Check detected type first, then browser type
            if (detectedType?.mime.startsWith(baseType + '/')) return true;
            if (browserMimeType.startsWith(baseType + '/')) return true;
            return false;
        }

        // Handle specific MIME types (e.g., image/x-icon, application/pdf)
        if (type.includes('/')) {
            // Check if detected type matches (most reliable)
            if (detectedType?.mime === type) return true;
            // Check if browser MIME type matches
            if (browserMimeType === type) return true;
            // Fallback: check file extension using the library's detected extension
            if (detectedType?.ext && getExtensionsForMimeType(type).includes(detectedType.ext)) {
                return true;
            }
            // Last resort: check filename extension
            if (fileExtension && getExtensionsForMimeType(type).includes(fileExtension)) {
                return true;
            }
        }

        return false;
    });
}

/**
 * Maps common MIME types to their file extensions
 * This is used as a fallback when the file-type library can't detect the type
 */
function getExtensionsForMimeType(mimeType: string): string[] {
    const mimeToExtensions: Record<string, string[]> = {
        // Images
        'image/x-icon': ['ico'],
        'image/vnd.microsoft.icon': ['ico'],
        'image/svg+xml': ['svg'],
        'image/png': ['png'],
        'image/jpeg': ['jpg', 'jpeg'],
        'image/gif': ['gif'],
        'image/webp': ['webp'],
        'image/bmp': ['bmp'],
        'image/tiff': ['tiff', 'tif'],
        'image/avif': ['avif'],

        // Documents
        'application/pdf': ['pdf'],
        'application/msword': ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['docx'],
        'application/vnd.ms-excel': ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['xlsx'],
        'application/vnd.ms-powerpoint': ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['pptx'],

        // Archives
        'application/zip': ['zip'],
        'application/x-rar-compressed': ['rar'],
        'application/x-7z-compressed': ['7z'],
        'application/gzip': ['gz'],
        'application/x-tar': ['tar'],

        // Text
        'text/plain': ['txt'],
        'text/csv': ['csv'],
        'text/html': ['html', 'htm'],
        'text/css': ['css'],
        'text/javascript': ['js'],
        'application/json': ['json'],
        'application/xml': ['xml'],
        'text/xml': ['xml'],

        // Audio
        'audio/mpeg': ['mp3'],
        'audio/wav': ['wav'],
        'audio/ogg': ['ogg'],
        'audio/webm': ['webm'],

        // Video
        'video/mp4': ['mp4'],
        'video/mpeg': ['mpeg', 'mpg'],
        'video/webm': ['webm'],
        'video/ogg': ['ogv'],
        'video/quicktime': ['mov'],
    };

    return mimeToExtensions[mimeType] || [];
}

/**
 * Formats file size for display
 * @param sizeInKB - File size in kilobytes
 * @returns Formatted string (e.g., "2.5MB" or "512KB")
 */
export function formatFileSize(sizeInKB: number): string {
    if (sizeInKB >= 1024) {
        return `${(sizeInKB / 1024).toFixed(2)}MB`;
    }
    return `${sizeInKB}KB`;
}

/**
 * Validates file size
 * @param file - The file to validate
 * @param maxSizeInKB - Maximum size in kilobytes (undefined means unlimited)
 * @returns true if valid, false otherwise
 */
export function validateFileSize(file: File, maxSizeInKB?: number): boolean {
    if (maxSizeInKB === undefined) return true;

    const fileSizeInKB = file.size / 1024;
    return fileSizeInKB <= maxSizeInKB;
}
