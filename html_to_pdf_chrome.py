#!/usr/bin/env python3
"""
Convert HTML files to PDF using Chrome headless browser
"""
import subprocess
import os
import sys

def convert_html_to_pdf_chrome():
    html_files = [
        "01_BACKEND_ARCHITECTURE.html",
        "02_DATABASE_STRUCTURE.html",
        "03_API_DOCUMENTATION.html",
        "04_ANDROID_APP_ARCHITECTURE.html",
        "05_SYSTEM_INTEGRATION_GUIDE.html"
    ]
    
    base_dir = "/Users/anshu/sams-backend"
    
    # Try to find Chrome
    chrome_paths = [
        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        "/Applications/Chromium.app/Contents/MacOS/Chromium"
    ]
    
    chrome_path = None
    for path in chrome_paths:
        if os.path.exists(path):
            chrome_path = path
            break
    
    if not chrome_path:
        print("ERROR: Chrome or Chromium not found")
        print("Please install: brew install --cask google-chrome")
        return
    
    print("\n" + "=" * 80)
    print("HTML TO PDF CONVERSION USING CHROME HEADLESS")
    print("=" * 80 + "\n")
    
    for html_file in html_files:
        html_path = os.path.join(base_dir, html_file)
        pdf_file = html_file.replace(".html", ".pdf")
        pdf_path = os.path.join(base_dir, pdf_file)
        
        if not os.path.exists(html_path):
            print(f"✗ {html_file:45} - NOT FOUND")
            continue
        
        try:
            # Use Chrome to print HTML to PDF
            cmd = [
                chrome_path,
                "--headless",
                "--disable-gpu",
                f"--print-to-pdf={pdf_path}",
                html_path
            ]
            
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=60
            )
            
            if os.path.exists(pdf_path) and os.path.getsize(pdf_path) > 1000:
                size = os.path.getsize(pdf_path) / 1024
                print(f"✓ {html_file:40} → {pdf_file:40} ({size:,.0f} KB)")
            else:
                print(f"⚠ {html_file:40} - Conversion may have failed")
                
        except subprocess.TimeoutExpired:
            print(f"✗ {html_file:40} - TIMEOUT")
        except Exception as e:
            print(f"✗ {html_file:40} - ERROR: {str(e)[:40]}")
    
    print("\n" + "=" * 80)
    print("Conversion complete! Check output files.")
    print("=" * 80 + "\n")

if __name__ == "__main__":
    convert_html_to_pdf_chrome()
