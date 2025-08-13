import sys
import os


def read_file(path: str) -> str:
    """Return text extracted from the uploaded file.

    Currently only plain text files are parsed. Other file types
    return a placeholder message. This is a starting point that can
    be extended to handle PDFs, Word documents, PowerPoint files, etc.
    """
    _, ext = os.path.splitext(path)
    ext = ext.lower()
    if ext == ".txt":
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            return f.read()
    return f"Unsupported file type: {ext}"


def generate_homepage(text: str) -> str:
    """Generate simple homepage sections from the source text."""
    snippet = text.strip().splitlines()[0] if text.strip() else "Your content"
    return (
        "# Hero\n"
        f"Welcome to our site! {snippet}\n\n"
        "# About Us\n"
        "We turn your documents into compelling web pages.\n\n"
        "# Services\n"
        "Our services are tailored to your needs.\n\n"
        "# Contact\n"
        "Get in touch for more information."
    )


def main() -> None:
    if len(sys.argv) < 3:
        print("Usage: generate_content.py <page> <file>", file=sys.stderr)
        sys.exit(1)
    page, file_path = sys.argv[1], sys.argv[2]
    text = read_file(file_path)
    if page == "home":
        print(generate_homepage(text))
    else:
        print(f"Page type '{page}' not supported.")


if __name__ == "__main__":
    main()
