#!/var/www/vendor/bin/drush
cd $WEBDOC

### The System Is Down
drush scr scripts/nodepgs/src/nodecreate.php "The System Is Down" "scripts/nodepgs/codepages/systemdown.txt" "basic_page"


### Page Not Found
drush scr scripts/nodepgs/src/nodecreate.php "Page Not Found" "scripts/nodepgs/codepages/404.txt" "basic_page"
drush scr scripts/nodepgs/src/nodecreate.php "No se encontró la página" "scripts/nodepgs/codepages/404es.txt" "basic_page" "es"

drush config:set system.site page.404 '/page-not-found' -y

mkdir -p $WEBDOC/scode/
wget https://localhost/page-not-found --no-check-certificate -O $WEBDOC/scode/page-not-found.html
wget https://localhost/es/no-se-encontro-la-pagina --no-check-certificate -O $WEBDOC/scode/no-se-encontro-la-pagina.html
wget https://localhost/system-down --no-check-certificate -O $WEBDOC/scode/system-down.html

# Restart services
s6-svc -r /var/run/s6/services/php/
s6-svc -r /var/run/s6/services/nginx/

wget https://cms-dev.usa.gov/page-not-found --no-check-certificate -O $WEBDOC/scode/page-not-found.html