/**
 * Composable for client-side image compression before upload.
 * Automatically compresses images larger than threshold to reduce upload time and storage.
 */

/**
 * Default compression options
 */
const defaultOptions = {
    maxWidth: 2048,
    maxHeight: 2048,
    quality: 0.8,
    maxSizeKB: 1024, // 1MB threshold for compression
    outputFormat: 'image/jpeg', // fallback format
};

/**
 * Get dimensions of an image file
 * @param {File} file - The image file
 * @returns {Promise<{width: number, height: number}>}
 */
export const getImageDimensions = (file) => {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(img.src);
            resolve({ width: img.width, height: img.height });
        };
        img.onerror = () => {
            URL.revokeObjectURL(img.src);
            reject(new Error('Failed to load image'));
        };
        img.src = URL.createObjectURL(file);
    });
};

/**
 * Check if the file should be compressed
 * @param {File} file - The file to check
 * @param {Object} options - Compression options
 * @returns {boolean}
 */
export const shouldCompress = (file, options = {}) => {
    const opts = { ...defaultOptions, ...options };

    // Only compress raster images
    const compressibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!compressibleTypes.includes(file.type)) {
        return false;
    }

    // Check file size
    const fileSizeKB = file.size / 1024;
    return fileSizeKB > opts.maxSizeKB;
};

/**
 * Compress an image file
 * @param {File} file - The image file to compress
 * @param {Object} options - Compression options
 * @returns {Promise<{file: File, compressed: boolean, originalSize: number, compressedSize: number}>}
 */
export const compressImage = async (file, options = {}) => {
    const opts = { ...defaultOptions, ...options };

    // Check if compression is needed
    if (!shouldCompress(file, opts)) {
        return {
            file,
            compressed: false,
            originalSize: file.size,
            compressedSize: file.size,
        };
    }

    return new Promise((resolve, reject) => {
        const img = new Image();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        img.onload = () => {
            URL.revokeObjectURL(img.src);

            // Calculate new dimensions while maintaining aspect ratio
            let { width, height } = img;

            if (width > opts.maxWidth) {
                height = Math.round((height * opts.maxWidth) / width);
                width = opts.maxWidth;
            }

            if (height > opts.maxHeight) {
                width = Math.round((width * opts.maxHeight) / height);
                height = opts.maxHeight;
            }

            // Set canvas dimensions
            canvas.width = width;
            canvas.height = height;

            // Draw image on canvas
            ctx.drawImage(img, 0, 0, width, height);

            // Determine output format
            let outputFormat = file.type;
            if (outputFormat === 'image/png') {
                // Keep PNG for images that might have transparency
                outputFormat = 'image/png';
            } else {
                // Use JPEG for everything else (better compression)
                outputFormat = 'image/jpeg';
            }

            // Convert canvas to blob
            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        reject(new Error('Failed to compress image'));
                        return;
                    }

                    // If compressed is larger than original, use original
                    if (blob.size >= file.size) {
                        resolve({
                            file,
                            compressed: false,
                            originalSize: file.size,
                            compressedSize: file.size,
                        });
                        return;
                    }

                    // Create new file with original name
                    const extension = outputFormat === 'image/jpeg' ? '.jpg' : '.png';
                    const baseName = file.name.replace(/\.[^/.]+$/, '');
                    const newFileName = baseName + extension;

                    const compressedFile = new File([blob], newFileName, {
                        type: outputFormat,
                        lastModified: Date.now(),
                    });

                    resolve({
                        file: compressedFile,
                        compressed: true,
                        originalSize: file.size,
                        compressedSize: compressedFile.size,
                    });
                },
                outputFormat,
                opts.quality
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(img.src);
            reject(new Error('Failed to load image for compression'));
        };

        img.src = URL.createObjectURL(file);
    });
};

/**
 * Compress multiple files
 * @param {FileList|File[]} files - The files to compress
 * @param {Object} options - Compression options
 * @param {Function} onProgress - Progress callback (index, total, result)
 * @returns {Promise<{files: File[], stats: {totalOriginal: number, totalCompressed: number, compressedCount: number}}>}
 */
export const compressImages = async (files, options = {}, onProgress = null) => {
    const results = [];
    let totalOriginal = 0;
    let totalCompressed = 0;
    let compressedCount = 0;

    const fileArray = Array.from(files);

    for (let i = 0; i < fileArray.length; i++) {
        const file = fileArray[i];

        try {
            const result = await compressImage(file, options);
            results.push(result.file);
            totalOriginal += result.originalSize;
            totalCompressed += result.compressedSize;
            if (result.compressed) compressedCount++;

            if (onProgress) {
                onProgress(i + 1, fileArray.length, result);
            }
        } catch (error) {
            // If compression fails, use original file
            console.warn(`Failed to compress ${file.name}:`, error);
            results.push(file);
            totalOriginal += file.size;
            totalCompressed += file.size;
        }
    }

    return {
        files: results,
        stats: {
            totalOriginal,
            totalCompressed,
            compressedCount,
        },
    };
};

/**
 * Format file size for display
 * @param {number} bytes - Size in bytes
 * @returns {string}
 */
export const formatFileSize = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return bytes + ' B';
};

export default {
    compressImage,
    compressImages,
    shouldCompress,
    getImageDimensions,
    formatFileSize,
};
