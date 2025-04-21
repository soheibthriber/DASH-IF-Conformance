#!/usr/bin/env python3
import os
import requests
import sys
import re
from urllib.parse import urljoin

# All Dolby AC-4 test vectors
DOLBY_AC4_VECTORS = [
    # On-Demand profile
    "https://dash.akamaized.net/dash264/TestCasesDolby/1/Living_Room_1080p_20_96k_25fps.mpd",           # Stereo 25fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/2/Living_Room_1080p_20_96k_2997fps.mpd",         # Stereo 29.97fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/3/Living_Room_1080p_51_192k_25fps.mpd",          # 5.1 25fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/4/Living_Room_1080p_51_192k_2997fps.mpd",        # 5.1 29.97fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/5/Living_Room_1080p_51_192k_320k_25fps.mpd",     # Multi-rep 25fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/6/Living_Room_1080p_51_192k_320k_2997fps.mpd",   # Multi-rep 29.97fps
    
    # Live profile
    "https://dash.akamaized.net/dash264/TestCasesDolby/7/Living_Room_1080p_20_96k_25fps.mpd",          # Stereo 25fps 
    "https://dash.akamaized.net/dash264/TestCasesDolby/8/Living_Room_1080p_20_96k_2997fps.mpd",        # Stereo 29.97fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/9/Living_Room_1080p_51_192k_25fps.mpd",         # 5.1 25fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/10/Living_Room_1080p_51_192k_2997fps.mpd",      # 5.1 29.97fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/11/Living_Room_1080p_51_192k_320k_25fps.mpd",   # Multi-rep 25fps
    "https://dash.akamaized.net/dash264/TestCasesDolby/12/Living_Room_1080p_51_192k_320k_2997fps.mpd", # Multi-rep 29.97fps
]

DOWNLOAD_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "testvectors")

def download_file(url, local_path, byte_range=None):
    """Download a file and save it locally."""
    headers = {}
    if byte_range:
        headers['Range'] = f'bytes={byte_range}'
        print(f"Downloading: {url} (Range: {byte_range})")
    else:
        print(f"Downloading: {url}")
        
    try:
        response = requests.get(url, headers=headers, stream=True)
        response.raise_for_status()
        
        # Ensure directory exists
        os.makedirs(os.path.dirname(local_path), exist_ok=True)
        
        with open(local_path, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)
        
        if os.path.exists(local_path) and os.path.getsize(local_path) > 0:
            print(f"Saved to: {local_path} ({os.path.getsize(local_path)/1024:.1f} KB)")
            return True
        else:
            print(f"ERROR: File is empty: {local_path}")
            return False
            
    except Exception as e:
        print(f"Error downloading {url}: {e}")
        return False

def process_vector(mpd_url, vector_number):
    """Process a single test vector."""
    # Create directory with numbered and descriptive name
    vector_name = f"{vector_number:02d}_AC4_Vector_{os.path.basename(os.path.dirname(mpd_url))}"
    vector_dir = os.path.join(DOWNLOAD_DIR, vector_name)
    os.makedirs(vector_dir, exist_ok=True)
    
    # Download the MPD file
    mpd_filename = os.path.basename(mpd_url)
    mpd_path = os.path.join(vector_dir, mpd_filename)
    
    if not os.path.exists(mpd_path):
        if not download_file(mpd_url, mpd_path):
            return False
    else:
        print(f"Using existing MPD file: {mpd_path}")
    
    # Parse the MPD to find media segments
    try:
        with open(mpd_path, 'r') as f:
            mpd_content = f.read()
        
        # Determine MPD structure
        segment_base = "SegmentBase" in mpd_content
        segment_template = "SegmentTemplate" in mpd_content
        print(f"MPD contains: SegmentBase={segment_base}, SegmentTemplate={segment_template}")
        
        # Base URL for media files
        base_url = os.path.dirname(mpd_url) + "/"
        print(f"Base URL: {base_url}")
        
        # Create a list to track all downloaded files
        downloaded_files = []
        
        # CASE 1: MPD uses SegmentBase with BaseURL (typical for On-Demand)
        if segment_base:
            # Extract all BaseURL elements with AC-4 audio files
            audio_files = []
            
            # Look for BaseURL elements that contain media files
            base_url_pattern = r"<BaseURL>([^<]+)</BaseURL>"
            media_files = re.findall(base_url_pattern, mpd_content)
            
            # Filter for audio files
            for media_file in media_files:
                if "audio" in media_file.lower() or "ac-4" in media_file.lower():
                    audio_files.append(media_file)
            
            if audio_files:
                print(f"Found {len(audio_files)} AC-4 audio files (SegmentBase)")
                
                # Download each audio file
                for audio_file in audio_files:
                    media_url = urljoin(base_url, audio_file)
                    media_path = os.path.join(vector_dir, audio_file)
                    
                    if not os.path.exists(media_path):
                        if download_file(media_url, media_path):
                            downloaded_files.append(audio_file)
                    else:
                        print(f"Audio file already exists: {media_path}")
                        downloaded_files.append(audio_file)
                    
                    # Extract initialization segment for each audio file
                    init_ranges = re.findall(r'<Initialization[^>]*range="([^"]+)"', mpd_content)
                    if init_ranges:
                        for init_range in init_ranges:
                            init_filename = f"{os.path.splitext(audio_file)[0]}_init.mp4"
                            init_path = os.path.join(vector_dir, init_filename)
                            
                            if not os.path.exists(init_path):
                                if download_file(media_url, init_path, init_range):
                                    downloaded_files.append(init_filename)
                            else:
                                print(f"Init segment already exists: {init_path}")
                                downloaded_files.append(init_filename)
        
        # CASE 2: MPD uses SegmentTemplate (typical for Live)
        if segment_template:
            print("Processing SegmentTemplate...")
            
            # Extract both representations and their codecs directly
            rep_pattern = r'<Representation\s+([^>]*)>'
            representations = re.findall(rep_pattern, mpd_content)
            
            # Extract all representation IDs and check for AC-4 codecs
            rep_ids = []
            ac4_rep_ids = []
            
            for rep_attrs in representations:
                id_match = re.search(r'id="([^"]*)"', rep_attrs)
                codec_match = re.search(r'codecs="([^"]*)"', rep_attrs)
                
                if id_match:
                    rep_id = id_match.group(1)
                    rep_ids.append(rep_id)
                    
                    # Check if this is an AC-4 representation
                    if codec_match and "ac-4" in codec_match.group(1).lower():
                        ac4_rep_ids.append(rep_id)
            
            print(f"Found {len(rep_ids)} representation IDs: {rep_ids}")
            
            if ac4_rep_ids:
                print(f"Found {len(ac4_rep_ids)} AC-4 representations: {ac4_rep_ids}")
                
                # Get the initialization and media template patterns
                init_template_pattern = r'<SegmentTemplate[^>]*initialization="([^"]*)"'
                media_template_pattern = r'<SegmentTemplate[^>]*media="([^"]*)"'
                
                init_templates = re.findall(init_template_pattern, mpd_content)
                media_templates = re.findall(media_template_pattern, mpd_content)
                
                # Process each AC-4 representation
                for rep_id in ac4_rep_ids:
                    for init_template in init_templates:
                        # Replace $RepresentationID$ with the actual ID
                        url_init = init_template.replace("$RepresentationID$", rep_id)
                        init_url = urljoin(base_url, url_init)
                        
                        # Create safe filename for local storage
                        safe_filename = url_init.replace("/", "_")
                        init_path = os.path.join(vector_dir, safe_filename)
                        
                        print(f"Downloading init segment: {init_url}")
                        if download_file(init_url, init_path):
                            downloaded_files.append(os.path.basename(init_path))
                    
                    # Download first few media segments
                    for media_template in media_templates:
                        # For Live, we need the first 3 segments to fully validate
                        for segment_number in range(1, 4):
                            # Replace both $RepresentationID$ and $Number$ in the template
                            url_media = media_template.replace("$RepresentationID$", rep_id).replace("$Number$", str(segment_number))
                            media_url = urljoin(base_url, url_media)
                            
                            # Create safe filename for local storage
                            safe_filename = url_media.replace("/", "_")
                            media_path = os.path.join(vector_dir, safe_filename)
                            
                            print(f"Downloading media segment {segment_number}: {media_url}")
                            if download_file(media_url, media_path):
                                downloaded_files.append(os.path.basename(media_path))
            else:
                print("Warning: No AC-4 representations found in MPD")
        
        # Extract MPD information for the info file
        profile_type = "Unknown"
        if "on-demand" in mpd_content.lower():
            profile_type = "On-Demand"
        elif "live" in mpd_content.lower() or "dynamic" in mpd_content.lower():
            profile_type = "Live"
        
        channel_config = "Unknown"
        if "ChannelConfiguration" in mpd_content:
            channel_config_match = re.search(r'ChannelConfiguration[^>]*value="([^"]+)"', mpd_content)
            if channel_config_match:
                ch_value = channel_config_match.group(1)
                if ch_value == "2":
                    channel_config = "Stereo (2.0)"
                elif ch_value == "6":
                    channel_config = "5.1 Surround"
                else:
                    channel_config = f"{ch_value} channels"
        
        framerate = "Unknown"
        framerate_match = re.search(r'frameRate="([^"]+)"', mpd_content)
        if framerate_match:
            framerate = framerate_match.group(1)
        
        # Save information file
        with open(os.path.join(vector_dir, "info.txt"), 'w') as f:
            f.write(f"Vector Number: {vector_number}\n")
            f.write(f"Source MPD: {mpd_url}\n")
            f.write(f"Content type: Dolby AC-4 audio\n")
            f.write(f"Profile: {profile_type}\n")
            f.write(f"Channels: {channel_config}\n")
            f.write(f"Frame rate: {framerate}\n")
            f.write(f"MPD structure: SegmentBase={segment_base}, SegmentTemplate={segment_template}\n\n")
            
            f.write(f"Downloaded files ({len(downloaded_files)}):\n")
            for file in downloaded_files:
                f.write(f"  - {file}\n")
            
            f.write(f"\nValidation information:\n")
            f.write(f"  - This vector contains AC-4 audio that can be validated\n")
            f.write(f"  - The module checks consistency between TOC data and DAC4 box\n")
        
        return len(downloaded_files) > 0
    
    except Exception as e:
        print(f"Error processing MPD: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    print(f"Downloading Dolby AC-4 test vectors to {DOWNLOAD_DIR}")
    os.makedirs(DOWNLOAD_DIR, exist_ok=True)
    
    # Process all vectors
    vectors_to_process = [(i+1, url) for i, url in enumerate(DOLBY_AC4_VECTORS)]
    
    print(f"Downloading {len(vectors_to_process)} Dolby AC-4 test vectors")
    
    success_count = 0
    for vector_num, url in vectors_to_process:
        vector_name = os.path.basename(os.path.dirname(url))
        print(f"\n=== Processing AC-4 Vector {vector_num}: {vector_name} ===")
        
        if process_vector(url, vector_num):
            success_count += 1
    
    print(f"\nSuccessfully processed {success_count}/{len(vectors_to_process)} Dolby AC-4 test vectors")
    print(f"Test vectors downloaded to: {DOWNLOAD_DIR}")

if __name__ == "__main__":
    main()