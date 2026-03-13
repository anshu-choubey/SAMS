#!/usr/bin/env python3
"""
Convert markdown files to PDF using pandoc
"""
import subprocess
import os
import sys

def convert_md_to_pdf():
    md_files = [
        "01_BACKEND_ARCHITECTURE.md",
        "02_DATABASE_STRUCTURE.md",
        "03_API_DOCUMENTATION.md",
        "04_ANDROID_APP_ARCHITECTURE.md",
        "05_SYSTEM_INTEGRATION_GUIDE.md"
    ]
    
    pandoc_path = "/opt/homebrew/bin/pandoc"
    base_dir = "/Users/anshu/sams-backend"
    
    print("=" * 70)
    print("SAMS DOCUMENTATION - MARKDOWN TO PDF CONVERSION")
    print("=" * 70)
    
    success_count = 0
    failed_count = 0
    
    for md_file in md_files:
        md_path = os.path.join(base_dir, md_file)
        pdf_file = md_file.replace(".md", ".pdf")
        pdf_path = os.path.join(base_dir, pdf_file)
        
        if not os.path.exists(md_path):
            print(f"✗ {md_file:45} - FILE NOT FOUND")
            failed_count += 1
            continue
        
        try:
            # Run pandoc conversion
            result = subprocess.run(
                [pandoc_path, md_path, "-o", pdf_path],
                capture_output=True,
                text=True,
                timeout=180
            )
            
            if result.returncode == 0 and os.path.exists(pdf_path):
                file_size = os.path.getsize(pdf_path) / 1024  # KB
                print(f"✓ {md_file:45} → {pdf_file:30} ({file_size:,.0f} KB)")
                success_count += 1
            else:
                print(f"✗ {md_file:45} - CONVERSION FAILED")
                if result.stderr:
                    print(f"   Error: {result.stderr[:150]}")
                failed_count += 1
                
        except subprocess.TimeoutExpired:
            print(f"✗ {md_file:45} - TIMEOUT (>180s)")
            failed_count += 1
        except Exception as e:
            print(f"✗ {md_file:45} - ERROR: {str(e)[:60]}")
            failed_count += 1
    
    print("=" * 70)
    print(f"CONVERSION COMPLETE: {success_count} successful, {failed_count} failed")
    print("=" * 70)
    
    return success_count, failed_count

if __name__ == "__main__":
    convert_md_to_pdf()
