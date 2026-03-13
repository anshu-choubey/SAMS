#!/bin/bash

cd /Users/anshu/sams-backend

echo ""
echo "┌────────────────────────────────────────────────────────────────────┐"
echo "│  SAMS DOCUMENTATION - CONVERTING TO HTML & PDF FORMAT             │"
echo "└────────────────────────────────────────────────────────────────────┘"
echo ""

PANDOC="/opt/homebrew/bin/pandoc"

files=(
    "01_BACKEND_ARCHITECTURE.md"
    "02_DATABASE_STRUCTURE.md"
    "03_API_DOCUMENTATION.md"
    "04_ANDROID_APP_ARCHITECTURE.md"
    "05_SYSTEM_INTEGRATION_GUIDE.md"
)

echo "Converting Markdown to HTML..."
echo ""

html_success=0
pdf_success=0

for file in "${files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "  ✗ $file - NOT FOUND"
        continue
    fi
    
    html_file="${file%.md}.html"
    pdf_file="${file%.md}.pdf"
    
    # Convert to HTML
    "$PANDOC" "$file" -o "$html_file" -t html5 --metadata title="${file%.md}" 2>/dev/null
    
    if [ -f "$html_file" ]; then
        size=$(du -h "$html_file" | cut -f1)
        lines=$(wc -l < "$html_file")
        echo "  ✓ $file"
        echo "    └─ HTML: $html_file (${size})"
        ((html_success++))
    fi
    
    # Try to convert to PDF with pandoc
    "$PANDOC" "$file" -o "$pdf_file" --pdf-engine=pdflatex 2>/dev/null
    
    if [ -f "$pdf_file" ] && [ -s "$pdf_file" ]; then
        size=$(du -h "$pdf_file" | cut -f1)
        echo "    └─ PDF: $pdf_file (${size})"
        ((pdf_success++))
    fi
    echo ""
done

echo "────────────────────────────────────────────────────────────────────"
echo ""

# Show summary
echo "✓ HTML Files Generated: $html_success"
echo "✓ PDF Files Generated: $pdf_success (if LaTeX is installed)"
echo ""

# List all generated files
echo "Generated Files:"
echo ""
ls -lh 0*.html 2>/dev/null | awk '{printf "  HTML: %-45s %6s\n", $9, $5}'
echo ""
ls -lh 0*.pdf 2>/dev/null | awk '{printf "  PDF:  %-45s %6s\n", $9, $5}' || echo "  (PDF requires LaTeX - install via: brew install basictex)"
echo ""

echo "────────────────────────────────────────────────────────────────────"
echo "✓ Documentation conversion complete!"
echo "  - HTML files can be opened in any web browser"
echo "  - From browser, use Print → Save as PDF for PDF versions"
echo "────────────────────────────────────────────────────────────────────"
echo ""
