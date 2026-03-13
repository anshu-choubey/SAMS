#!/bin/bash

cd /Users/anshu/sams-backend

echo "=========================================================================="
echo "SAMS DOCUMENTATION - CONVERTING TO PDF"
echo "=========================================================================="
echo ""

PANDOC="/opt/homebrew/bin/pandoc"

files=(
    "01_BACKEND_ARCHITECTURE.md"
    "02_DATABASE_STRUCTURE.md"
    "03_API_DOCUMENTATION.md"
    "04_ANDROID_APP_ARCHITECTURE.md"
    "05_SYSTEM_INTEGRATION_GUIDE.md"
)

success=0
failed=0

for file in "${files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "  ✗ $file - NOT FOUND"
        ((failed++))
        continue
    fi
    
    pdf_file="${file%.md}.pdf"
    
    # Try to convert - without LaTeX engine (use default)
    echo "  Converting: $file..."
    $PANDOC "$file" -o "$pdf_file" 2>/dev/null
    
    if [ -f "$pdf_file" ]; then
        size=$(du -sh "$pdf_file" | cut -f1)
        echo "    ✓ Created: $pdf_file ($size)"
        ((success++))
    else
        echo "    ✗ Failed to create PDF"
        ((failed++))
    fi
done

echo ""
echo "=========================================================================="
echo "CONVERSION RESULTS: $success successful, $failed failed"
echo "=========================================================================="
echo ""

# List generated PDFs
echo "Generated PDF files:"
ls -lh *.pdf 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
