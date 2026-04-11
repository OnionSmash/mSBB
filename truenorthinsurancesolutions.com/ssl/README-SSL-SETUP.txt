True North SSL Setup (Isolated Per-Site)

Created files:
- certs/truenorthinsurancesolutions.com.crt (self-signed cert)
- private/truenorthinsurancesolutions.com.key (private key)
- csr/truenorthinsurancesolutions.com.csr (CSR for CA submission)

Current status:
- Self-signed cert is ready for immediate use.
- Let's Encrypt webroot validation currently fails because HTTP for truenorthinsurancesolutions.com redirects to feautofab.com before ACME challenge can be read.

1) Apache vhost requirements for this domain
- Ensure a dedicated :80 vhost exists for:
  ServerName truenorthinsurancesolutions.com
  ServerAlias www.truenorthinsurancesolutions.com
  DocumentRoot /var/www/truenorthinsurancesolutions.com

- Add ACME challenge exception before redirect rule in the :80 vhost:
  RewriteEngine On
  RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

2) Issue isolated Let's Encrypt cert (no cross-site config mixing)
Run:
certbot certonly --webroot \
  -w /var/www/truenorthinsurancesolutions.com \
  -d truenorthinsurancesolutions.com -d www.truenorthinsurancesolutions.com \
  --config-dir /var/www/truenorthinsurancesolutions.com/ssl/config \
  --work-dir /var/www/truenorthinsurancesolutions.com/ssl/work \
  --logs-dir /var/www/truenorthinsurancesolutions.com/ssl/logs \
  --agree-tos --register-unsafely-without-email --non-interactive

Resulting trusted cert files will be under:
- /var/www/truenorthinsurancesolutions.com/ssl/config/live/truenorthinsurancesolutions.com/fullchain.pem
- /var/www/truenorthinsurancesolutions.com/ssl/config/live/truenorthinsurancesolutions.com/privkey.pem

3) Apache SSL vhost paths
For self-signed now:
- SSLCertificateFile /var/www/truenorthinsurancesolutions.com/ssl/certs/truenorthinsurancesolutions.com.crt
- SSLCertificateKeyFile /var/www/truenorthinsurancesolutions.com/ssl/private/truenorthinsurancesolutions.com.key

For Let's Encrypt after successful issuance:
- SSLCertificateFile /var/www/truenorthinsurancesolutions.com/ssl/config/live/truenorthinsurancesolutions.com/fullchain.pem
- SSLCertificateKeyFile /var/www/truenorthinsurancesolutions.com/ssl/config/live/truenorthinsurancesolutions.com/privkey.pem

4) Auto-renew check (isolated dirs)
certbot renew \
  --config-dir /var/www/truenorthinsurancesolutions.com/ssl/config \
  --work-dir /var/www/truenorthinsurancesolutions.com/ssl/work \
  --logs-dir /var/www/truenorthinsurancesolutions.com/ssl/logs \
  --dry-run

5) Installed auto-renew schedule
- Renewal is scheduled twice daily at 03:17 and 15:17 server time.
- Renewal command targets only this site's isolated certbot directories.
- On successful renewal, Apache is reloaded automatically.
