/**
 * Composable for client-side image compression before upload.
 * Automatically compresses images larger than threshold to reduce upload time and storage.
 * Uses iterative compression to ensure files fit within server limits.
 */

/**
 * Default compression options
 */
const defaultOptions = {
    maxWidth: 2048,
    maxHeight: 2048,
    quality: 0.85,
    minQuality: 0.3, // Minimum quality to try before giving up
    maxSizeKB: 1024, // 1MB threshold for triggering compression
    targetSizeKB: 10240, // 10MB - maximum file size allowed by server
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

    // Check file size - compress if larger than threshold OR larger than target
    const fileSizeKB = file.size / 1024;
    return fileSizeKB > opts.maxSizeKB || fileSizeKB > opts.targetSizeKB;
};

/**
 * Check if the file exceeds the target size limit
 * @param {File} file - The file to check
 * @param {Object} options - Compression options
 * @returns {boolean}
 */
export const exceedsTargetSize = (file, options = {}) => {
    const opts = { ...defaultOptions, ...options };
    const fileSizeKB = file.size / 1024;
    return fileSizeKB > opts.targetSizeKB;
};

/**
 * Compress an image with specific parameters
 * @param {HTMLImageElement} img - The loaded image element
 * @param {Object} params - Compression parameters {width, height, quality, format}
 * @param {string} originalName - Original file name
 * @returns {Promise<File>}
 */
const compressWithParams = (img, params, originalName) => {
    return new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        canvas.width = params.width;
        canvas.height = params.height;

        // Draw image on canvas
        ctx.drawImage(img, 0, 0, params.width, params.height);

        // Convert canvas to blob
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Failed to compress image'));
                    return;
                }

                // Create new file with original name
                const extension = params.format === 'image/jpeg' ? '.jpg' : '.png';
                const baseName = originalName.replace(/\.[^/.]+$/, '');
                const newFileName = baseName + extension;

                const compressedFile = new File([blob], newFileName, {
                    type: params.format,
                    lastModified: Date.now(),
                });

                resolve(compressedFile);
            },
            params.format,
            params.quality
        );
    });
};

/**
 * Compress an image file with iterative compression to fit within target size
 * @param {File} file - The image file to compress
 * @param {Object} options - Compression options
 * @returns {Promise<{file: File, compressed: boolean, originalSize: number, compressedSize: number, iterations: number, tooLarge: boolean}>}
 */
export const compressImage = async (file, options = {}) => {
    const opts = { ...defaultOptions, ...options };

    // Check if file type is compressible
    const compressibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!compressibleTypes.includes(file.type)) {
        // For non-compressible types, just check if it exceeds target size
        const fileSizeKB = file.size / 1024;
        return {
            file,
            compressed: false,
            originalSize: file.size,
            compressedSize: file.size,
            iterations: 0,
            tooLarge: fileSizeKB > opts.targetSizeKB,
        };
    }

    // Check if compression is needed
    const fileSizeKB = file.size / 1024;
    if (fileSizeKB <= opts.maxSizeKB && fileSizeKB <= opts.targetSizeKB) {
        return {
            file,
            compressed: false,
            originalSize: file.size,
            compressedSize: file.size,
            iterations: 0,
            tooLarge: false,
        };
    }

    return new Promise((resolve, reject) => {
        const img = new Image();

        img.onload = async () => {
            URL.revokeObjectURL(img.src);

            // Determine output format
            let outputFormat = file.type;
            if (outputFormat === 'image/png') {
                // Keep PNG for images that might have transparency
                outputFormat = 'image/png';
            } else {
                // Use JPEG for everything else (better compression)
                outputFormat = 'image/jpeg';
            }

            // Calculate initial dimensions while maintaining aspect ratio
            let currentWidth = img.width;
            let currentHeight = img.height;

            if (currentWidth > opts.maxWidth) {
                currentHeight = Math.round((currentHeight * opts.maxWidth) / currentWidth);
                currentWidth = opts.maxWidth;
            }

            if (currentHeight > opts.maxHeight) {
                currentWidth = Math.round((currentWidth * opts.maxHeight) / currentHeight);
                currentHeight = opts.maxHeight;
            }

            // Iterative compression
            let quality = opts.quality;
            let iterations = 0;
            let bestResult = null;
            const targetSizeBytes = opts.targetSizeKB * 1024;
            const maxIterations = 10;

            // Quality steps to try
            const qualitySteps = [0.85, 0.75, 0.65, 0.55, 0.45, 0.35, 0.30];
            // Size reduction factors to try after quality steps
            const sizeReductions = [1.0, 0.75, 0.5, 0.35];

            try {
                for (const sizeFactor of sizeReductions) {
                    const width = Math.round(currentWidth * sizeFactor);
                    const height = Math.round(currentHeight * sizeFactor);

                    for (const q of qualitySteps) {
                        if (q < opts.minQuality) continue;
                        iterations++;

                        const compressedFile = await compressWithParams(
                            img,
                            { width, height, quality: q, format: outputFormat },
                            file.name
                        );

                        // Check if this is the best result so far
                        if (!bestResult || compressedFile.size < bestResult.size) {
                            bestResult = compressedFile;
                        }

                        // If we're under the target size, use this
                        if (compressedFile.size <= targetSizeBytes) {
                            // Also check that it's smaller than original
                            if (compressedFile.size < file.size) {
                                resolve({
                                    file: compressedFile,
                                    compressed: true,
                                    originalSize: file.size,
                                    compressedSize: compressedFile.size,
                                    iterations,
                                    tooLarge: false,
                                });
                                return;
                            }
                        }

                        if (iterations >= maxIterations) break;
                    }
                    if (iterations >= maxIterations) break;
                }

                // If we couldn't get under target, use best result or original
                if (bestResult && bestResult.size < file.size) {
                    resolve({
                        file: bestResult,
                        compressed: true,
                        originalSize: file.size,
                        compressedSize: bestResult.size,
                        iterations,
                        tooLarge: bestResult.size > targetSizeBytes,
                    });
                } else {
                    // Original is smaller or equal - use original
                    resolve({
                        file,
                        compressed: false,
                        originalSize: file.size,
                        compressedSize: file.size,
                        iterations,
                        tooLarge: file.size > targetSizeBytes,
                    });
                }
            } catch (error) {
                reject(error);
            }
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
 * @returns {Promise<{files: File[], stats: {totalOriginal: number, totalCompressed: number, compressedCount: number, tooLargeCount: number, tooLargeFiles: string[]}}>}
 */
export const compressImages = async (files, options = {}, onProgress = null) => {
    const results = [];
    let totalOriginal = 0;
    let totalCompressed = 0;
    let compressedCount = 0;
    let tooLargeCount = 0;
    const tooLargeFiles = [];

    const fileArray = Array.from(files);

    for (let i = 0; i < fileArray.length; i++) {
        const file = fileArray[i];

        try {
            const result = await compressImage(file, options);

            if (result.tooLarge) {
                tooLargeCount++;
                tooLargeFiles.push(file.name);
            } else {
                results.push(result.file);
            }

            totalOriginal += result.originalSize;
            totalCompressed += result.compressedSize;
            if (result.compressed) compressedCount++;

            if (onProgress) {
                onProgress(i + 1, fileArray.length, result);
            }
        } catch (error) {
            // If compression fails, check if original is too large
            console.warn(`Failed to compress ${file.name}:`, error);
            const fileSizeKB = file.size / 1024;
            const opts = { ...defaultOptions, ...options };

            if (fileSizeKB > opts.targetSizeKB) {
                tooLargeCount++;
                tooLargeFiles.push(file.name);
            } else {
                results.push(file);
            }

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
            tooLargeCount,
            tooLargeFiles,
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

/**
 * Get the default target size in KB
 * @returns {number}
 */
export const getTargetSizeKB = () => defaultOptions.targetSizeKB;

export default {
    compressImage,
    compressImages,
    shouldCompress,
    exceedsTargetSize,
    getImageDimensions,
    formatFileSize,
    getTargetSizeKB,
};
