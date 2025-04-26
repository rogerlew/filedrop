# Bearhive Filedrop

A minimal self-hosted file drop service with end-to-end encryption and one-time-use keys.  
Upload a single file archive up to 200 MB and get back a secure download link and shell command to fetch, decrypt, and extract—without the server ever retaining your decryption key.

## Features

- **Single-file upload** via a clean, one-page interface (`index.html`).
- **AES-256-CBC encryption** with a unique, randomly generated key & IV per upload.
- **One-time-use download command**: `wget | openssl | tar xz` pipeline that embeds the key/IV.
- **200 MB upload limit** configured in Nginx (`client_max_body_size`) and PHP (`upload_max_filesize`, `post_max_size`).
- **Opaque storage layout**: encrypted archives saved under `.files/xx/yy/<UUID>.tar.gz.enc`.
- **No server-side key retention**: after upload, only the client holds the decryption key.

## Installation

1. **Clone the repository**  
   ```bash
   git clone https://github.com/rogerlew/filedrop.git
   cd filedrop
   rsync -av filedrop/ var/www/filedrop/
   ```

2. **Create storage directory**  
   ```bash
   sudo mkdir -p /var/www/filedrop/.files
   sudo chown -R www-data:www-data /var/www/filedrop/.files
   ```

3. **Deploy Nginx vhost**  
   - Copy `etc/nginx/sites-enabled/bearhive-filedrop` into `/etc/nginx/sites-available/`  
   - Enable it:
     ```bash
     sudo ln -s /etc/nginx/sites-available/bearhive-filedrop /etc/nginx/sites-enabled/
     sudo nginx -t && sudo systemctl reload nginx
     ```

4. **Configure PHP-FPM**  
   - Ensure PHP 8.3 FPM is installed and running.  
   - Adjust `upload_max_filesize` and `post_max_size` to `200M` in `/etc/php/8.3/fpm/php.ini` or pool config.  
   - Reload:
     ```bash
     sudo systemctl reload php8.3-fpm
     ```

5. **Point your DNS** (or pfSense override)  
   - Make `filedrop.bearhive.duckdns.org` resolve to your homelab Nginx box.

6. **TLS termination**  
   - pfSense handles HTTPS. The web app assumes `https://` at the client side.

## Configuration

- **Upload limit**: `client_max_body_size 200M` in Nginx vhost.  
- **PHP limits**:  
  ```ini
  upload_max_filesize = 200M
  post_max_size      = 200M
  memory_limit       = 256M
  ```

## Usage

1. Navigate to `https://filedrop.bearhive.duckdns.org/`.  
2. Select a file (≤ 200 MB) and click **Upload**.  
3. Copy the returned **Download Command** and paste it into your terminal. The command will:
   - Fetch the encrypted archive  
   - Decrypt with the one‐time key & IV  
   - Extract the original file  

Example download command:
```bash
wget --quiet --output-document=- "https://filedrop.bearhive.duckdns.org/.files/38/fe/...enc" \
  | openssl enc -d --aes-256-cbc -K <hex-key> -iv <hex-iv> \
  | tar xz
```

## Disclaimer

This tool is provided **as-is** for personal or internal homelab use.  
Use at your own risk—audit the code and ensure it meets your security requirements before exposing publicly.

---
