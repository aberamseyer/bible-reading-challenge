# Pointing the Domain
Create 2 DNS `A` records with the value `5.161.204.56`

> e.g., `brc.ramseyer.dev` and `brc-socket.ramseyer.dev`

# Nginx, Apache
Nginx needs 2 new configuration files, Apache doesn't need any:
```
cp /etc/nginx/sites-available/abe-brc /etc/nginx/sites-available/{SHORT_NAME}
cp /etc/nginx/sites-available/abe-brc-socket /etc/nginx/sites-available/{SHORT_NAME}-socket
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

Otherwise:

1. Copy crt file to `/etc/ssl/personal-certs`
2. Copy site configuration from `churchinfairborn.org` (`site-available/cif-brc`), replacing the SSL file path

# Email
1. Create an account with `https://app.sendgrid.com`
2. create 3 templates: Register email, daily email, and forgot password. 
3. Put the template IDS in the environment configuration

# Google Sign in
1. Open Google cloud console, personal project
2. Navigate to APIs & Services -> Credentials -> CoC login button
3. Add the new domain to the Authorized Javascript origins list
4. Add {NEW_DOMAIN}/auth/oath to the Authorized redirect URI list
