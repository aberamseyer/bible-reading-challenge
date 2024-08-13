# Pointing the Domain
Create 2 DNS `A` records with the value `5.161.204.56` for your www and socket subdomains

> e.g., `brc.churchinfairborn.org` and `brc-socket.churchinfairborn.org`

# Nginx, Apache
Nginx needs 2 new configuration files, Apache doesn't need any:
```
cp /etc/nginx/sites-available/cif-brc /etc/nginx/sites-available/{SHORT_NAME}
cp /etc/nginx/sites-available/cif-brc-socket /etc/nginx/sites-available/{SHORT_NAME}-socket
```
1. Edit both configuration files, replacing the domain name with the new domain name
2. Link the files to enable them:
```
ln -s /etc/nginx/sites-available/{SHORT_NAME} /etc/nginx/sites-enabled/{SHORT_NAME}
ln -s /etc/nginx/sites-available/{SHORT_NAME}-socket /etc/nginx/sites-enabled/{SHORT_NAME}-socket
```

Verify the files with `nginx -t`, enable them with `systemctl reload nginx`

# SSL
If they use cloudflare or have another way to proxy requests, great.

Otherwise, following the tutorial [here](https://certbot.eff.org/instructions?ws=nginx&os=debianbuster)
1. Ensure certbot is installed
2. `certbot certonly --nginx`
3. Go through steps to select a certificate for just the new virtual hosts you created for nginx
4. Add the following lines to vhost files (see [this](https://ssl-config.mozilla.org/#server=nginx&version=1.17.7&config=modern&openssl=1.1.1k&guideline=5.7) for help):
```
# SSL
ssl_certificate /etc/letsencrypt/live/{DOMAIN_NAME}/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/{DOMAIN_NAME}/privkey.pem;
ssl_trusted_certificate /etc/letsencrypt/live/{DOMAIN_NAME}/chain.pem;
include /etc/nginx/snippets/ssl.conf;
```
5. Test `certbot renew --dry-run`. A job should be scheduled to renew the certificate automatically, but set yourself a remind to check in on it

If these instructions were followed, a single certificate was generated that includes both domains (e.g., `brc` and `brc-socket`), so the nginx config lines will be identical in the vhost file

Again, check `nginx -t` and `systemctl reload nginx`

# Email
1. Create an account with `https://app.mailgun.com`
2. Go to Sending -> Domains, verify a domain using DNS
3. In the domain list, choose the 'settings' gear -> Sending API keys, and create a 'sending API key'

# Google Sign in
1. Open Google cloud console, personal project
2. Navigate to APIs & Services -> Credentials -> CoC login button
3. Add the new domain to the Authorized Javascript origins list
4. Add {NEW_DOMAIN}/auth/oath to the Authorized redirect URI list
