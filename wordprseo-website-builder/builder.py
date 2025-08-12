import os
import json


def main():
    raw = os.environ.get('SITEMAP', '')
    lines = [line.strip() for line in raw.splitlines() if line.strip()]
    print(json.dumps({'sitemap': lines}))


if __name__ == '__main__':
    main()
