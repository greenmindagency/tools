import os
import json


PAGES = {"home", "about", "careers", "contact us"}
CATEGORIES = {"latest work", "clients", "blog", "news"}


def classify(entry: str):
    line = entry.strip()
    lower = line.lower()
    if lower.startswith("tag:"):
        return line[4:].strip(), "tag"
    if lower in PAGES:
        return line, "page"
    if lower in CATEGORIES:
        return line, "category"
    return line, "single"


def main():
    raw = os.environ.get("SITEMAP", "")
    lines = [line for line in raw.splitlines() if line.strip()]
    items = []
    for line in lines:
        title, typ = classify(line)
        items.append({"title": title, "type": typ})
    print(json.dumps({"sitemap": items}))


if __name__ == "__main__":
    main()
