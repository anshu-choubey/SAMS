#!/usr/bin/env python3
"""
Convert markdown files to PDF using pandoc with HTML intermediate
"""
import subprocess
import os

def convert_md_to_pdf_with_html():
    md_files = [
        "01_BACKEND_ARCHITECTURE.md",
        "02_DATABASE_STRUCTURE.md",
        "03_API_DOCUMENTATION.md",
        "04_ANDROID_APP_ARCHITECTURE.md",
        "05_SYSTEM_INTEGRATION_GUIDE.md"
    ]
    
    pandoc_path = "/opt/homebrew/bin/pandoc"
    base_dir = "/Users/anshu/sams-backend"
    
    print("\n" + "=" * 80)
    print("SAMS DOCUMENTATION - MARKDOWN TO PDF CONVERSION")
    print("=" * 80 + "\n")
    
    success = 0
    failed = 0
    
    for md_file in md_files:
        md_path = os.path.join(base_dir, md_file)
        pdf_file = md_file.replace(".md", ".pdf")
        html_file = md_file.replace(".md", ".html")
        html_path = os.path.join(base_dir, html_file)
        pdf_path = os.path.join(base_dir, pdf_file)
        
        if not os.path.exists(md_path):
            print(f"  ✗ {md_file:50} - NOT FOUND")
            failed += 1
            continue
        
        try:
            # Step 1: Convert MD to HTML
            html_result = subprocess.run(
                [pandoc_path, md_path, "-o", html_path, "-t", "html5"],
                capture_output=True,
                text=True,
                timeout=60
            )
            
            if html_result.returncode != 0:
                print(f"  ✗ {md_file:50} - HTML conversion failed")
                failed += 1
                continue
            
            # Step 2: Convert HTML to PDF using weasyprint (if available) or reportlab
            try:
                import weasyprint
                weasyprint.HTML(html_path).write_pdf(pdf_path)
                file_size = os.path.getsize(pdf_path) / 1024
                print(f"  ✓ {md_file:50} → PDF ({file_size:,.0f} KB)")
                success += 1
            except ImportError:
                # Fallback: Try another method or just keep HTML
                print(f"  ⚠ {md_file:50} → HTML ({os.path.getsize(html_path)/1024:,.0f} KB) [weasyprint not available]")
                failed += 1
                
        except subprocess.TimeoutExpired:
            print(f"  ✗ {md_file:50} - TIMEOUT")
            failed += 1
        except Exception as e:
            print(f"  ✗ {md_file:50} - ERROR: {str(e)[:40]}")
            failed += 1
    
    print("\n" + "=" * 80)
    print(f"Result: {success} successful, {failed} failed/warning")
    print("=" * 80 + "\n")

if __name__ == "__main__":
    convert_md_to_pdf_with_html()
