import json
import os

def get_keys(data, prefix=''):
    keys = set()
    for k, v in data.items():
        if isinstance(v, dict):
            keys.update(get_keys(v, f"{prefix}{k}."))
        else:
            keys.add(f"{prefix}{k}")
    return keys

def compare_locales(base_path):
    pl_file = os.path.join(base_path, 'pl.json')
    with open(pl_file, 'r', encoding='utf-8') as f:
        pl_data = json.load(f)
    
    pl_keys = get_keys(pl_data)
    
    for lang in ['en', 'de', 'es']:
        lang_file = os.path.join(base_path, f"{lang}.json")
        if not os.path.exists(lang_file):
            print(f"File {lang_file} missing")
            continue
            
        with open(lang_file, 'r', encoding='utf-8') as f:
            lang_data = json.load(f)
        
        lang_keys = get_keys(lang_data)
        missing = pl_keys - lang_keys
        
        print(f"\nMissing keys for {lang}: {len(missing)}")
        for m in sorted(list(missing)):
            print(f"  {m}")

if __name__ == "__main__":
    compare_locales('/Users/grzegorzciupek/Downloads/Programowanie/NetSendo/v2/src/resources/js/locales')
