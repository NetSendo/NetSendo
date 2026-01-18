const fs = require('fs');

// Parse JSON while tracking duplicate keys
function parseWithDuplicateDetection(content) {
    const lines = content.split('\n');
    const duplicates = [];
    const pathStack = [];
    const seenPaths = new Map();

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const lineNum = i + 1;

        // Count closing braces to pop from stack
        const closingCount = (line.match(/}/g) || []).length;
        for (let j = 0; j < closingCount; j++) {
            pathStack.pop();
        }

        // Find key definition
        const keyMatch = line.match(/^\s*"([^"]+)"\s*:/);
        if (keyMatch) {
            const key = keyMatch[1];
            const isObject = line.includes('{') && !line.includes('}');
            const fullPath = [...pathStack, key].join('.');

            if (seenPaths.has(fullPath)) {
                const prev = seenPaths.get(fullPath);
                duplicates.push({
                    key: fullPath,
                    firstLine: prev.line,
                    secondLine: lineNum,
                    firstIsObject: prev.isObject,
                    secondIsObject: isObject
                });
            } else {
                seenPaths.set(fullPath, { line: lineNum, isObject });
            }

            if (isObject) {
                pathStack.push(key);
            }
        }
    }

    return duplicates;
}

const files = ['pl.json', 'en.json', 'de.json', 'es.json'];

files.forEach(filename => {
    const content = fs.readFileSync(`resources/js/locales/${filename}`, 'utf8');
    const dups = parseWithDuplicateDetection(content);

    console.log(`\n=== ${filename} ===`);
    console.log(`Total duplicates: ${dups.length}`);

    // Group by root key
    const byRoot = {};
    dups.forEach(d => {
        const root = d.key.split('.')[0];
        if (!byRoot[root]) byRoot[root] = [];
        byRoot[root].push(d);
    });

    Object.entries(byRoot).forEach(([root, items]) => {
        console.log(`\n  ${root} (${items.length} duplicates)`);
        items.slice(0, 3).forEach(d => {
            console.log(`    - ${d.key}: Line ${d.firstLine} vs ${d.secondLine}`);
        });
        if (items.length > 3) console.log(`    ... and ${items.length - 3} more`);
    });
});
