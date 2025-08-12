import sys
import os
import json

def main():
    sitemap = os.environ.get('SITEMAP', '')
    files = sys.argv[1:]
    details = []
    for path in files:
        info = {'file': os.path.basename(path)}
        try:
            with open(path, 'r', errors='ignore') as f:
                info['preview'] = f.read(500)
        except Exception as e:
            info['error'] = str(e)
        details.append(info)
    output = {'sitemap': sitemap, 'files': details}
    print(json.dumps(output))

if __name__ == '__main__':
    main()
