# NoMail php mailer

Just a test implementation of SMTP protocol with PHP without any native sendmail or any existing php mail library.

## Setup

```bash
git clone ...
cp .env.dist .env
docker-compose up -d
docker-compose exec php ash
composer install
exit
open http://nomail.docker.localhost
open http://mailhog.nomail.docker.localhost
```

## Restrictions

No authentication supported.

Cannot be used with external mail services to avoid the spam, only local testing!
