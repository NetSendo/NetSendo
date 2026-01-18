#!/usr/bin/env python3
"""
Script to fix duplicate keys in JSON translation files.
Strategy: Parse as ordered JSON, detect duplicates, and merge/keep the more complete version.
"""

import json
import re
import sys
from collections import OrderedDict

def find_duplicate_keys(file_path):
    """Find duplicate keys at each level by parsing manually."""
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    duplicates = []
    lines = content.split('\n')
    path_stack = []
    seen_paths = {}  # path -> (line_number, is_object)

    for i, line in enumerate(lines):
        line_num = i + 1

        # Count closing braces to pop
        close_count = line.count('}')
        for _ in range(close_count):
            if path_stack:
                path_stack.pop()

        # Find key definition
        match = re.match(r'^\s*"([^"]+)"\s*:', line)
        if match:
            key = match.group(1)
            is_object = '{' in line and '}' not in line
            full_path = '.'.join(path_stack + [key])

            if full_path in seen_paths:
                prev_line, prev_is_obj = seen_paths[full_path]
                duplicates.append({
                    'key': full_path,
                    'first_line': prev_line,
                    'second_line': line_num,
                    'first_is_object': prev_is_obj,
                    'second_is_object': is_object
                })
            else:
                seen_paths[full_path] = (line_num, is_object)

            if is_object:
                path_stack.append(key)

    return duplicates

def get_duplicate_ranges(file_path, target_path):
    """Find the line range for a duplicate key section that should be removed."""
    with open(file_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    ranges = []
    path_stack = []
    in_target = False
    start_line = None
    brace_depth = 0
    occurrences = []

    for i, line in enumerate(lines):
        line_num = i + 1

        # Count braces
        open_count = line.count('{')
        close_count = line.count('}')

        for _ in range(close_count):
            if path_stack:
                path_stack.pop()

        # Check if entering target section
        match = re.match(r'^\s*"([^"]+)"\s*:', line)
        if match:
            key = match.group(1)
            full_path = '.'.join(path_stack + [key])
            is_object = '{' in line and '}' not in line

            if full_path == target_path:
                occurrences.append({
                    'start': line_num,
                    'is_object': is_object
                })

            if is_object:
                path_stack.append(key)

    return occurrences

def main():
    files = [
        'resources/js/locales/pl.json',
        'resources/js/locales/en.json',
        'resources/js/locales/de.json',
        'resources/js/locales/es.json'
    ]

    for file_path in files:
        try:
            dups = find_duplicate_keys(file_path)
            print(f"\n=== {file_path} ===")
            print(f"Total duplicates: {len(dups)}")

            # Group by root
            by_root = {}
            for d in dups:
                root = d['key'].split('.')[0]
                if root not in by_root:
                    by_root[root] = []
                by_root[root].append(d)

            for root, items in sorted(by_root.items()):
                print(f"\n  {root}: {len(items)} duplicates")
                for item in items[:3]:
                    print(f"    - {item['key']}: lines {item['first_line']} vs {item['second_line']}")
                if len(items) > 3:
                    print(f"    ... and {len(items) - 3} more")
        except Exception as e:
            print(f"Error processing {file_path}: {e}")

if __name__ == '__main__':
    main()
