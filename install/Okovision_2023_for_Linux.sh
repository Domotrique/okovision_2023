#!/bin/sh
sudo apt-get -y install mariadb-server
sudo mysql -e "CREATE USER 'okouser'@'localhost' IDENTIFIED BY 'okopass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'okouser'@'localhost' ;"
sudo apt-get -y install apache2
sudo systemctl enable apache2

sudo apt update
sudo apt-get -y install php8.2 php8.2-cli php8.2-common || apt-get -y install php php-cli php-common
sudo apt-get -y install php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-intl php8.2-zip || apt-get -y install php-mysql php-mbstring php-xml php-curl php-gd php-mbstring php-intl php-zip

cd /var/www/
sudo wget https://github.com/domotrique/okovision_2023/archive/master.zip
sudo unzip -q master.zip
[ -d "okovision" ] && mv okovision/ "$(date +"%y-%m-%d")_okovision"
sudo mv okovision_2023-master okovision
sudo rm master.zip
sudo chown www-data:www-data -R okovision/
sudo cp /var/www/okovision/install/099-okovision.conf /etc/apache2/sites-available/.
sudo a2ensite 099-okovision.conf
sudo a2dissite 000-default
sudo service apache2 reload

sudo crontab -l > crontab_new
if grep -R "okovision" "crontab_new" > 0
then
    echo "Crontab Ready"
else
    sudo echo "22 */1 * * * cd /var/www/okovision; /usr/bin/php -f cron.php" >> crontab_new
	sudo crontab crontab_new
fi
sudo rm crontab_new
echo "Install done! Please open localhost in your web browser."
