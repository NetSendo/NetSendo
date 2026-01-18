#!/usr/bin/env python3
"""
Fix duplicate keys in JSON translation files by:
1. Parsing JSON with duplicate key detection
2. Keeping the FIRST occurrence of each key (which is typically more complete in this codebase)
3. Writing clean JSON back
"""

import json
import re
import sys
from typing import Any, Dict, List, Tuple

def parse_json_with_duplicates(content: str) -> Tuple[Dict, List[Dict]]:
    """
    Parse JSON content, keeping track of duplicates.
    Returns (parsed_dict, list_of_duplicates)
    """
    duplicates = []

    # Custom JSON decoder that detects duplicates
    def object_pairs_hook(pairs):
        result = {}
        for key, value in pairs:
            if key in result:
                duplicates.append({
                    'key': key,
                    'first_value': result[key],
                    'second_value': value
                })
                # Keep the first value (usually more complete in this codebase)
            else:
                result[key] = value
        return result

    try:
        parsed = json.loads(content, object_pairs_hook=object_pairs_hook)
        return parsed, duplicates
    except json.JSONDecodeError as e:
        print(f"JSON parse error: {e}")
        return None, []

def fix_json_file(file_path: str, dry_run: bool = True) -> int:
    """
    Fix duplicate keys in a JSON file.
    Returns number of duplicates found.
    """
    print(f"\n{'='*60}")
    print(f"Processing: {file_path}")
    print('='*60)

    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    parsed, duplicates = parse_json_with_duplicates(content)

    if parsed is None:
        print(f"ERROR: Could not parse {file_path}")
        return -1

    if duplicates:
        print(f"Found {len(duplicates)} duplicate keys:")
        for dup in duplicates[:10]:
            key = dup['key']
            first_type = type(dup['first_value']).__name__
            second_type = type(dup['second_value']).__name__
            print(f"  - '{key}': {first_type} vs {second_type}")
        if len(duplicates) > 10:
            print(f"  ... and {len(duplicates) - 10} more")
    else:
        print("No duplicates found!")
        return 0

    if not dry_run:
        # Write deduplicated JSON back
        with open(file_path, 'w', encoding='utf-8') as f:
            json.dump(parsed, f, ensure_ascii=False, indent=4)
        print(f"✓ Fixed and saved {file_path}")
    else:
        print(f"[DRY RUN] Would fix {len(duplicates)} duplicates")

    return len(duplicates)

def main():
    files = [
        'resources/js/locales/pl.json',
        'resources/js/locales/en.json',
        'resources/js/locales/de.json',
        'resources/js/locales/es.json'
    ]

    # Check for --fix flag
    dry_run = '--fix' not in sys.argv

    if dry_run:
        print("=" * 60)
        print("DRY RUN MODE - No changes will be made")
        print("Run with --fix to apply changes")
        print("=" * 60)
    else:
        print("=" * 60)
        print("FIX MODE - Changes will be applied")
        print("=" * 60)

    total_duplicates = 0
    for file_path in files:
        try:
            count = fix_json_file(file_path, dry_run=dry_run)
            if count > 0:
                total_duplicates += count
        except FileNotFoundError:
            print(f"File not found: {file_path}")
        except Exception as e:
            print(f"Error processing {file_path}: {e}")

    print(f"\n{'='*60}")
    print(f"SUMMARY: {total_duplicates} total duplicates across all files")
    if dry_run and total_duplicates > 0:
        print("Run with --fix to apply fixes")
    print('='*60)

if __name__ == '__main__':
    main()
