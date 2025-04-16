#!/usr/bin/env python3
import os
import json
import requests
import sys
import urllib3

# Disable SSL warnings 
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

DASHJS_JSON_URL = "https://testassets.dashif.org/dashjs.json"
DOWNLOAD_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "testvectors")
TIMEOUT = 60

def download_file(url, local_path, verify_ssl=False):
    try:
        with requests.get(url, stream=True, timeout=TIMEOUT, verify=verify_ssl) as r:
            r.raise_for_status()
            total_size = int(r.headers.get('content-length', 0))
            
            os.makedirs(os.path.dirname(local_path), exist_ok=True)
            
            with open(local_path, 'wb') as f:
                if total_size == 0:
                    print(f"Downloading {url} (unknown size)")
                    f.write(r.content)
                else:
                    downloaded = 0
                    print(f"Downloading {url} ({total_size/1024/1024:.1f} MB)")
                    for chunk in r.iter_content(chunk_size=8192):
                        if chunk:
                            f.write(chunk)
                            downloaded += len(chunk)
                            done = int(50 * downloaded / total_size)
                            sys.stdout.write("\r[%s%s] %d%%" % ('=' * done, ' ' * (50-done), done*2))
                            sys.stdout.flush()
                    print()
            return True
    except Exception as e:
        print(f"Error downloading {url}: {e}")
        return False

def main():
    print(f"Downloading Dolby test vectors to {DOWNLOAD_DIR}")
    os.makedirs(DOWNLOAD_DIR, exist_ok=True)
    
    print(f"Fetching test vector list from {DASHJS_JSON_URL}")
    try:
        response = requests.get(DASHJS_JSON_URL, timeout=10, verify=False)
        response.raise_for_status()
        config = response.json()
    except Exception as e:
        print(f"Error fetching test vectors list: {e}")
        return 1
    
    dolby_streams = []
    for group in config.get('items', []):
        if group.get('name') == "Dolby Audio AC-4":
            dolby_streams = group.get('submenu', [])
            break
    
    if not dolby_streams:
        print("No Dolby test vectors found!")
        return 1
    
    print(f"Found {len(dolby_streams)} Dolby test vectors")
    
    for i, stream in enumerate(dolby_streams, 1):
        name = stream.get('name', f"Vector_{i}")
        url = stream.get('url')
        if not url:
            continue
            
        safe_name = "".join([c if c.isalnum() else "_" for c in name])
        local_path = os.path.join(DOWNLOAD_DIR, f"{i:02d}_{safe_name}.mpd")
        
        if os.path.exists(local_path):
            print(f"Already downloaded: {name}")
            continue
            
        print(f"\nVector {i}/{len(dolby_streams)}: {name}")
        success = download_file(url, local_path, verify_ssl=False)
        if success:
            print(f"Saved to {local_path}")
    
    print("\nDownload complete!")
    return 0

if __name__ == "__main__":
    sys.exit(main())